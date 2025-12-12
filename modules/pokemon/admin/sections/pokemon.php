<?php
// File: modules/pokemon/admin/sections/pokemon.php

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
 * On inclut le formulaire dédié aux Pokémon
 */
require_once POKE_HUB_POKEMON_PATH . '/admin/forms/pokemon-form.php';

/**
 * List table des Pokémon
 */
class Poke_Hub_Pokemon_List_Table extends WP_List_Table {

    protected $filters = [];

    /** @var array<int,object> form_variant_id => variant row */
    protected $variant_map = [];

    public function __construct() {
        parent::__construct([
            'singular' => 'pokemon',
            'plural'   => 'pokemon',
            'ajax'     => false,
        ]);

        // Récupération des filtres depuis $_GET
        $this->filters = [
            'generation_id'     => isset($_GET['filter_generation_id']) ? (int) $_GET['filter_generation_id'] : 0,
            'form_variant_id'   => isset($_GET['filter_form_variant_id']) ? (int) $_GET['filter_form_variant_id'] : 0,
            'type_id'           => isset($_GET['filter_type_id']) ? (int) $_GET['filter_type_id'] : 0,
            'is_default'        => isset($_GET['filter_is_default']) ? sanitize_text_field(wp_unslash($_GET['filter_is_default'])) : '',
            'variant_category'  => isset($_GET['filter_variant_category']) ? sanitize_text_field(wp_unslash($_GET['filter_variant_category'])) : '',
            'variant_group'     => isset($_GET['filter_variant_group']) ? sanitize_text_field(wp_unslash($_GET['filter_variant_group'])) : '',
            'regional'          => isset($_GET['filter_regional']) ? sanitize_text_field(wp_unslash($_GET['filter_regional'])) : '',
        ];
    }

    public function get_columns() {
        return [
            'cb'               => '<input type="checkbox" />',
            'dex_number'       => __('Dex #', 'poke-hub'),
            'name'             => __('Name', 'poke-hub'),
            'form'             => __('Form / Variant', 'poke-hub'),
            'generation'       => __('Gen', 'poke-hub'),
            'is_default'       => __('Default', 'poke-hub'),
            'release_normal'   => __('Release (normal)', 'poke-hub'),
            'release_shiny'    => __('Release (shiny)', 'poke-hub'),
            'release_shadow'   => __('Release (shadow)', 'poke-hub'),
            'release_mega'     => __('Release (mega)', 'poke-hub'),
            'release_dynamax'  => __('Release (dynamax)', 'poke-hub'),
            'regional'         => __('Regional', 'poke-hub'),
            'variant_category' => __('Variant category', 'poke-hub'),
            'variant_group'    => __('Variant group', 'poke-hub'),
        ];
    }

