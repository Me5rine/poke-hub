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
            'cb'          => '<input type="checkbox" />',
            // On garde "name" comme identifiant de colonne, mais on affiche name_fr/name_en derrière.
            'name'        => __('Name', 'poke-hub'),
            'slug'        => __('Slug', 'poke-hub'),
            'weaknesses'  => __('Weak to (×2)', 'poke-hub'),
            'resistances' => __('Resists (×½)', 'poke-hub'),
            'immunities'  => __('Immune to (×0)', 'poke-hub'),
            'offensive_super_effective' => __('Off. Super (×2)', 'poke-hub'),
            'offensive_not_very_effective' => __('Off. Not (×½)', 'poke-hub'),
            'offensive_no_effect' => __('Off. None (×0)', 'poke-hub'),
            'color'       => __('Color', 'poke-hub'),
            'icon'        => __('Icon', 'poke-hub'),
            'sort_order'  => __('Order', 'poke-hub'),
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

            case 'weaknesses':
                return $this->column_type_relations($item->id, 'weakness');

            case 'resistances':
                return $this->column_type_relations($item->id, 'resistance');

            case 'immunities':
                return $this->column_type_relations($item->id, 'immune');

            case 'offensive_super_effective':
                return $this->column_type_relations($item->id, 'offensive_super_effective');

            case 'offensive_not_very_effective':
                return $this->column_type_relations($item->id, 'offensive_not_very_effective');

            case 'offensive_no_effect':
                return $this->column_type_relations($item->id, 'offensive_no_effect');

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

    /**
     * Affiche les relations de type (faiblesses, résistances, immunités, efficacités offensives)
     *
     * @param int    $type_id
     * @param string $relation_type 'weakness', 'resistance', 'immune', 'offensive_super_effective', 'offensive_not_very_effective', 'offensive_no_effect'
     * @return string
     */
    private function column_type_relations($type_id, $relation_type) {
        if (!function_exists('pokehub_get_table')) {
            return '–';
        }

        global $wpdb;

        $type_id = (int) $type_id;
        if ($type_id <= 0) {
            return '–';
        }

        // Détermine la table et la colonne selon le type de relation
        $table_name = '';
        $join_column = '';
        
        switch ($relation_type) {
            case 'weakness':
                $table_name = 'pokemon_type_weakness_links';
                $join_column = 'weakness_type_id';
                break;
            case 'resistance':
                $table_name = 'pokemon_type_resistance_links';
                $join_column = 'resistance_type_id';
                break;
            case 'immune':
                $table_name = 'pokemon_type_immune_links';
                $join_column = 'immune_type_id';
                break;
            case 'offensive_super_effective':
                $table_name = 'pokemon_type_offensive_super_effective_links';
                $join_column = 'target_type_id';
                break;
            case 'offensive_not_very_effective':
                $table_name = 'pokemon_type_offensive_not_very_effective_links';
                $join_column = 'target_type_id';
                break;
            case 'offensive_no_effect':
                $table_name = 'pokemon_type_offensive_no_effect_links';
                $join_column = 'target_type_id';
                break;
            default:
                return '–';
        }
        
        $link_table = pokehub_get_table($table_name);
        $types_table = pokehub_get_table('pokemon_types');

        if (!$link_table || !$types_table) {
            return '–';
        }

        // Filtre par game_key : on affiche d'abord pokemon_go, puis core_series
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.id, t.name_fr, t.name_en, t.slug, link.game_key
                 FROM {$link_table} AS link
                 INNER JOIN {$types_table} AS t ON t.id = link.{$join_column}
                 WHERE link.type_id = %d
                 ORDER BY 
                     CASE link.game_key 
                         WHEN 'pokemon_go' THEN 1 
                         WHEN 'core_series' THEN 2 
                         ELSE 3 
                     END,
                     t.name_fr ASC, t.name_en ASC",
                $type_id
            )
        );

        if (empty($rows)) {
            return '–';
        }

        $labels = [];
        foreach ($rows as $row) {
            $label_fr = isset($row->name_fr) ? (string) $row->name_fr : '';
            $label_en = isset($row->name_en) ? (string) $row->name_en : '';
            $label = $label_fr !== '' ? $label_fr : ($label_en !== '' ? $label_en : $row->slug);
            
            // Affiche le game_key si différent de core_series
            $game_key = isset($row->game_key) ? (string) $row->game_key : 'core_series';
            if ($game_key === 'pokemon_go') {
                $label = esc_html($label) . ' <span style="color:#999;font-size:0.9em;">(GO)</span>';
            } else {
                $label = esc_html($label);
            }
            
            $labels[] = $label;
        }

        return implode(', ', $labels);
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

            // 🔹 Nettoyage des liaisons type ↔ faiblesses
            $weakness_table = pokehub_get_table('pokemon_type_weakness_links');
            if ($weakness_table) {
                $params = array_merge($ids, $ids);
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$weakness_table} WHERE type_id IN ($in) OR weakness_type_id IN ($in)",
                        ...$params
                    )
                );
            }

            // 🔹 Nettoyage des liaisons type ↔ résistances
            $resistance_table = pokehub_get_table('pokemon_type_resistance_links');
            if ($resistance_table) {
                $params = array_merge($ids, $ids);
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$resistance_table} WHERE type_id IN ($in) OR resistance_type_id IN ($in)",
                        ...$params
                    )
                );
            }

            // 🔹 Nettoyage des liaisons type ↔ immunités
            $immune_table = pokehub_get_table('pokemon_type_immune_links');
            if ($immune_table) {
                $params = array_merge($ids, $ids);
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$immune_table} WHERE type_id IN ($in) OR immune_type_id IN ($in)",
                        ...$params
                    )
                );
            }

            // 🔹 Nettoyage des liaisons type ↔ efficacités offensives
            $offensive_tables = [
                'pokemon_type_offensive_super_effective_links',
                'pokemon_type_offensive_not_very_effective_links',
                'pokemon_type_offensive_no_effect_links',
            ];
            foreach ($offensive_tables as $offensive_table_name) {
                $offensive_table = pokehub_get_table($offensive_table_name);
                if ($offensive_table) {
                    $params = array_merge($ids, $ids);
                    $wpdb->query(
                        $wpdb->prepare(
                            "DELETE FROM {$offensive_table} WHERE type_id IN ($in) OR target_type_id IN ($in)",
                            ...$params
                        )
                    );
                }
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

    // 🔹 supprimer aussi les liaisons type ↔ faiblesses
    $weakness_table = pokehub_get_table('pokemon_type_weakness_links');
    if ($weakness_table) {
        $wpdb->delete($weakness_table, ['type_id' => $id], ['%d']);
        $wpdb->delete($weakness_table, ['weakness_type_id' => $id], ['%d']);
    }

    // 🔹 supprimer aussi les liaisons type ↔ résistances
    $resistance_table = pokehub_get_table('pokemon_type_resistance_links');
    if ($resistance_table) {
        $wpdb->delete($resistance_table, ['type_id' => $id], ['%d']);
        $wpdb->delete($resistance_table, ['resistance_type_id' => $id], ['%d']);
    }

    // 🔹 supprimer aussi les liaisons type ↔ immunités
    $immune_table = pokehub_get_table('pokemon_type_immune_links');
    if ($immune_table) {
        $wpdb->delete($immune_table, ['type_id' => $id], ['%d']);
        $wpdb->delete($immune_table, ['immune_type_id' => $id], ['%d']);
    }

    // 🔹 supprimer aussi les liaisons type ↔ efficacités offensives
    $offensive_tables = [
        'pokemon_type_offensive_super_effective_links' => 'target_type_id',
        'pokemon_type_offensive_not_very_effective_links' => 'target_type_id',
        'pokemon_type_offensive_no_effect_links' => 'target_type_id',
    ];
    foreach ($offensive_tables as $table_name => $target_column) {
        $table = pokehub_get_table($table_name);
        if ($table) {
            $wpdb->delete($table, ['type_id' => $id], ['%d']);
            $wpdb->delete($table, [$target_column => $id], ['%d']);
        }
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

    // 🔹 NEW : faiblesses sélectionnées
    $weakness_ids = [];
    if (!empty($_POST['weakness_ids']) && is_array($_POST['weakness_ids'])) {
        $weakness_ids = array_map('intval', $_POST['weakness_ids']);
        $weakness_ids = array_filter($weakness_ids, function ($v) {
            return $v > 0;
        });
        $weakness_ids = array_values(array_unique($weakness_ids));
    }

    // 🔹 NEW : résistances sélectionnées
    $resistance_ids = [];
    if (!empty($_POST['resistance_ids']) && is_array($_POST['resistance_ids'])) {
        $resistance_ids = array_map('intval', $_POST['resistance_ids']);
        $resistance_ids = array_filter($resistance_ids, function ($v) {
            return $v > 0;
        });
        $resistance_ids = array_values(array_unique($resistance_ids));
    }

    // 🔹 NEW : immunités sélectionnées
    $immune_ids = [];
    if (!empty($_POST['immune_ids']) && is_array($_POST['immune_ids'])) {
        $immune_ids = array_map('intval', $_POST['immune_ids']);
        $immune_ids = array_filter($immune_ids, function ($v) {
            return $v > 0;
        });
        $immune_ids = array_values(array_unique($immune_ids));
    }

    // 🔹 NEW : efficacités offensives - super efficace
    $offensive_super_effective_ids = [];
    if (!empty($_POST['offensive_super_effective_ids']) && is_array($_POST['offensive_super_effective_ids'])) {
        $offensive_super_effective_ids = array_map('intval', $_POST['offensive_super_effective_ids']);
        $offensive_super_effective_ids = array_filter($offensive_super_effective_ids, function ($v) {
            return $v > 0;
        });
        $offensive_super_effective_ids = array_values(array_unique($offensive_super_effective_ids));
    }

    // 🔹 NEW : efficacités offensives - peu efficace
    $offensive_not_very_effective_ids = [];
    if (!empty($_POST['offensive_not_very_effective_ids']) && is_array($_POST['offensive_not_very_effective_ids'])) {
        $offensive_not_very_effective_ids = array_map('intval', $_POST['offensive_not_very_effective_ids']);
        $offensive_not_very_effective_ids = array_filter($offensive_not_very_effective_ids, function ($v) {
            return $v > 0;
        });
        $offensive_not_very_effective_ids = array_values(array_unique($offensive_not_very_effective_ids));
    }

    // 🔹 NEW : efficacités offensives - sans effet
    $offensive_no_effect_ids = [];
    if (!empty($_POST['offensive_no_effect_ids']) && is_array($_POST['offensive_no_effect_ids'])) {
        $offensive_no_effect_ids = array_map('intval', $_POST['offensive_no_effect_ids']);
        $offensive_no_effect_ids = array_filter($offensive_no_effect_ids, function ($v) {
            return $v > 0;
        });
        $offensive_no_effect_ids = array_values(array_unique($offensive_no_effect_ids));
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

        // Récupération automatique des traductions depuis Bulbapedia
        if ($type_id > 0 && !empty($name_en) && function_exists('poke_hub_type_auto_fetch_translations')) {
            poke_hub_type_auto_fetch_translations($type_id, $name_en);
        }

        // 🔹 sync météo ↔ type
        // Pour le formulaire, on sauvegarde avec game_key = 'core_series' par défaut
        // Les données Pokémon GO seront importées automatiquement via l'import Game Master
        $game_key = 'core_series';
        if ($type_id > 0) {
            poke_hub_pokemon_sync_type_weathers($type_id, $weather_ids);
            poke_hub_pokemon_sync_type_weaknesses($type_id, $weakness_ids, $game_key);
            poke_hub_pokemon_sync_type_resistances($type_id, $resistance_ids, $game_key);
            poke_hub_pokemon_sync_type_immunities($type_id, $immune_ids, $game_key);
            poke_hub_pokemon_sync_type_offensive_super_effective($type_id, $offensive_super_effective_ids, $game_key);
            poke_hub_pokemon_sync_type_offensive_not_very_effective($type_id, $offensive_not_very_effective_ids, $game_key);
            poke_hub_pokemon_sync_type_offensive_no_effect($type_id, $offensive_no_effect_ids, $game_key);
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

    // Récupération automatique des traductions depuis Bulbapedia
    if (!empty($name_en) && function_exists('poke_hub_type_auto_fetch_translations')) {
        poke_hub_type_auto_fetch_translations($id, $name_en);
    }

    // 🔹 sync météo ↔ type
    // Pour le formulaire, on sauvegarde avec game_key = 'core_series' par défaut
    // Les données Pokémon GO seront importées automatiquement via l'import Game Master
    $game_key = 'core_series';
    poke_hub_pokemon_sync_type_weathers($id, $weather_ids);
    poke_hub_pokemon_sync_type_weaknesses($id, $weakness_ids, $game_key);
    poke_hub_pokemon_sync_type_resistances($id, $resistance_ids, $game_key);
    poke_hub_pokemon_sync_type_immunities($id, $immune_ids, $game_key);
    poke_hub_pokemon_sync_type_offensive_super_effective($id, $offensive_super_effective_ids, $game_key);
    poke_hub_pokemon_sync_type_offensive_not_very_effective($id, $offensive_not_very_effective_ids, $game_key);
    poke_hub_pokemon_sync_type_offensive_no_effect($id, $offensive_no_effect_ids, $game_key);

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
 * 🔹 Synchronise les faiblesses d'un type donné.
 *
 * @param int   $type_id
 * @param int[] $weakness_ids
 * @param string $game_key 'core_series' ou 'pokemon_go' (défaut: 'core_series')
 */
function poke_hub_pokemon_sync_type_weaknesses(int $type_id, array $weakness_ids, string $game_key = 'core_series') {
    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    $link_table = pokehub_get_table('pokemon_type_weakness_links');
    if (!$link_table) {
        return;
    }

    $type_id = (int) $type_id;
    if ($type_id <= 0) {
        return;
    }

    $game_key = sanitize_key($game_key);
    if (empty($game_key)) {
        $game_key = 'core_series';
    }

    // Nettoyage / dedup
    $weakness_ids = array_map('intval', $weakness_ids);
    $weakness_ids = array_filter($weakness_ids, function ($v) {
        return $v > 0;
    });
    $weakness_ids = array_values(array_unique($weakness_ids));

    // Efface toutes les anciennes liaisons pour ce type et ce jeu
    $wpdb->delete(
        $link_table,
        [
            'type_id' => $type_id,
            'game_key' => $game_key,
        ],
        ['%d', '%s']
    );

    if (empty($weakness_ids)) {
        return;
    }

    // Réinsère les nouvelles liaisons
    foreach ($weakness_ids as $wid) {
        $wpdb->insert(
            $link_table,
            [
                'type_id'          => $type_id,
                'weakness_type_id' => $wid,
                'game_key'         => $game_key,
            ],
            ['%d', '%d', '%s']
        );
    }
}

/**
 * 🔹 Synchronise les résistances d'un type donné.
 *
 * @param int   $type_id
 * @param int[] $resistance_ids
 * @param string $game_key 'core_series' ou 'pokemon_go' (défaut: 'core_series')
 */
function poke_hub_pokemon_sync_type_resistances(int $type_id, array $resistance_ids, string $game_key = 'core_series') {
    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    $link_table = pokehub_get_table('pokemon_type_resistance_links');
    if (!$link_table) {
        return;
    }

    $type_id = (int) $type_id;
    if ($type_id <= 0) {
        return;
    }

    $game_key = sanitize_key($game_key);
    if (empty($game_key)) {
        $game_key = 'core_series';
    }

    // Nettoyage / dedup
    $resistance_ids = array_map('intval', $resistance_ids);
    $resistance_ids = array_filter($resistance_ids, function ($v) {
        return $v > 0;
    });
    $resistance_ids = array_values(array_unique($resistance_ids));

    // Efface toutes les anciennes liaisons pour ce type et ce jeu
    $wpdb->delete(
        $link_table,
        [
            'type_id' => $type_id,
            'game_key' => $game_key,
        ],
        ['%d', '%s']
    );

    if (empty($resistance_ids)) {
        return;
    }

    // Réinsère les nouvelles liaisons
    foreach ($resistance_ids as $rid) {
        $wpdb->insert(
            $link_table,
            [
                'type_id'            => $type_id,
                'resistance_type_id' => $rid,
                'game_key'           => $game_key,
            ],
            ['%d', '%d', '%s']
        );
    }
}

/**
 * 🔹 Synchronise les immunités d'un type donné.
 *
 * @param int   $type_id
 * @param int[] $immune_ids
 * @param string $game_key 'core_series' ou 'pokemon_go' (défaut: 'core_series')
 */
function poke_hub_pokemon_sync_type_immunities(int $type_id, array $immune_ids, string $game_key = 'core_series') {
    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    $link_table = pokehub_get_table('pokemon_type_immune_links');
    if (!$link_table) {
        return;
    }

    $type_id = (int) $type_id;
    if ($type_id <= 0) {
        return;
    }

    $game_key = sanitize_key($game_key);
    if (empty($game_key)) {
        $game_key = 'core_series';
    }

    // Nettoyage / dedup
    $immune_ids = array_map('intval', $immune_ids);
    $immune_ids = array_filter($immune_ids, function ($v) {
        return $v > 0;
    });
    $immune_ids = array_values(array_unique($immune_ids));

    // Efface toutes les anciennes liaisons pour ce type et ce jeu
    $wpdb->delete(
        $link_table,
        [
            'type_id' => $type_id,
            'game_key' => $game_key,
        ],
        ['%d', '%s']
    );

    if (empty($immune_ids)) {
        return;
    }

    // Réinsère les nouvelles liaisons
    foreach ($immune_ids as $iid) {
        $wpdb->insert(
            $link_table,
            [
                'type_id'     => $type_id,
                'immune_type_id' => $iid,
                'game_key'    => $game_key,
            ],
            ['%d', '%d', '%s']
        );
    }
}

/**
 * 🔹 Synchronise les efficacités offensives - Super efficace (×2) d'un type donné.
 *
 * @param int   $type_id
 * @param int[] $target_type_ids
 * @param string $game_key 'core_series' ou 'pokemon_go' (défaut: 'core_series')
 */
function poke_hub_pokemon_sync_type_offensive_super_effective(int $type_id, array $target_type_ids, string $game_key = 'core_series') {
    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    $link_table = pokehub_get_table('pokemon_type_offensive_super_effective_links');
    if (!$link_table) {
        return;
    }

    $type_id = (int) $type_id;
    if ($type_id <= 0) {
        return;
    }

    $game_key = sanitize_key($game_key);
    if (empty($game_key)) {
        $game_key = 'core_series';
    }

    // Nettoyage / dedup
    $target_type_ids = array_map('intval', $target_type_ids);
    $target_type_ids = array_filter($target_type_ids, function ($v) {
        return $v > 0;
    });
    $target_type_ids = array_values(array_unique($target_type_ids));

    // Efface toutes les anciennes liaisons pour ce type et ce jeu
    $wpdb->delete(
        $link_table,
        [
            'type_id' => $type_id,
            'game_key' => $game_key,
        ],
        ['%d', '%s']
    );

    if (empty($target_type_ids)) {
        return;
    }

    // Réinsère les nouvelles liaisons
    foreach ($target_type_ids as $tid) {
        $wpdb->insert(
            $link_table,
            [
                'type_id'       => $type_id,
                'target_type_id' => $tid,
                'game_key'      => $game_key,
            ],
            ['%d', '%d', '%s']
        );
    }
}

/**
 * 🔹 Synchronise les efficacités offensives - Peu efficace (×½) d'un type donné.
 *
 * @param int   $type_id
 * @param int[] $target_type_ids
 * @param string $game_key 'core_series' ou 'pokemon_go' (défaut: 'core_series')
 */
function poke_hub_pokemon_sync_type_offensive_not_very_effective(int $type_id, array $target_type_ids, string $game_key = 'core_series') {
    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    $link_table = pokehub_get_table('pokemon_type_offensive_not_very_effective_links');
    if (!$link_table) {
        return;
    }

    $type_id = (int) $type_id;
    if ($type_id <= 0) {
        return;
    }

    $game_key = sanitize_key($game_key);
    if (empty($game_key)) {
        $game_key = 'core_series';
    }

    // Nettoyage / dedup
    $target_type_ids = array_map('intval', $target_type_ids);
    $target_type_ids = array_filter($target_type_ids, function ($v) {
        return $v > 0;
    });
    $target_type_ids = array_values(array_unique($target_type_ids));

    // Efface toutes les anciennes liaisons pour ce type et ce jeu
    $wpdb->delete(
        $link_table,
        [
            'type_id' => $type_id,
            'game_key' => $game_key,
        ],
        ['%d', '%s']
    );

    if (empty($target_type_ids)) {
        return;
    }

    // Réinsère les nouvelles liaisons
    foreach ($target_type_ids as $tid) {
        $wpdb->insert(
            $link_table,
            [
                'type_id'       => $type_id,
                'target_type_id' => $tid,
                'game_key'      => $game_key,
            ],
            ['%d', '%d', '%s']
        );
    }
}

/**
 * 🔹 Synchronise les efficacités offensives - Sans effet (×0) d'un type donné.
 *
 * @param int   $type_id
 * @param int[] $target_type_ids
 * @param string $game_key 'core_series' ou 'pokemon_go' (défaut: 'core_series')
 */
function poke_hub_pokemon_sync_type_offensive_no_effect(int $type_id, array $target_type_ids, string $game_key = 'core_series') {
    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    $link_table = pokehub_get_table('pokemon_type_offensive_no_effect_links');
    if (!$link_table) {
        return;
    }

    $type_id = (int) $type_id;
    if ($type_id <= 0) {
        return;
    }

    $game_key = sanitize_key($game_key);
    if (empty($game_key)) {
        $game_key = 'core_series';
    }

    // Nettoyage / dedup
    $target_type_ids = array_map('intval', $target_type_ids);
    $target_type_ids = array_filter($target_type_ids, function ($v) {
        return $v > 0;
    });
    $target_type_ids = array_values(array_unique($target_type_ids));

    // Efface toutes les anciennes liaisons pour ce type et ce jeu
    $wpdb->delete(
        $link_table,
        [
            'type_id' => $type_id,
            'game_key' => $game_key,
        ],
        ['%d', '%s']
    );

    if (empty($target_type_ids)) {
        return;
    }

    // Réinsère les nouvelles liaisons
    foreach ($target_type_ids as $tid) {
        $wpdb->insert(
            $link_table,
            [
                'type_id'       => $type_id,
                'target_type_id' => $tid,
                'game_key'      => $game_key,
            ],
            ['%d', '%d', '%s']
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
