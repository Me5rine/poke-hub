<?php
/*
Plugin Name: Poké HUB
Plugin URI: https://poke-hub.fr
Description: Plugin modulaire pour le site Poké HUB (Pokémon GO, Pokédex, événements, actualités, outils...).
Version: 1.9.4
Author: Me5rine
Author URI: https://me5rine.com
Text Domain: poke-hub
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

// Charger les traductions
function poke_hub_load_textdomain() {
    load_plugin_textdomain(
        'poke-hub',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'poke_hub_load_textdomain');

// Récupérer la version depuis l'entête du plugin
$poke_hub_plugin_data = get_file_data(__FILE__, ['Version' => 'Version'], false);

// Définir les constantes principales
define('POKE_HUB_PATH', plugin_dir_path(__FILE__));
define('POKE_HUB_URL', plugin_dir_url(__FILE__));
define('POKE_HUB_VERSION', $poke_hub_plugin_data['Version']);

define('POKE_HUB_MODULES_DIR', POKE_HUB_PATH . 'modules/');
define('POKE_HUB_INCLUDES_DIR', POKE_HUB_PATH . 'includes/');

// Charger les fichiers nécessaires
require_once POKE_HUB_INCLUDES_DIR . 'functions/pokehub-helpers.php';
require_once POKE_HUB_INCLUDES_DIR . 'functions/pokehub-encryption.php';
require_once POKE_HUB_INCLUDES_DIR . 'settings/settings.php';
require_once POKE_HUB_INCLUDES_DIR . 'settings/settings-modules.php';
require_once POKE_HUB_INCLUDES_DIR . 'settings/settings-module-hooks.php';
require_once POKE_HUB_INCLUDES_DIR . 'admin-ui.php';
require_once POKE_HUB_INCLUDES_DIR . 'pokehub-db.php';

/**
 * Déclaration du menu d'admin Poké HUB.
 * Ordre souhaité :
 * 1. Dashboard
 * 2. Pokémon
 * 3. Bonus
 * 4. Events
 * 5. Raids (à venir)
 * 6. Eggs (à venir)
 * 7. Quests
 * 8. User Profiles
 * 9. Games
 * 10. Settings
 */
function poke_hub_admin_menu() {

    // Récupère les modules actifs
    $active_modules = get_option('poke_hub_active_modules', []);
    if (!is_array($active_modules)) {
        $active_modules = [];
    }

    // Top-level → dashboard Poké HUB
    add_menu_page(
        __('Poké HUB', 'poke-hub'),
        __('Poké HUB', 'poke-hub'),
        'manage_options',
        'poke-hub',
        'poke_hub_dashboard_page',
        'dashicons-buddicons-activity',
        60
    );

    // 1. Dashboard (priorité 10)
    add_submenu_page(
        'poke-hub',
        __('Dashboard', 'poke-hub'),
        __('Dashboard', 'poke-hub'),
        'manage_options',
        'poke-hub',
        'poke_hub_dashboard_page'
    );
}

/**
 * 2. Sous-menu Pokémon (priorité 11)
 */
function poke_hub_admin_menu_pokemon() {
    $active_modules = get_option('poke_hub_active_modules', []);
    if (!is_array($active_modules)) {
        $active_modules = [];
    }
    
    if (in_array('pokemon', $active_modules, true)) {
        add_submenu_page(
            'poke-hub',
            __('Pokémon', 'poke-hub'),
            __('Pokémon', 'poke-hub'),
            'manage_options',
            'poke-hub-pokemon',
            'poke_hub_pokemon_admin_ui'
        );
    }
}
add_action('admin_menu', 'poke_hub_admin_menu_pokemon', 11);

/**
 * 3. Sous-menu Bonus (priorité 12)
 */
