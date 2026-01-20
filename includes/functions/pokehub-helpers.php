<?php
// File: includes/functions/pokehub-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vérifie si un module Poké HUB est actif.
 *
 * @param string $module Slug du module (ex: "pokedex", "events").
 * @return bool
 */
function poke_hub_is_module_active(string $module): bool {
    $active_modules = get_option('poke_hub_active_modules', []);
    return is_array($active_modules) && in_array($module, $active_modules, true);
}

/**
 * Helper générique pour récupérer le registry des modules.
 * Wrapper sur poke_hub_get_modules_registry() pour éviter les erreurs fatales.
 *
 * @return array<string,string>
 */
function poke_hub_get_modules_registry_safe(): array {
    return function_exists('poke_hub_get_modules_registry')
        ? poke_hub_get_modules_registry()
        : [];
}

/**
 * Récupère le préfixe des tables distantes.
 *
 * Contexte possible :
 *  - 'events'      → posts, postmeta, special_events, etc.
 *  - 'event_types' → terms, termmeta, term_taxonomy, term_relationships
 *
 * Options :
 *  - poke_hub_events_remote_prefix       (events)
 *  - poke_hub_event_types_remote_prefix  (event types) [optionnelle]
 *
 * Si l'option du contexte est vide, on retombe sur :
 *  - l'autre option (events ↔ event_types)
 *  - puis sur $wpdb->prefix (dev local)
 */
function poke_hub_events_get_table_prefix(string $context = 'events'): string {
    global $wpdb;

    $context = ($context === 'event_types') ? 'event_types' : 'events';

    if ($context === 'event_types') {
        // 1) Préfixe spécifique aux event types
        $prefix = (string) get_option('poke_hub_event_types_remote_prefix', '');
        $prefix = trim($prefix);

        // 2) Si vide → on retombe sur le préfixe des events "généraux"
        if ($prefix === '') {
            $prefix = (string) get_option('poke_hub_events_remote_prefix', '');
            $prefix = trim($prefix);
        }
    } else {
        // Contexte "events"
        $prefix = (string) get_option('poke_hub_events_remote_prefix', '');
        $prefix = trim($prefix);

        // Si vide → on tentera éventuellement l'autre option plus bas
    }

    // Si toujours vide → fallback sur l'autre option
    if ($prefix === '') {
        if ($context === 'events') {
            $prefix = (string) get_option('poke_hub_event_types_remote_prefix', '');
        } else {
            $prefix = (string) get_option('poke_hub_events_remote_prefix', '');
        }
        $prefix = trim($prefix);
    }

    // Si toujours vide → fallback dev : préfixe local
    if ($prefix === '') {
        return $wpdb->prefix;
    }

    // Tu peux décider si tu forces ou non le trailing underscore ici.
    // Si tu veux être safe :
    // if (substr($prefix, -1) !== '_') {
    //     $prefix .= '_';
    // }

    return $prefix;
}

/**
 * Récupère le préfixe des tables Pokémon distantes.
 *
 * Option :
 *  - poke_hub_pokemon_remote_prefix (tables Pokémon)
 *
 * Si l'option est vide, on retombe sur $wpdb->prefix (préfixe local).
 *
 * @return string Préfixe des tables Pokémon
 */
function poke_hub_pokemon_get_table_prefix(): string {
    global $wpdb;
    
    // Sécurité : vérifier que $wpdb est disponible
    if (!isset($wpdb) || !is_object($wpdb)) {
        return '';
    }

    $prefix = (string) get_option('poke_hub_pokemon_remote_prefix', '');
    $prefix = trim($prefix);

    // Si vide → fallback dev : préfixe local
    if ($prefix === '') {
        return $wpdb->prefix;
    }

    return $prefix;
}

/**
 * Récupère le préfixe des tables globales partagées entre tous les sites.
 *
 * Utilise la constante ME5RINE_LAB_GLOBAL_PREFIX définie dans wp-config.php,
 * ou ME5RINE_LAB_CUSTOM_PREFIX si elle est définie.
 *
 * Si aucune constante n'est définie, on retombe sur $wpdb->prefix (préfixe local).
 *
 * @return string Préfixe des tables globales
 */
