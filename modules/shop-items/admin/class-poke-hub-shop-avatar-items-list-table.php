<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Liste des articles boutique avatar (écran principal).
 */
class Poke_Hub_Shop_Avatar_Items_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'shop_avatar_item',
            'plural'   => 'shop_avatar_items',
            'ajax'     => false,
            'screen'   => 'poke-hub_page_poke-hub-shop-items',
        ]);
    }

    public function get_columns(): array {
        return [
            'cb'       => '<input type="checkbox" />',
            'name_fr'  => __('Name (FR)', 'poke-hub'),
            'thumb'    => __('Image', 'poke-hub'),
            'name_en'  => __('Name (EN)', 'poke-hub'),
            'slug'     => __('Slug', 'poke-hub'),
            'category' => __('Category', 'poke-hub'),
            'sort'     => __('Sort', 'poke-hub'),
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
        esc_html_e('No shop items found.', 'poke-hub');
    }

    protected function get_views(): array {
        global $wpdb;
        $views   = [];
        $base    = poke_hub_shop_items_admin_url('avatar');
        $current = isset($_GET['cat_id']) ? (int) $_GET['cat_id'] : 0;

        $views['all'] = sprintf(
            '<a href="%s"%s>%s</a>',
            esc_url(remove_query_arg(['cat_id', 'paged'], $base)),
            $current === 0 ? ' class="current" aria-current="page"' : '',
            esc_html__('All categories', 'poke-hub')
        );

        if (!function_exists('pokehub_get_table')) {
            return $views;
        }
        $cat_tbl = pokehub_get_table('shop_avatar_categories');
        if (!$cat_tbl) {
            return $views;
        }
        $cats = $wpdb->get_results("SELECT id, name_fr, name_en, slug FROM {$cat_tbl} ORDER BY sort_order ASC, id ASC");
        foreach ((array) $cats as $c) {
            $cid = (int) $c->id;
            $lab = trim((string) $c->name_fr) !== '' ? (string) $c->name_fr : (string) $c->name_en;
            if ($lab === '') {
                $lab = (string) $c->slug;
            }
            $url = add_query_arg(['cat_id' => $cid, 'paged' => false], $base);
            $views['cat_' . $cid] = sprintf(
                '<a href="%s"%s>%s</a>',
                esc_url($url),
                $current === $cid ? ' class="current" aria-current="page"' : '',
                esc_html($lab)
            );
        }

        return $views;
    }

    public function column_cb($item): string {
        return sprintf(
            '<input type="checkbox" name="ids[]" value="%d" />',
            (int) $item->id
        );
    }

    public function column_thumb($item): string {
        if (!function_exists('poke_hub_shop_avatar_get_item_image_urls')) {
            return '&mdash;';
        }
        $urls = poke_hub_shop_avatar_get_item_image_urls($item);
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
        $edit_url = poke_hub_shop_items_admin_url('avatar', ['action' => 'edit', 'id' => (int) $item->id]);
        $del_url = wp_nonce_url(
            poke_hub_shop_items_admin_url('avatar', ['action' => 'delete', 'id' => (int) $item->id]),
            'poke_hub_shop_avatar_delete_item_' . (int) $item->id
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

    public function column_category($item): string {
        if (!empty($item->cat_name_fr) || !empty($item->cat_name_en)) {
            $lab = trim((string) $item->cat_name_fr) !== '' ? (string) $item->cat_name_fr : (string) $item->cat_name_en;
            return esc_html($lab);
        }
        if (!empty($item->cat_slug)) {
            return esc_html((string) $item->cat_slug);
        }
        return '&mdash;';
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
        check_admin_referer('bulk-shop_avatar_items');

        if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
            return;
        }

        if (!function_exists('pokehub_get_table')) {
            return;
        }

        global $wpdb;
        $items_tbl = pokehub_get_table('shop_avatar_items');
        $ev_tbl    = pokehub_get_table('shop_avatar_item_events');
        $ent_tbl   = pokehub_get_table('content_shop_avatar_entries');

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
            $wpdb->delete($ent_tbl, ['shop_avatar_item_id' => $id], ['%d']);
        }
        $wpdb->query($wpdb->prepare("DELETE FROM {$items_tbl} WHERE id IN ({$in})", ...$ids));
    }

    public function prepare_items(): void {
        if (!function_exists('pokehub_get_table')) {
            $this->items = [];
            return;
        }

        global $wpdb;
        $items_tbl = pokehub_get_table('shop_avatar_items');
        $cat_tbl   = pokehub_get_table('shop_avatar_categories');
        if (!$items_tbl || !$cat_tbl) {
            $this->items = [];
            return;
        }

        $per_page = (int) get_user_meta(get_current_user_id(), 'poke_hub_shop_avatar_items_per_page', true);
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

        $cat_filter = isset($_GET['cat_id']) ? (int) $_GET['cat_id'] : 0;
        if ($cat_filter > 0) {
            $where   .= ' AND i.category_id = %d';
            $params[] = $cat_filter;
        }

        if ($search !== '') {
            $where   .= ' AND (i.name_fr LIKE %s OR i.name_en LIKE %s OR i.slug LIKE %s)';
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $from = "FROM {$items_tbl} i LEFT JOIN {$cat_tbl} c ON c.id = i.category_id {$where}";

        $sql_count = "SELECT COUNT(*) {$from}";
        $total_items = $params
            ? (int) $wpdb->get_var($wpdb->prepare($sql_count, $params))
            : (int) $wpdb->get_var($sql_count);

        $sql_items = "SELECT i.*, c.name_fr AS cat_name_fr, c.name_en AS cat_name_en, c.slug AS cat_slug {$from} ORDER BY {$order_sql} {$order} LIMIT %d OFFSET %d";
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
