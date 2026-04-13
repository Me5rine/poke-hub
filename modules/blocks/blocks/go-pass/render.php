<?php
/**
 * Bloc Pass GO — rendu serveur (module Blocs).
 * La logique métier (JSON, grille) reste dans modules/events/functions/events-go-pass-helpers.php.
 *
 * @var array         $attributes Attributs du bloc.
 * @var string        $content    Contenu interne (vide).
 * @var WP_Block|null $block      Instance du bloc.
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pokehub_go_pass_get_special_event_row_by_id')) {
    $go_helpers = defined('POKE_HUB_PATH') ? POKE_HUB_PATH . 'modules/events/functions/events-go-pass-helpers.php' : '';
    if ($go_helpers && is_readable($go_helpers)) {
        require_once $go_helpers;
    }
}

$event_id = isset($attributes['specialEventId']) ? (int) $attributes['specialEventId'] : 0;
$variant  = isset($attributes['displayMode']) ? sanitize_key((string) $attributes['displayMode']) : 'summary';
if (!in_array($variant, ['summary', 'full'], true)) {
    $variant = 'summary';
}

$post_id = 0;
if (isset($block) && is_object($block) && !empty($block->context['postId'])) {
    $post_id = (int) $block->context['postId'];
}
if (!$post_id) {
    $post_id = (int) get_the_ID();
}
if (!$post_id && !empty($GLOBALS['post']->ID)) {
    $post_id = (int) $GLOBALS['post']->ID;
}

if ($event_id <= 0 && $post_id > 0) {
    $meta_eid = (int) get_post_meta($post_id, '_pokehub_go_pass_special_event_id', true);
    if ($meta_eid > 0) {
        $event_id = $meta_eid;
    }
}

if (!function_exists('pokehub_go_pass_get_special_event_row_by_id')) {
    return '';
}

if ($event_id <= 0) {
    $msg = __('Select a GO Pass in the block settings or in the “GO Pass (block)” meta box.', 'poke-hub');
    return '<div class="pokehub-go-pass-block pokehub-go-pass-block--empty"><p>' . esc_html($msg) . '</p></div>';
}

$event = pokehub_go_pass_get_special_event_row_by_id($event_id);
if (!$event || !function_exists('pokehub_is_go_pass_special_event') || !pokehub_is_go_pass_special_event($event)) {
    return '';
}

if (!wp_style_is('pokehub-go-pass-block-front', 'registered')) {
    wp_register_style(
        'pokehub-go-pass-block-front',
        POKE_HUB_URL . 'assets/css/poke-hub-go-pass-block-front.css',
        ['pokehub-blocks-front-style'],
        defined('POKE_HUB_VERSION') ? POKE_HUB_VERSION : '1.0.0'
    );
}
wp_enqueue_style('pokehub-go-pass-block-front');

if ($variant === 'full') {
    if (!wp_style_is('pokehub-special-event-single', 'registered')) {
        wp_register_style(
            'pokehub-special-event-single',
            POKE_HUB_URL . 'assets/css/poke-hub-special-events-single.css',
            [],
            defined('POKE_HUB_VERSION') ? POKE_HUB_VERSION : '1.0.0'
        );
    }
    wp_enqueue_style('pokehub-special-event-single');
}

if ($variant === 'full' && function_exists('pokehub_render_go_pass_html')) {
    $full = pokehub_render_go_pass_html($event);
    if ($full !== '') {
        return '<div class="pokehub-go-pass-block pokehub-go-pass-block--full">' . $full . '</div>';
    }
}

if (function_exists('pokehub_render_go_pass_summary_html')) {
    return '<div class="pokehub-go-pass-block pokehub-go-pass-block--summary">' .
        pokehub_render_go_pass_summary_html($event, null) .
        '</div>';
}

return '';
