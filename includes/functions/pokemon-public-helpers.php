<?php
// File: includes/functions/pokemon-public-helpers.php
// Helpers publics pour les données Pokémon (disponibles même si le module Pokémon n'est pas actif)
// Ces fonctions sont utilisées par d'autres modules (ex: user-profiles) et doivent être disponibles
// dès l'activation du plugin.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Scatterbug/Vivillon patterns from database.
 * Only returns patterns marked as regional (extra->regional->is_regional = true).
 * Patterns are stored as form variants for Scatterbug (dex_number 664) and Vivillon (dex_number 666).
 * 
 * This function is available even if the Pokémon module is not active, as it's used by
 * the user-profiles module to display Scatterbug pattern selection.
 *
 * @return array Associative array form_slug => label (French or English name)
 */
function poke_hub_pokemon_get_scatterbug_patterns(): array {
    // Cache avec transient (12 heures) pour éviter les requêtes DB répétées
    $cache_key = 'poke_hub_scatterbug_patterns';
    $cached = get_transient($cache_key);
    if ($cached !== false && is_array($cached)) {
        return $cached;
    }
    
    if (!function_exists('pokehub_get_table')) {
        return [];
    }

    global $wpdb;
    
    // Sécurité : vérifier que $wpdb est disponible
    if (!isset($wpdb) || !is_object($wpdb)) {
        return [];
    }

    // Vérifier si un préfixe Pokémon distant est configuré
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);
    
    // Si un préfixe distant est configuré ET différent du préfixe local, utiliser les tables distantes
    // On vérifie aussi que poke_hub_pokemon_get_table_prefix() retourne bien un préfixe différent
    $use_remote = false;
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        // Vérifier que la fonction retourne bien le préfixe distant
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            // Si le préfixe retourné est différent du préfixe local, on utilise les tables distantes
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote = true;
            }
        }
    }
    
    if ($use_remote) {
        $pokemon_table = pokehub_get_table('remote_pokemon');
        $form_variants_table = pokehub_get_table('remote_pokemon_form_variants');
    } else {
        // Sinon, utiliser les tables locales
        $pokemon_table = pokehub_get_table('pokemon');
        $form_variants_table = pokehub_get_table('pokemon_form_variants');
    }

    // Vérifier que les noms de tables sont valides
    if (empty($pokemon_table) || empty($form_variants_table)) {
        return [];
    }

    // Note: On ne vérifie pas l'existence des tables distantes avec pokehub_table_exists()
    // car cette fonction ne peut vérifier que les tables locales (SHOW TABLES ne fonctionne pas pour les tables distantes)
    // On laisse la requête SQL échouer silencieusement si les tables n'existent pas

    // Get form variants for Scatterbug (664) and Vivillon (666)
    // Only those marked as regional (extra->regional->is_regional = true)
    // Utiliser esc_sql pour sécuriser les noms de tables
    $pokemon_table_escaped = esc_sql($pokemon_table);
    $form_variants_table_escaped = esc_sql($form_variants_table);
    
    $sql = "SELECT DISTINCT 
                fv.form_slug,
                fv.label,
                fv.extra AS form_variant_extra,
                COALESCE(p.name_fr, p.name_en, '') AS pokemon_name
            FROM `{$pokemon_table_escaped}` AS p
            INNER JOIN `{$form_variants_table_escaped}` AS fv ON p.form_variant_id = fv.id
            WHERE p.dex_number IN (664, 666)
            AND p.form_variant_id > 0
            AND p.extra LIKE '%\"regional\":{\"is_regional\":true%'
            ORDER BY fv.label ASC, fv.form_slug ASC";
    
    $patterns = $wpdb->get_results($sql);
    
    // Vérifier s'il y a eu une erreur SQL
    if ($wpdb->last_error) {
        // En cas d'erreur SQL, retourner un tableau vide
        // Le fallback dans poke_hub_get_scatterbug_patterns() utilisera la liste par défaut
        return [];
    }
    
    // Si $patterns est false (erreur) ou null, retourner un tableau vide
    if ($patterns === false || $patterns === null) {
        return [];
    }

    $result = [];
    foreach ($patterns as $pattern) {
        // Vérifier que $pattern est un objet
        if (!is_object($pattern)) {
            continue;
        }
        
        $form_slug = (string) ($pattern->form_slug ?? '');
        if (empty($form_slug)) {
            continue;
        }

        // Mapping des traductions WordPress pour les patterns courants
        $pattern_translations = [
            'archipelago' => __('Archipel', 'poke-hub'),
            'continental' => __('Continental', 'poke-hub'),
            'elegant' => __('Élégant', 'poke-hub'),
            'garden' => __('Jardin', 'poke-hub'),
            'high-plains' => __('Hautes Plaines', 'poke-hub'),
            'icy-snow' => __('Neige Glacée', 'poke-hub'),
            'jungle' => __('Jungle', 'poke-hub'),
            'marine' => __('Marin', 'poke-hub'),
            'meadow' => __('Prairie', 'poke-hub'),
            'modern' => __('Moderne', 'poke-hub'),
            'monsoon' => __('Mousson', 'poke-hub'),
            'ocean' => __('Océan', 'poke-hub'),
            'polar' => __('Polaire', 'poke-hub'),
            'river' => __('Rivière', 'poke-hub'),
            'sandstorm' => __('Tempête de Sable', 'poke-hub'),
            'savanna' => __('Savane', 'poke-hub'),
            'sun' => __('Soleil', 'poke-hub'),
            'tundra' => __('Toundra', 'poke-hub'),
        ];

        $final_label = null;

        // Priorité 1: Vérifier si une traduction WordPress existe (via __())
        if (isset($pattern_translations[$form_slug])) {
            $final_label = $pattern_translations[$form_slug];
        }

        // Priorité 2: Si pas de traduction WordPress, vérifier si extra->names->fr existe
        if (empty($final_label) && !empty($pattern->form_variant_extra)) {
            $extra = json_decode($pattern->form_variant_extra, true);
            if (is_array($extra) && !empty($extra['names']['fr'])) {
                $final_label = trim((string) $extra['names']['fr']);
            }
        }

        // Priorité 3: Sinon, laisser en anglais (utiliser le label de la DB ou form_slug formaté)
        if (empty($final_label)) {
            $label = (string) ($pattern->label ?? '');
            if (empty($label)) {
                // Fallback: générer un label à partir du form_slug (en anglais)
                $final_label = ucwords(str_replace(['-', '_'], ' ', $form_slug));
            } else {
                // Utiliser le label de la DB (probablement en anglais)
                $final_label = $label;
            }
        }

        $result[$form_slug] = $final_label;
    }

    // Cache pour 12 heures (43200 secondes)
    if (!empty($result)) {
        set_transient($cache_key, $result, 12 * HOUR_IN_SECONDS);
    }

    // Si aucun pattern trouvé, retourner un tableau vide
    // Le fallback dans poke_hub_get_scatterbug_patterns() utilisera la liste par défaut
    return $result;
}

/**
 * ============================================================================
 * HELPERS POUR LES DONNÉES POKÉMON (ID, CP, Types, etc.)
 * Ces fonctions sont disponibles même si le module Pokémon n'est pas actif,
 * car elles sont utilisées par d'autres modules (ex: events, user-profiles).
 * ============================================================================
 */

/**
 * Récupère les données d'un Pokémon par son ID (avec gestion des préfixes distants)
 * 
 * @param int $pokemon_id ID du Pokémon
 * @return array|null Array avec id, dex_number, slug, name_fr, name_en, form_variant_id, form, name
 */
function pokehub_get_pokemon_data_by_id(int $pokemon_id): ?array {
    if (!function_exists('pokehub_get_table')) {
        return null;
    }

    global $wpdb;
    
    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return null;
    }

    // Sécurité : vérifier que $wpdb est disponible
    if (!isset($wpdb) || !is_object($wpdb)) {
        return null;
    }

    // Vérifier si un préfixe Pokémon distant est configuré
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);
    
    // Déterminer quelle table utiliser (locale ou distante)
    $use_remote = false;
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote = true;
            }
        }
    }
    
    if ($use_remote) {
        $pokemon_table = pokehub_get_table('remote_pokemon');
        $form_variants_table = pokehub_get_table('remote_pokemon_form_variants');
    } else {
        $pokemon_table = pokehub_get_table('pokemon');
        $form_variants_table = pokehub_get_table('pokemon_form_variants');
    }
    
    if (!$pokemon_table || !$form_variants_table) {
        return null;
    }
    
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT p.id, 
                    p.dex_number, 
                    p.slug,
                    p.name_fr,
                    p.name_en,
                    p.form_variant_id,
                    COALESCE(fv.label, fv.form_slug, '') AS form
             FROM {$pokemon_table} p
             LEFT JOIN {$form_variants_table} fv ON p.form_variant_id = fv.id
             WHERE p.id = %d
             LIMIT 1",
            $pokemon_id
        ),
        ARRAY_A
    );
    
    if (!$row) {
        return null;
    }
    
    // Construire le nom au format "nom-fr (nom-anglais)" si les deux sont disponibles
    $name_fr = $row['name_fr'] ?? '';
    $name_en = $row['name_en'] ?? '';
    $name = $name_fr;
    if ($name_fr !== '' && $name_en !== '' && $name_fr !== $name_en) {
        $name = $name_fr . ' (' . $name_en . ')';
    } elseif ($name_fr === '' && $name_en !== '') {
        $name = $name_en;
    }
    
    $row['name'] = $name;
    return $row;
}

/**
 * Récupère les CP d'un Pokémon pour un niveau donné (depuis extra->games->pokemon_go->cp_sets)
 * 
 * @param int $pokemon_id ID du Pokémon
 * @param int $level Niveau (par défaut 15 pour les quêtes)
 * @return array|null ['max_cp' => int, 'min_cp' => int] ou null si non trouvé
 */
