<?php
/**
 * AJAX métabox article / événement — boutique avatar (module Blocks uniquement).
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('POKEHUB_BLOCKS_SHOP_AVATAR_METABOX_AJAX_LOADED')) {
    return;
}
define('POKEHUB_BLOCKS_SHOP_AVATAR_METABOX_AJAX_LOADED', true);

add_action('wp_ajax_pokehub_shop_avatar_items_search', function (): void {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'pokehub_shop_avatar_metabox_ajax')) {
        wp_send_json_error(['message' => __('Invalid nonce.', 'poke-hub')], 403);
    }
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Forbidden.', 'poke-hub')], 403);
    }
    $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash((string) $_POST['q'])) : '';
    if (!function_exists('poke_hub_shop_avatar_search_items')) {
        wp_send_json_error(['message' => __('Shop avatar helpers are not available.', 'poke-hub')], 500);
    }
    $results = poke_hub_shop_avatar_search_items($q, 40);
    $out = [];
    foreach ($results as $r) {
        $out[] = ['id' => (int) $r['id'], 'text' => (string) $r['text']];
    }
    wp_send_json_success(['results' => $out]);
});

add_action('wp_ajax_pokehub_shop_avatar_metabox_create_item', function (): void {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'pokehub_shop_avatar_metabox_ajax')) {
        wp_send_json_error(['message' => __('Invalid nonce.', 'poke-hub')], 403);
    }
    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if ($post_id <= 0 || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => __('Forbidden.', 'poke-hub')], 403);
    }
    $name_en = isset($_POST['name_en']) ? sanitize_text_field(wp_unslash((string) $_POST['name_en'])) : '';
    if ($name_en === '') {
        wp_send_json_error(['message' => __('English name is required.', 'poke-hub')], 400);
    }
    $name_fr = isset($_POST['name_fr']) ? sanitize_text_field(wp_unslash((string) $_POST['name_fr'])) : '';
    if (!function_exists('poke_hub_shop_avatar_create_item')) {
        wp_send_json_error(['message' => __('Shop avatar helpers are not available.', 'poke-hub')], 500);
    }
    $result = poke_hub_shop_avatar_create_item(['name_en' => $name_en, 'name_fr' => $name_fr]);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 400);
    }
    $id = (int) $result;
    $row = function_exists('poke_hub_shop_avatar_get_item_by_id') ? poke_hub_shop_avatar_get_item_by_id($id) : null;
    $text = $row && !empty($row->name_en) ? (string) $row->name_en : ('#' . $id);
    wp_send_json_success(['id' => $id, 'text' => $text]);
});
