<?php
// File: includes/settings/settings-module-hooks.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Avant la sauvegarde de la liste des modules actifs Poké HUB.
 * On gère ici les dépendances (ex : events nécessite Me5rine LAB,
 * pokemon nécessite WP Offload Media) et la sanitation de la valeur.
 */
add_filter('pre_update_option_poke_hub_active_modules', function ($new_value, $old_value) {

    include_once ABSPATH . 'wp-admin/includes/plugin.php';

    // Dépendance : "events" → Me5rine LAB
    // Dépendance : "user-profiles" → Me5rine LAB (pour subscription_accounts et préfixe global)
    $is_me5rine_lab_active = is_plugin_active('me5rine-lab/me5rine-lab.php');

    if (!is_array($new_value)) {
        $new_value = [];
    }
    if (!is_array($old_value)) {
        $old_value = [];
    }

    // Bloquer "events" si Me5rine LAB n'est pas actif
    if (in_array('events', $new_value, true) && !$is_me5rine_lab_active) {
        $new_value = array_diff($new_value, ['events']);

        set_transient('poke_hub_admin_notice', [
            'message' => __('Events module could not be activated because Me5rine LAB is not installed or active.', 'poke-hub'),
            'type'    => 'error',
        ], 30);
    }

    // Bloquer "user-profiles" si Me5rine LAB n'est pas actif
    if (in_array('user-profiles', $new_value, true) && !$is_me5rine_lab_active) {
        $new_value = array_diff($new_value, ['user-profiles']);

        set_transient('poke_hub_admin_notice', [
            'message' => __('User Profiles module could not be activated because Me5rine LAB is not installed or active.', 'poke-hub'),
            'type'    => 'error',
        ], 30);
    }

    // Sanitize global si helper dispo
    if (function_exists('poke_hub_sanitize_active_modules')) {
        $new_value = poke_hub_sanitize_active_modules($new_value);
    }

    return $new_value;
}, 10, 2);

/**
 * Valeur par défaut de l’option si elle n’existe pas encore.
 */
add_filter('default_option_poke_hub_active_modules', function ($value = false) {
    return is_array($value) ? $value : [];
}, 10, 3);

/**
 * Détection d’activation / désactivation de modules.
 * On déclenche des hooks du type : poke_hub_{module}_module_activated / deactivated.
 */
add_action('update_option_poke_hub_active_modules', function ($old_value, $new_value) {

    if (!is_array($new_value)) {
        return;
    }
    if (!is_array($old_value)) {
        $old_value = [];
    }

    // Modules activés
    $activated = array_diff($new_value, $old_value);
    foreach ($activated as $module) {
        do_action("poke_hub_{$module}_module_activated");
    }

    // Modules désactivés
    $deactivated = array_diff($old_value, $new_value);
    foreach ($deactivated as $module) {
        do_action("poke_hub_{$module}_module_deactivated");
    }

    // Historique
    update_option('poke_hub_last_active_modules', $new_value);

}, 10, 2);

/**
 * Affichage des notices stockées dans les transients.
 */
add_action('admin_notices', function () {
    $notice = get_transient('poke_hub_admin_notice');
    if ($notice && !empty($notice['message'])) {
        $type = !empty($notice['type']) ? $notice['type'] : 'info';
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
        delete_transient('poke_hub_admin_notice');
    }
});

/**
 * Vérification / création des tables pour les modules qui en ont besoin.
 * Même méthode que Me5rine LAB :
 * - on inspecte les modules actifs
 * - on regarde si leurs tables existent
 * - si des tables manquent → Pokehub_DB::createTables() pour ces modules-là.
 */
add_action('admin_init', function () {

    if (!class_exists('Pokehub_DB')) {
        // Si pour une raison X ce fichier n'est pas encore chargé, on tente.
        if (defined('POKE_HUB_INCLUDES_DIR')) {
            $db_file = POKE_HUB_INCLUDES_DIR . 'pokehub-db.php';
            if (file_exists($db_file)) {
                require_once $db_file;
            }
        }
    }

    if (!class_exists('Pokehub_DB')) {
        return;
    }

    $active_modules = get_option('poke_hub_active_modules', []);
    if (!is_array($active_modules) || empty($active_modules)) {
        return;
    }

    // Liste des modules Poké HUB qui nécessitent des tables SQL,
    // avec la/les table(s) attendue(s) pour chacun.
    $modules_needing_tables = [
        'pokemon' => [
            pokehub_get_table('pokemon'),
            pokehub_get_table('pokemon_types'),
            pokehub_get_table('regions'),
            pokehub_get_table('generations'),
            pokehub_get_table('attacks'),
            pokehub_get_table('attack_stats'),
            pokehub_get_table('attack_type_links'),
            pokehub_get_table('pokemon_type_links'),
            pokehub_get_table('pokemon_attack_links'),
            pokehub_get_table('pokemon_weathers'),
            pokehub_get_table('pokemon_type_weather_links'),
            pokehub_get_table('pokemon_type_weakness_links'),
            pokehub_get_table('pokemon_type_resistance_links'),
            pokehub_get_table('pokemon_type_immune_links'),
            pokehub_get_table('pokemon_type_offensive_super_effective_links'),
            pokehub_get_table('pokemon_type_offensive_not_very_effective_links'),
            pokehub_get_table('pokemon_type_offensive_no_effect_links'),
            pokehub_get_table('pokemon_form_mappings'),
            pokehub_get_table('pokemon_form_variants'),
            pokehub_get_table('pokemon_evolutions'),
            pokehub_get_table('items'),
            pokehub_get_table('pokemon_backgrounds'),
            pokehub_get_table('pokemon_background_pokemon_links'),
        ],

        'events' => [
            pokehub_get_table('special_events'),
            pokehub_get_table('special_event_pokemon'),
            pokehub_get_table('special_event_bonus'),
            pokehub_get_table('special_event_pokemon_attacks'),
        ],

        'user-profiles' => [
            pokehub_get_table('user_profiles'),
        ],

        'games' => [
            pokehub_get_table('games_scores'),
            pokehub_get_table('pokedle_daily'),
            pokehub_get_table('games_points'),
        ],
    ];

    global $wpdb;
    $modules_to_create = [];

    foreach ($modules_needing_tables as $module => $tables) {
        $tables = (array) $tables;

        $needs_create = false;
        foreach ($tables as $table) {
            // On vérifie si la table existe physiquement dans la DB
            $exists = ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table);
            if (!$exists) {
                $needs_create = true;
                break;
            }
        }

        if ($needs_create && in_array($module, $active_modules, true)) {
            $modules_to_create[] = $module;
        }
    }

    if (!empty($modules_to_create)) {
        Pokehub_DB::getInstance()->createTables($modules_to_create);
    }
    
    // Migration des colonnes recurring pour special_events (même si la table existe déjà)
    if (in_array('events', $active_modules, true)) {
        $events_table = pokehub_get_table('special_events');
        if ($events_table && ($wpdb->get_var("SHOW TABLES LIKE '{$events_table}'") === $events_table)) {
            Pokehub_DB::getInstance()->migrateEventsRecurringColumns();
        }
    }
});
