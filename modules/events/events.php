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

require_once __DIR__ . '/functions/events-admin-helpers.php';
require_once __DIR__ . '/admin/forms/events-admin-special-events-form.php';
require_once __DIR__ . '/admin/events-admin-special-events.php';
require_once __DIR__ . '/functions/events-helpers.php';
require_once __DIR__ . '/functions/events-queries.php';
require_once __DIR__ . '/functions/events-render.php';
require_once __DIR__ . '/public/shortcode-events.php';
require_once __DIR__ . '/public/events-front-routing.php';

/**
 * Assets front (optionnel pour l'instant, mais prêt à être utilisé).
 */
function poke_hub_events_assets() {
    // CSS principal + Select2
    wp_enqueue_style('pokehub-events-style', POKE_HUB_URL . 'assets/css/poke-hub-events-front.css', [], POKE_HUB_VERSION);
    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');

    // CSS pour les pages d'événements spéciaux individuels
    if (get_query_var('pokehub_special_event')) {
        wp_enqueue_style('pokehub-special-event-single', POKE_HUB_URL . 'assets/css/poke-hub-special-events-single.css', [], POKE_HUB_VERSION);
    }

    // JS Select2
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);

    // Init inline
    wp_add_inline_script('select2', "jQuery(function($){
        var \$s=$('#pokehub-event-type-select');
        if(!\$s.length)return;
        // Find the closest form field wrapper for dropdownParent
        var \$parent = \$s.closest('.me5rine-lab-form-field, .pokehub-event-type-filter-form');
        if (!\$parent.length) {
            \$parent = \$s.parent();
        }
        \$s.select2({
            placeholder:'" . esc_js(__('Select one or more types', 'poke-hub')) . "',
            allowClear:true,
            width:'resolve',
            dropdownParent: \$parent.length ? \$parent : $('body')
        });
        \$s.on('change',()=>\$s.closest('form').submit());
    });");
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

