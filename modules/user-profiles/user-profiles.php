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
require_once POKE_HUB_USER_PROFILES_PATH . '/functions/user-profiles-keycloak-sync.php';   // Keycloak nickname synchronization
require_once POKE_HUB_USER_PROFILES_PATH . '/functions/user-profiles-friend-codes-helpers.php';    // Friend codes helpers
require_once POKE_HUB_USER_PROFILES_PATH . '/admin/user-profiles-admin.php';        // Admin interface
require_once POKE_HUB_USER_PROFILES_PATH . '/public/user-profiles-shortcode.php';   // Shortcode
require_once POKE_HUB_USER_PROFILES_PATH . '/public/user-profiles-friend-codes-header.php';           // Header adaptatif
require_once POKE_HUB_USER_PROFILES_PATH . '/public/user-profiles-friend-codes-form.php';             // Reusable friend code form
require_once POKE_HUB_USER_PROFILES_PATH . '/public/user-profiles-friend-codes-filters-template.php'; // Reusable filters template
require_once POKE_HUB_USER_PROFILES_PATH . '/public/user-profiles-friend-codes-list-template.php';   // Reusable friend codes list template
require_once POKE_HUB_USER_PROFILES_PATH . '/public/user-profiles-friend-codes-shortcode.php';        // Friend codes public shortcode
require_once POKE_HUB_USER_PROFILES_PATH . '/public/user-profiles-vivillon-shortcode.php';            // Vivillon shortcode

// Load pages creation function
require_once POKE_HUB_USER_PROFILES_PATH . '/functions/user-profiles-pages.php';

/**
 * Admin assets for admin interface
 */
function poke_hub_user_profiles_admin_assets($hook) {
    // Load only on module pages
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'poke-hub-user-profiles') === false) {
        return;
    }

    // Le CSS admin unifié est chargé via admin-unified.css (dans le plugin principal)

    // Select2 CSS
    wp_enqueue_style(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
        [],
        '4.1.0'
    );

    // Select2 JS
    wp_enqueue_script(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        ['jquery'],
        '4.1.0',
        true
    );

    wp_enqueue_script(
        'pokehub-user-profiles-admin-script',
        POKE_HUB_URL . 'assets/js/poke-hub-user-profiles-admin.js',
        ['jquery', 'select2'],
        POKE_HUB_VERSION,
        true
    );

    // Initialize Select2 for profile form selects (admin)
    wp_add_inline_script('select2', "
    jQuery(document).ready(function($) {
        if (typeof $.fn.select2 !== 'undefined') {
            // Initialize Select2 on country, team, and scatterbug_pattern selects
            // IMPORTANT: Only target <select> elements, not table headers
            // Only in forms (not in table headers or filters)
            $('select#country, select#team, select#scatterbug_pattern').each(function() {
                var \$select = $(this);
                // Skip if it's in a table header (thead) or in filter nav
                if (\$select.closest('thead, .tablenav').length > 0) {
                    return;
                }
                // Only initialize on actual select elements in forms
                if (!\$select.data('select2') && \$select.is('select')) {
                    // Find the closest form field wrapper for dropdownParent (admin uses admin-lab-form-section)
                    var \$parent = \$select.closest('.admin-lab-form-section, .form-table, td');
                    if (!\$parent.length) {
                        \$parent = \$select.parent();
                    }
                    \$select.select2({
                        width: '100%',
                        allowClear: true,
                        placeholder: \$select.find('option[value=\"\"]').text() || 'Select...',
                        dropdownParent: \$parent.length ? \$parent : $('body')
                    });
                }
            });
        }
    });
    ");
}
add_action('admin_enqueue_scripts', 'poke_hub_user_profiles_admin_assets');

/**
 * Screen options pour la page User Profiles
 */
