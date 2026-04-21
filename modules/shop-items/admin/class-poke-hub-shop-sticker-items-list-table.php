<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Liste des stickers en jeu (écran principal).
 */
class Poke_Hub_Shop_Sticker_Items_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'shop_sticker_item',
            'plural'   => 'shop_sticker_items',
            'ajax'     => false,
            'screen'   => 'poke-hub_page_poke-hub-shop-items',
        ]);
    }

    public function get_columns(): array {
        return [
            'cb'      => '<input type="checkbox" />',
            'name_fr' => __('Name (FR)', 'poke-hub'),
            'thumb'   => __('Image', 'poke-hub'),
            'name_en' => __('Name (EN)', 'poke-hub'),
            'slug'    => __('Slug', 'poke-hub'),
            'sort'    => __('Sort', 'poke-hub'),
        ];
    }

    public function get_sortable_columns(): array {
        return [
            'name_fr' => ['name_fr', true],
            'name_en' => ['name_en', false],
            'slug'    => ['slug', false],
            'sort'    => ['sort_order', false],
        ];
    }

    public function get_bulk_actions(): array {
        return [
            'bulk_delete' => __('Delete', 'poke-hub'),
        ];
    }

    public function no_items(): void {
        esc_html_e('No sticker items found.', 'poke-hub');
    }

    public function column_cb($item): string {
        return sprintf(
            '<input type="checkbox" name="ids[]" value="%d" />',
            (int) $item->id
        );
    }

    public function column_thumb($item): string {
        if (!function_exists('poke_hub_shop_sticker_get_item_image_urls')) {
            return '&mdash;';
        }
        $urls = poke_hub_shop_sticker_get_item_image_urls($item);
        $src  = $urls['primary'] ?? '';
        if ($src === '') {
            return '&mdash;';
        }
        return sprintf(
            '<img src="%s" alt="" width="48" height="48" loading="lazy" style="object-fit:contain;border-radius:4px;background:#f0f0f1" onerror="this.style.visibility=\'hidden\'" />',
            esc_url($src)
        );
    }

    public function column_name_fr($item): string {
        $edit_url = poke_hub_shop_items_admin_url('stickers', ['action' => 'edit', 'id' => (int) $item->id]);
        $del_url = wp_nonce_url(
            poke_hub_shop_items_admin_url('stickers', ['action' => 'delete', 'id' => (int) $item->id]),
            'poke_hub_shop_sticker_delete_item_' . (int) $item->id
        );

        $title = trim((string) $item->name_fr) !== '' ? (string) $item->name_fr : (string) $item->name_en;
        if ($title === '') {
            $title = (string) $item->slug;
        }

        $actions = [
            'edit' => sprintf('<a href="%s">%s</a>', esc_url($edit_url), esc_html__('Edit', 'poke-hub')),
            'delete' => sprintf(
                '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($del_url),
                esc_attr__('Delete this item?', 'poke-hub'),
                esc_html__('Delete', 'poke-hub')
            ),
        ];

        return sprintf(
            '<strong><a class="row-title" href="%1$s">%2$s</a></strong>%3$s',
            esc_url($edit_url),
            esc_html($title),
            $this->row_actions($actions)
        );
    }

    public function column_name_en($item): string {
        return esc_html((string) $item->name_en);
    }

    public function column_slug($item): string {
        return '<code>' . esc_html((string) $item->slug) . '</code>';
    }

    public function column_sort($item): string {
        return esc_html((string) (int) $item->sort_order);
    }

    public function column_default($item, $column_name): string {
        return '';
    }

    public function process_bulk_action(): void {
        if ('bulk_delete' !== $this->current_action()) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('bulk-shop_sticker_items');

        if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
            return;
        }

        if (!function_exists('pokehub_get_table')) {
            return;
        }

        global $wpdb;
        $items_tbl = pokehub_get_table('shop_sticker_items');
        $ev_tbl    = pokehub_get_table('shop_sticker_item_events');
        $ent_tbl   = pokehub_get_table('content_shop_sticker_entries');

        $ids = array_map('intval', wp_unslash($_POST['ids']));
        $ids = array_values(array_filter($ids, static function ($id) {
            return $id > 0;
        }));
        if (!$ids) {
            return;
        }

        $in = implode(',', array_fill(0, count($ids), '%d'));
        foreach ($ids as $id) {
            $wpdb->delete($ev_tbl, ['item_id' => $id], ['%d']);
            $wpdb->delete($ent_tbl, ['shop_sticker_item_id' => $id], ['%d']);
        }
        $wpdb->query($wpdb->prepare("DELETE FROM {$items_tbl} WHERE id IN ({$in})", ...$ids));
    }

    public function prepare_items(): void {
        if (!function_exists('pokehub_get_table')) {
            $this->items = [];
            return;
        }

        global $wpdb;
        $items_tbl = pokehub_get_table('shop_sticker_items');
        if (!$items_tbl) {
            $this->items = [];
            return;
        }

        $per_page = (int) get_user_meta(get_current_user_id(), 'poke_hub_shop_sticker_items_per_page', true);
        if ($per_page < 1 || $per_page > 200) {
            $per_page = 20;
        }

        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_key((string) wp_unslash($_GET['orderby'])) : 'name_fr';
        $order   = isset($_GET['order']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) : 'ASC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }
        $allowed = ['name_fr', 'name_en', 'slug', 'sort_order', 'id'];
        if (!in_array($orderby, $allowed, true)) {
            $orderby = 'name_fr';
        }
        $order_sql = 'i.' . $orderby;

        $search = isset($_REQUEST['s']) ? trim((string) wp_unslash($_REQUEST['s'])) : '';
        $where  = 'WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $where   .= ' AND (i.name_fr LIKE %s OR i.name_en LIKE %s OR i.slug LIKE %s)';
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $from = "FROM {$items_tbl} i {$where}";

        $sql_count = "SELECT COUNT(*) {$from}";
        $total_items = $params
            ? (int) $wpdb->get_var($wpdb->prepare($sql_count, $params))
            : (int) $wpdb->get_var($sql_count);

        $sql_items = "SELECT i.* {$from} ORDER BY {$order_sql} {$order} LIMIT %d OFFSET %d";
        $params_items   = $params;
        $params_items[] = $per_page;
        $params_items[] = $offset;

        $this->items = $wpdb->get_results($wpdb->prepare($sql_items, $params_items));

        $columns  = $this->get_columns();
        $hidden   = [];
        if ($this->screen) {
            $hidden = get_hidden_columns($this->screen);
        }
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => max(1, (int) ceil($total_items / $per_page)),
        ]);
    }
}