function poke_hub_admin_menu_bonus() {
    $active_modules = get_option('poke_hub_active_modules', []);
    if (!is_array($active_modules)) {
        $active_modules = [];
    }
    
    if (in_array('bonus', $active_modules, true)) {
        add_submenu_page(
            'poke-hub',
            __('Bonus', 'poke-hub'),
            __('Bonus', 'poke-hub'),
            'manage_options',
            'edit.php?post_type=pokehub_bonus'
        );
    }
}
add_action('admin_menu', 'poke_hub_admin_menu_bonus', 12);

/**
 * 4. Sous-menu Events (priorité 13)
 */
function poke_hub_admin_menu_events() {
    $active_modules = get_option('poke_hub_active_modules', []);
    if (!is_array($active_modules)) {
        $active_modules = [];
    }
    
    if (in_array('events', $active_modules, true)) {
        add_submenu_page(
            'poke-hub',
            __('Events', 'poke-hub'),
            __('Events', 'poke-hub'),
            'manage_options',
            'poke-hub-events',
            'pokehub_render_special_events_page'
        );
    }
}
add_action('admin_menu', 'poke_hub_admin_menu_events', 13);

/**
 * 5. Sous-menu Raids (priorité 14) - À venir
 */
function poke_hub_admin_menu_raids() {
    // TODO: À implémenter quand le module Raids sera créé
    /*
    $active_modules = get_option('poke_hub_active_modules', []);
    if (!is_array($active_modules)) {
        $active_modules = [];
    }
    
    if (in_array('raids', $active_modules, true)) {
        add_submenu_page(
            'poke-hub',
            __('Raids', 'poke-hub'),
            __('Raids', 'poke-hub'),
            'manage_options',
            'poke-hub-raids',
            'poke_hub_raids_admin_ui'
        );
    }
    */
}
add_action('admin_menu', 'poke_hub_admin_menu_raids', 14);

/**
 * 6. Sous-menu Eggs (priorité 15) - À venir
 */
function poke_hub_admin_menu_eggs() {
    // TODO: À implémenter quand le module Eggs sera créé
    /*
    $active_modules = get_option('poke_hub_active_modules', []);
    if (!is_array($active_modules)) {
        $active_modules = [];
    }
    
    if (in_array('eggs', $active_modules, true)) {
        add_submenu_page(
            'poke-hub',
            __('Eggs', 'poke-hub'),
            __('Eggs', 'poke-hub'),
            'manage_options',
            'poke-hub-eggs',
            'poke_hub_eggs_admin_ui'
        );
    }
    */
}
add_action('admin_menu', 'poke_hub_admin_menu_eggs', 15);

/**
 * 8. Sous-menu User Profiles (priorité 17)
 */
function poke_hub_admin_menu_user_profiles() {
    $active_modules = get_option('poke_hub_active_modules', []);
    if (!is_array($active_modules)) {
        $active_modules = [];
    }
    
    if (in_array('user-profiles', $active_modules, true)) {
        add_submenu_page(
            'poke-hub',
            __('User Profiles', 'poke-hub'),
            __('User Profiles', 'poke-hub'),
            'manage_options',
            'poke-hub-user-profiles',
            'poke_hub_user_profiles_admin_ui'
        );
    }
}
add_action('admin_menu', 'poke_hub_admin_menu_user_profiles', 17);

/**
 * 9. Sous-menu Games (priorité 18)
 */
function poke_hub_admin_menu_games() {
    $active_modules = get_option('poke_hub_active_modules', []);
    if (!is_array($active_modules)) {
        $active_modules = [];
    }
    
    if (in_array('games', $active_modules, true)) {
        add_submenu_page(
            'poke-hub',
            __('Games', 'poke-hub'),
            __('Games', 'poke-hub'),
            'manage_options',
            'poke-hub-games',
            'poke_hub_games_admin_ui'
        );
    }
}
add_action('admin_menu', 'poke_hub_admin_menu_games', 18);

/**
 * 10. Sous-menu Settings (priorité 19) - Dernier élément
 */
