<?php
// modules/pokemon/admin/sections/backgrounds.php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// Formulaire add/edit
require_once POKE_HUB_POKEMON_PATH . '/admin/forms/background-form.php';

class Poke_Hub_Pokemon_Backgrounds_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'pokemon_background',
            'plural'   => 'pokemon_backgrounds',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'        => '<input type="checkbox" />',
            'title'     => __('Title', 'poke-hub'),
            'slug'      => __('Slug', 'poke-hub'),
            'image'     => __('Image', 'poke-hub'),
            'event'     => __('Event', 'poke-hub'),
            'pokemon'   => __('Pokémon', 'poke-hub'),
        ];
    }

    public function get_sortable_columns() {
        return [
            'title' => ['title', true],
            'slug'  => ['slug', true],
        ];
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="ids[]" value="%d" />',
            (int) $item->id
        );
    }

    public function column_title($item) {
        $edit_url = add_query_arg(
            [
                'page'       => 'poke-hub-pokemon',
                'ph_section' => 'backgrounds',
                'action'     => 'edit',
                'id'         => (int) $item->id,
            ],
            admin_url('admin.php')
        );

        $delete_url = wp_nonce_url(
            add_query_arg(
                [
                    'page'       => 'poke-hub-pokemon',
                    'ph_section' => 'backgrounds',
                    'action'     => 'delete',
                    'id'         => (int) $item->id,
                ],
                admin_url('admin.php')
            ),
            'poke_hub_delete_background_' . (int) $item->id
        );

        $title = esc_html($item->title);

        // URL de la page publique du fond
        $view_url = '';
        if (!empty($item->slug) && function_exists('pokehub_get_background_url')) {
            $view_url = pokehub_get_background_url($item->slug);
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
        
        $actions['edit'] = sprintf('<a href="%s">%s</a>', esc_url($edit_url), esc_html__('Edit', 'poke-hub'));
        
        $actions['delete'] = sprintf(
            '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
            esc_url($delete_url),
            esc_attr__('Are you sure you want to delete this background?', 'poke-hub'),
            esc_html__('Delete', 'poke-hub')
        );

        return sprintf(
            '<strong><a href="%1$s">%2$s</a></strong> %3$s',
            esc_url($edit_url),
            $title,
            $this->row_actions($actions)
        );
    }

    public function column_slug($item) {
        return '<code>' . esc_html($item->slug) . '</code>';
    }

    public function column_image($item) {
        $extra = [];
        if (!empty($item->extra)) {
            $decoded = json_decode($item->extra, true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }
        $url = $item->image_url ?? ($extra['image_url'] ?? '');

        if (!$url) {
            return '&mdash;';
        }

        return sprintf(
            '<img src="%1$s" alt="" style="width:64px;height:64px;object-fit:contain;border:1px solid #ddd;" />',
            esc_url($url)
        );
    }

    public function column_event($item) {
        if (empty($item->event_id) || empty($item->event_type)) {
            return '&mdash;';
        }

        global $wpdb;
        
        $event_type = (string) $item->event_type;
        $event_id = (int) $item->event_id;
        
        // Récupérer le titre de l'événement depuis la table special_events
        $event_title = '';
        $events_table = pokehub_get_table('special_events');
        
        if ($events_table) {
            $event_row = $wpdb->get_row(
                $wpdb->prepare("SELECT title FROM {$events_table} WHERE id = %d", $event_id)
            );
            if ($event_row && !empty($event_row->title)) {
                $event_title = $event_row->title;
            }
        }
        
        // Si pas de titre trouvé, fallback sur l'ancien affichage
        if (empty($event_title)) {
            $event_title = sprintf(
                '%s #%d',
                ucfirst(str_replace('_', ' ', $event_type)),
                $event_id
            );
        }
        
        // Afficher le titre avec le type en sous-titre
        return sprintf(
            '%s<br><small style="color:#666;">(%s)</small>',
            esc_html($event_title),
            esc_html(ucfirst(str_replace('_', ' ', $event_type)))
        );
    }

    public function column_pokemon($item) {
        global $wpdb;

        $links_table = pokehub_get_table('pokemon_background_pokemon_links');
        $pokemon_table = pokehub_get_table('pokemon');

        if (!$links_table || !$pokemon_table) {
            return '&mdash;';
        }

        $pokemon_list = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.id, p.dex_number, p.name_fr, p.name_en
                 FROM {$links_table} l
                 INNER JOIN {$pokemon_table} p ON p.id = l.pokemon_id
                 WHERE l.background_id = %d
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

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$links_table} WHERE background_id = %d",
                (int) $item->id
            )
        );

        $output = implode(', ', $names);
        if ($count > 5) {
            $output .= sprintf(' <span class="description">(+%d)</span>', $count - 5);
        }

        return $output;
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

        check_admin_referer('bulk-pokemon_backgrounds');

        if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
            return;
        }

        global $wpdb;
        $table = pokehub_get_table('pokemon_backgrounds');
        $links_table = pokehub_get_table('pokemon_background_pokemon_links');

        $ids = array_map('intval', $_POST['ids']);
        $ids = array_filter($ids);

        if (!$ids) {
            return;
        }

        $in = implode(',', array_fill(0, count($ids), '%d'));

        // Supprimer les liens d'abord
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$links_table} WHERE background_id IN ($in)",
                $ids
            )
        );

        // Puis supprimer les backgrounds
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE id IN ($in)",
                $ids
            )
        );
    }

    public function prepare_items() {
        if (!function_exists('pokehub_get_table')) {
            $this->items = [];
            return;
        }

        global $wpdb;
        $table = pokehub_get_table('pokemon_backgrounds');

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'title';
        $order   = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $allowed_orderby = ['title', 'slug'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'title';
        }

        $search = isset($_REQUEST['s']) ? wp_unslash(trim($_REQUEST['s'])) : '';
        $where  = 'WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $where   .= ' AND (title LIKE %s OR slug LIKE %s)';
            $like     = '%' . $wpdb->esc_like($search) . '%';
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
 * Traitement formulaire Background (add / update)
 */
function poke_hub_pokemon_handle_backgrounds_form() {
    if (!is_admin()) {
        return;
    }

    if (empty($_POST['poke_hub_pokemon_action'])) {
        return;
    }

    if (empty($_POST['ph_section']) || $_POST['ph_section'] !== 'backgrounds') {
        return;
    }

    $action = sanitize_text_field($_POST['poke_hub_pokemon_action']);
    if (!in_array($action, ['add_background', 'update_background'], true)) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('poke_hub_pokemon_edit_background');

    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;
    $table = pokehub_get_table('pokemon_backgrounds');
    $links_table = pokehub_get_table('pokemon_background_pokemon_links');

    $redirect_base = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'backgrounds',
        ],
        admin_url('admin.php')
    );

    $id        = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $title     = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $slug      = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
    $image_url = isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '';
    $event_id  = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
    $event_type = isset($_POST['event_type']) ? sanitize_text_field(wp_unslash($_POST['event_type'])) : '';
    $pokemon_ids = isset($_POST['pokemon_ids']) && is_array($_POST['pokemon_ids'])
        ? array_map('intval', $_POST['pokemon_ids'])
        : [];

    // Validation
    if ($title === '') {
        wp_safe_redirect(add_query_arg('ph_msg', 'missing_title', $redirect_base));
        exit;
    }

    // Slug auto depuis le titre
    if ($slug === '') {
        $slug = sanitize_title($title);
    }

    $extra = [
        'image_url' => $image_url,
    ];

    $data = [
        'slug'      => $slug,
        'title'     => $title,
        'image_url' => $image_url,
        'event_id'  => $event_id > 0 ? $event_id : null,
        'event_type' => $event_type,
        'extra'     => wp_json_encode($extra),
    ];
    $format = ['%s', '%s', '%s', '%d', '%s', '%s'];

    if ($action === 'add_background') {
        $wpdb->insert($table, $data, $format);
        $background_id = $wpdb->insert_id;

        // Insérer les liens Pokémon
        if ($background_id > 0 && !empty($pokemon_ids)) {
            foreach ($pokemon_ids as $pokemon_id) {
                if ($pokemon_id > 0) {
                    $wpdb->insert(
                        $links_table,
                        [
                            'background_id' => $background_id,
                            'pokemon_id'    => $pokemon_id,
                        ],
                        ['%d', '%d']
                    );
                }
            }
        }

        wp_safe_redirect(add_query_arg('ph_msg', 'saved', $redirect_base));
        exit;
    }

    // update
    if ($id <= 0) {
        wp_safe_redirect(add_query_arg('ph_msg', 'invalid_id', $redirect_base));
        exit;
    }

    $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);

    // Mettre à jour les liens Pokémon
    // Supprimer les anciens liens
    $wpdb->delete($links_table, ['background_id' => $id], ['%d']);

    // Insérer les nouveaux liens
    if (!empty($pokemon_ids)) {
        foreach ($pokemon_ids as $pokemon_id) {
            if ($pokemon_id > 0) {
                $wpdb->insert(
                    $links_table,
                    [
                        'background_id' => $id,
                        'pokemon_id'    => $pokemon_id,
                    ],
                    ['%d', '%d']
                );
            }
        }
    }

    wp_safe_redirect(add_query_arg('ph_msg', 'updated', $redirect_base));
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_backgrounds_form');

