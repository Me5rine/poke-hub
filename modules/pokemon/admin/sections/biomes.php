<?php
// modules/pokemon/admin/sections/biomes.php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

require_once POKE_HUB_POKEMON_PATH . '/admin/forms/biome-form.php';

class Poke_Hub_Pokemon_Biomes_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'pokemon_biome',
            'plural'   => 'pokemon_biomes',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'name'        => __('Name', 'poke-hub'),
            'slug'        => __('Slug', 'poke-hub'),
            'images'      => __('Background images', 'poke-hub'),
            'pokemon'     => __('Pokémon', 'poke-hub'),
            'description' => __('Description', 'poke-hub'),
        ];
    }

    public function get_sortable_columns() {
        return [
            'name' => ['name_en', true],
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
                'ph_section' => 'biomes',
                'action'     => 'edit',
                'id'         => (int) $item->id,
            ],
            admin_url('admin.php')
        );

        $delete_url = wp_nonce_url(
            add_query_arg(
                [
                    'page'       => 'poke-hub-pokemon',
                    'ph_section' => 'biomes',
                    'action'     => 'delete',
                    'id'         => (int) $item->id,
                ],
                admin_url('admin.php')
            ),
            'poke_hub_delete_biome_' . (int) $item->id
        );

        $fr = isset($item->name_fr) ? (string) $item->name_fr : '';
        $en = isset($item->name_en) ? (string) $item->name_en : '';
        $title = $fr !== '' ? $fr : $en;
        if ($fr !== '' && $en !== '' && $fr !== $en) {
            $subtitle = '<br /><span class="description">' . esc_html($en) . '</span>';
        } else {
            $subtitle = '';
        }

        $actions = [
            'edit'   => sprintf('<a href="%s">%s</a>', esc_url($edit_url), esc_html__('Edit', 'poke-hub')),
            'delete' => sprintf(
                '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($delete_url),
                esc_attr__('Are you sure you want to delete this biome?', 'poke-hub'),
                esc_html__('Delete', 'poke-hub')
            ),
        ];

        return sprintf(
            '<strong><a href="%1$s">%2$s</a></strong>%3$s %4$s',
            esc_url($edit_url),
            esc_html($title),
            $subtitle,
            $this->row_actions($actions)
        );
    }

    public function column_slug($item) {
        return '<code>' . esc_html($item->slug) . '</code>';
    }

    public function column_images($item) {
        if (!function_exists('poke_hub_pokemon_get_biome_image_urls')) {
            return '&mdash;';
        }
        $urls = poke_hub_pokemon_get_biome_image_urls((int) $item->id);
        if (empty($urls)) {
            return '&mdash;';
        }
        $first = $urls[0];
        $n = count($urls);
        $html = sprintf(
            '<img src="%1$s" alt="" style="width:48px;height:48px;object-fit:contain;border:1px solid #ddd;" />',
            esc_url($first)
        );
        if ($n > 1) {
            $html .= ' <span class="description">(' . sprintf(esc_html__('%d images', 'poke-hub'), $n) . ')</span>';
        }
        return $html;
    }

    public function column_pokemon($item) {
        global $wpdb;

        $links_table = pokehub_get_table('pokemon_biome_pokemon_links');
        $pokemon_table = pokehub_get_table('pokemon');

        if (!$links_table || !$pokemon_table) {
            return '&mdash;';
        }

        $pokemon_list = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.id, p.dex_number, p.name_fr, p.name_en
                 FROM {$links_table} l
                 INNER JOIN {$pokemon_table} p ON p.id = l.pokemon_id
                 WHERE l.biome_id = %d
                 ORDER BY p.dex_number ASC, p.name_fr ASC
                 LIMIT 5",
                (int) $item->id
            )
        );

        if (empty($pokemon_list)) {
            return '&mdash;';
        }

        $names = [];
        foreach ($pokemon_list as $p) {
            $name = !empty($p->name_fr) ? $p->name_fr : $p->name_en;
            $names[] = sprintf('#%d %s', $p->dex_number, esc_html($name));
        }

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$links_table} WHERE biome_id = %d",
                (int) $item->id
            )
        );

        $output = implode(', ', $names);
        if ($count > 5) {
            $output .= sprintf(' <span class="description">(+%d)</span>', $count - 5);
        }

        return $output;
    }

    public function column_description($item) {
        $d = isset($item->description) ? (string) $item->description : '';
        if ($d === '') {
            return '&mdash;';
        }
        $plain = wp_strip_all_tags($d);
        if (function_exists('mb_substr')) {
            $excerpt = mb_strlen($plain) > 120 ? mb_substr($plain, 0, 120) . '…' : $plain;
        } else {
            $excerpt = strlen($plain) > 120 ? substr($plain, 0, 120) . '…' : $plain;
        }
        return esc_html($excerpt);
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
        if ('bulk_delete' !== $this->current_action()) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('bulk-pokemon_biomes');

        if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
            return;
        }

        if (!function_exists('pokehub_get_table')) {
            return;
        }

        global $wpdb;
        $table = pokehub_get_table('pokemon_biomes');
        $img_table = pokehub_get_table('pokemon_biome_images');
        $links_table = pokehub_get_table('pokemon_biome_pokemon_links');

        $ids = array_map('intval', $_POST['ids']);
        $ids = array_filter($ids);

        if (!$ids) {
            return;
        }

        $in = implode(',', array_fill(0, count($ids), '%d'));

        $wpdb->query($wpdb->prepare("DELETE FROM {$links_table} WHERE biome_id IN ($in)", $ids));
        $wpdb->query($wpdb->prepare("DELETE FROM {$img_table} WHERE biome_id IN ($in)", $ids));
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN ($in)", $ids));
    }

    public function prepare_items() {
        if (!function_exists('pokehub_get_table')) {
            $this->items = [];
            return;
        }

        global $wpdb;
        $table = pokehub_get_table('pokemon_biomes');

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'name_en';
        $order   = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $allowed_orderby = ['name_en', 'name_fr', 'slug'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'name_en';
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
 * Formulaire biome (POST)
 */
function poke_hub_pokemon_handle_biomes_form() {
    if (!is_admin()) {
        return;
    }

    if (empty($_POST['poke_hub_pokemon_action'])) {
        return;
    }

    if (empty($_POST['ph_section']) || $_POST['ph_section'] !== 'biomes') {
        return;
    }

    $action = sanitize_text_field($_POST['poke_hub_pokemon_action']);
    if (!in_array($action, ['add_biome', 'update_biome'], true)) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('poke_hub_pokemon_edit_biome');

    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;
    $table = pokehub_get_table('pokemon_biomes');

    $redirect_base = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'biomes',
        ],
        admin_url('admin.php')
    );

    $id          = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $name_en     = isset($_POST['name_en']) ? sanitize_text_field(wp_unslash($_POST['name_en'])) : '';
    $name_fr     = isset($_POST['name_fr']) ? sanitize_text_field(wp_unslash($_POST['name_fr'])) : '';
    $slug        = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
    $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';

    $image_urls = isset($_POST['biome_image_urls']) && is_array($_POST['biome_image_urls'])
        ? array_map(static function ($u) {
            return esc_url_raw(trim((string) wp_unslash($u)));
        }, $_POST['biome_image_urls'])
        : [];

    $pokemon_ids = isset($_POST['biome_pokemon_ids']) && is_array($_POST['biome_pokemon_ids'])
        ? array_map('intval', $_POST['biome_pokemon_ids'])
        : [];

    if ($name_en === '' || $name_fr === '') {
        wp_safe_redirect(add_query_arg('ph_msg', 'missing_names', $redirect_base));
        exit;
    }

    if ($slug === '') {
        $slug = sanitize_title($name_en);
    }

    if ($slug === '') {
        wp_safe_redirect(add_query_arg('ph_msg', 'invalid_slug', $redirect_base));
        exit;
    }

    $slug_conflict = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$table} WHERE slug = %s AND id != %d",
            $slug,
            $action === 'update_biome' ? $id : 0
        )
    );
    if ($slug_conflict > 0) {
        wp_safe_redirect(add_query_arg('ph_msg', 'slug_exists', $redirect_base));
        exit;
    }

    $data = [
        'slug'        => $slug,
        'name_en'     => $name_en,
        'name_fr'     => $name_fr,
        'description' => $description,
    ];
    $format = ['%s', '%s', '%s', '%s'];

    if ($action === 'add_biome') {
        $wpdb->insert($table, $data, $format);
        $biome_id = (int) $wpdb->insert_id;

        if ($biome_id > 0 && function_exists('poke_hub_pokemon_sync_biome_images')) {
            poke_hub_pokemon_sync_biome_images($biome_id, $image_urls);
        }
        if ($biome_id > 0 && function_exists('poke_hub_pokemon_sync_biome_pokemon_links')) {
            poke_hub_pokemon_sync_biome_pokemon_links($biome_id, $pokemon_ids);
        }

        wp_safe_redirect(add_query_arg('ph_msg', 'saved', $redirect_base));
        exit;
    }

    if ($id <= 0) {
        wp_safe_redirect(add_query_arg('ph_msg', 'invalid_id', $redirect_base));
        exit;
    }

    $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);

    if (function_exists('poke_hub_pokemon_sync_biome_images')) {
        poke_hub_pokemon_sync_biome_images($id, $image_urls);
    }
    if (function_exists('poke_hub_pokemon_sync_biome_pokemon_links')) {
        poke_hub_pokemon_sync_biome_pokemon_links($id, $pokemon_ids);
    }

    wp_safe_redirect(add_query_arg('ph_msg', 'updated', $redirect_base));
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_biomes_form');

