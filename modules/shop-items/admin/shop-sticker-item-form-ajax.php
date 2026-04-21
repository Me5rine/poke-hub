<?php
/**
 * AJAX formulaire admin stickers (liaisons événements) — module shop-items.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('POKEHUB_SHOP_STICKER_ITEM_FORM_AJAX_LOADED')) {
    return;
}
define('POKEHUB_SHOP_STICKER_ITEM_FORM_AJAX_LOADED', true);

add_action('wp_ajax_pokehub_shop_sticker_special_events_search', function (): void {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'pokehub_shop_sticker_item_form_ajax')) {
        wp_send_json_error(['message' => __('Invalid nonce.', 'poke-hub')], 403);
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Forbidden.', 'poke-hub')], 403);
    }
    $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash((string) $_POST['q'])) : '';
    if (strlen($q) < 2) {
        wp_send_json_success(['results' => []]);
    }
    if (!function_exists('pokehub_get_table')) {
        wp_send_json_error(['message' => __('Database helpers are not available.', 'poke-hub')], 500);
    }
    $tbl = pokehub_get_table('special_events');
    if ($tbl === '' || !function_exists('pokehub_table_exists') || !pokehub_table_exists($tbl)) {
        wp_send_json_success(['results' => []]);
    }
    global $wpdb;
    $like = '%' . $wpdb->esc_like($q) . '%';
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, slug, title, title_en, title_fr FROM {$tbl}
             WHERE title LIKE %s OR title_en LIKE %s OR title_fr LIKE %s OR slug LIKE %s
             ORDER BY start_ts DESC
             LIMIT 40",
            $like,
            $like,
            $like,
            $like
        )
    );
    $out = [];
    $label_fn = function_exists('poke_hub_shop_avatar_special_event_label') ? 'poke_hub_shop_avatar_special_event_label' : null;
    foreach ((array) $rows as $row) {
        $out[] = [
            'id'   => (int) $row->id,
            'text' => $label_fn ? $label_fn($row) : (string) $row->id,
        ];
    }
    wp_send_json_success(['results' => $out]);
});
