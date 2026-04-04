<?php
// File: /includes/pokehub-db.php

if (!defined('ABSPATH')) exit;

/**
 * Class Pokehub_DB
 * Handler central des tables Poké HUB (par module).
 */
class Pokehub_DB {

    /**
     * Singleton
     *
     * @var Pokehub_DB
     */
    private static $_instance;

    /**
     * @return Pokehub_DB
     */
    public static function getInstance() {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Crée les tables uniquement pour les modules activés.
     *
     * @param array $active_modules ex: ['events', 'bonus', 'pokemon']
     */
    public function createTables($active_modules = []) {

        // Exemple pour d'autres modules plus tard :
        // if (in_array('events', $active_modules, true)) { ... }

        if (in_array('pokemon', $active_modules, true)) {
            $this->createPokemonTables();
        }

        if (in_array('events', $active_modules, true)) {
            $this->createEventsTables();
        }

        // Tables de contenu communes (post, special_event, global_pool) — œufs, quêtes, raids, etc.
        // Les blocs utilisent aussi ces tables (ex: Day Pokémon Hours / Featured Hours).
        if (
            in_array('events', $active_modules, true)
            || in_array('eggs', $active_modules, true)
            || in_array('quests', $active_modules, true)
            || in_array('blocks', $active_modules, true)
        ) {
            $this->createContentTables();
        }

        // Catalogue des types de bonus (source de vérité, lue à distance via même préfixe que Pokémon)
        // Créée si module Bonus OU Blocks actif (le bloc bonus fonctionne sans le module Bonus)
        if (in_array('bonus', $active_modules, true) || in_array('blocks', $active_modules, true)) {
            $this->createBonusTypesTable();
        }

        if (in_array('user-profiles', $active_modules, true)) {
            $this->createUserProfilesTables();
        }

        if (in_array('games', $active_modules, true)) {
            $this->createGamesTables();
        }

        if (in_array('collections', $active_modules, true)) {
            $this->createCollectionsTables();
        }

        // Plus de global_egg_pools : les œufs (y compris pools globaux) sont dans content_eggs.
        // if (in_array('eggs', $active_modules, true)) { $this->createEggsTables(); }
    }

    /**
     * Création de TOUTES les tables liées aux Pokémon :
     * - pokemon
     * - pokemon_types
     * - regions
     * - generations
     * - attacks
     * - attack_stats
     * - pokemon_type_links
     * - attack_type_links
     * - pokemon_attack_links
     * - pokemon_weathers
     * - pokemon_type_weather_links
     * - pokemon_type_weakness_links
     * - pokemon_type_resistance_links
     * - pokemon_regional_regions (ensembles de pays géographiques)
     * - pokemon_regional_mappings (mapping pattern Vivillon => pays/régions)
     */
    private function createPokemonTables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $pokemon_table         = pokehub_get_table('pokemon');
        $types_table           = pokehub_get_table('pokemon_types');
        $regions_table         = pokehub_get_table('regions');
        $gens_table            = pokehub_get_table('generations');
        $attacks_table         = pokehub_get_table('attacks');
        $attack_stats_table    = pokehub_get_table('attack_stats');
        $attack_type_links     = pokehub_get_table('attack_type_links');
        $pokemon_type_links    = pokehub_get_table('pokemon_type_links');
        $pokemon_attack_links  = pokehub_get_table('pokemon_attack_links');
        $weathers_table        = pokehub_get_table('pokemon_weathers');
        $egg_types_table       = pokehub_get_table('pokemon_egg_types');
        $type_weather_links    = pokehub_get_table('pokemon_type_weather_links');
        $type_weakness_links   = pokehub_get_table('pokemon_type_weakness_links');
        $type_resistance_links = pokehub_get_table('pokemon_type_resistance_links');
        $pokemon_form_variants = pokehub_get_table('pokemon_form_variants');
        $evolutions_table      = pokehub_get_table('pokemon_evolutions');
        $items_table           = pokehub_get_table('items');
        $backgrounds_table     = pokehub_get_table('pokemon_backgrounds');
        $background_pokemon_links = pokehub_get_table('pokemon_background_pokemon_links');
        $background_events_table = pokehub_get_table('pokemon_background_events');
        $form_variant_events_table = pokehub_get_table('pokemon_form_variant_events');
        $pokemon_events_table = pokehub_get_table('pokemon_pokemon_events');
        $regional_regions_table = pokehub_get_table('pokemon_regional_regions');
        $regional_mappings_table = pokehub_get_table('pokemon_regional_mappings');

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1) Table principale Pokémon (Pokédex + formes)
        $sql_pokemon = "CREATE TABLE {$pokemon_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            dex_number SMALLINT UNSIGNED NOT NULL DEFAULT 0,

            name_en VARCHAR(191) NOT NULL DEFAULT '',
            name_fr VARCHAR(191) NOT NULL DEFAULT '',
            slug    VARCHAR(191) NOT NULL,

            form_variant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            is_default TINYINT(1) NOT NULL DEFAULT 0,

            generation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,

            base_atk SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            base_def SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            base_sta SMALLINT UNSIGNED NOT NULL DEFAULT 0,

            /* === Nouveaux champs issus du Game Master === */

            -- Échange / transfert
            is_tradable      TINYINT(1) NOT NULL DEFAULT 1,
            is_transferable  TINYINT(1) NOT NULL DEFAULT 1,

            -- Shadow / purifié
            has_shadow       TINYINT(1) NOT NULL DEFAULT 0,
            has_purified     TINYINT(1) NOT NULL DEFAULT 0,
            shadow_purification_stardust SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            shadow_purification_candy    SMALLINT UNSIGNED NOT NULL DEFAULT 0,

            -- Copain : méga énergie en marchant
            buddy_walked_mega_energy_award SMALLINT UNSIGNED NOT NULL DEFAULT 0,

            -- Probabilités d'attaque / esquive (rencontre)
            dodge_probability  DECIMAL(5,3) NOT NULL DEFAULT 0,
            attack_probability DECIMAL(5,3) NOT NULL DEFAULT 0,

            /* ============================================ */

