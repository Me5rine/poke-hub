<?php
// File: modules/pokemon/admin/sections/form-mappings.php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// Formulaire add/edit
require_once POKE_HUB_POKEMON_PATH . '/admin/forms/form-mapping-form.php';

class Poke_Hub_Pokemon_Form_Mappings_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'pokemon_form_mapping',
            'plural'   => 'pokemon_form_mappings',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'               => '<input type="checkbox" />',
            'pokemon_id_proto' => __('Pokémon ID (proto)', 'poke-hub'),
            'form_proto'       => __('Form (proto)', 'poke-hub'),
            'form_slug'        => __('Form slug', 'poke-hub'),
            'variant_label'    => __('Variant label', 'poke-hub'),
            'variant_category' => __('Category', 'poke-hub'),
            'variant_group'    => __('Group', 'poke-hub'),
            'label_suffix'     => __('Label suffix (mapping)', 'poke-hub'),
            'sort_order'       => __('Order', 'poke-hub'),
        ];
    }

    public function get_sortable_columns() {
        return [
            'pokemon_id_proto' => ['pokemon_id_proto', true],
            'form_proto'       => ['form_proto', true],
            'form_slug'        => ['form_slug', true],
            'variant_category' => ['variant_category', true],
            'variant_group'    => ['variant_group', true],
            'label_suffix'     => ['label_suffix', true],
            'sort_order'       => ['sort_order', true],
        ];
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="ids[]" value="%d" />',
            (int) $item->id
        );
    }

    public function column_pokemon_id_proto($item) {
        $edit_url = add_query_arg(
            [
                'page'       => 'poke-hub-pokemon',
                'ph_section' => 'form_mappings',
                'action'     => 'edit',
                'id'         => (int) $item->id,
            ],
            admin_url('admin.php')
        );

        $delete_url = wp_nonce_url(
            add_query_arg(
                [
                    'page'       => 'poke-hub-pokemon',
                    'ph_section' => 'form_mappings',
                    'action'     => 'delete',
                    'id'         => (int) $item->id,
                ],
                admin_url('admin.php')
            ),
            'poke_hub_delete_form_mapping_' . (int) $item->id
        );

        $title = esc_html($item->pokemon_id_proto);

        $actions = [
            'edit'   => sprintf('<a href="%s">%s</a>', esc_url($edit_url), esc_html__('Edit', 'poke-hub')),
            'delete' => sprintf(
                '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($delete_url),
                esc_attr__('Are you sure you want to delete this mapping?', 'poke-hub'),
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

    public function column_form_proto($item) {
        return '<code>' . esc_html($item->form_proto) . '</code>';
    }

    public function column_form_slug($item) {
        if ($item->form_slug === '') {
            return '<span class="description">— ' . esc_html__('Base form', 'poke-hub') . ' —</span>';
        }
        return '<code>' . esc_html($item->form_slug) . '</code>';
    }

    public function column_variant_label($item) {
        if (!empty($item->variant_label)) {
            return esc_html($item->variant_label);
        }
        if ($item->form_slug === '') {
            return '—';
        }
        return '<span class="description">' . esc_html__('(no variant found)', 'poke-hub') . '</span>';
    }

    public function column_variant_category($item) {
        return esc_html($item->variant_category ?: '—');
    }

    public function column_variant_group($item) {
        return esc_html($item->variant_group ?: '—');
    }

    public function column_label_suffix($item) {
        return esc_html($item->label_suffix);
    }

    public function column_sort_order($item) {
        return (int) $item->sort_order;
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

        check_admin_referer('bulk-pokemon_form_mappings');

        if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
            return;
        }

        global $wpdb;
        $table = pokehub_get_table('pokemon_form_mappings');

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

        // IMPORTANT :
        // On ne supprime PAS dans pokemon_form_variants.
        // Une même forme (form_slug) peut être utilisée pour plusieurs mappings
        // ou avoir été créée par l’import Game Master ou via l’écran “Form variants”.
    }

    public function prepare_items() {
        if (!function_exists('pokehub_get_table')) {
            $this->items = [];
            return;
        }

        global $wpdb;
        $table          = pokehub_get_table('pokemon_form_mappings');
        $variants_table = pokehub_get_table('pokemon_form_variants');

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        // Tri
        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'pokemon_id_proto';
        $order   = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $allowed_orderby = [
            'pokemon_id_proto',
            'form_proto',
            'form_slug',
            'variant_category',
            'variant_group',
            'label_suffix',
            'sort_order',
        ];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'pokemon_id_proto';
        }

        // Recherche
        $search = isset($_REQUEST['s']) ? wp_unslash(trim($_REQUEST['s'])) : '';
        $where  = 'WHERE 1=1';
        $params = [];

        if ($search !== '') {
            // On cherche sur les champs principaux du mapping
            $where   .= ' AND (m.pokemon_id_proto LIKE %s OR m.form_proto LIKE %s OR m.form_slug LIKE %s OR m.label_suffix LIKE %s)';
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Total items (sur la table de mapping seule)
        $sql_count   = "SELECT COUNT(*) FROM {$table} m {$where}";
        $total_items = $params
            ? (int) $wpdb->get_var($wpdb->prepare($sql_count, $params))
            : (int) $wpdb->get_var($sql_count);

        // Items avec éventuel LEFT JOIN sur pokemon_form_variants
        if ($variants_table) {
            $sql_items = "
                SELECT 
                    m.*,
                    v.category AS variant_category,
                    v.`group`  AS variant_group,
                    v.label    AS variant_label
                FROM {$table} m
                LEFT JOIN {$variants_table} v
                    ON v.form_slug = m.form_slug
                {$where}
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d
            ";
        } else {
            // Fallback : pas de table variants (dev / install incomplète)
            $sql_items = "
                SELECT 
                    m.*,
                    '' AS variant_category,
                    '' AS variant_group,
                    '' AS variant_label
                FROM {$table} m
                {$where}
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d
            ";
        }

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
 * Traitement formulaire Form Mapping (add / update)
 *
 * Désormais :
 * - on ne gère PLUS category / group ici (c’est le job de pokemon_form_variants)
 * - on ne fait que lier pokemon_id_proto + form_proto à un form_slug existant (ou vide pour la forme de base)
 */
function poke_hub_pokemon_handle_form_mappings_form() {
    if (!is_admin()) {
        return;
    }

    if (empty($_POST['poke_hub_pokemon_action'])) {
        return;
    }

    if (empty($_POST['ph_section']) || $_POST['ph_section'] !== 'form_mappings') {
        return;
    }

    $action = sanitize_text_field($_POST['poke_hub_pokemon_action']);
    if (!in_array($action, ['add_form_mapping', 'update_form_mapping'], true)) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('poke_hub_pokemon_edit_form_mapping');

    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;
    $table = pokehub_get_table('pokemon_form_mappings');

    $redirect_base = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'form_mappings',
        ],
        admin_url('admin.php')
    );

    $id               = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $pokemon_id_proto = isset($_POST['pokemon_id_proto']) ? sanitize_text_field(wp_unslash($_POST['pokemon_id_proto'])) : '';
    $form_proto       = isset($_POST['form_proto']) ? sanitize_text_field(wp_unslash($_POST['form_proto'])) : '';
    $form_slug        = isset($_POST['form_slug']) ? sanitize_title(wp_unslash($_POST['form_slug'])) : '';
    $label_suffix     = isset($_POST['label_suffix']) ? sanitize_text_field(wp_unslash($_POST['label_suffix'])) : '';
    $sort_order       = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;

    // Champs obligatoires (le form_slug peut être vide = forme de base)
    if ($pokemon_id_proto === '' || $form_proto === '') {
        wp_safe_redirect(add_query_arg('ph_msg', 'missing_proto', $redirect_base));
        exit;
    }

    $data = [
        'pokemon_id_proto' => $pokemon_id_proto,
        'form_proto'       => $form_proto,
        'form_slug'        => $form_slug,
        'label_suffix'     => $label_suffix,
        'sort_order'       => $sort_order,
        'flags'            => null,
    ];
    $format = ['%s', '%s', '%s', '%s', '%d', '%s'];

    if ($action === 'add_form_mapping') {
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
add_action('admin_init', 'poke_hub_pokemon_handle_form_mappings_form');

/**
 * Delete simple (action=delete sur une ligne)
 */
function poke_hub_pokemon_handle_form_mappings_delete() {
    if (!is_admin()) {
        return;
    }

    if (empty($_GET['page']) || $_GET['page'] !== 'poke-hub-pokemon') {
        return;
    }

    if (empty($_GET['ph_section']) || $_GET['ph_section'] !== 'form_mappings') {
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

    check_admin_referer('poke_hub_delete_form_mapping_' . $id);

    global $wpdb;
    $table = pokehub_get_table('pokemon_form_mappings');

    $wpdb->delete($table, ['id' => $id], ['%d']);

    // Comme pour le bulk delete, on ne touche PAS à pokemon_form_variants ici.

    $redirect = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'form_mappings',
            'ph_msg'     => 'deleted',
        ],
        admin_url('admin.php')
    );

    wp_redirect($redirect);
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_form_mappings_delete');

/**
 * Écran principal "Form mappings"
 */
function poke_hub_pokemon_admin_form_mappings_screen() {
    $list_table = new Poke_Hub_Pokemon_Form_Mappings_List_Table();

    // Bulk actions
    $list_table->process_bulk_action();
    $list_table->prepare_items();

    // Notices (basées sur ph_msg)
    if (!empty($_GET['ph_msg'])) {
        $msg = sanitize_key($_GET['ph_msg']);
        if ($msg === 'saved' || $msg === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Form mapping saved.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Form mapping deleted.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'missing_proto') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Pokémon ID proto and Form proto are required.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'invalid_id') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid form mapping ID.', 'poke-hub') . '</p></div>';
        }
    }
    ?>
    <form method="post">
        <input type="hidden" name="page" value="poke-hub-pokemon" />
        <input type="hidden" name="ph_section" value="form_mappings" />
        <?php
        // nonce pour les bulk actions
        wp_nonce_field('bulk-pokemon_form_mappings');

        $list_table->search_box(__('Search form mapping', 'poke-hub'), 'pokemon-form-mapping');
        $list_table->display();
        ?>
    </form>
    <?php
}
