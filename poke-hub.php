<?php
/*
Plugin Name: Poké HUB
Plugin URI: https://example.com
Description: Plugin modulaire pour le site Poké HUB (Pokémon GO, Pokédex, événements, actualités, outils...).
Version: 1.5.7
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
require_once POKE_HUB_INCLUDES_DIR . 'settings/settings.php';
require_once POKE_HUB_INCLUDES_DIR . 'settings/settings-modules.php';
require_once POKE_HUB_INCLUDES_DIR . 'settings/settings-module-hooks.php';
require_once POKE_HUB_INCLUDES_DIR . 'admin-ui.php';
require_once POKE_HUB_INCLUDES_DIR . 'pokehub-db.php';

/**
 * Déclaration du menu d'admin Poké HUB.
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

    // Ajout d’un premier sous-menu "Dashboard"
    // (comme WordPress empêche l'entrée top-level d’être cliquée parfois)
    add_submenu_page(
        'poke-hub',
        __('Dashboard', 'poke-hub'),
        __('Dashboard', 'poke-hub'),
        'manage_options',
        'poke-hub',
        'poke_hub_dashboard_page'
    );

    // 🔥 Sous-menu Pokémon (si module pokemon actif)
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

    // 🔥 Sous-menu Events (si module events actif)
    if (in_array('events', $active_modules, true)) {
        add_submenu_page(
            'poke-hub',
            __('Events', 'poke-hub'),
            __('Events', 'poke-hub'),
            'manage_options',
            'poke-hub-events',
            'pokehub_render_special_events_page' // ou URL si CPT
        );
    }
    
    // 🔥 Sous-menu BONUS (seulement si module bonus actif)
    if (in_array('bonus', $active_modules, true)) {
        add_submenu_page(
            'poke-hub',
            __('Bonus', 'poke-hub'),
            __('Bonus', 'poke-hub'),
            'manage_options',
            'edit.php?post_type=pokehub_bonus'
        );
    }

    // Sous-menu Settings → toujours visible
    add_submenu_page(
        'poke-hub',
        __('Settings', 'poke-hub'),
        __('Settings', 'poke-hub'),
        'manage_options',
        'poke-hub-settings',
        'poke_hub_settings_ui'
    );
}

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

register_activation_hook(__FILE__, 'poke_hub_ensure_custom_hooks_file');

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

