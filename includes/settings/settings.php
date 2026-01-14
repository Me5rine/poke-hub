<?php
// File: includes/settings/settings.php

if (!defined('ABSPATH')) exit;

/**
 * Enregistrement des options Poké HUB.
 *
 * ⚠ Très important :
 * On NE met dans le group 'poke_hub_settings' que les options
 * réellement gérées via le formulaire "General" (options.php).
 *
 * Toutes les autres options (Sources, Game Master, etc.)
 * sont gérées manuellement via update_option() dans leurs onglets respectifs.
 */
function poke_hub_register_settings() {

    // Modules actifs
    register_setting(
        'poke_hub_settings',              // option_group
        'poke_hub_active_modules',        // option_name
        [
            'type'              => 'array',
            'sanitize_callback' => 'poke_hub_sanitize_active_modules',
            'default'           => [],
        ]
    );

    // Suppression des données à la désinstallation
    register_setting(
        'poke_hub_settings',
        'poke_hub_delete_data_on_uninstall',
        [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]
    );

    // User Profiles: création automatique des pages
    register_setting(
        'poke_hub_settings',
        'poke_hub_user_profiles_auto_create_pages',
        [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ]
    );

    // Games: création automatique des pages
    register_setting(
        'poke_hub_settings',
        'poke_hub_games_auto_create_pages',
        [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ]
    );

    // Games: mode développement (réinitialisation à chaque refresh)
    register_setting(
        'poke_hub_settings',
        'poke_hub_games_dev_mode',
        [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]
    );

}
add_action('admin_init', 'poke_hub_register_settings');

/**
 * Sanitize de la liste des modules actifs.
 */
function poke_hub_sanitize_active_modules($value): array {
    $registry = function_exists('poke_hub_get_modules_registry_safe')
        ? poke_hub_get_modules_registry_safe()
        : [];

    if (!is_array($value)) {
        $value = [];
    }

    $value = array_map('sanitize_text_field', $value);

    return array_values(array_intersect($value, array_keys($registry)));
}

/**
 * Page d'administration principale avec onglets (comme Me5rine LAB).
 */
function poke_hub_settings_ui() {
    $requested_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

    // Quels modules sont actifs ?
    $active_modules = get_option('poke_hub_active_modules', []);
    if (!is_array($active_modules)) {
        $active_modules = [];
    }

    $events_enabled  = in_array('events', $active_modules, true);
    $pokemon_enabled = in_array('pokemon', $active_modules, true);

    // Onglet Sources toujours visible (sources image et préfixe Pokémon disponibles à l'activation)
    $sources_enabled     = true;
    // Onglet Game Master visible seulement si le module Pokémon est actif
    $gamemaster_enabled  = $pokemon_enabled;
    // Onglet Translation visible si le module Pokémon est actif
    $translation_enabled = $pokemon_enabled;

    // Liste des onglets autorisés
    $allowed_tabs = ['general'];
    if ($sources_enabled) {
        $allowed_tabs[] = 'sources';
    }
    if ($gamemaster_enabled) {
        $allowed_tabs[] = 'gamemaster';
    }
    if ($translation_enabled) {
        $allowed_tabs[] = 'translation';
    }

    // Si on demande un onglet non autorisé (ex: désactivation de pokemon
    // alors qu'on est sur gamemaster), on revient sur "general"
    if (!in_array($requested_tab, $allowed_tabs, true)) {
        $active_tab = 'general';
    } else {
        $active_tab = $requested_tab;
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Poké HUB – Settings', 'poke-hub') . '</h1>';

    echo '<nav class="nav-tab-wrapper">';

    // Onglet Général (toujours là)
    echo '<a href="?page=poke-hub-settings&tab=general" class="nav-tab ' . ($active_tab === 'general' ? 'nav-tab-active' : '') . '">'
            . esc_html__('General', 'poke-hub') . '</a>';

    // Onglet Sources (Events / Pokémon)
    if ($sources_enabled) {
        echo '<a href="?page=poke-hub-settings&tab=sources" class="nav-tab ' . ($active_tab === 'sources' ? 'nav-tab-active' : '') . '">'
                . esc_html__('Sources', 'poke-hub') . '</a>';
    }

    // Onglet Game Master (si Pokémon actif)
    if ($gamemaster_enabled) {
        echo '<a href="?page=poke-hub-settings&tab=gamemaster" class="nav-tab ' . ($active_tab === 'gamemaster' ? 'nav-tab-active' : '') . '">'
                . esc_html__('Game Master', 'poke-hub') . '</a>';
    }

    // Onglet Translation (si module Pokémon actif)
    if ($translation_enabled) {
        echo '<a href="?page=poke-hub-settings&tab=translation" class="nav-tab ' . ($active_tab === 'translation' ? 'nav-tab-active' : '') . '">'
                . esc_html__('Translation', 'poke-hub') . '</a>';
    }

    echo '</nav>';

    // Inclusion des onglets
    $tabs_dir = __DIR__ . '/tabs/';

    if ($active_tab === 'general') {
        include $tabs_dir . 'settings-tab-general.php';
    } elseif ($active_tab === 'sources') {
        include $tabs_dir . 'settings-tab-sources.php';
    } elseif ($active_tab === 'gamemaster') {
        include $tabs_dir . 'settings-tab-gamemaster.php';
    } elseif ($active_tab === 'translation') {
        include $tabs_dir . 'settings-tab-translation.php';
    }

    echo '</div>';
}

add_action( 'wp_ajax_poke_hub_gm_status', function () {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'forbidden', 403 );
    }

    $status = get_option( 'poke_hub_gm_import_status', [] );
    $state  = get_option( 'poke_hub_gm_batch_state', [] );

    wp_send_json_success( [
        'status' => $status,
        'progress' => $state['progress'] ?? [],
    ] );
});
