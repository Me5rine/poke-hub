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
 * Ce préfixe sert aussi pour toutes les tables de contenu dérivées (content_eggs,
 * content_quests, content_bonus, content_habitats, etc.) : une seule base pour Pokémon
 * et tous les contenus des articles / événements.
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
 * Table catalogue des types de bonus (source de vérité).
 * Si un préfixe Pokémon distant est défini, on lit depuis le site principal (remote_bonus_types).
 * Sinon on lit la table locale (bonus_types) sur le site principal.
 *
 * @return string Nom de la table (bonus_types ou remote_bonus_types selon le contexte)
 */
function pokehub_get_bonus_types_table(): string {
    $prefix = function_exists('poke_hub_pokemon_get_table_prefix')
        ? (string) poke_hub_pokemon_get_table_prefix()
        : '';
    global $wpdb;
    $local_prefix = isset($wpdb->prefix) ? trim((string) $wpdb->prefix) : '';
    $prefix = trim($prefix);
    // Si le préfixe configuré est vide ou identique au préfixe local → on est sur le site principal
    if ($prefix === '' || $prefix === $local_prefix) {
        return function_exists('pokehub_get_table') ? pokehub_get_table('bonus_types') : '';
    }
    return function_exists('pokehub_get_table') ? pokehub_get_table('remote_bonus_types') : '';
}

/**
 * Indique si les types de bonus sont gérés localement (site principal) ou lus à distance.
 *
 * @return bool true si on lit les bonus depuis le site principal (préfixe distant configuré)
 */
