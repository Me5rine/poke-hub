<?php
// modules/pokemon/admin/sections/moves.php

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
 * On inclut le formulaire dédié aux moves
 */
require_once POKE_HUB_POKEMON_PATH . '/admin/forms/move-form.php';

/**
 * List table des moves
 */
class Poke_Hub_Pokemon_attacks_List_Table extends WP_List_Table {

    /**
     * Filtres actuels (type / jeu / catégorie)
     *
     * @var array{type_id:int,game_key:string,category:string}
     */
    protected $filters = [
        'type_id'  => 0,
        'game_key' => '',
        'category' => '',
    ];

    public function __construct() {
        parent::__construct([
            'singular' => 'pokemon_move',
            'plural'   => 'pokemon_moves',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'       => '<input type="checkbox" />',
            'name'     => __('Name', 'poke-hub'),
            'slug'     => __('Slug', 'poke-hub'),
            'type'     => __('Type', 'poke-hub'),
            'game'     => __('Game', 'poke-hub'),
            'category' => __('Category', 'poke-hub'),
        ];
    }

    public function get_sortable_columns() {
        return [
            // L'orderby "name" mappe sur name_fr en base
            'name'     => ['name_fr', true],
            'slug'     => ['slug', true],
            // tri PHP pour les colonnes dérivées :
            'type'     => ['type', false],
            'game'     => ['game', false],
            'category' => ['category', false],
        ];
    }

    /**
     * URL de base pour l’onglet "Attacks".
     */
    private function get_base_url() {
        static $base = null;

        if ($base === null) {
            $base = add_query_arg(
                [
                    'page'       => 'poke-hub-pokemon',
                    'ph_section' => 'moves',
                ],
                admin_url('admin.php')
            );
        }

        return $base;
    }

    /**
     * Récupère tous les types (pour le filtre).
     *
     * @return array
     */
    private function get_all_types() {
        static $all_types = null;

        if ($all_types !== null) {
            return $all_types;
        }

        $all_types = [];

        if (!function_exists('pokehub_get_table')) {
            return $all_types;
        }

        global $wpdb;
        $types_table = pokehub_get_table('pokemon_types');
        if (!$types_table) {
            return $all_types;
        }

        // NEW : noms multilingues
        $all_types = $wpdb->get_results(
            "SELECT id, name_en, name_fr, slug, color
             FROM {$types_table}
             ORDER BY sort_order ASC, name_fr ASC"
        );

        return $all_types;
    }

    /**
     * Index des types par attaque :
     *  attack_id => ['names' => [...], 'type_ids' => [...]]
     *
     * @return array<int,array{names:array,type_ids:array}>
     */
    private function get_attack_types_index() {
        static $index = null;

        if ($index !== null) {
            return $index;
        }

        $index = [];

        if (!function_exists('pokehub_get_table')) {
            return $index;
        }

        global $wpdb;

        $link_table  = pokehub_get_table('attack_type_links');
        $types_table = pokehub_get_table('pokemon_types');

        if (!$link_table || !$types_table) {
            return $index;
        }

        // NEW : name_fr / name_en
        $rows = $wpdb->get_results("
            SELECT atl.attack_id, atl.type_id, t.name_fr, t.name_en
            FROM {$link_table} AS atl
            INNER JOIN {$types_table} AS t ON t.id = atl.type_id
            ORDER BY t.sort_order ASC, t.name_fr ASC
        ");

        foreach ($rows as $row) {
            $attack_id = (int) $row->attack_id;
            $type_id   = (int) $row->type_id;
            $name_fr   = (string) ($row->name_fr ?? '');
            $name_en   = (string) ($row->name_en ?? '');
            $label     = $name_fr !== '' ? $name_fr : $name_en;

            if (!isset($index[$attack_id])) {
                $index[$attack_id] = [
                    'names'    => [],
                    'type_ids' => [],
                ];
            }

            $index[$attack_id]['names'][]    = $label;
            $index[$attack_id]['type_ids'][] = $type_id;
        }

        return $index;
    }

    /**
     * Lit les filtres depuis la requête (GET/POST).
     */
    protected function parse_filters() {
        $this->filters['type_id']   = isset($_REQUEST['filter_type']) ? (int) $_REQUEST['filter_type'] : 0;
        $this->filters['game_key']  = isset($_REQUEST['filter_game']) ? sanitize_key($_REQUEST['filter_game']) : '';
        $this->filters['category']  = isset($_REQUEST['filter_category']) ? sanitize_key($_REQUEST['filter_category']) : '';
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
                'ph_section' => 'moves',
                'action'     => 'edit',
                'id'         => (int) $item->id,
            ],
            admin_url('admin.php')
        );

        $delete_url = wp_nonce_url(
            add_query_arg(
                [
                    'page'       => 'poke-hub-pokemon',
                    'ph_section' => 'moves',
                    'action'     => 'delete',
                    'id'         => (int) $item->id,
                ],
                admin_url('admin.php')
            ),
            'poke_hub_delete_attack_' . (int) $item->id
        );

        // NEW : priorité nom FR / fallback EN / puis ancien champ name
        $name_fr     = isset($item->name_fr) ? (string) $item->name_fr : '';
        $name_en     = isset($item->name_en) ? (string) $item->name_en : '';
        $name_legacy = isset($item->name) ? (string) $item->name : '';

        $display_name = $name_fr !== '' ? $name_fr : ($name_en !== '' ? $name_en : $name_legacy);

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
                esc_attr__('Are you sure you want to delete this move?', 'poke-hub'),
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

    public function column_slug($item) {
        return '<code>' . esc_html($item->slug) . '</code>';
    }

    /**
     * Colonne Type : affiche les types liés, cliquables pour filtrer.
     */
    public function column_type($item) {
        $index = $this->get_attack_types_index();
        $entry = $index[(int) $item->id] ?? null;

        if (!$entry || empty($entry['names'])) {
            return '&mdash;';
        }

        $base = $this->get_base_url();
        $out  = [];

        foreach ($entry['names'] as $i => $name) {
            $type_id = $entry['type_ids'][$i] ?? 0;
            if ($type_id <= 0) {
                $out[] = esc_html($name);
                continue;
            }

            $url = add_query_arg(
                [
                    'filter_type' => $type_id,
                    'paged'       => 1,
                ],
                $base
            );

            $out[] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($url),
                esc_html($name)
            );
        }

        return implode(', ', $out);
    }

