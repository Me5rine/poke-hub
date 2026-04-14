<?php
// File: includes/functions/pokemon-public-helpers.php
// Helpers publics pour les données Pokémon (disponibles même si le module Pokémon n'est pas actif)
// Ces fonctions sont utilisées par d'autres modules (ex: user-profiles) et doivent être disponibles
// dès l'activation du plugin.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Indique si les données Pokémon (fiches, extra, évolutions…) doivent être lues depuis les tables
 * au préfixe « source » (option poke_hub_pokemon_remote_prefix + poke_hub_pokemon_get_table_prefix()),
 * plutôt que depuis les tables locales wpdb->prefix.
 */
function pokehub_pokemon_uses_remote_dataset(): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb)) {
        $cached = false;

        return false;
    }
    $pokemon_remote_prefix = trim((string) get_option('poke_hub_pokemon_remote_prefix', ''));
    if ($pokemon_remote_prefix === '' || $pokemon_remote_prefix === $wpdb->prefix) {
        $cached = false;

        return false;
    }
    if (!function_exists('poke_hub_pokemon_get_table_prefix')) {
        $cached = false;

        return false;
    }
    $actual = (string) poke_hub_pokemon_get_table_prefix();
    $cached = ($actual !== '' && $actual !== $wpdb->prefix);

    return $cached;
}

/**
 * Pour les tables « source » (remote), on ne fait pas de SHOW TABLES (même principe que poke_hub_pokemon_get_scatterbug_patterns).
 * Pour le préfixe WP courant, on vérifie l’existence via pokehub_table_ready_cached.
 *
 * @param bool    $use_remote Résultat de pokehub_pokemon_uses_remote_dataset()
 * @param string  ...$table_names Noms complets de tables (non vides)
 */
function pokehub_pokemon_tables_ready_for_query(bool $use_remote, string ...$table_names): bool {
    if ($use_remote) {
        return true;
    }
    if (!function_exists('pokehub_table_ready_cached')) {
        return false;
    }
    foreach ($table_names as $t) {
        $t = trim((string) $t);
        if ($t === '' || !pokehub_table_ready_cached($t)) {
            return false;
        }
    }

    return true;
}

/**
 * Purge le cache des motifs Scatterbug / Vivillon (transient utilisé par
 * {@see poke_hub_pokemon_get_scatterbug_patterns()}).
 *
 * À appeler après modification ou suppression d’un form variant en admin, ou après un import.
 */
function poke_hub_flush_scatterbug_patterns_cache(): void {
    delete_transient('poke_hub_scatterbug_patterns');
    delete_transient('poke_hub_scatterbug_patterns_v2');
}

/**
 * Get Scatterbug/Vivillon patterns from database.
 * Only returns patterns marked as regional (extra->regional->is_regional = true).
 * Patterns are stored as form variants for Scatterbug (dex_number 664) and Vivillon (dex_number 666).
 * 
 * This function is available even if the Pokémon module is not active, as it's used by
 * the user-profiles module to display Scatterbug pattern selection.
 *
 * Résultat mis en cache 12 h ; utiliser {@see poke_hub_flush_scatterbug_patterns_cache()} pour forcer
 * un rechargement depuis la base (ex. après édition des traductions dans les variants).
 *
 * @return array Associative array form_slug => label (French or English name)
 */
