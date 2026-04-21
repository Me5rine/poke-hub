<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-poke-hub-shop-sticker-items-list-table.php';

function poke_hub_shop_sticker_admin_handle_get_delete(): void {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    if (empty($_GET['page']) || sanitize_key((string) wp_unslash($_GET['page'])) !== POKE_HUB_SHOP_ITEMS_ADMIN_PAGE
        || poke_hub_shop_items_admin_tab() !== 'stickers'
        || empty($_GET['action']) || $_GET['action'] !== 'delete') {
        return;
    }
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0 || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'poke_hub_shop_sticker_delete_item_' . $id)) {
        return;
    }
    if (!function_exists('pokehub_get_table') || !poke_hub_shop_sticker_db_tables_ready()) {
        return;
    }
    global $wpdb;
    $wpdb->delete(pokehub_get_table('shop_sticker_item_events'), ['item_id' => $id], ['%d']);
    $wpdb->delete(pokehub_get_table('content_shop_sticker_entries'), ['shop_sticker_item_id' => $id], ['%d']);
    $wpdb->delete(pokehub_get_table('shop_sticker_items'), ['id' => $id], ['%d']);
    wp_safe_redirect(poke_hub_shop_items_admin_url('stickers', ['deleted' => '1']));
    exit;
}
add_action('admin_init', 'poke_hub_shop_sticker_admin_handle_get_delete', 5);

function poke_hub_shop_sticker_admin_handle_post(): void {
    if (empty($_POST['poke_hub_shop_sticker_admin']) || !current_user_can('manage_options')) {
        return;
    }
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'poke_hub_shop_sticker_admin')) {
        return;
    }
    if (!poke_hub_shop_sticker_db_tables_ready()) {
        return;
    }
    global $wpdb;

    $kind = isset($_POST['save_kind']) ? sanitize_key((string) wp_unslash($_POST['save_kind'])) : '';
    if ($kind !== 'item') {
        return;
    }

    $id   = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
    $tbl  = pokehub_get_table('shop_sticker_items');
    $data = [
        'name_fr'    => isset($_POST['item_name_fr']) ? sanitize_text_field(wp_unslash((string) $_POST['item_name_fr'])) : '',
        'name_en'    => isset($_POST['item_name_en']) ? sanitize_text_field(wp_unslash((string) $_POST['item_name_en'])) : '',
        'sort_order' => isset($_POST['item_sort']) ? (int) $_POST['item_sort'] : 0,
    ];
    $slug = pokehub_slug_base_from_names($data['name_en'], $data['name_fr'], 'sticker');
    $slug = pokehub_unique_slug_for_table($tbl, $slug, $id > 0 ? $id : 0, 'slug', 'id', 'sticker');
    if ($id > 0) {
        $wpdb->update($tbl, array_merge($data, ['slug' => $slug]), ['id' => $id], ['%s', '%s', '%d', '%s'], ['%d']);
        $item_id = $id;
    } else {
        $wpdb->insert($tbl, array_merge($data, ['slug' => $slug]), ['%s', '%s', '%s', '%d']);
        $item_id = (int) $wpdb->insert_id;
    }
    $event_ids = isset($_POST['item_event_ids']) && is_array($_POST['item_event_ids'])
        ? array_map('intval', wp_unslash($_POST['item_event_ids']))
        : [];
    poke_hub_shop_sticker_save_item_events($item_id, $event_ids);

    wp_safe_redirect(poke_hub_shop_items_admin_url('stickers', ['updated' => '1']));
    exit;
}
add_action('admin_init', 'poke_hub_shop_sticker_admin_handle_post');