/**
 * Delete simple (action=delete sur une ligne)
 */
function poke_hub_pokemon_handle_backgrounds_delete() {
    if (!is_admin()) {
        return;
    }

    if (empty($_GET['page']) || $_GET['page'] !== 'poke-hub-pokemon') {
        return;
    }

    if (empty($_GET['ph_section']) || $_GET['ph_section'] !== 'backgrounds') {
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

    check_admin_referer('poke_hub_delete_background_' . $id);

    global $wpdb;
    $table = pokehub_get_table('pokemon_backgrounds');
    $links_table = pokehub_get_table('pokemon_background_pokemon_links');

    // Supprimer les liens d'abord
    $wpdb->delete($links_table, ['background_id' => $id], ['%d']);

    // Puis supprimer le background
    $wpdb->delete($table, ['id' => $id], ['%d']);

    $redirect = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'backgrounds',
            'ph_msg'     => 'deleted',
        ],
        admin_url('admin.php')
    );

    wp_redirect($redirect);
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_backgrounds_delete');

/**
 * Écran principal "Backgrounds"
 */
function poke_hub_pokemon_admin_backgrounds_screen() {
    $list_table = new Poke_Hub_Pokemon_Backgrounds_List_Table();

    $list_table->process_bulk_action();
    $list_table->prepare_items();

    if (!empty($_GET['ph_msg'])) {
        $msg = sanitize_key($_GET['ph_msg']);
        if ($msg === 'saved' || $msg === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Background saved.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Background deleted.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'missing_title') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Title is required.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'invalid_id') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid background ID.', 'poke-hub') . '</p></div>';
        }
    }
    ?>
    <form method="post">
        <input type="hidden" name="page" value="poke-hub-pokemon" />
        <input type="hidden" name="ph_section" value="backgrounds" />
        <?php
        wp_nonce_field('bulk-pokemon_backgrounds');

        $list_table->search_box(__('Search background', 'poke-hub'), 'pokemon-background');
        $list_table->display();
        ?>
    </form>
    <?php
}

