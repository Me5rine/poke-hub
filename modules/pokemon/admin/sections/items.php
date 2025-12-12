<?php
// modules/pokemon/admin/sections/items.php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// Formulaire add/edit
require_once POKE_HUB_POKEMON_PATH . '/admin/forms/item-form.php';

class Poke_Hub_Pokemon_Items_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'pokemon_item',
            'plural'   => 'pokemon_items',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'        => '<input type="checkbox" />',
            'name'      => __('Name', 'poke-hub'),
            'slug'      => __('Slug', 'poke-hub'),
            'image_url' => __('Image', 'poke-hub'),
        ];
    }

    public function get_sortable_columns() {
        return [
            'name' => ['name_fr', true],
            'slug' => ['slug', true],
        ];
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="ids[]" value="%d" />',
            (int) $item->id
        );
    }

    public function column_name($item) {
        $edit_url = add_query_arg(
            [
                'page'       => 'poke-hub-pokemon',
                'ph_section' => 'items',
                'action'     => 'edit',
                'id'         => (int) $item->id,
            ],
            admin_url('admin.php')
        );

        $delete_url = wp_nonce_url(
            add_query_arg(
                [
                    'page'       => 'poke-hub-pokemon',
                    'ph_section' => 'items',
                    'action'     => 'delete',
                    'id'         => (int) $item->id,
                ],
                admin_url('admin.php')
            ),
            'poke_hub_delete_item_' . (int) $item->id
        );

        $name_fr = isset($item->name_fr) ? (string) $item->name_fr : '';
        $name_en = isset($item->name_en) ? (string) $item->name_en : '';
        $display_name = $name_fr !== '' ? $name_fr : $name_en;

        $title = esc_html($display_name);

        $actions = [
            'edit'   => sprintf('<a href="%s">%s</a>', esc_url($edit_url), esc_html__('Edit', 'poke-hub')),
            'delete' => sprintf(
                '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($delete_url),
                esc_attr__('Are you sure you want to delete this item?', 'poke-hub'),
                esc_html__('Delete', 'poke-hub')
            ),
        ];

        return sprintf(
            '<strong><a href="%1$s">%2$s</a></strong> %3$s',
            esc_url($edit_url),
            $title,
            $this->row_actions($actions)
        );
    }

    public function column_slug($item) {
        return '<code>' . esc_html($item->slug) . '</code>';
    }

    public function column_image_url($item) {
        $extra = [];
        if (!empty($item->extra)) {
            $decoded = json_decode($item->extra, true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }
        $url = $extra['image_url'] ?? '';

        if (!$url) {
            return '&mdash;';
        }

        return sprintf(
            '<img src="%1$s" alt="" style="width:32px;height:32px;object-fit:contain;" />',
            esc_url($url)
        );
    }

    public function column_default($item, $column_name) {
        return '';
    }

    public function get_bulk_actions() {
        return [
            'bulk_delete' => __('Delete', 'poke-hub'),
        ];
    }

    public function process_bulk_action() {
        if (!function_exists('pokehub_get_table')) {
            return;
        }

        if ('bulk_delete' !== $this->current_action()) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('bulk-pokemon_items');

        if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
            return;
        }

        global $wpdb;
        $table = pokehub_get_table('pokemon_items');

        $ids = array_map('intval', $_POST['ids']);
        $ids = array_filter($ids);

        if (!$ids) {
            return;
        }

        $in = implode(',', array_fill(0, count($ids), '%d'));

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE id IN ($in)",
                $ids
            )
        );
    }

    public function prepare_items() {
        if (!function_exists('pokehub_get_table')) {
            $this->items = [];
            return;
        }

        global $wpdb;
        $table = pokehub_get_table('pokemon_items');

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'name_fr';
        $order   = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $allowed_orderby = ['name_fr', 'slug'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'name_fr';
        }

        $search = isset($_REQUEST['s']) ? wp_unslash(trim($_REQUEST['s'])) : '';
        $where  = 'WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $where   .= ' AND (name_en LIKE %s OR name_fr LIKE %s OR slug LIKE %s)';
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql_count = "SELECT COUNT(*) FROM {$table} {$where}";
        $total_items = $params
            ? (int) $wpdb->get_var($wpdb->prepare($sql_count, $params))
            : (int) $wpdb->get_var($sql_count);

        $sql_items = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params_items   = $params;
        $params_items[] = $per_page;
        $params_items[] = $offset;

        $this->items = $params
            ? $wpdb->get_results($wpdb->prepare($sql_items, $params_items))
            : $wpdb->get_results($wpdb->prepare($sql_items, $per_page, $offset));

        $columns  = $this->get_columns();
        $hidden   = get_hidden_columns($this->screen);
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => max(1, ceil($total_items / $per_page)),
        ]);
    }
}