function poke_hub_admin_menu_settings() {
    add_submenu_page(
        'poke-hub',
        __('Settings', 'poke-hub'),
        __('Settings', 'poke-hub'),
        'manage_options',
        'poke-hub-settings',
        'poke_hub_settings_ui'
    );
}
add_action('admin_menu', 'poke_hub_admin_menu_settings', 19);

add_action('admin_menu', 'poke_hub_admin_menu');

/**
 * Pages rattachées au menu Poké HUB
 */
function poke_hub_admin_pages() {
    return [
        'poke-hub',
        'poke-hub-settings',
        'poke-hub-pokemon',
        'poke-hub-events',
        'poke-hub-user-profiles',
        'poke-hub-games',
    ];
}

/**
 * Groupes de sous-menus (si tu ajoutes des écrans enfants plus tard)
 */
function poke_hub_submenu_groups() {
    return [
        // exemple plus tard si tu as plusieurs écrans pour un même item
        // 'poke-hub-pokemon' => ['poke-hub-pokemon', 'poke-hub-pokemon-edit'],
    ];
}

// Screen options pour la page Pokémon (hook "load-<screen_id>")
add_action( 'load-poke-hub_page_poke-hub-pokemon', 'poke_hub_pokemon_screen_options' );

/**
 * Écrans liés au CPT Bonus (pokehub_bonus)
 */
function poke_hub_is_bonus_related() {
    global $pagenow;

    // Liste des bonus
    if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'pokehub_bonus') {
        return true;
    }

    // Édition / création d’un bonus
    if ($pagenow === 'post.php' && isset($_GET['post']) && get_post_type((int) $_GET['post']) === 'pokehub_bonus') {
        return true;
    }
    if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'pokehub_bonus') {
        return true;
    }

    // Si plus tard tu ajoutes des taxos pour les bonus, tu pourras les gérer ici :
    /*
    if ($pagenow === 'edit-tags.php' && isset($_GET['taxonomy']) && in_array($_GET['taxonomy'], ['bonus_taxo_slug'], true)) {
        return true;
    }
    if ($pagenow === 'term.php' && isset($_GET['taxonomy']) && in_array($_GET['taxonomy'], ['bonus_taxo_slug'], true)) {
        return true;
    }
    */

    return false;
}

add_filter('parent_file', function ($parent_file) {
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

    // Écrans Bonus → parent forcé à Poké HUB
    if (poke_hub_is_bonus_related()) {
        return 'poke-hub';
    }

    // Écrans internes Poké HUB
    if ($page && in_array($page, poke_hub_admin_pages(), true)) {
        return 'poke-hub';
    }

    return $parent_file;
});

add_filter('submenu_file', function ($submenu_file) {
    $page   = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    $groups = poke_hub_submenu_groups();

    // Gestion de groupes (plus tard si tu ajoutes des écrans enfants)
    if ($page) {
        foreach ($groups as $parent_slug => $children) {
            if (in_array($page, $children, true)) {
                $submenu_file = $parent_slug;
                break;
            }
        }
    }

    // Écrans liés au CPT Bonus → on surligne le sous-menu Bonus
    if (poke_hub_is_bonus_related()) {
        $submenu_file = 'edit.php?post_type=pokehub_bonus';
    }

    return $submenu_file;
});

// Fichier pour les hooks personnalisés (comme pour Me5rine LAB)
define('POKE_HUB_HOOKS_DIR', WP_CONTENT_DIR . '/uploads/poke-hub');
define('POKE_HUB_HOOKS_FILE', POKE_HUB_HOOKS_DIR . '/custom-hooks.php');

if (file_exists(POKE_HUB_HOOKS_FILE)) {
    include_once POKE_HUB_HOOKS_FILE;
}

