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
     * - pokemon_form_mappings
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
        $type_weather_links    = pokehub_get_table('pokemon_type_weather_links');
        $type_weakness_links   = pokehub_get_table('pokemon_type_weakness_links');
        $type_resistance_links = pokehub_get_table('pokemon_type_resistance_links');
        $form_mappings_table   = pokehub_get_table('pokemon_form_mappings');
        $pokemon_form_variants = pokehub_get_table('pokemon_form_variants');
        $evolutions_table      = pokehub_get_table('pokemon_evolutions');
        $items_table           = pokehub_get_table('items');
        $backgrounds_table     = pokehub_get_table('pokemon_backgrounds');
        $background_pokemon_links = pokehub_get_table('pokemon_background_pokemon_links');

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

        // 11) Mappings de formes (costumes, clones, etc.)
        $sql_form_mappings = "CREATE TABLE {$form_mappings_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pokemon_id_proto VARCHAR(100) NOT NULL,
            form_proto       VARCHAR(150) NOT NULL,
            form_slug        VARCHAR(100) NOT NULL DEFAULT '',
            label_suffix     VARCHAR(150) NOT NULL DEFAULT '',
            sort_order       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            flags            LONGTEXT NULL,

            PRIMARY KEY (id),
            UNIQUE KEY unique_mapping (pokemon_id_proto, form_proto),
            KEY form_slug (form_slug)
        ) {$charset_collate};";

        // 12) Registre global des formes de Pokémon
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
        $sql_backgrounds = "CREATE TABLE {$backgrounds_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(191) NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            image_url TEXT NULL,
            event_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            event_type VARCHAR(50) NOT NULL DEFAULT '', -- 'local_post', 'remote_post', 'special_local', 'special_remote'
            extra LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY event_id (event_id),
            KEY event_type (event_type)
        ) {$charset_collate};";

        // 16) Liaison Background ↔ Pokémon (plusieurs Pokémon peuvent avoir le même background)
        $sql_background_pokemon_links = "CREATE TABLE {$background_pokemon_links} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            background_id BIGINT(20) UNSIGNED NOT NULL,
            pokemon_id BIGINT(20) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY background_id (background_id),
            KEY pokemon_id (pokemon_id),
            UNIQUE KEY background_pokemon (background_id, pokemon_id)
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
        dbDelta($sql_type_weather_links);
        dbDelta($sql_type_weakness_links);
        dbDelta($sql_type_resistance_links);
        dbDelta($sql_form_mappings);
        dbDelta($sql_form_variants);
        dbDelta($sql_evolutions);
        dbDelta($sql_items);
        dbDelta($sql_backgrounds);
        dbDelta($sql_background_pokemon_links);
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
}