function pokehub_get_pokemon_cp_for_level(int $pokemon_id, int $level = 15): ?array {
    if (!function_exists('pokehub_get_table')) {
        return null;
    }

    global $wpdb;
    
    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return null;
    }

    // Sécurité : vérifier que $wpdb est disponible
    if (!isset($wpdb) || !is_object($wpdb)) {
        return null;
    }

    // Vérifier si un préfixe Pokémon distant est configuré
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);
    
    $use_remote = false;
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote = true;
            }
        }
    }
    
    if ($use_remote) {
        $pokemon_table = pokehub_get_table('remote_pokemon');
    } else {
        $pokemon_table = pokehub_get_table('pokemon');
    }
    
    if (!$pokemon_table) {
        return null;
    }
    
    // Récupérer le champ extra
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT extra FROM {$pokemon_table} WHERE id = %d LIMIT 1", $pokemon_id)
    );
    
    if (!$row || empty($row->extra)) {
        return null;
    }
    
    // Décoder le JSON extra
    $extra = json_decode($row->extra, true);
    if (!is_array($extra)) {
        return null;
    }
    
    // Récupérer les CP sets depuis extra->games->pokemon_go->cp_sets
    $cp_sets = $extra['games']['pokemon_go']['cp_sets'] ?? null;
    if (!$cp_sets || !is_array($cp_sets)) {
        return null;
    }
    
    // Récupérer max_cp et min_cp_10 pour le niveau demandé
    $level_key = (string) $level;
    $max_cp = $cp_sets['max_cp'][$level_key] ?? null;
    $min_cp = $cp_sets['min_cp_10'][$level_key] ?? null;
    
    if ($max_cp === null && $min_cp === null) {
        return null;
    }
    
    return [
        'max_cp' => $max_cp !== null ? (int) $max_cp : null,
        'min_cp' => $min_cp !== null ? (int) $min_cp : null,
    ];
}

/**
 * Récupère le slug du premier type d'un Pokémon
 * 
 * @param int $pokemon_id ID du Pokémon
 * @return string Slug du type ou chaîne vide
 */
function pokehub_get_pokemon_first_type_slug(int $pokemon_id): string {
    if (!function_exists('pokehub_get_table')) {
        return '';
    }

    global $wpdb;
    
    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return '';
    }

    // Sécurité : vérifier que $wpdb est disponible
    if (!isset($wpdb) || !is_object($wpdb)) {
        return '';
    }

    // Vérifier si un préfixe Pokémon distant est configuré
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);
    
    $use_remote = false;
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote = true;
            }
        }
    }
    
    if ($use_remote) {
        $types_table = pokehub_get_table('remote_pokemon_types');
        $type_links_table = pokehub_get_table('remote_pokemon_type_links');
    } else {
        $types_table = pokehub_get_table('pokemon_types');
        $type_links_table = pokehub_get_table('pokemon_type_links');
    }
    
    if (!$types_table || !$type_links_table) {
        return '';
    }
    
    $type = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT t.slug
             FROM {$types_table} t
             INNER JOIN {$type_links_table} ptl ON t.id = ptl.type_id
             WHERE ptl.pokemon_id = %d
             ORDER BY ptl.slot ASC
             LIMIT 1",
            $pokemon_id
        )
    );
    
    return $type && isset($type->slug) ? (string) $type->slug : '';
}

/**
 * Récupère la couleur du premier type d'un Pokémon
 * 
 * @param int $pokemon_id ID du Pokémon
 * @return string Couleur hexadécimale ou chaîne vide
 */
function pokehub_get_pokemon_type_color(int $pokemon_id): string {
    if (!function_exists('pokehub_get_table')) {
        return '';
    }

    global $wpdb;
    
    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return '';
    }

    // Sécurité : vérifier que $wpdb est disponible
    if (!isset($wpdb) || !is_object($wpdb)) {
        return '';
    }

    // Vérifier si un préfixe Pokémon distant est configuré
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);
    
    $use_remote = false;
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote = true;
            }
        }
    }
    
    if ($use_remote) {
        $types_table = pokehub_get_table('remote_pokemon_types');
        $type_links_table = pokehub_get_table('remote_pokemon_type_links');
    } else {
        $types_table = pokehub_get_table('pokemon_types');
        $type_links_table = pokehub_get_table('pokemon_type_links');
    }
    
    if (!$types_table || !$type_links_table) {
        return '';
    }
    
    $type = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT t.color
             FROM {$types_table} t
             INNER JOIN {$type_links_table} ptl ON t.id = ptl.type_id
             WHERE ptl.pokemon_id = %d
             ORDER BY ptl.slot ASC
             LIMIT 1",
            $pokemon_id
        )
    );
    
    $color = $type && isset($type->color) ? trim((string) $type->color) : '';
    return $color !== '' ? $color : '';
}

/**
 * Vérifie si un Pokémon peut être shiny ("chromatique" dans l'UI).
 *
 * Règle Pokémon GO (changement récent) :
 * si un Pokémon de base (1ère étape de la lignée d'évolution) est shiny-disponible,
 * alors ses évolutions peuvent aussi être trouvées directement en shiny.
 * 
 * @param int $pokemon_id ID du Pokémon
 * @return bool True si le Pokémon peut être shiny, false sinon
 */
function pokehub_pokemon_can_be_shiny(int $pokemon_id): bool {
    static $result_cache = [];
    static $direct_shiny_cache = [];
    static $base_id_cache = [];

    if (!function_exists('pokehub_get_table')) {
        return false;
    }

    global $wpdb;

    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return false;
    }

    if (!isset($wpdb) || !is_object($wpdb)) {
        return false;
    }

    // Vérifier si un préfixe Pokémon distant est configuré
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);

    $use_remote = false;
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote = true;
            }
        }
    }

    $pokemon_table = $use_remote ? pokehub_get_table('remote_pokemon') : pokehub_get_table('pokemon');
    if (!$pokemon_table) {
        return false;
    }

    $cache_prefix = $use_remote ? 'remote' : 'local';
    $cache_key = $cache_prefix . ':' . $pokemon_id;
    if (isset($result_cache[$cache_key])) {
        return (bool) $result_cache[$cache_key];
    }

    // Helper : shiny-disponible "direct" (extra->release->shiny uniquement, sans règle propagation)
    $has_direct_shiny = function (int $id) use ($wpdb, $pokemon_table, $cache_prefix, &$direct_shiny_cache): bool {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }

        $key = $cache_prefix . ':direct:' . $id;
        if (isset($direct_shiny_cache[$key])) {
            return (bool) $direct_shiny_cache[$key];
        }

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT extra FROM {$pokemon_table} WHERE id = %d LIMIT 1", $id)
        );

        if (!$row || empty($row->extra)) {
            $direct_shiny_cache[$key] = false;
            return false;
        }

        $extra = json_decode($row->extra, true);
        $release_shiny = is_array($extra) ? ($extra['release']['shiny'] ?? '') : '';

        $direct_shiny_cache[$key] = (!empty($release_shiny));
        return (bool) $direct_shiny_cache[$key];
    };

    // Si le Pokémon lui-même est shiny-disponible : ok.
    if ($has_direct_shiny($pokemon_id)) {
        $result_cache[$cache_key] = true;
        return true;
    }

    // Sinon, on regarde si le "base" de la lignée est shiny-disponible.
    $evolutions_table = $use_remote ? pokehub_get_table('remote_pokemon_evolutions') : pokehub_get_table('pokemon_evolutions');
    if (!$evolutions_table) {
        $result_cache[$cache_key] = false;
        return false;
    }

    // Remonter jusqu'au Pokémon de base (pas une cible d'évolution).
    $base_id = $pokemon_id;
    $base_cache_key = $cache_prefix . ':base:' . $pokemon_id;
    if (isset($base_id_cache[$base_cache_key])) {
        $base_id = (int) $base_id_cache[$base_cache_key];
    } else {
        $visited = [];
        for ($depth = 0; $depth < 10; $depth++) {
            if (isset($visited[$base_id])) {
                break; // évite une boucle en cas de données incohérentes
            }
            $visited[$base_id] = true;

            $prev_base = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT base_pokemon_id
                     FROM {$evolutions_table}
                     WHERE target_pokemon_id = %d
                     LIMIT 1",
                    $base_id
                )
            );

            $prev_base = (int) $prev_base;
            if ($prev_base <= 0 || $prev_base === $base_id) {
                break;
            }

            $base_id = $prev_base;
        }

        $base_id_cache[$base_cache_key] = $base_id;
    }

    $is_base_shiny = $has_direct_shiny($base_id);
    $result_cache[$cache_key] = (bool) $is_base_shiny;
    return (bool) $is_base_shiny;
}

/**
 * Indique si un Pokémon a une date de sortie dans Pokémon GO pour le contexte donné.
 * Utilise extra->release (normal, shiny, shadow, mega, dynamax, gigantamax).
 *
 * @param int    $pokemon_id ID du Pokémon
 * @param string $context    Clé de sortie : 'normal', 'shiny', 'shadow', 'mega', 'dynamax', 'gigantamax'
 * @return bool
 */
function poke_hub_pokemon_is_released_in_go(int $pokemon_id, string $context = 'normal'): bool {
    $context = in_array($context, ['normal', 'shiny', 'shadow', 'mega', 'dynamax', 'gigantamax'], true) ? $context : 'normal';

    if (!function_exists('pokehub_get_table')) {
        return false;
    }

    global $wpdb;

    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return false;
    }

    // Règle GO : si le Pokémon de base est shiny-disponible,
    // alors ses évolutions peuvent aussi être trouvées en shiny.
    // (on réutilise la logique centralisée via pokehub_pokemon_can_be_shiny()).
    if ($context === 'shiny' && function_exists('pokehub_pokemon_can_be_shiny')) {
        return pokehub_pokemon_can_be_shiny($pokemon_id);
    }

    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $use_remote           = false;
    if (!empty(trim($pokemon_remote_prefix)) && trim($pokemon_remote_prefix) !== $wpdb->prefix
        && function_exists('poke_hub_pokemon_get_table_prefix')) {
        $actual = poke_hub_pokemon_get_table_prefix();
        if (!empty($actual) && $actual !== $wpdb->prefix) {
            $use_remote = true;
        }
    }

    $pokemon_table = $use_remote ? pokehub_get_table('remote_pokemon') : pokehub_get_table('pokemon');
    if (!$pokemon_table) {
        return false;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT extra FROM {$pokemon_table} WHERE id = %d LIMIT 1", $pokemon_id)
    );

    if (!$row || empty($row->extra)) {
        return false;
    }

    $extra = json_decode($row->extra, true);
    if (!is_array($extra)) {
        return false;
    }

    $release = $extra['release'][$context] ?? '';

    return trim((string) $release) !== '';
}