/**
 * Suppression unitaire
 */
function poke_hub_pokemon_handle_biomes_delete() {
    if (!is_admin()) {
        return;
    }

    if (empty($_GET['page']) || $_GET['page'] !== 'poke-hub-pokemon') {
        return;
    }

    if (empty($_GET['ph_section']) || $_GET['ph_section'] !== 'biomes') {
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

    check_admin_referer('poke_hub_delete_biome_' . $id);

    global $wpdb;
    $table = pokehub_get_table('pokemon_biomes');
    $img_table = pokehub_get_table('pokemon_biome_images');
    $links_table = pokehub_get_table('pokemon_biome_pokemon_links');

    $wpdb->delete($links_table, ['biome_id' => $id], ['%d']);
    $wpdb->delete($img_table, ['biome_id' => $id], ['%d']);
    $wpdb->delete($table, ['id' => $id], ['%d']);

    wp_safe_redirect(
        add_query_arg(
            [
                'page'       => 'poke-hub-pokemon',
                'ph_section' => 'biomes',
                'ph_msg'     => 'deleted',
            ],
            admin_url('admin.php')
        )
    );
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_biomes_delete');

/**
 * Liste biomes
 */
function poke_hub_pokemon_admin_biomes_screen() {
    $list_table = new Poke_Hub_Pokemon_Biomes_List_Table();

    $list_table->process_bulk_action();
    $list_table->prepare_items();

    if (!empty($_GET['ph_msg'])) {
        $msg = sanitize_key($_GET['ph_msg']);
        if ($msg === 'saved' || $msg === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Biome saved.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Biome deleted.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'missing_names') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('English and French names are required.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'invalid_slug') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Could not build a valid slug.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'slug_exists') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('This slug is already used by another biome.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'invalid_id') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid biome ID.', 'poke-hub') . '</p></div>';
        }
    }
    ?>
    <form method="post">
        <input type="hidden" name="page" value="poke-hub-pokemon" />
        <input type="hidden" name="ph_section" value="biomes" />
        <?php
        wp_nonce_field('bulk-pokemon_biomes');

        $list_table->search_box(__('Search biomes', 'poke-hub'), 'pokemon-biome');
        $list_table->display();
        ?>
    </form>
    <?php
}