    public function get_sortable_columns() {
        return [
            'dex_number'       => ['dex_number', true],
            'name'             => ['name_fr', true],
            'release_normal'   => ['release_normal', false],
            'release_shiny'    => ['release_shiny', false],
            'release_shadow'   => ['release_shadow', false],
            'release_mega'     => ['release_mega', false],
            'release_dynamax'  => ['release_dynamax', false],
            'regional'         => ['regional', false],
            'variant_category' => ['variant_category', false],
            'variant_group'    => ['variant_group', false],
        ];
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="ids[]" value="%d" />',
            (int) $item->id
        );
    }

    /**
     * Colonne Name : lien vers l’édition + actions
     */
    public function column_name($item) {
        $edit_url = add_query_arg(
            [
                'page'       => 'poke-hub-pokemon',
                'ph_section' => 'pokemon',
                'action'     => 'edit',
                'id'         => (int) $item->id,
            ],
            admin_url('admin.php')
        );

        $delete_url = wp_nonce_url(
            add_query_arg(
                [
                    'page'       => 'poke-hub-pokemon',
                    'ph_section' => 'pokemon',
                    'action'     => 'delete',
                    'id'         => (int) $item->id,
                ],
                admin_url('admin.php')
            ),
            'poke_hub_delete_pokemon_' . (int) $item->id
        );

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
                esc_attr__('Are you sure you want to delete this Pokémon?', 'poke-hub'),
                esc_html__('Delete', 'poke-hub')
            ),
        ];

        return sprintf(
            '<strong><a class="row-title" href="%1$s">%2$s</a></strong> %3$s',
            esc_url($edit_url),
            $title,
            $this->row_actions($actions)
        );
    }

    public function column_default($item, $column_name) {
        // URL de base de l’onglet Pokémon pour les liens de filtre
        $base_url = add_query_arg(
            [
                'page'       => 'poke-hub-pokemon',
                'ph_section' => 'pokemon',
            ],
            admin_url('admin.php')
        );

        // Décodage extra (release + regional + variants)
        static $cache_extra = [];
        if (!isset($cache_extra[$item->id])) {
            $extra = [];
            if (!empty($item->extra)) {
                $decoded = json_decode($item->extra, true);
                if (is_array($decoded)) {
                    $extra = $decoded;
                }
            }
            $cache_extra[$item->id] = $extra;
        } else {
            $extra = $cache_extra[$item->id];
        }

        $release          = $extra['release']          ?? [];
        $regional         = $extra['regional']         ?? [];
        $variant_category = $extra['variant_category'] ?? '';
        $variant_group    = $extra['variant_group']    ?? '';

        switch ($column_name) {
            case 'dex_number':
                return sprintf('#%03d', (int) $item->dex_number);

            case 'form':
                $variant_id = isset($item->form_variant_id) ? (int) $item->form_variant_id : 0;
                if ($variant_id <= 0) {
                    return '&mdash;';
                }

                if (!isset($this->variant_map[$variant_id])) {
                    return '&mdash;';
                }

                $fv = $this->variant_map[$variant_id];

                $display = $fv->label !== '' ? $fv->label : $fv->form_slug;
                $meta    = [];

                if (!empty($fv->category) && $fv->category !== 'normal') {
                    $meta[] = $fv->category;
                }
                if (!empty($fv->group)) {
                    $meta[] = $fv->group;
                }
                if ($meta) {
                    $display .= ' (' . implode(' – ', $meta) . ')';
                }

                $filter_url = add_query_arg('filter_form_variant_id', $variant_id, $base_url);
                return sprintf(
                    '<a href="%s">%s</a>',
                    esc_url($filter_url),
                    esc_html($display)
                );

            case 'generation':
                if (empty($item->generation_number)) {
                    return '&mdash;';
                }

                $gen_id     = (int) $item->generation_id;
                $filter_url = add_query_arg('filter_generation_id', $gen_id, $base_url);

                return sprintf(
                    '<a href="%s">%s</a>',
                    esc_url($filter_url),
                    sprintf(__('Gen %d', 'poke-hub'), (int) $item->generation_number)
                );

            case 'is_default':
                if (!$item->is_default) {
                    $filter_url = add_query_arg('filter_is_default', '0', $base_url);
                    return sprintf(
                        '<a href="%s">&mdash;</a>',
                        esc_url($filter_url)
                    );
                }

                $filter_url = add_query_arg('filter_is_default', '1', $base_url);

                return sprintf(
                    '<a href="%s"><span class="dashicons dashicons-yes"></span></a>',
                    esc_url($filter_url)
                );

            case 'release_normal':
                return !empty($release['normal']) ? esc_html($release['normal']) : '&mdash;';

            case 'release_shiny':
                return !empty($release['shiny']) ? esc_html($release['shiny']) : '&mdash;';

            case 'release_shadow':
                return !empty($release['shadow']) ? esc_html($release['shadow']) : '&mdash;';

            case 'release_mega':
                return !empty($release['mega']) ? esc_html($release['mega']) : '&mdash;';

            case 'release_dynamax':
                return !empty($release['dynamax']) ? esc_html($release['dynamax']) : '&mdash;';

            case 'regional':
                $is_regional = !empty($regional['is_regional']);
                if ($is_regional) {
                    $url = add_query_arg('filter_regional', '1', $base_url);
                    return sprintf(
                        '<a href="%s"><span class="dashicons dashicons-location"></span></a>',
                        esc_url($url)
                    );
                }
                $url = add_query_arg('filter_regional', '0', $base_url);
                return sprintf('<a href="%s">&mdash;</a>', esc_url($url));

            case 'variant_category':
                return $variant_category !== '' ? esc_html($variant_category) : '&mdash;';

            case 'variant_group':
                return $variant_group !== '' ? esc_html($variant_group) : '&mdash;';
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

        check_admin_referer('bulk-pokemon');

        if (empty($_REQUEST['ids']) || !is_array($_REQUEST['ids'])) {
            return;
        }

        if (!function_exists('pokehub_get_table')) {
            return;
        }

        global $wpdb;
        $table = pokehub_get_table('pokemon');

        $ids = array_map('intval', $_REQUEST['ids']);
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

    /**
     * Filtres au-dessus du tableau (formes, générations, types, variants…)
     */
    protected function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        if (!function_exists('pokehub_get_table')) {
            return;
        }

        global $wpdb;

        $gens_table      = pokehub_get_table('generations');
        $pokemon_table   = pokehub_get_table('pokemon');
        $types_table     = pokehub_get_table('pokemon_types');
        $types_rel       = pokehub_get_table('pokemon_type_links');
        $variants_table  = pokehub_get_table('pokemon_form_variants');

        // Gens
        $gens = [];
        if ($gens_table) {
            $gens = $wpdb->get_results("
                SELECT id, generation_number
                FROM {$gens_table}
                ORDER BY generation_number ASC
            ");
        }

        // Forms : variantes réellement utilisées par au moins un Pokémon
        $forms = [];
        if ($pokemon_table && $variants_table) {
            $forms = $wpdb->get_results("
                SELECT DISTINCT v.id AS variant_id, v.form_slug, v.label, v.category, v.`group`
                FROM {$pokemon_table} p
                INNER JOIN {$variants_table} v ON v.id = p.form_variant_id
                WHERE p.form_variant_id <> 0
                ORDER BY v.label ASC, v.form_slug ASC
            ");
        }

        // Types
        $types = [];
        if ($types_table) {
            $types = $wpdb->get_results("
                SELECT id, name_en, name_fr
                FROM {$types_table}
                ORDER BY name_fr ASC, name_en ASC
            ");
        }
        ?>
        <div class="alignleft actions">
            <!-- Génération -->
            <label class="screen-reader-text" for="filter_generation_id">
                <?php _e('Filter by generation', 'poke-hub'); ?>
            </label>
            <select name="filter_generation_id" id="filter_generation_id">
                <option value="0"><?php _e('All generations', 'poke-hub'); ?></option>
                <?php foreach ($gens as $gen) : ?>
                    <option value="<?php echo (int) $gen->id; ?>"
                        <?php selected($this->filters['generation_id'], (int) $gen->id); ?>>
                        <?php
                        echo sprintf(
                            __('Generation %d', 'poke-hub'),
                            (int) $gen->generation_number
                        );
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Form (variant) -->
            <label class="screen-reader-text" for="filter_form_variant_id">
                <?php _e('Filter by form', 'poke-hub'); ?>
            </label>
            <select name="filter_form_variant_id" id="filter_form_variant_id">
                <option value="0"><?php _e('All forms', 'poke-hub'); ?></option>
                <?php foreach ($forms as $form_row) : ?>
                    <?php
                    $display = $form_row->label !== '' ? $form_row->label : $form_row->form_slug;
                    $meta    = [];
                    if (!empty($form_row->category) && $form_row->category !== 'normal') {
                        $meta[] = $form_row->category;
                    }
                    if (!empty($form_row->group)) {
                        $meta[] = $form_row->group;
                    }
                    if ($meta) {
                        $display .= ' (' . implode(' – ', $meta) . ')';
                    }
                    ?>
                    <option value="<?php echo (int) $form_row->variant_id; ?>"
                        <?php selected($this->filters['form_variant_id'], (int) $form_row->variant_id); ?>>
                        <?php echo esc_html($display); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Type -->
            <?php if (!empty($types)) : ?>
                <label class="screen-reader-text" for="filter_type_id">
                    <?php _e('Filter by type', 'poke-hub'); ?>
                </label>
                <select name="filter_type_id" id="filter_type_id">
                    <option value="0"><?php _e('All types', 'poke-hub'); ?></option>
                    <?php foreach ($types as $type) : ?>
                        <?php
                        $t_name_fr = isset($type->name_fr) ? $type->name_fr : '';
                        $t_name_en = isset($type->name_en) ? $type->name_en : '';
                        $t_label   = $t_name_fr !== '' ? $t_name_fr : $t_name_en;
                        ?>
                        <option value="<?php echo (int) $type->id; ?>"
                            <?php selected($this->filters['type_id'], (int) $type->id); ?>>
                            <?php echo esc_html($t_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <!-- Default only ? -->
            <label class="screen-reader-text" for="filter_is_default">
                <?php _e('Filter by default form', 'poke-hub'); ?>
            </label>
            <select name="filter_is_default" id="filter_is_default">
                <option value=""><?php _e('All forms (default or not)', 'poke-hub'); ?></option>
                <option value="1" <?php selected($this->filters['is_default'], '1'); ?>>
                    <?php _e('Default forms only', 'poke-hub'); ?>
                </option>
                <option value="0" <?php selected($this->filters['is_default'], '0'); ?>>
                    <?php _e('Non-default forms only', 'poke-hub'); ?>
                </option>
            </select>

            <!-- Filtres variantes (texte libre sur extra JSON) -->
            <label class="screen-reader-text" for="filter_variant_category">
                <?php _e('Filter by variant category', 'poke-hub'); ?>
            </label>
            <input type="text"
                   name="filter_variant_category"
                   id="filter_variant_category"
                   value="<?php echo esc_attr($this->filters['variant_category']); ?>"
                   placeholder="<?php esc_attr_e('Variant category', 'poke-hub'); ?>" />

            <label class="screen-reader-text" for="filter_variant_group">
                <?php _e('Filter by variant group', 'poke-hub'); ?>
            </label>
            <input type="text"
                   name="filter_variant_group"
                   id="filter_variant_group"
                   value="<?php echo esc_attr($this->filters['variant_group']); ?>"
                   placeholder="<?php esc_attr_e('Variant group', 'poke-hub'); ?>" />

            <!-- Filtre régional (basé sur extra.regional.is_regional) -->
            <label class="screen-reader-text" for="filter_regional">
                <?php _e('Filter by regional', 'poke-hub'); ?>
            </label>
            <select name="filter_regional" id="filter_regional">
                <option value=""><?php _e('All (regional or not)', 'poke-hub'); ?></option>
                <option value="1" <?php selected($this->filters['regional'], '1'); ?>>
                    <?php _e('Regional only', 'poke-hub'); ?>
                </option>
                <option value="0" <?php selected($this->filters['regional'], '0'); ?>>
                    <?php _e('Non-regional only', 'poke-hub'); ?>
                </option>
            </select>

            <?php submit_button(__('Filter'), '', 'filter_action', false); ?>
        </div>
        <?php
    }

    /**
     * Prépare les items et la pagination
     */
    public function prepare_items() {
        if (!function_exists('pokehub_get_table')) {
            $this->items = [];
            return;
        }

        global $wpdb;

        $table_pokemon  = pokehub_get_table('pokemon');
        $table_gens     = pokehub_get_table('generations');
        $table_type_rel = pokehub_get_table('pokemon_type_links');
        $table_types    = pokehub_get_table('pokemon_types');

        // Screen option "Pokémon per page"
        $per_page     = $this->get_items_per_page('poke_hub_pokemon_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        // Tri demandé dans l’URL
        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'dex_number';
        $order   = isset($_GET['order']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) : 'ASC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        // Colonnes triables côté PHP (basées sur extra JSON)
        $php_sortable = [
            'release_normal',
            'release_shiny',
            'release_shadow',
            'release_mega',
            'release_dynamax',
            'regional',
            'variant_category',
            'variant_group',
        ];

        // Mapper 'name' → 'name_fr'
        if ($orderby === 'name') {
            $orderby = 'name_fr';
        }

        // Colonnes triables SQL + PHP
        $allowed_orderby = array_merge(['dex_number', 'name_fr'], $php_sortable);
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'dex_number';
        }

        // Recherche texte
        $search = isset($_REQUEST['s']) ? wp_unslash(trim($_REQUEST['s'])) : '';

        $where_parts = ['1=1'];
        $params      = [];

        if ($search !== '') {
            $where_parts[] = '(p.name_en LIKE %s OR p.name_fr LIKE %s OR p.slug LIKE %s OR p.dex_number = %d)';
            $like          = '%' . $wpdb->esc_like($search) . '%';
            $params[]      = $like;
            $params[]      = $like;
            $params[]      = $like;
            $params[]      = (int) $search;
        }

        // Filtres : génération
        if (!empty($this->filters['generation_id'])) {
            $where_parts[] = 'p.generation_id = %d';
            $params[]      = $this->filters['generation_id'];
        }

        // Filtres : forme (variant_id)
        if (!empty($this->filters['form_variant_id'])) {
            $where_parts[] = 'p.form_variant_id = %d';
            $params[]      = $this->filters['form_variant_id'];
        }

        // Filtres : is_default
        if ($this->filters['is_default'] === '1') {
            $where_parts[] = 'p.is_default = 1';
        } elseif ($this->filters['is_default'] === '0') {
            $where_parts[] = 'p.is_default = 0';
        }

        // Filtres : variant_category / variant_group via LIKE sur JSON
        if ($this->filters['variant_category'] !== '') {
            $needle        = $wpdb->esc_like($this->filters['variant_category']);
            $where_parts[] = 'p.extra LIKE %s';
            $params[]      = '%"variant_category":"' . $needle . '%';
        }

        if ($this->filters['variant_group'] !== '') {
            $needle        = $wpdb->esc_like($this->filters['variant_group']);
            $where_parts[] = 'p.extra LIKE %s';
            $params[]      = '%"variant_group":"' . $needle . '%';
        }

        // Filtre régional (extra.regional.is_regional)
        if ($this->filters['regional'] === '1') {
            $where_parts[] = 'p.extra LIKE %s';
            $params[]      = '%"regional":{"is_regional":true%';
        } elseif ($this->filters['regional'] === '0') {
            // "Non-régional" = soit pas de clé regional, soit is_regional = false
            // On simplifie en excluant les JSON où is_regional:true
            $where_parts[] = 'p.extra NOT LIKE %s';
            $params[]      = '%"regional":{"is_regional":true%';
        }

        // Jointures
        $join = "
            FROM {$table_pokemon} AS p
            LEFT JOIN {$table_gens} AS g ON g.id = p.generation_id
        ";

        // Filtres : type (via table de relation)
        if ($this->filters['type_id'] > 0 && $table_type_rel && $table_types) {
            $join .= " INNER JOIN {$table_type_rel} AS ptr ON ptr.pokemon_id = p.id";
            $where_parts[] = 'ptr.type_id = %d';
            $params[]      = $this->filters['type_id'];
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where_parts);

        // Total items
        $sql_count = "SELECT COUNT(*) {$join} {$where_sql}";

        if (!empty($params)) {
            $total_items = (int) $wpdb->get_var(
                $wpdb->prepare($sql_count, $params)
            );
        } else {
            $total_items = (int) $wpdb->get_var($sql_count);
        }

        /**
         * CAS 1 : tri sur release_*, regional, variant_*
         * → tri PHP
         */
        if (in_array($orderby, $php_sortable, true)) {

            // Récupérer tous les enregistrements filtrés (sans LIMIT)
            $sql_items_all = "
                SELECT 
                    p.*,
                    g.generation_number
                {$join}
                {$where_sql}
            ";

            if (!empty($params)) {
                $items = $wpdb->get_results(
                    $wpdb->prepare($sql_items_all, $params)
                );
            } else {
                $items = $wpdb->get_results($sql_items_all);
            }

            // Tri PHP
            usort($items, function($a, $b) use ($orderby, $order) {
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

                $release_a          = $extra_a['release']          ?? [];
                $release_b          = $extra_b['release']          ?? [];
                $regional_a         = $extra_a['regional']         ?? [];
                $regional_b         = $extra_b['regional']         ?? [];
                $variant_category_a = $extra_a['variant_category'] ?? '';
                $variant_category_b = $extra_b['variant_category'] ?? '';
                $variant_group_a    = $extra_a['variant_group']    ?? '';
                $variant_group_b    = $extra_b['variant_group']    ?? '';

                switch ($orderby) {
                    case 'release_normal':
                        $val_a = $release_a['normal'] ?? '';
                        $val_b = $release_b['normal'] ?? '';
                        break;
                    case 'release_shiny':
                        $val_a = $release_a['shiny'] ?? '';
                        $val_b = $release_b['shiny'] ?? '';
                        break;
                    case 'release_shadow':
                        $val_a = $release_a['shadow'] ?? '';
                        $val_b = $release_b['shadow'] ?? '';
                        break;
                    case 'release_mega':
                        $val_a = $release_a['mega'] ?? '';
                        $val_b = $release_b['mega'] ?? '';
                        break;
                    case 'release_dynamax':
                        $val_a = $release_a['dynamax'] ?? '';
                        $val_b = $release_b['dynamax'] ?? '';
                        break;
                    case 'regional':
                        $val_a = !empty($regional_a['is_regional']) ? 1 : 0;
                        $val_b = !empty($regional_b['is_regional']) ? 1 : 0;
                        break;
                    case 'variant_category':
                        $val_a = $variant_category_a;
                        $val_b = $variant_category_b;
                        break;
                    case 'variant_group':
                        $val_a = $variant_group_a;
                        $val_b = $variant_group_b;
                        break;
                    default:
                        $val_a = '';
                        $val_b = '';
                }

                if ($val_a == $val_b) {
                    return 0;
                }

                if ($order === 'DESC') {
                    return ($val_a < $val_b) ? 1 : -1;
                }

                return ($val_a < $val_b) ? -1 : 1;
            });

            // Pagination manuelle
            $per_page     = $this->get_items_per_page('poke_hub_pokemon_per_page', 20);
            $current_page = $this->get_pagenum();
            $offset       = ($current_page - 1) * $per_page;

            $items = array_slice($items, $offset, $per_page);

            $this->items = $items;

            // Colonnes / options d’écran
            $columns  = $this->get_columns();
            $hidden   = get_hidden_columns($this->screen);
            $sortable = $this->get_sortable_columns();
            $this->_column_headers = [$columns, $hidden, $sortable];

            $this->set_pagination_args([
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => max(1, ceil($total_items / $per_page)),
            ]);

            // Construire la map des variantes pour la colonne "form"
            $this->build_variant_map();

            return;
        }

        /**
         * CAS 2 : tri SQL normal (dex_number, name_fr, etc.)
         */
        $sql_items = "
            SELECT 
                p.*,
                g.generation_number
            {$join}
            {$where_sql}
            ORDER BY {$orderby} {$order}
            LIMIT %d OFFSET %d
        ";

        $params_items   = $params;
        $params_items[] = $per_page;
        $params_items[] = $offset;

        if (!empty($params)) {
            $this->items = $wpdb->get_results(
                $wpdb->prepare($sql_items, $params_items)
            );
        } else {
            $this->items = $wpdb->get_results(
                $wpdb->prepare($sql_items, $per_page, $offset)
            );
        }

        // Colonnes / options d’écran
        $columns  = $this->get_columns();
        $hidden   = get_hidden_columns($this->screen);
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => max(1, ceil($total_items / $per_page)),
        ]);

        // Construire la map des variantes pour la colonne "form"
        $this->build_variant_map();
    }

    /**
     * Construit une map form_variant_id => objet variante pour les items courants
     */
    protected function build_variant_map() {
        if (!function_exists('pokehub_get_table')) {
            return;
        }

        global $wpdb;
        $variants_table = pokehub_get_table('pokemon_form_variants');

        if (!$variants_table) {
            return;
        }

        $ids = [];
        foreach ($this->items as $item) {
            if (isset($item->form_variant_id) && (int) $item->form_variant_id > 0) {
                $ids[] = (int) $item->form_variant_id;
            }
        }

        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = "
            SELECT id, form_slug, category, `group`, label
            FROM {$variants_table}
            WHERE id IN ($placeholders)
        ";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $ids));

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->id] = $row;
        }

        $this->variant_map = $map;
    }
}

