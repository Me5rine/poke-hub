<?php
// modules/events/events.php

if (!defined('ABSPATH')) {
    exit;
}

if (!poke_hub_is_module_active('events')) {
    return;
}

define('POKE_HUB_EVENTS_PATH', __DIR__);
define('POKE_HUB_EVENTS_URL', POKE_HUB_URL . 'modules/events/');

require_once __DIR__ . '/functions/events-pages.php';

$pokehub_bonus_helpers = POKE_HUB_PATH . 'modules/bonus/functions/bonus-helpers.php';
if (is_readable($pokehub_bonus_helpers)) {
    require_once $pokehub_bonus_helpers;
}

require_once __DIR__ . '/functions/events-go-pass-helpers.php';
require_once __DIR__ . '/functions/events-admin-helpers.php';
require_once __DIR__ . '/functions/events-helpers.php';
require_once __DIR__ . '/admin/forms/events-admin-special-events-form.php';
require_once __DIR__ . '/admin/forms/events-admin-go-pass-form.php';
require_once __DIR__ . '/admin/events-admin-special-events.php';
require_once __DIR__ . '/functions/events-queries.php';
require_once __DIR__ . '/functions/events-render.php';
require_once __DIR__ . '/public/shortcode-events.php';
require_once __DIR__ . '/public/events-front-routing.php';

/**
 * Assets front (optionnel pour l'instant, mais prêt à être utilisé).
 */
function poke_hub_events_assets() {
    wp_enqueue_style(
        'poke-hub-global-colors',
        POKE_HUB_URL . 'assets/css/global-colors.css',
        [],
        POKE_HUB_VERSION
    );
    // CSS principal + Select2 (après les titres de blocs si le module Blocks est actif)
    $events_css_deps = ['poke-hub-global-colors'];
    if (wp_style_is('pokehub-blocks-front-style', 'registered')) {
        $events_css_deps[] = 'pokehub-blocks-front-style';
    }
    wp_enqueue_style('pokehub-events-style', POKE_HUB_URL . 'assets/css/poke-hub-events-front.css', $events_css_deps, POKE_HUB_VERSION);
    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');

    // CSS pour les pages d'événements spéciaux individuels
    if (get_query_var('pokehub_special_event')) {
        wp_enqueue_style('pokehub-special-event-single', POKE_HUB_URL . 'assets/css/poke-hub-special-events-single.css', [], POKE_HUB_VERSION);
    }

    // JS Select2
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);

    // Initialisation centralisée de Select2 pour le front-end
    wp_enqueue_script(
        'pokehub-front-select2',
        POKE_HUB_URL . 'assets/js/pokehub-front-select2.js',
        ['jquery', 'select2'],
        POKE_HUB_VERSION,
        true
    );

    // Script pour le toggle des quêtes
    wp_enqueue_script(
        'pokehub-events-quests',
        POKE_HUB_URL . 'assets/js/pokehub-events-quests.js',
        ['jquery'],
        POKE_HUB_VERSION,
        true
    );

    // Soumission automatique du formulaire lors du changement (spécifique aux événements)
    wp_add_inline_script('pokehub-front-select2', "
        jQuery(function($){
            $('#pokehub-event-type-select').on('select2:select select2:unselect', function(){
                $(this).closest('form').submit();
            });
        });
    ");
}
add_action('wp_enqueue_scripts', 'poke_hub_events_assets');

