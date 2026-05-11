<?php
// modules/go-search-filters/admin/class-poke-hub-go-search-filters-list-table.php

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Liste admin du catalogue de filtres recherche Pokémon GO.
 */
class Poke_Hub_Go_Search_Filters_List_Table extends WP_List_Table {

    /** @var string */
    private $search = '';

    public function __construct() {
        parent::__construct([
            'singular' => 'poke_hub_go_search_filter',
            'plural'   => 'poke_hub_go_search_filters',
            'ajax'     => false,
            'screen'   => 'poke-hub_page_poke-hub-go-tools',
        ]);
    }

    public function set_search(string $search): void {
        $this->search = $search;
    }

    public function get_columns(): array {
        return [
            'code'               => __('Code', 'poke-hub'),
            'filter_fr'          => __('French keyword', 'poke-hub'),
            'filter_en'          => __('English keyword', 'poke-hub'),
            'scopes'             => __('Scopes', 'poke-hub'),
            'use_in_collections' => __('Collections', 'poke-hub'),
            'sort_order'         => __('Order', 'poke-hub'),
        ];
    }

    protected function get_sortable_columns(): array {
        return [
            'code'       => ['code', false],
            'filter_fr'  => ['filter_fr', false],
            'filter_en'  => ['filter_en', false],
            'sort_order' => ['sort_order', true],
        ];
    }

    protected function column_default($item, $column_name): string {
        unset($item, $column_name);

        return '&mdash;';
    }

    public function column_code(array $item): string {
        $id   = (int) ($item['id'] ?? 0);
        $code = (string) ($item['code'] ?? '');
        $edit = add_query_arg(
            [
                'page'    => 'poke-hub-go-tools',
                'tab'     => 'search-filters',
                'item_id' => $id,
            ],
            admin_url('admin.php')
        );

        $actions = [
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($edit),
                esc_html__('Edit', 'poke-hub')
            ),
        ];
        if (empty($item['is_system'])) {
            $del = wp_nonce_url(
                add_query_arg(
                    [
                        'page'                 => 'poke-hub-go-tools',
                        'tab'                  => 'search-filters',
                        'poke_hub_gsf_delete' => '1',
                        'item_id'              => $id,
                    ],
                    admin_url('admin.php')
                ),
                'poke_hub_gsf_delete_' . $id
            );
            $actions['delete'] = sprintf(
                '<a href="%s" class="poke-hub-go-search-filters-delete" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($del),
                esc_attr(__('Delete this filter?', 'poke-hub')),
                esc_html__('Delete', 'poke-hub')
            );
        }

        $bundled = ! empty($item['is_system'])
            ? ' <span class="description">(' . esc_html__('bundled', 'poke-hub') . ')</span>'
            : '';

        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s%s',
            esc_url($edit),
            esc_html($code),
            $bundled,
            $this->row_actions($actions)
        );
    }

    public function column_filter_fr(array $item): string {
        $v = (string) ($item['filter_fr'] ?? '');

        return $v !== '' ? '<code>' . esc_html($v) . '</code>' : '<span class="description">—</span>';
    }

    public function column_filter_en(array $item): string {
        $v = (string) ($item['filter_en'] ?? '');

        return $v !== '' ? '<code>' . esc_html($v) . '</code>' : '<span class="description">—</span>';
    }

    public function column_scopes(array $item): string {
        $scopes = [];
        if (! empty($item['scope_pokemon'])) {
            $scopes[] = __('Pokémon', 'poke-hub');
        }
        if (! empty($item['scope_friends'])) {
            $scopes[] = __('Friends', 'poke-hub');
        }

        return esc_html(implode(', ', $scopes !== [] ? $scopes : ['—']));
    }

    public function column_use_in_collections(array $item): string {
        return ! empty($item['use_in_collections'])
            ? '<span class="screen-reader-text">' . esc_html__('Yes', 'poke-hub') . '</span>'
                . '<span class="dashicons dashicons-yes" aria-hidden="true"></span>'
            : '<span class="description">—</span>';
    }

    public function column_sort_order(array $item): string {
        return esc_html((string) (int) ($item['sort_order'] ?? 0));
    }

    public function no_items(): void {
        esc_html_e('No filters found.', 'poke-hub');
    }

    protected function get_primary_column_name(): string {
        return 'code';
    }

    public function prepare_items(): void {
        global $wpdb;

        $per_page = $this->get_items_per_page('poke_hub_gsf_per_page', 25);
        $paged    = $this->get_pagenum();
        $offset   = max(0, ($paged - 1) * $per_page);

        $table = function_exists('pokehub_get_table') ? pokehub_get_table('go_search_filters') : '';
        if ($table === '' || ! poke_hub_go_search_filters_table_exists()) {
            $this->items = [];
            $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), $this->get_primary_column_name()];
            $this->set_pagination_args([
                'total_items' => 0,
                'per_page'    => $per_page,
                'total_pages' => 0,
            ]);

            return;
        }

        $where  = ['1=1'];
        $params = [];

        if ($this->search !== '') {
            $like      = '%' . $wpdb->esc_like($this->search) . '%';
            $where[] = '(code LIKE %s OR filter_fr LIKE %s OR filter_en LIKE %s)';
            array_push($params, $like, $like, $like);
        }

        $where_sql = implode(' AND ', $where);

        $orderby_req = isset($_REQUEST['orderby']) ? sanitize_key((string) wp_unslash($_REQUEST['orderby'])) : '';
        $order_req   = isset($_REQUEST['order']) ? strtoupper(sanitize_key((string) wp_unslash($_REQUEST['order']))) : '';
        $order_req   = $order_req === 'DESC' ? 'DESC' : 'ASC';

        $orderby_map = [
            'code'       => 'code',
            'filter_fr'   => 'filter_fr',
            'filter_en'   => 'filter_en',
            'sort_order' => 'sort_order',
        ];
        $order_col = isset($orderby_map[$orderby_req]) ? $orderby_map[$orderby_req] : 'sort_order';
        if ($order_col === 'sort_order' && $orderby_req === '') {
            $order_req = 'ASC';
        }

        $count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}";
        $total     = (int) ($params !== [] ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql));

        $sql = "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY {$order_col} {$order_req}, code ASC LIMIT %d OFFSET %d";
        $all_params = array_merge($params, [$per_page, $offset]);
        $rows       = $wpdb->get_results($wpdb->prepare($sql, $all_params), ARRAY_A);
        $this->items = is_array($rows) ? $rows : [];

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), $this->get_primary_column_name()];

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
        ]);
    }
}
