<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-poke-hub-shop-avatar-items-list-table.php';
require_once __DIR__ . '/class-poke-hub-shop-avatar-categories-list-table.php';

/**
 * Suppression unitaire (lien ligne ou URL directe).
 */
function poke_hub_shop_avatar_admin_handle_get_delete(): void {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    if (empty($_GET['page']) || empty($_GET['action']) || $_GET['action'] !== 'delete') {
        return;
    }
    $page = sanitize_key((string) wp_unslash($_GET['page']));
    if ($page !== POKE_HUB_SHOP_ITEMS_ADMIN_PAGE) {
        return;
    }
    $tab = poke_hub_shop_items_admin_tab();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0 || !function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    if ($tab === 'avatar') {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'poke_hub_shop_avatar_delete_item_' . $id)) {
            return;
        }
        if (!poke_hub_shop_avatar_db_tables_ready()) {
            return;
        }
        $wpdb->delete(pokehub_get_table('shop_avatar_item_events'), ['item_id' => $id], ['%d']);
        $wpdb->delete(pokehub_get_table('content_shop_avatar_entries'), ['shop_avatar_item_id' => $id], ['%d']);
        $wpdb->delete(pokehub_get_table('shop_avatar_items'), ['id' => $id], ['%d']);
        wp_safe_redirect(poke_hub_shop_items_admin_url('avatar', ['deleted' => '1']));
        exit;
    }

    if ($tab !== 'categories') {
        return;
    }

    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'poke_hub_shop_avatar_delete_category_' . $id)) {
        return;
    }
    if (!poke_hub_shop_avatar_db_tables_ready()) {
        return;
    }
    $wpdb->update(pokehub_get_table('shop_avatar_items'), ['category_id' => 0], ['category_id' => $id], ['%d'], ['%d']);
    $wpdb->delete(pokehub_get_table('shop_avatar_categories'), ['id' => $id], ['%d']);
    wp_safe_redirect(poke_hub_shop_items_admin_url('categories', ['deleted' => '1']));
    exit;
}
add_action('admin_init', 'poke_hub_shop_avatar_admin_handle_get_delete', 5);

/**
 * Sauvegarde formulaires catégorie / item.
 */
