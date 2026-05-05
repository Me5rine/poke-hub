<?php
// modules/collections/admin/collections-admin.php — liste des collections enregistrées (admin).

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Tableau liste des collections (compte WP ou anonymes).
 */
class Poke_Hub_Collections_Admin_List_Table extends WP_List_Table {

    /** @var int */
    private $owner_filter = 0;

    /** @var string */
    private $search = '';

    public function __construct() {
        parent::__construct([
            'singular' => 'poke_hub_collection',
            'plural'   => 'poke_hub_collections',
            'ajax'     => false,
            'screen'   => 'poke-hub_page_poke-hub-collections',
        ]);
    }

    /**
     * @param int    $owner_filter 0=all, 1=registered users, 2=anonymous
     * @param string $search
     */
    public function set_filters(int $owner_filter, string $search): void {
        $this->owner_filter = max(0, min(2, $owner_filter));
        $this->search      = $search;
    }

    /**
     * Suppression unitaire ou en masse (admin).
     */
    public function process_actions(): void {
        if (! isset($_REQUEST['page']) || sanitize_key((string) wp_unslash($_REQUEST['page'])) !== 'poke-hub-collections') {
            return;
        }
        if (! current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['action']) && sanitize_key((string) wp_unslash($_GET['action'])) === 'delete' && isset($_GET['collection_id'])) {
            $cid = (int) $_GET['collection_id'];
            check_admin_referer('poke_hub_collection_delete_' . $cid);
            $res = poke_hub_collections_admin_force_delete($cid);
            $redirect_base = add_query_arg(
                'poke_hub_col_msg',
                ! empty($res['success']) ? 'deleted' : 'err',
                admin_url('admin.php?page=poke-hub-collections')
            );
            wp_safe_redirect($redirect_base);
            exit;
        }

