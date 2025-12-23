<?php
// File: modules/pokemon/includes/pokemon-translation-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère une valeur de traduction depuis extra JSON.
 */
function poke_hub_tr_get_extra_name($extra_json, $lang) {
    if (empty($extra_json)) {
        return '';
    }
    $decoded = json_decode((string) $extra_json, true);
    if (!is_array($decoded)) {
        return '';
    }
    if (empty($decoded['names']) || !is_array($decoded['names'])) {
        return '';
    }
    return trim((string) ($decoded['names'][$lang] ?? ''));
}

/**
 * Détermine si une traduction existe (non vide), même si identique à l'anglais.
 * - FR : on regarde d'abord la colonne name_fr, sinon extra.names.fr
 * - autres langues : extra.names.xx
 */
function poke_hub_tr_has_translation($row, $lang) {
    $lang = (string) $lang;

    if ($lang === 'fr') {
        $name_fr = trim((string) ($row->name_fr ?? ''));
        if ($name_fr !== '') {
            return true;
        }
        $extra_fr = poke_hub_tr_get_extra_name($row->extra ?? '', 'fr');
        return $extra_fr !== '';
    }

    $extra_val = poke_hub_tr_get_extra_name($row->extra ?? '', $lang);
    return $extra_val !== '';
}

/**
 * Détecte les traductions manquantes pour les Pokémon.
 * 
 * @param array $filters Filtres optionnels ['lang' => 'fr', 'force_check' => false]
 * @return array Liste des traductions manquantes
 */