/**
 * ============================================================================
 * HELPERS POUR LES IMAGES POKÉMON
 * Ces fonctions sont disponibles même si le module Pokémon n'est pas actif,
 * car elles sont utilisées par d'autres modules (ex: events, user-profiles).
 * ============================================================================
 */

/**
 * URL de base principale des assets Pokémon (définie dans les settings).
 */
function poke_hub_pokemon_get_assets_base_url() {
    $opt = trim((string) get_option('poke_hub_pokemon_assets_base_url', ''));
    if ($opt !== '') {
        return rtrim($opt, '/');
    }

    if (defined('POKE_HUB_POKEMON_ASSETS_BASE_URL')) {
        return rtrim(POKE_HUB_POKEMON_ASSETS_BASE_URL, '/');
    }

    return '';
}

/**
 * URL de base fallback des assets Pokémon.
 */
function poke_hub_pokemon_get_assets_fallback_base_url() {
    $opt = trim((string) get_option('poke_hub_pokemon_assets_fallback_base_url', ''));
    if ($opt !== '') {
        return rtrim($opt, '/');
    }

    if (defined('POKE_HUB_POKEMON_ASSETS_FALLBACK_BASE_URL')) {
        return rtrim(POKE_HUB_POKEMON_ASSETS_FALLBACK_BASE_URL, '/');
    }

    // Pas de fallback configuré
    return '';
}

/**
 * Construit la "clé" d'image à partir du slug + shiny + genre.
 */
function poke_hub_pokemon_build_image_key_from_slug($slug, array $args = []) {
    $args = wp_parse_args($args, [
        'shiny'  => false,
        'gender' => null,
    ]);

    $slug = sanitize_title($slug);
    $key  = $slug;

    // Genre
    if ($args['gender'] === 'male') {
        if (!preg_match('/-male(?:-|$)/', $key)) {
            $key .= '-male';
        }
    } elseif ($args['gender'] === 'female') {
        if (!preg_match('/-female(?:-|$)/', $key)) {
            $key .= '-female';
        }
    }

    // Shiny
    if (!empty($args['shiny'])) {
        if (!preg_match('/-shiny(?:-|$)/', $key)) {
            $key .= '-shiny';
        }
    }

    return $key;
}

/**
 * Version simple : ne renvoie que l'URL principale (string).
 */
function poke_hub_pokemon_get_image_url($pokemon, array $args = []) {
    $sources = poke_hub_pokemon_get_image_sources($pokemon, $args);

    return $sources['primary'];
}

/**
 * Version complète : renvoie primary + fallback (même pattern slug).
 *
 * @return array {
 *   'primary'  => string, // peut être '' si pas de base url
 *   'fallback' => string, // peut être '' si pas configuré
 * }
 */
function poke_hub_pokemon_get_image_sources($pokemon, array $args = []) {
    $args = wp_parse_args($args, [
        'shiny'   => false,
        'gender'  => null,
        'variant' => 'sprite', // si un jour tu veux des sous-dossiers
    ]);

    $base_url     = poke_hub_pokemon_get_assets_base_url();
    $fallback_url = poke_hub_pokemon_get_assets_fallback_base_url();

    $slug = isset($pokemon->slug) ? $pokemon->slug : '';
    if ($slug === '') {
        $slug = sprintf('%03d', (int) $pokemon->dex_number);
    }

    $key  = poke_hub_pokemon_build_image_key_from_slug($slug, $args);

    // Si tu rajoutes des sous-dossiers par variant, adapte ici :
    // $path = 'sprites/' . $key . '.png';
    $path = $key . '.png';

    $primary  = '';
    $fallback = '';

    if ($base_url !== '') {
        $primary = $base_url . '/' . ltrim($path, '/');
    }
    if ($fallback_url !== '') {
        $fallback = $fallback_url . '/' . ltrim($path, '/');
    }

    $sources = [
        'primary'  => $primary,
        'fallback' => $fallback,
    ];

    /**
     * Filtre si tu veux personnaliser pour certains Pokémon / formes.
     */
    return apply_filters('poke_hub_pokemon_image_sources', $sources, $pokemon, $args);
}

/**
 * ============================================================================
 * HELPERS POUR LE GENRE (GENDER)
 * ============================================================================
 */

/**
 * Détermine le genre à utiliser pour l'affichage d'un Pokémon
 * 
 * @param int $pokemon_id ID du Pokémon
 * @param string|null $forced_gender Genre forcé (male, female, ou null)
 * @return string|null 'male', 'female', ou null
 */
function poke_hub_pokemon_determine_gender($pokemon_id, $forced_gender = null) {
    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return null;
    }
    
    // Si un genre est forcé, l'utiliser
    if (!empty($forced_gender) && in_array($forced_gender, ['male', 'female'], true)) {
        return $forced_gender;
    }
    
    // Vérifier si le pokémon a un dysmorphisme de genre
    global $wpdb;
    $table = pokehub_get_table('pokemon');
    if (!$table) {
        return null;
    }
    
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT extra FROM {$table} WHERE id = %d", $pokemon_id)
    );
    
    if ($row && !empty($row->extra)) {
        $extra = json_decode($row->extra, true);
        if (is_array($extra) && !empty($extra['has_gender_dimorphism'])) {
            // Par défaut, utiliser 'male' si le pokémon a un dysmorphisme de genre
            return 'male';
        }
    }
    
    return null;
}

/**
 * ============================================================================
 * HELPERS POUR LE SHINY
 * Ces fonctions sont disponibles même si le module Pokémon n'est pas actif,
 * car elles sont utilisées par d'autres modules (ex: events, blocks).
 * ============================================================================
 */

/**
 * Récupère toutes les informations liées au shiny d'un Pokémon
 * 
 * Cette fonction centralise la logique du shiny pour éviter de la dupliquer
 * dans chaque bloc/module. Elle retourne toutes les informations nécessaires
 * pour l'affichage : disponibilité shiny, shiny forcé, image à utiliser, etc.
 * 
 * @param int|array|object $pokemon ID du Pokémon, ou données du Pokémon (array/object)
 * @param array $args {
 *     Arguments optionnels
 *     @type array $forced_shiny_ids Liste des IDs de Pokémon avec shiny forcé (par défaut: [])
 *     @type bool  $force_shiny      Forcer le shiny pour ce Pokémon spécifique (par défaut: false)
 * }
 * @return array {
 *     Informations sur le shiny
 *     @type bool   $is_shiny_available True si le Pokémon peut être shiny
 *     @type bool   $is_shiny_forced    True si le shiny est forcé pour ce Pokémon
 *     @type bool   $should_show_shiny  True si l'icône shiny doit être affichée
 *     @type string $image_url          URL de l'image normale à utiliser (toujours normale, même si shiny forcé)
 *                                      L'icône shiny sert d'indicateur visuel, pas de garantie
 * }
 */
function poke_hub_pokemon_get_shiny_info($pokemon, array $args = []) {
    $args = wp_parse_args($args, [
        'forced_shiny_ids' => [],
        'force_shiny'      => false,
    ]);
    
    // Récupérer l'ID du Pokémon
    $pokemon_id = 0;
    if (is_int($pokemon) || is_numeric($pokemon)) {
        $pokemon_id = (int) $pokemon;
    } elseif (is_array($pokemon)) {
        $pokemon_id = isset($pokemon['id']) ? (int) $pokemon['id'] : 0;
    } elseif (is_object($pokemon)) {
        $pokemon_id = isset($pokemon->id) ? (int) $pokemon->id : 0;
    }
    
    if ($pokemon_id <= 0) {
        return [
            'is_shiny_available' => false,
            'is_shiny_forced'    => false,
            'should_show_shiny'  => false,
            'image_url'          => '',
        ];
    }
    
    // Vérifier si shiny est disponible
    $is_shiny_available = function_exists('pokehub_pokemon_can_be_shiny') 
        ? pokehub_pokemon_can_be_shiny($pokemon_id) 
        : false;
    
    // Vérifier si shiny est forcé (soit dans la liste, soit explicitement forcé)
    $forced_shiny_ids = is_array($args['forced_shiny_ids']) ? $args['forced_shiny_ids'] : [];
    $is_shiny_forced = $args['force_shiny'] || in_array($pokemon_id, $forced_shiny_ids, true);
    
    // Déterminer si l'icône shiny doit être affichée
    $should_show_shiny = $is_shiny_forced || $is_shiny_available;
    
    // Déterminer le genre à utiliser
    $gender = $args['gender'] ?? null;
    if ($gender === null) {
        // Si aucun genre n'est spécifié, déterminer automatiquement
        $gender = poke_hub_pokemon_determine_gender($pokemon_id);
    }
    
    // Récupérer l'image (TOUJOURS normale, même si shiny forcé)
    // L'icône shiny sert juste d'indicateur visuel, pas de garantie
    $image_url = '';
    if (function_exists('pokehub_get_pokemon_data_by_id')) {
        $pokemon_data = pokehub_get_pokemon_data_by_id($pokemon_id);
        if ($pokemon_data) {
            $pokemon_obj = is_object($pokemon_data) ? $pokemon_data : (object) $pokemon_data;
            // Toujours utiliser l'image normale (shiny = false), mais avec le genre si nécessaire
            $image_sources = poke_hub_pokemon_get_image_sources($pokemon_obj, [
                'shiny' => false,
                'gender' => $gender,
            ]);
            $image_url = !empty($image_sources['primary']) ? $image_sources['primary'] : $image_sources['fallback'];
        }
    }
    
    return [
        'is_shiny_available' => $is_shiny_available,
        'is_shiny_forced'    => $is_shiny_forced,
        'should_show_shiny'  => $should_show_shiny,
        'image_url'          => $image_url,
    ];
}

/**
 * Récupère toutes les informations liées au statut régional d'un Pokémon
 * 
 * Cette fonction centralise la logique du régional pour éviter de la dupliquer
 * dans chaque bloc/module. Elle retourne toutes les informations nécessaires
 * pour l'affichage : statut régional, régions associées, etc.
 * 
 * @param int|array|object $pokemon ID du Pokémon, ou données du Pokémon (array/object)
 * @return array {
 *     Informations sur le régional
 *     @type bool   $is_regional      True si le Pokémon est régional
 *     @type bool   $should_show_icon True si l'icône régional doit être affichée
 *     @type array  $regions          Liste des régions où le Pokémon est disponible (optionnel)
 * }
 */
