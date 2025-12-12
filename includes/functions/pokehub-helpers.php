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

        // 2) Si vide → on retombe sur le préfixe des events “généraux”
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
        'pokemon_form_variants'      => ['scope' => 'local',  'suffix' => 'pokemon_form_variants'],
        'evolutions'                 => ['scope' => 'local',  'suffix' => 'pokemon_evolutions'],
        'pokemon_evolutions'         => ['scope' => 'local',  'suffix' => 'pokemon_evolutions'],
        'items'                      => ['scope' => 'local',  'suffix' => 'items'],
        'pokemon_items'                      => ['scope' => 'local',  'suffix' => 'items'],

        //=== table de mapping des formes / costumes / clones ===
        'pokemon_form_mappings'      => ['scope' => 'local',  'suffix' => 'pokemon_form_mappings'],
        'form_mappings'              => ['scope' => 'local',  'suffix' => 'pokemon_form_mappings'],

        // ==== Tables locales Events spéciaux ====
        'special_events'                => ['scope' => 'local', 'suffix' => 'special_events'],
        'special_event_pokemon'         => ['scope' => 'local', 'suffix' => 'special_event_pokemon'],
        'special_event_pokemon_attacks' => ['scope' => 'local', 'suffix' => 'special_event_pokemon_attacks'],
        'special_event_bonus'           => ['scope' => 'local', 'suffix' => 'special_event_bonus'],

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
    ];

    $scope  = 'local';
    $suffix = '';

    if (isset($tables[$key])) {
        $scope  = $tables[$key]['scope'];
        $suffix = $tables[$key]['suffix'];
    } else {
        /**
         * Fallback minimal :
         *  - si la clé commence par "remote_", on considère que c'est une table distante
         *    avec suffix = tout ce qui suit "remote_"
         *  - sinon, on considère que c'est un suffix local direct
         *
         * Exemple :
         *  - "remote_foo"  => scope remote, suffix "foo"
         *  - "my_custom"   => scope local, suffix "my_custom"
         */
        if (strpos($key, 'remote_') === 0) {
            $scope  = 'remote';
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
    if ($scope === 'remote') {

        // Détermination du contexte : "events" vs "event_types"
        $context = 'events';

        // Toutes les tables de taxonomie → event_types
        $event_type_suffixes = [
            'terms',
            'termmeta',
            'term_taxonomy',
            'term_relationships',
        ];

        // On teste sur $suffix (pas la clé) car tu peux avoir d’autres mappings
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
