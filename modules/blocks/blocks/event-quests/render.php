<?php
// modules/blocks/blocks/event-quests/render.php

/**
 * Rendu du bloc Field Research (quêtes du post courant ou ID explicite).
 *
 * @var array         $attributes
 * @var string        $content
 * @var WP_Block|null $block
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = 0;

// ID explicite (éditeur / JSON du bloc), prioritaire s’il est valide
if (!empty($attributes['eventPostId'])) {
    $post_id = (int) $attributes['eventPostId'];
}

// Sinon : même logique que wild-pokemon / autoDetect (post courant)
if ($post_id <= 0) {
    if (isset($block) && is_object($block) && !empty($block->context['postId'])) {
        $post_id = (int) $block->context['postId'];
    }
}

if ($post_id <= 0) {
    $post_id = (int) get_the_ID();
}

if ($post_id <= 0) {
    $post_id = (int) get_queried_object_id();
}

if ($post_id <= 0 && !empty($GLOBALS['post']->ID)) {
    $post_id = (int) $GLOBALS['post']->ID;
}

if ($post_id <= 0) {
    if (current_user_can('manage_options')) {
        echo '<p><strong>Poke Hub:</strong> aucune étude sur le terrain sélectionnée (impossible de déterminer l’article / l’événement).</p>';
    }
    return;
}

$quests = pokehub_blocks_get_event_quests($post_id);
if (empty($quests)) {
    if (current_user_can('manage_options')) {
        echo '<p><strong>Poke Hub:</strong> aucune étude sur le terrain trouvée pour cet événement (ID ' . esc_html((string) $post_id) . ').</p>';
    }
    return;
}

if (!function_exists('pokehub_blocks_render_event_quests')) {
    if (current_user_can('manage_options')) {
        echo '<p><strong>Poke Hub:</strong> module de rendu des quêtes indisponible.</p>';
    }
    return;
}

$quests_html = pokehub_blocks_render_event_quests($quests);
if ($quests_html === '') {
    return '';
}

$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-event-quests-block-wrapper']);

return '<div ' . $wrapper_attributes . '>'
    . (function_exists('pokehub_render_block_title')
        ? pokehub_render_block_title(__('Field Research', 'poke-hub'), 'event-quests')
        : '<h2 class="pokehub-block-title">' . esc_html__('Field Research', 'poke-hub') . '</h2>')
    . $quests_html
    . '</div>';