function poke_hub_global_get_table_prefix(): string {
    global $wpdb;
    
    // Sécurité : vérifier que $wpdb est disponible
    if (!isset($wpdb) || !is_object($wpdb)) {
        return '';
    }

    // Utiliser ME5RINE_LAB_CUSTOM_PREFIX si défini, sinon ME5RINE_LAB_GLOBAL_PREFIX
    if (defined('ME5RINE_LAB_CUSTOM_PREFIX')) {
        $prefix = ME5RINE_LAB_CUSTOM_PREFIX;
    } elseif (defined('ME5RINE_LAB_GLOBAL_PREFIX')) {
        $prefix = ME5RINE_LAB_GLOBAL_PREFIX;
    } else {
        // Fallback : préfixe local
        $prefix = $wpdb->prefix;
    }

    $prefix = trim((string) $prefix);

    // Si vide → fallback dev : préfixe local
    if ($prefix === '') {
        return $wpdb->prefix;
    }

    return $prefix;
}

/**
 * Helper unique pour retourner le nom réel d'une table,
 * locale (wp_...pokehub_*) ou distante (JV Actu / BDD remote).
 *
 * Usage :
 *  - Tables locales PokéHub :
 *      pokehub_get_table('pokemon');
 *      pokehub_get_table('pokemon_types');
 *      pokehub_get_table('special_events');
 *      pokehub_get_table('special_event_pokemon');
 *
 *  - Tables distantes (remote, JV Actu) :
 *      pokehub_get_table('remote_posts');
 *      pokehub_get_table('remote_postmeta');
 *      pokehub_get_table('remote_terms');
 *      pokehub_get_table('remote_termmeta');
 *      pokehub_get_table('remote_term_taxonomy');
 *      pokehub_get_table('remote_term_relationships');
 *      pokehub_get_table('remote_as3cf_items');
 *
 * @param string $key Clé logique.
 * @return string Nom complet de la table (avec préfixe). Chaîne vide si clé vide.
 */
