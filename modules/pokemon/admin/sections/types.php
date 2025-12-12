<?php
// modules/pokemon/admin/sections/types.php

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
 * On inclut le formulaire dédié aux types
 */
require_once POKE_HUB_POKEMON_PATH . '/admin/forms/type-form.php';

/**
 * List table des types Pokémon
 */
class Poke_Hub_Pokemon_Types_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'pokemon_type',
            'plural'   => 'pokemon_types',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            // On garde "name" comme identifiant de colonne, mais on affiche name_fr/name_en derrière.
            'name'       => __('Name', 'poke-hub'),
            'slug'       => __('Slug', 'poke-hub'),
            'color'      => __('Color', 'poke-hub'),
            'icon'       => __('Icon', 'poke-hub'),
            'sort_order' => __('Order', 'poke-hub'),
        ];
    }

    public function get_sortable_columns() {
        return [
            // L'orderby "name" côté UI correspondra à la colonne name_fr en base.
            'name'       => ['name_fr', true],
            'slug'       => ['slug', true],
            'sort_order' => ['sort_order', false],
        ];
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="ids[]" value="%d" />',
            (int) $item->id
        );
    }

    public function column_name($item) {
        $edit_url = add_query_arg([
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'types',
            'action'     => 'edit',
            'id'         => (int) $item->id,
        ], admin_url('admin.php'));

        $delete_url = wp_nonce_url(
            add_query_arg([
                'page'       => 'poke-hub-pokemon',
                'ph_section' => 'types',
                'action'     => 'delete',
                'id'         => (int) $item->id,
            ], admin_url('admin.php')),
            'poke_hub_delete_type_' . (int) $item->id
        );

        // NEW : on affiche le nom FR si dispo, sinon EN.
        $name_fr = isset($item->name_fr) ? (string) $item->name_fr : '';
        $name_en = isset($item->name_en) ? (string) $item->name_en : '';
        $display_name = $name_fr !== '' ? $name_fr : $name_en;

        $title = esc_html($display_name);

        $actions = [
            'edit'   => sprintf(
                '<a href="%s">%s</a>',
                esc_url($edit_url),
                esc_html__('Edit', 'poke-hub')
            ),
            'delete' => sprintf(
                '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($delete_url),
                esc_attr__('Are you sure you want to delete this type?', 'poke-hub'),
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

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'slug':
                return '<code>' . esc_html($item->slug) . '</code>';

            case 'color':
                $color = trim((string) $item->color);
                if ($color === '') {
                    return '–';
                }

                $swatch = sprintf(
                    '<span style="display:inline-block;width:16px;height:16px;border-radius:3px;vertical-align:middle;margin-right:4px;background:%s;"></span>',
                    esc_attr($color)
                );

                return $swatch . '<code>' . esc_html($color) . '</code>';

            case 'icon':
                if (!empty($item->icon)) {
                    $url = esc_url($item->icon);

                    return sprintf(
                        '<img src="%s" alt="" style="width:40px;height:40px;object-fit:contain;border:1px solid #ddd;padding:2px;border-radius:4px;background:#fff;" />',
                        $url
                    );
                }
                return '–';

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

        check_admin_referer('bulk-pokemon_types');

        if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
            return;
        }

        if (!function_exists('pokehub_get_table')) {
            return;
        }

        global $wpdb;
        $table = pokehub_get_table('pokemon_types');

        $ids = array_map('intval', $_POST['ids']);
        $ids = array_filter($ids);

        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '%d'));

            // Delete types
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE id IN ($in)",
                    $ids
                )
            );

            // 🔹 Nettoyage des liaisons type ↔ météo
            $link_table = pokehub_get_table('pokemon_type_weather_links');
            if ($link_table) {
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$link_table} WHERE type_id IN ($in)",
                        $ids
                    )
                );
            }
        }
    }

    public function prepare_items() {
        if (!function_exists('pokehub_get_table')) {
            $this->items = [];
            return;
        }

        global $wpdb;

        $table_types = pokehub_get_table('pokemon_types');

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'sort_order';
        $order   = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        // L'UI enverra "name", on le mappe vers la colonne name_fr.
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
            // NEW : recherche sur name_en, name_fr et slug
            $where   .= " AND (t.name_en LIKE %s OR t.name_fr LIKE %s OR t.slug LIKE %s)";
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Total items
        $sql_count = "SELECT COUNT(*) FROM {$table_types} AS t {$where}";
        $total_items = $params
            ? (int) $wpdb->get_var($wpdb->prepare($sql_count, $params))
            : (int) $wpdb->get_var($sql_count);

        // Items
        $sql_items = "
            SELECT t.*
            FROM {$table_types} AS t
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
function poke_hub_pokemon_handle_types_delete() {
    if (!is_admin()) {
        return;
    }

    if (empty($_GET['page']) || $_GET['page'] !== 'poke-hub-pokemon') {
        return;
    }

    if (empty($_GET['ph_section']) || $_GET['ph_section'] !== 'types') {
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

    check_admin_referer('poke_hub_delete_type_' . $id);

    global $wpdb;
    $table = pokehub_get_table('pokemon_types');
    $wpdb->delete($table, ['id' => $id], ['%d']);

    // 🔹 supprimer aussi les liaisons type ↔ météo
    $link_table = pokehub_get_table('pokemon_type_weather_links');
    if ($link_table) {
        $wpdb->delete(
            $link_table,
            ['type_id' => $id],
            ['%d']
        );
    }

    $redirect = add_query_arg([
        'page'       => 'poke-hub-pokemon',
        'ph_section' => 'types',
        'ph_msg'     => 'deleted',
    ], admin_url('admin.php'));

    wp_redirect($redirect);
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_types_delete');

/**
 * Traitement formulaire Types (add / update)
 */
function poke_hub_pokemon_handle_types_form() {
    if (!is_admin()) {
        return;
    }

    if (empty($_POST['poke_hub_pokemon_action'])) {
        return;
    }

    if (empty($_POST['ph_section']) || $_POST['ph_section'] !== 'types') {
        return;
    }

    $action = sanitize_text_field($_POST['poke_hub_pokemon_action']);
    if (!in_array($action, ['add_type', 'update_type'], true)) {
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

    $redirect_base = add_query_arg([
        'page'       => 'poke-hub-pokemon',
        'ph_section' => 'types',
    ], admin_url('admin.php'));

    $id         = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    // NEW : noms multilingues
    $name_en    = isset($_POST['name_en']) ? sanitize_text_field($_POST['name_en']) : '';
    $name_fr    = isset($_POST['name_fr']) ? sanitize_text_field($_POST['name_fr']) : '';

    // Compatibilité éventuelle avec un ancien champ 'name'
    if ($name_en === '' && $name_fr === '' && !empty($_POST['name'])) {
        $fallback_name = sanitize_text_field($_POST['name']);
        $name_en = $fallback_name;
    }

    $slug       = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
    $color      = isset($_POST['color']) ? sanitize_text_field($_POST['color']) : '';
    $icon       = isset($_POST['icon']) ? sanitize_text_field($_POST['icon']) : '';
    $sort_order = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;

    // 🔹 NEW : météos sélectionnées
    $weather_ids = [];
    if (!empty($_POST['weather_ids']) && is_array($_POST['weather_ids'])) {
        $weather_ids = array_map('intval', $_POST['weather_ids']);
        $weather_ids = array_filter($weather_ids, function ($v) {
            return $v > 0;
        });
        $weather_ids = array_values(array_unique($weather_ids));
    }

    // Au moins un nom requis (EN ou FR)
    if ($name_en === '' && $name_fr === '') {
        wp_redirect(add_query_arg('ph_msg', 'missing_name', $redirect_base));
        exit;
    }

    if ($slug === '') {
        // On génère le slug à partir du EN si dispo, sinon FR.
        $base = $name_en !== '' ? $name_en : $name_fr;
        $slug = sanitize_title($base);
    }

    $table = pokehub_get_table('pokemon_types');

    $data = [
        'slug'       => $slug,
        'name_en'    => $name_en,
        'name_fr'    => $name_fr,
        'color'      => $color,
        'icon'       => $icon,
        'sort_order' => $sort_order,
    ];
    $format = ['%s', '%s', '%s', '%s', '%s', '%d'];

    if ($action === 'add_type') {
        $wpdb->insert($table, $data, $format);
        $type_id = (int) $wpdb->insert_id;

        // 🔹 sync météo ↔ type
        if ($type_id > 0) {
            poke_hub_pokemon_sync_type_weathers($type_id, $weather_ids);
        }

        wp_redirect(add_query_arg('ph_msg', 'saved', $redirect_base));
        exit;
    }

    // update
    if ($id <= 0) {
        wp_redirect(add_query_arg('ph_msg', 'invalid_id', $redirect_base));
        exit;
    }

    $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);

    // 🔹 sync météo ↔ type
    poke_hub_pokemon_sync_type_weathers($id, $weather_ids);

    $redirect = add_query_arg([
        'ph_msg' => 'updated',
    ], $redirect_base);

    wp_redirect($redirect);
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_types_form');

/**
 * 🔹 Synchronise les météos qui boostent un type donné.
 *
 * @param int   $type_id
 * @param int[] $weather_ids
 */
function poke_hub_pokemon_sync_type_weathers(int $type_id, array $weather_ids) {
    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    $link_table = pokehub_get_table('pokemon_type_weather_links');
    if (!$link_table) {
        return;
    }

    $type_id = (int) $type_id;
    if ($type_id <= 0) {
        return;
    }

    // Nettoyage / dedup
    $weather_ids = array_map('intval', $weather_ids);
    $weather_ids = array_filter($weather_ids, function ($v) {
        return $v > 0;
    });
    $weather_ids = array_values(array_unique($weather_ids));

    // Efface toutes les anciennes liaisons pour ce type
    $wpdb->delete(
        $link_table,
        ['type_id' => $type_id],
        ['%d']
    );

    if (empty($weather_ids)) {
        return;
    }

    // Réinsère les nouvelles liaisons
    foreach ($weather_ids as $wid) {
        $wpdb->insert(
            $link_table,
            [
                'type_id'    => $type_id,
                'weather_id' => $wid,
            ],
            ['%d', '%d']
        );
    }
}

/**
 * Écran principal de l’onglet "Types"
 * → utilisé UNIQUEMENT en mode LISTE (add/edit gérés par poke_hub_pokemon_admin_ui())
 */
function poke_hub_pokemon_admin_types_screen() {

    $list_table = new Poke_Hub_Pokemon_Types_List_Table();

    // Bulk actions
    $list_table->process_bulk_action();
    $list_table->prepare_items();

    // Notices
    if (!empty($_GET['ph_msg'])) {
        $msg = sanitize_key($_GET['ph_msg']);
        if ($msg === 'saved' || $msg === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Type saved.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Type deleted.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'missing_name') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Name is required.', 'poke-hub') . '</p></div>';
        }
    }
    ?>
    <form method="post">
        <input type="hidden" name="page" value="poke-hub-pokemon" />
        <input type="hidden" name="ph_section" value="types" />

        <?php
        // 🔐 nonce pour les actions groupées (doit matcher check_admin_referer('bulk-pokemon_types'))
        wp_nonce_field('bulk-pokemon_types');

        // Search + table (WP_List_Table génère les selects "Actions groupées")
        $list_table->search_box(__('Search types', 'poke-hub'), 'pokemon-types');
        $list_table->display();
        ?>
    </form>
    <?php
}