add_action('load-poke-hub_page_poke-hub-user-profiles', function() {
    // Option "per page"
    $args = [
        'label'   => __('User profiles per page', 'poke-hub'),
        'default' => 20,
        'option'  => 'pokehub_user_profiles_per_page',
    ];
    add_screen_option('per_page', $args);
    
    // Les colonnes seront automatiquement disponibles dans Screen Options
    // grâce à get_hidden_columns() dans la classe PokeHub_User_Profiles_List_Table
});

// Sauvegarde de l'option "per page"
add_filter('set-screen-option', function($status, $option, $value) {
    if ('pokehub_user_profiles_per_page' === $option) {
        return (int) $value;
    }
    return $status;
}, 10, 3);

// Hook pour gérer les colonnes dans les Screen Options
add_filter('manage_poke-hub_page_poke-hub-user-profiles_columns', function($columns) {
    if (class_exists('PokeHub_User_Profiles_List_Table')) {
        $table = new PokeHub_User_Profiles_List_Table();
        return $table->get_columns();
    }
    return $columns;
});

/**
 * Hook into subscription_accounts updates to sync user_profiles.
 * 
 * This listens for WordPress hooks that might indicate subscription_accounts changes.
 * Other plugins can also trigger the action 'poke_hub_sync_user_profile_from_subscription'
 * with the user_id as parameter.
 * 
 * This hook is safe to call even if the table doesn't exist yet (function checks for table existence).
 */
add_action('poke_hub_sync_user_profile_from_subscription', function($user_id) {
    if (function_exists('poke_hub_sync_user_profile_ids_from_subscription')) {
        poke_hub_sync_user_profile_ids_from_subscription($user_id);
    }
}, 10, 1);

// Hook when user profile is saved to sync IDs from subscription_accounts
add_action('poke_hub_user_profile_saved', function($user_id, $profile, $discord_id) {
    if (function_exists('poke_hub_sync_user_profile_ids_on_save')) {
        poke_hub_sync_user_profile_ids_on_save($user_id, $discord_id);
    }
}, 10, 3);

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

    // Select2 CSS
    wp_enqueue_style(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
        [],
        '4.1.0'
    );

    // Select2 JS
    wp_enqueue_script(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        ['jquery'],
        '4.1.0',
        true
    );

    // JavaScript for checkbox state management (using generic classes)
    wp_enqueue_script(
        'pokehub-user-profiles-um-script',
        POKE_HUB_URL . 'assets/js/poke-hub-user-profiles-um.js',
        ['jquery', 'select2'],
        POKE_HUB_VERSION,
        true
    );

    // Select2 initialization is now handled in poke-hub-user-profiles-um.js
    // No need for inline script here to avoid conflicts
}
add_action('wp_enqueue_scripts', 'poke_hub_user_profiles_frontend_assets', 20);

/**
 * Front-end assets for shortcode (can be on any page)
 */
function poke_hub_user_profiles_shortcode_assets() {
    // Only load if shortcode is present on the page
    global $post;
    if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'poke_hub_user_profile')) {
        return;
    }

    // Select2 CSS
    wp_enqueue_style(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
        [],
        '4.1.0'
    );

    // Select2 JS
    wp_enqueue_script(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        ['jquery'],
        '4.1.0',
        true
    );

    // JavaScript for checkbox state management and Select2 initialization (using generic classes)
    wp_enqueue_script(
        'pokehub-user-profiles-um-script',
        POKE_HUB_URL . 'assets/js/poke-hub-user-profiles-um.js',
        ['jquery', 'select2'],
        POKE_HUB_VERSION,
        true
    );

    // Select2 initialization is now handled in poke-hub-user-profiles-um.js
    // No need for inline script here to avoid conflicts
}
add_action('wp_enqueue_scripts', 'poke_hub_user_profiles_shortcode_assets', 20);

/**
 * Front-end assets for friend codes shortcode
 */