function pokehub_get_table(string $key): string {
    static $cache = [];

    global $wpdb;
    
    // Sécurité : vérifier que $wpdb est disponible et que WordPress est chargé
    if (!isset($wpdb) || !is_object($wpdb)) {
        return '';
    }
    
    // Vérifier que le préfixe existe (peut être vide dans certains contextes rares, mais $wpdb doit exister)
    if (!isset($wpdb->prefix)) {
        return '';
    }
    
    // Vérifier que les fonctions WordPress de base sont disponibles
    if (!function_exists('get_option')) {
        return '';
    }

    $key = trim($key);
    if ($key === '') {
        return '';
    }

    // Cache simple
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    /**
     * Mapping central minimal.
     *
     * scope:
     *  - local  => tables du plugin, préfixées par "{$wpdb->prefix}pokehub_{$suffix}"
     *  - remote => tables distantes (JV Actu), préfixées par un helper d'events
     */
    $tables = [

        // ==== Tables locales Pokédex / Pokémon ====
        'pokemon'                    => ['scope' => 'local',  'suffix' => 'pokemon'],
        'pokemon_types'              => ['scope' => 'local',  'suffix' => 'pokemon_types'],
        'regions'                    => ['scope' => 'local',  'suffix' => 'regions'],
        'pokemon_regions'            => ['scope' => 'local',  'suffix' => 'regions'],         // alias
        'generations'                => ['scope' => 'local',  'suffix' => 'generations'],
        'pokemon_generations'        => ['scope' => 'local',  'suffix' => 'generations'],     // alias
        'attacks'                    => ['scope' => 'local',  'suffix' => 'attacks'],
        'pokemon_attacks'            => ['scope' => 'local',  'suffix' => 'attacks'],         // alias
        'attack_stats'               => ['scope' => 'local',  'suffix' => 'attack_stats'],
        'pokemon_attack_stats'       => ['scope' => 'local',  'suffix' => 'attack_stats'],    // alias
        'attack_type_links'          => ['scope' => 'local',  'suffix' => 'attack_type_links'],
        'pokemon_type_links'         => ['scope' => 'local',  'suffix' => 'pokemon_type_links'],
        'pokemon_attack_links'       => ['scope' => 'local',  'suffix' => 'pokemon_attack_links'],
        'pokemon_weathers'           => ['scope' => 'local',  'suffix' => 'pokemon_weathers'],
        'weathers'                   => ['scope' => 'local',  'suffix' => 'pokemon_weathers'],
        'pokemon_type_weather_links' => ['scope' => 'local',  'suffix' => 'pokemon_type_weather_links'],
        'pokemon_type_weakness_links' => ['scope' => 'local',  'suffix' => 'pokemon_type_weakness_links'],
        'pokemon_type_resistance_links' => ['scope' => 'local',  'suffix' => 'pokemon_type_resistance_links'],
        'pokemon_form_variants'      => ['scope' => 'local',  'suffix' => 'pokemon_form_variants'],
        'evolutions'                 => ['scope' => 'local',  'suffix' => 'pokemon_evolutions'],
        'pokemon_evolutions'         => ['scope' => 'local',  'suffix' => 'pokemon_evolutions'],
        'items'                      => ['scope' => 'local',  'suffix' => 'items'],
        'pokemon_items'                      => ['scope' => 'local',  'suffix' => 'items'],
        'pokemon_backgrounds'        => ['scope' => 'local',  'suffix' => 'pokemon_backgrounds'],
        'backgrounds'               => ['scope' => 'local',  'suffix' => 'pokemon_backgrounds'],
        'pokemon_background_pokemon_links' => ['scope' => 'local',  'suffix' => 'pokemon_background_pokemon_links'],
        'pokemon_regional_regions'   => ['scope' => 'local',  'suffix' => 'pokemon_regional_regions'],
        'pokemon_regional_mappings'  => ['scope' => 'local',  'suffix' => 'pokemon_regional_mappings'],

        //=== table de mapping des formes / costumes / clones ===
        'pokemon_form_mappings'      => ['scope' => 'local',  'suffix' => 'pokemon_form_mappings'],
        'form_mappings'              => ['scope' => 'local',  'suffix' => 'pokemon_form_mappings'],

        // ==== Tables locales Events spéciaux ====
        'special_events'                => ['scope' => 'local', 'suffix' => 'special_events'],
        'special_event_pokemon'         => ['scope' => 'local', 'suffix' => 'special_event_pokemon'],
        'special_event_pokemon_attacks' => ['scope' => 'local', 'suffix' => 'special_event_pokemon_attacks'],
        'special_event_bonus'           => ['scope' => 'local', 'suffix' => 'special_event_bonus'],

        // ==== Tables locales Games ====
        'games_scores'                  => ['scope' => 'local', 'suffix' => 'games_scores'],
        'pokedle_daily'                 => ['scope' => 'local', 'suffix' => 'pokedle_daily'],
        'games_points'                  => ['scope' => 'local', 'suffix' => 'games_points'],

        // ==== Tables distantes (JV Actu / remote WP) ====
        'remote_posts'              => ['scope' => 'remote', 'suffix' => 'posts'],
        'remote_postmeta'           => ['scope' => 'remote', 'suffix' => 'postmeta'],
        'remote_terms'              => ['scope' => 'remote', 'suffix' => 'terms'],
        'remote_termmeta'           => ['scope' => 'remote', 'suffix' => 'termmeta'],
        'remote_term_taxonomy'      => ['scope' => 'remote', 'suffix' => 'term_taxonomy'],
        'remote_term_relationships' => ['scope' => 'remote', 'suffix' => 'term_relationships'],
        'remote_as3cf_items'        => ['scope' => 'remote', 'suffix' => 'as3cf_items'],

        // ==== Tables distantes Events spéciaux ====
        'remote_special_events'                => ['scope' => 'remote', 'suffix' => 'pokehub_special_events'],
        'remote_special_event_pokemon'         => ['scope' => 'remote', 'suffix' => 'pokehub_special_event_pokemon'],
        'remote_special_event_pokemon_attacks' => ['scope' => 'remote', 'suffix' => 'pokehub_special_event_pokemon_attacks'],
        'remote_special_event_bonus'           => ['scope' => 'remote', 'suffix' => 'pokehub_special_event_bonus'],

        // ==== Tables distantes Pokémon ====
        'remote_pokemon'                    => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon'],
        'remote_pokemon_types'              => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_types'],
        'remote_regions'                    => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_regions'],
        'remote_generations'                => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_generations'],
        'remote_attacks'                    => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_attacks'],
        'remote_attack_stats'               => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_attack_stats'],
        'remote_attack_type_links'          => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_attack_type_links'],
        'remote_pokemon_type_links'         => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_type_links'],
        'remote_pokemon_attack_links'        => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_attack_links'],
        'remote_pokemon_weathers'           => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_weathers'],
        'remote_pokemon_type_weather_links' => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_type_weather_links'],
        'remote_pokemon_type_weakness_links' => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_type_weakness_links'],
        'remote_pokemon_type_resistance_links' => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_type_resistance_links'],
        'remote_pokemon_form_variants'      => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_form_variants'],
        'remote_pokemon_evolutions'          => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_evolutions'],
        'remote_items'                     => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_items'],
        'remote_pokemon_backgrounds'       => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_backgrounds'],
        'remote_pokemon_background_pokemon_links' => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_background_pokemon_links'],
        'remote_pokemon_form_mappings'      => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_form_mappings'],
        'remote_pokemon_regional_regions'   => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_regional_regions'],
        'remote_pokemon_regional_mappings'  => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_regional_mappings'],

        // ==== Tables globales partagées (ME5RINE_LAB_GLOBAL_PREFIX) ====
        'user_profiles'                    => ['scope' => 'global', 'suffix' => 'pokehub_user_profiles'],
    ];

    $scope  = 'local';
    $suffix = '';

    if (isset($tables[$key])) {
        $scope  = $tables[$key]['scope'];
        $suffix = $tables[$key]['suffix'];
    } else {
        /**
         * Fallback minimal et conservateur :
         *  - si la clé commence par "remote_", on traite comme table distante events par défaut
         *  - Seules les clés explicitement mappées ci-dessus utilisent le scope remote_pokemon
         *  - sinon, on considère que c'est un suffix local direct
         *
         * Exemple :
         *  - "remote_pokemon"  => déjà dans le mapping → scope remote_pokemon
         *  - "remote_posts"     => scope remote, suffix "posts"
         *  - "remote_special_event_pokemon" => scope remote (car pas dans le mapping)
         *  - "my_custom"        => scope local, suffix "my_custom"
         */
        if (strpos($key, 'remote_') === 0) {
            // Par défaut, toutes les clés remote_ non mappées sont des tables events
            $scope = 'remote';
            $suffix = substr($key, strlen('remote_'));
        } else {
            $scope  = 'local';
            $suffix = $key;
        }
    }

    if ($suffix === '') {
        return '';
    }

    // Construction du nom de table selon le scope
    if ($scope === 'global') {
        // Tables globales partagées : utiliser le préfixe global ME5RINE_LAB_GLOBAL_PREFIX
        if (function_exists('poke_hub_global_get_table_prefix')) {
            $prefix = (string) poke_hub_global_get_table_prefix();
            // Si le préfixe est vide (erreur ou $wpdb non disponible), utiliser le préfixe local
            if (empty($prefix)) {
                $prefix = $wpdb->prefix;
            }
        } else {
            // Si la fonction n'existe pas, utiliser le préfixe local (fallback)
            $prefix = $wpdb->prefix;
        }

        $table = $prefix . $suffix;
    } elseif ($scope === 'remote_pokemon') {
        // Tables Pokémon distantes : utiliser le préfixe Pokémon
        // Vérifier d'abord si un préfixe distant est vraiment configuré
        $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
        $pokemon_remote_prefix = trim($pokemon_remote_prefix);
        
        if (!empty($pokemon_remote_prefix) && function_exists('poke_hub_pokemon_get_table_prefix')) {
            $prefix = (string) poke_hub_pokemon_get_table_prefix();
            // Si le préfixe est vide (erreur ou $wpdb non disponible), utiliser le préfixe local
            if (empty($prefix)) {
                $prefix = $wpdb->prefix;
            }
        } else {
            // Si pas de préfixe distant configuré, utiliser le préfixe local (fallback)
            $prefix = $wpdb->prefix;
        }

        $table = $prefix . $suffix;
        
        // Debug temporaire pour vérifier la construction de la table
        error_log('[POKE-HUB] pokehub_get_table - remote_pokemon - key: ' . $key . ', prefix: ' . $prefix . ', suffix: ' . $suffix . ', table finale: ' . $table);
    } elseif ($scope === 'remote') {
        // Tables distantes Events : utiliser le préfixe events
        // Détermination du contexte : "events" vs "event_types"
        $context = 'events';

        // Toutes les tables de taxonomie → event_types
        $event_type_suffixes = [
            'terms',
            'termmeta',
            'term_taxonomy',
            'term_relationships',
        ];

        // On teste sur $suffix (pas la clé) car tu peux avoir d'autres mappings
        if (in_array($suffix, $event_type_suffixes, true)) {
            $context = 'event_types';
        }

        // Synthèse du préfixe
        if (function_exists('pokehub_events_get_table_prefix')) {
            $prefix = (string) pokehub_events_get_table_prefix($context);
        } elseif (function_exists('poke_hub_events_get_table_prefix')) {
            $prefix = (string) poke_hub_events_get_table_prefix($context);
        } else {
            $prefix = $wpdb->prefix;
        }

        $table = $prefix . $suffix;
    } else {
        // scope "local" : tables PokéHub
        $table = $wpdb->prefix . 'pokehub_' . $suffix;
    }

    $cache[$key] = $table;

    return $table;
}