function poke_hub_pokemon_get_regional_info($pokemon) {
    // Récupérer l'ID du Pokémon
    $pokemon_id = 0;
    if (is_int($pokemon) || is_numeric($pokemon)) {
        $pokemon_id = (int) $pokemon;
    } elseif (is_array($pokemon)) {
        $pokemon_id = isset($pokemon['id']) ? (int) $pokemon['id'] : 0;
    } elseif (is_object($pokemon)) {
        $pokemon_id = isset($pokemon->id) ? (int) $pokemon->id : 0;
    }
    
    if ($pokemon_id <= 0) {
        return [
            'is_regional'      => false,
            'should_show_icon' => false,
            'regions'          => [],
        ];
    }
    
    // Vérifier si le Pokémon est régional
    $is_regional = false;
    $regions = [];
    
    if (function_exists('pokehub_get_table')) {
        global $wpdb;
        
        // Vérifier si un préfixe Pokémon distant est configuré
        $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
        $pokemon_remote_prefix = trim($pokemon_remote_prefix);
        
        $use_remote = false;
        if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
            if (function_exists('poke_hub_pokemon_get_table_prefix')) {
                $actual_prefix = poke_hub_pokemon_get_table_prefix();
                if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                    $use_remote = true;
                }
            }
        }
        
        $pokemon_table = $use_remote ? pokehub_get_table('remote_pokemon') : pokehub_get_table('pokemon');
        
        if ($pokemon_table) {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT extra FROM {$pokemon_table} WHERE id = %d LIMIT 1", $pokemon_id)
            );
            
            if ($row && !empty($row->extra)) {
                $extra = json_decode($row->extra, true);
                if (is_array($extra) && !empty($extra['regional']['is_regional'])) {
                    $is_regional = true;
                    
                    // Optionnel : récupérer les régions associées
                    if (!empty($extra['regional']['regions']) && is_array($extra['regional']['regions'])) {
                        $regions = $extra['regional']['regions'];
                    }
                }
            }
        }
    }
    
    return [
        'is_regional'      => $is_regional,
        'should_show_icon' => $is_regional, // Afficher l'icône si régional
        'regions'          => $regions,
    ];
}

/**
 * Récupère toutes les informations d'affichage d'un Pokémon (shiny + régional + image)
 * 
 * Cette fonction combine les informations shiny et régional pour simplifier
 * l'utilisation dans les blocs/modules. Elle retourne toutes les informations
 * nécessaires pour l'affichage complet d'un Pokémon.
 * 
 * @param int|array|object $pokemon ID du Pokémon, ou données du Pokémon (array/object)
 * @param array $args {
 *     Arguments optionnels
 *     @type array $forced_shiny_ids Liste des IDs de Pokémon avec shiny forcé (par défaut: [])
 *     @type bool  $force_shiny      Forcer le shiny pour ce Pokémon spécifique (par défaut: false)
 * }
 * @return array {
 *     Informations complètes pour l'affichage
 *     @type bool   $is_shiny_available       True si le Pokémon peut être shiny
 *     @type bool   $is_shiny_forced          True si le shiny est forcé pour ce Pokémon
 *     @type bool   $should_show_shiny        True si l'icône shiny doit être affichée
 *     @type bool   $is_regional              True si le Pokémon est régional
 *     @type bool   $should_show_regional_icon True si l'icône régional doit être affichée
 *     @type string $image_url               URL de l'image normale à utiliser
 *     @type array  $regions                 Liste des régions où le Pokémon est disponible (optionnel)
 * }
 */
function poke_hub_pokemon_get_display_info($pokemon, array $args = []) {
    $shiny_info = poke_hub_pokemon_get_shiny_info($pokemon, $args);
    $regional_info = poke_hub_pokemon_get_regional_info($pokemon);
    
    return array_merge($shiny_info, [
        'is_regional'              => $regional_info['is_regional'],
        'should_show_regional_icon' => $regional_info['should_show_icon'],
        'regions'                   => $regional_info['regions'],
    ]);
}

/**
 * ============================================================================
 * HELPERS POUR SELECT2 (Pokémon, Items, etc.)
 * Ces fonctions sont disponibles même si le module Pokémon n'est pas actif.
 * ============================================================================
 */

/**
 * Récupère tous les Pokémon pour les sélecteurs Select2
 * 
 * @return array Format: [['id' => 1, 'text' => 'Pikachu (#025)', 'name_fr' => 'Pikachu', 'name_en' => 'Pikachu', 'dex_number' => 25], ...]
 */
function pokehub_get_pokemon_for_select(): array {
    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
        error_log('[PokeHub SR LOG] pokehub_get_pokemon_for_select called, is_admin=' . (is_admin() ? '1' : '0'));
    }
    if (!function_exists('pokehub_get_table')) {
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            error_log('[PokeHub SR LOG] pokehub_get_pokemon_for_select: pokehub_get_table not found, return []');
        }
        return [];
    }

    global $wpdb;
    
    if (!isset($wpdb) || !is_object($wpdb)) {
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            error_log('[PokeHub SR LOG] pokehub_get_pokemon_for_select: wpdb not available, return []');
        }
        return [];
    }

    // Vérifier si un préfixe Pokémon distant est configuré
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);
    
    $use_remote = false;
    $actual_prefix = '';
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote = true;
            }
        }
    }
    
    if ($use_remote) {
        $pokemon_table = pokehub_get_table('remote_pokemon');
        $form_variants_table = pokehub_get_table('remote_pokemon_form_variants');
    } else {
        $pokemon_table = pokehub_get_table('pokemon');
        $form_variants_table = pokehub_get_table('pokemon_form_variants');
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
        error_log('[PokeHub SR LOG] pokehub_get_pokemon_for_select: use_remote=' . ($use_remote ? '1' : '0') . ' wpdb_prefix=' . $wpdb->prefix . ' actual_prefix=' . $actual_prefix . ' pokemon_table=' . ($pokemon_table ?: 'NULL') . ' form_variants_table=' . ($form_variants_table ?: 'NULL'));
    }
    
    if (!$pokemon_table || !$form_variants_table) {
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            error_log('[PokeHub SR LOG] pokehub_get_pokemon_for_select: tables missing, return []');
        }
        return [];
    }
    
    $sql = "SELECT p.id, 
                p.dex_number, 
                p.name_fr,
                p.name_en,
                p.form_variant_id,
                COALESCE(fv.label, fv.form_slug, '') AS form
         FROM {$pokemon_table} p
         LEFT JOIN {$form_variants_table} fv ON p.form_variant_id = fv.id
         ORDER BY p.dex_number ASC, p.name_fr ASC, p.name_en ASC";
    $rows = $wpdb->get_results($sql, ARRAY_A);
    
    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
        $count = is_array($rows) ? count($rows) : 0;
        error_log('[PokeHub SR LOG] pokehub_get_pokemon_for_select: query returned rows=' . $count . ' last_error=' . ($wpdb->last_error ?: 'none'));
        if ($count === 0 && $wpdb->last_error) {
            error_log('[PokeHub SR LOG] pokehub_get_pokemon_for_select: sql=' . $sql);
        }
        if ($count > 0) {
            $first = $rows[0];
            error_log('[PokeHub SR LOG] pokehub_get_pokemon_for_select: first row id=' . ($first['id'] ?? '') . ' text_sample=' . (isset($first['name_fr']) ? substr($first['name_fr'], 0, 30) : ''));
        }
    }
    
    if (empty($rows)) {
        return [];
    }
    
    $result = [];
    foreach ($rows as $pokemon) {
        $dex_number = isset($pokemon['dex_number']) ? (int) $pokemon['dex_number'] : 0;
        $name_fr = $pokemon['name_fr'] ?? '';
        $name_en = $pokemon['name_en'] ?? '';
        
        // Construire le nom au format "nom-fr (nom-anglais)" si les deux sont disponibles
        $name = $name_fr;
        if ($name_fr !== '' && $name_en !== '' && $name_fr !== $name_en) {
            $name = $name_fr . ' (' . $name_en . ')';
        } elseif ($name_fr === '' && $name_en !== '') {
            $name = $name_en;
        }
        
        $text = $name;
        if ($dex_number > 0) {
            $text .= ' #' . str_pad((string) $dex_number, 3, '0', STR_PAD_LEFT);
        }
        
        $result[] = [
            'id' => (int) $pokemon['id'],
            'text' => $text,
            'name_fr' => $name_fr,
            'name_en' => $name_en,
            'dex_number' => $dex_number,
        ];
    }
    
    return $result;
}

/**
 * Récupère des Pokémon pour Select2 avec filtre optionnel par IDs et/ou recherche texte.
 * Utilisé par l’API REST (recherche) et pour n’afficher que les options présélectionnées en PHP.
 *
 * @param int[] $ids   IDs à retourner (si non vide, ignore $search).
 * @param string $search Terme de recherche (name_fr, name_en, dex_number).
 * @return array Format: [['id' => 1, 'text' => '...', 'name_fr' => '...', 'name_en' => '...', 'dex_number' => 25], ...]
 */