/**
 * Gestion delete (action simple)
 */
function poke_hub_pokemon_handle_pokemon_delete() {
    if (!is_admin()) {
        return;
    }

    if (empty($_GET['page']) || $_GET['page'] !== 'poke-hub-pokemon') {
        return;
    }

    if (empty($_GET['ph_section']) || $_GET['ph_section'] !== 'pokemon') {
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

    check_admin_referer('poke_hub_delete_pokemon_' . $id);

    global $wpdb;
    $table_pokemon = pokehub_get_table('pokemon');

    // Suppression du Pokémon
    $wpdb->delete($table_pokemon, ['id' => $id], ['%d']);

    // Nettoyage des liens Pokémon ↔ attaques
    $table_pokemon_attack_links = pokehub_get_table('pokemon_attack_links');
    if ($table_pokemon_attack_links) {
        $wpdb->delete(
            $table_pokemon_attack_links,
            ['pokemon_id' => $id],
            ['%d']
        );
    }

    // Nettoyage des liens Pokémon ↔ types
    $table_pokemon_type_links = pokehub_get_table('pokemon_type_links');
    if ($table_pokemon_type_links) {
        $wpdb->delete(
            $table_pokemon_type_links,
            ['pokemon_id' => $id],
            ['%d']
        );
    }

    // On pourrait aussi nettoyer les lignes d'évolutions où ce Pokémon apparaît (base / target)
    $table_evolutions = pokehub_get_table('pokemon_evolutions');
    if ($table_evolutions) {
        $wpdb->delete(
            $table_evolutions,
            ['base_pokemon_id' => $id],
            ['%d']
        );
        $wpdb->delete(
            $table_evolutions,
            ['target_pokemon_id' => $id],
            ['%d']
        );
    }

    $redirect = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'pokemon',
            'ph_msg'     => 'deleted',
        ],
        admin_url('admin.php')
    );

    wp_redirect($redirect);
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_pokemon_delete');