/**
 * Vérifie si une table existe dans la base courante.
 *
 * @param string $table_name Nom complet (avec préfixe), ex: wp_pokehub_pokemon
 * @return bool
 */
function pokehub_table_exists(string $table_name): bool {
    global $wpdb;

    $table_name = trim($table_name);
    if ($table_name === '') {
        return false;
    }

    // SHOW TABLES LIKE 'xxx'
    $sql   = $wpdb->prepare('SHOW TABLES LIKE %s', $table_name);
    $found = $wpdb->get_var($sql);

    return ($found === $table_name);
}

/**
 * Génère le HTML de pagination selon la documentation PLUGIN_INTEGRATION.md
 * 
 * Template global réutilisable pour toutes les paginations front-end.
 * Utilise les classes génériques me5rine-lab-pagination-*.
 * 
 * @param array $args {
 *     Arguments de la pagination
 *     
 *     @type int    $total_items  Nombre total d'éléments
 *     @type int    $paged        Page courante (défaut: 1)
 *     @type int    $total_pages  Nombre total de pages
 *     @type string $page_var     Nom de la variable GET pour la pagination (défaut: 'pg')
 *     @type string $text_domain  Domaine de traduction (défaut: 'poke-hub')
 * }
 * @return string HTML de la pagination (vide si total_pages <= 1)
 */