function poke_hub_shop_sticker_admin_notices(): void {
    if (empty($_GET['page']) || sanitize_key((string) wp_unslash($_GET['page'])) !== POKE_HUB_SHOP_ITEMS_ADMIN_PAGE) {
        return;
    }
    if (poke_hub_shop_items_admin_tab() !== 'stickers') {
        return;
    }
    if (!empty($_GET['updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Saved.', 'poke-hub') . '</p></div>';
    }
    if (!empty($_GET['deleted'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Deleted.', 'poke-hub') . '</p></div>';
    }
}

function poke_hub_shop_sticker_items_admin_ui(): void {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!poke_hub_shop_sticker_db_tables_ready()) {
        poke_hub_shop_items_admin_render_list_frame_start('stickers', null);
        echo '<p>' . esc_html__('Database tables are not ready. Save Poké HUB settings or re-enable the Shop items module.', 'poke-hub') . '</p>';
        poke_hub_shop_items_admin_render_list_frame_end();
        return;
    }

    $action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : '';
    if (in_array($action, ['add', 'edit'], true)) {
        poke_hub_shop_sticker_render_item_form($action);
        return;
    }

    $list_table = new Poke_Hub_Shop_Sticker_Items_List_Table();
    $list_table->process_bulk_action();
    $list_table->prepare_items();

    poke_hub_shop_items_admin_render_list_frame_start('stickers', [
        'label' => __('Add sticker', 'poke-hub'),
        'url'   => poke_hub_shop_items_admin_url('stickers', ['action' => 'add']),
    ]);

    poke_hub_shop_sticker_admin_notices();

    echo '<form method="post" id="shop-sticker-items-form">';
    echo '<input type="hidden" name="page" value="' . esc_attr(POKE_HUB_SHOP_ITEMS_ADMIN_PAGE) . '" />';
    echo '<input type="hidden" name="tab" value="stickers" />';
    wp_nonce_field('bulk-shop_sticker_items');
    $list_table->search_box(__('Search stickers', 'poke-hub'), 'shop-sticker-item');
    $list_table->display();
    echo '</form>';
    poke_hub_shop_items_admin_render_list_frame_end();
}

function poke_hub_shop_sticker_render_item_form(string $action): void {
    global $wpdb;
    $edit = null;
    if ($action === 'edit' && !empty($_GET['id'])) {
        $edit = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . pokehub_get_table('shop_sticker_items') . ' WHERE id = %d',
            (int) $_GET['id']
        ));
    }
    $linked = $edit ? poke_hub_shop_sticker_get_item_event_ids((int) $edit->id) : [];

    $events_tbl = pokehub_get_table('special_events');
    $preloaded  = [];
    if ($linked && $events_tbl && function_exists('pokehub_table_exists') && pokehub_table_exists($events_tbl)) {
        $in = implode(',', array_fill(0, count($linked), '%d'));
        $preloaded = $wpdb->get_results($wpdb->prepare("SELECT id, slug, title, title_en, title_fr FROM {$events_tbl} WHERE id IN ({$in})", ...$linked));
    }

    $event_label_fn = function_exists('poke_hub_shop_avatar_special_event_label') ? 'poke_hub_shop_avatar_special_event_label' : null;

    poke_hub_shop_items_admin_render_list_frame_start('stickers', null);
    echo '<h2>' . esc_html($action === 'add' ? __('Add sticker', 'poke-hub') : __('Edit sticker', 'poke-hub')) . '</h2>';
    poke_hub_shop_sticker_admin_notices();
    echo '<form method="post" id="pokehub-shop-sticker-item-form">';
    wp_nonce_field('poke_hub_shop_sticker_admin');
    echo '<input type="hidden" name="poke_hub_shop_sticker_admin" value="1" />';
    echo '<input type="hidden" name="save_kind" value="item" />';
    echo '<input type="hidden" name="item_id" value="' . esc_attr($edit ? (string) (int) $edit->id : '0') . '" />';
    echo '<table class="form-table">';
    echo '<tr><th><label for="sticker_item_name_fr">' . esc_html__('Name (FR)', 'poke-hub') . '</label></th><td><input class="regular-text" id="sticker_item_name_fr" name="item_name_fr" value="' . esc_attr($edit ? (string) $edit->name_fr : '') . '" /></td></tr>';
    echo '<tr><th><label for="sticker_item_name_en">' . esc_html__('Name (EN)', 'poke-hub') . '</label></th><td><input class="regular-text" id="sticker_item_name_en" name="item_name_en" value="' . esc_attr($edit ? (string) $edit->name_en : '') . '" />';
    echo '<p class="description">' . esc_html__('The slug (bucket files slug.webp / slug.png in the In-game stickers folder from Sources) is generated from the English name (or French if English is empty), then made unique automatically.', 'poke-hub') . '</p></td></tr>';
    echo '<tr><th><label for="sticker_item_sort">' . esc_html__('Sort order', 'poke-hub') . '</label></th><td><input type="number" id="sticker_item_sort" name="item_sort" value="' . esc_attr($edit ? (string) (int) $edit->sort_order : '0') . '" /></td></tr>';
    echo '<tr><th><label for="pokehub_shop_sticker_item_events">' . esc_html__('Linked events', 'poke-hub') . '</label></th><td>';
    echo '<select name="item_event_ids[]" id="pokehub_shop_sticker_item_events" class="pokehub-shop-sticker-events-select" multiple="multiple" style="width:100%;max-width:640px" data-placeholder="' . esc_attr__('Search events by name…', 'poke-hub') . '">';
    foreach ((array) $preloaded as $ev) {
        $eid = (int) $ev->id;
        $lab = $event_label_fn ? $event_label_fn($ev) : ('#' . $eid);
        echo '<option value="' . esc_attr((string) $eid) . '" selected>' . esc_html($lab) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__('Type to search special events, then select one or more.', 'poke-hub') . '</p>';
    echo '</td></tr>';
    echo '</table>';
    submit_button(__('Save', 'poke-hub'));
    echo '</form>';
    echo '<p><a href="' . esc_url(poke_hub_shop_items_admin_url('stickers')) . '">' . esc_html__('← Back to stickers', 'poke-hub') . '</a></p>';
    poke_hub_shop_items_admin_render_list_frame_end();
}

function poke_hub_shop_sticker_items_screen_options(): void {
    add_screen_option('per_page', [
        'label'   => __('Items per page', 'poke-hub'),
        'default' => 20,
        'option'  => 'poke_hub_shop_sticker_items_per_page',
    ]);
}

function poke_hub_shop_sticker_set_items_per_page($value, string $option, int $user_id) {
    unset($option, $user_id);
    return (int) $value;
}
add_filter('set_screen_option_poke_hub_shop_sticker_items_per_page', 'poke_hub_shop_sticker_set_items_per_page', 10, 3);

/**
 * @param mixed $status
 */
function poke_hub_shop_sticker_set_screen_option_legacy($status, string $option, $value) {
    if ($option === 'poke_hub_shop_sticker_items_per_page') {
        return (int) $value;
    }
    return $status;
}
add_filter('set-screen-option', 'poke_hub_shop_sticker_set_screen_option_legacy', 10, 3);

function poke_hub_shop_sticker_item_form_assets(string $hook): void {
    if ($hook !== 'poke-hub_page_' . POKE_HUB_SHOP_ITEMS_ADMIN_PAGE) {
        return;
    }
    if (poke_hub_shop_items_admin_tab() !== 'stickers') {
        return;
    }
    $action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : '';
    if (!in_array($action, ['add', 'edit'], true)) {
        return;
    }

    wp_enqueue_style(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
        [],
        '4.1.0'
    );
    wp_enqueue_script(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        ['jquery'],
        '4.1.0',
        true
    );
    wp_enqueue_script(
        'pokehub-shop-sticker-item-form-events',
        POKE_HUB_SHOP_ITEMS_URL . 'admin/js/shop-sticker-item-form-events.js',
        ['jquery', 'select2'],
        defined('POKE_HUB_VERSION') ? POKE_HUB_VERSION : '1.0',
        true
    );
    wp_localize_script(
        'pokehub-shop-sticker-item-form-events',
        'pokehubShopStickerItemForm',
        [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('pokehub_shop_sticker_item_form_ajax'),
            'placeholder' => __('Search events by name…', 'poke-hub'),
        ]
    );
}
add_action('admin_enqueue_scripts', 'poke_hub_shop_sticker_item_form_assets', 20);
