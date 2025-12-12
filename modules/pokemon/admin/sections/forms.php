<?php
// File: modules/pokemon/admin/sections/forms.php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// Formulaire add/edit
require_once POKE_HUB_POKEMON_PATH . '/admin/forms/form-form.php';

/**
 * Liste des formes globales (pokemon_form_variants).
 */
class Poke_Hub_Pokemon_Forms_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'pokemon_form',
            'plural'   => 'pokemon_forms',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'               => '<input type="checkbox" />',
            'form_slug'        => __('Form slug', 'poke-hub'),
            'label'            => __('Label', 'poke-hub'),
            'category'         => __('Category', 'poke-hub'),
            'group_key'        => __('Group key', 'poke-hub'),
            'parent_form_slug' => __('Parent form', 'poke-hub'),
        ];
    }

    public function get_sortable_columns() {
        return [
            'form_slug'        => ['form_slug', true],
            'label'            => ['label', true],
            'category'         => ['category', true],
            'group_key'        => ['group_key', true],
            'parent_form_slug' => ['parent_form_slug', true],
        ];
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="ids[]" value="%d" />',
            (int) $item->id
        );
    }

    public function column_form_slug($item) {
        $edit_url = add_query_arg(
            [
                'page'       => 'poke-hub-pokemon',
                'ph_section' => 'forms',
                'action'     => 'edit',
                'id'         => (int) $item->id,
            ],
            admin_url('admin.php')
        );

        $delete_url = wp_nonce_url(
            add_query_arg(
                [
                    'page'       => 'poke-hub-pokemon',
                    'ph_section' => 'forms',
                    'action'     => 'delete',
                    'id'         => (int) $item->id,
                ],
                admin_url('admin.php')
            ),
            'poke_hub_delete_form_' . (int) $item->id
        );

        $title = esc_html($item->form_slug);

        $actions = [
            'edit'   => sprintf('<a href="%s">%s</a>', esc_url($edit_url), esc_html__('Edit', 'poke-hub')),
            'delete' => sprintf(
                '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($delete_url),
                esc_attr__('Are you sure you want to delete this form?', 'poke-hub'),
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

    public function column_label($item) {
        return esc_html($item->label);
    }

    public function column_category($item) {
        return esc_html($item->category ?: 'normal');
    }

    public function column_group_key($item) {
        return esc_html($item->group_key ?: '—');
    }

    public function column_parent_form_slug($item) {
        return $item->parent_form_slug !== ''
            ? '<code>' . esc_html($item->parent_form_slug) . '</code>'
            : '—';
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

        check_admin_referer('bulk-pokemon_forms');

        if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
            return;
        }

        global $wpdb;
        $table = pokehub_get_table('pokemon_form_variants');
        if (!$table) {
            return;
        }

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

        // NOTE :
        // On ne touche pas à pokemon_form_mappings ici.
        // Des mappings peuvent pointer vers un form_slug supprimé : ce sera à gérer
        // plus tard (ex: contrôle dans le mapping ou nettoyage séparé).
    }

    public function prepare_items() {
        if (!function_exists('pokehub_get_table')) {
            $this->items = [];
            return;
        }

        global $wpdb;
        $table = pokehub_get_table('pokemon_form_variants');
        if (!$table) {
            $this->items = [];
            return;
        }

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        // Tri
        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'form_slug';
        $order   = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $allowed_orderby = [
            'form_slug',
            'label',
            'category',
            'group_key',
            'parent_form_slug',
        ];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'form_slug';
        }

        // Recherche
        $search = isset($_REQUEST['s']) ? wp_unslash(trim($_REQUEST['s'])) : '';
        $where  = 'WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $where   .= ' AND (form_slug LIKE %s OR label LIKE %s OR category LIKE %s OR group_key LIKE %s)';
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Total items
        $sql_count   = "SELECT COUNT(*) FROM {$table} {$where}";
        $total_items = $params
            ? (int) $wpdb->get_var($wpdb->prepare($sql_count, $params))
            : (int) $wpdb->get_var($sql_count);

        // Items
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
 * Traitement formulaire Forms (add / update).
 *
 * Ici on gère :
 * - validation/sanitize
 * - appel à l'upsert (pour slug/category/group/label/extra)
 * - mise à jour de parent_form_slug
 */
function poke_hub_pokemon_handle_forms_form() {
    if (!is_admin()) {
        return;
    }

    if (empty($_POST['poke_hub_pokemon_action'])) {
        return;
    }

    if (empty($_POST['ph_section']) || $_POST['ph_section'] !== 'forms') {
        return;
    }

    $action = sanitize_text_field($_POST['poke_hub_pokemon_action']);
    if (!in_array($action, ['add_form', 'update_form'], true)) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('poke_hub_pokemon_edit_form');

    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;
    $table = pokehub_get_table('pokemon_form_variants');
    if (!$table) {
        return;
    }

    $redirect_base = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'forms',
        ],
        admin_url('admin.php')
    );

    $id               = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $form_slug_raw    = isset($_POST['form_slug']) ? wp_unslash($_POST['form_slug']) : '';
    $label_raw        = isset($_POST['label']) ? wp_unslash($_POST['label']) : '';
    $category_raw     = isset($_POST['category']) ? wp_unslash($_POST['category']) : '';
    $group_key_raw    = isset($_POST['group_key']) ? wp_unslash($_POST['group_key']) : '';
    $parent_slug_raw  = isset($_POST['parent_form_slug']) ? wp_unslash($_POST['parent_form_slug']) : '';

    $form_slug = sanitize_title($form_slug_raw);
    $label     = sanitize_text_field($label_raw);

    $category  = sanitize_key($category_raw);
    if ($category === '') {
        $category = 'normal';
    }

    // Normalisation du group_key : minuscules, [a-z0-9_-]
    $group_key = strtolower((string) $group_key_raw);
    $group_key = preg_replace('/[^a-z0-9_\-]/', '_', $group_key);

    $parent_form_slug = sanitize_title($parent_slug_raw);
    if ($parent_form_slug === $form_slug) {
        // On évite un parent qui pointe sur lui-même
        $parent_form_slug = '';
    }

    // Champs obligatoires
    if ($form_slug === '' || $label === '') {
        wp_safe_redirect(add_query_arg('ph_msg', 'missing_fields', $redirect_base));
        exit;
    }

    // Extra minimal : on marque que ça vient du form admin.
    $extra = [
        'source' => 'manual-admin',
    ];

    // On laisse l'upsert s'occuper de :
    // - INSERT si form_slug inexistant
    // - UPDATE si déjà présent
    if (!function_exists('poke_hub_pokemon_upsert_form_variant')) {
        // fallback : pas d’upsert => on fait un insert/update basique
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE form_slug = %s LIMIT 1",
                $form_slug
            )
        );

        $extra_json = wp_json_encode($extra);

        if ($existing) {
            $wpdb->update(
                $table,
                [
                    'category'  => $category,
                    'group_key' => $group_key,
                    'label'     => $label,
                    'extra'     => $extra_json,
                ],
                ['id' => (int) $existing->id],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );
            $variant_id = (int) $existing->id;
        } else {
            $wpdb->insert(
                $table,
                [
                    'form_slug'        => $form_slug,
                    'category'         => $category,
                    'group_key'        => $group_key,
                    'parent_form_slug' => '',
                    'label'            => $label,
                    'extra'            => $extra_json,
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s']
            );
            $variant_id = (int) $wpdb->insert_id;
        }
    } else {
        $variant_id = poke_hub_pokemon_upsert_form_variant(
            $form_slug,
            $category,
            $group_key,
            $label,
            $extra
        );
    }

    if ($variant_id > 0) {
        // On applique ensuite le parent_form_slug (l’upsert ne le touche jamais)
        $wpdb->update(
            $table,
            [
                'parent_form_slug' => $parent_form_slug,
            ],
            ['id' => $variant_id],
            ['%s'],
            ['%d']
        );
    }

    $msg = ($action === 'add_form') ? 'saved' : 'updated';
    wp_safe_redirect(add_query_arg('ph_msg', $msg, $redirect_base));
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_forms_form');