/**
 * Purge Nginx Helper cache for specific URLs or patterns
 * This is a global function that can be used across all modules (events, user-profiles, etc.)
 * 
 * @param array|string $urls URLs to purge (string for single URL, array for multiple)
 *                           If empty array, purges only current page
 *                           If null/empty, purges all cache (use with caution)
 * @param bool $purge_all If true, purges entire cache regardless of URLs (use with caution)
 * @return bool True if purge was attempted, false otherwise
 */
function poke_hub_purge_nginx_cache($urls = null, $purge_all = false) {
    // If explicitly requested to purge all
    if ($purge_all) {
        // Purge Nginx Helper cache completely if available
        if (function_exists('rt_wp_nginx_helper_purge_all')) {
            rt_wp_nginx_helper_purge_all();
            return true;
        }
        
        // Alternative: Use action hook if available
        if (has_action('rt_nginx_helper_purge_all')) {
            do_action('rt_nginx_helper_purge_all');
            return true;
        }
        
        // Fallback: WordPress cache flush
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            return true;
        }
        
        return false;
    }
    
    // Normalize URLs parameter
    $urls_to_purge = [];
    
    if ($urls === null) {
        // No URLs provided - purge only current page
        if (isset($_SERVER['REQUEST_URI'])) {
            $urls_to_purge[] = home_url($_SERVER['REQUEST_URI']);
        }
    } elseif (is_string($urls)) {
        // Single URL provided
        $urls_to_purge[] = $urls;
    } elseif (is_array($urls)) {
        // Multiple URLs provided
        $urls_to_purge = array_filter(array_map('trim', $urls));
    }
    
    // If no URLs to purge, return false
    if (empty($urls_to_purge)) {
        return false;
    }
    
    // Try to purge specific URLs
    if (function_exists('rt_wp_nginx_helper_purge_url')) {
        foreach ($urls_to_purge as $url) {
            rt_wp_nginx_helper_purge_url($url);
        }
        return true;
    }
    
    // Alternative: Try action hook for URL-specific purge
    if (has_action('rt_nginx_helper_purge_url')) {
        foreach ($urls_to_purge as $url) {
            do_action('rt_nginx_helper_purge_url', $url);
        }
        return true;
    }
    
    // Fallback: Purge all if URL-specific purge is not available
    // (better to purge all than nothing, but log it)
    if (function_exists('rt_wp_nginx_helper_purge_all')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PokeHub] URL-specific purge not available, purging all cache instead');
        }
        rt_wp_nginx_helper_purge_all();
        return true;
    }
    
    // Last resort: WordPress cache flush
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
        return true;
    }
    
    return false;
}

/**
 * Find pages/posts containing specific shortcodes and return their URLs
 * Useful for purging cache of pages that display dynamic content
 * 
 * @param array $shortcodes Array of shortcode names (e.g., ['poke_hub_friend_codes', 'poke_hub_vivillon'])
 * @return array Array of URLs
 */
