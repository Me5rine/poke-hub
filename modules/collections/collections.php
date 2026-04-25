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
add_action('init', 'poke_hub_collections_maybe_add_anonymous_owner_key_column', 3);

/**
 * Enqueue des assets front (appelé depuis le shortcode pour ne charger que sur les pages qui l'utilisent)
 */
function poke_hub_collections_enqueue_front_assets() {
    if (did_action('poke_hub_collections_enqueue_assets')) {
        return;
    }
    do_action('poke_hub_collections_enqueue_assets');

    /* Même variables que les notices (notice-sucess, notice-warning) → global-colors.css en dépendance */
    poke_hub_enqueue_bundled_front_style('poke-hub-global-colors', 'global-colors.css', []);
    poke_hub_enqueue_bundled_front_style('poke-hub-collections-front', 'poke-hub-collections-front.css', [
        'poke-hub-global-colors',
    ]);

    // Réordonne en dernier (après thème) quand le lot plugin est actif ; sinon seul filet de secours
    if (apply_filters('poke_hub_enqueue_collections_cascade_late', true)) {
        $late = POKE_HUB_PATH . 'assets/css/poke-hub-collections-cascade-late.css';
        if (is_readable($late)) {
            $late_deps = function_exists('poke_hub_is_plugin_bundled_front_css_enabled') && poke_hub_is_plugin_bundled_front_css_enabled()
                ? ['poke-hub-collections-front']
                : [];
            wp_enqueue_style(
                'poke-hub-collections-cascade-late',
                POKE_HUB_URL . 'assets/css/poke-hub-collections-cascade-late.css',
                $late_deps,
                POKE_HUB_VERSION
            );
        }
    }

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
        /* Catégorie => clés data-collections-control masquées (création + défauts sauvegarde édition). Voir docs/COLLECTIONS_MODULE.md. */
        'settingsHiddenByCategory' => function_exists('poke_hub_collections_settings_hidden_control_keys_map_for_ui')
            ? poke_hub_collections_settings_hidden_control_keys_map_for_ui()
            : [],
        /* Console : traces du bloc « phrases GO » (changement des select, rebuild). */
        'debugPogo'  => (defined('WP_DEBUG') && WP_DEBUG),
        'pokemonIconsBase' => $pokemon_assets_base ? rtrim($pokemon_assets_base, '/') . '/' : '',
        'i18n'      => [
            'createCollection' => __('Create a collection', 'poke-hub'),
            'collectionName'   => __('Collection name', 'poke-hub'),
            'category'        => __('Collection category', 'poke-hub'),
            'optional'        => __('Options', 'poke-hub'),
            'public'          => __('Make this collection public', 'poke-hub'),
            'displayTiles'    => __('Display as tiles (1 click = owned)', 'poke-hub'),
            'displaySelect'  => __('Tiles + lists below (legend hidden)', 'poke-hub'),
            'displayTilesSelect' => __('Tiles + list selects (owned / for trade)', 'poke-hub'),
            'addSelectors'   => __('Add selectors to add Pokémon', 'poke-hub'),
            'includeNational' => __('Show Pokédex numbers', 'poke-hub'),
            'includeGender'   => __('Include sexual dimorphism', 'poke-hub'),
            'includeBothSexes' => __('Include male and female', 'poke-hub'),
            'onePerSpecies'   => __('Show only one entry per species', 'poke-hub'),
            'includeRegionalForms' => __('Include regional forms', 'poke-hub'),
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
            'pogoNoPool'            => __('The Pokémon list is not loaded yet, or the collection is empty.', 'poke-hub'),
            'pogoEmptyStatus'      => __('No Pokémon match the selected status in this list.', 'poke-hub'),
            'pogoCopy'              => __('Copy', 'poke-hub'),
            'pogoCopied'            => __('Copied!', 'poke-hub'),
            'pogoNudge'            => __('If a line fails in-game, check the name or your game version.', 'poke-hub'),
            'pogoGroupBase'         => __('Classic', 'poke-hub'),
            'pogoGroupBaseDex'      => __('Classic #', 'poke-hub'),
            'pogoGroupAlola'        => __('Alola', 'poke-hub'),
            'pogoGroupGalar'        => __('Galar', 'poke-hub'),
            'pogoGroupPaldea'       => __('Paldea', 'poke-hub'),
            'pogoGroupHisui'        => __('Hisui', 'poke-hub'),
            'pogoGroupMega'         => __('Mega', 'poke-hub'),
            'pogoGroupMegaDex'      => __('Mega #', 'poke-hub'),
            'pogoGroupGigamax'      => __('Gigantamax', 'poke-hub'),
            'pogoGroupDynamax'      => __('Dynamax', 'poke-hub'),
            'pogoGroupMale'         => __('Male', 'poke-hub'),
            'pogoGroupFemale'       => __('Female', 'poke-hub'),
            'pogoGroupCostume'      => __('Costume / event', 'poke-hub'),
            'pogoGroupCostumeDex'   => __('Costume #', 'poke-hub'),
            'pogoGroupFond'         => __('Backgrounds', 'poke-hub'),
            'pogoGroupFondDex'      => __('Backgrounds #', 'poke-hub'),
            'pogoGroupFond_dynamax' => __('Dynamax + background', 'poke-hub'),
            'pogoGroupFond_dynamaxDex' => __('Dynamax + BG #', 'poke-hub'),
            'pogoGroupFond_gigamax' => __('Gigantamax + background', 'poke-hub'),
            'pogoGroupFond_gigamaxDex' => __('G-Max + BG #', 'poke-hub'),
            'collectionDefaultNameLegendaryMythicalUltra' => __('Legendary, Mythical & Ultra Beasts', 'poke-hub'),
        ],
    ]);
}
