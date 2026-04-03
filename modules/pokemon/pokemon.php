<?php
// modules/pokemon/pokemon.php

if (!defined('ABSPATH')) {
    exit;
}

// Si le module "pokemon" n'est pas actif, on ne charge rien
if (!function_exists('poke_hub_is_module_active') || !poke_hub_is_module_active('pokemon')) {
    return;
}

define('POKE_HUB_POKEMON_PATH', __DIR__);
define('POKE_HUB_POKEMON_URL', POKE_HUB_URL . 'modules/pokemon/');

require_once __DIR__ . '/admin/pokemon-admin.php';
require_once __DIR__ . '/includes/pokemon-helpers.php';
require_once __DIR__ . '/includes/pokemon-cp-helpers.php';
require_once __DIR__ . '/includes/pokemon-regional-helpers.php';
require_once __DIR__ . '/includes/pokemon-regional-db-helpers.php';
require_once __DIR__ . '/includes/pokemon-regional-data.php'; // SINGLE SOURCE OF TRUTH for all regional data
require_once __DIR__ . '/includes/pokemon-regional-seed.php';
require_once __DIR__ . '/includes/pokemon-import-game-master-helpers.php'; // Contains regional auto-config functions for Game Master import
require_once __DIR__ . '/includes/pokemon-items-helpers.php';
require_once __DIR__ . '/includes/pokemon-weathers-helpers.php';
require_once __DIR__ . '/includes/pokemon-official-names-fetcher.php';
require_once __DIR__ . '/includes/pokemon-auto-translations.php';
require_once __DIR__ . '/includes/pokemon-translation-helpers.php';
require_once __DIR__ . '/includes/pokemon-type-bulbapedia-importer.php';
require_once __DIR__ . '/functions/pokemon-import-game-master.php';
require_once __DIR__ . '/functions/pokemon-import-game-master-batch.php';
require_once __DIR__ . '/public/pokemon-front-routing.php';
require_once __DIR__ . '/public/pokemon-entities-front-routing.php';

/**
 * Assets admin pour la page Pokémon (types, etc.)
 */
function poke_hub_pokemon_admin_assets($hook) {

    // On limite aux écrans où page = poke-hub-pokemon
    if (empty($_GET['page']) || $_GET['page'] !== 'poke-hub-pokemon') {
        return;
    }

    // Médiathèque WP (obligatoire pour wp.media)
    wp_enqueue_media();

    // Color picker natif
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');

    // JS de gestion média + URL pour les types
    wp_enqueue_script(
        'pokehub-media-url',
        POKE_HUB_URL . 'assets/js/pokehub-media-url.js',
        ['jquery', 'media-views', 'wp-color-picker'],
        POKE_HUB_VERSION,
        true
    );

    // Labels / textes utilisés dans le JS
    wp_localize_script(
        'pokehub-media-url',
        'pokemonTypesMedia',
        [
            'selectTitle' => __('Select or Upload Type Icon', 'poke-hub'),
            'buttonText'  => __('Use this image', 'poke-hub'),
            'tabUrl'      => __('Insert from URL', 'poke-hub'),
            'inputLabel'  => __('Image URL:', 'poke-hub'),
            'inputDesc'   => __('Enter a direct image URL to use instead of the media library.', 'poke-hub'),
            'svgOnly'     => __('The type icon must be an SVG (file or URL ending in .svg).', 'poke-hub'),
        ]
    );

    wp_localize_script(
        'pokehub-media-url',
        'pokehubTypeIconPreview',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action'  => 'pokehub_type_icon_preview',
            'nonce'   => wp_create_nonce('pokehub_type_icon_preview'),
        ]
    );
}

/**
 * Prévisualisation AJAX de l’icône type (SVG teinté comme en PHP).
 */
function poke_hub_ajax_type_icon_preview(): void {
    check_ajax_referer('pokehub_type_icon_preview', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'forbidden']);
    }

    $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
    $color = isset($_POST['color']) ? sanitize_text_field(wp_unslash($_POST['color'])) : '';

    if ($url === '' || !function_exists('pokehub_render_pokemon_type_icon_html')) {
        wp_send_json_error(['message' => 'bad_request']);
    }

    if (function_exists('pokehub_type_icon_url_path_ends_with_svg') && !pokehub_type_icon_url_path_ends_with_svg($url)) {
        wp_send_json_error(['message' => 'not_svg']);
    }

    $html = pokehub_render_pokemon_type_icon_html(
        $url,
        [
            'color'       => $color,
            'class'       => 'pokehub-type-icon--admin-preview',
            'aria_hidden' => true,
        ]
    );

    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_pokehub_type_icon_preview', 'poke_hub_ajax_type_icon_preview');
add_action('admin_enqueue_scripts', 'poke_hub_pokemon_admin_assets');