function poke_hub_get_pages_with_shortcodes(array $shortcodes) {
    if (empty($shortcodes)) {
        return [];
    }
    
    $urls = [];
    
    // Search in published pages
    $pages = get_pages([
        'post_status' => 'publish',
        'number' => 100, // Limit to avoid too many queries
    ]);
    
    foreach ($pages as $page) {
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($page->post_content, $shortcode)) {
                $urls[] = get_permalink($page->ID);
                break; // Only add once per page
            }
        }
    }
    
    // Also search in published posts
    $posts = get_posts([
        'post_status' => 'publish',
        'numberposts' => 100,
        'post_type' => 'any',
    ]);
    
    foreach ($posts as $post) {
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                $urls[] = get_permalink($post->ID);
                break; // Only add once per post
            }
        }
    }
    
    // Remove duplicates and return
    return array_unique(array_filter($urls));
}

function poke_hub_render_pagination(array $args = []): string {
    $args = wp_parse_args($args, [
        'total_items' => 0,
        'paged'        => 1,
        'total_pages'  => 1,
        'page_var'     => 'pg',
        'text_domain'  => 'poke-hub',
    ]);

    $total_items = max(0, (int) $args['total_items']);
    $paged       = max(1, (int) $args['paged']);
    $total_pages = max(1, (int) $args['total_pages']);
    $page_var    = sanitize_key($args['page_var']) ?: 'pg';
    $text_domain = sanitize_text_field($args['text_domain']) ?: 'poke-hub';

    // Ne pas afficher si une seule page ou moins
    if ($total_pages <= 1) {
        return '';
    }

    // S'assurer que paged ne dépasse pas total_pages
    if ($paged > $total_pages) {
        $paged = $total_pages;
    }

    ob_start();
    ?>
    <div class="me5rine-lab-pagination">
        <span class="me5rine-lab-pagination-info">
            <?php
            printf(
                /* translators: %s: number of items */
                _n('%s résultat', '%s résultats', $total_items, $text_domain),
                number_format_i18n($total_items)
            );
            ?>
        </span>
        <div class="me5rine-lab-pagination-links">
            <?php
            // Bouton première page
            if ($paged > 1) :
                ?>
                <a href="<?php echo esc_url(add_query_arg($page_var, 1)); ?>" 
                   class="me5rine-lab-pagination-button" 
                   aria-label="<?php esc_attr_e('Première page', $text_domain); ?>">
                    <span aria-hidden="true">«</span>
                </a>
                <?php
            else :
                ?>
                <span class="me5rine-lab-pagination-button disabled" aria-hidden="true">«</span>
                <?php
            endif;

            // Bouton précédente
            if ($paged > 1) :
                ?>
                <a href="<?php echo esc_url(add_query_arg($page_var, $paged - 1)); ?>" 
                   class="me5rine-lab-pagination-button" 
                   aria-label="<?php esc_attr_e('Page précédente', $text_domain); ?>">
                    <span aria-hidden="true">‹</span>
                </a>
                <?php
            else :
                ?>
                <span class="me5rine-lab-pagination-button disabled" aria-hidden="true">‹</span>
                <?php
            endif;

            // Page actuelle
            ?>
            <span class="me5rine-lab-pagination-button active">
                <?php echo esc_html($paged); ?>
            </span>
            <?php

            // Bouton suivante
            if ($paged < $total_pages) :
                ?>
                <a href="<?php echo esc_url(add_query_arg($page_var, $paged + 1)); ?>" 
                   class="me5rine-lab-pagination-button" 
                   aria-label="<?php esc_attr_e('Page suivante', $text_domain); ?>">
                    <span aria-hidden="true">›</span>
                </a>
                <?php
            else :
                ?>
                <span class="me5rine-lab-pagination-button disabled" aria-hidden="true">›</span>
                <?php
            endif;

            // Bouton dernière page
            if ($paged < $total_pages) :
                ?>
                <a href="<?php echo esc_url(add_query_arg($page_var, $total_pages)); ?>" 
                   class="me5rine-lab-pagination-button" 
                   aria-label="<?php esc_attr_e('Dernière page', $text_domain); ?>">
                    <span aria-hidden="true">»</span>
                </a>
                <?php
            else :
                ?>
                <span class="me5rine-lab-pagination-button disabled" aria-hidden="true">»</span>
                <?php
            endif;
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