function poke_hub_shop_avatar_admin_handle_post(): void {
    if (empty($_POST['poke_hub_shop_avatar_admin']) || !current_user_can('manage_options')) {
        return;
    }
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'poke_hub_shop_avatar_admin')) {
        return;
    }
    if (!poke_hub_shop_avatar_db_tables_ready()) {
        return;
    }
    global $wpdb;

    $kind = isset($_POST['save_kind']) ? sanitize_key((string) wp_unslash($_POST['save_kind'])) : '';

    if ($kind === 'category') {
        $id   = isset($_POST['cat_id']) ? (int) $_POST['cat_id'] : 0;
        $tbl  = pokehub_get_table('shop_avatar_categories');
        $data = [
            'name_fr'    => isset($_POST['cat_name_fr']) ? sanitize_text_field(wp_unslash((string) $_POST['cat_name_fr'])) : '',
            'name_en'    => isset($_POST['cat_name_en']) ? sanitize_text_field(wp_unslash((string) $_POST['cat_name_en'])) : '',
            'sort_order' => isset($_POST['cat_sort']) ? (int) $_POST['cat_sort'] : 0,
        ];
        $slug = pokehub_slug_base_from_names($data['name_en'], $data['name_fr'], 'category');
        $slug = pokehub_unique_slug_for_table($tbl, $slug, $id > 0 ? $id : 0, 'slug', 'id', 'category');
        if ($id > 0) {
            $wpdb->update($tbl, array_merge($data, ['slug' => $slug]), ['id' => $id], ['%s', '%s', '%d', '%s'], ['%d']);
        } else {
            $wpdb->insert($tbl, array_merge($data, ['slug' => $slug]), ['%s', '%s', '%d', '%s']);
        }
        wp_safe_redirect(poke_hub_shop_items_admin_url('categories', ['updated' => '1']));
        exit;
    }

    if ($kind === 'item') {
        $id   = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
        $tbl  = pokehub_get_table('shop_avatar_items');
        $data = [
            'category_id' => isset($_POST['item_category_id']) ? (int) $_POST['item_category_id'] : 0,
            'name_fr'     => isset($_POST['item_name_fr']) ? sanitize_text_field(wp_unslash((string) $_POST['item_name_fr'])) : '',
            'name_en'     => isset($_POST['item_name_en']) ? sanitize_text_field(wp_unslash((string) $_POST['item_name_en'])) : '',
            'sort_order'  => isset($_POST['item_sort']) ? (int) $_POST['item_sort'] : 0,
        ];
        $slug = pokehub_slug_base_from_names($data['name_en'], $data['name_fr'], 'item');
        $slug = pokehub_unique_slug_for_table($tbl, $slug, $id > 0 ? $id : 0, 'slug', 'id', 'item');
        if ($id > 0) {
            $wpdb->update($tbl, array_merge($data, ['slug' => $slug]), ['id' => $id], ['%d', '%s', '%s', '%s', '%d'], ['%d']);
            $item_id = $id;
        } else {
            $wpdb->insert($tbl, array_merge($data, ['slug' => $slug]), ['%d', '%s', '%s', '%s', '%d']);
            $item_id = (int) $wpdb->insert_id;
        }
        $event_ids = isset($_POST['item_event_ids']) && is_array($_POST['item_event_ids'])
            ? array_map('intval', wp_unslash($_POST['item_event_ids']))
            : [];
        poke_hub_shop_avatar_save_item_events($item_id, $event_ids);

        wp_safe_redirect(poke_hub_shop_items_admin_url('avatar', ['updated' => '1']));
        exit;
    }
}
add_action('admin_init', 'poke_hub_shop_avatar_admin_handle_post');