            extra LONGTEXT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY  (id),
            KEY dex_number (dex_number),
            KEY slug (slug),
            KEY form_variant_id (form_variant_id),
            KEY is_default (is_default),
            KEY generation_id (generation_id),
            KEY is_tradable (is_tradable),
            KEY is_transferable (is_transferable),
            KEY has_shadow (has_shadow),
            KEY has_purified (has_purified)
        ) {$charset_collate};";

        // 2) Types Pokémon (Eau, Feu, Plante... 1 seule fois ici)
        $sql_types = "CREATE TABLE {$types_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(50) NOT NULL,

            name_en VARCHAR(100) NOT NULL DEFAULT '',
            name_fr VARCHAR(100) NOT NULL DEFAULT '',

            color VARCHAR(20) DEFAULT '' NOT NULL,
            icon VARCHAR(191) DEFAULT '' NOT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            extra LONGTEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) {$charset_collate};";


        // 3) Régions (Kanto, Johto, etc.)
        $sql_regions = "CREATE TABLE {$regions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(50) NOT NULL,

            name_en VARCHAR(100) NOT NULL DEFAULT '',
            name_fr VARCHAR(100) NOT NULL DEFAULT '',

            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            extra LONGTEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) {$charset_collate};";


        // 4) Générations (Gen 1, 2...), liées éventuellement à une région
        $sql_gens = "CREATE TABLE {$gens_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(50) NOT NULL,

            name_en VARCHAR(100) NOT NULL DEFAULT '',
            name_fr VARCHAR(100) NOT NULL DEFAULT '',

            generation_number TINYINT UNSIGNED NOT NULL DEFAULT 0,
            region_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            extra LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) {$charset_collate};";

        // 5) Attaques (moves)
        $sql_attacks = "CREATE TABLE {$attacks_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(100) NOT NULL,

            name_en VARCHAR(191) NOT NULL DEFAULT '',
            name_fr VARCHAR(191) NOT NULL DEFAULT '',

            category VARCHAR(20) NOT NULL DEFAULT '',

            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            damage_window_start_ms INT UNSIGNED NOT NULL DEFAULT 0,
            damage_window_end_ms INT UNSIGNED NOT NULL DEFAULT 0,
            energy SMALLINT NOT NULL DEFAULT 0,

            extra LONGTEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY category (category)
        ) {$charset_collate};";


        // 6) Attaques stats
        $sql_attack_stats = "CREATE TABLE {$attack_stats_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            attack_id BIGINT UNSIGNED NOT NULL,
            game_key VARCHAR(50) NOT NULL,
            context VARCHAR(20) NOT NULL DEFAULT '',

            damage SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            dps DECIMAL(6,3) NOT NULL DEFAULT 0,
            eps DECIMAL(6,3) NOT NULL DEFAULT 0,

            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            damage_window_start_ms INT UNSIGNED NOT NULL DEFAULT 0,
            damage_window_end_ms INT UNSIGNED NOT NULL DEFAULT 0,
            energy SMALLINT NOT NULL DEFAULT 0,

            extra LONGTEXT NULL,

            PRIMARY KEY (id),
            KEY attack_id (attack_id),
            KEY game_key (game_key),
            KEY context (context)
        ) {$charset_collate};";


        // 7) Lien Pokémon ↔ Types (1 Pokémon peut avoir 1 ou 2 types)
        $sql_pokemon_type_links = "CREATE TABLE {$pokemon_type_links} (
            pokemon_id BIGINT UNSIGNED NOT NULL,
            type_id BIGINT UNSIGNED NOT NULL,
            slot TINYINT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (pokemon_id, type_id),
            KEY type_id (type_id),
            KEY slot (slot)
        ) {$charset_collate};";


        // 7bis) Lien Attaque ↔ Types (une attaque peut avoir 1 ou plusieurs types)
        $sql_attack_type_links = "CREATE TABLE {$attack_type_links} (
            attack_id BIGINT UNSIGNED NOT NULL,
            type_id  BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (attack_id, type_id),
            KEY type_id (type_id)
        ) {$charset_collate};";


        // 8) Lien Pokémon ↔ Attaques
        $sql_pokemon_attack_links = "CREATE TABLE {$pokemon_attack_links} (
            pokemon_id BIGINT UNSIGNED NOT NULL,
            attack_id BIGINT UNSIGNED NOT NULL,

            role VARCHAR(20) NOT NULL DEFAULT '',

            is_legacy TINYINT(1) NOT NULL DEFAULT 0,
            is_event TINYINT(1) NOT NULL DEFAULT 0,
            is_elite_tm TINYINT(1) NOT NULL DEFAULT 0,

            extra LONGTEXT NULL,

            PRIMARY KEY (pokemon_id, attack_id, role),
            KEY attack_id (attack_id),
            KEY role (role),
            KEY is_legacy (is_legacy),
            KEY is_event (is_event),
            KEY is_elite_tm (is_elite_tm)
        ) {$charset_collate};";

        // 9) Météos (Sunny, Rainy, etc.)
        $sql_weathers = "CREATE TABLE {$weathers_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(50) NOT NULL,
            name_en VARCHAR(100) NOT NULL DEFAULT '',
            name_fr VARCHAR(100) NOT NULL DEFAULT '',
            extra LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) {$charset_collate};";

        // 9bis) Types d'œufs (2 km, 7 km, etc.)
        $sql_egg_types = "CREATE TABLE {$egg_types_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(50) NOT NULL,
            name_en VARCHAR(100) NOT NULL DEFAULT '',
            name_fr VARCHAR(100) NOT NULL DEFAULT '',
            hatch_distance_km SMALLINT UNSIGNED NOT NULL DEFAULT 2,
            extra LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) {$charset_collate};";

        // 10) Lien Type ↔ Météos (boosts)
        $sql_type_weather_links = "CREATE TABLE {$type_weather_links} (
            type_id BIGINT UNSIGNED NOT NULL,
            weather_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (type_id, weather_id),
            KEY weather_id (weather_id)
        ) {$charset_collate};";

        // 10bis) Lien Type ↔ Faiblesses (un type est faible contre d'autres types)
        $sql_type_weakness_links = "CREATE TABLE {$type_weakness_links} (
            type_id BIGINT UNSIGNED NOT NULL,
            weakness_type_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (type_id, weakness_type_id),
            KEY weakness_type_id (weakness_type_id)
        ) {$charset_collate};";

        // 10ter) Lien Type ↔ Résistances (un type résiste à d'autres types)
        $sql_type_resistance_links = "CREATE TABLE {$type_resistance_links} (
            type_id BIGINT UNSIGNED NOT NULL,
            resistance_type_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (type_id, resistance_type_id),
            KEY resistance_type_id (resistance_type_id)
        ) {$charset_collate};";

        // 11) Registre global des formes de Pokémon
        $sql_form_variants = "CREATE TABLE {$pokemon_form_variants} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_slug VARCHAR(191) NOT NULL,
            category VARCHAR(64) NOT NULL DEFAULT 'normal',
            `group` VARCHAR(128) NOT NULL DEFAULT '',
            label VARCHAR(191) NOT NULL DEFAULT '',
            extra LONGTEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY form_slug (form_slug),
            KEY category (category),
            KEY group_idx (`group`)
        ) {$charset_collate};";

        // 11b) Liaison Form variant (costume / forme) ↔ Événements (une forme peut être associée à plusieurs événements)
        $sql_form_variant_events = "CREATE TABLE {$form_variant_events_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_variant_id BIGINT(20) UNSIGNED NOT NULL,
            event_type VARCHAR(50) NOT NULL DEFAULT '',
            event_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY form_variant_id (form_variant_id),
            UNIQUE KEY form_variant_event (form_variant_id, event_type, event_id)
        ) {$charset_collate};";

        // 11c) Liaison Pokémon (marqué événement/costumé) ↔ Événements (un Pokémon peut être associé à plusieurs événements)
        $sql_pokemon_events = "CREATE TABLE {$pokemon_events_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            pokemon_id BIGINT(20) UNSIGNED NOT NULL,
            event_type VARCHAR(50) NOT NULL DEFAULT '',
            event_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY pokemon_id (pokemon_id),
            UNIQUE KEY pokemon_event (pokemon_id, event_type, event_id)
        ) {$charset_collate};";

        // 13) Branches d'évolution Pokémon (avec item_id / lure_item_id)
        $sql_evolutions = "CREATE TABLE {$evolutions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            base_pokemon_id BIGINT UNSIGNED NOT NULL,
            target_pokemon_id BIGINT UNSIGNED NOT NULL,

            base_form_variant_id   BIGINT UNSIGNED NOT NULL DEFAULT 0,
            target_form_variant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,

            candy_cost          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            candy_cost_purified SMALLINT UNSIGNED NOT NULL DEFAULT 0,

            is_trade_evolution      TINYINT(1) NOT NULL DEFAULT 0,
            no_candy_cost_via_trade TINYINT(1) NOT NULL DEFAULT 0,
            is_random_evolution     TINYINT(1) NOT NULL DEFAULT 0,

            method VARCHAR(50) NOT NULL DEFAULT '',

            item_requirement_slug  VARCHAR(100) NOT NULL DEFAULT '',
            item_requirement_cost  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            item_id                BIGINT UNSIGNED NOT NULL DEFAULT 0,

            lure_item_slug          VARCHAR(100) NOT NULL DEFAULT '',
            lure_item_id            BIGINT UNSIGNED NOT NULL DEFAULT 0,

            weather_requirement_slug VARCHAR(100) NOT NULL DEFAULT '',
            gender_requirement       VARCHAR(10)  NOT NULL DEFAULT '',
            time_of_day              VARCHAR(20)  NOT NULL DEFAULT '',

            priority TINYINT UNSIGNED NOT NULL DEFAULT 0,

            quest_template_id VARCHAR(191) NOT NULL DEFAULT '',

            extra LONGTEXT NULL,

            PRIMARY KEY (id),
            KEY base_pokemon_id (base_pokemon_id),
            KEY target_pokemon_id (target_pokemon_id),
            KEY method (method),
            KEY item_requirement_slug (item_requirement_slug),
            KEY item_id (item_id),
            KEY lure_item_slug (lure_item_slug),
            KEY lure_item_id (lure_item_id),
            KEY gender_requirement (gender_requirement),
            KEY time_of_day (time_of_day)
        ) {$charset_collate};";

        // 14) Objets / Items (évo, leurres, balls, etc.)
        $sql_items = "CREATE TABLE {$items_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(191) NOT NULL,
            proto_id VARCHAR(191) NOT NULL, -- ex: ITEM_KINGS_ROCK, ITEM_TROY_DISK_RAINY
            category VARCHAR(50) NOT NULL DEFAULT 'other', -- evolution_item, lure, ball, mega_item, other...
            subtype VARCHAR(50) NOT NULL DEFAULT '',

            name_en VARCHAR(191) NOT NULL DEFAULT '',
            name_fr VARCHAR(191) NOT NULL DEFAULT '',

            description_en TEXT NULL,
            description_fr TEXT NULL,

            image_id BIGINT(20) UNSIGNED NULL,

            game_key VARCHAR(50) NOT NULL DEFAULT 'pokemon_go',

            extra LONGTEXT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            UNIQUE KEY proto_game (proto_id, game_key),
            KEY category (category),
            KEY game_key (game_key)
        ) {$charset_collate};";

        // 15) Backgrounds (fonds spéciaux pour Pokémon)
        // background_type : 'location' = fonds de lieux, 'special' = fonds spéciaux (Pokémon GO)
        $sql_backgrounds = "CREATE TABLE {$backgrounds_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(191) NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            background_type VARCHAR(50) NOT NULL DEFAULT 'special',
            image_url TEXT NULL,
            event_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            event_type VARCHAR(50) NOT NULL DEFAULT '', -- 'local_post', 'remote_post', 'special_local', 'special_remote'
            extra LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY background_type (background_type),
            KEY event_id (event_id),
            KEY event_type (event_type)
        ) {$charset_collate};";

        // 16) Liaison Background ↔ Pokémon (plusieurs Pokémon peuvent avoir le même background)
        // is_shiny_locked : 1 = le fond est sorti avant le shiny, ce Pokémon ne peut pas être shiny pour ce fond
        $sql_background_pokemon_links = "CREATE TABLE {$background_pokemon_links} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            background_id BIGINT(20) UNSIGNED NOT NULL,
            pokemon_id BIGINT(20) UNSIGNED NOT NULL,
            is_shiny_locked TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY background_id (background_id),
            KEY pokemon_id (pokemon_id),
            UNIQUE KEY background_pokemon (background_id, pokemon_id)
        ) {$charset_collate};";

        // 16b) Liaison Background ↔ Événements (un fond peut être associé à plusieurs événements)
        $sql_background_events = "CREATE TABLE {$background_events_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            background_id BIGINT(20) UNSIGNED NOT NULL,
            event_type VARCHAR(50) NOT NULL DEFAULT '',
            event_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY background_id (background_id),
            UNIQUE KEY background_event (background_id, event_type, event_id)
        ) {$charset_collate};";

        dbDelta($sql_pokemon);
        dbDelta($sql_types);
        dbDelta($sql_regions);
        dbDelta($sql_gens);
        dbDelta($sql_attacks);
        dbDelta($sql_attack_stats);
        dbDelta($sql_pokemon_type_links);
        dbDelta($sql_attack_type_links);
        dbDelta($sql_pokemon_attack_links);
        dbDelta($sql_weathers);
        dbDelta($sql_egg_types);
        dbDelta($sql_type_weather_links);
        dbDelta($sql_type_weakness_links);
        dbDelta($sql_type_resistance_links);
        dbDelta($sql_form_variants);
        dbDelta($sql_form_variant_events);
        dbDelta($sql_pokemon_events);
        dbDelta($sql_evolutions);
        dbDelta($sql_items);
        dbDelta($sql_backgrounds);
        dbDelta($sql_background_pokemon_links);
        dbDelta($sql_background_events);

        // Migration : copier event_id/event_type des backgrounds vers pokemon_background_events (une seule fois)
        $migrated = get_option('pokehub_background_events_migrated', false);
        if (!$migrated) {
            $existing = $wpdb->get_results(
                "SELECT id, event_id, event_type FROM {$backgrounds_table} WHERE event_id IS NOT NULL AND event_id > 0 AND TRIM(COALESCE(event_type, '')) != ''"
            );
            if (!empty($existing)) {
                foreach ($existing as $row) {
                    $wpdb->replace(
                        $background_events_table,
                        [
                            'background_id' => (int) $row->id,
                            'event_type'     => (string) $row->event_type,
                            'event_id'       => (int) $row->event_id,
                        ],
                        ['%d', '%s', '%d']
                    );
                }
            }
            update_option('pokehub_background_events_migrated', true);
        }

        // 17) Table des régions géographiques (Europe, Asie, Hémisphère Est, etc.)
        $sql_regional_regions = "CREATE TABLE {$regional_regions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(100) NOT NULL,
            name_fr VARCHAR(191) NOT NULL DEFAULT '',
            name_en VARCHAR(191) NOT NULL DEFAULT '',
            countries LONGTEXT NULL COMMENT 'JSON array of country labels',
            description TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY name_fr (name_fr(191)),
            KEY name_en (name_en(191))
        ) {$charset_collate};";
        
        // 18) Table du mapping régional (pattern Vivillon => pays/régions)
        $sql_regional_mappings = "CREATE TABLE {$regional_mappings_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pattern_slug VARCHAR(100) NOT NULL COMMENT 'Pattern slug (e.g., continental, archipelago)',
            countries LONGTEXT NULL COMMENT 'JSON array of country labels',
            region_slugs LONGTEXT NULL COMMENT 'JSON array of region slugs (references to pokemon_regional_regions)',
            description TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY pattern_slug (pattern_slug)
        ) {$charset_collate};";
        
        dbDelta($sql_regional_regions);
        dbDelta($sql_regional_mappings);
        
        // Seed initial regional data if tables are empty
        // Only seed if this is a fresh install (tables are empty)
        $regions_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$regional_regions_table}");
        $mappings_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$regional_mappings_table}");
        
        if ($regions_count === 0 && $mappings_count === 0) {
            $this->seedRegionalData();
        }
    }
    
    /**
     * Seed initial regional data
     * Only inserts if tables are empty
     * 
     * @param bool $force If true, will seed even if tables are not empty (use with caution)
     * @return bool True if data was seeded, false otherwise
     */
    /**
     * DEPRECATED: Use poke_hub_seed_regional_data() from pokemon module instead
     * This method is kept for backward compatibility but delegates to the new function
     * 
     * @param bool $force Force update even if data already exists
     * @return bool True on success, false on failure
     * @deprecated Use poke_hub_seed_regional_data() from modules/pokemon/includes/pokemon-regional-seed.php instead
     */
    public function seedRegionalData($force = false) {
        if (function_exists('poke_hub_seed_regional_data')) {
            return poke_hub_seed_regional_data($force);
        }
        return false;
    }
    
    /**
     * Ensure regional data is seeded if tables exist but are empty
     * Can be called at any time to check and seed data
     * 
     * @return bool True if data was seeded or already exists, false if tables don't exist
     */
    public function ensureRegionalDataSeeded() {
        return $this->seedRegionalData(false);
    }
    
    /**
     * Ensure regional tables exist, create them if they don't
     * Uses Pokemon table prefix (remote if configured) for a single source of truth
     * 
     * @return bool True if tables exist or were created, false on error
     */
    public function ensureRegionalTablesExist() {
        global $wpdb;
        
        $regional_regions_table = pokehub_get_table('pokemon_regional_regions');
        $regional_mappings_table = pokehub_get_table('pokemon_regional_mappings');
        
        if (empty($regional_regions_table) || empty($regional_mappings_table)) {
            return false;
        }
        
        // Check if tables exist
        $regions_table_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$regional_regions_table}'") === $regional_regions_table);
        $mappings_table_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$regional_mappings_table}'") === $regional_mappings_table);
        
        // If both exist, nothing to do
        if ($regions_table_exists && $mappings_table_exists) {
            return true;
        }
        
        // Create missing tables
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create regional_regions table if it doesn't exist
        if (!$regions_table_exists) {
            $sql_regional_regions = "CREATE TABLE {$regional_regions_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                slug VARCHAR(100) NOT NULL,
                name_fr VARCHAR(191) NOT NULL DEFAULT '',
                name_en VARCHAR(191) NOT NULL DEFAULT '',
                countries LONGTEXT NULL COMMENT 'JSON array of country labels',
                description TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                KEY name_fr (name_fr(191)),
                KEY name_en (name_en(191))
            ) {$charset_collate};";
            
            dbDelta($sql_regional_regions);
        }
        
        // Create regional_mappings table if it doesn't exist
        if (!$mappings_table_exists) {
            $sql_regional_mappings = "CREATE TABLE {$regional_mappings_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                pattern_slug VARCHAR(191) NOT NULL COMMENT 'Pokemon slug or form slug (e.g., vivillon-archipelago)',
                countries LONGTEXT NULL COMMENT 'JSON array of country labels',
                region_slugs LONGTEXT NULL COMMENT 'JSON array of region slugs',
                description TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY pattern_slug (pattern_slug),
                KEY created_at (created_at)
            ) {$charset_collate};";
            
            dbDelta($sql_regional_mappings);
        }
        
        return true;
    }
    
    /**
     * Création de toutes les tables liées aux événements spéciaux :
     * - special_events
     * - special_event_pokemon
     * - special_event_bonus
     * - special_event_pokemon_attacks
     */
    private function createEventsTables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $events_table                = pokehub_get_table('special_events');
        $event_pokemon_table         = pokehub_get_table('special_event_pokemon');
        $event_bonus_table           = pokehub_get_table('special_event_bonus');
        $event_pokemon_attacks_table = pokehub_get_table('special_event_pokemon_attacks');

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1) Table principale des événements spéciaux
        $sql_events = "CREATE TABLE {$events_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(191) NOT NULL,
            title VARCHAR(255) NOT NULL,
            title_en VARCHAR(255) NULL,
            title_fr VARCHAR(255) NULL,
            description LONGTEXT NULL,
            event_type VARCHAR(50) NOT NULL,
            start_ts INT UNSIGNED NOT NULL,
            end_ts INT UNSIGNED NOT NULL,
            mode VARCHAR(20) NOT NULL DEFAULT 'local',

            recurring TINYINT(1) NOT NULL DEFAULT 0,
            recurring_freq VARCHAR(20) NOT NULL DEFAULT 'weekly',
            recurring_interval INT UNSIGNED NOT NULL DEFAULT 1,
            recurring_window_end_ts INT UNSIGNED NOT NULL DEFAULT 0,

            image_id BIGINT UNSIGNED NULL DEFAULT NULL,
            image_url TEXT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY event_type (event_type),
            KEY start_ts (start_ts),
            KEY end_ts (end_ts)
        ) {$charset_collate};";

        // 2) Liaison événement ↔ Pokémon
        $sql_event_pokemon = "CREATE TABLE {$event_pokemon_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT UNSIGNED NOT NULL,
            pokemon_id BIGINT UNSIGNED NOT NULL,
            gender VARCHAR(10) NULL DEFAULT NULL COMMENT 'male, female, or NULL for default',

            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY pokemon_id (pokemon_id)
        ) {$charset_collate};";

        // 3) Liaison événement ↔ bonus
        $sql_event_bonus = "CREATE TABLE {$event_bonus_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT UNSIGNED NOT NULL,
            bonus_id BIGINT UNSIGNED NOT NULL,
            description LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY bonus_id (bonus_id)
        ) {$charset_collate};";

        // 4) Liaison Pokémon ↔ attaque pour un event
        $sql_event_pokemon_attacks = "CREATE TABLE {$event_pokemon_attacks_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT UNSIGNED NOT NULL,
            pokemon_id BIGINT UNSIGNED NOT NULL,
            attack_id BIGINT UNSIGNED NOT NULL,
            is_forced TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY pokemon_id (pokemon_id),
            KEY attack_id (attack_id),
            KEY is_forced (is_forced)
        ) {$charset_collate};";

        dbDelta($sql_events);
        dbDelta($sql_event_pokemon);
        dbDelta($sql_event_bonus);
        dbDelta($sql_event_pokemon_attacks);
        
        // Migration : ajouter les colonnes recurring si elles n'existent pas
        $this->migrateSpecialEventsRecurringColumns($events_table);
        
        // Migration : ajouter la colonne gender si elle n'existe pas
        $this->migrateSpecialEventPokemonGenderColumn($event_pokemon_table);
    }
    
    /**
     * Migration : ajoute les colonnes recurring si elles n'existent pas.
     * 
     * @param string $table_name Nom de la table special_events
     */
    private function migrateSpecialEventsRecurringColumns($table_name) {
        global $wpdb;
        
        // Vérifier si la table existe
        $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name);
        if (!$table_exists) {
            return;
        }
        
        // Vérifier si la colonne recurring existe
        $column_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = 'recurring'",
                DB_NAME,
                $table_name
            )
        );
        
        if (empty($column_exists) || (int) $column_exists === 0) {
            // Ajouter les colonnes manquantes une par une pour éviter les erreurs
            $columns_to_add = [
                'recurring' => "ADD COLUMN recurring TINYINT(1) NOT NULL DEFAULT 0 AFTER mode",
                'recurring_freq' => "ADD COLUMN recurring_freq VARCHAR(20) NOT NULL DEFAULT 'weekly' AFTER recurring",
                'recurring_interval' => "ADD COLUMN recurring_interval INT UNSIGNED NOT NULL DEFAULT 1 AFTER recurring_freq",
                'recurring_window_end_ts' => "ADD COLUMN recurring_window_end_ts INT UNSIGNED NOT NULL DEFAULT 0 AFTER recurring_interval"
            ];
            
            foreach ($columns_to_add as $column_name => $sql_part) {
                $column_check = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                         WHERE TABLE_SCHEMA = %s 
                         AND TABLE_NAME = %s 
                         AND COLUMN_NAME = %s",
                        DB_NAME,
                        $table_name,
                        $column_name
                    )
                );
                
                if (empty($column_check) || (int) $column_check === 0) {
                    $wpdb->query("ALTER TABLE {$table_name} {$sql_part}");
                }
            }
        }
    }
    
    /**
     * Méthode publique pour exécuter la migration des colonnes recurring.
     * Peut être appelée depuis l'extérieur de la classe.
     */
    public function migrateEventsRecurringColumns() {
        $events_table = pokehub_get_table('special_events');
        if ($events_table) {
            $this->migrateSpecialEventsRecurringColumns($events_table);
        }
    }
    
    /**
     * Migration : ajoute la colonne gender à special_event_pokemon si elle n'existe pas.
     * 
     * @param string $table_name Nom de la table special_event_pokemon
     */
    private function migrateSpecialEventPokemonGenderColumn($table_name) {
        global $wpdb;
        
        // Vérifier si la table existe
        $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name);
        if (!$table_exists) {
            return;
        }
        
        // Vérifier si la colonne gender existe
        $column_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = 'gender'",
                DB_NAME,
                $table_name
            )
        );
        
        if (empty($column_exists) || (int) $column_exists === 0) {
            // Ajouter la colonne gender
            $wpdb->query(
                "ALTER TABLE {$table_name} 
                 ADD COLUMN gender VARCHAR(10) NULL DEFAULT NULL COMMENT 'male, female, or NULL for default' 
                 AFTER pokemon_id"
            );
        }
    }
    
    /**
     * Migration publique : ajoute la colonne gender à special_event_pokemon si elle n'existe pas.
     */
    public function migrateEventPokemonGenderColumn() {
        $event_pokemon_table = pokehub_get_table('special_event_pokemon');
        if ($event_pokemon_table) {
            $this->migrateSpecialEventPokemonGenderColumn($event_pokemon_table);
        }
    }

    /**
     * Migration unique : renommer les tables de contenu qui avaient un double préfixe "pokehub"
     * (ex. actu_pokehub_pokehub_content_special_research_steps -> actu_pokehub_content_special_research_steps).
     * Si la table au bon nom existe déjà (vide), on la supprime puis on renomme l'ancienne.
     */
    public function migrateContentTablesDoublePrefix() {
        $option_key = 'pokehub_content_tables_double_prefix_migrated';
        if (get_option($option_key, false)) {
            return;
        }

        global $wpdb;
        $prefix = $wpdb->prefix;
        $content_keys = [
            'content_eggs', 'content_egg_pokemon', 'content_quests', 'content_quest_lines', 'quest_groups',
            'content_habitats', 'content_habitat_entries', 'content_special_research', 'content_special_research_steps',
            'content_collection_challenges', 'content_collection_challenge_items', 'content_bonus', 'content_bonus_entries',
            'content_wild_pokemon', 'content_wild_pokemon_entries', 'content_new_pokemon', 'content_new_pokemon_entries',
            'content_raids', 'content_raid_bosses',
            // Bloc "jour -> Pokémon(s) -> heures" (raids/oeufs/encens/leurres/heure vedette/quêtes...)
            'content_day_pokemon_hours', 'content_day_pokemon_hour_entries',
        ];

        foreach ($content_keys as $key) {
            if (!function_exists('pokehub_get_table')) {
                continue;
            }
            $new_table = pokehub_get_table($key);
            if (empty($new_table) || strpos($new_table, $prefix . 'pokehub_') !== 0) {
                continue;
            }
            $new_suffix = preg_replace('#^' . preg_quote($prefix . 'pokehub_', '#') . '#', '', $new_table);
            $old_table = $prefix . 'pokehub_pokehub_' . $new_suffix;

            $old_exists = ($wpdb->get_var("SHOW TABLES LIKE " . $wpdb->prepare('%s', $old_table)) === $old_table);
            $new_exists = ($wpdb->get_var("SHOW TABLES LIKE " . $wpdb->prepare('%s', $new_table)) === $new_table);

            if (!$old_exists) {
                continue;
            }
            if ($new_exists) {
                $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `" . esc_sql($new_table) . "`");
                if ($count === 0) {
                    $wpdb->query("DROP TABLE `" . esc_sql($new_table) . "`");
                    $new_exists = false;
                } else {
                    continue;
                }
            }
            if (!$new_exists) {
                $wpdb->query("RENAME TABLE `" . esc_sql($old_table) . "` TO `" . esc_sql($new_table) . "`");
            }
        }

        update_option($option_key, true);
    }

    /**
     * Création des tables de contenu communes (source_type: post, special_event, global_pool).
     * Une source de vérité par type : œufs, quêtes, raids, habitats, bonus, etc.
     * Les dates (start_ts, end_ts) sont dupliquées ; la mise à jour des dates d’un event/post
     * doit mettre à jour ces tables (sync via helpers).
     */
    private function createContentTables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $eggs_tbl           = pokehub_get_table('content_eggs');
        $egg_pokemon_tbl    = pokehub_get_table('content_egg_pokemon');
        $quests_tbl         = pokehub_get_table('content_quests');
        $quest_lines_tbl    = pokehub_get_table('content_quest_lines');
        $quest_groups_tbl   = pokehub_get_table('quest_groups');
        $habitats_tbl       = pokehub_get_table('content_habitats');
        $habitat_entries_tbl = pokehub_get_table('content_habitat_entries');
        $research_tbl      = pokehub_get_table('content_special_research');
        $research_steps_tbl = pokehub_get_table('content_special_research_steps');
        $challenges_tbl    = pokehub_get_table('content_collection_challenges');
        $challenge_items_tbl = pokehub_get_table('content_collection_challenge_items');
        $bonus_tbl         = pokehub_get_table('content_bonus');
        $bonus_entries_tbl = pokehub_get_table('content_bonus_entries');
        $wild_tbl          = pokehub_get_table('content_wild_pokemon');
        $wild_entries_tbl  = pokehub_get_table('content_wild_pokemon_entries');
        $new_pokemon_tbl   = pokehub_get_table('content_new_pokemon');
        $new_pokemon_entries_tbl = pokehub_get_table('content_new_pokemon_entries');
        $day_pokemon_hours_tbl = pokehub_get_table('content_day_pokemon_hours');
        $day_pokemon_hour_entries_tbl = pokehub_get_table('content_day_pokemon_hour_entries');
        $raids_tbl         = pokehub_get_table('content_raids');
        $raid_bosses_tbl   = pokehub_get_table('content_raid_bosses');

        $sql_eggs = "CREATE TABLE {$eggs_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type VARCHAR(20) NOT NULL DEFAULT 'post',
            source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            start_ts INT UNSIGNED NOT NULL DEFAULT 0,
            end_ts INT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(255) NULL DEFAULT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source (source_type, source_id),
            KEY dates (start_ts, end_ts)
        ) {$charset_collate};";

        // Seuls les overrides sont stockés ; is_shiny, is_regional, cp_min/cp_max sont calculés à l'affichage.
        $sql_egg_pokemon = "CREATE TABLE {$egg_pokemon_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_egg_id BIGINT UNSIGNED NOT NULL,
            egg_type_id BIGINT UNSIGNED NOT NULL,
            pokemon_id BIGINT UNSIGNED NOT NULL,
            rarity TINYINT UNSIGNED NOT NULL DEFAULT 1,
            is_worldwide_override TINYINT(1) NOT NULL DEFAULT 0,
            is_forced_shiny TINYINT(1) NOT NULL DEFAULT 0,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY content_egg_id (content_egg_id),
            KEY egg_type_id (egg_type_id),
            KEY pokemon_id (pokemon_id)
        ) {$charset_collate};";

        $sql_quests = "CREATE TABLE {$quests_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type VARCHAR(20) NOT NULL DEFAULT 'post',
            source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            start_ts INT UNSIGNED NOT NULL DEFAULT 0,
            end_ts INT UNSIGNED NOT NULL DEFAULT 0,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source (source_type, source_id),
            KEY dates (start_ts, end_ts)
        ) {$charset_collate};";

        $sql_quest_groups = "CREATE TABLE {$quest_groups_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title_en VARCHAR(255) NOT NULL DEFAULT '',
            title_fr VARCHAR(255) NOT NULL DEFAULT '',
            color VARCHAR(20) NULL DEFAULT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sort_order (sort_order)
        ) {$charset_collate};";

        $sql_quest_lines = "CREATE TABLE {$quest_lines_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_quest_id BIGINT UNSIGNED NOT NULL,
            quest_group_id BIGINT UNSIGNED NULL DEFAULT NULL,
            task TEXT NULL,
            rewards LONGTEXT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY content_quest_id (content_quest_id),
            KEY quest_group_id (quest_group_id)
        ) {$charset_collate};";

        $sql_habitats = "CREATE TABLE {$habitats_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type VARCHAR(20) NOT NULL DEFAULT 'post',
            source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            start_ts INT UNSIGNED NOT NULL DEFAULT 0,
            end_ts INT UNSIGNED NOT NULL DEFAULT 0,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source (source_type, source_id),
            KEY dates (start_ts, end_ts)
        ) {$charset_collate};";

        $sql_habitat_entries = "CREATE TABLE {$habitat_entries_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_habitat_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL DEFAULT '',
            slug VARCHAR(255) NOT NULL DEFAULT '',
            pokemon_data LONGTEXT NULL,
            schedule_data LONGTEXT NULL,
            all_pokemon_available TINYINT(1) NOT NULL DEFAULT 0,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY content_habitat_id (content_habitat_id)
        ) {$charset_collate};";

        $sql_research = "CREATE TABLE {$research_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type VARCHAR(20) NOT NULL DEFAULT 'post',
            source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            start_ts INT UNSIGNED NOT NULL DEFAULT 0,
            end_ts INT UNSIGNED NOT NULL DEFAULT 0,
            research_type VARCHAR(30) NOT NULL DEFAULT 'special',
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source (source_type, source_id),
            KEY dates (start_ts, end_ts)
        ) {$charset_collate};";

        $sql_research_steps = "CREATE TABLE {$research_steps_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_research_id BIGINT UNSIGNED NOT NULL,
            step_data LONGTEXT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY content_research_id (content_research_id)
        ) {$charset_collate};";

        $sql_challenges = "CREATE TABLE {$challenges_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type VARCHAR(20) NOT NULL DEFAULT 'post',
            source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            start_ts INT UNSIGNED NOT NULL DEFAULT 0,
            end_ts INT UNSIGNED NOT NULL DEFAULT 0,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source (source_type, source_id),
            KEY dates (start_ts, end_ts)
        ) {$charset_collate};";

        $sql_challenge_items = "CREATE TABLE {$challenge_items_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_challenge_id BIGINT UNSIGNED NOT NULL,
            item_data LONGTEXT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY content_challenge_id (content_challenge_id)
        ) {$charset_collate};";

        $sql_bonus = "CREATE TABLE {$bonus_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type VARCHAR(20) NOT NULL DEFAULT 'post',
            source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            start_ts INT UNSIGNED NOT NULL DEFAULT 0,
            end_ts INT UNSIGNED NOT NULL DEFAULT 0,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source (source_type, source_id),
            KEY dates (start_ts, end_ts)
        ) {$charset_collate};";

        $sql_bonus_entries = "CREATE TABLE {$bonus_entries_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_bonus_id BIGINT UNSIGNED NOT NULL,
            bonus_id BIGINT UNSIGNED NOT NULL,
            description LONGTEXT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY content_bonus_id (content_bonus_id),
            KEY bonus_id (bonus_id)
        ) {$charset_collate};";

        $sql_wild = "CREATE TABLE {$wild_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type VARCHAR(20) NOT NULL DEFAULT 'post',
            source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            start_ts INT UNSIGNED NOT NULL DEFAULT 0,
            end_ts INT UNSIGNED NOT NULL DEFAULT 0,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source (source_type, source_id),
            KEY dates (start_ts, end_ts)
        ) {$charset_collate};";

        $sql_wild_entries = "CREATE TABLE {$wild_entries_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_wild_pokemon_id BIGINT UNSIGNED NOT NULL,
            pokemon_id BIGINT UNSIGNED NOT NULL,
            is_rare TINYINT(1) NOT NULL DEFAULT 0,
            is_forced_shiny TINYINT(1) NOT NULL DEFAULT 0,
            gender VARCHAR(10) NULL DEFAULT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY content_wild_pokemon_id (content_wild_pokemon_id),
            KEY pokemon_id (pokemon_id)
        ) {$charset_collate};";

        $sql_new_pokemon = "CREATE TABLE {$new_pokemon_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type VARCHAR(20) NOT NULL DEFAULT 'post',
            source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            start_ts INT UNSIGNED NOT NULL DEFAULT 0,
            end_ts INT UNSIGNED NOT NULL DEFAULT 0,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source (source_type, source_id),
            KEY dates (start_ts, end_ts)
        ) {$charset_collate};";

        $sql_new_pokemon_entries = "CREATE TABLE {$new_pokemon_entries_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_new_pokemon_id BIGINT UNSIGNED NOT NULL,
            pokemon_id BIGINT UNSIGNED NOT NULL,
            gender VARCHAR(10) NULL DEFAULT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY content_new_pokemon_id (content_new_pokemon_id),
            KEY pokemon_id (pokemon_id)
        ) {$charset_collate};";

        $sql_day_pokemon_hours = "CREATE TABLE {$day_pokemon_hours_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type VARCHAR(20) NOT NULL DEFAULT 'post',
            source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            content_type VARCHAR(30) NOT NULL DEFAULT 'featured_hours',
            start_ts INT UNSIGNED NOT NULL DEFAULT 0,
            end_ts INT UNSIGNED NOT NULL DEFAULT 0,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source (source_type, source_id),
            KEY content_type (content_type),
            KEY dates (start_ts, end_ts)
        ) {$charset_collate};";

        $sql_day_pokemon_hour_entries = "CREATE TABLE {$day_pokemon_hour_entries_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_day_pokemon_hours_id BIGINT UNSIGNED NOT NULL,
            day_date VARCHAR(10) NOT NULL DEFAULT '',
            start_time VARCHAR(8) NOT NULL DEFAULT '',
            end_time VARCHAR(8) NOT NULL DEFAULT '',
            end_day_date VARCHAR(10) NOT NULL DEFAULT '',
            pokemon_ids LONGTEXT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY content_day_pokemon_hours_id (content_day_pokemon_hours_id),
            KEY day_date (day_date),
            KEY end_day_date (end_day_date)
        ) {$charset_collate};";

        $sql_raids = "CREATE TABLE {$raids_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type VARCHAR(20) NOT NULL DEFAULT 'post',
            source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            start_ts INT UNSIGNED NOT NULL DEFAULT 0,
            end_ts INT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(255) NULL DEFAULT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source (source_type, source_id),
            KEY dates (start_ts, end_ts)
        ) {$charset_collate};";

        $sql_raid_bosses = "CREATE TABLE {$raid_bosses_tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_raid_id BIGINT UNSIGNED NOT NULL,
            tier TINYINT UNSIGNED NOT NULL DEFAULT 1,
            pokemon_id BIGINT UNSIGNED NOT NULL,
            is_mega TINYINT(1) NOT NULL DEFAULT 0,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY content_raid_id (content_raid_id),
            KEY pokemon_id (pokemon_id)
        ) {$charset_collate};";

        dbDelta($sql_eggs);
        dbDelta($sql_egg_pokemon);
        dbDelta($sql_quest_groups);
        dbDelta($sql_quests);
        dbDelta($sql_quest_lines);
        dbDelta($sql_habitats);
        dbDelta($sql_habitat_entries);
        dbDelta($sql_research);
        dbDelta($sql_research_steps);
        dbDelta($sql_challenges);
        dbDelta($sql_challenge_items);
        dbDelta($sql_bonus);
        dbDelta($sql_bonus_entries);
        dbDelta($sql_wild);
        dbDelta($sql_wild_entries);
        dbDelta($sql_new_pokemon);
        dbDelta($sql_new_pokemon_entries);
        dbDelta($sql_day_pokemon_hours);
        dbDelta($sql_day_pokemon_hour_entries);

        // Migration: ajoute end_day_date pour supporter un end sur un autre jour
        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME = %s
               AND COLUMN_NAME = 'end_day_date'",
            $wpdb->dbname,
            $day_pokemon_hour_entries_tbl
        ));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$day_pokemon_hour_entries_tbl} ADD COLUMN end_day_date VARCHAR(10) NOT NULL DEFAULT '' AFTER end_time, ADD KEY end_day_date (end_day_date)");
        }
        dbDelta($sql_raids);
        dbDelta($sql_raid_bosses);

        // Migration : ajouter quest_group_id à content_quest_lines si absent (installations existantes)
        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'quest_group_id'",
            $wpdb->dbname,
            $quest_lines_tbl
        ));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$quest_lines_tbl} ADD COLUMN quest_group_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER content_quest_id, ADD KEY quest_group_id (quest_group_id)");
        }
    }

    /**
     * Table catalogue des types de bonus (source de vérité sur le site principal).
     * Sur les sites distants, on lit cette table via le même préfixe que les tables Pokémon (remote_pokemon).
     */
    private function createBonusTypesTable() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table = pokehub_get_table('bonus_types');
        if (empty($table)) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL DEFAULT '',
            slug VARCHAR(191) NOT NULL DEFAULT '',
            description LONGTEXT NULL,
            image_slug VARCHAR(191) NULL DEFAULT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY sort_order (sort_order)
        ) {$charset_collate};";

        dbDelta($sql);
        $this->migrateBonusTypesTableSchema();
    }

    /**
     * Met à jour les installations existantes : id AUTO_INCREMENT, index slug unique.
     * Utilise la table catalogue effective (locale ou distante selon pokehub_get_bonus_types_table()).
     */
    public function migrateBonusTypesTableSchema(): void {
        global $wpdb;

        $table = '';
        if (function_exists('pokehub_get_bonus_types_table')) {
            $table = pokehub_get_bonus_types_table();
        } elseif (function_exists('pokehub_get_table')) {
            $table = pokehub_get_table('bonus_types');
        }
        if ($table === '' || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return;
        }

        $col = $wpdb->get_row("SHOW COLUMNS FROM `{$table}` LIKE 'id'", ARRAY_A);
        if ($col && stripos((string) ($col['Extra'] ?? ''), 'auto_increment') === false) {
            $max = (int) $wpdb->get_var("SELECT MAX(id) FROM `{$table}`");
            $next = max(1, $max + 1);
            $wpdb->query("ALTER TABLE `{$table}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
            $wpdb->query($wpdb->prepare("ALTER TABLE `{$table}` AUTO_INCREMENT = %d", $next));
        }

        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}` WHERE Column_name = 'slug'", ARRAY_A);
        $has_unique = false;
        foreach ((array) $indexes as $idx) {
            if (isset($idx['Non_unique']) && (int) $idx['Non_unique'] === 0) {
                $has_unique = true;
                break;
            }
        }
        if (!$has_unique) {
            if (!empty($indexes)) {
                $wpdb->query("ALTER TABLE `{$table}` DROP INDEX `slug`");
            }
            $wpdb->query("ALTER TABLE `{$table}` ADD UNIQUE KEY `slug` (`slug`)");
        }
    }

    /**
     * Création de la table user_profiles pour stocker les profils Pokémon GO.
     * Table partagée entre tous les sites utilisant ME5RINE_LAB_GLOBAL_PREFIX.
     */
    private function createUserProfilesTables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $user_profiles_table = pokehub_get_table('user_profiles');

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Table principale des profils utilisateur Pokémon GO
        // Note: On ne peut pas utiliser UNIQUE sur des colonnes NULL en MySQL standard
        // L'unicité est gérée au niveau application
        $sql_user_profiles = "CREATE TABLE {$user_profiles_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL DEFAULT NULL,
            discord_id VARCHAR(191) NULL DEFAULT NULL,
            profile_type VARCHAR(50) NOT NULL DEFAULT 'classic',
            team VARCHAR(50) NOT NULL DEFAULT '',
            friend_code VARCHAR(12) NOT NULL DEFAULT '',
            friend_code_public TINYINT(1) NOT NULL DEFAULT 1,
            xp BIGINT UNSIGNED NOT NULL DEFAULT 0,
            pokemon_go_username VARCHAR(191) NOT NULL DEFAULT '',
            scatterbug_pattern VARCHAR(50) NOT NULL DEFAULT '',
            country VARCHAR(191) NULL DEFAULT NULL,
            country_custom VARCHAR(191) NULL DEFAULT NULL,
            reasons LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY discord_id (discord_id),
            KEY profile_type (profile_type)
        ) {$charset_collate};";

        dbDelta($sql_user_profiles);
        
        // Migration: add country column if it doesn't exist (for anonymous users)
        $this->migrateUserProfilesAddCountryColumn($user_profiles_table);
        
        // Migration: add profile_type column if it doesn't exist
        $this->migrateUserProfilesAddProfileTypeColumn($user_profiles_table);
        
        // Migration: add country_custom column if it doesn't exist (for custom countries like "Hawaï")
        $this->migrateUserProfilesAddCountryCustomColumn($user_profiles_table);
    }
    
    /**
     * Migration: add country column to user_profiles table if it doesn't exist.
     * Country is stored in Ultimate Member usermeta for logged-in users,
     * and in this table column for anonymous users (user_id IS NULL).
     * 
     * @param string $table_name Name of the user_profiles table
     */
    private function migrateUserProfilesAddCountryColumn($table_name) {
        global $wpdb;
        
        // Check if table exists
        $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name);
        if (!$table_exists) {
            return;
        }
        
        // Check if country column exists
        $column_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = 'country'",
                DB_NAME,
                $table_name
            )
        );
        
        if (empty($column_exists) || (int) $column_exists === 0) {
            // Add country column for anonymous users
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN country VARCHAR(191) NULL DEFAULT NULL AFTER scatterbug_pattern");
        }
    }
    
    /**
     * Migration: add profile_type column to user_profiles table if it doesn't exist.
     * profile_type can be: 'classic' (WordPress user), 'discord' (Discord ID only), 'anonymous' (no user, no Discord)
     * 
     * @param string $table_name Name of the user_profiles table
     */
    private function migrateUserProfilesAddProfileTypeColumn($table_name) {
        global $wpdb;
        
        // Check if table exists
        $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name);
        if (!$table_exists) {
            return;
        }
        
        // Check if profile_type column exists
        $column_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = 'profile_type'",
                DB_NAME,
                $table_name
            )
        );
        
        if (empty($column_exists) || (int) $column_exists === 0) {
            // Add profile_type column
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN profile_type VARCHAR(50) NOT NULL DEFAULT 'classic' AFTER discord_id");
            
            // Add index for profile_type
            $wpdb->query("ALTER TABLE {$table_name} ADD INDEX profile_type (profile_type)");
            
            // Determine and set profile_type for existing profiles
            // classic: has user_id (regardless of discord_id)
            // discord: has discord_id but no user_id
            // anonymous: has neither user_id nor discord_id
            $wpdb->query("UPDATE {$table_name} SET profile_type = 'classic' WHERE user_id IS NOT NULL");
            $wpdb->query("UPDATE {$table_name} SET profile_type = 'discord' WHERE user_id IS NULL AND discord_id IS NOT NULL");
            $wpdb->query("UPDATE {$table_name} SET profile_type = 'anonymous' WHERE user_id IS NULL AND discord_id IS NULL");
        }
    }
    
    /**
     * Public method to migrate all user_profiles columns (called from admin_init).
     * This ensures migrations run even if the table already exists.
     */
    public function migrateUserProfilesColumns() {
        $table_name = pokehub_get_table('user_profiles');
        if (empty($table_name)) {
            return;
        }
        
        $this->migrateUserProfilesAddCountryColumn($table_name);
        $this->migrateUserProfilesAddProfileTypeColumn($table_name);
        $this->migrateUserProfilesAddCountryCustomColumn($table_name);
    }
    
    /**
     * Migration: add country_custom column to user_profiles table if it doesn't exist.
     * country_custom stores the custom country name (like "Hawaï") that was selected by the user,
     * while the actual UM country is stored in UM usermeta (for logged-in users) or in country column (for anonymous users).
     * This allows us to display "Hawaï" to the user even though UM stores "États-Unis d'Amérique".
     * 
     * @param string $table_name Name of the user_profiles table
     */
    private function migrateUserProfilesAddCountryCustomColumn($table_name) {
        global $wpdb;
        
        // Check if table exists
        $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name);
        if (!$table_exists) {
            return;
        }
        
        // Check if country_custom column exists
        $column_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = 'country_custom'",
                DB_NAME,
                $table_name
            )
        );
        
        if (empty($column_exists) || (int) $column_exists === 0) {
            // Add country_custom column after country column
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN country_custom VARCHAR(191) NULL DEFAULT NULL AFTER country");
        }
    }

    /**
     * Création de toutes les tables liées aux jeux :
     * - games_scores : stocke les scores des joueurs (connectés et anonymes)
     * - pokedle_daily : stocke les Pokedle quotidiens (date, génération, Pokémon)
     * - games_points : stocke les points des joueurs par période (quotidien/semaine/mois/année/total)
     */
    private function createGamesTables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $scores_table = pokehub_get_table('games_scores');
        $pokedle_daily_table = pokehub_get_table('pokedle_daily');
        $points_table = pokehub_get_table('games_points');

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Table principale des scores de jeux
        $sql_scores = "CREATE TABLE {$scores_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL DEFAULT NULL,
            game_type VARCHAR(50) NOT NULL DEFAULT 'pokedle',
            game_date DATE NOT NULL,
            pokemon_id BIGINT UNSIGNED NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            is_success TINYINT(1) NOT NULL DEFAULT 0,
            completion_time INT UNSIGNED NOT NULL DEFAULT 0,
            score_data LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY game_type (game_type),
            KEY game_date (game_date),
            KEY pokemon_id (pokemon_id),
            KEY is_success (is_success),
            UNIQUE KEY unique_user_game_date (user_id, game_type, game_date)
        ) {$charset_collate};";

        // Table des Pokedle quotidiens (historique indépendant des scores)
        $sql_pokedle_daily = "CREATE TABLE {$pokedle_daily_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            game_date DATE NOT NULL,
            generation_id BIGINT UNSIGNED NULL DEFAULT NULL,
            pokemon_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY game_date (game_date),
            KEY generation_id (generation_id),
            KEY pokemon_id (pokemon_id),
            UNIQUE KEY unique_date_generation (game_date, generation_id)
        ) {$charset_collate};";

        // Table des points des joueurs par période
        $sql_points = "CREATE TABLE {$points_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            period_type VARCHAR(20) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NULL,
            points INT UNSIGNED NOT NULL DEFAULT 0,
            games_completed INT UNSIGNED NOT NULL DEFAULT 0,
            games_succeeded INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY period_type (period_type),
            KEY period_start (period_start),
            KEY points (points),
            UNIQUE KEY unique_user_period (user_id, period_type, period_start)
        ) {$charset_collate};";

        dbDelta($sql_scores);
        dbDelta($sql_pokedle_daily);
        dbDelta($sql_points);
    }

    /**
     * Création des tables du module Collections Pokémon GO.
     * - collections : définitions des collections (nom, catégorie, options)
     * - collection_items : Pokémon possédés / à l'échange / manquants par collection
     */
    private function createCollectionsTables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $collections_table = pokehub_get_table('collections');
        $items_table       = pokehub_get_table('collection_items');

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql_collections = "CREATE TABLE {$collections_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(191) NOT NULL DEFAULT '',
            slug VARCHAR(191) NOT NULL DEFAULT '',
            share_token VARCHAR(20) NULL DEFAULT NULL,
            anonymous_ip VARCHAR(45) NULL DEFAULT NULL,
            category VARCHAR(64) NOT NULL DEFAULT 'custom',
            options LONGTEXT NULL,
            is_public TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY slug (slug),
            KEY anonymous_ip (anonymous_ip),
            UNIQUE KEY share_token (share_token),
            KEY category (category),
            KEY is_public (is_public),
            UNIQUE KEY unique_user_slug (user_id, slug)
        ) {$charset_collate};";

        $sql_items = "CREATE TABLE {$items_table} (
            collection_id BIGINT UNSIGNED NOT NULL,
            pokemon_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'missing',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (collection_id, pokemon_id),
            KEY pokemon_id (pokemon_id),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta($sql_collections);
        dbDelta($sql_items);
    }

    /**
     * Création des tables du module Eggs (pools globaux par période).
     * - global_egg_pools : pools par mois/saison avec dates
     * - global_egg_pool_pokemon : Pokémon par pool et type d'œuf (rareté 1-5, shiny, régional, CP)
     */
    private function createEggsTables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $pools_table   = pokehub_get_table('global_egg_pools');
        $pokemon_table = pokehub_get_table('global_egg_pool_pokemon');

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql_pools = "CREATE TABLE {$pools_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            period_type VARCHAR(20) NOT NULL DEFAULT 'month',
            period_value VARCHAR(50) NOT NULL DEFAULT '',
            start_ts INT UNSIGNED NOT NULL DEFAULT 0,
            end_ts INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY period_type (period_type),
            KEY start_ts (start_ts),
            KEY end_ts (end_ts)
        ) {$charset_collate};";

        $sql_pokemon = "CREATE TABLE {$pokemon_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pool_id BIGINT UNSIGNED NOT NULL,
            egg_type_id BIGINT UNSIGNED NOT NULL,
            pokemon_id BIGINT UNSIGNED NOT NULL,
            rarity TINYINT UNSIGNED NOT NULL DEFAULT 1,
            is_shiny TINYINT(1) NOT NULL DEFAULT 0,
            is_regional TINYINT(1) NOT NULL DEFAULT 0,
            is_worldwide_override TINYINT(1) NOT NULL DEFAULT 0,
            is_forced_shiny TINYINT(1) NOT NULL DEFAULT 0,
            cp_min SMALLINT UNSIGNED NULL DEFAULT NULL,
            cp_max SMALLINT UNSIGNED NULL DEFAULT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY pool_id (pool_id),
            KEY egg_type_id (egg_type_id),
            KEY pokemon_id (pokemon_id),
            KEY rarity (rarity)
        ) {$charset_collate};";

        dbDelta($sql_pools);
        dbDelta($sql_pokemon);
    }
}
