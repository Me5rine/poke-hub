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
require_once POKE_HUB_COLLECTIONS_PATH . '/functions/collections-pogo-keywords.php';
require_once POKE_HUB_COLLECTIONS_PATH . '/functions/collections-pages.php';
require_once POKE_HUB_COLLECTIONS_PATH . '/public/collections-routing.php';
require_once POKE_HUB_COLLECTIONS_PATH . '/public/collections-shortcode.php';
require_once POKE_HUB_COLLECTIONS_PATH . '/public/collections-rest.php';
if ( is_admin() ) {
    require_once POKE_HUB_COLLECTIONS_PATH . '/admin/collections-admin.php';
}

add_action('init', 'poke_hub_collections_maybe_add_share_token_column', 1);
add_action('init', 'poke_hub_collections_maybe_add_anonymous_ip_column', 2);
add_action('init', 'poke_hub_collections_maybe_add_anonymous_owner_key_column', 3);
add_action('init', 'poke_hub_collections_maybe_migrate_category_slugs_canonical', 4);

/**
 * Enqueue des assets front (appelé depuis le shortcode pour ne charger que sur les pages qui l'utilisent)
 */
function poke_hub_collections_enqueue_front_assets() {
    if (did_action('poke_hub_collections_enqueue_assets')) {
        return;
    }
    do_action('poke_hub_collections_enqueue_assets');

    if (function_exists('poke_hub_go_search_filters_ensure_table')) {
        poke_hub_go_search_filters_ensure_table();
    }

    /* Même variables que les notices (notice-sucess, notice-warning) → global-colors.css en dépendance */
    poke_hub_enqueue_bundled_front_style('poke-hub-global-colors', 'global-colors.css', []);
    poke_hub_enqueue_bundled_front_style('poke-hub-collections-front', 'poke-hub-collections-front.css', [
        'poke-hub-global-colors',
    ]);

    wp_enqueue_script(
        'poke-hub-collections-front',
        POKE_HUB_COLLECTIONS_URL . 'assets/js/collections-front.js',
        ['jquery'],
        poke_hub_plugin_asset_version('modules/collections/assets/js/collections-front.js'),
        true
    );

    $pokemon_assets_base = function_exists('poke_hub_pokemon_get_assets_base_url')
        ? poke_hub_pokemon_get_assets_base_url()
        : '';

    $collections_page_url = rtrim((string) get_permalink(), '/');
    $register_url           = get_option('users_can_register') ? (string) wp_registration_url() : '';

    wp_localize_script('poke-hub-collections-front', 'pokeHubCollections', [
        'pogoSearchKeywords' => poke_hub_collections_get_pogo_search_keywords(),
        'ajaxUrl'            => admin_url('admin-ajax.php'),
        'restUrl'    => rest_url('poke-hub/v1/'),
        'nonce'      => wp_create_nonce('wp_rest'),
        'isLoggedIn' => is_user_logged_in(),
        'collectionsBaseUrl' => $collections_page_url,
        'registerUrl'        => $register_url,
        'loginUrl'           => (string) wp_login_url($collections_page_url ?: home_url('/')),
        'categoryLabels'     => poke_hub_collections_get_categories(),
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
            'includeRegionalForms' => __('Include regional forms (Alola, Galar, Paldea, Hisui)', 'poke-hub'),
            'includeCostumes' => __('Include costumed Pokémon', 'poke-hub'),
            'includeSpecialAttacks' => __('Include special attacks', 'poke-hub'),
            'notLoggedInWarning' => __('You are not logged in. Your list is saved for this browser and connection; keep the link to open it again.', 'poke-hub'),
            'createAccountHint' => __('Create an account to manage several collections and sync them everywhere.', 'poke-hub'),
            'guestSecondCollectionTitle' => __('Create an account for another list', 'poke-hub'),
            'guestSecondCollectionBody' => __('You already have a collection without an account. Log in or register to create additional lists and keep them if you change device.', 'poke-hub'),
            'guestLocalBadge' => __('On this device only', 'poke-hub'),
            'cancel'          => __('Cancel', 'poke-hub'),
            'save'            => __('Create collection', 'poke-hub'),
            'collectionDefaultNameLuckyDex' => __( 'Lucky National Dex', 'poke-hub' ),
            'collectionDefaultNameShiny'    => __( 'My Shinies', 'poke-hub' ),
            'collectionDefaultNameAllPokemon' => __( 'My full collection', 'poke-hub' ),
            'collectionDefaultNameGoRegional' => __( 'Pokémon GO regionals', 'poke-hub' ),
            'collectionDefaultNameBabies' => __( 'Baby Pokémon', 'poke-hub' ),
            'collectionDefaultNameLucky'  => __( 'Lucky Pokémon', 'poke-hub' ),
            'owned'           => __('Owned', 'poke-hub'),
            'forTrade'        => __('For trade', 'poke-hub'),
            'forTradeProgressLegendCounted' => __('In this collection, "For trade" is counted as "Owned" for progress. You can turn this off in the collection settings.', 'poke-hub'),
            'forTradeProgressLegendSeparate' => __('In this collection, "For trade" is not counted as "Owned". You can change this in the collection settings.', 'poke-hub'),
            'missing'         => __('Missing', 'poke-hub'),
            'share'           => __('Share', 'poke-hub'),
            'shareLink'       => __('Link', 'poke-hub'),
            'shareImage'      => __('Image', 'poke-hub'),
            'anonymousBannerOne'   => __('A collection was created from this connection (this device). Do you want to add it to your account?', 'poke-hub'),
            'anonymousBannerMany' => __('%d collections were created from this connection. Do you want to add them to your account?', 'poke-hub'),
            'unnamedCollection' => __('Untitled collection', 'poke-hub'),
            'generationOther'       => __('Other / unknown region', 'poke-hub'),
            'generationNumber'      => __('Generation %d', 'poke-hub'),
            'finished'              => __('Finished', 'poke-hub'),
            'pogoNoPool'            => __('The Pokémon list is not loaded yet, or the collection is empty.', 'poke-hub'),
            'pogoEmptyStatus'      => __('No Pokémon match the selected status in this list.', 'poke-hub'),
            'pogoCopy'              => __('Copy', 'poke-hub'),
            'pogoCopied'            => __('Copied!', 'poke-hub'),
            'pogoNudge'            => __('If a line fails in-game, check the name or your game version.', 'poke-hub'),
            'pogoGroupBase'         => __('Classic', 'poke-hub'),
            'pogoGroupBaseDex'      => __('Classic #', 'poke-hub'),
            'pogoGroupShiny'        => __('Shiny', 'poke-hub'),
            'pogoGroupShinyDex'     => __('Shiny #', 'poke-hub'),
            'pogoGroupShadow'       => __('Shadow', 'poke-hub'),
            'pogoGroupShadowDex'    => __('Shadow #', 'poke-hub'),
            'pogoGroupPurified'     => __('Purified', 'poke-hub'),
            'pogoGroupPurifiedDex'  => __('Purified #', 'poke-hub'),
            'pogoGroupAlola'        => __('Alola', 'poke-hub'),
            'pogoGroupGalar'        => __('Galar', 'poke-hub'),
            'pogoGroupPaldea'       => __('Paldea', 'poke-hub'),
            'pogoGroupHisui'        => __('Hisui', 'poke-hub'),
            'pogoGroupMega'         => __('Mega', 'poke-hub'),
            'pogoGroupMegaDex'      => __('Mega #', 'poke-hub'),
            'pogoGroupGigamax'      => __('Gigantamax', 'poke-hub'),
            'pogoGroupDynamax'      => __('Dynamax', 'poke-hub'),
            'pogoGroupDynamaxMale'   => __('Dynamax (male)', 'poke-hub'),
            'pogoGroupDynamaxFemale' => __('Dynamax (female)', 'poke-hub'),
            'pogoGroupAlolaMale'     => __('Alola (male)', 'poke-hub'),
            'pogoGroupAlolaFemale'   => __('Alola (female)', 'poke-hub'),
            'pogoGroupGalarMale'     => __('Galar (male)', 'poke-hub'),
            'pogoGroupGalarFemale'   => __('Galar (female)', 'poke-hub'),
            'pogoGroupPaldeaMale'    => __('Paldea (male)', 'poke-hub'),
            'pogoGroupPaldeaFemale'  => __('Paldea (female)', 'poke-hub'),
            'pogoGroupHisuiMale'     => __('Hisui (male)', 'poke-hub'),
            'pogoGroupHisuiFemale'   => __('Hisui (female)', 'poke-hub'),
            'pogoGroupMegaMale'      => __('Mega (male)', 'poke-hub'),
            'pogoGroupMegaFemale'    => __('Mega (female)', 'poke-hub'),
            'pogoGroupGigamaxMale'   => __('Gigantamax (male)', 'poke-hub'),
            'pogoGroupGigamaxFemale' => __('Gigantamax (female)', 'poke-hub'),
            'pogoGroupShinyMale'     => __('Shiny (male)', 'poke-hub'),
            'pogoGroupShinyFemale'   => __('Shiny (female)', 'poke-hub'),
            'pogoGroupShadowMale'    => __('Shadow (male)', 'poke-hub'),
            'pogoGroupShadowFemale'  => __('Shadow (female)', 'poke-hub'),
            'pogoGroupPurifiedMale'  => __('Purified (male)', 'poke-hub'),
            'pogoGroupPurifiedFemale'=> __('Purified (female)', 'poke-hub'),
            'pogoGroupCostumeMale'   => __('Costume / event (male)', 'poke-hub'),
            'pogoGroupCostumeFemale' => __('Costume / event (female)', 'poke-hub'),
            'pogoGroupMale'         => __('Male', 'poke-hub'),
            'pogoGroupFemale'       => __('Female', 'poke-hub'),
            'pogoGroupCostume'      => __('Costume / event', 'poke-hub'),
            'pogoGroupCostumeDex'   => __('Costume #', 'poke-hub'),
            'pogoGroupFond'         => __('Backgrounds', 'poke-hub'),
            'pogoGroupFondDex'      => __('Backgrounds #', 'poke-hub'),
            'pogoGroupFond_lieu'    => __('Location background', 'poke-hub'),
            'pogoGroupFond_lieuDex' => __('Location BG #', 'poke-hub'),
            'pogoGroupFond_special' => __('Special background', 'poke-hub'),
            'pogoGroupFond_specialDex' => __('Special BG #', 'poke-hub'),
            'pogoGroupFond_dynamax' => __('Dynamax + background', 'poke-hub'),
            'pogoGroupFond_dynamaxDex' => __('Dynamax + BG #', 'poke-hub'),
            'pogoGroupFond_gigamax' => __('Gigantamax + background', 'poke-hub'),
            'pogoGroupFond_gigamaxDex' => __('G-Max + BG #', 'poke-hub'),
            'collectionDefaultNameLegendaryMythicalUltra' => __('Legendary, Mythical & Ultra Beasts', 'poke-hub'),
            'collectionDefaultNameMega'                 => __('Mega & Primal', 'poke-hub'),
            'collectionDefaultNameFallback'           => __('My collection', 'poke-hub'),
            'linkCopied'                              => __('Link copied to clipboard.', 'poke-hub'),
            'copyLinkManually'                        => __('Copy this link:', 'poke-hub'),
            'networkError'                            => __('Network error. Try again.', 'poke-hub'),
            'genericError'                           => __('Something went wrong.', 'poke-hub'),
            'deleteCollectionConfirm'                 => __('Delete the collection "%s"? This cannot be undone.', 'poke-hub'),
            'loadPoolFailed'                          => __('Could not load the Pokémon list.', 'poke-hub'),
            'localCollectionMissing'                  => __('This local collection could not be found.', 'poke-hub'),
            'offlineLocalSaveFailed'                  => __('Could not save this list on your device. Check storage space and private mode.', 'poke-hub'),
            'collectionNotFoundTitle'                 => __('Collection not found', 'poke-hub'),
            'batchAddError'                           => __('Could not add Pokémon. Try again.', 'poke-hub'),
            'shareDialogTitle'                       => __('Share collection', 'poke-hub'),
            'shareDialogHint'                        => __('Anyone with the link can open this collection when it is public.', 'poke-hub'),
            'copyShareLink'                           => __('Copy share link', 'poke-hub'),
            'shareCoverHeading'                      => __('Cover image', 'poke-hub'),
            'shareCoverAlt'                          => __('Collection cover image', 'poke-hub'),
            'shareCoverEmpty'                        => __('No cover URL is set. Add one under Sharing & visibility in settings.', 'poke-hub'),
            'dialogClose'                            => __('Close', 'poke-hub'),
            'selectListPresetRequired'               => __('Please select a list preset.', 'poke-hub'),
        ],
    ]);
}