        if (isset($_POST['action']) && sanitize_key((string) wp_unslash($_POST['action'])) === 'delete') {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $ids = isset($_POST['collection_ids']) ? array_map('intval', (array) wp_unslash($_POST['collection_ids'])) : [];
            $n   = 0;
            foreach ($ids as $id) {
                if ($id > 0 && ! empty(poke_hub_collections_admin_force_delete($id)['success'])) {
                    $n++;
                }
            }
            wp_safe_redirect(
                add_query_arg('poke_hub_col_msg', 'bulk_' . $n, admin_url('admin.php?page=poke-hub-collections'))
            );
            exit;
        }
    }

    public function get_columns(): array {
        return [
            'cb'                => '<input type="checkbox" />',
            'collection_title'  => __('Name', 'poke-hub'),
            'owner'             => __('Owner', 'poke-hub'),
            'category'          => __('Collection type', 'poke-hub'),
            'progress'          => __('Progress', 'poke-hub'),
            'visibility'        => __('Visibility', 'poke-hub'),
            'share_token'       => __('Share token', 'poke-hub'),
            'updated_at'        => __('Last update', 'poke-hub'),
        ];
    }

    protected function get_sortable_columns(): array {
        return [
            'collection_title' => ['name', false],
            'updated_at'       => ['updated_at', true],
            'share_token'      => ['share_token', false],
        ];
    }

    public function no_items(): void {
        esc_html_e('No collections found.', 'poke-hub');
    }

    public function column_cb($item): string {
        return sprintf(
            '<input type="checkbox" name="collection_ids[]" value="%d" />',
            (int) $item['id']
        );
    }

    public function column_collection_title($item): string {
        $name = (string) ($item['name'] ?? '');
        if ($name === '') {
            $name = __('(no name)', 'poke-hub');
        }
        $id    = (int) $item['id'];
        $nonce = wp_create_nonce('poke_hub_collection_delete_' . $id);
        $del   = add_query_arg(
            [
                'page'           => 'poke-hub-collections',
                'action'         => 'delete',
                'collection_id'  => $id,
                '_wpnonce'       => $nonce,
            ],
            admin_url('admin.php')
        );

        $actions = [
            'delete' => sprintf(
                '<a href="%s" class="poke-hub-col-admin-delete" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($del),
                esc_attr(__('Delete this collection permanently?', 'poke-hub')),
                esc_html__('Delete', 'poke-hub')
            ),
        ];

        $url = function_exists('poke_hub_collections_public_view_url') ? poke_hub_collections_public_view_url($item) : '';
        if ($url !== '') {
            $actions['view'] = sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url($url),
                esc_html__('View', 'poke-hub')
            );
        }

        return sprintf('<strong>%s</strong> <span class="description">(ID %d)</span>%s', esc_html($name), $id, $this->row_actions($actions));
    }

    public function column_owner($item): string {
        $uid = (int) ($item['user_id'] ?? 0);
        if ($uid > 0) {
            $u = get_userdata($uid);
            if ($u) {
                $line = sprintf(
                    '<a href="%s">%s</a><br><span class="description">%s · ID %d</span>',
                    esc_url(get_edit_user_link($uid)),
                    esc_html($u->display_name ?: $u->user_login),
                    esc_html($u->user_email),
                    $uid
                );

                return sprintf(
                    '<span class="poke-hub-col-owner poke-hub-col-owner--user">%s<br><em>%s</em></span>',
                    $line,
                    esc_html__('Registered account', 'poke-hub')
                );
            }

            return sprintf(
                '<span class="description">%s (ID %d)</span>',
                esc_html__('Unknown user', 'poke-hub'),
                $uid
            );
        }

        $ip   = isset($item['anonymous_ip']) ? preg_replace('/[^0-9a-f.:]/', '', (string) $item['anonymous_ip']) : '';
        $ok   = isset($item['anonymous_owner_key']) ? (string) $item['anonymous_owner_key'] : '';
        $hint = '';
        if ($ok !== '') {
            $hint = '<br><span class="description">' . esc_html__('Owner key (prefix)', 'poke-hub') . ': ' . esc_html(substr($ok, 0, 12)) . '…</span>';
        }

        return sprintf(
            '<span class="poke-hub-col-owner poke-hub-col-owner--anon"><strong>%s</strong>%s%s</span>',
            esc_html__('Anonymous', 'poke-hub'),
            $ip !== '' ? '<br><span class="description">IP&nbsp;: ' . esc_html($ip) . '</span>' : '',
            $hint
        );
    }

    public function column_category($item): string {
        $cat = sanitize_key((string) ($item['category'] ?? 'custom'));
        $map = function_exists('poke_hub_collections_get_categories') ? poke_hub_collections_get_categories() : [];

        return esc_html(isset($map[$cat]) ? (string) $map[$cat] : $cat);
    }

    public function column_progress($item): string {
        if (! function_exists('poke_hub_collections_get_pool') || ! function_exists('poke_hub_collections_get_items')
            || ! function_exists('poke_hub_collections_compute_progress_totals')) {
            return '<span class="description">—</span>';
        }
        $id      = (int) $item['id'];
        $options = isset($item['options']) && is_array($item['options']) ? $item['options'] : [];
        $cat     = sanitize_key((string) ($item['category'] ?? 'custom'));
        $pool  = poke_hub_collections_get_pool($cat, $options);
        $items = poke_hub_collections_get_items($id);
        $t     = poke_hub_collections_compute_progress_totals($pool, $items);
        $total = (int) $t['total'];
        $own   = (int) $t['owned'];
        $ft    = (int) $t['for_trade'];
        $pct   = $t['percent_owned'];
        if ($total <= 0) {
            return '<span class="description">—</span>';
        }

        return sprintf(
            '<strong>%d / %d</strong> (%s%%)<br><span class="description">%s: %d · %s: %d</span>',
            $own,
            $total,
            esc_html(number_format_i18n($pct, 1)),
            esc_html(__('Owned', 'poke-hub')),
            $own,
            esc_html(__('For trade', 'poke-hub')),
            $ft
        );
    }

    public function column_visibility($item): string {
        $pub = ! empty($item['is_public']);

        return $pub
            ? '<span class="dashicons dashicons-visibility" style="vertical-align:text-top" title="' . esc_attr__('Public', 'poke-hub') . '"></span> ' . esc_html__('Public', 'poke-hub')
            : '<span class="dashicons dashicons-hidden" style="vertical-align:text-top;color:#757575" title="' . esc_attr__('Private', 'poke-hub') . '"></span> ' . esc_html__('Private', 'poke-hub');
    }

    public function column_share_token($item): string {
        $tok = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($item['share_token'] ?? ''));

        return $tok !== '' ? sprintf('<code>%s</code>', esc_html($tok)) : '<span class="description">—</span>';
    }

    public function column_updated_at($item): string {
        $ts = strtotime((string) ($item['updated_at'] ?? ''));
        if (! $ts) {
            return '<span class="description">—</span>';
        }

        return esc_html(
            sprintf(
                /* translators: %s = localized date/time. */
                __('Updated: %s', 'poke-hub'),
                wp_date(get_option('date_format') . ' ' . get_option('time_format'), $ts)
            )
        );
    }

    public function prepare_items(): void {
        global $wpdb;

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = max(0, ($current_page - 1) * $per_page);

        $collections_table = function_exists('pokehub_get_table') ? pokehub_get_table('collections') : '';
        if (! $collections_table) {
            $this->items = [];
            $this->set_pagination_args([
                'total_items' => 0,
                'per_page'    => $per_page,
                'total_pages' => 0,
            ]);

            return;
        }

        $where  = ['1=1'];
        $params = [];

        if ($this->owner_filter === 1) {
            $where[] = 'user_id > 0';
        } elseif ($this->owner_filter === 2) {
            $where[] = 'user_id = 0';
        }

        if ($this->search !== '') {
            $like    = '%' . $wpdb->esc_like($this->search) . '%';
            $where[] = 'name LIKE %s';
            $params[] = $like;
        }

        $where_sql = implode(' AND ', $where);

        $orderby_req = isset($_REQUEST['orderby']) ? sanitize_key((string) wp_unslash($_REQUEST['orderby'])) : '';
        $order_req   = isset($_REQUEST['order']) ? strtoupper(sanitize_key((string) wp_unslash($_REQUEST['order']))) : 'DESC';
        $order_req   = $order_req === 'ASC' ? 'ASC' : 'DESC';

        $orderby_map = [
            'name'        => 'name',
            'updated_at'  => 'updated_at',
            'share_token' => 'share_token',
            'id'          => 'id',
        ];
        $order_col = isset($orderby_map[$orderby_req]) ? $orderby_map[$orderby_req] : 'updated_at';

        $count_sql = "SELECT COUNT(*) FROM {$collections_table} WHERE {$where_sql}";
        $total     = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, $params) : $count_sql);

        $sql = "SELECT id, user_id, name, slug, share_token, anonymous_ip, anonymous_owner_key, category, options, is_public, created_at, updated_at
                FROM {$collections_table}
                WHERE {$where_sql}
                ORDER BY {$order_col} {$order_req}
                LIMIT %d OFFSET %d";
        $all_params   = array_merge($params, [$per_page, $offset]);
        $rows         = $wpdb->get_results($wpdb->prepare($sql, $all_params), ARRAY_A);
        $this->items  = is_array($rows) ? $rows : [];

        foreach ($this->items as &$row) {
            if (! empty($row['options'])) {
                $row['options'] = json_decode((string) $row['options'], true) ?: [];
            } else {
                $row['options'] = function_exists('poke_hub_collections_default_options') ? poke_hub_collections_default_options() : [];
            }
        }
        unset($row);

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), $this->get_primary_column_name()];

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total / $per_page),
        ]);
    }

    protected function get_primary_column_name(): string {
        return 'collection_title';
    }

    protected function get_bulk_actions(): array {
        return [
            'delete' => __('Delete', 'poke-hub'),
        ];
    }
}