/**
 * Prépare les évolutions envoyées par le formulaire (POST) pour un Pokémon.
 *
 * @param array $raw Données brutes issues de $_POST['evolutions'] (déjà wp_unslash).
 * @return array Liste de lignes normalisées.
 */
function poke_hub_pokemon_prepare_evolutions_from_request(array $raw): array {
    $rows = [];

    if (empty($raw) || !is_array($raw)) {
        return $rows;
    }

    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $target_id = isset($entry['target_pokemon_id']) ? (int) $entry['target_pokemon_id'] : 0;
        if ($target_id <= 0) {
            // Pas de cible → on ignore la ligne
            continue;
        }

        // --- Normalisation du genre ---
        $gender_raw = isset($entry['gender_requirement'])
            ? sanitize_text_field($entry['gender_requirement'])
            : '';
        $gender_requirement = strtoupper(trim($gender_raw));
        if (!in_array($gender_requirement, ['MALE', 'FEMALE'], true)) {
            $gender_requirement = '';
        }

        // --- Normalisation du time_of_day (day / night / dusk / full_moon) ---
        $time_raw = isset($entry['time_of_day'])
            ? sanitize_text_field($entry['time_of_day'])
            : '';
        $time_of_day = strtolower(trim($time_raw));

        // Quelques alias tolérés (au cas où)
        if ($time_of_day === 'fullmoon') {
            $time_of_day = 'full_moon';
        }

        $allowed_times = ['day', 'night', 'dusk', 'full_moon'];
        if (!in_array($time_of_day, $allowed_times, true)) {
            $time_of_day = '';
        }

        $rows[] = [
            'target_pokemon_id'       => $target_id,
            'candy_cost'              => isset($entry['candy_cost'])             ? max(0, (int) $entry['candy_cost']) : 0,
            'candy_cost_purified'     => isset($entry['candy_cost_purified'])    ? max(0, (int) $entry['candy_cost_purified']) : 0,
            'is_trade_evolution'      => !empty($entry['is_trade_evolution'])      ? 1 : 0,
            'no_candy_cost_via_trade' => !empty($entry['no_candy_cost_via_trade']) ? 1 : 0,
            'is_random_evolution'     => !empty($entry['is_random_evolution'])     ? 1 : 0,
            'method'                  => isset($entry['method'])                 ? sanitize_text_field($entry['method']) : '',
            'item_requirement_slug'   => isset($entry['item_requirement_slug'])  ? sanitize_text_field($entry['item_requirement_slug']) : '',
            'item_requirement_cost'   => isset($entry['item_requirement_cost'])  ? max(0, (int) $entry['item_requirement_cost']) : 0,
            'lure_item_slug'          => isset($entry['lure_item_slug'])         ? sanitize_text_field($entry['lure_item_slug']) : '',
            'weather_requirement_slug'=> isset($entry['weather_requirement_slug']) ? sanitize_text_field($entry['weather_requirement_slug']) : '',
            'gender_requirement'      => $gender_requirement,
            'time_of_day'             => $time_of_day,
            'priority'                => isset($entry['priority'])               ? (int) $entry['priority'] : 0,
            'quest_template_id'       => isset($entry['quest_template_id'])      ? sanitize_text_field($entry['quest_template_id']) : '',
        ];
    }

    return $rows;
}

