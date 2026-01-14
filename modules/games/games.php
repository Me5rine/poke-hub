<?php
// modules/games/games.php

if (!defined('ABSPATH')) {
    exit;
}

if (!poke_hub_is_module_active('games')) {
    return;
}

define('POKE_HUB_GAMES_PATH', __DIR__);
define('POKE_HUB_GAMES_URL', POKE_HUB_URL . 'modules/games/');

require_once __DIR__ . '/functions/games-helpers.php';
require_once __DIR__ . '/functions/games-points.php';
require_once __DIR__ . '/functions/games-pokedle-helpers.php';
require_once __DIR__ . '/functions/games-pages.php';
require_once __DIR__ . '/admin/games-admin.php';
require_once __DIR__ . '/public/games-shortcode-pokedle.php';
require_once __DIR__ . '/public/games-shortcode-leaderboard.php';

/**
 * Assets front pour les jeux
 */
function poke_hub_games_assets() {
    // CSS principal
    wp_enqueue_style(
        'pokehub-games-style',
        POKE_HUB_URL . 'assets/css/poke-hub-games.css',
        [],
        POKE_HUB_VERSION
    );
}
add_action('wp_enqueue_scripts', 'poke_hub_games_assets');

/**
 * Hook lors de l'activation du module games
 * Note: WordPress convertit les tirets en underscores dans les noms d'actions
 */
add_action('poke_hub_games_module_activated', 'poke_hub_games_create_pages');

/**
 * Créer la page lors de l'activation du plugin si le module est actif
 * Utiliser 'init' au lieu de 'plugins_loaded' pour éviter les erreurs de traduction et wp_rewrite
 * Respecte l'option poke_hub_games_auto_create_pages
 */
add_action('init', function() {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    
    if (!poke_hub_is_module_active('games')) {
        return;
    }
    
    // Check if automatic creation is enabled
    $auto_create = get_option('poke_hub_games_auto_create_pages', true);
    if (!$auto_create) {
        return;
    }
    
    // Check if all required pages exist
    $required_pages = ['pokedle', 'games-leaderboard'];
    $missing_pages = false;
    
    foreach ($required_pages as $page_slug) {
        $option_key = 'poke_hub_games_page_' . $page_slug;
        $page_id = get_option($option_key);
        
        // If the page doesn't exist or the stored ID is invalid, mark as missing
        if (!$page_id || !get_post_status($page_id)) {
            $missing_pages = true;
            break;
        }
    }
    
    // If any page is missing, create all pages
    if ($missing_pages && function_exists('poke_hub_games_create_pages')) {
        poke_hub_games_create_pages();
    }
}, 20);

/**
 * Assets admin
 */
add_action('admin_enqueue_scripts', function ($hook) {
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    
    if ($page !== 'poke-hub-games') {
        return;
    }

    // Pas besoin de JS spécial pour l'admin pour l'instant
});

