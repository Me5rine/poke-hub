<?php
// modules/pokemon/includes/pokemon-type-bulbapedia-importer.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Importe les données d'efficacité des types depuis Bulbapedia.
 *
 * @param string $type_name_en Nom du type en anglais (ex: "Grass", "Fire")
 * @return array|WP_Error Tableau avec les données ou WP_Error en cas d'erreur
 */
function poke_hub_pokemon_import_type_from_bulbapedia($type_name_en) {
    if (!function_exists('pokehub_get_table')) {
        return new WP_Error('missing_helper', 'pokehub_get_table() is required.');
    }

    // URL de la page Bulbapedia pour ce type
    $type_slug = ucfirst(strtolower(trim($type_name_en)));
    $url = 'https://bulbapedia.bulbagarden.net/wiki/' . urlencode($type_slug) . '_(type)';

    // Récupération de la page HTML avec retry
    if (function_exists('poke_hub_http_request_with_retry')) {
        $response = poke_hub_http_request_with_retry($url, [
            'timeout' => 35,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
    } else {
        $response = wp_remote_get($url, [
            'timeout' => 35,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
    }

    if (is_wp_error($response)) {
        // Log de l'erreur pour debugging
        if (function_exists('error_log')) {
            error_log(sprintf(
                '[PokeHub] Type Bulbapedia fetch error: %s - %s (URL: %s)',
                $response->get_error_code(),
                $response->get_error_message(),
                $url
            ));
        }
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        $error = new WP_Error('http_error', 'Bad response code: ' . $code, ['status_code' => $code]);
        if (function_exists('error_log')) {
            error_log(sprintf('[PokeHub] Type Bulbapedia HTTP error: %d (URL: %s)', $code, $url));
        }
        return $error;
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        $error = new WP_Error('empty_response', 'Empty response from Bulbapedia.', ['url' => $url]);
        if (function_exists('error_log')) {
            error_log(sprintf('[PokeHub] Type Bulbapedia empty body (URL: %s)', $url));
        }
        return $error;
    }

    // Parse le HTML avec DOMDocument
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $body);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // Recherche de la section "Battle properties"
    // Essaie plusieurs variantes du titre
    $battle_properties_heading = $xpath->query("//h2[contains(., 'Battle properties')]")->item(0);
    if (!$battle_properties_heading) {
        // Essaie avec span mw-headline
        $span = $xpath->query("//h2/span[@id='Battle_properties']")->item(0);
        if ($span && $span->parentNode) {
            $battle_properties_heading = $span->parentNode;
        }
    }
    if (!$battle_properties_heading) {
        // Essaie une recherche plus large (insensible à la casse)
        $battle_properties_heading = $xpath->query("//h2[contains(translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'battle properties')]")->item(0);
    }
    if (!$battle_properties_heading) {
        return new WP_Error('section_not_found', 'Battle properties section not found on Bulbapedia page.');
    }

    $result = [
        'offensive' => [
            'super_effective' => [],
            'not_very_effective' => [],
            'no_effect' => [],
        ],
        'defensive' => [
            'weak_to' => [],
            'resists' => [],
            'immune_to' => [],
        ],
    ];

    // Trouve les tableaux après le titre "Battle properties"
    // Recherche directe des tableaux avec les bonnes classes/headers
    $tables_found = [];
    
    // Essaie d'abord de trouver les tableaux avec la classe roundy
    $tables = $xpath->query("//table[contains(@class, 'roundy')]");
    foreach ($tables as $table) {
        $tables_found[] = $table;
    }
    
    // Si pas de résultats avec roundy, cherche tous les tableaux après le h2
    if (empty($tables_found)) {
        $current = $battle_properties_heading;
        $count = 0;
        while ($current && $count < 20) {
            $current = $current->nextSibling;
            $count++;
            if ($current && $current->nodeType === XML_ELEMENT_NODE && $current->nodeName === 'table') {
                $tables_found[] = $current;
            }
            // Limite la recherche aux 2 premiers tableaux
            if (count($tables_found) >= 2) {
                break;
            }
        }
    }
    
    // Parcourt les tableaux trouvés
    foreach ($tables_found as $table) {
        // Vérifie si c'est le tableau des propriétés offensives
        $th = $xpath->query(".//th[contains(., 'Offensive properties')]", $table)->item(0);
        if ($th) {
            // Parse le tableau offensif
            $rows = $xpath->query(".//tr", $table);
            foreach ($rows as $row) {
                $cells = $xpath->query(".//td", $row);
                if ($cells->length >= 3) {
                    // Colonne 1: Super effective (×2)
                    $super_effective_cell = $cells->item(0);
                    if ($super_effective_cell) {
                        $links = $xpath->query(".//a[@title]", $super_effective_cell);
                        foreach ($links as $link) {
                            $title = $link->getAttribute('title');
                            if (preg_match('/^(.+?)\s*\(type\)$/', $title, $matches)) {
                                $result['offensive']['super_effective'][] = trim($matches[1]);
                            }
                        }
                    }

                    // Colonne 2: Not very effective (×½)
                    $not_very_effective_cell = $cells->item(1);
                    if ($not_very_effective_cell) {
                        $links = $xpath->query(".//a[@title]", $not_very_effective_cell);
                        foreach ($links as $link) {
                            $title = $link->getAttribute('title');
                            if (preg_match('/^(.+?)\s*\(type\)$/', $title, $matches)) {
                                $result['offensive']['not_very_effective'][] = trim($matches[1]);
                            }
                        }
                    }

                    // Colonne 3: No effect (×0)
                    $no_effect_cell = $cells->item(2);
                    if ($no_effect_cell) {
                        $text = trim($no_effect_cell->textContent);
                        if (strtolower($text) !== 'none') {
                            $links = $xpath->query(".//a[@title]", $no_effect_cell);
                            foreach ($links as $link) {
                                $title = $link->getAttribute('title');
                                if (preg_match('/^(.+?)\s*\(type\)$/', $title, $matches)) {
                                    $result['offensive']['no_effect'][] = trim($matches[1]);
                                }
                            }
                        }
                    }
                }
            }
        }

        // Vérifie si c'est le tableau des propriétés défensives
        $th_defensive = $xpath->query(".//th[contains(., 'Defensive properties')]", $table)->item(0);
        if ($th_defensive) {
            // Parse le tableau défensif
            $rows = $xpath->query(".//tr", $table);
            foreach ($rows as $row) {
                $cells = $xpath->query(".//td", $row);
                if ($cells->length >= 3) {
                    // Colonne 1: Weak to (×2)
                    $weak_to_cell = $cells->item(0);
                    if ($weak_to_cell) {
                        $links = $xpath->query(".//a[@title]", $weak_to_cell);
                        foreach ($links as $link) {
                            $title = $link->getAttribute('title');
                            if (preg_match('/^(.+?)\s*\(type\)$/', $title, $matches)) {
                                $result['defensive']['weak_to'][] = trim($matches[1]);
                            }
                        }
                    }

                    // Colonne 2: Resists (×½)
                    $resists_cell = $cells->item(1);
                    if ($resists_cell) {
                        $links = $xpath->query(".//a[@title]", $resists_cell);
                        foreach ($links as $link) {
                            $title = $link->getAttribute('title');
                            if (preg_match('/^(.+?)\s*\(type\)$/', $title, $matches)) {
                                $result['defensive']['resists'][] = trim($matches[1]);
                            }
                        }
                    }

                    // Colonne 3: Immune to (×0)
                    $immune_to_cell = $cells->item(2);
                    if ($immune_to_cell) {
                        $text = trim($immune_to_cell->textContent);
                        if (strtolower($text) !== 'none') {
                            $links = $xpath->query(".//a[@title]", $immune_to_cell);
                            foreach ($links as $link) {
                                $title = $link->getAttribute('title');
                                if (preg_match('/^(.+?)\s*\(type\)$/', $title, $matches)) {
                                    $result['defensive']['immune_to'][] = trim($matches[1]);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    return $result;
}

/**
 * Applique les données importées depuis Bulbapedia à un type dans la base de données.
 * Les données Bulbapedia correspondent aux jeux principaux (core_series).
 *
 * @param int   $type_id ID du type dans la base de données
 * @param array $bulbapedia_data Données importées depuis Bulbapedia
 * @param string $game_key 'core_series' (défaut) ou 'pokemon_go'
 * @return bool|WP_Error True en cas de succès, WP_Error en cas d'erreur
 */
function poke_hub_pokemon_apply_bulbapedia_type_data($type_id, $bulbapedia_data, $game_key = 'core_series') {
    if (!function_exists('pokehub_get_table')) {
        return new WP_Error('missing_helper', 'pokehub_get_table() is required.');
    }

    global $wpdb;

    $types_table = pokehub_get_table('pokemon_types');
    if (!$types_table) {
        return new WP_Error('table_not_found', 'Types table not found.');
    }

    $type_id = (int) $type_id;
    if ($type_id <= 0) {
        return new WP_Error('invalid_id', 'Invalid type ID.');
    }

    // Fonction helper pour convertir un nom de type en ID
    $get_type_id_by_name = function($type_name) use ($wpdb, $types_table) {
        $type_name = trim($type_name);
        if (empty($type_name)) {
            return 0;
        }

        // Essaie d'abord avec name_en
        $id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$types_table} WHERE name_en = %s OR slug = %s LIMIT 1",
                $type_name,
                sanitize_title($type_name)
            )
        );

        if ($id > 0) {
            return $id;
        }

        // Essaie avec name_fr
        $id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$types_table} WHERE name_fr = %s LIMIT 1",
                $type_name
            )
        );

        return $id;
    };

    // Applique les données défensives
    if (isset($bulbapedia_data['defensive'])) {
        // Weak to (×2)
        if (isset($bulbapedia_data['defensive']['weak_to'])) {
            $weakness_ids = [];
            foreach ($bulbapedia_data['defensive']['weak_to'] as $type_name) {
                $tid = $get_type_id_by_name($type_name);
                if ($tid > 0) {
                    $weakness_ids[] = $tid;
                }
            }
            // Ajoute le type lui-même s'il n'est pas déjà présent
            if (!in_array($type_id, $weakness_ids, true)) {
                // Un type peut être faible contre lui-même dans certains cas (rare mais possible)
                // On ne l'ajoute pas automatiquement ici, seulement si présent dans Bulbapedia
            }
            if (!empty($weakness_ids)) {
                poke_hub_pokemon_sync_type_weaknesses($type_id, $weakness_ids, $game_key);
            }
        }

        // Resists (×½) - On respecte uniquement les données Bulbapedia
        if (isset($bulbapedia_data['defensive']['resists'])) {
            $resistance_ids = [];
            foreach ($bulbapedia_data['defensive']['resists'] as $type_name) {
                $tid = $get_type_id_by_name($type_name);
                if ($tid > 0) {
                    $resistance_ids[] = $tid;
                }
            }
            if (!empty($resistance_ids)) {
                poke_hub_pokemon_sync_type_resistances($type_id, $resistance_ids, $game_key);
            }
        }

        // Immune to (×0)
        if (isset($bulbapedia_data['defensive']['immune_to'])) {
            $immune_ids = [];
            foreach ($bulbapedia_data['defensive']['immune_to'] as $type_name) {
                $tid = $get_type_id_by_name($type_name);
                if ($tid > 0) {
                    $immune_ids[] = $tid;
                }
            }
            if (!empty($immune_ids)) {
                poke_hub_pokemon_sync_type_immunities($type_id, $immune_ids, $game_key);
            }
        }
    }

    // Applique les données offensives
    if (isset($bulbapedia_data['offensive'])) {
        // Récupère d'abord les types "no effect" pour les exclure de "not very effective"
        $no_effect_type_names = isset($bulbapedia_data['offensive']['no_effect']) 
            ? $bulbapedia_data['offensive']['no_effect'] 
            : [];
        $no_effect_ids_map = [];
        foreach ($no_effect_type_names as $type_name) {
            $tid = $get_type_id_by_name($type_name);
            if ($tid > 0) {
                $no_effect_ids_map[$tid] = true;
            }
        }

        // Super effective (×2) - On respecte uniquement les données Bulbapedia
        if (isset($bulbapedia_data['offensive']['super_effective'])) {
            $super_effective_ids = [];
            foreach ($bulbapedia_data['offensive']['super_effective'] as $type_name) {
                $tid = $get_type_id_by_name($type_name);
                if ($tid > 0) {
                    $super_effective_ids[] = $tid;
                }
            }
            if (!empty($super_effective_ids)) {
                poke_hub_pokemon_sync_type_offensive_super_effective($type_id, $super_effective_ids, $game_key);
            }
        }

        // Not very effective (×½)
        // On respecte exactement les données Bulbapedia et on exclut les types "no effect"
        if (isset($bulbapedia_data['offensive']['not_very_effective'])) {
            $not_very_effective_ids = [];
            foreach ($bulbapedia_data['offensive']['not_very_effective'] as $type_name) {
                $tid = $get_type_id_by_name($type_name);
                // Exclut les types qui sont dans "no effect"
                if ($tid > 0 && !isset($no_effect_ids_map[$tid])) {
                    $not_very_effective_ids[] = $tid;
                }
            }
            if (!empty($not_very_effective_ids)) {
                poke_hub_pokemon_sync_type_offensive_not_very_effective($type_id, $not_very_effective_ids, $game_key);
            }
        }

        // No effect (×0)
        if (isset($bulbapedia_data['offensive']['no_effect'])) {
            $no_effect_ids = [];
            foreach ($bulbapedia_data['offensive']['no_effect'] as $type_name) {
                $tid = $get_type_id_by_name($type_name);
                if ($tid > 0) {
                    $no_effect_ids[] = $tid;
                }
            }
            if (!empty($no_effect_ids)) {
                poke_hub_pokemon_sync_type_offensive_no_effect($type_id, $no_effect_ids, $game_key);
            }
        }
    }

    return true;
}

/**
 * Importe automatiquement les données de tous les types depuis Bulbapedia.
 * Les données Bulbapedia correspondent aux jeux principaux (core_series).
 * 
 * Pour Pokémon GO, utiliser poke_hub_pokemon_import_all_types_for_pokemon_go().
 *
 * @param string $game_key 'core_series' (défaut) ou 'pokemon_go'
 * @return array Statistiques de l'import
 */
function poke_hub_pokemon_import_all_types_from_bulbapedia($game_key = 'core_series') {
    if (!function_exists('pokehub_get_table')) {
        return ['error' => 'pokehub_get_table() is required.'];
    }

    global $wpdb;

    $types_table = pokehub_get_table('pokemon_types');
    if (!$types_table) {
        return ['error' => 'Types table not found.'];
    }

    // Récupère tous les types
    $types = $wpdb->get_results("SELECT id, name_en, name_fr, slug FROM {$types_table} ORDER BY id ASC");

    $stats = [
        'total' => count($types),
        'success' => 0,
        'errors' => [],
    ];

    foreach ($types as $type) {
        $type_name = !empty($type->name_en) ? $type->name_en : $type->name_fr;
        if (empty($type_name)) {
            continue;
        }

        // Import depuis Bulbapedia
        $bulbapedia_data = poke_hub_pokemon_import_type_from_bulbapedia($type_name);
        if (is_wp_error($bulbapedia_data)) {
            $stats['errors'][] = [
                'type' => $type_name,
                'error' => $bulbapedia_data->get_error_message(),
            ];
            continue;
        }

        // Applique les données
        $result = poke_hub_pokemon_apply_bulbapedia_type_data($type->id, $bulbapedia_data, $game_key);
        if (is_wp_error($result)) {
            $stats['errors'][] = [
                'type' => $type_name,
                'error' => $result->get_error_message(),
            ];
            continue;
        }

        $stats['success']++;

        // Petite pause pour ne pas surcharger Bulbapedia
        sleep(1);
    }

    return $stats;
}

/**
 * Importe les données de types spécifiques à Pokémon GO.
 * 
 * Dans Pokémon GO, les multiplicateurs sont différents :
 * - Super efficace : ×1.6 (au lieu de ×2)
 * - Peu efficace : ×0.625 (au lieu de ×0.5)
 * - Sans effet : ×0.39 (au lieu de ×0)
 * 
 * Les relations sont généralement les mêmes que les jeux principaux,
 * sauf quelques exceptions :
 * - Normal vs Ghost : ×0.39 au lieu de ×0
 * - Ghost vs Normal : ×0.39 au lieu de ×0
 * - Ground vs Flying : ×0.39 au lieu de ×0
 * - Electric vs Ground : ×0.39 au lieu de ×0
 *
 * @return array Statistiques de l'import
 */
function poke_hub_pokemon_import_all_types_for_pokemon_go() {
    if (!function_exists('pokehub_get_table')) {
        return ['error' => 'pokehub_get_table() is required.'];
    }

    global $wpdb;

    $types_table = pokehub_get_table('pokemon_types');
    if (!$types_table) {
        return ['error' => 'Types table not found.'];
    }

    // Récupère tous les types
    $types = $wpdb->get_results("SELECT id, name_en, name_fr, slug FROM {$types_table} ORDER BY id ASC");

    $stats = [
        'total' => count($types),
        'success' => 0,
        'errors' => [],
    ];

    // Mapping des noms de types pour la conversion
    $type_name_mapping = [
        'Normal' => 'normal',
        'Grass' => 'grass',
        'Fire' => 'fire',
        'Water' => 'water',
        'Electric' => 'electric',
        'Ice' => 'ice',
        'Fighting' => 'fighting',
        'Poison' => 'poison',
        'Ground' => 'ground',
        'Flying' => 'flying',
        'Psychic' => 'psychic',
        'Bug' => 'bug',
        'Rock' => 'rock',
        'Ghost' => 'ghost',
        'Dragon' => 'dragon',
        'Dark' => 'dark',
        'Steel' => 'steel',
        'Fairy' => 'fairy',
    ];

    // Fonction helper pour convertir un nom de type en ID
    $get_type_id_by_name = function($type_name) use ($wpdb, $types_table) {
        $type_name = trim($type_name);
        if (empty($type_name)) {
            return 0;
        }

        // Essaie d'abord avec name_en
        $id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$types_table} WHERE name_en = %s OR slug = %s LIMIT 1",
                $type_name,
                sanitize_title($type_name)
            )
        );

        if ($id > 0) {
            return $id;
        }

        // Essaie avec name_fr
        $id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$types_table} WHERE name_fr = %s LIMIT 1",
                $type_name
            )
        );

        return $id;
    };

    foreach ($types as $type) {
        $type_name = !empty($type->name_en) ? $type->name_en : $type->name_fr;
        if (empty($type_name)) {
            continue;
        }

        // Import depuis Bulbapedia (core_series)
        $bulbapedia_data = poke_hub_pokemon_import_type_from_bulbapedia($type_name);
        if (is_wp_error($bulbapedia_data)) {
            $stats['errors'][] = [
                'type' => $type_name,
                'error' => $bulbapedia_data->get_error_message(),
            ];
            // Log l'erreur pour debug
            error_log(sprintf(
                'Poke Hub: Erreur import type %s depuis Bulbapedia: %s',
                $type_name,
                $bulbapedia_data->get_error_message()
            ));
            continue;
        }
        
        // Vérifie que des données ont été récupérées
        $has_data = false;
        if (isset($bulbapedia_data['offensive'])) {
            $has_data = !empty($bulbapedia_data['offensive']['super_effective']) 
                     || !empty($bulbapedia_data['offensive']['not_very_effective'])
                     || !empty($bulbapedia_data['offensive']['no_effect']);
        }
        if (!$has_data && isset($bulbapedia_data['defensive'])) {
            $has_data = !empty($bulbapedia_data['defensive']['weak_to'])
                     || !empty($bulbapedia_data['defensive']['resists'])
                     || !empty($bulbapedia_data['defensive']['immune_to']);
        }
        
        if (!$has_data) {
            $stats['errors'][] = [
                'type' => $type_name,
                'error' => 'Aucune donnée récupérée depuis Bulbapedia',
            ];
            error_log(sprintf(
                'Poke Hub: Aucune donnée récupérée pour le type %s depuis Bulbapedia',
                $type_name
            ));
            continue;
        }

        // Convertir les données pour Pokémon GO (copie profonde)
        // IMPORTANT: Exclure les types "no_effect" de "not_very_effective"
        $no_effect_names = isset($bulbapedia_data['offensive']['no_effect']) 
            ? array_map('trim', $bulbapedia_data['offensive']['no_effect']) 
            : [];
        
        $not_very_effective_filtered = [];
        if (isset($bulbapedia_data['offensive']['not_very_effective'])) {
            foreach ($bulbapedia_data['offensive']['not_very_effective'] as $type_name) {
                $type_name_trimmed = trim($type_name);
                // Exclut les types qui sont dans "no_effect"
                if (!in_array($type_name_trimmed, $no_effect_names, true)) {
                    $not_very_effective_filtered[] = $type_name_trimmed;
                }
            }
        }
        
        $pokemon_go_data = [
            'offensive' => [
                'super_effective' => isset($bulbapedia_data['offensive']['super_effective']) 
                    ? array_values($bulbapedia_data['offensive']['super_effective']) 
                    : [],
                'not_very_effective' => $not_very_effective_filtered,
                'no_effect' => isset($bulbapedia_data['offensive']['no_effect']) 
                    ? array_values($bulbapedia_data['offensive']['no_effect']) 
                    : [],
            ],
            'defensive' => [
                'weak_to' => isset($bulbapedia_data['defensive']['weak_to']) 
                    ? array_values($bulbapedia_data['defensive']['weak_to']) 
                    : [],
                'resists' => isset($bulbapedia_data['defensive']['resists']) 
                    ? array_values($bulbapedia_data['defensive']['resists']) 
                    : [],
                'immune_to' => isset($bulbapedia_data['defensive']['immune_to']) 
                    ? array_values($bulbapedia_data['defensive']['immune_to']) 
                    : [],
            ],
        ];

        // Exceptions spécifiques à Pokémon GO
        $type_slug = sanitize_title($type_name);
        
        // Normal vs Ghost : ×0.39 au lieu de ×0
        if ($type_slug === 'normal') {
            // Normal n'est pas efficace contre Ghost → passe de no_effect à not_very_effective
            if (isset($pokemon_go_data['offensive']['no_effect'])) {
                $ghost_id = $get_type_id_by_name('Ghost');
                if ($ghost_id > 0) {
                    $key = array_search('Ghost', $pokemon_go_data['offensive']['no_effect']);
                    if ($key !== false) {
                        unset($pokemon_go_data['offensive']['no_effect'][$key]);
                        $pokemon_go_data['offensive']['no_effect'] = array_values($pokemon_go_data['offensive']['no_effect']);
                        if (!in_array('Ghost', $pokemon_go_data['offensive']['not_very_effective'])) {
                            $pokemon_go_data['offensive']['not_very_effective'][] = 'Ghost';
                        }
                    }
                }
            }
        }

        // Ghost vs Normal : ×0.39 au lieu de ×0
        if ($type_slug === 'ghost') {
            // Ghost n'est pas efficace contre Normal → passe de no_effect à not_very_effective
            if (isset($pokemon_go_data['offensive']['no_effect'])) {
                $key = array_search('Normal', $pokemon_go_data['offensive']['no_effect']);
                if ($key !== false) {
                    unset($pokemon_go_data['offensive']['no_effect'][$key]);
                    $pokemon_go_data['offensive']['no_effect'] = array_values($pokemon_go_data['offensive']['no_effect']);
                    if (!in_array('Normal', $pokemon_go_data['offensive']['not_very_effective'])) {
                        $pokemon_go_data['offensive']['not_very_effective'][] = 'Normal';
                    }
                }
            }
        }

        // Ground vs Flying : ×0.39 au lieu de ×0
        if ($type_slug === 'ground') {
            // Ground n'est pas efficace contre Flying → passe de no_effect à not_very_effective
            if (isset($pokemon_go_data['offensive']['no_effect'])) {
                $key = array_search('Flying', $pokemon_go_data['offensive']['no_effect']);
                if ($key !== false) {
                    unset($pokemon_go_data['offensive']['no_effect'][$key]);
                    $pokemon_go_data['offensive']['no_effect'] = array_values($pokemon_go_data['offensive']['no_effect']);
                    if (!in_array('Flying', $pokemon_go_data['offensive']['not_very_effective'])) {
                        $pokemon_go_data['offensive']['not_very_effective'][] = 'Flying';
                    }
                }
            }
        }

        // Electric vs Ground : ×0.39 au lieu de ×0
        if ($type_slug === 'electric') {
            // Electric n'est pas efficace contre Ground → passe de no_effect à not_very_effective
            if (isset($pokemon_go_data['offensive']['no_effect'])) {
                $key = array_search('Ground', $pokemon_go_data['offensive']['no_effect']);
                if ($key !== false) {
                    unset($pokemon_go_data['offensive']['no_effect'][$key]);
                    $pokemon_go_data['offensive']['no_effect'] = array_values($pokemon_go_data['offensive']['no_effect']);
                    if (!in_array('Ground', $pokemon_go_data['offensive']['not_very_effective'])) {
                        $pokemon_go_data['offensive']['not_very_effective'][] = 'Ground';
                    }
                }
            }
        }

        // Applique les données pour Pokémon GO
        $result = poke_hub_pokemon_apply_bulbapedia_type_data($type->id, $pokemon_go_data, 'pokemon_go');
        if (is_wp_error($result)) {
            $stats['errors'][] = [
                'type' => $type_name,
                'error' => $result->get_error_message(),
            ];
            continue;
        }

        $stats['success']++;

        // Petite pause pour ne pas surcharger Bulbapedia
        sleep(1);
    }

    return $stats;
}