function pokehub_get_pokemon_for_select_filtered(array $ids = [], string $search = ''): array {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }
    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb)) {
        return [];
    }
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $use_remote = false;
    if (!empty(trim($pokemon_remote_prefix)) && function_exists('poke_hub_pokemon_get_table_prefix')) {
        $actual = poke_hub_pokemon_get_table_prefix();
        if (!empty($actual) && $actual !== $wpdb->prefix) {
            $use_remote = true;
        }
    }
    $pokemon_table = $use_remote ? pokehub_get_table('remote_pokemon') : pokehub_get_table('pokemon');
    $form_variants_table = $use_remote ? pokehub_get_table('remote_pokemon_form_variants') : pokehub_get_table('pokemon_form_variants');
    if (!$pokemon_table || !$form_variants_table) {
        return [];
    }
    if (empty($ids) && $search === '') {
        return [];
    }
    $where = ['1=1'];
    $prepare_args = [];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $where[] = "p.id IN ($placeholders)";
        $prepare_args = array_merge($prepare_args, $ids);
    }
    if ($search !== '') {
        $term = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(p.name_fr LIKE %s OR p.name_en LIKE %s OR p.dex_number LIKE %s)';
        $prepare_args = array_merge($prepare_args, [$term, $term, $term]);
    }
    $where_sql = implode(' AND ', $where);
    $sql = "SELECT p.id, p.dex_number, p.name_fr, p.name_en, p.form_variant_id,
            COALESCE(fv.label, fv.form_slug, '') AS form
            FROM {$pokemon_table} p
            LEFT JOIN {$form_variants_table} fv ON p.form_variant_id = fv.id
            WHERE {$where_sql}
            ORDER BY p.dex_number ASC, p.name_fr ASC, p.name_en ASC";
    if (!empty($prepare_args)) {
        $sql = $wpdb->prepare($sql, $prepare_args);
    }
    $rows = $wpdb->get_results($sql, ARRAY_A);
    if (empty($rows)) {
        return [];
    }
    $result = [];
    foreach ($rows as $pokemon) {
        $dex_number = isset($pokemon['dex_number']) ? (int) $pokemon['dex_number'] : 0;
        $name_fr = $pokemon['name_fr'] ?? '';
        $name_en = $pokemon['name_en'] ?? '';
        $name = $name_fr;
        if ($name_fr !== '' && $name_en !== '' && $name_fr !== $name_en) {
            $name = $name_fr . ' (' . $name_en . ')';
        } elseif ($name_fr === '' && $name_en !== '') {
            $name = $name_en;
        }
        $text = $name;
        if ($dex_number > 0) {
            $text .= ' #' . str_pad((string) $dex_number, 3, '0', STR_PAD_LEFT);
        }
        $result[] = [
            'id' => (int) $pokemon['id'],
            'text' => $text,
            'name_fr' => $name_fr,
            'name_en' => $name_en,
            'dex_number' => $dex_number,
        ];
    }
    return $result;
}

/**
 * Récupère tous les items pour les sélecteurs Select2
 * 
 * @return array Format: [['id' => 1, 'text' => 'Pierre Évolutive', 'name_fr' => 'Pierre Évolutive', 'name_en' => 'Evolution Stone'], ...]
 */
function pokehub_get_items_for_select(): array {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }

    global $wpdb;
    
    if (!isset($wpdb) || !is_object($wpdb)) {
        return [];
    }

    // Vérifier si un préfixe Pokémon distant est configuré (les items peuvent être dans la même DB)
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);
    
    $use_remote = false;
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote = true;
            }
        }
    }
    
    if ($use_remote) {
        $items_table = pokehub_get_table('remote_items');
    } else {
        $items_table = pokehub_get_table('items');
    }
    
    if (!$items_table) {
        return [];
    }
    
    $rows = $wpdb->get_results(
        "SELECT id, name_fr, name_en
         FROM {$items_table}
         ORDER BY name_fr ASC, name_en ASC",
        ARRAY_A
    );
    
    if (empty($rows)) {
        return [];
    }
    
    $result = [];
    foreach ($rows as $item) {
        $name_fr = $item['name_fr'] ?? '';
        $name_en = $item['name_en'] ?? '';
        
        // Construire le nom au format "nom-fr (nom-anglais)" si les deux sont disponibles
        $name = $name_fr;
        if ($name_fr !== '' && $name_en !== '' && $name_fr !== $name_en) {
            $name = $name_fr . ' (' . $name_en . ')';
        } elseif ($name_fr === '' && $name_en !== '') {
            $name = $name_en;
        }
        
        $result[] = [
            'id' => (int) $item['id'],
            'text' => $name,
            'name_fr' => $name_fr,
            'name_en' => $name_en,
        ];
    }
    
    return $result;
}

/**
 * Récupère les Pokémon avec formes Mega pour les sélecteurs Select2 (pour méga énergie)
 * 
 * @return array Format: [['id' => 1, 'text' => 'Charizard (Mega X)', ...], ...]
 */
function pokehub_get_mega_pokemon_for_select(): array {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }

    global $wpdb;
    
    if (!isset($wpdb) || !is_object($wpdb)) {
        return [];
    }

    // Vérifier si un préfixe Pokémon distant est configuré
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);
    
    $use_remote = false;
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote = true;
            }
        }
    }
    
    if ($use_remote) {
        $pokemon_table = pokehub_get_table('remote_pokemon');
        $form_variants_table = pokehub_get_table('remote_pokemon_form_variants');
    } else {
        $pokemon_table = pokehub_get_table('pokemon');
        $form_variants_table = pokehub_get_table('pokemon_form_variants');
    }
    
    if (!$pokemon_table || !$form_variants_table) {
        return [];
    }
    
    // Récupérer uniquement les Pokémon avec formes Mega (category = 'mega' dans pokemon_form_variants)
    $rows = $wpdb->get_results(
        "SELECT p.id, 
                p.dex_number, 
                p.name_fr,
                p.name_en,
                p.form_variant_id,
                COALESCE(fv.label, fv.form_slug, '') AS form
         FROM {$pokemon_table} p
         INNER JOIN {$form_variants_table} fv ON p.form_variant_id = fv.id
         WHERE fv.category = 'mega'
         ORDER BY p.dex_number ASC, p.name_fr ASC, p.name_en ASC",
        ARRAY_A
    );
    
    if (empty($rows)) {
        return [];
    }
    
    $result = [];
    foreach ($rows as $pokemon) {
        $dex_number = isset($pokemon['dex_number']) ? (int) $pokemon['dex_number'] : 0;
        $name_fr = $pokemon['name_fr'] ?? '';
        $name_en = $pokemon['name_en'] ?? '';
        $form = $pokemon['form'] ?? '';
        
        // Construire le nom au format "nom-fr (nom-anglais)" si les deux sont disponibles
        $name = $name_fr;
        if ($name_fr !== '' && $name_en !== '' && $name_fr !== $name_en) {
            $name = $name_fr . ' (' . $name_en . ')';
        } elseif ($name_fr === '' && $name_en !== '') {
            $name = $name_en;
        }
        
        // Ajouter le nom de la forme si disponible
        if ($form !== '') {
            $name .= ' (' . $form . ')';
        }
        
        $text = $name;
        if ($dex_number > 0) {
            $text .= ' #' . str_pad((string) $dex_number, 3, '0', STR_PAD_LEFT);
        }
        
        $result[] = [
            'id' => (int) $pokemon['id'],
            'text' => $text,
            'name_fr' => $name_fr,
            'name_en' => $name_en,
            'dex_number' => $dex_number,
        ];
    }
    
    return $result;
}

/**
 * Récupère les Pokémon de base (pour les bonbons) pour les sélecteurs Select2
 * Un Pokémon de base est un Pokémon qui n'est pas une évolution (pas présent comme target_pokemon_id dans pokemon_evolutions)
 * 
 * @return array Format: [['id' => 1, 'text' => 'Pikachu (#025)', ...], ...]
 */
function pokehub_get_base_pokemon_for_select(): array {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }

    global $wpdb;
    
    if (!isset($wpdb) || !is_object($wpdb)) {
        return [];
    }

    // Vérifier si un préfixe Pokémon distant est configuré
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);
    
    $use_remote = false;
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote = true;
            }
        }
    }
    
    if ($use_remote) {
        $pokemon_table = pokehub_get_table('remote_pokemon');
        $form_variants_table = pokehub_get_table('remote_pokemon_form_variants');
        $evolutions_table = pokehub_get_table('remote_pokemon_evolutions');
    } else {
        $pokemon_table = pokehub_get_table('pokemon');
        $form_variants_table = pokehub_get_table('pokemon_form_variants');
        $evolutions_table = pokehub_get_table('pokemon_evolutions');
    }
    
    if (!$pokemon_table || !$form_variants_table) {
        return [];
    }
    
    // Si la table d'évolutions existe, exclure les Pokémon qui sont des évolutions
    if ($evolutions_table) {
        $rows = $wpdb->get_results(
            "SELECT p.id, 
                    p.dex_number, 
                    p.name_fr,
                    p.name_en,
                    p.form_variant_id,
                    COALESCE(fv.label, fv.form_slug, '') AS form
             FROM {$pokemon_table} p
             LEFT JOIN {$form_variants_table} fv ON p.form_variant_id = fv.id
             WHERE p.id NOT IN (SELECT DISTINCT target_pokemon_id FROM {$evolutions_table} WHERE target_pokemon_id > 0)
             ORDER BY p.dex_number ASC, p.name_fr ASC, p.name_en ASC",
            ARRAY_A
        );
    } else {
        // Si pas de table d'évolutions, retourner tous les Pokémon par défaut
        $rows = $wpdb->get_results(
            "SELECT p.id, 
                    p.dex_number, 
                    p.name_fr,
                    p.name_en,
                    p.form_variant_id,
                    COALESCE(fv.label, fv.form_slug, '') AS form
             FROM {$pokemon_table} p
             LEFT JOIN {$form_variants_table} fv ON p.form_variant_id = fv.id
             WHERE p.is_default = 1
             ORDER BY p.dex_number ASC, p.name_fr ASC, p.name_en ASC",
            ARRAY_A
        );
    }
    
    if (empty($rows)) {
        return [];
    }
    
    $result = [];
    foreach ($rows as $pokemon) {
        $dex_number = isset($pokemon['dex_number']) ? (int) $pokemon['dex_number'] : 0;
        $name_fr = $pokemon['name_fr'] ?? '';
        $name_en = $pokemon['name_en'] ?? '';
        
        // Construire le nom au format "nom-fr (nom-anglais)" si les deux sont disponibles
        $name = $name_fr;
        if ($name_fr !== '' && $name_en !== '' && $name_fr !== $name_en) {
            $name = $name_fr . ' (' . $name_en . ')';
        } elseif ($name_fr === '' && $name_en !== '') {
            $name = $name_en;
        }
        
        $text = $name;
        if ($dex_number > 0) {
            $text .= ' #' . str_pad((string) $dex_number, 3, '0', STR_PAD_LEFT);
        }
        
        $result[] = [
            'id' => (int) $pokemon['id'],
            'text' => $text,
            'name_fr' => $name_fr,
            'name_en' => $name_en,
            'dex_number' => $dex_number,
        ];
    }
    
    return $result;
}

/**
 * Récupère les données d'un item par son ID
 * 
 * @param int $item_id ID de l'item
 * @return array|null Array avec id, name_fr, name_en, name
 */
