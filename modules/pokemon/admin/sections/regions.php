<?php
// modules/pokemon/admin/sections/regions.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * On s'assure que WP_List_Table est dispo
 */
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * On inclut le formulaire dédié aux régions
 */
require_once POKE_HUB_POKEMON_PATH . '/admin/forms/region-form.php';

/**
 * List table des régions
 */
class Poke_Hub_Pokemon_Regions_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'pokemon_region',
            'plural'   => 'pokemon_regions',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            // On garde "name" comme identifiant de colonne (WP_List_Table),
            // mais on utilise name_fr / name_en en base.
            'name'       => __('Name', 'poke-hub'),
            'slug'       => __('Slug', 'poke-hub'),
            'sort_order' => __('Order', 'poke-hub'),
        ];
    }

    public function get_sortable_columns() {
        return [
            // L'orderby "name" côté UI mappe sur name_fr en base.
            'name'       => ['name_fr', true],
            'slug'       => ['slug', true],
            'sort_order' => ['sort_order', true],
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
                'ph_section' => 'regions',
                'action'     => 'edit',
                'id'         => (int) $item->id,
            ],
            admin_url('admin.php')
        );

        $delete_url = wp_nonce_url(
            add_query_arg(
                [
                    'page'       => 'poke-hub-pokemon',
                    'ph_section' => 'regions',
                    'action'     => 'delete',
                    'id'         => (int) $item->id,
                ],
                admin_url('admin.php')
            ),
            'poke_hub_delete_region_' . (int) $item->id
        );

        // NEW : priorité au nom FR, sinon EN.
        $name_fr = isset($item->name_fr) ? (string) $item->name_fr : '';
        $name_en = isset($item->name_en) ? (string) $item->name_en : '';
        $display_name = $name_fr !== '' ? $name_fr : $name_en;

        $title = esc_html($display_name);

        // URL de la page publique de la région
        $view_url = '';
        if (!empty($item->slug) && function_exists('pokehub_get_region_url')) {
            $view_url = pokehub_get_region_url($item->slug);
        }

        $actions = [];
        
        // Lien "View" vers la page publique
        if ($view_url) {
            $actions['view'] = sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url($view_url),
                esc_html__('View', 'poke-hub')
            );
        }
        
        $actions['edit'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($edit_url),
            esc_html__('Edit', 'poke-hub')
        );
        
        $actions['delete'] = sprintf(
            '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
            esc_url($delete_url),
            esc_attr__('Are you sure you want to delete this region?', 'poke-hub'),
            esc_html__('Delete', 'poke-hub')
        );

        return sprintf(
            '<strong><a href="%1$s">%2$s</a></strong> %3$s',
            esc_url($edit_url),
            $title,
            $this->row_actions($actions)
        );
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'slug':
                return '<code>' . esc_html($item->slug) . '</code>';

            case 'sort_order':
                return (int) $item->sort_order;
        }
        return '';
    }

    public function get_bulk_actions() {
        return [
            'bulk_delete' => __('Delete', 'poke-hub'),
        ];
    }

    public function process_bulk_action() {
        if ('bulk_delete' !== $this->current_action()) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('bulk-pokemon_regions');

        if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
            return;
        }

        if (!function_exists('pokehub_get_table')) {
            return;
        }

        global $wpdb;
        $table = pokehub_get_table('pokemon_regions');

        $ids = array_map('intval', $_POST['ids']);
        $ids = array_filter($ids);

        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '%d'));
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE id IN ($in)",
                    $ids
                )
            );
        }
    }

    public function prepare_items() {
        if (!function_exists('pokehub_get_table')) {
            $this->items = [];
            return;
        }

        global $wpdb;

        $table_regions = pokehub_get_table('pokemon_regions');

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'sort_order';
        $order   = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        // Mapper 'name' → 'name_fr'
        if ($orderby === 'name') {
            $orderby = 'name_fr';
        }

        $allowed_orderby = ['name_fr', 'slug', 'sort_order'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'sort_order';
        }

        // Recherche
        $search = isset($_REQUEST['s']) ? wp_unslash(trim($_REQUEST['s'])) : '';
        $where  = 'WHERE 1=1';
        $params = [];

        if ($search !== '') {
            // NEW : recherche sur name_en, name_fr, slug
            $where   .= " AND (name_en LIKE %s OR name_fr LIKE %s OR slug LIKE %s)";
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Total items
        $sql_count   = "SELECT COUNT(*) FROM {$table_regions} {$where}";
        $total_items = $params
            ? (int) $wpdb->get_var($wpdb->prepare($sql_count, $params))
            : (int) $wpdb->get_var($sql_count);

        // Items
        $sql_items = "
            SELECT *
            FROM {$table_regions}
            {$where}
            ORDER BY {$orderby} {$order}
            LIMIT %d OFFSET %d
        ";

        $params_items   = $params;
        $params_items[] = $per_page;
        $params_items[] = $offset;

        if ($params) {
            $this->items = $wpdb->get_results(
                $wpdb->prepare($sql_items, $params_items)
            );
        } else {
            $this->items = $wpdb->get_results(
                $wpdb->prepare($sql_items, $per_page, $offset)
            );
        }

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }
}

