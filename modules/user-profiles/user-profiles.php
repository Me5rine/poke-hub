<?php
// modules/user-profiles/user-profiles.php

if (!defined('ABSPATH')) {
    exit;
}

// If "user-profiles" module is not activated in global config, exit
if (!function_exists('poke_hub_is_module_active') || !poke_hub_is_module_active('user-profiles')) {
    return;
}

/**
 * User Profiles module path / URL constants
 */
define('POKE_HUB_USER_PROFILES_PATH', __DIR__);
define('POKE_HUB_USER_PROFILES_URL', POKE_HUB_URL . 'modules/user-profiles/');

/**
 * Load User Profiles module features
 */
require_once POKE_HUB_USER_PROFILES_PATH . '/includes/user-profiles-data.php';      // Centralized data definitions
require_once POKE_HUB_USER_PROFILES_PATH . '/functions/user-profiles-helpers.php';   // Helpers (includes Ultimate Member sync)
require_once POKE_HUB_USER_PROFILES_PATH . '/admin/user-profiles-admin.php';        // Admin interface
require_once POKE_HUB_USER_PROFILES_PATH . '/public/user-profiles-shortcode.php';   // Shortcode

/**
 * Admin assets for admin interface
 */
function poke_hub_user_profiles_admin_assets($hook) {
    // Load only on module pages
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'poke-hub-user-profiles') === false) {
        return;
    }

    wp_enqueue_style(
        'pokehub-user-profiles-admin-style',
        POKE_HUB_URL . 'assets/css/poke-hub-user-profiles-admin.css',
        [],
        POKE_HUB_VERSION
    );

    wp_enqueue_script(
        'pokehub-user-profiles-admin-script',
        POKE_HUB_URL . 'assets/js/poke-hub-user-profiles-admin.js',
        ['jquery'],
        POKE_HUB_VERSION,
        true
    );
}
add_action('admin_enqueue_scripts', 'poke_hub_user_profiles_admin_assets');

/**
 * Front-end assets for Ultimate Member integration
 * Note: CSS is handled by the theme (see docs/user-profiles/CSS_RULES.md)
 */
function poke_hub_user_profiles_frontend_assets() {
    // Load only on Ultimate Member profile pages
    if (!function_exists('um_is_core_page')) {
        return;
    }
    
    // Check if we're on a profile page
    if (!um_is_core_page('user')) {
        return;
    }

    // JavaScript for checkbox state management (using generic classes)
    wp_enqueue_script(
        'pokehub-user-profiles-um-script',
        POKE_HUB_URL . 'assets/js/poke-hub-user-profiles-um.js',
        ['jquery'],
        POKE_HUB_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'poke_hub_user_profiles_frontend_assets', 20);