function poke_hub_pokemon_get_missing_translations($filters = []) {
    global $wpdb;

    if (!function_exists('pokehub_get_table')) {
        return [];
    }

    $table = pokehub_get_table('pokemon');
    if (!$table) {
        return [];
    }

    $lang = isset($filters['lang']) ? sanitize_text_field($filters['lang']) : '';
    $force_check = isset($filters['force_check']) && $filters['force_check'];

    $allowed_langs = ['fr', 'de', 'it', 'es', 'ja', 'ko'];
    if (!empty($lang) && !in_array($lang, $allowed_langs, true)) {
        return [];
    }

    $languages_to_check = !empty($lang) ? [$lang] : $allowed_langs;

    $missing = [];

    // Récupérer tous les Pokémon avec name_en
    $pokemon_list = $wpdb->get_results(
        "SELECT id, dex_number, name_en, name_fr, slug, extra
         FROM {$table}
         WHERE name_en != ''
         ORDER BY dex_number ASC, id ASC"
    );

    foreach ($pokemon_list as $pokemon) {
        $name_en = trim($pokemon->name_en);
        if (empty($name_en)) {
            continue;
        }

        // Récupérer les traductions existantes depuis extra
        $extra = [];
        if (!empty($pokemon->extra)) {
            $decoded = json_decode($pokemon->extra, true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }

        $names = $extra['names'] ?? [];
        $names['en'] = $name_en;
        if (!empty($pokemon->name_fr)) {
            $names['fr'] = $pokemon->name_fr;
        }

        // Vérifier chaque langue
        foreach ($languages_to_check as $check_lang) {
            $has_translation = poke_hub_tr_has_translation($pokemon, $check_lang);

            // Missing uniquement si vraiment vide
            if (!$has_translation) {
                if (!isset($missing[$check_lang])) {
                    $missing[$check_lang] = [];
                }

                $missing[$check_lang][] = [
                    'id' => (int) $pokemon->id,
                    'dex_number' => (int) $pokemon->dex_number,
                    'name_en' => $name_en,
                    'name_fr' => $pokemon->name_fr,
                    'slug' => $pokemon->slug,
                    'type' => 'pokemon',
                    'extra' => $pokemon->extra, // utile pour l'affichage du current_translation
                ];
                continue;
            }

            /**
             * Optionnel: si force_check=true, tu peux considérer "à compléter"
             * quand la clé extra.names[lang] est vide, même si la trad existe ailleurs.
             * Perso je te conseille de NE PAS le mélanger à "missing".
             * Donc ici: on ne fait rien.
             */
        }
    }

    return $missing;
}

/**
 * Détecte les traductions manquantes pour les attaques.
 * 
 * @param array $filters Filtres optionnels ['lang' => 'fr', 'force_check' => false]
 * @return array Liste des traductions manquantes
 */
function poke_hub_attacks_get_missing_translations($filters = []) {
    global $wpdb;

    if (!function_exists('pokehub_get_table')) {
        return [];
    }

    $table = pokehub_get_table('attacks');
    if (!$table) {
        return [];
    }

    $lang = isset($filters['lang']) ? sanitize_text_field($filters['lang']) : '';
    $force_check = isset($filters['force_check']) && $filters['force_check'];

    $allowed_langs = ['fr', 'de', 'it', 'es', 'ja', 'ko'];
    if (!empty($lang) && !in_array($lang, $allowed_langs, true)) {
        return [];
    }

    $languages_to_check = !empty($lang) ? [$lang] : $allowed_langs;

    $missing = [];

    // Récupérer toutes les attaques avec name_en
    $attacks_list = $wpdb->get_results(
        "SELECT id, name_en, name_fr, slug, extra
         FROM {$table}
         WHERE name_en != ''
         ORDER BY id ASC"
    );

    foreach ($attacks_list as $attack) {
        $name_en = trim($attack->name_en);
        if (empty($name_en)) {
            continue;
        }

        // Récupérer les traductions existantes depuis extra
        $extra = [];
        if (!empty($attack->extra)) {
            $decoded = json_decode($attack->extra, true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }

        $names = $extra['names'] ?? [];
        $names['en'] = $name_en;
        if (!empty($attack->name_fr)) {
            $names['fr'] = $attack->name_fr;
        }

        // Vérifier chaque langue
        foreach ($languages_to_check as $check_lang) {
            $has_translation = poke_hub_tr_has_translation($attack, $check_lang);
        
            if (!$has_translation) {
                if (!isset($missing[$check_lang])) {
                    $missing[$check_lang] = [];
                }
        
                $missing[$check_lang][] = [
                    'id' => (int) $attack->id,
                    'name_en' => $name_en,
                    'name_fr' => $attack->name_fr,
                    'slug' => $attack->slug,
                    'type' => 'attack',
                    'extra' => $attack->extra,
                ];
            }
        }        
    }

    return $missing;
}

/**
 * Détecte les traductions manquantes pour les types.
 * 
 * @param array $filters Filtres optionnels ['lang' => 'fr', 'force_check' => false]
 * @return array Liste des traductions manquantes
 */
function poke_hub_types_get_missing_translations($filters = []) {
    global $wpdb;

    if (!function_exists('pokehub_get_table')) {
        return [];
    }

    $table = pokehub_get_table('pokemon_types');
    if (!$table) {
        return [];
    }

    $lang = isset($filters['lang']) ? sanitize_text_field($filters['lang']) : '';
    $force_check = isset($filters['force_check']) && $filters['force_check'];

    $allowed_langs = ['fr', 'de', 'it', 'es', 'ja', 'ko'];
    if (!empty($lang) && !in_array($lang, $allowed_langs, true)) {
        return [];
    }

    $languages_to_check = !empty($lang) ? [$lang] : $allowed_langs;

    $missing = [];

    // Récupérer tous les types avec name_en
    $types_list = $wpdb->get_results(
        "SELECT id, name_en, name_fr, slug, extra
         FROM {$table}
         WHERE name_en != ''
         ORDER BY id ASC"
    );

    foreach ($types_list as $type) {
        $name_en = trim($type->name_en);
        if (empty($name_en)) {
            continue;
        }

        // Récupérer les traductions existantes depuis extra
        $extra = [];
        if (!empty($type->extra)) {
            $decoded = json_decode($type->extra, true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }

        $names = $extra['names'] ?? [];
        $names['en'] = $name_en;
        if (!empty($type->name_fr)) {
            $names['fr'] = $type->name_fr;
        }

        // Vérifier chaque langue
        foreach ($languages_to_check as $check_lang) {
            $has_translation = poke_hub_tr_has_translation($type, $check_lang);
        
            if (!$has_translation) {
                if (!isset($missing[$check_lang])) {
                    $missing[$check_lang] = [];
                }
        
                $missing[$check_lang][] = [
                    'id' => (int) $type->id,
                    'name_en' => $name_en,
                    'name_fr' => $type->name_fr,
                    'slug' => $type->slug,
                    'type' => 'type',
                    'extra' => $type->extra,
                ];
            }
        }        
    }

    return $missing;
}

/**
 * Récupère toutes les traductions manquantes (Pokémon, attaques, types).
 * 
 * @param array $filters Filtres optionnels
 * @return array Structure: ['pokemon' => [...], 'attacks' => [...], 'types' => [...]]
 */
function poke_hub_get_all_missing_translations($filters = []) {
    return [
        'pokemon' => poke_hub_pokemon_get_missing_translations($filters),
        'attacks' => poke_hub_attacks_get_missing_translations($filters),
        'types' => poke_hub_types_get_missing_translations($filters),
    ];
}