/**
 * Synchronise les évolutions d'un Pokémon dans la table pokemon_evolutions.
 *
 * Stratégie : on SUPPRIME toutes les lignes existantes pour (base_pokemon_id, base_form_variant_id)
 * puis on INSERT les nouvelles.
 *
 * Deux modes :
 * - mode 'import' : données venant d'un import / normalisation (proto IDs, items, extra JSON, resolver, etc.)
 * - mode 'form'   : données venant d'un formulaire d’édition admin (target_pokemon_id déjà réel, pas d'items créés, pas d'extra).
 *
 * @param int   $base_pokemon_id
 * @param int   $base_form_variant_id
 * @param array $rows    En mode 'import' = tes $branches normalisés, en mode 'form' = tes $rows du form.
 * @param array $options {
 *     @type string        $mode     'import' (par défaut) ou 'form'.
 *     @type array         $tables   Optionnel. ['pokemon_evolutions' => '...', 'pokemon' => '...'].
 *                                   Si absent, on tentera pokehub_get_table().
 *     @type callable|null $resolver Requis en mode 'import' :
 *                                   function(string $target_id_proto, string $target_form_proto): array{int,int}
 * }
 */
function poke_hub_pokemon_sync_pokemon_evolutions(
    int $base_pokemon_id,
    int $base_form_variant_id,
    array $rows,
    array $options = []
): void {
    if ($base_pokemon_id <= 0) {
        return;
    }

    $mode    = $options['mode'] ?? 'import';
    $tables  = $options['tables'] ?? [];
    $resolver = $options['resolver'] ?? null;

    global $wpdb;

    // -------------------------------------------------
    // 1) Résolution des tables
    // -------------------------------------------------
    $evo_table = $tables['pokemon_evolutions'] ?? '';

    if (empty($evo_table)) {
        if (!function_exists('pokehub_get_table')) {
            return;
        }
        $evo_table = pokehub_get_table('pokemon_evolutions');
    }

    if (empty($evo_table)) {
        return;
    }

    $pokemon_table = '';
    if ($mode === 'form') {
        // En mode form, on a besoin d'un mapping vers form_variant_id
        $pokemon_table = $tables['pokemon'] ?? '';
        if (empty($pokemon_table) && function_exists('pokehub_get_table')) {
            $pokemon_table = pokehub_get_table('pokemon');
        }
        if (empty($pokemon_table)) {
            return;
        }
    }

    // -------------------------------------------------
    // 2) Suppression des lignes existantes
    // -------------------------------------------------
    $wpdb->delete(
        $evo_table,
        [
            'base_pokemon_id'      => $base_pokemon_id,
            'base_form_variant_id' => $base_form_variant_id,
        ],
        ['%d', '%d']
    );

    if (empty($rows)) {
        // Rien à insérer
        return;
    }

    // -------------------------------------------------
    // 3) Préparation contextuelle selon le mode
    // -------------------------------------------------
    $form_variant_map = [];

    if ($mode === 'form') {
        // On prépare un mapping target_pokemon_id => target_form_variant_id
        $target_ids = array_unique(
            array_map(
                static function ($row) {
                    return (int) ($row['target_pokemon_id'] ?? 0);
                },
                $rows
            )
        );
        $target_ids = array_filter($target_ids);

        if (!empty($target_ids)) {
            $placeholders = implode(',', array_fill(0, count($target_ids), '%d'));
            $query        = "
                SELECT id, form_variant_id
                FROM {$pokemon_table}
                WHERE id IN ({$placeholders})
            ";
            $results = $wpdb->get_results(
                $wpdb->prepare($query, $target_ids)
            );

            if ($results) {
                foreach ($results as $r) {
                    $form_variant_map[(int) $r->id] = (int) $r->form_variant_id;
                }
            }
        }
    }

    // -------------------------------------------------
    // 4) Boucle d'insertion
    // -------------------------------------------------
    foreach ($rows as $row) {
        // -------------------------
        // 4.1 Résolution des IDs cibles
        // -------------------------
        $target_pokemon_id      = 0;
        $target_form_variant_id = 0;

        if ($mode === 'import') {
            // On attend un format du style $branch, avec proto IDs
            if (!is_callable($resolver)) {
                // Pas de resolver => impossible de continuer proprement
                continue;
            }

            $target_id_proto   = (string) ($row['target_id_proto']   ?? '');
            $target_form_proto = (string) ($row['target_form_proto'] ?? '');

            [$target_pokemon_id, $target_form_variant_id] = $resolver(
                $target_id_proto,
                $target_form_proto
            );
        } else { // mode 'form'
            $target_pokemon_id      = (int) ($row['target_pokemon_id'] ?? 0);
            $target_form_variant_id = $form_variant_map[$target_pokemon_id] ?? 0;
        }

        if ($target_pokemon_id <= 0) {
            continue;
        }

        // -------------------------
        // 4.2 Items & leurres
        // -------------------------
        $item_id        = 0;
        $item_slug      = '';
        $lure_item_id   = 0;
        $lure_item_slug = '';

        $extra_json = null;

        if ($mode === 'import') {
            // Mode import = on gère les protos, les items et l'extra

            // --- Item d'évolution ---
            $item_requirement_proto = (string) ($row['item_requirement'] ?? '');
            $lure_requirement_proto = (string) ($row['lure_item_requirement'] ?? '');

            if ($item_requirement_proto !== '' && function_exists('poke_hub_items_get_or_create_from_proto')) {
                $item = poke_hub_items_get_or_create_from_proto(
                    $item_requirement_proto,
                    [],
                    'evolution_item',
                    '',
                    'pokemon_go'
                );

                if (is_array($item) && !empty($item['id'])) {
                    $item_id   = (int) $item['id'];
                    $item_slug = (string) ($item['slug'] ?? '');
                }
            }

            if ($item_slug === '' && !empty($row['item_requirement_slug'])) {
                $item_slug = (string) $row['item_requirement_slug'];
            }
            if ($item_slug === '' && $item_requirement_proto !== '') {
                $item_slug = sanitize_title(strtolower($item_requirement_proto));
            }

            // --- Leurre ---
            if ($lure_requirement_proto !== '' && function_exists('poke_hub_items_get_or_create_from_proto')) {
                $lure = poke_hub_items_get_or_create_from_proto(
                    $lure_requirement_proto,
                    [],
                    'lure',
                    '',
                    'pokemon_go'
                );

                if (is_array($lure) && !empty($lure['id'])) {
                    $lure_item_id   = (int) $lure['id'];
                    $lure_item_slug = (string) ($lure['slug'] ?? '');
                }
            }

            if ($lure_item_slug === '' && !empty($row['lure_item_slug'])) {
                $lure_item_slug = (string) $row['lure_item_slug'];
            }
            if ($lure_item_slug === '' && $lure_requirement_proto !== '') {
                $lure_item_slug = sanitize_title(strtolower($lure_requirement_proto));
            }

            // --- Extra JSON ---
            $extra = $row['extra'] ?? null;
            if (is_array($extra) && !empty($extra)) {
                $extra_json = wp_json_encode($extra);
            }
        } else {
            // Mode form : on ne gère PAS la création d'items
            $item_slug      = (string) ($row['item_requirement_slug'] ?? '');
            $lure_item_slug = (string) ($row['lure_item_slug'] ?? '');
            $extra_json     = null;
        }

        // -------------------------
        // 4.3 Données finales
        // -------------------------
        $data = [
            'base_pokemon_id'        => $base_pokemon_id,
            'target_pokemon_id'      => $target_pokemon_id,
            'base_form_variant_id'   => $base_form_variant_id,
            'target_form_variant_id' => $target_form_variant_id,

            'candy_cost'             => (int) ($row['candy_cost'] ?? 0),
            'candy_cost_purified'    => (int) ($row['candy_cost_purified'] ?? 0),

            'is_trade_evolution'     => !empty($row['is_trade_evolution']) ? 1 : 0,
            'no_candy_cost_via_trade'=> !empty($row['no_candy_cost_via_trade']) ? 1 : 0,
            'is_random_evolution'    => !empty($row['is_random_evolution']) ? 1 : 0,

            'method'                 => (string) ($row['method'] ?? ''),

            'item_requirement_slug'  => $item_slug,
            'item_requirement_cost'  => (int) ($row['item_requirement_cost'] ?? 0),
            'item_id'                => $item_id,

            'lure_item_slug'         => $lure_item_slug,
            'lure_item_id'           => $lure_item_id,

            'weather_requirement_slug'=> (string) ($row['weather_requirement_slug'] ?? ''),
            'gender_requirement'     => (string) ($row['gender_requirement'] ?? ''),
            'time_of_day'            => (string) ($row['time_of_day'] ?? ''),

            'priority'               => (int) ($row['priority'] ?? 0),

            'quest_template_id'      => (string) ($row['quest_template_id'] ?? ''),

            'extra'                  => $extra_json,
        ];

        $format = [
            '%d', '%d',   // base_pokemon_id, target_pokemon_id
            '%d', '%d',   // base_form_variant_id, target_form_variant_id
            '%d', '%d',   // candy_cost, candy_cost_purified
            '%d', '%d', '%d', // is_trade_evolution, no_candy_cost_via_trade, is_random_evolution
            '%s',         // method

            '%s',         // item_requirement_slug
            '%d',         // item_requirement_cost
            '%d',         // item_id

            '%s',         // lure_item_slug
            '%d',         // lure_item_id

            '%s',         // weather_requirement_slug
            '%s',         // gender_requirement
            '%s',         // time_of_day

            '%d',         // priority
            '%s',         // quest_template_id
            '%s',         // extra
        ];

        $wpdb->insert($evo_table, $data, $format);
    }
}

