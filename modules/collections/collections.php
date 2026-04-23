<?php
// modules/collections/collections.php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('poke_hub_is_module_active') || !poke_hub_is_module_active('collections')) {
    return;
}

/**
 * Module Collections Pokémon GO : suivi des collections (100%, shiny, costumés, etc.)
 * Dépend du module Pokémon.
 */
if (!poke_hub_is_module_active('pokemon')) {
    return;
}

define('POKE_HUB_COLLECTIONS_PATH', __DIR__);
define('POKE_HUB_COLLECTIONS_URL', POKE_HUB_URL . 'modules/collections/');

require_once POKE_HUB_COLLECTIONS_PATH . '/functions/collections-helpers.php';
require_once POKE_HUB_COLLECTIONS_PATH . '/functions/collections-pages.php';
require_once POKE_HUB_COLLECTIONS_PATH . '/public/collections-routing.php';
require_once POKE_HUB_COLLECTIONS_PATH . '/public/collections-shortcode.php';
require_once POKE_HUB_COLLECTIONS_PATH . '/public/collections-rest.php';

add_action('init', 'poke_hub_collections_maybe_add_share_token_column', 1);
add_action('init', 'poke_hub_collections_maybe_add_anonymous_ip_column', 2);

/**
 * Enqueue des assets front (appelé depuis le shortcode pour ne charger que sur les pages qui l'utilisent)
 */
function poke_hub_collections_enqueue_front_assets() {
    if (did_action('poke_hub_collections_enqueue_assets')) {
        return;
    }
    do_action('poke_hub_collections_enqueue_assets');

    /* Même variables que les notices (notice-sucess, notice-warning) → global-colors.css en dépendance */
    wp_enqueue_style(
        'poke-hub-global-colors',
        POKE_HUB_URL . 'assets/css/global-colors.css',
        [],
        POKE_HUB_VERSION
    );
    wp_enqueue_style(
        'poke-hub-collections-front',
        POKE_HUB_URL . 'assets/css/poke-hub-collections-front.css',
        ['poke-hub-global-colors'],
        POKE_HUB_VERSION
    );

    wp_enqueue_script(
        'poke-hub-collections-front',
        POKE_HUB_COLLECTIONS_URL . 'assets/js/collections-front.js',
        ['jquery'],
        POKE_HUB_VERSION,
        true
    );

    $pokemon_assets_base = function_exists('poke_hub_pokemon_get_assets_base_url')
        ? poke_hub_pokemon_get_assets_base_url()
        : '';

    wp_localize_script('poke-hub-collections-front', 'pokeHubCollections', [
        'ajaxUrl'    => admin_url('admin-ajax.php'),
        'restUrl'    => rest_url('poke-hub/v1/'),
        'nonce'      => wp_create_nonce('wp_rest'),
        'isLoggedIn' => is_user_logged_in(),
        'pokemonIconsBase' => $pokemon_assets_base ? rtrim($pokemon_assets_base, '/') . '/' : '',
        'i18n'      => [
            'createCollection' => __('Create a collection', 'poke-hub'),
            'collectionName'   => __('Collection name', 'poke-hub'),
            'category'        => __('Collection category', 'poke-hub'),
            'optional'        => __('Options', 'poke-hub'),
            'public'          => __('Make this collection public', 'poke-hub'),
            'displayTiles'    => __('Display as tiles (1 click = owned)', 'poke-hub'),
            'displaySelect'  => __('List + missing selection', 'poke-hub'),
            'includeNational' => __('Include national Pokédex', 'poke-hub'),
            'includeGender'   => __('Include gender differences', 'poke-hub'),
            'includeForms'    => __('Include alternate forms', 'poke-hub'),
            'includeCostumes' => __('Include costumed Pokémon', 'poke-hub'),
            'includeSpecialAttacks' => __('Include special attacks', 'poke-hub'),
            'notLoggedInWarning' => __('You are not logged in. This collection will be stored locally on this device.', 'poke-hub'),
            'createAccountHint' => __('Create an account to save your collections.', 'poke-hub'),
            'cancel'          => __('Cancel', 'poke-hub'),
            'save'            => __('Create collection', 'poke-hub'),
            'owned'           => __('Owned', 'poke-hub'),
            'forTrade'        => __('For trade', 'poke-hub'),
            'missing'         => __('Missing', 'poke-hub'),
            'share'           => __('Share', 'poke-hub'),
            'shareLink'       => __('Link', 'poke-hub'),
            'shareImage'      => __('Image', 'poke-hub'),
            'anonymousBannerOne'   => __('A collection was created from this connection (this device). Do you want to add it to your account?', 'poke-hub'),
            'anonymousBannerMany' => __('%d collections were created from this connection. Do you want to add them to your account?', 'poke-hub'),
            'generationOther'       => __('Other / unknown region', 'poke-hub'),
        ],
    ]);
}