function poke_hub_pokemon_get_scatterbug_patterns(): array {
    // Cache avec transient (12 heures) pour éviter les requêtes DB répétées
    $cache_key = 'poke_hub_scatterbug_patterns_v2';
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

    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
    
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

        $final_label = null;

        // Priorité 1: extra->names->fr (données importées / base)
        if (!empty($pattern->form_variant_extra)) {
            $extra = json_decode($pattern->form_variant_extra, true);
            if (is_array($extra) && !empty($extra['names']['fr'])) {
                $final_label = trim((string) $extra['names']['fr']);
            }
        }

        // Priorité 2: label DB, sinon slug formaté
        if (empty($final_label)) {
            $label = (string) ($pattern->label ?? '');
            if (empty($label)) {
                $final_label = ucwords(str_replace(['-', '_'], ' ', $form_slug));
            } else {
                $final_label = $label;
            }
        }

        $result[$form_slug] = $final_label;
    }

    // Cache pour 12 heures (43200 secondes)
    if (!empty($result)) {
        set_transient($cache_key, $result, 12 * HOUR_IN_SECONDS);
    }

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

    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
    
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

    if (!pokehub_pokemon_tables_ready_for_query($use_remote, $pokemon_table, $form_variants_table)) {
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

    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
    
    if ($use_remote) {
        $pokemon_table = pokehub_get_table('remote_pokemon');
    } else {
        $pokemon_table = pokehub_get_table('pokemon');
    }
    
    if (!$pokemon_table) {
        return null;
    }

    if (!pokehub_pokemon_tables_ready_for_query($use_remote, $pokemon_table)) {
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

    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
    
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

    if (!pokehub_pokemon_tables_ready_for_query($use_remote, $types_table, $type_links_table)) {
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

    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
    
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

    if (!pokehub_pokemon_tables_ready_for_query($use_remote, $types_table, $type_links_table)) {
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
 * Récupère les types d'un Pokémon pour l'affichage public (icône, couleur, libellés).
 * Gère les tables locales ou distantes comme pokehub_get_pokemon_type_color().
 *
 * @param int $pokemon_id ID du Pokémon
 * @return array<int, array{id:int|string, slug:string, name_fr:string, name_en:string, color:string, icon:string}> icon : URL .svg dérivée du slug (Réglages → Sources), pas de champ BDD.
 */
function pokehub_get_pokemon_types_for_display(int $pokemon_id): array {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }

    global $wpdb;

    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0 || !isset($wpdb) || !is_object($wpdb)) {
        return [];
    }

    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();

    if ($use_remote) {
        $types_table      = pokehub_get_table('remote_pokemon_types');
        $type_links_table = pokehub_get_table('remote_pokemon_type_links');
    } else {
        $types_table      = pokehub_get_table('pokemon_types');
        $type_links_table = pokehub_get_table('pokemon_type_links');
    }

    if (!$types_table || !$type_links_table) {
        return [];
    }

    if (!pokehub_pokemon_tables_ready_for_query($use_remote, $types_table, $type_links_table)) {
        return [];
    }

    $types = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.id, t.slug, t.name_fr, t.name_en, t.color
             FROM {$types_table} t
             INNER JOIN {$type_links_table} ptl ON t.id = ptl.type_id
             WHERE ptl.pokemon_id = %d
             ORDER BY ptl.slot ASC",
            $pokemon_id
        ),
        ARRAY_A
    );

    if (empty($types) || !is_array($types)) {
        return [];
    }

    foreach ($types as &$t) {
        $t['color'] = isset($t['color']) ? trim((string) $t['color']) : '';
        $slug       = isset($t['slug']) ? trim((string) $t['slug']) : '';
        $t['icon']  = ($slug !== '' && function_exists('poke_hub_get_type_icon_url'))
            ? poke_hub_get_type_icon_url($slug)
            : '';
    }
    unset($t);

    return $types;
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

    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();

    $pokemon_table = $use_remote ? pokehub_get_table('remote_pokemon') : pokehub_get_table('pokemon');
    if (!$pokemon_table) {
        return false;
    }

    if (!pokehub_pokemon_tables_ready_for_query($use_remote, $pokemon_table)) {
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

    if (!pokehub_pokemon_tables_ready_for_query($use_remote, $evolutions_table)) {
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

    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();

    $pokemon_table = $use_remote ? pokehub_get_table('remote_pokemon') : pokehub_get_table('pokemon');
    if (!$pokemon_table) {
        return false;
    }

    if (!pokehub_pokemon_tables_ready_for_query($use_remote, $pokemon_table)) {
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
 * URL de base principale des sprites Pokémon : bucket commun + chemin « Pokémon » (réglages Sources).
 */
function poke_hub_pokemon_get_assets_base_url() {
    $bucket = trim((string) get_option('poke_hub_assets_bucket_base_url', ''));
    $path_pokemon = (string) get_option('poke_hub_assets_path_pokemon', '/pokemon-go/pokemon/');
    if ($bucket === '') {
        return '';
    }

    $bucket       = rtrim($bucket, '/');
    $path_pokemon = '/' . ltrim($path_pokemon, '/');

    return $bucket . rtrim($path_pokemon, '/');
}

/**
 * URL de base de secours : même arborescence / mêmes noms de fichiers que la source principale.
 */
function poke_hub_pokemon_get_assets_fallback_base_url() {
    $opt = trim((string) get_option('poke_hub_pokemon_assets_fallback_base_url', ''));
    return $opt !== '' ? rtrim($opt, '/') : '';
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
 * Genre des fichiers : si `$pokemon` a un `id` connu, `poke_hub_pokemon_determine_gender( id, args['gender'] )`
 * applique à la fois le genre forcé (sauvages, habitats, quêtes, évolutions…) et le défaut mâle en cas de dimorphisme.
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

    // Genre pour le fichier sprite : une seule mécanique — poke_hub_pokemon_determine_gender().
    // Genre explicite (sauvages, habitats, méta évolutions, etc.) = 2e argument ; sinon null → défaut mâle si dimorphisme.
    $pokemon_id_for_gender = 0;
    if (is_object($pokemon) && isset($pokemon->id)) {
        $pokemon_id_for_gender = (int) $pokemon->id;
    } elseif (is_array($pokemon) && isset($pokemon['id'])) {
        $pokemon_id_for_gender = (int) $pokemon['id'];
    }

    $gender_arg = $args['gender'];
    if ($gender_arg === '') {
        $gender_arg = null;
    }

    if ($pokemon_id_for_gender > 0 && function_exists('poke_hub_pokemon_determine_gender')) {
        $resolved = poke_hub_pokemon_determine_gender($pokemon_id_for_gender, $gender_arg);
        if ($resolved !== null) {
            $args['gender'] = $resolved;
        }
    }

    $base_url          = poke_hub_pokemon_get_assets_base_url();
    $fallback_base_url = poke_hub_pokemon_get_assets_fallback_base_url();

    // Slug / dex : supporter tableau ou objet. Un slug stocké à 0 (int) ou "0" (chaîne) est invalide :
    // sanitize_title le garde en "0" → URL …/0.png (souvent vu sur formes Méga si données partielles / import).
    $slug             = '';
    $dex_for_fallback = 0;
    if (is_array($pokemon)) {
        if (array_key_exists('slug', $pokemon)) {
            $slug = $pokemon['slug'];
        }
        if (isset($pokemon['dex_number'])) {
            $dex_for_fallback = (int) $pokemon['dex_number'];
        }
    } elseif (is_object($pokemon)) {
        if (isset($pokemon->slug)) {
            $slug = $pokemon->slug;
        }
        if (isset($pokemon->dex_number)) {
            $dex_for_fallback = (int) $pokemon->dex_number;
        }
    }

    $slug = trim((string) $slug);
    if ($slug === '' || $slug === '0') {
        $slug = '';
    }
    if ($slug === '' && $dex_for_fallback > 0) {
        $slug = sprintf('%03d', $dex_for_fallback);
    }
    if ($slug === '' && $pokemon_id_for_gender > 0 && function_exists('pokehub_get_pokemon_data_by_id')) {
        $resolved = pokehub_get_pokemon_data_by_id($pokemon_id_for_gender);
        if (is_array($resolved)) {
            $s2 = isset($resolved['slug']) ? trim((string) $resolved['slug']) : '';
            $d2 = isset($resolved['dex_number']) ? (int) $resolved['dex_number'] : 0;
            if ($s2 !== '' && $s2 !== '0') {
                $slug = $s2;
            } elseif ($d2 > 0) {
                $slug = sprintf('%03d', $d2);
            }
        }
    }

    $primary  = '';
    $fallback = '';

    if ($slug !== '') {
        $key = poke_hub_pokemon_build_image_key_from_slug($slug, $args);
        // Si la clé reste vide ou "0", ne pas fabriquer …/0.png ni …/.png
        if ($key !== '' && $key !== '0') {
            // Si tu rajoutes des sous-dossiers par variant, adapte ici :
            // $path = 'sprites/' . $key . '.png';
            $path = $key . '.png';

            if ($base_url !== '') {
                $primary = $base_url . '/' . ltrim($path, '/');
            }

            // Fallback explicite : même clé/fichier, mais sur une base URL secondaire.
            // Si aucun fallback n'est configuré, on garde la compatibilité avec primary.
            if ($fallback_base_url !== '') {
                $fallback = $fallback_base_url . '/' . ltrim($path, '/');
            } else {
                $fallback = $primary;
            }
        }
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
 * Profil de genre d'un Pokémon : dimorphisme, ratios GM, genres disponibles et genre par défaut.
 *
 * Notes importantes :
 * - `available_genders` décrit les genres utilisables globalement (UI, assets).
 * - `spawn_available_genders` décrit les genres qui peuvent apparaître en nature (d'après ratios GM).
 * - Le genre forcé côté contenu reste prioritaire (quêtes, events, habitats...).
 *
 * @param int $pokemon_id
 * @return array{
 *   has_gender_dimorphism: bool,
 *   gender_ratio: array{male: float, female: float},
 *   available_genders: string[],
 *   spawn_available_genders: string[],
 *   default_gender: string|null
 * }
 */
function poke_hub_pokemon_get_gender_profile(int $pokemon_id): array {
    $pokemon_id = (int) $pokemon_id;
    $profile = [
        'has_gender_dimorphism'   => false,
        'gender_ratio'            => ['male' => 0.0, 'female' => 0.0],
        'available_genders'       => [],
        'spawn_available_genders' => [],
        'default_gender'          => null,
    ];

    if ($pokemon_id <= 0) {
        return $profile;
    }

    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb) || !function_exists('pokehub_get_table')) {
        return $profile;
    }

    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
    $table      = $use_remote ? pokehub_get_table('remote_pokemon') : pokehub_get_table('pokemon');
    if (!$table || !pokehub_pokemon_tables_ready_for_query($use_remote, $table)) {
        return $profile;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT extra FROM {$table} WHERE id = %d", $pokemon_id)
    );
    if (!$row || empty($row->extra)) {
        return $profile;
    }

    $extra = json_decode($row->extra, true);
    if (!is_array($extra)) {
        return $profile;
    }

    $has_dimorphism = !empty($extra['has_gender_dimorphism']);
    $ratio_male     = isset($extra['gender']['male']) ? (float) $extra['gender']['male'] : 0.0;
    $ratio_female   = isset($extra['gender']['female']) ? (float) $extra['gender']['female'] : 0.0;

    $available = [];
    if (isset($extra['gender']['available_genders']) && is_array($extra['gender']['available_genders'])) {
        foreach ($extra['gender']['available_genders'] as $g) {
            if (in_array($g, ['male', 'female'], true) && !in_array($g, $available, true)) {
                $available[] = $g;
            }
        }
    } elseif (isset($extra['available_genders']) && is_array($extra['available_genders'])) {
        foreach ($extra['available_genders'] as $g) {
            if (in_array($g, ['male', 'female'], true) && !in_array($g, $available, true)) {
                $available[] = $g;
            }
        }
    }

    if (empty($available)) {
        if ($has_dimorphism) {
            // Compatibilité : un Pokémon dimorphique doit permettre les deux sexes dans l'UI globale.
            $available = ['male', 'female'];
        } else {
            if ($ratio_male > 0) {
                $available[] = 'male';
            }
            if ($ratio_female > 0) {
                $available[] = 'female';
            }
        }
    }

    $spawn_available = [];
    if (isset($extra['gender']['spawn_available_genders']) && is_array($extra['gender']['spawn_available_genders'])) {
        foreach ($extra['gender']['spawn_available_genders'] as $g) {
            if (in_array($g, ['male', 'female'], true) && !in_array($g, $spawn_available, true)) {
                $spawn_available[] = $g;
            }
        }
    } elseif (isset($extra['spawn_available_genders']) && is_array($extra['spawn_available_genders'])) {
        foreach ($extra['spawn_available_genders'] as $g) {
            if (in_array($g, ['male', 'female'], true) && !in_array($g, $spawn_available, true)) {
                $spawn_available[] = $g;
            }
        }
    }

    if (empty($spawn_available)) {
        if ($has_dimorphism && !empty($available)) {
            // Cas des espèces à dimorphisme marqué (ex: Wimessir, Paragruel) :
            // on ne se fie pas aveuglément au ratio GM global, souvent orienté vers un visuel par défaut.
            $spawn_available = $available;
        } else {
            // Fallback ratio pour les espèces non dimorphiques / anciennes données.
            if ($ratio_male > 0) {
                $spawn_available[] = 'male';
            }
            if ($ratio_female > 0) {
                $spawn_available[] = 'female';
            }
        }
    }

    $default_gender = null;
    if ($has_dimorphism) {
        // Comportement conservé : mâle par défaut quand on ne force rien.
        if (in_array('male', $available, true)) {
            $default_gender = 'male';
        } elseif (!empty($available)) {
            $default_gender = (string) $available[0];
        }
    }

    $profile['has_gender_dimorphism']   = $has_dimorphism;
    $profile['gender_ratio']            = ['male' => $ratio_male, 'female' => $ratio_female];
    $profile['available_genders']       = $available;
    $profile['spawn_available_genders'] = $spawn_available;
    $profile['default_gender']          = $default_gender;

    return $profile;
}

/**
 * Genre à utiliser pour les sprites (et tout appelant qui s’aligne sur la même règle).
 *
 * @param int               $pokemon_id    ID du Pokémon en base.
 * @param string|null       $forced_gender male|female = priorité contenu (sauvages, habitats, quêtes, évolutions…), null = défaut.
 * @return string|null male, female, ou null (pas de suffixe genre sur le fichier).
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
    
    if (function_exists('poke_hub_pokemon_get_gender_profile')) {
        $profile = poke_hub_pokemon_get_gender_profile($pokemon_id);
        if (!empty($profile['default_gender']) && in_array($profile['default_gender'], ['male', 'female'], true)) {
            return $profile['default_gender'];
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
 *     @type array         $forced_shiny_ids Liste des IDs de Pokémon avec shiny forcé (par défaut: [])
 *     @type bool          $force_shiny      Forcer le shiny pour ce Pokémon spécifique (par défaut: false)
 *     @type string|null   $gender           male|female si imposé par le contenu ; sinon null (résolu dans poke_hub_pokemon_get_image_sources).
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
        'gender'           => null,
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
    
    // Image : le genre (défaut dimorphisme / mâle) est entièrement géré dans poke_hub_pokemon_get_image_sources().
    $image_gender = $args['gender'];
    if ($image_gender === '') {
        $image_gender = null;
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
                'gender' => $image_gender,
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
        
        $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
        
        $pokemon_table = $use_remote ? pokehub_get_table('remote_pokemon') : pokehub_get_table('pokemon');
        
        if ($pokemon_table && pokehub_pokemon_tables_ready_for_query($use_remote, $pokemon_table)) {
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
 *     @type array         $forced_shiny_ids Liste des IDs de Pokémon avec shiny forcé (par défaut: [])
 *     @type bool          $force_shiny      Forcer le shiny pour ce Pokémon spécifique (par défaut: false)
 *     @type string|null   $gender           Voir poke_hub_pokemon_get_shiny_info().
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
 * Rang de tri pour les catégories de formes dans les listes.
 * Ordre voulu: base/normal -> costume -> mega -> autres.
 */
function pokehub_pokemon_select_category_rank(string $category): int {
    $category = sanitize_key($category);
    if ($category === '' || in_array($category, ['normal', 'base', 'default'], true)) {
        return 0;
    }
    if (in_array($category, ['costume', 'costumed'], true)) {
        return 1;
    }
    if ($category === 'mega') {
        return 2;
    }
    return 3;
}

/**
 * Trie les rows Pokémon pour les listes/selecteurs:
 * dex -> nom (regroupement espèce) -> catégorie de forme -> libellé de forme.
 *
 * @param array<int, array<string, mixed>> $rows
 */
function pokehub_sort_pokemon_select_rows(array &$rows): void {
    usort($rows, static function (array $a, array $b): int {
        $dexA = isset($a['dex_number']) ? (int) $a['dex_number'] : 0;
        $dexB = isset($b['dex_number']) ? (int) $b['dex_number'] : 0;
        if ($dexA !== $dexB) {
            return $dexA <=> $dexB;
        }

        $nameA = trim((string) (($a['name_fr'] ?? '') !== '' ? $a['name_fr'] : ($a['name_en'] ?? '')));
        $nameB = trim((string) (($b['name_fr'] ?? '') !== '' ? $b['name_fr'] : ($b['name_en'] ?? '')));
        $nameAN = function_exists('mb_strtolower') ? mb_strtolower($nameA, 'UTF-8') : strtolower($nameA);
        $nameBN = function_exists('mb_strtolower') ? mb_strtolower($nameB, 'UTF-8') : strtolower($nameB);
        if ($nameAN !== $nameBN) {
            return $nameAN <=> $nameBN;
        }

        $rankA = pokehub_pokemon_select_category_rank((string) ($a['form_category'] ?? ''));
        $rankB = pokehub_pokemon_select_category_rank((string) ($b['form_category'] ?? ''));
        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }

        $formA = trim((string) ($a['form'] ?? ''));
        $formB = trim((string) ($b['form'] ?? ''));
        $formAN = function_exists('mb_strtolower') ? mb_strtolower($formA, 'UTF-8') : strtolower($formA);
        $formBN = function_exists('mb_strtolower') ? mb_strtolower($formB, 'UTF-8') : strtolower($formB);
        if ($formAN !== $formBN) {
            return $formAN <=> $formBN;
        }

        $idA = isset($a['id']) ? (int) $a['id'] : 0;
        $idB = isset($b['id']) ? (int) $b['id'] : 0;
        return $idA <=> $idB;
    });
}

/**
 * Vérifie rapidement si un Pokémon est dimorphique via le profil de genre.
 */
function pokehub_pokemon_is_dimorphic_for_select(int $pokemon_id): bool {
    static $cache = [];
    if ($pokemon_id <= 0) {
        return false;
    }
    if (array_key_exists($pokemon_id, $cache)) {
        return (bool) $cache[$pokemon_id];
    }
    $is_dimorphic = false;
    if (function_exists('poke_hub_pokemon_get_gender_profile')) {
        $profile = poke_hub_pokemon_get_gender_profile($pokemon_id);
        $is_dimorphic = !empty($profile['has_gender_dimorphism']);
    }
    $cache[$pokemon_id] = $is_dimorphic;
    return $is_dimorphic;
}

/**
 * Récupère tous les Pokémon pour les sélecteurs Select2
 * 
 * @return array Format: [['id' => 1, 'text' => 'Pikachu (#025)', 'name_fr' => 'Pikachu', 'name_en' => 'Pikachu', 'dex_number' => 25], ...]
 */
function pokehub_get_pokemon_for_select(): array {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }

    global $wpdb;
    
    if (!isset($wpdb) || !is_object($wpdb)) {
        return [];
    }

    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
    
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
    
    $sql = "SELECT p.id, 
                p.dex_number, 
                p.name_fr,
                p.name_en,
                p.form_variant_id,
                COALESCE(fv.label, fv.form_slug, '') AS form,
                COALESCE(fv.category, 'normal') AS form_category
         FROM {$pokemon_table} p
         LEFT JOIN {$form_variants_table} fv ON p.form_variant_id = fv.id
         ORDER BY p.dex_number ASC, p.name_fr ASC, p.name_en ASC";
    $rows = $wpdb->get_results($sql, ARRAY_A);
    
    if (empty($rows)) {
        return [];
    }
    pokehub_sort_pokemon_select_rows($rows);
    
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
            'has_gender_dimorphism' => pokehub_pokemon_is_dimorphic_for_select((int) $pokemon['id']),
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
function pokehub_get_pokemon_for_select_filtered(array $ids = [], string $search = '', bool $dimorphic_only = false): array {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }
    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb)) {
        return [];
    }
    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
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
            COALESCE(fv.label, fv.form_slug, '') AS form,
            COALESCE(fv.category, 'normal') AS form_category
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
    pokehub_sort_pokemon_select_rows($rows);
    $result = [];
    foreach ($rows as $pokemon) {
        $pokemon_id = (int) $pokemon['id'];
        $is_dimorphic = pokehub_pokemon_is_dimorphic_for_select($pokemon_id);
        if ($dimorphic_only && !$is_dimorphic) {
            continue;
        }
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
            'id' => $pokemon_id,
            'text' => $text,
            'name_fr' => $name_fr,
            'name_en' => $name_en,
            'dex_number' => $dex_number,
            'has_gender_dimorphism' => $is_dimorphic,
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

    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
    
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

    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
    
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

    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
    
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
                    COALESCE(fv.label, fv.form_slug, '') AS form,
                    COALESCE(fv.category, 'normal') AS form_category
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
                    COALESCE(fv.label, fv.form_slug, '') AS form,
                    COALESCE(fv.category, 'normal') AS form_category
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
    pokehub_sort_pokemon_select_rows($rows);
    
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

    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
    
    if ($use_remote) {
        $items_table = pokehub_get_table('remote_items');
    } else {
        $items_table = pokehub_get_table('items');
    }
    
    if (!$items_table) {
        return null;
    }

    if (!pokehub_pokemon_tables_ready_for_query($use_remote, $items_table)) {
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
    
    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
    
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
 * @param string $asset_type Type d'asset ('icons', 'habitats', 'bonus', 'types', 'vivillon', 'teams', 'candies', 'mega_energies', 'fallback')
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
        'candies' => get_option('poke_hub_assets_path_candies', '/pokemon-go/candies/'),
        'mega_energies' => get_option('poke_hub_assets_path_mega_energies', '/pokemon-go/mega-energies/'),
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
 * URLs raster pour un même stem de fichier : WebP, PNG, JPG (ordre de repli côté navigateur).
 *
 * @return array{webp: string, png: string, jpg: string}
 */
function poke_hub_get_raster_asset_variant_urls(string $asset_type, string $slug): array {
    return [
        'webp' => poke_hub_get_asset_url($asset_type, $slug, 'webp'),
        'png'  => poke_hub_get_asset_url($asset_type, $slug, 'png'),
        'jpg'  => poke_hub_get_asset_url($asset_type, $slug, 'jpg'),
    ];
}

/**
 * Chaîne d’URLs uniques pour repli format raster (sans HEAD serveur).
 *
 * Ordre par défaut : WebP → PNG → JPG (y compris Vivillon, équipes, etc.). Filtre
 * {@see 'poke_hub_raster_asset_url_chain_order'} pour ajuster par type d’asset.
 *
 * @return string[]
 */
function poke_hub_get_raster_asset_url_chain(string $asset_type, string $slug): array {
    $slug = trim((string) $slug);
    if ($slug === '') {
        return [];
    }

    $u = poke_hub_get_raster_asset_variant_urls($asset_type, $slug);
    $order = ['webp', 'png', 'jpg'];
    /**
     * Ordre des variantes raster pour data-ph-raster (clés : webp, png, jpg).
     *
     * @param string[] $order      Clés dans l’ordre de tentative
     * @param string   $asset_type Type d’asset
     * @param string   $slug       Slug
     */
    $order = apply_filters('poke_hub_raster_asset_url_chain_order', $order, $asset_type, $slug);
    if (!is_array($order) || $order === []) {
        $order = ['webp', 'png', 'jpg'];
    }

    $out = [];
    foreach ($order as $k) {
        if (!is_string($k) || $k === '') {
            continue;
        }
        $url = isset($u[$k]) ? trim((string) $u[$k]) : '';
        if ($url !== '' && !in_array($url, $out, true)) {
            $out[] = $url;
        }
    }

    return $out;
}

/**
 * Attribut data-ph-raster pour &lt;option&gt; ou balises custom (JSON de la chaîne).
 */
function poke_hub_get_raster_option_data_attr(string $asset_type, string $slug): string {
    $chain = poke_hub_get_raster_asset_url_chain($asset_type, $slug);
    if ($chain === []) {
        return '';
    }

    return ' data-ph-raster="' . esc_attr(wp_json_encode($chain)) . '"';
}

/**
 * Balise &lt;img&gt; bucket avec repli de format (attribut data-ph-raster + script global).
 *
 * @param array<string, mixed> $args alt, class, width, height, loading, decoding
 */
function poke_hub_render_bucket_raster_img(string $asset_type, string $slug, array $args = []): string {
    $chain = poke_hub_get_raster_asset_url_chain($asset_type, $slug);
    if ($chain === []) {
        return '';
    }

    $args = wp_parse_args(
        $args,
        [
            'alt'      => '',
            'class'    => '',
            'width'    => null,
            'height'   => null,
            'loading'  => 'lazy',
            'decoding' => 'async',
        ]
    );

    $html = '<img src="' . esc_url($chain[0]) . '" alt="' . esc_attr((string) $args['alt']) . '" data-ph-raster="' . esc_attr(wp_json_encode($chain)) . '"';

    $class = trim((string) $args['class']);
    if ($class !== '') {
        $html .= ' class="' . esc_attr($class) . '"';
    }

    $w = $args['width'];
    $h = $args['height'];
    if ($w !== null && $w !== '') {
        $html .= ' width="' . (int) $w . '"';
    }
    if ($h !== null && $h !== '') {
        $html .= ' height="' . (int) $h . '"';
    }

    $loading = sanitize_key((string) $args['loading']);
    if ($loading !== '') {
        $html .= ' loading="' . esc_attr($loading) . '"';
    }

    $decoding = sanitize_key((string) $args['decoding']);
    if ($decoding !== '') {
        $html .= ' decoding="' . esc_attr($decoding) . '"';
    }

    $html .= ' />';

    return $html;
}

/**
 * Rendu visuel d’un bonus sur le bucket : **SVG inline** en priorité (mode icône), repli &lt;img&gt; raster WebP/PNG/JPG.
 *
 * @param array<string, mixed> $args alt (libellé accessible), class (classes sur le conteneur), icon_size (côté en px, défaut 80), loading, decoding (raster uniquement).
 */
function poke_hub_render_bonus_asset_markup(string $slug, array $args = []): string {
    $slug = trim((string) $slug);
    if ($slug === '') {
        return '';
    }

    $args = wp_parse_args(
        $args,
        [
            'alt'       => '',
            'class'     => '',
            'icon_size' => 80,
            'loading'   => 'lazy',
            'decoding'  => 'async',
        ]
    );

    $alt       = (string) $args['alt'];
    $extra_cls = trim((string) $args['class']);
    $size      = max(16, (int) $args['icon_size']);

    $svg_url = poke_hub_get_asset_url('bonus', $slug, 'svg');
    if ($svg_url !== '' && function_exists('pokehub_render_inline_svg_from_url')) {
        $svg_inner = pokehub_render_inline_svg_from_url(
            $svg_url,
            [
                'class'       => 'pokehub-bonus-icon pokehub-bonus-icon--svg',
                'aria_hidden' => true,
            ]
        );
        if ($svg_inner !== '') {
            $wrap_class = trim('pokehub-bonus-icon-wrap ' . $extra_cls);
            $label      = $alt !== '' ? $alt : $slug;
            $style      = sprintf('width:%dpx;height:%dpx;max-width:100%%;', $size, $size);

            return sprintf(
                '<span class="%s" role="img" aria-label="%s" style="%s">%s</span>',
                esc_attr($wrap_class),
                esc_attr($label),
                esc_attr($style),
                $svg_inner
            );
        }
    }

    $raster_class = trim('pokehub-bonus-icon pokehub-bonus-icon--raster ' . $extra_cls);

    return poke_hub_render_bucket_raster_img(
        'bonus',
        $slug,
        [
            'alt'      => $alt,
            'class'    => $raster_class,
            'width'    => $size,
            'height'   => $size,
            'loading'  => (string) $args['loading'],
            'decoding' => (string) $args['decoding'],
        ]
    );
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
 * Récupère l’URL préférée de l’icône d’un bonus (fichier .svg sur le bucket).
 *
 * @param string $slug Slug du bonus
 * @return string URL de l’icône
 */
function poke_hub_get_bonus_icon_url(string $slug): string {
    return poke_hub_get_asset_url('bonus', $slug, 'svg');
}

/**
 * URL de l’image bonbon d’une famille (fichier {slug}-candy.png dans le dossier configuré pour les candies).
 *
 * @param string $pokemon_slug Slug du Pokémon dont le nom porte le bonbon (souvent l’espèce de base de la lignée).
 */
function poke_hub_get_pokemon_candy_image_url(string $pokemon_slug): string {
    $slug = sanitize_title($pokemon_slug);
    if ($slug === '') {
        return '';
    }

    return poke_hub_get_asset_url('candies', $slug . '-candy', 'png');
}

/**
 * URL de l’image bonbon XL ({slug}-xl-candy.png, même dossier « Candies » que les bonbons classiques).
 */
function poke_hub_get_pokemon_xl_candy_image_url(string $pokemon_slug): string {
    $slug = sanitize_title($pokemon_slug);
    if ($slug === '') {
        return '';
    }

    return poke_hub_get_asset_url('candies', $slug . '-xl-candy', 'png');
}

/**
 * URL de l’image méga-énergie (slug.png dans le dossier configuré).
 */
function poke_hub_get_pokemon_mega_energy_image_url(string $pokemon_slug): string {
    $slug = sanitize_title($pokemon_slug);
    if ($slug === '') {
        return '';
    }

    return poke_hub_get_asset_url('mega_energies', $slug, 'png');
}

/**
 * Balises HTML autorisées pour le rendu bonbon / méga-énergie.
 *
 * @return array<string, array<string, bool>>
 */
function pokehub_pokemon_candy_reward_allowed_html(): array {
    return [
        'span' => [
            'class'       => true,
            'role'        => true,
            'aria-label'  => true,
            'aria-hidden' => true,
        ],
        'img'  => [
            'src'            => true,
            'alt'            => true,
            'class'          => true,
            'width'          => true,
            'height'         => true,
            'loading'        => true,
            'decoding'       => true,
            'data-ph-raster' => true,
        ],
    ];
}

/**
 * Indique si le marquage contient une image de ressource (bonbon / méga-énergie).
 */
function pokehub_pokemon_candy_reward_markup_has_image(string $html): bool {
    return $html !== '' && strpos($html, 'pokehub-pokemon-candy-img') !== false;
}

/**
 * @return string candy|xl_candy|mega_energy
 */
function pokehub_candy_resource_kind_from_reward_type(string $type): string {
    $type = sanitize_key($type);
    if ($type === 'mega_energy') {
        return 'mega_energy';
    }
    if ($type === 'xl_candy') {
        return 'xl_candy';
    }

    return 'candy';
}

/**
 * Libellé court pour l’affichage (traduisible, ex. FR : Bonbon / Bonbon XL).
 */
function pokehub_candy_resource_label_for_reward_type(string $type): string {
    $type = sanitize_key($type);
    if ($type === 'xl_candy') {
        /* translators: Pokémon GO XL Candy resource name */
        return __('XL Candy', 'poke-hub');
    }
    if ($type === 'mega_energy') {
        return __('Mega Energy', 'poke-hub');
    }

    return __('Candy', 'poke-hub');
}

/**
 * Icône asset (bonbon, bonbon XL ou méga-énergie) + quantité ×N ; texte de repli si pas d’URL.
 *
 * @param int    $pokemon_id ID du Pokémon associé à la ressource.
 * @param int    $quantity   Quantité affichée (×N).
 * @param string $kind       candy|xl_candy|mega_energy
 * @param array  $args       extra_class, img_size
 */
function pokehub_render_pokemon_candy_reward_html(int $pokemon_id, int $quantity, string $kind = 'candy', array $args = []): string {
    $args = wp_parse_args(
        $args,
        [
            'extra_class' => '',
            'img_size'    => 40,
        ]
    );

    $kind = sanitize_key($kind);
    if (!in_array($kind, ['candy', 'xl_candy', 'mega_energy'], true)) {
        $kind = 'candy';
    }
    $qty = max(1, $quantity);

    if ($pokemon_id <= 0 || !function_exists('pokehub_get_pokemon_data_by_id')) {
        return '';
    }

    $pdata = pokehub_get_pokemon_data_by_id($pokemon_id);
    if (!$pdata || empty($pdata['slug'])) {
        return '';
    }

    $slug = (string) $pdata['slug'];
    if ($kind === 'mega_energy') {
        $raster_slug = $slug;
        $raster_type = 'mega_energies';
    } elseif ($kind === 'xl_candy') {
        $raster_slug = $slug . '-xl-candy';
        $raster_type = 'candies';
    } else {
        $raster_slug = $slug . '-candy';
        $raster_type = 'candies';
    }

    $name_fr = isset($pdata['name_fr']) ? (string) $pdata['name_fr'] : '';
    $name_en = isset($pdata['name_en']) ? (string) $pdata['name_en'] : '';
    $pname   = $name_fr !== '' ? $name_fr : ($name_en !== '' ? $name_en : '');

    if ($kind === 'mega_energy') {
        $label = $pname !== ''
            ? sprintf(
                /* translators: 1: mega energy quantity, 2: Pokémon name */
                __('Mega Energy ×%1$d (%2$s)', 'poke-hub'),
                $qty,
                $pname
            )
            : sprintf(__('Mega Energy ×%d', 'poke-hub'), $qty);
        $fallback = $pname !== ''
            ? sprintf(
                /* translators: 1: Pokémon name, 2: quantity (formatted) */
                __('%1$s Mega Energy × %2$s', 'poke-hub'),
                $pname,
                number_format_i18n($qty)
            )
            : sprintf(
                /* translators: %s: quantity (formatted) */
                __('Mega Energy × %s', 'poke-hub'),
                number_format_i18n($qty)
            );
    } elseif ($kind === 'xl_candy') {
        $label = $pname !== ''
            ? sprintf(
                /* translators: 1: XL candy quantity, 2: Pokémon family name */
                __('Pokémon XL candy ×%1$d (%2$s)', 'poke-hub'),
                $qty,
                $pname
            )
            : sprintf(__('Pokémon XL candy ×%d', 'poke-hub'), $qty);
        $fallback = $pname !== ''
            ? sprintf(
                /* translators: 1: Pokémon name, 2: quantity (formatted) */
                __('%1$s XL Candy × %2$s', 'poke-hub'),
                $pname,
                number_format_i18n($qty)
            )
            : sprintf(
                /* translators: %s: quantity (formatted) */
                __('XL Candy × %s', 'poke-hub'),
                number_format_i18n($qty)
            );
    } else {
        $label = $pname !== ''
            ? sprintf(
                /* translators: 1: candy quantity, 2: Pokémon family name */
                __('Pokémon candy ×%1$d (%2$s)', 'poke-hub'),
                $qty,
                $pname
            )
            : sprintf(__('Pokémon candy ×%d', 'poke-hub'), $qty);
        $fallback = $pname !== ''
            ? sprintf(
                /* translators: 1: Pokémon name, 2: quantity (formatted) */
                __('%1$s Candy × %2$s', 'poke-hub'),
                $pname,
                number_format_i18n($qty)
            )
            : sprintf(
                /* translators: %s: quantity (formatted) */
                __('Candy × %s', 'poke-hub'),
                number_format_i18n($qty)
            );
    }

    $classes = trim('pokehub-pokemon-candy ' . (string) $args['extra_class']);
    $size    = (int) $args['img_size'];
    $size    = $size > 0 ? $size : 30;

    $img_tag = function_exists('poke_hub_render_bucket_raster_img')
        ? poke_hub_render_bucket_raster_img(
            $raster_type,
            $raster_slug,
            [
                'class'    => 'pokehub-pokemon-candy-img',
                'width'    => $size,
                'height'   => $size,
                'loading'  => 'lazy',
                'decoding' => 'async',
            ]
        )
        : '';

    if ($img_tag !== '') {
        $inner = sprintf(
            '<span class="%s" role="group" aria-label="%s">'
            . '%s'
            . '<span class="pokehub-pokemon-candy-qty" aria-hidden="true">×%s</span>'
            . '</span>',
            esc_attr($classes),
            esc_attr($label),
            $img_tag,
            esc_html((string) $qty)
        );
    } else {
        $inner = sprintf(
            '<span class="%s pokehub-pokemon-candy--text" role="group" aria-label="%s">%s</span>',
            esc_attr($classes),
            esc_attr($label),
            esc_html($fallback)
        );
    }

    return wp_kses($inner, pokehub_pokemon_candy_reward_allowed_html());
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