/**
 * Sous-menu admin Collections.
 */
function poke_hub_collections_register_admin_menu(): void {
    if (! function_exists('poke_hub_is_module_active') || ! poke_hub_is_module_active('collections')) {
        return;
    }

    add_submenu_page(
        'poke-hub',
        __('Pokémon GO collections (saved)', 'poke-hub'),
        __('Collections', 'poke-hub'),
        'manage_options',
        'poke-hub-collections',
        'poke_hub_collections_admin_render_page'
    );
}

/**
 * Rend la page liste.
 */
function poke_hub_collections_admin_render_page(): void {
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'poke-hub'));
    }

    if (! function_exists('pokehub_get_table') || ! pokehub_get_table('collections')) {
        echo '<div class="wrap"><h1>' . esc_html(__('Collections', 'poke-hub')) . '</h1>';
        echo '<p class="description">' . esc_html__('The collections table is not available.', 'poke-hub') . '</p></div>';

        return;
    }

    if (isset($_GET['poke_hub_col_msg'])) {
        $msg = sanitize_key((string) wp_unslash($_GET['poke_hub_col_msg']));
        if ($msg === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Collection deleted.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'err') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Could not delete.', 'poke-hub') . '</p></div>';
        } elseif (strpos($msg, 'bulk_') === 0) {
            $n = (int) substr($msg, 5);
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(
                /* translators: %d = number deleted. */
                esc_html(_n('%d collection deleted.', '%d collections deleted.', $n, 'poke-hub')),
                $n
            ) . '</p></div>';
        }
    }

    $owner_filter = isset($_GET['owner']) ? (int) $_GET['owner'] : 0;
    $search       = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';

    $table = new Poke_Hub_Collections_Admin_List_Table();
    $table->set_filters($owner_filter, $search);
    $table->process_actions();
    $table->prepare_items();

    echo '<div class="wrap poke-hub-collections-admin">';
    echo '<h1 class="wp-heading-inline">' . esc_html(__('Saved collections', 'poke-hub')) . '</h1>';
    echo '<hr class="wp-header-end" />';

    echo '<form method="get" style="margin:1em 0">';
    echo '<input type="hidden" name="page" value="poke-hub-collections" />';

    echo '<p class="search-box">';
    printf(
        '<label class="screen-reader-text" for="poke-hub-col-owner">%s</label>',
        esc_html__('Filter by owner', 'poke-hub')
    );
    echo '<select name="owner" id="poke-hub-col-owner">';
    printf('<option value="0"%s>%s</option>', selected($owner_filter, 0, false), esc_html(__('All owners', 'poke-hub')));
    printf('<option value="1"%s>%s</option>', selected($owner_filter, 1, false), esc_html(__('Registered accounts', 'poke-hub')));
    printf('<option value="2"%s>%s</option>', selected($owner_filter, 2, false), esc_html(__('Anonymous', 'poke-hub')));
    echo '</select> ';

    printf(
        '<label class="screen-reader-text" for="poke-hub-col-search">%s</label>',
        esc_html__('Search collections', 'poke-hub')
    );
    printf(
        '<input type="search" id="poke-hub-col-search" name="s" value="%s" placeholder="%s" /> ',
        esc_attr($search),
        esc_attr(__('Collection name…', 'poke-hub'))
    );
    submit_button(__('Filter', 'poke-hub'), 'secondary', 'filter_action', false);
    echo '</p>';
    echo '</form>';

    echo '<form method="post">';
    $table->display();
    echo '</form>';
    echo '</div>';
}

add_action('admin_menu', 'poke_hub_collections_register_admin_menu', 18);