    /**
     * Colonne Game : label cliquable pour filtrer par jeu.
     */
    public function column_game($item) {
        $extra = [];
        if (!empty($item->extra)) {
            $decoded = json_decode($item->extra, true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }

        $game_key = isset($extra['game_key']) ? sanitize_key($extra['game_key']) : 'pokemon_go';

        switch ($game_key) {
            case 'pokemon_go':
            default:
                $label = esc_html__('Pokémon GO', 'poke-hub');
                break;
        }

        $base = $this->get_base_url();
        $url  = add_query_arg(
            [
                'filter_game' => $game_key,
                'paged'       => 1,
            ],
            $base
        );

        return sprintf(
            '<a href="%s">%s</a>',
            esc_url($url),
            $label
        );
    }

    /**
     * Colonne Category : Fast / Charged (cliquable pour filtrer).
     */
    public function column_category($item) {
        $category = isset($item->category) ? sanitize_key($item->category) : '';

        if ($category === '') {
            return '&mdash;';
        }

        switch ($category) {
            case 'fast':
                $label = __('Fast move', 'poke-hub');
                break;
            case 'charged':
                $label = __('Charged move', 'poke-hub');
                break;
            default:
                $label = $category;
                break;
        }

        $base = $this->get_base_url();
        $url  = add_query_arg(
            [
                'filter_category' => $category,
                'paged'           => 1,
            ],
            $base
        );

        return sprintf(
            '<a href="%s">%s</a>',
            esc_url($url),
            esc_html($label)
        );
    }

    public function column_default($item, $column_name) {
        return '';
    }

    public function get_bulk_actions() {
        return [
            'bulk_delete' => __('Delete', 'poke-hub'),
        ];
    }

    /**
     * Filtres au-dessus du tableau (type / game / category).
     */
    protected function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        $types           = $this->get_all_types();
        $current_type    = $this->filters['type_id'];
        $current_game    = $this->filters['game_key'];
        $current_category = $this->filters['category'];
        ?>
        <div class="alignleft actions">
            <label class="screen-reader-text" for="filter_type">
                <?php esc_html_e('Filter by type', 'poke-hub'); ?>
            </label>
            <select name="filter_type" id="filter_type">
                <option value=""><?php esc_html_e('All types', 'poke-hub'); ?></option>
                <?php foreach ($types as $type) : ?>
                    <?php
                    $t_name_fr = isset($type->name_fr) ? $type->name_fr : '';
                    $t_name_en = isset($type->name_en) ? $type->name_en : '';
                    $t_label   = $t_name_fr !== '' ? $t_name_fr : $t_name_en;
                    ?>
                    <option value="<?php echo (int) $type->id; ?>"
                        <?php selected($current_type, (int) $type->id); ?>>
                        <?php echo esc_html($t_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="screen-reader-text" for="filter_game">
                <?php esc_html_e('Filter by game', 'poke-hub'); ?>
            </label>
            <select name="filter_game" id="filter_game">
                <option value=""><?php esc_html_e('All games', 'poke-hub'); ?></option>
                <option value="pokemon_go" <?php selected($current_game, 'pokemon_go'); ?>>
                    <?php esc_html_e('Pokémon GO', 'poke-hub'); ?>
                </option>
                <!-- plus tard : autres jeux -->
            </select>

            <label class="screen-reader-text" for="filter_category">
                <?php esc_html_e('Filter by category', 'poke-hub'); ?>
            </label>
            <select name="filter_category" id="filter_category">
                <option value=""><?php esc_html_e('All categories', 'poke-hub'); ?></option>
                <option value="fast" <?php selected($current_category, 'fast'); ?>>
                    <?php esc_html_e('Fast moves', 'poke-hub'); ?>
                </option>
                <option value="charged" <?php selected($current_category, 'charged'); ?>>
                    <?php esc_html_e('Charged moves', 'poke-hub'); ?>
                </option>
            </select>

            <?php submit_button(__('Filter'), '', 'filter_action', false); ?>
        </div>
        <?php
    }

    public function process_bulk_action() {
        if ('bulk_delete' !== $this->current_action()) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('bulk-pokemon_moves');

        if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
            return;
        }

        if (!function_exists('pokehub_get_table')) {
            return;
        }

        global $wpdb;
        $table_attacks = pokehub_get_table('attacks');
        $table_stats   = pokehub_get_table('attack_stats');

        $ids = array_map('intval', $_POST['ids']);
        $ids = array_filter($ids);

        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '%d'));

            // Supprime d'abord les stats
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table_stats} WHERE attack_id IN ($in)",
                    $ids
                )
            );

            // Puis les attaques
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table_attacks} WHERE id IN ($in)",
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

        $table_attacks = pokehub_get_table('attacks');

        // Screen option : moves par page
        $per_page     = $this->get_items_per_page('poke_hub_pokemon_attacks_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        // Filtres
        $this->parse_filters();
        $filter_type     = $this->filters['type_id'];
        $filter_game     = $this->filters['game_key'];
        $filter_category = $this->filters['category'];

        // Tri demandé
        $orderby = isset($_REQUEST['orderby']) ? sanitize_key($_REQUEST['orderby']) : 'name';
        $order   = isset($_REQUEST['order']) ? strtoupper(sanitize_text_field($_REQUEST['order'])) : 'ASC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        // Colonnes triables côté SQL
        // NEW : name_fr (mapped depuis "name")
        $sql_orderby_allowed = ['name_fr', 'slug'];

        // Colonnes triables côté PHP (données dérivées)
        $php_sortable = ['type', 'game', 'category'];

        // Mapper 'name' → 'name_fr'
        if ($orderby === 'name') {
            $orderby = 'name_fr';
        }

        if (!in_array($orderby, array_merge($sql_orderby_allowed, $php_sortable), true)) {
            $orderby = 'name_fr';
        }

        // Recherche
        $search = isset($_REQUEST['s']) ? wp_unslash(trim($_REQUEST['s'])) : '';
        $where  = 'WHERE 1=1';
        $params = [];

        if ($search !== '') {
            // NEW : recherche sur name_en, name_fr, slug
            $where   .= " AND (a.name_en LIKE %s OR a.name_fr LIKE %s OR a.slug LIKE %s)";
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Mode PHP si tri sur colonne virtuelle OU filtres actifs
        $use_php_mode = in_array($orderby, $php_sortable, true)
            || $filter_type > 0
            || $filter_game !== ''
            || $filter_category !== '';

        if ($use_php_mode) {
            // On récupère toutes les attaques qui matchent la recherche
            $sql_all = "SELECT a.* FROM {$table_attacks} AS a {$where}";
            $items   = $params
                ? $wpdb->get_results($wpdb->prepare($sql_all, $params))
                : $wpdb->get_results($sql_all);

            // Index types
            $types_index = $this->get_attack_types_index();

            // 1) Filtrage en PHP
            $items = array_filter(
                $items,
                function ($item) use ($filter_type, $filter_game, $filter_category, $types_index) {
                    $attack_id = (int) $item->id;

                    // Filtre type
                    if ($filter_type > 0) {
                        $entry    = $types_index[$attack_id] ?? null;
                        $type_ids = $entry ? $entry['type_ids'] : [];
                        if (empty($type_ids) || !in_array($filter_type, $type_ids, true)) {
                            return false;
                        }
                    }

                    // Filtre game
                    if ($filter_game !== '') {
                        $extra = [];
                        if (!empty($item->extra)) {
                            $decoded = json_decode($item->extra, true);
                            if (is_array($decoded)) {
                                $extra = $decoded;
                            }
                        }
                        $game_key = isset($extra['game_key']) ? sanitize_key($extra['game_key']) : 'pokemon_go';
                        if ($game_key !== $filter_game) {
                            return false;
                        }
                    }

                    // Filtre catégorie (fast / charged)
                    if ($filter_category !== '') {
                        $cat = isset($item->category) ? sanitize_key($item->category) : '';
                        if ($cat !== $filter_category) {
                            return false;
                        }
                    }

                    return true;
                }
            );

            // 2) Tri en PHP
            $items = array_values($items);

            usort($items, function ($a, $b) use ($orderby, $order, $types_index) {
                $extra_a = [];
                $extra_b = [];

                if (!empty($a->extra)) {
                    $decoded = json_decode($a->extra, true);
                    if (is_array($decoded)) {
                        $extra_a = $decoded;
                    }
                }
                if (!empty($b->extra)) {
                    $decoded = json_decode($b->extra, true);
                    if (is_array($decoded)) {
                        $extra_b = $decoded;
                    }
                }

                switch ($orderby) {
                    case 'game':
                        $val_a = $extra_a['game_key'] ?? '';
                        $val_b = $extra_b['game_key'] ?? '';
                        break;

                    case 'type':
                        $val_a = $types_index[(int) $a->id]['names'][0] ?? '';
                        $val_b = $types_index[(int) $b->id]['names'][0] ?? '';
                        break;

                    case 'category':
                        $val_a = isset($a->category) ? $a->category : '';
                        $val_b = isset($b->category) ? $b->category : '';
                        break;

                    case 'name_fr':
                    case 'slug':
                    default:
                        $val_a = $a->{$orderby} ?? '';
                        $val_b = $b->{$orderby} ?? '';
                        break;
                }

                if ($val_a == $val_b) {
                    return 0;
                }

                if ($order === 'DESC') {
                    return ($val_a < $val_b) ? 1 : -1;
                }

                return ($val_a < $val_b) ? -1 : 1;
            });

            // 3) Pagination manuelle après filtrage + tri
            $total_items = count($items);
            $items       = array_slice($items, $offset, $per_page);

            $this->items = $items;

            $columns  = $this->get_columns();
            $hidden   = get_hidden_columns($this->screen);
            $sortable = $this->get_sortable_columns();
            $this->_column_headers = [$columns, $hidden, $sortable];

            $this->set_pagination_args([
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => max(1, ceil($total_items / $per_page)),
            ]);

            return;
        }

        // === Cas "normal" : tri SQL sur name_fr / slug ===

        // Total items
        $sql_count = "SELECT COUNT(*) FROM {$table_attacks} AS a {$where}";
        $total_items = $params
            ? (int) $wpdb->get_var($wpdb->prepare($sql_count, $params))
            : (int) $wpdb->get_var($sql_count);

        // Items paginés
        $sql_items = "
            SELECT a.*
            FROM {$table_attacks} AS a
            {$where}
            ORDER BY {$orderby} {$order}
            LIMIT %d OFFSET %d
        ";

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
 * Gestion delete (action simple)
 */
function poke_hub_pokemon_handle_attacks_delete() {
    if (!is_admin()) {
        return;
    }

    if (empty($_GET['page']) || $_GET['page'] !== 'poke-hub-pokemon') {
        return;
    }

    if (empty($_GET['ph_section']) || $_GET['ph_section'] !== 'moves') {
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

    check_admin_referer('poke_hub_delete_attack_' . $id);

    global $wpdb;
    $table_attacks = pokehub_get_table('attacks');
    $table_stats   = pokehub_get_table('attack_stats');

    // Supprimer les stats associées
    $wpdb->delete($table_stats, ['attack_id' => $id], ['%d']);

    // Supprimer l'attaque
    $wpdb->delete($table_attacks, ['id' => $id], ['%d']);

    $redirect = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'moves',
            'ph_msg'     => 'deleted',
        ],
        admin_url('admin.php')
    );

    wp_redirect($redirect);
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_attacks_delete');

/**
 * Traitement formulaire Attacks (add / update)
 */
function poke_hub_pokemon_handle_attacks_form() {
    if (!is_admin()) {
        return;
    }

    if (empty($_POST['poke_hub_pokemon_action'])) {
        return;
    }

    if (empty($_POST['ph_section']) || $_POST['ph_section'] !== 'moves') {
        return;
    }

    $action = sanitize_text_field($_POST['poke_hub_pokemon_action']);
    if (!in_array($action, ['add_move', 'update_move'], true)) {
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
            'ph_section' => 'moves',
        ],
        admin_url('admin.php')
    );

    $id       = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    // NEW : noms multilingues
    $name_en  = isset($_POST['name_en']) ? sanitize_text_field($_POST['name_en']) : '';
    $name_fr  = isset($_POST['name_fr']) ? sanitize_text_field($_POST['name_fr']) : '';

    // Compat rétro : si les deux vides mais un ancien champ name existe
    if ($name_en === '' && $name_fr === '' && !empty($_POST['name'])) {
        $fallback = sanitize_text_field($_POST['name']);
        $name_en  = $fallback;
    }

    $slug       = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
    $game_key   = isset($_POST['game_key']) ? sanitize_key($_POST['game_key']) : 'pokemon_go';
    $type_ids   = isset($_POST['type_ids']) && is_array($_POST['type_ids'])
        ? $_POST['type_ids']
        : [];
    $category   = isset($_POST['category']) ? sanitize_key($_POST['category']) : '';

    // Normalisation catégorie : on limite à fast / charged / vide
    $allowed_categories = ['fast', 'charged'];
    if (!in_array($category, $allowed_categories, true)) {
        $category = '';
    }

    // Au moins un nom requis
    if ($name_en === '' && $name_fr === '') {
        wp_redirect(add_query_arg('ph_msg', 'missing_name', $redirect_base));
        exit;
    }

    if ($slug === '') {
        $base = $name_en !== '' ? $name_en : $name_fr;
        $slug = sanitize_title($base);
    }

    $table_attacks = pokehub_get_table('attacks');
    $table_stats   = pokehub_get_table('attack_stats');

    // On continue à stocker le jeu dans extra (cohérence avec l'existant)
    $extra = [
        'game_key' => $game_key,
    ];
    $extra_json = wp_json_encode($extra);

    $data = [
        'slug'     => $slug,
        'name_en'  => $name_en,
        'name_fr'  => $name_fr,
        'category' => $category,
        'extra'    => $extra_json,
    ];
    $format = ['%s', '%s', '%s', '%s', '%s'];

    if ($action === 'add_move') {
        $wpdb->insert($table_attacks, $data, $format);
        $attack_id = (int) $wpdb->insert_id;

        if ($attack_id > 0) {
            // Types
            poke_hub_pokemon_sync_attack_types_links($attack_id, $type_ids);
            // Stats
            poke_hub_pokemon_save_attack_stats_for_attack($attack_id, $game_key, $table_stats);
        }

        wp_redirect(add_query_arg('ph_msg', 'saved', $redirect_base));
        exit;
    }

    // update
    if ($id <= 0) {
        wp_redirect(add_query_arg('ph_msg', 'invalid_id', $redirect_base));
        exit;
    }

    $wpdb->update($table_attacks, $data, ['id' => $id], $format, ['%d']);

    // On (re)enregistre les types
    poke_hub_pokemon_sync_attack_types_links($id, $type_ids);

    // On (re)enregistre les stats
    poke_hub_pokemon_save_attack_stats_for_attack($id, $game_key, $table_stats);

    $redirect = add_query_arg(
        [
            'ph_msg' => 'updated',
        ],
        $redirect_base
    );

    wp_redirect($redirect);
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_attacks_form');

/**
 * Sauvegarde les stats PvE / PvP pour une attaque donnée
 *
 * @param int    $attack_id
 * @param string $game_key
 * @param string $table_stats
 */
function poke_hub_pokemon_save_attack_stats_for_attack($attack_id, $game_key, $table_stats) {
    global $wpdb;

    $attack_id = (int) $attack_id;
    if ($attack_id <= 0) {
        return;
    }

    // On efface d'abord les stats existantes pour ce jeu
    $wpdb->delete(
        $table_stats,
        [
            'attack_id' => $attack_id,
            'game_key'  => $game_key,
        ],
        [
            '%d',
            '%s',
        ]
    );

    // PvE
    $pve_damage                 = isset($_POST['pve_damage']) ? (int) $_POST['pve_damage'] : 0;
    $pve_dps                    = isset($_POST['pve_dps']) ? (float) $_POST['pve_dps'] : 0;
    $pve_eps                    = isset($_POST['pve_eps']) ? (float) $_POST['pve_eps'] : 0;
    $pve_duration_ms            = isset($_POST['pve_duration_ms']) ? (int) $_POST['pve_duration_ms'] : 0;
    $pve_damage_window_start_ms = isset($_POST['pve_damage_window_start_ms']) ? (int) $_POST['pve_damage_window_start_ms'] : 0;
    $pve_damage_window_end_ms   = isset($_POST['pve_damage_window_end_ms']) ? (int) $_POST['pve_damage_window_end_ms'] : 0;
    $pve_energy                 = isset($_POST['pve_energy']) ? (int) $_POST['pve_energy'] : 0;

    $has_pve = (
        $pve_damage ||
        $pve_dps ||
        $pve_eps ||
        $pve_duration_ms ||
        $pve_damage_window_start_ms ||
        $pve_damage_window_end_ms ||
        $pve_energy
    );

    if ($has_pve) {
        $wpdb->insert(
            $table_stats,
            [
                'attack_id'              => $attack_id,
                'game_key'               => $game_key,
                'context'                => 'pve',
                'damage'                 => $pve_damage,
                'dps'                    => $pve_dps,
                'eps'                    => $pve_eps,
                'duration_ms'            => $pve_duration_ms,
                'damage_window_start_ms' => $pve_damage_window_start_ms,
                'damage_window_end_ms'   => $pve_damage_window_end_ms,
                'energy'                 => $pve_energy,
                'extra'                  => null,
            ],
            [
                '%d', // attack_id
                '%s', // game_key
                '%s', // context
                '%d', // damage
                '%f', // dps
                '%f', // eps
                '%d', // duration_ms
                '%d', // damage_window_start_ms
                '%d', // damage_window_end_ms
                '%d', // energy
                '%s', // extra
            ]
        );
    }

    // PvP
    $pvp_damage                 = isset($_POST['pvp_damage']) ? (int) $_POST['pvp_damage'] : 0;
    $pvp_dps                    = isset($_POST['pvp_dps']) ? (float) $_POST['pvp_dps'] : 0;
    $pvp_eps                    = isset($_POST['pvp_eps']) ? (float) $_POST['pvp_eps'] : 0;
    $pvp_duration_ms            = isset($_POST['pvp_duration_ms']) ? (int) $_POST['pvp_duration_ms'] : 0;
    $pvp_damage_window_start_ms = isset($_POST['pvp_damage_window_start_ms']) ? (int) $_POST['pvp_damage_window_start_ms'] : 0;
    $pvp_damage_window_end_ms   = isset($_POST['pvp_damage_window_end_ms']) ? (int) $_POST['pvp_damage_window_end_ms'] : 0;
    $pvp_energy                 = isset($_POST['pvp_energy']) ? (int) $_POST['pvp_energy'] : 0;

    $has_pvp = (
        $pvp_damage ||
        $pvp_dps ||
        $pvp_eps ||
        $pvp_duration_ms ||
        $pvp_damage_window_start_ms ||
        $pvp_damage_window_end_ms ||
        $pvp_energy
    );

    if ($has_pvp) {
        $wpdb->insert(
            $table_stats,
            [
                'attack_id'              => $attack_id,
                'game_key'               => $game_key,
                'context'                => 'pvp',
                'damage'                 => $pvp_damage,
                'dps'                    => $pvp_dps,
                'eps'                    => $pvp_eps,
                'duration_ms'            => $pvp_duration_ms,
                'damage_window_start_ms' => $pvp_damage_window_start_ms,
                'damage_window_end_ms'   => $pvp_damage_window_end_ms,
                'energy'                 => $pvp_energy,
                'extra'                  => null,
            ],
            [
                '%d',
                '%s',
                '%s',
                '%d',
                '%f',
                '%f',
                '%d',
                '%d',
                '%d',
                '%d',
                '%s',
            ]
        );
    }
}

/**
 * Synchronise les types associés à une attaque.
 *
 * @param int   $attack_id
 * @param array $type_ids IDs de types (non nettoyés)
 */
function poke_hub_pokemon_sync_attack_types_links($attack_id, $type_ids) {
    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    $attack_id = (int) $attack_id;
    if ($attack_id <= 0) {
        return;
    }

    $link_table = pokehub_get_table('attack_type_links');
    if (!$link_table) {
        return;
    }

    // Nettoyage / normalisation de la liste de types
    if (!is_array($type_ids)) {
        $type_ids = [];
    }

    $type_ids = array_map('intval', $type_ids);
    $type_ids = array_filter($type_ids, function ($id) {
        return $id > 0;
    });
    $type_ids = array_values(array_unique($type_ids));

    // On supprime d'abord ce qui existe
    $wpdb->delete(
        $link_table,
        ['attack_id' => $attack_id],
        ['%d']
    );

    if (empty($type_ids)) {
        return;
    }

    // Insert bulk (attack_id, type_id)
    $values       = [];
    $placeholders = [];

    foreach ($type_ids as $type_id) {
        $values[]       = $attack_id;
        $values[]       = $type_id;
        $placeholders[] = '(%d, %d)';
    }

    $sql = "INSERT INTO {$link_table} (attack_id, type_id) VALUES " . implode(',', $placeholders);
    $wpdb->query($wpdb->prepare($sql, $values));
}

/**
 * Écran principal de l’onglet "Attacks"
 */
function poke_hub_pokemon_admin_attacks_screen() {

    $list_table = new Poke_Hub_Pokemon_attacks_List_Table();

    // Bulk actions
    $list_table->process_bulk_action();
    $list_table->prepare_items();

    // Notices
    if (!empty($_GET['ph_msg'])) {
        $msg = sanitize_key($_GET['ph_msg']);
        if ($msg === 'saved' || $msg === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Attack saved.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Attack deleted.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'missing_name') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Name is required.', 'poke-hub') . '</p></div>';
        }
    }
    ?>
    <form method="post">
        <input type="hidden" name="page" value="poke-hub-pokemon" />
        <input type="hidden" name="ph_section" value="moves" />
        <?php
        // nonce pour les bulk actions
        wp_nonce_field('bulk-pokemon_moves');

        $list_table->search_box(__('Search moves', 'poke-hub'), 'pokemon-moves');
        $list_table->display();
        ?>
    </form>
    <?php
}
