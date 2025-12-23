<?php
// modules/pokemon/admin/sections/generations.php

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
 * On inclut le formulaire dédié aux générations
 */
require_once POKE_HUB_POKEMON_PATH . '/admin/forms/generation-form.php';

/**
 * List table des générations
 */
class Poke_Hub_Pokemon_Generations_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'pokemon_generation',
            'plural'   => 'pokemon_generations',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'                => '<input type="checkbox" />',
            'label'             => __('Name', 'poke-hub'),
            'generation_number' => __('Generation #', 'poke-hub'),
            'slug'              => __('Slug', 'poke-hub'),
            'region_label'      => __('Region', 'poke-hub'),
        ];
    }

    public function get_sortable_columns() {
        return [
            'generation_number' => ['generation_number', true],
            // 'label' va être mappé sur g.name_fr en SQL
            'label'             => ['label', true],
            'slug'              => ['slug', true],
        ];
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="ids[]" value="%d" />',
            (int) $item->id
        );
    }

    public function column_label($item) {
        $edit_url = add_query_arg(
            [
                'page'       => 'poke-hub-pokemon',
                'ph_section' => 'generations',
                'action'     => 'edit',
                'id'         => (int) $item->id,
            ],
            admin_url('admin.php')
        );

        $delete_url = wp_nonce_url(
            add_query_arg(
                [
                    'page'       => 'poke-hub-pokemon',
                    'ph_section' => 'generations',
                    'action'     => 'delete',
                    'id'         => (int) $item->id,
                ],
                admin_url('admin.php')
            ),
            'poke_hub_delete_generation_' . (int) $item->id
        );

        // NEW : ui_label = COALESCE(name_fr, name_en) dans la requête SQL
        $title_raw = isset($item->ui_label) ? $item->ui_label : '';
        $title     = esc_html($title_raw);

        // URL de la page publique de la génération
        $view_url = '';
        if (!empty($item->slug) && function_exists('pokehub_get_generation_url')) {
            $view_url = pokehub_get_generation_url($item->slug);
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
            esc_attr__('Are you sure you want to delete this generation?', 'poke-hub'),
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
            case 'generation_number':
                return $item->generation_number ? (int) $item->generation_number : '–';

            case 'slug':
                return '<code>' . esc_html($item->slug) . '</code>';

            case 'region_label':
                // NEW : region_label = COALESCE(r.name_fr, r.name_en)
                return !empty($item->region_label)
                    ? esc_html($item->region_label)
                    : '–';
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

        check_admin_referer('bulk-pokemon_generations');

        if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
            return;
        }

        if (!function_exists('pokehub_get_table')) {
            return;
        }

        global $wpdb;
        $table = pokehub_get_table('pokemon_generations');

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

        $table_gens    = pokehub_get_table('pokemon_generations');
        // NEW : table des régions Pokémon (multilingue)
        $table_regions = pokehub_get_table('pokemon_regions');

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'generation_number';
        $order   = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $allowed_orderby = ['generation_number', 'label', 'slug'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'generation_number';
        }

        // Mapping de la colonne "label" sur name_fr pour le ORDER BY SQL
        if ($orderby === 'label') {
            $sql_orderby = 'g.name_fr';
        } else {
            // generation_number, slug
            $sql_orderby = 'g.' . $orderby;
        }

        // Recherche
        $search = isset($_REQUEST['s']) ? wp_unslash(trim($_REQUEST['s'])) : '';
        $where  = 'WHERE 1=1';
        $params = [];

        if ($search !== '') {
            // NEW : recherche sur name_fr, name_en et slug
            $where   .= " AND (g.name_fr LIKE %s OR g.name_en LIKE %s OR g.slug LIKE %s)";
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Total items
        $sql_count = "SELECT COUNT(*) FROM {$table_gens} AS g {$where}";
        $total_items = $params
            ? (int) $wpdb->get_var($wpdb->prepare($sql_count, $params))
            : (int) $wpdb->get_var($sql_count);

        // Items
        $sql_items = "
            SELECT 
                g.*,
                COALESCE(g.name_fr, g.name_en) AS ui_label,
                COALESCE(r.name_fr, r.name_en) AS region_label
            FROM {$table_gens} AS g
            LEFT JOIN {$table_regions} AS r ON g.region_id = r.id
            {$where}
            ORDER BY {$sql_orderby} {$order}
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
function poke_hub_pokemon_handle_generations_delete() {
    if (!is_admin()) {
        return;
    }

    if (empty($_GET['page']) || $_GET['page'] !== 'poke-hub-pokemon') {
        return;
    }

    if (empty($_GET['ph_section']) || $_GET['ph_section'] !== 'generations') {
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

    check_admin_referer('poke_hub_delete_generation_' . $id);

    global $wpdb;
    $table = pokehub_get_table('pokemon_generations');
    $wpdb->delete($table, ['id' => $id], ['%d']);

    $redirect = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'generations',
            'ph_msg'     => 'deleted',
        ],
        admin_url('admin.php')
    );

    wp_redirect($redirect);
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_generations_delete');

/**
 * Traitement formulaire Générations (add / update)
 */
function poke_hub_pokemon_handle_generations_form() {
    if (!is_admin()) {
        return;
    }

    if (empty($_POST['poke_hub_pokemon_action'])) {
        return;
    }

    if (empty($_POST['ph_section']) || $_POST['ph_section'] !== 'generations') {
        return;
    }

    $action = sanitize_text_field($_POST['poke_hub_pokemon_action']);
    if (!in_array($action, ['add_generation', 'update_generation'], true)) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('poke_hub_pokemon_form', 'poke_hub_pokemon_nonce');

    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    $redirect_base = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'generations',
        ],
        admin_url('admin.php')
    );

    $id         = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    // NEW : noms multilingues
    $name_fr    = isset($_POST['name_fr']) ? sanitize_text_field($_POST['name_fr']) : '';
    $name_en    = isset($_POST['name_en']) ? sanitize_text_field($_POST['name_en']) : '';

    // Compat rétro : si pas de name_fr / name_en mais ancien champ "name"
    if ($name_fr === '' && $name_en === '' && !empty($_POST['name'])) {
        $fallback = sanitize_text_field($_POST['name']);
        $name_fr  = $fallback; // ou name_en, au choix ; on prend FR ici
    }

    $slug       = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
    $gen_number = isset($_POST['generation_number']) ? (int) $_POST['generation_number'] : 0;
    $region_id  = isset($_POST['region_id']) ? (int) $_POST['region_id'] : 0;

    // Au moins un nom requis
    if ($name_fr === '' && $name_en === '') {
        wp_redirect(add_query_arg('ph_msg', 'missing_name', $redirect_base));
        exit;
    }

    if ($slug === '') {
        $base = $name_en !== '' ? $name_en : $name_fr;
        $slug = sanitize_title($base);
    }

    if ($gen_number < 0) {
        $gen_number = 0;
    }
    if ($region_id < 0) {
        $region_id = 0;
    }

    $table = pokehub_get_table('pokemon_generations');
    $now   = current_time('mysql');

    $data = [
        'slug'              => $slug,
        'name_en'           => $name_en,
        'name_fr'           => $name_fr,
        'generation_number' => $gen_number,
        'region_id'         => $region_id,
    ];
    $format = ['%s', '%s', '%s', '%d', '%d'];

    if ($action === 'add_generation') {
        // created_at peut rester géré par MySQL automatiquement, mais on le force si tu veux
        $data['created_at'] = $now;
        $format[] = '%s';

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
add_action('admin_init', 'poke_hub_pokemon_handle_generations_form');

/**
 * Écran principal de l’onglet "Generations"
 * → utilisé UNIQUEMENT en mode LISTE (add/edit gérés par poke_hub_pokemon_admin_ui())
 */
function poke_hub_pokemon_admin_generations_screen() {

    $list_table = new Poke_Hub_Pokemon_Generations_List_Table();

    // Bulk actions
    $list_table->process_bulk_action();
    $list_table->prepare_items();

    // Notices
    if (!empty($_GET['ph_msg'])) {
        $msg = sanitize_key($_GET['ph_msg']);
        if ($msg === 'saved' || $msg === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Generation saved.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Generation deleted.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'missing_name') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Name is required.', 'poke-hub') . '</p></div>';
        }
    }
    ?>
    <form method="post">
        <input type="hidden" name="page" value="poke-hub-pokemon" />
        <input type="hidden" name="ph_section" value="generations" />
        <?php
        // 🔐 nonce pour les actions groupées
        wp_nonce_field('bulk-pokemon_generations');

        // Search + table (WP_List_Table va générer les select "Actions groupées")
        $list_table->search_box(__('Search generations', 'poke-hub'), 'pokemon-generations');
        $list_table->display();
        ?>
    </form>
    <?php
}