function pokehub_get_item_data_by_id(int $item_id): ?array {
    if (!function_exists('pokehub_get_table')) {
        return null;
    }

    global $wpdb;
    
    $item_id = (int) $item_id;
    if ($item_id <= 0) {
        return null;
    }

    // Sécurité : vérifier que $wpdb est disponible
    if (!isset($wpdb) || !is_object($wpdb)) {
        return null;
    }

    // Vérifier si un préfixe Pokémon distant est configuré (les items peuvent être dans la même DB)
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);
    
    $use_remote = false;
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote = true;
            }
        }
    }
    
    if ($use_remote) {
        $items_table = pokehub_get_table('remote_items');
    } else {
        $items_table = pokehub_get_table('items');
    }
    
    if (!$items_table) {
        return null;
    }
    
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, name_fr, name_en
             FROM {$items_table}
             WHERE id = %d
             LIMIT 1",
            $item_id
        ),
        ARRAY_A
    );
    
    if (!$row) {
        return null;
    }
    
    // Construire le nom au format "nom-fr (nom-anglais)" si les deux sont disponibles
    $name_fr = $row['name_fr'] ?? '';
    $name_en = $row['name_en'] ?? '';
    $name = $name_fr;
    if ($name_fr !== '' && $name_en !== '' && $name_fr !== $name_en) {
        $name = $name_fr . ' (' . $name_en . ')';
    } elseif ($name_fr === '' && $name_en !== '') {
        $name = $name_en;
    }
    
    $row['name'] = $name;
    return $row;
}

/**
 * Vivillon Pattern/Country Mapping Functions
 * 
 * These functions are available globally (not tied to any specific module)
 * so they can be used by both the Pokémon module (for Game Master import)
 * and the user-profiles module (for validation).
 * 
 * They are placed here in pokemon-public-helpers.php because they are primarily
 * related to Pokémon data (Vivillon/Scatterbug patterns), but they need to be
 * available even if the user-profiles module is not active.
 */

/**
 * Normalize a label to avoid mismatches (UM uses typographic apostrophes, special spaces, etc.)
 * This function is also defined in pokemon-regional-helpers.php but we keep it here
 * for availability even when the Pokémon module is not active.
 *
 * @param string $s
 * @return string
 */
function poke_hub_normalize_um_label_safe($s) {
    $s = (string) $s;

    // Replace typographic apostrophes (U+2019 RIGHT SINGLE QUOTATION MARK and U+2018 LEFT SINGLE QUOTATION MARK) with ASCII apostrophe
    $s = str_replace(["\xE2\x80\x99", "\xE2\x80\x98", "´", "`"], "'", $s);

    // Non-breaking spaces -> normal space
    $s = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], " ", $s);

    // Normalize whitespace
    $s = trim(preg_replace('/\s+/', ' ', $s));

    return $s;
}

/**
 * Récupère le mapping pays/motifs depuis les fiches Pokémon Vivillon/Scatterbug
 * 
 * @return array Mapping au format: ['pattern_slug' => ['country1', 'country2', ...], ...]
 */
function poke_hub_get_vivillon_pattern_country_mapping_from_db() {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }
    
    global $wpdb;
    
    if (!isset($wpdb) || !is_object($wpdb)) {
        return [];
    }
    
    // Vérifier si un préfixe Pokémon distant est configuré
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);
    
    $use_remote = false;
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote = true;
            }
        }
    }
    
    if ($use_remote) {
        $pokemon_table = pokehub_get_table('remote_pokemon');
        $form_variants_table = pokehub_get_table('remote_pokemon_form_variants');
    } else {
        $pokemon_table = pokehub_get_table('pokemon');
        $form_variants_table = pokehub_get_table('pokemon_form_variants');
    }
    
    if (empty($pokemon_table) || empty($form_variants_table)) {
        return [];
    }
    
    // Récupérer les Pokémon Vivillon/Scatterbug avec leurs pays
    $pokemon_table_escaped = esc_sql($pokemon_table);
    $form_variants_table_escaped = esc_sql($form_variants_table);
    
    $sql = "SELECT 
                p.extra,
                fv.form_slug
            FROM `{$pokemon_table_escaped}` AS p
            INNER JOIN `{$form_variants_table_escaped}` AS fv ON p.form_variant_id = fv.id
            WHERE p.dex_number IN (664, 666)
            AND p.form_variant_id > 0
            AND p.extra LIKE '%\"regional\":{\"is_regional\":true%'";
    
    $results = $wpdb->get_results($sql);
    
    if (empty($results) || $wpdb->last_error) {
        return [];
    }
    
    $mapping = [];
    
    foreach ($results as $row) {
        if (empty($row->extra) || empty($row->form_slug)) {
            continue;
        }
        
        $extra = json_decode($row->extra, true);
        if (!is_array($extra) || empty($extra['regional'])) {
            continue;
        }
        
        $regional = $extra['regional'];
        $form_slug = $row->form_slug;
        
        // Récupérer les pays depuis extra->regional->countries
        if (!empty($regional['countries']) && is_array($regional['countries'])) {
            $mapping[$form_slug] = $regional['countries'];
        }
    }
    
    return $mapping;
}

/**
 * Mapping des pays vers les motifs de lépidonille (Vivillon) dans Pokémon GO
 * 
 * Ce fichier contient la correspondance entre les pays (noms en français comme dans Ultimate Member)
 * et les motifs de lépidonille disponibles.
 * 
 * Source: Pokémon GO - Les motifs sont déterminés par la région géographique
 * 
 * @return array Mapping au format: ['pattern_slug' => ['country1', 'country2', ...], ...]
 */
