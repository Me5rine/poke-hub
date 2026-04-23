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
if (!function_exists('pokehub_go_pass_host_link_get')) {
    $host_link = defined('POKE_HUB_PATH') ? POKE_HUB_PATH . 'modules/blocks/functions/blocks-go-pass-host-link.php' : '';
    if ($host_link && is_readable($host_link)) {
        require_once $host_link;
    }
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

$host_kinds = function_exists('pokehub_go_pass_host_kinds')
    ? pokehub_go_pass_host_kinds()
    : ['local_post', 'remote_post', 'special_event'];

$event_id = 0;
$variant  = 'summary';

if ($post_id > 0 && function_exists('pokehub_go_pass_host_link_get_for_post')) {
    $link = pokehub_go_pass_host_link_get_for_post($post_id);
    if ($link) {
        $event_id = (int) $link['special_event_id'];
        $variant  = ($link['display_mode'] === 'full') ? 'full' : 'summary';
    }
}

if ($event_id <= 0 && $post_id > 0 && function_exists('pokehub_go_pass_host_link_get')) {
    $default_host = ['kind' => 'local_post', 'id' => $post_id];
    $ctx          = apply_filters('pokehub_go_pass_host_from_context', $default_host, $block ?? null, $attributes, $post_id);
    if (is_array($ctx) && isset($ctx['kind'], $ctx['id']) && in_array((string) $ctx['kind'], $host_kinds, true)) {
        $cid = (int) $ctx['id'];
        if ($cid > 0) {
            $link = pokehub_go_pass_host_link_get((string) $ctx['kind'], $cid);
            if ($link) {
                $event_id = (int) $link['special_event_id'];
                $variant  = ($link['display_mode'] === 'full') ? 'full' : 'summary';
            }
        }
    }
}

if ($event_id <= 0) {
    $event_id = isset($attributes['specialEventId']) ? (int) $attributes['specialEventId'] : 0;
    $variant  = isset($attributes['displayMode']) ? sanitize_key((string) $attributes['displayMode']) : 'summary';
    if (!in_array($variant, ['summary', 'full'], true)) {
        $variant = 'summary';
    }
}

if (!function_exists('pokehub_go_pass_get_special_event_row_by_id')) {
    return '';
}

if ($event_id <= 0) {
    return '';
}

$event = pokehub_go_pass_get_special_event_row_by_id($event_id);
if (!$event || !function_exists('pokehub_is_go_pass_special_event') || !pokehub_is_go_pass_special_event($event)) {
    return '';
}

$block_title_html = function_exists('pokehub_render_block_title')
    ? pokehub_render_block_title(__('Pass GO', 'poke-hub'), 'go-pass')
    : '<h2 class="pokehub-block-title">' . esc_html__('Pass GO', 'poke-hub') . '</h2>';

if (function_exists('poke_hub_register_bundled_front_style') && !wp_style_is('pokehub-go-pass-block-front', 'registered')) {
    poke_hub_register_bundled_front_style(
        'pokehub-go-pass-block-front',
        'poke-hub-go-pass-block-front.css',
        ['pokehub-blocks-front-style'],
        defined('POKE_HUB_VERSION') ? POKE_HUB_VERSION : '1.0.0'
    );
}
if (wp_style_is('pokehub-go-pass-block-front', 'registered')) {
    wp_enqueue_style('pokehub-go-pass-block-front');
}

if ($variant === 'full') {
    if (function_exists('poke_hub_register_bundled_front_style') && !wp_style_is('pokehub-special-event-single', 'registered')) {
        poke_hub_register_bundled_front_style(
            'pokehub-special-event-single',
            'poke-hub-special-events-single.css',
            [],
            defined('POKE_HUB_VERSION') ? POKE_HUB_VERSION : '1.0.0'
        );
    }
    if (wp_style_is('pokehub-special-event-single', 'registered')) {
        wp_enqueue_style('pokehub-special-event-single');
    }
}

if ($variant === 'full' && function_exists('pokehub_render_go_pass_html')) {
    $full = pokehub_render_go_pass_html($event);
    if ($full !== '') {
        return '<div class="pokehub-go-pass-block-wrapper pokehub-go-pass-block pokehub-go-pass-block--full">' . $block_title_html . $full . '</div>';
    }
}

if (function_exists('pokehub_render_go_pass_summary_html')) {
    $summary = pokehub_render_go_pass_summary_html($event, null);
    if ($summary !== '') {
        return '<div class="pokehub-go-pass-block-wrapper pokehub-go-pass-block pokehub-go-pass-block--summary">' . $block_title_html . $summary . '</div>';
    }
}

return '';