function poke_hub_friend_codes_shortcode_assets() {
    // Load assets if:
    // 1. Shortcode is in post content, OR
    // 2. On Ultimate Member profile page (shortcode may be rendered via do_shortcode() in templates)
    global $post;
    
    $should_load = false;
    
    // Check if shortcode is in post content
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'poke_hub_friend_codes') || has_shortcode($post->post_content, 'poke_hub_vivillon'))) {
        $should_load = true;
    }
    
    // Check if on Ultimate Member profile page
    if (!$should_load && function_exists('um_is_core_page') && um_is_core_page('user')) {
        $should_load = true;
    }
    
    // Also check if we're on a page that might use the shortcode via do_shortcode()
    // (e.g., in custom templates)
    if (!$should_load && function_exists('um_get_requested_user') && um_get_requested_user()) {
        $should_load = true;
    }
    
    if (!$should_load) {
        return;
    }

    // Select2 CSS for filters
    wp_enqueue_style(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
        [],
        '4.1.0'
    );

    // Select2 JS
    wp_enqueue_script(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        ['jquery'],
        '4.1.0',
        true
    );

    // Friend codes CSS
    wp_enqueue_style(
        'poke-hub-friend-codes',
        POKE_HUB_URL . 'assets/css/user-profiles-friend-codes.css',
        [],
        POKE_HUB_VERSION
    );

    // Friend codes JS
    wp_enqueue_script(
        'poke-hub-friend-codes',
        POKE_HUB_URL . 'assets/js/user-profiles-friend-codes.js',
        ['jquery', 'select2'],
        POKE_HUB_VERSION,
        true
    );
    
    // Localize script for translations
    wp_localize_script('poke-hub-friend-codes', 'pokeHubFriendCodes', [
        'copySuccess' => __('✓ Copié!', 'poke-hub'),
        'copyError' => __('Erreur lors de la copie', 'poke-hub'),
    ]);

    // Initialize Select2 for friend codes selects
    wp_add_inline_script('poke-hub-friend-codes', "
    jQuery(document).ready(function($) {
        if (typeof $.fn.select2 !== 'undefined') {
            $('.friend-codes-dashboard .me5rine-lab-form-select').select2({
                width: '100%',
                allowClear: true
            });
        }
    });
    ");
}
add_action('wp_enqueue_scripts', 'poke_hub_friend_codes_shortcode_assets', 20);

// Load pages creation function
require_once POKE_HUB_USER_PROFILES_PATH . '/functions/user-profiles-pages.php';

/**
 * Hook lors de l'activation du module user-profiles
 * Note: WordPress convertit les tirets en underscores dans les noms d'actions
 */
add_action('poke_hub_user-profiles_module_activated', 'poke_hub_user_profiles_create_pages');
add_action('poke_hub_user_profiles_module_activated', 'poke_hub_user_profiles_create_pages'); // Version avec underscore au cas où

/**
 * Créer les pages lors de l'activation du plugin si le module est actif
 * Utiliser 'init' au lieu de 'plugins_loaded' pour éviter les erreurs de traduction et wp_rewrite
 * Respecte l'option poke_hub_user_profiles_auto_create_pages
 */
add_action('init', function() {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    
    if (!poke_hub_is_module_active('user-profiles')) {
        return;
    }
    
    // Vérifier si la création automatique est activée
    $auto_create = get_option('poke_hub_user_profiles_auto_create_pages', true);
    if (!$auto_create) {
        return;
    }
    
    // Vérifier si au moins une page n'existe pas
    $friend_codes_page_id = get_option('poke_hub_user_profiles_page_friend-codes');
    $vivillon_page_id = get_option('poke_hub_user_profiles_page_vivillon');
    
    // Si au moins une page manque, créer toutes les pages
    if ((!$friend_codes_page_id || !get_post_status($friend_codes_page_id)) || 
        (!$vivillon_page_id || !get_post_status($vivillon_page_id))) {
        if (function_exists('poke_hub_user_profiles_create_pages')) {
            poke_hub_user_profiles_create_pages();
        }
    }
}, 20);