function poke_hub_get_vivillon_pattern_country_mapping() {
    // Cache avec transient (12 heures) pour éviter les requêtes DB répétées
    $cache_key = 'poke_hub_vivillon_pattern_mapping';
    $cached = get_transient($cache_key);
    if ($cached !== false && is_array($cached)) {
        return $cached;
    }
    
    // Cache statique pour éviter de récupérer les mappings à chaque appel dans la même requête
    static $cached_mapping = null;
    static $cached_checked = false;
    
    if ($cached_checked) {
        return $cached_mapping;
    }
    
    $cached_checked = true;
    
    // 1) D'abord, essayer de récupérer depuis la table pokemon_regional_mappings (nouvelle source de vérité)
    if (function_exists('poke_hub_pokemon_get_regional_mappings_from_db')) {
        $mappings_from_table = poke_hub_pokemon_get_regional_mappings_from_db();
        
        if (!empty($mappings_from_table)) {
            // Convertir le format de la table en format attendu (pattern => countries array)
            // IMPORTANT: Normaliser les pattern_slug pour correspondre au form_slug du Game Master
            // Le Game Master utilise juste le pattern (e.g., "continental"), pas "vivillon-continental"
            $mapping = [];
            foreach ($mappings_from_table as $mapping_row) {
                $pattern_slug = $mapping_row['pattern_slug'];
                $countries = $mapping_row['countries'] ?? [];
                $region_slugs = $mapping_row['region_slugs'] ?? [];
                
                // Extraire le pattern du pattern_slug (e.g., "vivillon-continental" -> "continental")
                $pattern_only = $pattern_slug;
                if (preg_match('/^(scatterbug|spewpa|vivillon)-(.+)$/i', $pattern_slug, $matches)) {
                    $pattern_only = strtolower($matches[2]);
                }
                
                // Résoudre les régions en pays
                $resolved_countries = $countries;
                if (!empty($region_slugs) && function_exists('poke_hub_pokemon_get_countries_for_region')) {
                    foreach ($region_slugs as $region_slug) {
                        $region_countries = poke_hub_pokemon_get_countries_for_region($region_slug);
                        $resolved_countries = array_merge($resolved_countries, $region_countries);
                    }
                }
                
                // Dédupliquer et nettoyer
                $resolved_countries = array_unique(array_filter($resolved_countries));
                
                // Stocker sous le pattern uniquement (pas le pattern_slug complet)
                // Si plusieurs mappings partagent le même pattern (scatterbug-continental, vivillon-continental),
                // on merge les pays
                if (isset($mapping[$pattern_only])) {
                    $mapping[$pattern_only] = array_unique(array_merge($mapping[$pattern_only], $resolved_countries));
                } else {
                    $mapping[$pattern_only] = array_values($resolved_countries);
                }
            }
            
            // Only use mapping from table if it contains all expected Vivillon patterns
            // Expected patterns: continental, garden, elegant, modern, marine, archipelago, high-plains, jungle, ocean, meadow, polar, river, sandstorm, savanna, sun, icy-snow, monsoon, tundra (18 patterns)
            $expected_patterns = ['continental', 'garden', 'elegant', 'modern', 'marine', 'archipelago', 'high-plains', 'jungle', 'ocean', 'meadow', 'polar', 'river', 'sandstorm', 'savanna', 'sun', 'icy-snow', 'monsoon', 'tundra'];
            $found_patterns = array_keys($mapping);
            $missing_patterns = array_diff($expected_patterns, $found_patterns);
            
            // Only use DB mapping if we have most patterns (at least 15 out of 18)
            if (!empty($mapping) && count($missing_patterns) <= 3) {
                /**
                 * Filtre pour permettre aux thèmes/plugins de modifier le mapping depuis la table
                 * 
                 * @param array $mapping Mapping depuis la table pokemon_regional_mappings
                 * @return array Mapping modifié
                 */
                $cached_mapping = apply_filters('poke_hub_vivillon_pattern_country_mapping', $mapping);
                // Cache pour 12 heures
                if (!empty($cached_mapping)) {
                    set_transient('poke_hub_vivillon_pattern_mapping', $cached_mapping, 12 * HOUR_IN_SECONDS);
                }
                return $cached_mapping;
            }
        }
    }
    
    // 2) Ensuite, essayer de récupérer depuis les fiches Pokémon (ancienne méthode)
    $mapping_from_db = poke_hub_get_vivillon_pattern_country_mapping_from_db();
    
    // Si on a des données depuis la DB, les utiliser
    if (!empty($mapping_from_db)) {
        /**
         * Filtre pour permettre aux thèmes/plugins de modifier le mapping depuis la DB
         * 
         * @param array $mapping Mapping depuis la base de données
         * @return array Mapping modifié
         */
        $cached_mapping = apply_filters('poke_hub_vivillon_pattern_country_mapping', $mapping_from_db);
        // Cache pour 12 heures
        if (!empty($cached_mapping)) {
            set_transient('poke_hub_vivillon_pattern_mapping', $cached_mapping, 12 * HOUR_IN_SECONDS);
        }
        return $cached_mapping;
    }
    
    // 3) Sinon, utiliser le mapping statique par défaut
    /**
     * Filtre pour permettre aux thèmes/plugins de modifier le mapping par défaut
     * 
     * @param array $mapping Mapping par défaut
     * @return array Mapping modifié
     */
    $mapping = [
        // Motif Continental (Europe centrale et orientale + Asie)
        'continental' => [
            // Europe
            'Allemagne', 'Belgique', 'Biélorussie', 'Danemark', 'Estonie', 'France',
            'Hongrie', 'Lettonie', 'Lituanie', 'Luxembourg', 'Moldavie', 'Norvège',
            'Pays-Bas', 'Pologne', 'Russie', 'Slovaquie', 'Suède', 'Ukraine',
            'République Tchèque',
            // Asie
            'Argentine', 'Birmanie', 'Chili', 'Corée du Sud', 'Hong Kong', 'Inde',
            'Japon', 'Népal', 'Taïwan', 'Viêtnam',
        ],
        
        // Motif Jardin (Royaume-Uni, Irlande, Océanie)
        'garden' => [
            'Royaume-Uni', 'Irlande', 'Guernesey', 'Jersey', 'Île de Man',
            // Océanie
            'Australie', 'Nouvelle-Zélande',
        ],
        
        // Motif Élégant (Japon)
        'elegant' => [
            'Japon',
        ],
        
        // Motif Moderne (États-Unis, Canada - régions spécifiques)
        'modern' => [
            "États-Unis d'Amérique", 'Canada', 'Bermudes',
        ],
        
        // Motif Marine (Méditerranée - régions spécifiques)
        // IMPORTANT: Les pays custom (Açores, Îles Baléares, Madère) seront mappés vers leurs pays UM lors de la sauvegarde
        'marine' => [
            // Pays custom
            'Açores', // Mappé vers "Portugal" dans UM
            'Îles Baléares', // Mappé vers "Espagne" dans UM
            'Madère', // Mappé vers "Portugal" dans UM
            'Kosovo', // Pays custom : sera mappé vers "Kosovo" dans UM (si présent) ou gardé tel quel
            // Europe
            'Espagne', 'Portugal', 'Italie', 'Grèce', 'Chypre', 'Malte',
            'Croatie', 'Albanie', 'Monténégro', 'Bosnie-Herzégovine',
            'Allemagne', 'Andorre', 'Autriche', 'Bulgarie', 'France', 'Gibraltar',
            'Guernesey', 'Hongrie', 'Jersey', 'Moldavie', 'Pologne',
            'Roumanie', 'Russie', 'Saint Marin', 'Serbie', 'Slovaquie', 'Slovénie',
            'République Tchèque', 'Tunisie', 'Turquie', 'Ukraine', 'Vatican',
            // Autres
            'Argentine', 'Chili', 'Maroc',
        ],
        
        // Motif Archipel (Caraïbes, certaines îles, certaines régions d'Afrique et Amérique)
        'archipelago' => [
            // Caraïbes
            'Anguilla', 'Antigua-et-Barbuda', 'Antilles néerlandaises', 'Aruba', 'Bahamas', 'Barbade',
            'Cuba', 'Dominique', 'Grenade', 'Guadeloupe', 'Haïti', 'Îles Caïmans',
            'Îles Turques-et-Caïques', 'Îles Vierges américaines', 'Îles Vierges britanniques',
            'Jamaïque', 'Martinique', 'Montserrat', 'Porto Rico', 'République Dominicaine',
            'Saint Barthélemy', 'Saint-Christophe-et-Niévès', 'Sainte Lucie',
            'Saint-Vincent-et-les-Grenadines', 'Trinidad et Tobago',
            // Amérique du Sud / Amérique centrale
            'Colombie', 'Mexique', 'Venezuela',
            // Afrique
            'Afrique du Sud', 'Lesotho',
            // Europe (Îles Canaries / Açores - Espagne)
            'Espagne',
            // États-Unis (certaines îles/territoires - mais pas tous les US)
            "États-Unis d'Amérique",
        ],
        
        // Motif Hautes Plaines (États-Unis, Canada, Mexique, Asie centrale)
        'high-plains' => [
            "États-Unis d'Amérique", 'Canada', 'Mexique',
            // Asie centrale
            'Arménie', 'Azerbaïdjan', 'Géorgie', 'Kazakhstan', 'Kirghizistan',
            'Mongolie', 'Ouzbékistan', 'Russie', 'Tadjikistan', 'Turkménistan', 'Turquie',
        ],
        
        // Motif Jungle (Amérique du Sud - régions tropicales)
        'jungle' => [
            'Brésil', 'Colombie', 'Équateur', 'Guyane', 'Guyane française',
            'Panama', 'Pérou', 'Suriname', 'Venezuela',
            // Afrique tropicale
            'Angola', 'Bénin', 'Burundi', 'Cameroun', 'Côte d\'Ivoire', 'Gabon', 'Ghana',
            'Guinée Équatoriale', 'Kenya', 'Liberia', 'Nigeria', 'République centrafricaine',
            'République démocratique du Congo', 'Rwanda', 'São Tomé-et-Principe', 'Sierra Leone',
            'Soudan du Sud', 'Tanzanie', 'Togo', 'Ouganda', 'Zambie',
            // Asie du Sud-Est
            'Birmanie', 'Brunei', 'Cambodge', 'Costa Rica', 'Inde', 'Indonésie', 'Malaisie',
            'Papouasie Nouvelle Guinée', 'Philippines', 'Singapour', 'Sri Lanka', 'Viêtnam',
            // Autres
            'Comores', 'Îles Salomon', 'Trinidad et Tobago',
        ],
        
        // Motif Océan (Hawaï, certaines îles du Pacifique)
        // IMPORTANT: Les pays custom (Hawaï, Galápagos, Açores, Îles Baléares, Madère) seront mappés vers leurs pays UM lors de la sauvegarde
        'ocean' => [
            // Pays custom
            'Hawaï', // Mappé vers "États-Unis d'Amérique" dans UM
            'Galápagos', // Mappé vers "Équateur" dans UM
            'Açores', // Mappé vers "Portugal" dans UM
            'Îles Baléares', // Mappé vers "Espagne" dans UM
            'Madère', // Mappé vers "Portugal" dans UM
            // Autres îles du Pacifique
            'Fidji', 'Nouvelle Calédonie', 'Polynésie française', 'Samoa', 'Réunion',
            'Kiribati', 'Micronésie', 'Nauru', 'Niuéen', 'Palaos', 'Pitcairn',
            'Tokélaou', 'Tonga', 'Tuvalu', 'Vanuatu', 'Wallis et Futuna',
            // Pays supplémentaires
            'Barbade', 'Cap-Vert', 'Grenade', 'Guam', 'Iles Cook', 'Îles Mariannes du Nord',
            'Îles Marshall', 'Îles Salomon', 'Japon', 'Madagascar', 'Île Maurice',
            'Saint-Vincent-et-les-Grenadines', 'Seychelles', 'Trinidad et Tobago', 'Venezuela',
        ],
        
        // Motif Prairie (Europe centrale - régions spécifiques)
        'meadow' => [
            'Allemagne', 'Autriche', 'France', 'Italie', 'Suisse',
            'Belgique', 'Luxembourg', 'Pays-Bas',
            // Pays supplémentaires
            'Andorre', 'Liechtenstein', 'Monaco', 'Espagne',
            // Note: "Corse" n'est pas dans la liste UM (c'est une région de France), donc non inclus
        ],
        
        // Motif Polaire (Canada - régions nordiques, Scandinavie)
        'polar' => [
            'Canada', "États-Unis d'Amérique", // Alaska est partie des États-Unis
            'Norvège', 'Suède', 'Finlande', 'Islande', 'Danemark',
            'Groenland',
            // Pays manquants
            'Svalbard et Jan Mayen', // Optionnel si déjà en icy-snow, mais on l'ajoute
        ],
        
        // Motif Rivière (Australie, Afrique, Océanie)
        'river' => [
            'Australie',
            // Afrique
            'Afrique du Sud', 'Algérie', 'Angola', 'Bénin', 'Botswana', 'Burkina Faso',
            'Tchad', 'Côte d\'Ivoire', 'Égypte', 'Gambie', 'Ghana', 'Guinée', 'Guinée-Bissau',
            'Lesotho', 'Libye', 'Mali', 'Maroc', 'Mauritanie', 'Namibie', 'Niger', 'Nigeria',
            'Sahara occidental', 'Sénégal', 'Sierra Leone', 'Soudan', 'Togo', 'Tunisie', 'Zimbabwe',
        ],
        
        // Motif Tempête de sable (Moyen-Orient, Afrique du Nord)
        'sandstorm' => [
            'Arabie Saoudite', 'Bahreïn', 'Emirats Arabes Unis', 'Irak', 'Iran',
            'Israël', 'Jordanie', 'Koweït', 'Liban', 'Oman', 'Palestine',
            'Qatar', 'Syrie', 'Turquie', 'Yemen',
            'Algérie', 'Égypte', 'Libye', 'Maroc', 'Soudan', 'Tunisie',
            // Pays supplémentaires
            'Afghanistan', 'Arménie', 'Chypre', 'Comores', 'Djibouti', 'Érythrée', 'Éthiopie',
            'Inde', 'Kenya', 'Pakistan', 'Somalie', 'Turkménistan',
        ],
        
        // Motif Savane (Amérique du Sud - régions spécifiques)
        'savanna' => [
            'Argentine', 'Bolivie', 'Brésil', 'Paraguay', 'Pérou', 'Uruguay',
            // Pays africains (gardés de la liste précédente)
            'Angola', 'Bénin', 'Botswana', 'Burundi', 'Cameroun', 'Tchad', 'Congo',
            'République démocratique du Congo', 'Ghana', 'Kenya', 'Lesotho', 'Malawi',
            'Mali', 'Mozambique', 'Namibie', 'Niger', 'Nigeria', 'Rwanda', 'Sénégal',
            'Afrique du Sud', 'Soudan du Sud', 'Tanzanie', 'Togo', 'Zambie', 'Zimbabwe',
        ],
        
        // Motif Soleil (Zénith - Mexique, Amérique centrale, Afrique, Océanie)
        // IMPORTANT: "Galápagos" est un pays custom qui sera mappé vers "Équateur" dans UM lors de la sauvegarde
        'sun' => [
            // Pays custom
            'Galápagos', // Mappé vers "Équateur" dans UM
            // Amérique centrale
            'Mexique', 'Guatemala', 'Belize', 'Salvador', 'Honduras',
            'Nicaragua', 'Costa Rica',
            // Afrique
            'Afrique du Sud', 'Angola', 'Botswana', 'Comores', 'Éthiopie', 'Eswatini',
            'Kenya', 'Lesotho', 'Madagascar', 'Malawi', 'Mayotte', 'Mozambique',
            'Namibie', 'République démocratique du Congo', 'Somalie', 'Tanzanie',
            'Zambie', 'Zimbabwe',
            // Autres
            'Australie', 'Îles Caïmans',
        ],
        
        // Motif Neige glacée (Banquise / Blizzard - régions très nordiques)
        'icy-snow' => [
            // Amérique
            'Argentine', 'Canada', 'Chili', "États-Unis d'Amérique", // Alaska est partie des États-Unis
            // Europe / Asie nordiques
            'Finlande', 'Groenland', 'Kazakhstan', 'Mongolie', 'Norvège', 'Russie', 'Suède',
            // Territoires nordiques
            'Îles Åland', 'Îles Féroé', 'Svalbard et Jan Mayen', 'Saint Pierre et Miquelon',
            // Pays supplémentaires selon votre liste
            'Islande', 'Japon',
        ],
        
        // Motif Mousson (Asie du Sud)
        'monsoon' => [
            'Inde', 'Bangladesh', 'Bhoutan', 'Népal', 'Sri Lanka',
            // Pays supplémentaires
            'Birmanie', 'Cambodge', 'Japon', 'Personne de la République démocratique du Laos',
            'Philippines', 'Taïwan', 'Thaïlande', 'Viêtnam',
            // Note: "Tibet" n'est pas dans la liste UM, donc non inclus
        ],
        
        // Motif Toundra (Japon - île d'Hokkaido, Islande, Norvège, Suède, Finlande)
        'tundra' => [
            'Japon', 'Islande', 'Norvège', 'Suède', 'Finlande', 'Russie',
            // Territoires nordiques
            'Îles Åland', 'Îles Féroé', 'Svalbard et Jan Mayen', 'Groenland',
        ],
    ];
    
    /**
     * Filtre pour permettre aux thèmes/plugins de modifier le mapping par défaut
     * 
     * @param array $mapping Mapping par défaut
     * @return array Mapping modifié
     */
    $cached_mapping = apply_filters('poke_hub_vivillon_pattern_country_mapping', $mapping);
    // Cache pour 12 heures
    if (!empty($cached_mapping)) {
        set_transient('poke_hub_vivillon_pattern_mapping', $cached_mapping, 12 * HOUR_IN_SECONDS);
    }
    return $cached_mapping;
}