/**
 * Traitement formulaire Pokémon (add / update)
 */
function poke_hub_pokemon_handle_pokemon_form() {
    if (!is_admin()) {
        return;
    }

    if (empty($_POST['poke_hub_pokemon_action'])) {
        return;
    }

    if (empty($_POST['ph_section']) || $_POST['ph_section'] !== 'pokemon') {
        return;
    }

    $action = sanitize_text_field($_POST['poke_hub_pokemon_action']);
    if (!in_array($action, ['add_pokemon', 'update_pokemon'], true)) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('poke_hub_pokemon_edit_pokemon');

    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    $redirect_base = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'pokemon',
        ],
        admin_url('admin.php')
    );

    $table               = pokehub_get_table('pokemon');
    $form_variants_table = pokehub_get_table('pokemon_form_variants');

    // ---------- récupération / sanitisation ----------

    $id         = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $is_update  = ($action === 'update_pokemon' && $id > 0);

    $dex_number = isset($_POST['dex_number']) ? (int) $_POST['dex_number'] : 0;

    // Noms principaux en base
    $name_en = isset($_POST['name_en']) ? sanitize_text_field(wp_unslash($_POST['name_en'])) : '';
    $name_fr = isset($_POST['name_fr']) ? sanitize_text_field(wp_unslash($_POST['name_fr'])) : '';

    // Compat rétro : si les deux sont vides mais un champ `name` existe
    if ($name_en === '' && $name_fr === '' && !empty($_POST['name'])) {
        $fallback = sanitize_text_field(wp_unslash($_POST['name']));
        $name_en  = $fallback;
    }

    $slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';

    // Variante choisie via ID (et plus via slug)
    $form_variant_id   = isset($_POST['form_variant_id']) ? (int) $_POST['form_variant_id'] : 0;
    $variant_category  = '';
    $variant_group     = '';
    $variant_form_slug = '';

    if ($form_variant_id > 0 && $form_variants_table) {
        $row_variant = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT form_slug, category, `group` FROM {$form_variants_table} WHERE id = %d",
                $form_variant_id
            )
        );

        if ($row_variant) {
            $variant_category  = (string) $row_variant->category;
            $variant_group     = (string) $row_variant->group;
            $variant_form_slug = (string) $row_variant->form_slug;
        }
    }

    $is_default    = !empty($_POST['is_default']) ? 1 : 0;
    $generation_id = isset($_POST['generation_id']) ? (int) $_POST['generation_id'] : 0;

    $base_atk = isset($_POST['base_atk']) ? (int) $_POST['base_atk'] : 0;
    $base_def = isset($_POST['base_def']) ? (int) $_POST['base_def'] : 0;
    $base_sta = isset($_POST['base_sta']) ? (int) $_POST['base_sta'] : 0;

    // extra: infos générales + GO
    $about    = isset($_POST['about']) ? wp_kses_post(wp_unslash($_POST['about'])) : '';
    $category = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';

    $gender_male   = isset($_POST['gender_male']) ? (float) str_replace(',', '.', $_POST['gender_male']) : 0;
    $gender_female = isset($_POST['gender_female']) ? (float) str_replace(',', '.', $_POST['gender_female']) : 0;

    // Noms localisés (on garde la structure extra->names pour les autres langues)
    $names = [
        'fr' => isset($_POST['name_fr']) ? sanitize_text_field(wp_unslash($_POST['name_fr'])) : $name_fr,
        'en' => isset($_POST['name_en']) ? sanitize_text_field(wp_unslash($_POST['name_en'])) : $name_en,
        'de' => isset($_POST['name_de']) ? sanitize_text_field(wp_unslash($_POST['name_de'])) : '',
        'it' => isset($_POST['name_it']) ? sanitize_text_field(wp_unslash($_POST['name_it'])) : '',
        'es' => isset($_POST['name_es']) ? sanitize_text_field(wp_unslash($_POST['name_es'])) : '',
        'ja' => isset($_POST['name_ja']) ? sanitize_text_field(wp_unslash($_POST['name_ja'])) : '',
    ];

    // GO : stats complémentaires
    $go_height_m          = isset($_POST['go_height_m']) ? (float) str_replace(',', '.', $_POST['go_height_m']) : 0;
    $go_weight_kg         = isset($_POST['go_weight_kg']) ? (float) str_replace(',', '.', $_POST['go_weight_kg']) : 0;
    $go_catch_rate        = isset($_POST['go_catch_rate']) ? (float) str_replace(',', '.', $_POST['go_catch_rate']) : null;
    $go_flee_rate         = isset($_POST['go_flee_rate']) ? (float) str_replace(',', '.', $_POST['go_flee_rate']) : null;
    $go_egg_distance_km   = (isset($_POST['go_egg_distance_km']) && $_POST['go_egg_distance_km'] !== '')
        ? (float) str_replace(',', '.', $_POST['go_egg_distance_km'])
        : null;
    $go_buddy_distance_km = isset($_POST['go_buddy_distance_km']) ? (float) str_replace(',', '.', $_POST['go_buddy_distance_km']) : 0;
    $go_second_stardust   = isset($_POST['go_second_stardust']) ? (int) $_POST['go_second_stardust'] : 0;
    $go_second_candy      = isset($_POST['go_second_candy']) ? (int) $_POST['go_second_candy'] : 0;

    // Flags trade / shadow / purification / buddy mega / encounter probs
    $is_tradable      = !empty($_POST['is_tradable']) ? 1 : 0;
    $is_transferable  = !empty($_POST['is_transferable']) ? 1 : 0;
    $has_shadow       = !empty($_POST['has_shadow']) ? 1 : 0;
    $has_purified     = !empty($_POST['has_purified']) ? 1 : 0;
    $shadow_pur_stardust = isset($_POST['shadow_purification_stardust']) ? (int) $_POST['shadow_purification_stardust'] : 0;
    $shadow_pur_candy    = isset($_POST['shadow_purification_candy']) ? (int) $_POST['shadow_purification_candy'] : 0;

    $buddy_mega_energy_award = isset($_POST['buddy_mega_energy_award'])
        ? (int) $_POST['buddy_mega_energy_award']
        : 0;

    $attack_probability = isset($_POST['attack_probability'])
        ? (float) str_replace(',', '.', $_POST['attack_probability'])
        : 0.0;
    $dodge_probability = isset($_POST['dodge_probability'])
        ? (float) str_replace(',', '.', $_POST['dodge_probability'])
        : 0.0;

    // Releases
    $release_normal     = isset($_POST['release_normal']) ? sanitize_text_field(wp_unslash($_POST['release_normal'])) : '';
    $release_shiny      = isset($_POST['release_shiny']) ? sanitize_text_field(wp_unslash($_POST['release_shiny'])) : '';
    $release_shadow     = isset($_POST['release_shadow']) ? sanitize_text_field(wp_unslash($_POST['release_shadow'])) : '';
    $release_mega       = isset($_POST['release_mega']) ? sanitize_text_field(wp_unslash($_POST['release_mega'])) : '';
    $release_dynamax    = isset($_POST['release_dynamax']) ? sanitize_text_field(wp_unslash($_POST['release_dynamax'])) : '';
    $release_gigantamax = isset($_POST['release_gigantamax']) ? sanitize_text_field(wp_unslash($_POST['release_gigantamax'])) : '';

    // Régional
    $regional_is_regional  = !empty($_POST['regional_is_regional']);
    $regional_description  = isset($_POST['regional_description']) ? wp_kses_post(wp_unslash($_POST['regional_description'])) : '';
    $regional_map_image_id = isset($_POST['regional_map_image_id']) ? (int) $_POST['regional_map_image_id'] : 0;

    // ---------- Attaques (fast / charged) ----------

    $fast_attacks_raw = (isset($_POST['fast_moves']) && is_array($_POST['fast_moves']))
        ? wp_unslash($_POST['fast_moves'])
        : [];

    $charged_attacks_raw = (isset($_POST['charged_moves']) && is_array($_POST['charged_moves']))
        ? wp_unslash($_POST['charged_moves'])
        : [];

    // ---------- Evolutions ----------

    $evolutions_raw = (isset($_POST['evolutions']) && is_array($_POST['evolutions']))
        ? wp_unslash($_POST['evolutions'])
        : [];


    // On s’assure d’avoir un slug
    if ($slug === '') {
        $base = $name_en !== '' ? $name_en : $name_fr;
        if ($base !== '') {
            $slug = sanitize_title($base);
        }
    }

    // ---------- Extra : on part de l’existant (pour ne pas écraser les infos Game Master, etc.) ----------

    $existing_extra = [];
    if ($is_update) {
        $row_existing = $wpdb->get_row(
            $wpdb->prepare("SELECT extra FROM {$table} WHERE id = %d", $id)
        );
        if ($row_existing && !empty($row_existing->extra)) {
            $decoded_extra = json_decode($row_existing->extra, true);
            if (is_array($decoded_extra)) {
                $existing_extra = $decoded_extra;
            }
        }
    }

    $extra = is_array($existing_extra) ? $existing_extra : [];

    // Infos génériques
    $extra['category'] = $category;
    $extra['about']    = $about;
    $extra['gender']   = [
        'male'   => $gender_male,
        'female' => $gender_female,
    ];
    $extra['names']    = $names;

    // Release / régional
    $extra['release'] = [
        'normal'     => $release_normal,
        'shiny'      => $release_shiny,
        'shadow'     => $release_shadow,
        'mega'       => $release_mega,
        'dynamax'    => $release_dynamax,
        'gigantamax' => $release_gigantamax,
    ];

    $extra['regional'] = [
        'is_regional'  => $regional_is_regional,
        'description'  => $regional_description,
        'map_image_id' => $regional_map_image_id,
    ];

    // Infos de variante (génériques, pas spécifiques à un jeu)
    $extra['variant_category']   = $variant_category;
    $extra['variant_group']      = $variant_group;
    $extra['variant_id']         = $form_variant_id;
    $extra['variant_form_slug']  = $variant_form_slug;

    // ---------- Bloc par jeu : Pokémon GO ----------

    $games   = is_array($extra['games'] ?? null) ? $extra['games'] : [];
    $game_go = is_array($games['pokemon_go'] ?? null) ? $games['pokemon_go'] : [];

    // Pokédex GO
    $go_pokedex = is_array($game_go['pokedex'] ?? null)
        ? $game_go['pokedex']
        : (is_array($extra['pokedex'] ?? null) ? $extra['pokedex'] : []);
    $go_pokedex['height_m']  = $go_height_m;
    $go_pokedex['weight_kg'] = $go_weight_kg;

    // Buddy GO
    $go_buddy = is_array($game_go['buddy'] ?? null)
        ? $game_go['buddy']
        : (is_array($extra['buddy'] ?? null) ? $extra['buddy'] : []);
    $go_buddy['km_buddy_distance']      = $go_buddy_distance_km;
    $go_buddy['buddy_mega_energy_award'] = $buddy_mega_energy_award;

    // Encounter GO
    $go_encounter = is_array($game_go['encounter'] ?? null)
        ? $game_go['encounter']
        : (is_array($extra['encounter'] ?? null) ? $extra['encounter'] : []);
    $go_encounter['base_capture_rate']  = $go_catch_rate;
    $go_encounter['base_flee_rate']     = $go_flee_rate;
    $go_encounter['attack_probability'] = $attack_probability;
    $go_encounter['dodge_probability']  = $dodge_probability;

    // Egg distance
    $game_go['egg_distance_km'] = $go_egg_distance_km;

    // Second move
    $go_second       = is_array($game_go['second_move'] ?? null) ? $game_go['second_move'] : (is_array($extra['second_move'] ?? null) ? $extra['second_move'] : []);
    $go_second_cost  = is_array($go_second['cost'] ?? null) ? $go_second['cost'] : [];
    $go_second_cost['stardust'] = $go_second_stardust;
    $go_second_cost['candy']    = $go_second_candy;
    $go_second['cost'] = $go_second_cost;

    // Shadow / purification
    $shadow_extra = is_array($extra['shadow'] ?? null) ? $extra['shadow'] : [];
    $shadow_extra['has_shadow']   = (bool) $has_shadow;
    $shadow_extra['has_purified'] = (bool) $has_purified;
    $shadow_extra['stardust']     = $shadow_pur_stardust;
    $shadow_extra['candy']        = $shadow_pur_candy;

    // On laisse intacts shadow_move / purified_move dans $shadow_extra si déjà présents
    $extra['shadow']          = $shadow_extra;
    $game_go['shadow']        = $shadow_extra;

    // Trade flags
    $trade_extra = is_array($extra['trade'] ?? null) ? $extra['trade'] : [];
    $trade_extra['is_tradable']     = (bool) $is_tradable;
    $trade_extra['is_transferable'] = (bool) $is_transferable;

    $extra['trade']          = $trade_extra;
    $game_go['trade']        = $trade_extra;

    // CP sets (si helper dispo)
    $cp_sets = is_array($game_go['cp_sets'] ?? null) ? $game_go['cp_sets'] : [];
    if (function_exists('poke_hub_pokemon_build_cp_sets_for_pokemon')
        && $base_atk > 0 && $base_def > 0 && $base_sta > 0
    ) {
        $cp_sets = poke_hub_pokemon_build_cp_sets_for_pokemon(
            $base_atk,
            $base_def,
            $base_sta
        );
    }

    // On pose tout dans le bloc GO
    $game_go['pokedex']     = $go_pokedex;
    $game_go['buddy']       = $go_buddy;
    $game_go['encounter']   = $go_encounter;
    $game_go['second_move'] = $go_second;
    $game_go['cp_sets']     = $cp_sets;

    // Pour compatibilité : quelques dupes au niveau racine
    $extra['pokedex']            = $go_pokedex;
    $extra['buddy']              = $go_buddy;
    $extra['encounter']          = $go_encounter;
    $extra['second_move']        = $go_second;
    $extra['egg_distance_km']    = $go_egg_distance_km;
    $extra['max_cp']             = $cp_sets['max_cp'] ?? ($game_go['max_cp'] ?? []);
    $extra['game_key']           = $extra['game_key'] ?? 'pokemon_go';

    $games['pokemon_go'] = $game_go;
    $extra['games']      = $games;

    // ---------- Data communs ----------

    $data_common = [
        'dex_number'      => $dex_number,
        'name_en'         => $name_en,
        'name_fr'         => $name_fr,
        'slug'            => $slug,
        'form_variant_id' => $form_variant_id,
        'is_default'      => $is_default,
        'generation_id'   => $generation_id,
        'base_atk'        => $base_atk,
        'base_def'        => $base_def,
        'base_sta'        => $base_sta,

        // Flags / coûts shadow / buddy mega / encounter probs (nouvelles colonnes)
        'is_tradable'                    => $is_tradable,
        'is_transferable'                => $is_transferable,
        'has_shadow'                     => $has_shadow,
        'has_purified'                   => $has_purified,
        'shadow_purification_stardust'   => $shadow_pur_stardust,
        'shadow_purification_candy'      => $shadow_pur_candy,
        'buddy_walked_mega_energy_award' => $buddy_mega_energy_award,
        'dodge_probability'              => $dodge_probability,
        'attack_probability'             => $attack_probability,

        'extra'           => wp_json_encode($extra),
    ];

    // ---------- Insert ----------

    if ($action === 'add_pokemon') {

        $wpdb->insert($table, $data_common);

        $pokemon_id = (int) $wpdb->insert_id;

        // Sync attaques si helper dispo
        if ($pokemon_id > 0 && function_exists('poke_hub_pokemon_sync_pokemon_attacks')) {
            poke_hub_pokemon_sync_pokemon_attacks(
                $pokemon_id,
                (array) $fast_attacks_raw,
                (array) $charged_attacks_raw
            );
        }

    // Sync évolutions si helper dispo
    if ($pokemon_id > 0 && function_exists('poke_hub_pokemon_sync_pokemon_evolutions')) {
        $evo_rows = poke_hub_pokemon_prepare_evolutions_from_request((array) $evolutions_raw);

        poke_hub_pokemon_sync_pokemon_evolutions(
            $pokemon_id,
            (int) $form_variant_id,
            $evo_rows,
            [
                'mode' => 'form',
            ]
        );
    }

        wp_redirect(add_query_arg('ph_msg', 'saved', $redirect_base));
        exit;
    }

    // ---------- Update ----------

    if ($id <= 0) {
        wp_redirect(add_query_arg('ph_msg', 'invalid_id', $redirect_base));
        exit;
    }

    $wpdb->update($table, $data_common, ['id' => $id], null, ['%d']);

    if ($id > 0 && function_exists('poke_hub_pokemon_sync_pokemon_attacks')) {
        poke_hub_pokemon_sync_pokemon_attacks(
            (int) $id,
            (array) $fast_attacks_raw,
            (array) $charged_attacks_raw
        );
    }

    // Sync évolutions si helper dispo
    if ($id > 0 && function_exists('poke_hub_pokemon_sync_pokemon_evolutions')) {
        $evo_rows = poke_hub_pokemon_prepare_evolutions_from_request((array) $evolutions_raw);

        poke_hub_pokemon_sync_pokemon_evolutions(
            (int) $id,
            (int) $form_variant_id,
            $evo_rows,
            [
                'mode' => 'form',
            ]
        );
    }

    wp_redirect(add_query_arg('ph_msg', 'updated', $redirect_base));
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_pokemon_form');

/**
 * Écran principal de l’onglet "Pokémon"
 */
function poke_hub_pokemon_admin_pokemon_screen() {

    $list_table = new Poke_Hub_Pokemon_List_Table();

    // Bulk actions
    $list_table->process_bulk_action();
    $list_table->prepare_items();

    // Notices
    if (!empty($_GET['ph_msg'])) {
        $msg = sanitize_key($_GET['ph_msg']);
        if ($msg === 'saved' || $msg === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Pokémon saved.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Pokémon deleted.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'invalid_id') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid Pokémon ID.', 'poke-hub') . '</p></div>';
        }
    }
    ?>
    <form method="get">
        <input type="hidden" name="page" value="poke-hub-pokemon" />
        <input type="hidden" name="ph_section" value="pokemon" />
        <?php
        // nonce pour les bulk actions
        wp_nonce_field('bulk-pokemon');

        $list_table->search_box(__('Search Pokémon', 'poke-hub'), 'pokemon-list');
        $list_table->display();
        ?>
    </form>
    <?php
}