function poke_hub_shop_avatar_admin_notices(): void {
    if (empty($_GET['page']) || sanitize_key((string) wp_unslash($_GET['page'])) !== POKE_HUB_SHOP_ITEMS_ADMIN_PAGE) {
        return;
    }
    $t = poke_hub_shop_items_admin_tab();
    if (!in_array($t, ['avatar', 'categories'], true)) {
        return;
    }
    if (!empty($_GET['updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Saved.', 'poke-hub') . '</p></div>';
    }
    if (!empty($_GET['deleted'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Deleted.', 'poke-hub') . '</p></div>';
    }
}

function poke_hub_shop_avatar_items_admin_ui(): void {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!poke_hub_shop_avatar_db_tables_ready()) {
        poke_hub_shop_items_admin_render_list_frame_start('avatar', null);
        echo '<p>' . esc_html__('Database tables are not ready. Save Poké HUB settings or re-enable the Shop items module.', 'poke-hub') . '</p>';
        poke_hub_shop_items_admin_render_list_frame_end();
        return;
    }

    $action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : '';
    if (in_array($action, ['add', 'edit'], true)) {
        poke_hub_shop_avatar_render_item_form($action);
        return;
    }

    $list_table = new Poke_Hub_Shop_Avatar_Items_List_Table();
    $list_table->process_bulk_action();
    $list_table->prepare_items();

    poke_hub_shop_items_admin_render_list_frame_start('avatar', [
        'label' => __('Add item', 'poke-hub'),
        'url'   => poke_hub_shop_items_admin_url('avatar', ['action' => 'add']),
    ]);

    poke_hub_shop_avatar_admin_notices();

    echo '<form method="post" id="shop-avatar-items-form">';
    echo '<input type="hidden" name="page" value="' . esc_attr(POKE_HUB_SHOP_ITEMS_ADMIN_PAGE) . '" />';
    echo '<input type="hidden" name="tab" value="avatar" />';
    wp_nonce_field('bulk-shop_avatar_items');
    $list_table->views();
    $list_table->search_box(__('Search items', 'poke-hub'), 'shop-avatar-item');
    $list_table->display();
    echo '</form>';
    poke_hub_shop_items_admin_render_list_frame_end();
}

function poke_hub_shop_avatar_categories_admin_ui(): void {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!poke_hub_shop_avatar_db_tables_ready()) {
        poke_hub_shop_items_admin_render_list_frame_start('categories', null);
        echo '<p>' . esc_html__('Database tables are not ready.', 'poke-hub') . '</p>';
        poke_hub_shop_items_admin_render_list_frame_end();
        return;
    }

    $action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : '';
    if (in_array($action, ['add', 'edit'], true)) {
        poke_hub_shop_avatar_render_category_form($action);
        return;
    }

    $list_table = new Poke_Hub_Shop_Avatar_Categories_List_Table();
    $list_table->process_bulk_action();
    $list_table->prepare_items();

    poke_hub_shop_items_admin_render_list_frame_start('categories', [
        'label' => __('Add category', 'poke-hub'),
        'url'   => poke_hub_shop_items_admin_url('categories', ['action' => 'add']),
    ]);

    poke_hub_shop_avatar_admin_notices();

    echo '<form method="post" id="shop-avatar-categories-form">';
    echo '<input type="hidden" name="page" value="' . esc_attr(POKE_HUB_SHOP_ITEMS_ADMIN_PAGE) . '" />';
    echo '<input type="hidden" name="tab" value="categories" />';
    wp_nonce_field('bulk-shop_avatar_categories');
    $list_table->search_box(__('Search categories', 'poke-hub'), 'shop-avatar-category');
    $list_table->display();
    echo '</form>';
    poke_hub_shop_items_admin_render_list_frame_end();
}

function poke_hub_shop_avatar_render_category_form(string $action): void {
    global $wpdb;
    $edit = null;
    if ($action === 'edit' && !empty($_GET['id'])) {
        $edit = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . pokehub_get_table('shop_avatar_categories') . ' WHERE id = %d',
            (int) $_GET['id']
        ));
    }

    poke_hub_shop_items_admin_render_list_frame_start('categories', null);
    echo '<h2>' . esc_html($action === 'add' ? __('Add category', 'poke-hub') : __('Edit category', 'poke-hub')) . '</h2>';
    poke_hub_shop_avatar_admin_notices();
    echo '<form method="post">';
    wp_nonce_field('poke_hub_shop_avatar_admin');
    echo '<input type="hidden" name="poke_hub_shop_avatar_admin" value="1" />';
    echo '<input type="hidden" name="save_kind" value="category" />';
    echo '<input type="hidden" name="cat_id" value="' . esc_attr($edit ? (string) (int) $edit->id : '0') . '" />';
    echo '<table class="form-table">';
    echo '<tr><th><label for="cat_name_fr">' . esc_html__('Name (FR)', 'poke-hub') . '</label></th><td><input class="regular-text" id="cat_name_fr" name="cat_name_fr" value="' . esc_attr($edit ? (string) $edit->name_fr : '') . '" /></td></tr>';
    echo '<tr><th><label for="cat_name_en">' . esc_html__('Name (EN)', 'poke-hub') . '</label></th><td><input class="regular-text" id="cat_name_en" name="cat_name_en" value="' . esc_attr($edit ? (string) $edit->name_en : '') . '" />';
    echo '<p class="description">' . esc_html__('The slug is generated from the English name (or French if English is empty), then made unique automatically.', 'poke-hub') . '</p></td></tr>';
    echo '<tr><th><label for="cat_sort">' . esc_html__('Sort order', 'poke-hub') . '</label></th><td><input type="number" id="cat_sort" name="cat_sort" value="' . esc_attr($edit ? (string) (int) $edit->sort_order : '0') . '" /></td></tr>';
    echo '</table>';
    submit_button(__('Save', 'poke-hub'));
    echo '</form>';
    echo '<p><a href="' . esc_url(poke_hub_shop_items_admin_url('categories')) . '">' . esc_html__('← Back to categories', 'poke-hub') . '</a></p>';
    poke_hub_shop_items_admin_render_list_frame_end();
}

