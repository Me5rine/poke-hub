<?php
// modules/events/admin/events-class-pokehub-events-list-table.php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class PokeHub_Events_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'pokehub_event',
            'plural'   => 'pokehub_events',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'      => '<input type="checkbox" />',
            'title'   => __('Title', 'poke-hub'),
            'type'    => __('Type', 'poke-hub'),
            'start'   => __('Start', 'poke-hub'),
            'end'     => __('End', 'poke-hub'),
            'status'  => __('Status', 'poke-hub'),
            'source'  => __('Source', 'poke-hub'),
        ];
    }

    public function get_sortable_columns() {
        // 2e valeur = valeur de "orderby" dans l’URL
        return [
            'title'  => ['title', false],
            'type'   => ['event_type_name', false],
            'start'  => ['start_ts', true],
            'end'    => ['end_ts', false],
            'status' => ['status', false],
            'source' => ['source', false],
        ];
    }

    public function get_hidden_columns() {
        return [];
    }

    public function no_items() {
        _e('No events found.', 'poke-hub');
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="event_ids[]" value="%s" />',
            esc_attr($item->id)
        );
    }

    public function column_title($item) {
        $title  = esc_html($item->title);
        $source = isset($item->source) ? $item->source : 'remote';

        $actions = [];

        // Lien voir
        if (!empty($item->remote_url)) {
            $actions['view'] = sprintf(
                '<a href="%s" target="_blank" rel="noopener">%s</a>',
                esc_url($item->remote_url),
                esc_html__('View', 'poke-hub')
            );
        }

        // Lien modifier / supprimer pour SPECIAL vs REMOTE
        if ($source === 'special') {
            // Edit spécial
            $edit_url = add_query_arg(
                [
                    'page'     => 'poke-hub-events',
                    'action'   => 'edit_special',
                    'event_id' => (int) $item->id,
                ],
                admin_url('admin.php')
            );
            $actions['edit'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($edit_url),
                esc_html__('Edit', 'poke-hub')
            );

            // Delete spécial → utilise la même mécanique que les bulk actions
            $delete_url = wp_nonce_url(
                add_query_arg(
                    [
                        'page'        => 'poke-hub-events',
                        'action'      => 'delete_special',
                        'event_ids[]' => (int) $item->id,
                    ],
                    admin_url('admin.php')
                ),
                'bulk-pokehub_events'
            );

            $actions['delete'] = sprintf(
                '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($delete_url),
                esc_attr__('Are you sure you want to delete this special event (all its occurrences)?', 'poke-hub'),
                esc_html__('Delete', 'poke-hub')
            );

        } else {
            // Remote : URL d’édition distante via filtre
            $edit_url = apply_filters('pokehub_remote_events_edit_url', '', $item);
            if (!empty($edit_url)) {
                $actions['edit'] = sprintf(
                    '<a href="%s" target="_blank" rel="noopener">%s</a>',
                    esc_url($edit_url),
                    esc_html__('Edit', 'poke-hub')
                );
            }
        }

        return sprintf(
            '%s %s',
            $title,
            $this->row_actions($actions)
        );
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'type':
                return esc_html(
                    !empty($item->event_type_name)
                        ? $item->event_type_name
                        : ($item->event_type_slug ?? '')
                );

            case 'start':
                return !empty($item->start_ts)
                    ? esc_html(date_i18n(
                        get_option('date_format') . ' ' . get_option('time_format'),
                        $item->start_ts
                    ))
                    : '';

            case 'end':
                return !empty($item->end_ts)
                    ? esc_html(date_i18n(
                        get_option('date_format') . ' ' . get_option('time_format'),
                        $item->end_ts
                    ))
                    : '';

            case 'status':
                return esc_html($item->status ?? '');

            case 'source':
                $source = isset($item->source) ? $item->source : 'remote';
                return $source === 'special'
                    ? __('Special', 'poke-hub')
                    : __('Remote', 'poke-hub');
        }

        return '';
    }

    /**
     * Filtres au-dessus du tableau (status, source, type)
     */
    protected function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        $current_status = isset($_GET['event_status']) ? sanitize_key($_GET['event_status']) : '';
        $current_source = isset($_GET['event_source']) ? sanitize_key($_GET['event_source']) : '';
        $current_type   = isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : '';

        echo '<div class="alignleft actions">';

        // Filtre statut
        echo '<select name="event_status" id="filter-by-event-status">';
        echo '<option value="">' . esc_html__('All statuses', 'poke-hub') . '</option>';
        $status_options = [
            'current'  => __('Current', 'poke-hub'),
            'upcoming' => __('Upcoming', 'poke-hub'),
            'past'     => __('Past', 'poke-hub'),
        ];
        foreach ($status_options as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($current_status, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';

        // Filtre source
        echo '<select name="event_source" id="filter-by-event-source">';
        echo '<option value="">' . esc_html__('All sources', 'poke-hub') . '</option>';
        $source_options = [
            'remote'  => __('Remote', 'poke-hub'),
            'special' => __('Special', 'poke-hub'),
        ];
        foreach ($source_options as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($current_source, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';

        // Filtre type d’événement
        $types = function_exists('poke_hub_events_get_all_event_types')
            ? poke_hub_events_get_all_event_types()
            : [];

        echo '<select name="event_type" id="filter-by-event-type">';
        echo '<option value="">' . esc_html__('All event types', 'poke-hub') . '</option>';
        if ($types) {
            foreach ($types as $type) {
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr($type->slug),
                    selected($current_type, $type->slug, false),
                    esc_html($type->name)
                );
            }
        }
        echo '</select>';

        submit_button(__('Filter'), 'secondary', 'filter_action', false);

        echo '</div>';
    }

    public function prepare_items() {
        $this->process_bulk_action();
        
        $per_page     = $this->get_items_per_page('pokehub_events_per_page', 20);
        $current_page = $this->get_pagenum();

        $orderby = !empty($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'start_ts';
        $order   = (!empty($_GET['order']) && $_GET['order'] === 'asc') ? 'asc' : 'desc';

        // Filtres via GET
        $status_filter = isset($_GET['event_status']) ? sanitize_key($_GET['event_status']) : '';
        $source_filter = isset($_GET['event_source']) ? sanitize_key($_GET['event_source']) : '';
        $type_filter   = isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : '';

        // ---------- 1) Récupération unifiée des événements ----------
        $status_for_query = in_array($status_filter, ['current', 'upcoming', 'past'], true)
            ? $status_filter
            : 'all';

        $query_args = [
            // ordre de base par date de début, on retriera après selon $orderby
            'order' => 'asc',
        ];

        if ($type_filter !== '') {
            // get_by_status accepte string|array pour event_type
            $query_args['event_type'] = $type_filter;
        }

        if (function_exists('poke_hub_events_get_all_sources_by_status')) {
            $all_events = poke_hub_events_get_all_sources_by_status($status_for_query, $query_args);
        } else {
            // Fallback très dégradé (au cas où) :
            $all_events = [];
        }

        // ---------- 2) Filtrage par source (remote / special) ----------
        if ($source_filter !== '') {
            $filtered = [];
            foreach ($all_events as $e) {
                $src = isset($e->source) ? $e->source : 'remote';
                if ($src !== $source_filter) {
                    continue;
                }
                $filtered[] = $e;
            }
            $all_events = $filtered;
        }

        // ---------- 3) Tri selon les colonnes WP_List_Table ----------
        usort($all_events, function ($a, $b) use ($orderby, $order) {

            $result = 0;

            switch ($orderby) {
                case 'title':
                    $a_val = isset($a->title) ? strtolower($a->title) : '';
                    $b_val = isset($b->title) ? strtolower($b->title) : '';
                    $result = strcmp($a_val, $b_val);
                    break;

                case 'event_type_name':
                    $a_val = isset($a->event_type_name) ? strtolower($a->event_type_name) : '';
                    $b_val = isset($b->event_type_name) ? strtolower($b->event_type_name) : '';
                    $result = strcmp($a_val, $b_val);
                    break;

                case 'end_ts':
                    $a_val = isset($a->end_ts) ? (int) $a->end_ts : 0;
                    $b_val = isset($b->end_ts) ? (int) $b->end_ts : 0;
                    $result = $a_val <=> $b_val;
                    break;

                case 'status':
                    $a_val = isset($a->status) ? strtolower($a->status) : '';
                    $b_val = isset($b->status) ? strtolower($b->status) : '';
                    $result = strcmp($a_val, $b_val);
                    break;

                case 'source':
                    $a_val = isset($a->source) ? strtolower($a->source) : '';
                    $b_val = isset($b->source) ? strtolower($b->source) : '';
                    $result = strcmp($a_val, $b_val);
                    break;

                case 'start_ts':
                default:
                    $a_val = isset($a->start_ts) ? (int) $a->start_ts : 0;
                    $b_val = isset($b->start_ts) ? (int) $b->start_ts : 0;
                    $result = $a_val <=> $b_val;
                    break;
            }

            return ($order === 'asc') ? $result : -$result;
        });

        // ---------- 4) Pagination ----------
        $total_items = count($all_events);
        $total_pages = $per_page > 0 ? (int) ceil($total_items / $per_page) : 1;

        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }
        if ($current_page < 1) {
            $current_page = 1;
        }

        $this->items = array_slice(
            $all_events,
            ($current_page - 1) * $per_page,
            $per_page
        );

        // Colonnes
        $this->_column_headers = [
            $this->get_columns(),
            $this->get_hidden_columns(),
            $this->get_sortable_columns(),
            'title',
        ];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => $total_pages,
        ]);
    }

    public function get_bulk_actions() {
        return [
            'delete_special' => __('Delete Special Events', 'poke-hub'),
        ];
    }

    public function process_bulk_action() {
        // On ne traite que notre action personnalisée
        if ($this->current_action() !== 'delete_special') {
            return;
        }

        // Vérif nonce
        if (empty($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-pokehub_events')) {
            return;
        }

        // IDs sélectionnés (checkbox + delete ligne)
        $ids = isset($_REQUEST['event_ids']) ? (array) $_REQUEST['event_ids'] : [];
        $ids = array_unique(array_map('intval', $ids));

        if (!$ids) {
            return;
        }

        global $wpdb;

        $events_table              = pokehub_get_table('special_events');
        $event_pokemon_table       = pokehub_get_table('special_event_pokemon');
        $event_pokemon_attacks_tbl = pokehub_get_table('special_event_pokemon_attacks');
        $event_bonus_table         = pokehub_get_table('special_event_bonus');

        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }

            // On supprime toutes les liaisons liées à cet event
            $wpdb->delete($event_pokemon_attacks_tbl, ['event_id' => $id], ['%d']);
            $wpdb->delete($event_pokemon_table,       ['event_id' => $id], ['%d']);
            $wpdb->delete($event_bonus_table,         ['event_id' => $id], ['%d']);

            // Puis l’event lui-même
            $wpdb->delete($events_table, ['id' => $id], ['%d']);
        }
    }
}
