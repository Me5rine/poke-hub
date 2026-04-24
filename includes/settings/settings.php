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

    // Sous-menu admin « Outils temporaires » (imports ponctuels, etc.)
    register_setting(
        'poke_hub_settings',
        'poke_hub_temporary_tools_enabled',
        [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
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

    // User Profiles: seuil de signalements avant masquage d'un code ami
    register_setting(
        'poke_hub_settings',
        'poke_hub_friend_code_report_threshold',
        [
            'type'              => 'integer',
            'sanitize_callback' => 'poke_hub_sanitize_friend_code_report_threshold',
            'default'           => 3,
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
    
    // Games: activation du Pokedle
    register_setting(
        'poke_hub_games_settings',
        'poke_hub_games_pokedle_enabled',
        [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ]
    );

    // Collections: création automatique de la page
    register_setting(
        'poke_hub_settings',
        'poke_hub_collections_auto_create_pages',
        [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ]
    );

}
add_action('admin_init', 'poke_hub_register_settings');

/**
 * Sanitize du seuil de signalements de code ami.
 *
 * @param mixed $value
 * @return int
 */
function poke_hub_sanitize_friend_code_report_threshold($value): int {
    $threshold = absint($value);
    if ($threshold < 1) {
        $threshold = 1;
    }
    if ($threshold > 20) {
        $threshold = 20;
    }
    return $threshold;
}

/**
 * Liste des modules pour l'affichage (slug => libellé). Dérivée de la source unique settings-modules.php.
 *
 * @return array<string,string>
 */
function poke_hub_available_modules_for_display(): array {
    return function_exists('poke_hub_get_modules_labels') ? poke_hub_get_modules_labels() : [];
}

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

    // Onglet Sources toujours visible (sources image et préfixe Pokémon disponibles à l'activation)
    $sources_enabled     = true;
    // Liste des onglets autorisés
    $allowed_tabs = ['general'];
    if ($sources_enabled) {
        $allowed_tabs[] = 'sources';
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

    echo '</nav>';

    // Inclusion des onglets
    $tabs_dir = __DIR__ . '/tabs/';

    if ($active_tab === 'general') {
        include $tabs_dir . 'settings-tab-general.php';
    } elseif ($active_tab === 'sources') {
        include $tabs_dir . 'settings-tab-sources.php';
    }

    echo '</div>';
}

add_action( 'wp_ajax_poke_hub_gm_status', function () {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'forbidden', 403 );
    }

    $status = get_option( 'poke_hub_gm_import_status', [] );
    $state  = get_option( 'poke_hub_gm_batch_state', [] );

    if ( in_array( (string) ( $status['state'] ?? '' ), [ 'running', 'queued' ], true ) && ! empty( $state['updated_at'] ) ) {
        $last_ts = strtotime( (string) $state['updated_at'] );
        if ( $last_ts > 0 && ( time() - $last_ts ) > 600 ) {
            $status['state']   = 'error';
            $status['message'] = 'Import timeout/stalled. Check PHP memory/time limits and retry.';
            $state['progress'] = [ 'phase' => 'error', 'pct' => 100 ];
            if ( empty( $state['errors'] ) || ! is_array( $state['errors'] ) ) {
                $state['errors'] = [];
            }
            $state['errors'][] = [
                'time' => current_time( 'mysql' ),
                'step' => $state['step'] ?? 'unknown',
                'msg'  => 'Watchdog: import appears stalled (>10 minutes without progress).',
            ];
            $state['updated_at'] = current_time( 'mysql' );
            update_option( 'poke_hub_gm_import_status', $status, false );
            update_option( 'poke_hub_gm_batch_state', $state, false );
        }
    }

    wp_send_json_success( [
        'status' => $status,
        'progress' => $state['progress'] ?? [],
    ] );
});