function poke_hub_shop_avatar_render_item_form(string $action): void {
    global $wpdb;
    $edit = null;
    if ($action === 'edit' && !empty($_GET['id'])) {
        $edit = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . pokehub_get_table('shop_avatar_items') . ' WHERE id = %d',
            (int) $_GET['id']
        ));
    }
    $cats = $wpdb->get_results('SELECT id, name_fr, name_en, slug FROM ' . pokehub_get_table('shop_avatar_categories') . ' ORDER BY sort_order ASC, id ASC');
    $linked = $edit ? poke_hub_shop_avatar_get_item_event_ids((int) $edit->id) : [];

    $events_tbl = pokehub_get_table('special_events');
    $preloaded  = [];
    if ($linked && $events_tbl && function_exists('pokehub_table_exists') && pokehub_table_exists($events_tbl)) {
        $in = implode(',', array_fill(0, count($linked), '%d'));
        $preloaded = $wpdb->get_results($wpdb->prepare("SELECT id, slug, title, title_en, title_fr FROM {$events_tbl} WHERE id IN ({$in})", ...$linked));
    }

    poke_hub_shop_items_admin_render_list_frame_start('avatar', null);
    echo '<h2>' . esc_html($action === 'add' ? __('Add shop item', 'poke-hub') : __('Edit shop item', 'poke-hub')) . '</h2>';
    poke_hub_shop_avatar_admin_notices();
    echo '<form method="post" id="pokehub-shop-avatar-item-form">';
    wp_nonce_field('poke_hub_shop_avatar_admin');
    echo '<input type="hidden" name="poke_hub_shop_avatar_admin" value="1" />';
    echo '<input type="hidden" name="save_kind" value="item" />';
    echo '<input type="hidden" name="item_id" value="' . esc_attr($edit ? (string) (int) $edit->id : '0') . '" />';
    echo '<table class="form-table">';
    echo '<tr><th><label for="item_category_id">' . esc_html__('Category', 'poke-hub') . '</label></th><td><select name="item_category_id" id="item_category_id">';
    echo '<option value="0">' . esc_html__('— None —', 'poke-hub') . '</option>';
    foreach ((array) $cats as $c) {
        $lab = trim((string) $c->name_fr) !== '' ? (string) $c->name_fr : (string) $c->name_en;
        if ($lab === '') {
            $lab = (string) $c->slug;
        }
        $sel = $edit && (int) $edit->category_id === (int) $c->id ? ' selected' : '';
        echo '<option value="' . esc_attr((string) (int) $c->id) . '"' . $sel . '>' . esc_html($lab) . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><th><label for="item_name_fr">' . esc_html__('Name (FR)', 'poke-hub') . '</label></th><td><input class="regular-text" id="item_name_fr" name="item_name_fr" value="' . esc_attr($edit ? (string) $edit->name_fr : '') . '" /></td></tr>';
    echo '<tr><th><label for="item_name_en">' . esc_html__('Name (EN)', 'poke-hub') . '</label></th><td><input class="regular-text" id="item_name_en" name="item_name_en" value="' . esc_attr($edit ? (string) $edit->name_en : '') . '" />';
    echo '<p class="description">' . esc_html__('The slug (bucket files slug.webp / slug.png in the Avatar shop folder from Sources) is generated from the English name (or French if English is empty), then made unique automatically.', 'poke-hub') . '</p></td></tr>';
    echo '<tr><th><label for="item_sort">' . esc_html__('Sort order', 'poke-hub') . '</label></th><td><input type="number" id="item_sort" name="item_sort" value="' . esc_attr($edit ? (string) (int) $edit->sort_order : '0') . '" /></td></tr>';
    echo '<tr><th><label for="pokehub_shop_item_events">' . esc_html__('Linked events', 'poke-hub') . '</label></th><td>';
    echo '<select name="item_event_ids[]" id="pokehub_shop_item_events" class="pokehub-shop-avatar-events-select" multiple="multiple" style="width:100%;max-width:640px" data-placeholder="' . esc_attr__('Search events by name…', 'poke-hub') . '">';
    foreach ((array) $preloaded as $ev) {
        $eid = (int) $ev->id;
        echo '<option value="' . esc_attr((string) $eid) . '" selected>' . esc_html(poke_hub_shop_avatar_special_event_label($ev)) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__('Type to search special events, then select one or more.', 'poke-hub') . '</p>';
    echo '</td></tr>';
    echo '</table>';
    submit_button(__('Save', 'poke-hub'));
    echo '</form>';
    echo '<p><a href="' . esc_url(poke_hub_shop_items_admin_url('avatar')) . '">' . esc_html__('← Back to items', 'poke-hub') . '</a></p>';
    poke_hub_shop_items_admin_render_list_frame_end();
}

/**
 * Options d’écran (pagination).
 */
function poke_hub_shop_avatar_items_screen_options(): void {
    add_screen_option('per_page', [
        'label'   => __('Items per page', 'poke-hub'),
        'default' => 20,
        'option'  => 'poke_hub_shop_avatar_items_per_page',
    ]);
}

function poke_hub_shop_avatar_categories_screen_options(): void {
    add_screen_option('per_page', [
        'label'   => __('Categories per page', 'poke-hub'),
        'default' => 20,
        'option'  => 'poke_hub_shop_avatar_categories_per_page',
    ]);
}

/**
 * Pagination (WP 5.5+).
 */
function poke_hub_shop_avatar_set_items_per_page($value, string $option, int $user_id) {
    unset($option, $user_id);
    return (int) $value;
}
add_filter('set_screen_option_poke_hub_shop_avatar_items_per_page', 'poke_hub_shop_avatar_set_items_per_page', 10, 3);

function poke_hub_shop_avatar_set_categories_per_page($value, string $option, int $user_id) {
    unset($option, $user_id);
    return (int) $value;
}
add_filter('set_screen_option_poke_hub_shop_avatar_categories_per_page', 'poke_hub_shop_avatar_set_categories_per_page', 10, 3);

/**
 * Pagination (anciennes versions de WordPress).
 *
 * @param mixed $status
 */
function poke_hub_shop_avatar_set_screen_option_legacy($status, string $option, $value) {
    if ($option === 'poke_hub_shop_avatar_items_per_page' || $option === 'poke_hub_shop_avatar_categories_per_page') {
        return (int) $value;
    }
    return $status;
}
add_filter('set-screen-option', 'poke_hub_shop_avatar_set_screen_option_legacy', 10, 3);

/**
 * Select2 + recherche AJAX des événements (formulaire item).
 */
function poke_hub_shop_avatar_item_form_assets(string $hook): void {
    if ($hook !== 'poke-hub_page_' . POKE_HUB_SHOP_ITEMS_ADMIN_PAGE) {
        return;
    }
    if (poke_hub_shop_items_admin_tab() !== 'avatar') {
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
        'pokehub-shop-avatar-item-form-events',
        POKE_HUB_SHOP_ITEMS_URL . 'admin/js/shop-avatar-item-form-events.js',
        ['jquery', 'select2'],
        defined('POKE_HUB_VERSION') ? POKE_HUB_VERSION : '1.0',
        true
    );
    wp_localize_script(
        'pokehub-shop-avatar-item-form-events',
        'pokehubShopAvatarItemForm',
        [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('pokehub_shop_avatar_item_form_ajax'),
            'placeholder' => __('Search events by name…', 'poke-hub'),
        ]
    );
}
add_action('admin_enqueue_scripts', 'poke_hub_shop_avatar_item_form_assets', 20);
