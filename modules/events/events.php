<?php
// modules/events/events.php

if (!defined('ABSPATH')) {
    exit;
}

if (!poke_hub_is_module_active('events')) {
    return;
}

define('POKE_HUB_EVENTS_PATH', __DIR__);
define('poke_hub_EVENTS_URL', POKE_HUB_URL . 'modules/events/');

require_once __DIR__ . '/functions/events-admin-helpers.php';
require_once __DIR__ . '/admin/forms/events-admin-special-events-form.php';
require_once __DIR__ . '/admin/events-admin-special-events.php';
require_once __DIR__ . '/functions/events-helpers.php';
require_once __DIR__ . '/functions/events-queries.php';
require_once __DIR__ . '/functions/events-render.php';
require_once __DIR__ . '/public/shortcode-events.php';

/**
 * Assets front (optionnel pour l'instant, mais prêt à être utilisé).
 */
function poke_hub_events_assets() {
    // CSS principal + Select2
    wp_enqueue_style('pokehub-events-style', POKE_HUB_URL . 'assets/css/poke-hub-events-front.css', [], POKE_HUB_VERSION);
    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');

    // JS Select2
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);

    // Init inline
    wp_add_inline_script('select2', "jQuery(function($){
        var \$s=$('#pokehub-event-type-select');
        if(!\$s.length)return;
        \$s.select2({placeholder:'" . esc_js(__('Select one or more types', 'poke-hub')) . "',allowClear:true,width:'resolve'});
        \$s.on('change',()=>\$s.closest('form').submit());
    });");
}
add_action('wp_enqueue_scripts', 'poke_hub_events_assets');

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'poke-hub_page_poke-hub-events') {
        return;
    }

    // Nécessaire pour wp.media
    wp_enqueue_media();

    // CSS admin dédié aux Events
    wp_enqueue_style(
        'pokehub-events-admin-css',
        POKE_HUB_URL . 'assets/css/poke-hub-events-admin.css',
        [],
        POKE_HUB_VERSION
    );

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

    // Ton script existant pour la gestion du formulaire (Pokémon, bonus, etc.)
    wp_enqueue_script(
        'pokehub-special-events-admin',
        POKE_HUB_URL . 'assets/js/special-events-admin.js',
        ['jquery', 'pokehub-media-url'],
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
});