function pokehub_bonus_use_remote_source(): bool {
    $prefix = function_exists('poke_hub_pokemon_get_table_prefix')
        ? (string) poke_hub_pokemon_get_table_prefix()
        : '';
    global $wpdb;
    $local_prefix = isset($wpdb->prefix) ? trim((string) $wpdb->prefix) : '';
    $prefix = trim($prefix);
    return $prefix !== '' && $prefix !== $local_prefix;
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
 *
 *  - Special events (même préfixe que la source Pokémon / tables content_*) :
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
        'pokemon_egg_types'          => ['scope' => 'local',  'suffix' => 'pokemon_egg_types'],
        'egg_types'                  => ['scope' => 'local',  'suffix' => 'pokemon_egg_types'],
        'pokemon_type_weather_links' => ['scope' => 'local',  'suffix' => 'pokemon_type_weather_links'],
        'pokemon_type_weakness_links' => ['scope' => 'local',  'suffix' => 'pokemon_type_weakness_links'],
        'pokemon_type_resistance_links' => ['scope' => 'local',  'suffix' => 'pokemon_type_resistance_links'],
        'pokemon_form_variants'      => ['scope' => 'local',  'suffix' => 'pokemon_form_variants'],
        'pokemon_form_variant_events' => ['scope' => 'local',  'suffix' => 'pokemon_form_variant_events'],
        'pokemon_pokemon_events'      => ['scope' => 'local',  'suffix' => 'pokemon_pokemon_events'],
        'evolutions'                  => ['scope' => 'local',  'suffix' => 'pokemon_evolutions'],
        'pokemon_evolutions'         => ['scope' => 'local',  'suffix' => 'pokemon_evolutions'],
        'items'                      => ['scope' => 'local',  'suffix' => 'items'],
        'pokemon_items'                      => ['scope' => 'local',  'suffix' => 'items'],
        'pokemon_backgrounds'        => ['scope' => 'local',  'suffix' => 'pokemon_backgrounds'],
        'backgrounds'               => ['scope' => 'local',  'suffix' => 'pokemon_backgrounds'],
        'pokemon_background_pokemon_links' => ['scope' => 'local',  'suffix' => 'pokemon_background_pokemon_links'],
        'pokemon_background_events' => ['scope' => 'local',  'suffix' => 'pokemon_background_events'],
        'pokemon_biomes'             => ['scope' => 'local',  'suffix' => 'pokemon_biomes'],
        'biomes'                     => ['scope' => 'local',  'suffix' => 'pokemon_biomes'],
        'pokemon_biome_images'       => ['scope' => 'local',  'suffix' => 'pokemon_biome_images'],
        'pokemon_biome_pokemon_links' => ['scope' => 'local',  'suffix' => 'pokemon_biome_pokemon_links'],
        'pokemon_regional_regions'   => ['scope' => 'local',  'suffix' => 'pokemon_regional_regions'],
        'pokemon_regional_mappings'  => ['scope' => 'local',  'suffix' => 'pokemon_regional_mappings'],

        // ==== Tables Events spéciaux (même préfixe que la source Pokémon / contenu) ====
        // Comme content_* : si "Pokémon table prefix (remote)" est défini, lecture/écriture sous ce préfixe
        // (pas de second jeu de tables sous le seul préfixe WP du site courant).
        'special_events'                => ['scope' => 'content_source', 'suffix' => 'special_events'],
        'special_event_pokemon'         => ['scope' => 'content_source', 'suffix' => 'special_event_pokemon'],
        'special_event_pokemon_attacks' => ['scope' => 'content_source', 'suffix' => 'special_event_pokemon_attacks'],
        'special_event_bonus'           => ['scope' => 'content_source', 'suffix' => 'special_event_bonus'],

        // ==== Tables de contenu communes (post, special_event, global_pool) ====
        // scope content_source = même préfixe que la "source Pokémon" (Réglages > Sources) pour que
        // les blocs (œufs, quêtes, bonus, etc.) sauvegardent en mode local ou remote dans la même base.
        'content_eggs'                   => ['scope' => 'content_source', 'suffix' => 'content_eggs'],
        'content_egg_pokemon'             => ['scope' => 'content_source', 'suffix' => 'content_egg_pokemon'],
        'content_quests'                  => ['scope' => 'content_source', 'suffix' => 'content_quests'],
        'content_quest_lines'             => ['scope' => 'content_source', 'suffix' => 'content_quest_lines'],
        'quest_groups'                    => ['scope' => 'content_source', 'suffix' => 'quest_groups'],
        'content_habitats'                => ['scope' => 'content_source', 'suffix' => 'content_habitats'],
        'content_habitat_entries'         => ['scope' => 'content_source', 'suffix' => 'content_habitat_entries'],
        'content_special_research'        => ['scope' => 'content_source', 'suffix' => 'content_special_research'],
        'content_special_research_steps'  => ['scope' => 'content_source', 'suffix' => 'content_special_research_steps'],
        'content_collection_challenges'   => ['scope' => 'content_source', 'suffix' => 'content_collection_challenges'],
        'content_collection_challenge_items' => ['scope' => 'content_source', 'suffix' => 'content_collection_challenge_items'],
        'content_bonus'                  => ['scope' => 'content_source', 'suffix' => 'content_bonus'],
        'content_bonus_entries'           => ['scope' => 'content_source', 'suffix' => 'content_bonus_entries'],
        'content_wild_pokemon'            => ['scope' => 'content_source', 'suffix' => 'content_wild_pokemon'],
        'content_wild_pokemon_entries'   => ['scope' => 'content_source', 'suffix' => 'content_wild_pokemon_entries'],
        'content_new_pokemon'             => ['scope' => 'content_source', 'suffix' => 'content_new_pokemon'],
        'content_new_pokemon_entries'     => ['scope' => 'content_source', 'suffix' => 'content_new_pokemon_entries'],
        'content_raids'                   => ['scope' => 'content_source', 'suffix' => 'content_raids'],
        'content_raid_bosses'             => ['scope' => 'content_source', 'suffix' => 'content_raid_bosses'],
        'content_go_pass'                => ['scope' => 'content_source', 'suffix' => 'content_go_pass'],
        // Liaison wp_posts/special_events → pass GO
        // Doit suivre le même préfixe que les tables de contenu (special_events / content_go_pass)
        // afin d'éviter un décalage local vs remote.
        'go_pass_host_links'             => ['scope' => 'content_source', 'suffix' => 'go_pass_host_links'],
        // Bloc "jour -> Pokémon(s) -> heures" (raids/oeufs/encens/leurres/heure vedette/quêtes...)
        'content_day_pokemon_hours'       => ['scope' => 'content_source', 'suffix' => 'content_day_pokemon_hours'],
        'content_day_pokemon_hour_entries'=> ['scope' => 'content_source', 'suffix' => 'content_day_pokemon_hour_entries'],
        // Alias ancien format (évite double préfixe si du code appelle get_table('pokehub_content_*'))
        'pokehub_content_eggs' => ['scope' => 'content_source', 'suffix' => 'content_eggs'],
        'pokehub_content_egg_pokemon' => ['scope' => 'content_source', 'suffix' => 'content_egg_pokemon'],
        'pokehub_content_quests' => ['scope' => 'content_source', 'suffix' => 'content_quests'],
        'pokehub_content_quest_lines' => ['scope' => 'content_source', 'suffix' => 'content_quest_lines'],
        'pokehub_quest_groups' => ['scope' => 'content_source', 'suffix' => 'quest_groups'],
        'pokehub_content_habitats' => ['scope' => 'content_source', 'suffix' => 'content_habitats'],
        'pokehub_content_habitat_entries' => ['scope' => 'content_source', 'suffix' => 'content_habitat_entries'],
        'pokehub_content_special_research' => ['scope' => 'content_source', 'suffix' => 'content_special_research'],
        'pokehub_content_special_research_steps' => ['scope' => 'content_source', 'suffix' => 'content_special_research_steps'],
        'pokehub_content_collection_challenges' => ['scope' => 'content_source', 'suffix' => 'content_collection_challenges'],
        'pokehub_content_collection_challenge_items' => ['scope' => 'content_source', 'suffix' => 'content_collection_challenge_items'],
        'pokehub_content_bonus' => ['scope' => 'content_source', 'suffix' => 'content_bonus'],
        'pokehub_content_bonus_entries' => ['scope' => 'content_source', 'suffix' => 'content_bonus_entries'],
        'pokehub_content_wild_pokemon' => ['scope' => 'content_source', 'suffix' => 'content_wild_pokemon'],
        'pokehub_content_wild_pokemon_entries' => ['scope' => 'content_source', 'suffix' => 'content_wild_pokemon_entries'],
        'pokehub_content_new_pokemon' => ['scope' => 'content_source', 'suffix' => 'content_new_pokemon'],
        'pokehub_content_new_pokemon_entries' => ['scope' => 'content_source', 'suffix' => 'content_new_pokemon_entries'],
        'pokehub_content_raids' => ['scope' => 'content_source', 'suffix' => 'content_raids'],
        'pokehub_content_raid_bosses' => ['scope' => 'content_source', 'suffix' => 'content_raid_bosses'],

        // ==== Tables locales Games ====
        'games_scores'                  => ['scope' => 'local', 'suffix' => 'games_scores'],
        'pokedle_daily'                 => ['scope' => 'local', 'suffix' => 'pokedle_daily'],
        'games_points'                  => ['scope' => 'local', 'suffix' => 'games_points'],

        // ==== Tables locales Collections Pokémon GO ====
        'collections'                   => ['scope' => 'local', 'suffix' => 'collections'],
        'collection_items'              => ['scope' => 'local', 'suffix' => 'collection_items'],

        // ==== Module Eggs (pools globaux) ====
        'global_egg_pools'              => ['scope' => 'local', 'suffix' => 'global_egg_pools'],
        'global_egg_pool_pokemon'       => ['scope' => 'local', 'suffix' => 'global_egg_pool_pokemon'],

        // ==== Tables distantes (JV Actu / remote WP) ====
        'remote_posts'              => ['scope' => 'remote', 'suffix' => 'posts'],
        'remote_postmeta'           => ['scope' => 'remote', 'suffix' => 'postmeta'],
        'remote_terms'              => ['scope' => 'remote', 'suffix' => 'terms'],
        'remote_termmeta'           => ['scope' => 'remote', 'suffix' => 'termmeta'],
        'remote_term_taxonomy'      => ['scope' => 'remote', 'suffix' => 'term_taxonomy'],
        'remote_term_relationships' => ['scope' => 'remote', 'suffix' => 'term_relationships'],
        'remote_as3cf_items'        => ['scope' => 'remote', 'suffix' => 'as3cf_items'],

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
        'remote_pokemon_regional_regions'   => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_regional_regions'],
        'remote_pokemon_regional_mappings'  => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_pokemon_regional_mappings'],
        'remote_bonus_types'                => ['scope' => 'remote_pokemon', 'suffix' => 'pokehub_bonus_types'],

        // ==== Catalogue des types de bonus (source de vérité sur le site principal) ====
        'bonus_types'                       => ['scope' => 'local', 'suffix' => 'bonus_types'],

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
    } elseif ($scope === 'content_source') {
        // Tables de contenu : même préfixe que les tables Pokémon (même base).
        // Centralise : pokémon nature, field research, habitats, bonus, nouveaux pokémon,
        // special research, collection challenges, œufs (articles + événements).
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $prefix = (string) poke_hub_pokemon_get_table_prefix();
            if (empty($prefix)) {
                $prefix = $wpdb->prefix;
            }
        } else {
            $prefix = $wpdb->prefix;
        }
        $table = $prefix . 'pokehub_' . $suffix;
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

    // LIKE interprète _ et % : sans échappement, les underscores du nom de table matchent n’importe quel caractère
    // (risque de faux positifs / requêtes lourdes). Même logique que les requêtes WP core sur information_schema.
    $like  = $wpdb->esc_like($table_name);
    $sql   = $wpdb->prepare('SHOW TABLES LIKE %s', $like);
    $found = $wpdb->get_var($sql);

    return ($found === $table_name);
}

