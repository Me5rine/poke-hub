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
define('poke_hub_POKEMON_URL', POKE_HUB_URL . 'modules/pokemon/');

require_once __DIR__ . '/admin/pokemon-admin.php';
require_once __DIR__ . '/includes/pokemon-helpers.php';
require_once __DIR__ . '/includes/pokemon-cp-helpers.php';
require_once __DIR__ . '/includes/pokemon-images-helpers.php';
require_once __DIR__ . '/includes/pokemon-import-game-master-helpers.php';
require_once __DIR__ . '/includes/pokemon-items-helpers.php';
require_once __DIR__ . '/includes/pokemon-weathers-helpers.php';
require_once __DIR__ . '/includes/pokemon-official-names-fetcher.php';
require_once __DIR__ . '/includes/pokemon-auto-translations.php';
require_once __DIR__ . '/includes/pokemon-translation-helpers.php';
require_once __DIR__ . '/includes/pokemon-type-bulbapedia-importer.php';
require_once __DIR__ . '/functions/pokemon-import-game-master.php';
require_once __DIR__ . '/functions/pokemon-import-game-master-batch.php';

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
        ['jquery', 'media-views'],
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
        ]
    );

    // Optionnel : initialiser le color picker sur nos champs
    wp_add_inline_script(
        'pokehub-media-url',
        'jQuery(function($){ $(".pokehub-color-field").wpColorPicker(); });'
    );
}
add_action('admin_enqueue_scripts', 'poke_hub_pokemon_admin_assets');