add_action('admin_enqueue_scripts', function ($hook) {
    // Vérifier à la fois le hook et le paramètre page pour plus de fiabilité
    $is_events_page = ($hook === 'poke-hub_page_poke-hub-events') || 
                      (!empty($_GET['page']) && $_GET['page'] === 'poke-hub-events');
    
    if (!$is_events_page) {
        return;
    }

    // Nécessaire pour wp.media
    wp_enqueue_media();

    // Select2 pour les sélections de Pokémon et le filtre de type d'événement
    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');

    // Script commun pour la media frame + onglet URL
    wp_enqueue_script(
        'pokehub-media-url',
        POKE_HUB_URL . 'assets/js/pokehub-media-url.js',
        ['jquery', 'media-views'],
        POKE_HUB_VERSION,
        true
    );

    // Textes spécifiques pour les événements spéciaux
    wp_localize_script(
        'pokehub-media-url',
        'pokemonEventsMedia',
        [
            'selectTitle' => __('Select an image for the special event', 'poke-hub'),
            'buttonText'  => __('Use this image', 'poke-hub'),
            'noImage'     => __('No image selected yet.', 'poke-hub'),
        ]
    );

    // Select2 JS
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
    
    // Initialiser Select2 pour le filtre de type d'événement
    wp_add_inline_script('select2', "
    jQuery(document).ready(function($) {
        if ($('#filter-by-event-type').length && typeof $.fn.select2 !== 'undefined') {
            $('#filter-by-event-type').select2({
                placeholder: '" . esc_js(__('Search event type...', 'poke-hub')) . "',
                allowClear: true,
                width: '200px',
                language: {
                    noResults: function() { return '" . esc_js(__('No results found', 'poke-hub')) . "'; },
                    searching: function() { return '" . esc_js(__('Searching...', 'poke-hub')) . "'; }
                }
            });
        }
    });
    ");

    // Ton script existant pour la gestion du formulaire (Pokémon, bonus, etc.)
    wp_enqueue_script(
        'pokehub-special-events-admin',
        POKE_HUB_URL . 'assets/js/pokehub-special-events-admin.js',
        ['jquery', 'pokehub-media-url', 'select2'],
        POKE_HUB_VERSION,
        true
    );

    $gp_action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : '';
    if (in_array($gp_action, ['add_go_pass', 'edit_go_pass'], true)) {
        wp_enqueue_script(
            'pokehub-admin-select2',
            POKE_HUB_URL . 'assets/js/pokehub-admin-select2.js',
            ['jquery', 'select2'],
            POKE_HUB_VERSION,
            true
        );

        $pokemon_list      = function_exists('pokehub_get_pokemon_for_select') ? pokehub_get_pokemon_for_select() : [];
        $mega_pokemon_list = function_exists('pokehub_get_mega_pokemon_for_select') ? pokehub_get_mega_pokemon_for_select() : [];
        $base_pokemon_list = function_exists('pokehub_get_base_pokemon_for_select') ? pokehub_get_base_pokemon_for_select() : [];
        $items_list        = function_exists('pokehub_get_items_for_select') ? pokehub_get_items_for_select() : [];

        wp_localize_script(
            'pokehub-admin-select2',
            'pokehubQuestsData',
            [
                'pokemon'          => $pokemon_list,
                'mega_pokemon'     => $mega_pokemon_list,
                'base_pokemon'     => $base_pokemon_list,
                'items'            => $items_list,
                'ajax_url'         => admin_url('admin-ajax.php'),
                'nonce'            => wp_create_nonce('pokehub_quests_ajax'),
                'rest_nonce'       => wp_create_nonce('wp_rest'),
                'rest_pokemon_url' => rest_url('poke-hub/v1/pokemon-for-select'),
            ]
        );
        wp_localize_script(
            'pokehub-admin-select2',
            'pokehubPokemonGenderConfig',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('pokehub_check_pokemon_gender_dimorphism_nonce'),
            ]
        );

        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_style('dashicons');

        wp_enqueue_script(
            'pokehub-go-pass-admin',
            POKE_HUB_URL . 'assets/js/pokehub-go-pass-admin.js',
            ['jquery', 'jquery-ui-sortable', 'select2', 'pokehub-admin-select2', 'pokehub-media-url'],
            POKE_HUB_VERSION,
            true
        );
        wp_localize_script(
            'pokehub-go-pass-admin',
            'pokehubGoPassL10n',
            [
                'addReward'      => __('Ajouter une récompense', 'poke-hub'),
                'removeReward'   => __('Supprimer cette récompense', 'poke-hub'),
                'rewardType'     => __('Type de récompense', 'poke-hub'),
                'pokemon'        => __('Pokémon', 'poke-hub'),
                'stardust'       => __('Poussière d’étoile', 'poke-hub'),
                'xp'             => __('XP', 'poke-hub'),
                'candy'          => __('Bonbon', 'poke-hub'),
                'xlCandy'        => __('Bonbon XL', 'poke-hub'),
                'megaEnergy'     => __('Énergie Mega', 'poke-hub'),
                'item'           => __('Objet', 'poke-hub'),
                'selectPokemon'  => __('Choisir un Pokémon', 'poke-hub'),
                'searchPokemon'  => __('Rechercher un Pokémon…', 'poke-hub'),
                'quantity'       => __('Quantité', 'poke-hub'),
                'oneCopy'        => __('un exemplaire', 'poke-hub'),
                'flagShiny'      => __('Shiny', 'poke-hub'),
                'flagShadow'     => __('Obscur', 'poke-hub'),
                'flagDynamax'    => __('Dynamax', 'poke-hub'),
                'flagGigamax'    => __('Gigamax', 'poke-hub'),
                'bonusOptions'   => function_exists('pokehub_get_all_bonuses_for_select')
                    ? array_map(
                        static function (array $b): array {
                            return [
                                'id'    => (int) ($b['id'] ?? 0),
                                'label' => (string) ($b['label'] ?? ''),
                            ];
                        },
                        pokehub_get_all_bonuses_for_select()
                    )
                    : [],
                'bonusNone'      => __('— Choisir un bonus —', 'poke-hub'),
                'bonusCol'       => __('Bonus', 'poke-hub'),
                'rewardBonus'    => __('Bonus', 'poke-hub'),
                'rewardBonusDesc' => __('Texte complémentaire sur le Pass (optionnel)', 'poke-hub'),
                'rankToPlaceholder' => __('Optionnel', 'poke-hub'),
                'featuredReward'    => __('Mettre en avant dans le résumé', 'poke-hub'),
                'dragReorder'       => __('Drag to reorder rows', 'poke-hub'),
            ]
        );
    }

    wp_localize_script('pokehub-special-events-admin', 'PokeHubSpecialEvents', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('pokehub_pokemon_attacks'),
    ]);
});

// Gérer l'option "événements par page" (Screen Options)
add_filter('set-screen-option', function ($status, $option, $value) {
    if ('pokehub_events_per_page' === $option) {
        return (int) $value;
    }
    return $status;
}, 10, 3);

// Initialisation des Screen Options pour la page Events
add_action('load-poke-hub_page_poke-hub-events', function () {
    $args = [
        'label'   => __('Events per page', 'poke-hub'),
        'default' => 20,
        'option'  => 'pokehub_events_per_page',
    ];
    add_screen_option('per_page', $args);
    
    // Activer les Screen Options pour masquer/afficher les colonnes
    // WordPress le fait automatiquement si la classe WP_List_Table est utilisée correctement
    // On s'assure juste que le screen est bien défini
    $screen = get_current_screen();
    if ($screen && class_exists('PokeHub_Events_List_Table')) {
        // Les colonnes seront automatiquement disponibles dans Screen Options
        // grâce à get_hidden_columns() dans la classe
    }
});

// Hook pour gérer les colonnes dans les Screen Options
add_filter('manage_poke-hub_page_poke-hub-events_columns', function($columns) {
    if (class_exists('PokeHub_Events_List_Table')) {
        $table = new PokeHub_Events_List_Table();
        return $table->get_columns();
    }
    return $columns;
});