/**
 * Traitement formulaire Item (add / update)
 */
function poke_hub_pokemon_handle_items_form() {
    if (!is_admin()) {
        return;
    }

    if (empty($_POST['poke_hub_pokemon_action'])) {
        return;
    }

    if (empty($_POST['ph_section']) || $_POST['ph_section'] !== 'items') {
        return;
    }

    $action = sanitize_text_field($_POST['poke_hub_pokemon_action']);
    if (!in_array($action, ['add_item', 'update_item'], true)) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('poke_hub_pokemon_edit_item');

    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;
    $table = pokehub_get_table('pokemon_items');

    $redirect_base = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'items',
        ],
        admin_url('admin.php')
    );

    $id        = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $name_fr   = isset($_POST['name_fr']) ? sanitize_text_field(wp_unslash($_POST['name_fr'])) : '';
    $name_en   = isset($_POST['name_en']) ? sanitize_text_field(wp_unslash($_POST['name_en'])) : '';
    $slug      = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
    $image_url = isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '';

    // 🔹 Descriptions
    $description_fr = isset($_POST['description_fr'])
        ? sanitize_textarea_field(wp_unslash($_POST['description_fr']))
        : '';
    $description_en = isset($_POST['description_en'])
        ? sanitize_textarea_field(wp_unslash($_POST['description_en']))
        : '';

    // Au moins un nom requis
    if ($name_fr === '' && $name_en === '') {
        wp_safe_redirect(add_query_arg('ph_msg', 'missing_name', $redirect_base));
        exit;
    }

    // Slug auto depuis FR ou EN
    if ($slug === '') {
        $base_for_slug = $name_fr !== '' ? $name_fr : $name_en;
        $slug = sanitize_title($base_for_slug);
    }

    $extra = [
        'image_url' => $image_url,
    ];

    $data = [
        'slug'           => $slug,
        'name_fr'        => $name_fr,
        'name_en'        => $name_en,
        'description_fr' => $description_fr,
        'description_en' => $description_en,
        'extra'          => wp_json_encode($extra),
    ];
    $format = ['%s', '%s', '%s', '%s', '%s', '%s'];

    if ($action === 'add_item') {
        $wpdb->insert($table, $data, $format);
        wp_safe_redirect(add_query_arg('ph_msg', 'saved', $redirect_base));
        exit;
    }

    // update
    if ($id <= 0) {
        wp_safe_redirect(add_query_arg('ph_msg', 'invalid_id', $redirect_base));
        exit;
    }

    $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);

    wp_safe_redirect(add_query_arg('ph_msg', 'updated', $redirect_base));
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_items_form');

/**
 * Delete simple (action=delete sur une ligne)
 */
function poke_hub_pokemon_handle_items_delete() {
    if (!is_admin()) {
        return;
    }

    if (empty($_GET['page']) || $_GET['page'] !== 'poke-hub-pokemon') {
        return;
    }

    if (empty($_GET['ph_section']) || $_GET['ph_section'] !== 'items') {
        return;
    }

    if (empty($_GET['action']) || $_GET['action'] !== 'delete') {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    if (!function_exists('pokehub_get_table')) {
        return;
    }

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        return;
    }

    check_admin_referer('poke_hub_delete_item_' . $id);

    global $wpdb;
    $table = pokehub_get_table('pokemon_items');

    $wpdb->delete($table, ['id' => $id], ['%d']);

    $redirect = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'items',
            'ph_msg'     => 'deleted',
        ],
        admin_url('admin.php')
    );

    wp_redirect($redirect);
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_items_delete');

/**
 * Écran principal "Items"
 */
function poke_hub_pokemon_admin_items_screen() {
    $list_table = new Poke_Hub_Pokemon_Items_List_Table();

    $list_table->process_bulk_action();
    $list_table->prepare_items();

    if (!empty($_GET['ph_msg'])) {
        $msg = sanitize_key($_GET['ph_msg']);
        if ($msg === 'saved' || $msg === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Item saved.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Item deleted.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'missing_name') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Name is required.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'invalid_id') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid item ID.', 'poke-hub') . '</p></div>';
        }
    }
    ?>
    <form method="post">
        <input type="hidden" name="page" value="poke-hub-pokemon" />
        <input type="hidden" name="ph_section" value="items" />
        <?php
        wp_nonce_field('bulk-pokemon_items');

        $list_table->search_box(__('Search item', 'poke-hub'), 'pokemon-item');
        $list_table->display();
        ?>
    </form>
    <?php
}