/**
 * Gestion delete (action simple)
 */
function poke_hub_pokemon_handle_regions_delete() {
    if (!is_admin()) {
        return;
    }

    if (empty($_GET['page']) || $_GET['page'] !== 'poke-hub-pokemon') {
        return;
    }

    if (empty($_GET['ph_section']) || $_GET['ph_section'] !== 'regions') {
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

    check_admin_referer('poke_hub_delete_region_' . $id);

    global $wpdb;
    $table = pokehub_get_table('pokemon_regions');
    $wpdb->delete($table, ['id' => $id], ['%d']);

    $redirect = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'regions',
            'ph_msg'     => 'deleted',
        ],
        admin_url('admin.php')
    );

    wp_redirect($redirect);
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_regions_delete');

/**
 * Traitement formulaire Régions (add / update)
 */
function poke_hub_pokemon_handle_regions_form() {
    if (!is_admin()) {
        return;
    }

    if (empty($_POST['poke_hub_pokemon_action'])) {
        return;
    }

    if (empty($_POST['ph_section']) || $_POST['ph_section'] !== 'regions') {
        return;
    }

    $action = sanitize_text_field($_POST['poke_hub_pokemon_action']);
    if (!in_array($action, ['add_region', 'update_region'], true)) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('poke_hub_pokemon_region_form', 'poke_hub_pokemon_region_nonce');

    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    $redirect_base = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'regions',
        ],
        admin_url('admin.php')
    );

    $id         = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    // NEW : noms multilingues
    $name_en    = isset($_POST['name_en']) ? sanitize_text_field($_POST['name_en']) : '';
    $name_fr    = isset($_POST['name_fr']) ? sanitize_text_field($_POST['name_fr']) : '';

    // Compat rétro : si les deux vides mais un ancien champ 'name' existe
    if ($name_en === '' && $name_fr === '' && !empty($_POST['name'])) {
        $fallback = sanitize_text_field($_POST['name']);
        $name_en  = $fallback;
    }

    $slug       = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
    $sort_order = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;

    // Au moins un nom requis
    if ($name_en === '' && $name_fr === '') {
        wp_redirect(add_query_arg('ph_msg', 'missing_name', $redirect_base));
        exit;
    }

    if ($slug === '') {
        // On génère le slug à partir du EN si dispo, sinon FR.
        $base = $name_en !== '' ? $name_en : $name_fr;
        $slug = sanitize_title($base);
    }

    $table = pokehub_get_table('pokemon_regions');

    $data = [
        'name_en'    => $name_en,
        'name_fr'    => $name_fr,
        'slug'       => $slug,
        'sort_order' => $sort_order,
    ];
    $format = ['%s', '%s', '%s', '%d'];

    if ($action === 'add_region') {
        $wpdb->insert($table, $data, $format);

        wp_redirect(add_query_arg('ph_msg', 'saved', $redirect_base));
        exit;
    }

    // update
    if ($id <= 0) {
        wp_redirect(add_query_arg('ph_msg', 'invalid_id', $redirect_base));
        exit;
    }

    $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);

    $redirect = add_query_arg(
        [
            'ph_msg' => 'updated',
        ],
        $redirect_base
    );

    wp_redirect($redirect);
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_regions_form');

/**
 * Écran principal de l’onglet "Regions"
 * → utilisé UNIQUEMENT en mode LISTE (add/edit gérés par poke_hub_pokemon_admin_ui())
 */
function poke_hub_pokemon_admin_regions_screen() {

    $list_table = new Poke_Hub_Pokemon_Regions_List_Table();

    // Bulk actions
    $list_table->process_bulk_action();
    $list_table->prepare_items();

    // Notices
    if (!empty($_GET['ph_msg'])) {
        $msg = sanitize_key($_GET['ph_msg']);
        if ($msg === 'saved' || $msg === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Region saved.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Region deleted.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'missing_name') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Name is required.', 'poke-hub') . '</p></div>';
        }
    }
    ?>
    <form method="post">
        <input type="hidden" name="page" value="poke-hub-pokemon" />
        <input type="hidden" name="ph_section" value="regions" />
        <?php
        // nonce pour les actions groupées
        wp_nonce_field('bulk-pokemon_regions');

        $list_table->search_box(__('Search regions', 'poke-hub'), 'pokemon-regions');
        $list_table->display();
        ?>
    </form>
    <?php
}