/**
 * Récupère les motifs valides pour un pays donné
 * 
 * @param string $country_name Nom du pays (en français, comme dans Ultimate Member)
 * @return array Liste des motifs valides (form_slug) pour ce pays, ou tableau vide si aucun
 */
function poke_hub_get_vivillon_patterns_for_country($country_name) {
    if (empty($country_name)) {
        return [];
    }
    
    $mapping = poke_hub_get_vivillon_pattern_country_mapping();
    $patterns = [];
    
    // Normaliser le pays fourni pour éviter les problèmes d'apostrophes typographiques, espaces, etc.
    // Utiliser la fonction de normalisation disponible (priorité à pokemon-regional-helpers si disponible)
    $normalize = function_exists('poke_hub_pokemon_normalize_um_label') 
        ? 'poke_hub_pokemon_normalize_um_label' 
        : 'poke_hub_normalize_um_label_safe';
    
    $country_norm = $normalize($country_name);
    
    // Le mapping est au format: ['pattern_slug' => ['country1', 'country2', ...], ...]
    // Parcourir le mapping pour trouver les motifs correspondant au pays
    // Normaliser les deux côtés pour éviter les problèmes de casse, apostrophes, espaces
    foreach ($mapping as $pattern => $countries) {
        if (!is_array($countries)) {
            continue;
        }
        
        // Normaliser tous les pays du mapping pour ce pattern
        $countries_norm = array_map($normalize, $countries);
        
        // Comparer les versions normalisées
        if (in_array($country_norm, $countries_norm, true)) {
            $patterns[] = $pattern;
        }
    }
    
    return $patterns;
}

/**
 * Récupère les pays valides pour un motif donné
 * 
 * @param string $pattern_slug Slug du motif (form_slug)
 * @return array Liste des pays valides pour ce motif, ou tableau vide si aucun
 */
function poke_hub_get_countries_for_vivillon_pattern($pattern_slug) {
    if (empty($pattern_slug)) {
        return [];
    }
    
    $mapping = poke_hub_get_vivillon_pattern_country_mapping();
    
    if (isset($mapping[$pattern_slug])) {
        return $mapping[$pattern_slug];
    }
    
    return [];
}

/**
 * Valide si une combinaison pays/motif est valide
 * 
 * @param string $country_name Nom du pays (en français)
 * @param string $pattern_slug Slug du motif (form_slug)
 * @return bool True si la combinaison est valide, false sinon
 */
function poke_hub_validate_vivillon_country_pattern($country_name, $pattern_slug) {
    // Si l'un des deux est vide, on ne valide pas (la validation de champ requis se fait ailleurs)
    if (empty($country_name) || empty($pattern_slug)) {
        return true; // On retourne true pour ne pas bloquer si un champ est vide
    }
    
    // IMPORTANT: Les pays custom (comme "Hawaï") sont directement dans le mapping, pas besoin de mapper vers UM
    // La fonction poke_hub_get_vivillon_patterns_for_country cherche directement dans le mapping
    // qui contient les pays custom comme "Hawaï", "Açores", etc.
    $valid_patterns = poke_hub_get_vivillon_patterns_for_country($country_name);
    
    // Si aucun motif n'est défini pour ce pays, on accepte (pour ne pas bloquer les nouveaux pays)
    if (empty($valid_patterns)) {
        return true;
    }
    
    return in_array($pattern_slug, $valid_patterns, true);
}

/**
 * Récupère l'URL de base du bucket d'assets
 *
 * @return string URL de base du bucket
 */
function poke_hub_get_assets_bucket_base_url(): string {
    return get_option('poke_hub_assets_bucket_base_url', 'https://pokemon.me5rine-lab.com/');
}

/**
 * Récupère le chemin spécifique pour un type d'asset
 *
 * @param string $asset_type Type d'asset ('icons', 'habitats', 'bonus', 'types', 'vivillon', 'fallback')
 * @return string Chemin spécifique
 */
function poke_hub_get_assets_path(string $asset_type): string {
    $paths = [
        'icons' => get_option('poke_hub_assets_path_icons', '/pokemon-go/icons/'),
        'habitats' => get_option('poke_hub_assets_path_habitats', '/pokemon-go/habitats/'),
        'bonus' => get_option('poke_hub_assets_path_bonus', '/pokemon-go/bonus/'),
        'types' => get_option('poke_hub_assets_path_types', '/pokemon-go/types/'),
        'vivillon' => get_option('poke_hub_assets_path_vivillon', '/pokemon-go/vivillon/'),
        'teams' => get_option('poke_hub_assets_path_teams', '/pokemon-go/teams/'),
        'fallback' => get_option('poke_hub_assets_path_fallback', '/pokemon-go/icons/home/'),
    ];
    
    return $paths[$asset_type] ?? '';
}

/**
 * Construit l'URL complète pour un asset
 *
 * @param string $asset_type Type d'asset
 * @param string $slug Slug de l'asset
 * @param string $extension Extension du fichier (par défaut: 'png')
 * @return string URL complète
 */
function poke_hub_get_asset_url(string $asset_type, string $slug, string $extension = 'png'): string {
    $base_url = poke_hub_get_assets_bucket_base_url();
    $path = poke_hub_get_assets_path($asset_type);
    
    if (empty($base_url) || empty($path) || empty($slug)) {
        return '';
    }
    
    $base_url = rtrim($base_url, '/');
    $path = '/' . ltrim($path, '/');
    $slug = sanitize_file_name($slug);
    
    return $base_url . $path . $slug . '.' . $extension;
}

/**
 * Récupère l'URL de l'icône d'un habitat
 *
 * @param string $slug Slug de l'habitat
 * @return string URL de l'icône
 */
function poke_hub_get_habitat_icon_url(string $slug): string {
    return poke_hub_get_asset_url('habitats', $slug);
}

/**
 * Récupère l'URL de l'icône d'un bonus
 *
 * @param string $slug Slug du bonus
 * @return string URL de l'icône
 */
function poke_hub_get_bonus_icon_url(string $slug): string {
    return poke_hub_get_asset_url('bonus', $slug);
}

/**
 * Récupère l'URL de l'icône d'un type
 *
 * @param string $slug Slug du type
 * @return string URL de l'icône
 */
function poke_hub_get_type_icon_url(string $slug): string {
    return poke_hub_get_asset_url('types', $slug);
}

/**
 * Récupère l'URL de l'icône d'un pattern Vivillon
 *
 * @param string $slug Slug du pattern
 * @return string URL de l'icône
 */
function poke_hub_get_vivillon_pattern_icon_url(string $slug): string {
    return poke_hub_get_asset_url('vivillon', $slug);
}

/**
 * Récupère l'URL de l'icône d'une team
 *
 * @param string $slug Slug de la team
 * @return string URL de l'icône
 */
function poke_hub_get_team_icon_url(string $slug): string {
    return poke_hub_get_asset_url('teams', $slug);
}

/**
 * Récupère l'URL de fallback pour un asset
 *
 * @param string $slug Slug de l'asset
 * @return string URL de fallback
 */
function poke_hub_get_fallback_asset_url(string $slug): string {
    return poke_hub_get_asset_url('fallback', $slug);
}