/**
 * Delete simple (action=delete sur une ligne).
 */
function poke_hub_pokemon_handle_forms_delete() {
    if (!is_admin()) {
        return;
    }

    if (empty($_GET['page']) || $_GET['page'] !== 'poke-hub-pokemon') {
        return;
    }

    if (empty($_GET['ph_section']) || $_GET['ph_section'] !== 'forms') {
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

    check_admin_referer('poke_hub_delete_form_' . $id);

    global $wpdb;
    $table = pokehub_get_table('pokemon_form_variants');
    if (!$table) {
        return;
    }

    $wpdb->delete($table, ['id' => $id], ['%d']);

    // On laisse pokemon_form_mappings tel quel : si des mappings pointent sur ce form_slug,
    // ce sera visible dans la liste des mappings.

    $redirect = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'forms',
            'ph_msg'     => 'deleted',
        ],
        admin_url('admin.php')
    );

    wp_redirect($redirect);
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_forms_delete');

/**
 * Écran principal "Forms" (pokemon_form_variants).
 */
function poke_hub_pokemon_admin_forms_screen() {
    $list_table = new Poke_Hub_Pokemon_Forms_List_Table();

    // Bulk actions
    $list_table->process_bulk_action();
    $list_table->prepare_items();

    // Notices (basées sur ph_msg)
    if (!empty($_GET['ph_msg'])) {
        $msg = sanitize_key($_GET['ph_msg']);
        if ($msg === 'saved' || $msg === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Form saved.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Form deleted.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'missing_fields') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Form slug and label are required.', 'poke-hub') . '</p></div>';
        }
    }
    ?>
    <form method="post">
        <input type="hidden" name="page" value="poke-hub-pokemon" />
        <input type="hidden" name="ph_section" value="forms" />
        <?php
        // nonce pour les bulk actions
        wp_nonce_field('bulk-pokemon_forms');

        $list_table->search_box(__('Search form', 'poke-hub'), 'pokemon-form-variant');
        $list_table->display();
        ?>
    </form>
    <?php
}
