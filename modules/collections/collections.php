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

    wp_enqueue_style(
        'poke-hub-collections-front',
        POKE_HUB_COLLECTIONS_URL . 'assets/css/collections-front.css',
        [],
        POKE_HUB_VERSION
    );

    wp_enqueue_script(
        'poke-hub-collections-front',
        POKE_HUB_COLLECTIONS_URL . 'assets/js/collections-front.js',
        ['jquery'],
        POKE_HUB_VERSION,
        true
    );

    $pokemon_assets_base = (function () {
        if (function_exists('poke_hub_pokemon_asset_url')) {
            return poke_hub_pokemon_asset_url('pokemon');
        }
        return rtrim((string) get_option('poke_hub_pokemon_assets_base_url', ''), '/') . get_option('poke_hub_assets_path_pokemon', '/pokemon-go/pokemon/');
    })();

    wp_localize_script('poke-hub-collections-front', 'pokeHubCollections', [
        'ajaxUrl'    => admin_url('admin-ajax.php'),
        'restUrl'    => rest_url('poke-hub/v1/'),
        'nonce'      => wp_create_nonce('wp_rest'),
        'isLoggedIn' => is_user_logged_in(),
        'pokemonIconsBase' => $pokemon_assets_base ? rtrim($pokemon_assets_base, '/') . '/' : '',
        'i18n'      => [
            'createCollection' => __('Créer une collection', 'poke-hub'),
            'collectionName'   => __('Nom de la collection', 'poke-hub'),
            'category'        => __('Catégorie de collection', 'poke-hub'),
            'optional'        => __('Options', 'poke-hub'),
            'public'          => __('Rendre cette collection publique', 'poke-hub'),
            'displayTiles'    => __('Afficher en tuiles (1 clic = possédé)', 'poke-hub'),
            'displaySelect'  => __('Liste + sélection des manquants', 'poke-hub'),
            'includeNational' => __('Inclure le Pokédex national', 'poke-hub'),
            'includeGender'   => __('Inclure les différences de genre', 'poke-hub'),
            'includeForms'    => __('Inclure les formes alternatives', 'poke-hub'),
            'includeCostumes' => __('Inclure les Pokémon costumés', 'poke-hub'),
            'includeSpecialAttacks' => __('Inclure les attaques spéciales', 'poke-hub'),
            'notLoggedInWarning' => __('Vous n\'êtes pas connecté. Cette collection sera stockée localement sur cet appareil.', 'poke-hub'),
            'createAccountHint' => __('Créez un compte pour sauvegarder vos collections.', 'poke-hub'),
            'cancel'          => __('Annuler', 'poke-hub'),
            'save'            => __('Créer la collection', 'poke-hub'),
            'owned'           => __('Possédé', 'poke-hub'),
            'forTrade'        => __('À l\'échange', 'poke-hub'),
            'missing'         => __('Manquant', 'poke-hub'),
            'share'           => __('Partager', 'poke-hub'),
            'shareLink'       => __('Lien', 'poke-hub'),
            'shareImage'      => __('Image', 'poke-hub'),
            'anonymousBannerOne'   => __('Une collection a été créée depuis cette connexion (cet appareil). Voulez-vous l’ajouter à votre compte ?', 'poke-hub'),
            'anonymousBannerMany' => __('%d collections ont été créées depuis cette connexion. Voulez-vous les ajouter à votre compte ?', 'poke-hub'),
        ],
    ]);
}