/**
 * Comme pokehub_table_exists(), avec cache en mémoire pour la durée de la requête HTTP.
 * Limite les SHOW TABLES répétés et évite des erreurs SQL quand le schéma PokéHub n’est pas
 * installé sur la base courante (ex. environnement lab sans tables).
 *
 * @param string $table_name Nom complet de table (préfixe inclus).
 */
function pokehub_table_ready_cached(string $table_name): bool {
    static $cache = [];
    $table_name = trim($table_name);
    if ($table_name === '') {
        return false;
    }
    if (array_key_exists($table_name, $cache)) {
        return $cache[$table_name];
    }
    $cache[$table_name] = function_exists('pokehub_table_exists') && pokehub_table_exists($table_name);

    return $cache[$table_name];
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

/**
 * Purge cache for a module (Nginx Helper + WordPress object cache)
 * Generic function usable by all modules
 * 
 * @param array $shortcodes Array of shortcode names to find pages (e.g., ['poke_hub_events', 'poke_hub_friend_codes'])
 * @param string|null $cache_group WordPress cache group (e.g., 'poke_hub_events')
 * @param string|null $cache_key WordPress cache key (e.g., 'poke_hub_events_all')
 * @return bool True on success, false on failure
 */
function poke_hub_purge_module_cache(array $shortcodes, ?string $cache_group = null, ?string $cache_key = null): bool {
    if (empty($shortcodes)) {
        return false;
    }
    
    // Find all pages with these shortcodes
    $urls = [];
    if (function_exists('poke_hub_get_pages_with_shortcodes')) {
        $urls = poke_hub_get_pages_with_shortcodes($shortcodes);
    } else {
        // Fallback if helper not available yet
        $pages = get_pages(['post_status' => 'publish', 'number' => 50]);
        foreach ($pages as $page) {
            foreach ($shortcodes as $shortcode) {
                if (has_shortcode($page->post_content, $shortcode)) {
                    $urls[] = get_permalink($page->ID);
                    break; // Only add once per page
                }
            }
        }
    }
    
    // Also include current page if we're on a page with one of these shortcodes
    if (isset($_SERVER['REQUEST_URI'])) {
        $current_url = home_url($_SERVER['REQUEST_URI']);
        if (!in_array($current_url, $urls, true)) {
            $urls[] = $current_url;
        }
    }
    
    // Purge Nginx Helper cache for these specific URLs only (not the entire site)
    if (function_exists('poke_hub_purge_nginx_cache')) {
        poke_hub_purge_nginx_cache($urls, false);
    }
    
    // Purge WordPress object cache if cache group/key provided
    if (!empty($cache_group)) {
        if (!empty($cache_key)) {
            // Delete specific cache key
            wp_cache_delete($cache_key, $cache_group);
        }
        
        // Also clear the entire cache group if available (WordPress 6.1+)
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($cache_group);
        }
    }
    
    return true;
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
                _n('%s result', '%s results', $total_items, $text_domain),
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
                   aria-label="<?php esc_attr_e('First page', $text_domain); ?>">
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
                   aria-label="<?php esc_attr_e('Previous page', $text_domain); ?>">
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
                   aria-label="<?php esc_attr_e('Next page', $text_domain); ?>">
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
                   aria-label="<?php esc_attr_e('Last page', $text_domain); ?>">
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

/**
 * Normalise une date de sortie au format ISO YYYY-MM-DD (format attendu en base).
 * Accepte en entrée JJ/MM/AAAA ou YYYY-MM-DD.
 *
 * @param string $date Chaîne du type "06/07/2016" ou "2016-07-06".
 * @return string "Y-m-d" ou chaîne vide si invalide.
 */
function poke_hub_normalize_release_date(string $date): string {
    $date = trim($date);
    if ($date === '') {
        return '';
    }
    if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $date)) {
        return $date;
    }
    $d = DateTime::createFromFormat('d/m/Y', $date);
    if ($d instanceof DateTime) {
        return $d->format('Y-m-d');
    }
    return '';
}