function poke_hub_ensure_custom_hooks_file() {
    // Créer le dossier s'il n'existe pas
    if (!file_exists(POKE_HUB_HOOKS_DIR)) {
        wp_mkdir_p(POKE_HUB_HOOKS_DIR);
    }

    // Créer le fichier avec un contenu par défaut s'il n'existe pas
    if (!file_exists(POKE_HUB_HOOKS_FILE)) {
        $default_content = "<?php\n// Add your custom hooks here\n";
        file_put_contents(POKE_HUB_HOOKS_FILE, $default_content);
    }
}

/**
 * Fonction d'activation du plugin : flush les rewrite rules
 */
function poke_hub_plugin_activation() {
    poke_hub_ensure_custom_hooks_file();
    
    // Flush les rewrite rules pour activer les routes personnalisées
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'poke_hub_plugin_activation');

/**
 * Chargement des helpers Pokémon publics même si le module n'est pas actif.
 * Les sources Pokémon (préfixe, images) doivent être disponibles à l'activation du plugin.
 * Ces helpers contiennent uniquement les fonctions publiques utilisées par d'autres modules.
 */
function poke_hub_load_pokemon_public_helpers() {
    $pokemon_public_helpers_path = POKE_HUB_INCLUDES_DIR . 'functions/pokemon-public-helpers.php';
    if (file_exists($pokemon_public_helpers_path)) {
        require_once $pokemon_public_helpers_path;
    }
}
add_action('plugins_loaded', 'poke_hub_load_pokemon_public_helpers', 15);

/**
 * Chargement des modules activés.
 */
function poke_hub_load_modules() {
    $active_modules = get_option('poke_hub_active_modules', []);
    if (!is_array($active_modules)) {
        $active_modules = [];
    }

    $registry = function_exists('poke_hub_get_modules_registry')
        ? poke_hub_get_modules_registry()
        : [];

    foreach ($active_modules as $slug) {
        if (isset($registry[$slug])) {
            $path = POKE_HUB_MODULES_DIR . $registry[$slug];

            if (file_exists($path)) {
                include_once $path;
            }
        }
    }
}
add_action('plugins_loaded', 'poke_hub_load_modules', 20);

/**
 * Ensure regional data is seeded if tables exist but are empty
 * Runs on admin_init to check periodically
 */
function poke_hub_ensure_regional_data_seeded() {
    // Only run if Pokemon module is active
    if (!function_exists('poke_hub_is_module_active') || !poke_hub_is_module_active('pokemon')) {
        return;
    }
    
    // Only run in admin or if user is logged in (to avoid performance issues on frontend)
    if (!is_admin() && !is_user_logged_in()) {
        return;
    }
    
    // Check and seed if needed (only runs if tables are empty)
    Pokehub_DB::getInstance()->ensureRegionalDataSeeded();
}
add_action('admin_init', 'poke_hub_ensure_regional_data_seeded');

/**
 * Charger le CSS admin unifié pour toutes les pages admin du plugin
 */
function poke_hub_enqueue_admin_unified_styles($hook) {
    // Vérifier si on est sur une page admin du plugin
    $poke_hub_pages = [
        'poke-hub',
        'poke-hub-settings',
        'poke-hub-pokemon',
        'poke-hub-events',
        'poke-hub-user-profiles',
        'poke-hub-games',
    ];
    
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    
    // Vérifier aussi les pages Bonus (CPT)
    $is_bonus_page = poke_hub_is_bonus_related();
    
    if (!in_array($page, $poke_hub_pages, true) && !$is_bonus_page) {
        return;
    }
    
    // Charger global-colors.css d'abord
    wp_enqueue_style(
        'poke-hub-global-colors',
        POKE_HUB_URL . 'assets/css/global-colors.css',
        [],
        POKE_HUB_VERSION
    );
    
    // Charger admin-unified.css ensuite
    wp_enqueue_style(
        'poke-hub-admin-unified',
        POKE_HUB_URL . 'assets/css/admin-unified.css',
        ['poke-hub-global-colors'],
        POKE_HUB_VERSION
    );
}
add_action('admin_enqueue_scripts', 'poke_hub_enqueue_admin_unified_styles');

