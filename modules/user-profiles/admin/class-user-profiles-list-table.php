<?php
// modules/user-profiles/admin/class-user-profiles-list-table.php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table pour les profils utilisateurs Pokémon GO
 */
class PokeHub_User_Profiles_List_Table extends WP_List_Table {

    /**
     * Constructeur
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'user_profile',
            'plural'   => 'user_profiles',
            'ajax'     => false,
            'screen'   => 'poke-hub_page_poke-hub-user-profiles',
        ]);
    }

    /**
     * Définit les colonnes du tableau
     */
    public function get_columns() {
        return [
            'cb'                  => '<input type="checkbox" />',
            'user'                => __('User', 'poke-hub'),
            'team'                => __('Team', 'poke-hub'),
            'friend_code'         => __('Friend Code', 'poke-hub'),
            'xp'                  => __('XP', 'poke-hub'),
            'country'             => __('Country', 'poke-hub'),
            'pokemon_go_username' => __('Pokémon GO Username', 'poke-hub'),
            'scatterbug_pattern'  => __('Scatterbug Pattern', 'poke-hub'),
            'updated_at'          => __('Last Updated', 'poke-hub'),
        ];
    }

    /**
     * Définit les colonnes triables
     */
    public function get_sortable_columns() {
        return [
            'user'                => ['user_login', false],
            'team'                => ['team', false],
            'xp'                  => ['xp', true],
            'pokemon_go_username' => ['pokemon_go_username', false],
            'scatterbug_pattern'  => ['scatterbug_pattern', false],
            'updated_at'          => ['updated_at', true],
        ];
    }

    /**
     * Récupère les colonnes cachées depuis les préférences utilisateur
     */
    public function get_hidden_columns() {
        $screen = get_current_screen();
        if ($screen) {
            return get_hidden_columns($screen);
        }
        return [];
    }

    /**
     * Message quand aucun élément n'est trouvé
     */
    public function no_items() {
        _e('No user profiles found.', 'poke-hub');
    }

    /**
     * Colonne checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="profile_ids[]" value="%d" />',
            (int) $item->id
        );
    }

    /**
     * Colonne User
     */
    public function column_user($item) {
        $user_id = !empty($item->user_id) ? (int) $item->user_id : 0;
        $profile_id = (int) $item->id;
        
        if ($user_id > 0) {
            $user = get_userdata($user_id);
            if ($user) {
                $display_name = $user->display_name ?: $user->user_login;
                $user_email = $user->user_email;
                
                // Préserver les paramètres de filtrage dans l'URL
                $edit_url_args = [
                    'page'    => 'poke-hub-user-profiles',
                    'action'  => 'edit',
                    'user_id' => $user_id,
                ];
                $preserve_params = ['filter_team', 'filter_scatterbug_pattern', 's', 'paged', 'orderby', 'order'];
                foreach ($preserve_params as $param) {
                    if (isset($_GET[$param]) && $_GET[$param] !== '') {
                        $edit_url_args[$param] = sanitize_text_field($_GET[$param]);
                    }
                }
                $edit_url = add_query_arg($edit_url_args, admin_url('admin.php'));
                
                // URL pour voir le profil Ultimate Member
                $view_url = '';
                if (function_exists('poke_hub_get_um_profile_tab_url')) {
                    $view_url = poke_hub_get_um_profile_tab_url($user_id);
                }
                
                // URL pour supprimer
                $delete_url_args = [
                    'page'        => 'poke-hub-user-profiles',
                    'action'      => 'delete',
                    'profile_id'  => $profile_id,
                ];
                foreach ($preserve_params as $param) {
                    if (isset($_GET[$param]) && $_GET[$param] !== '') {
                        $delete_url_args[$param] = sanitize_text_field($_GET[$param]);
                    }
                }
                $delete_url = wp_nonce_url(
                    add_query_arg($delete_url_args, admin_url('admin.php')),
                    'delete_profile_' . $profile_id,
                    '_wpnonce'
                );
                
                $actions = [
                    'edit' => sprintf(
                        '<a href="%s">%s</a>',
                        esc_url($edit_url),
                        esc_html__('Edit', 'poke-hub')
                    ),
                ];
                
                if (!empty($view_url)) {
                    $actions['view'] = sprintf(
                        '<a href="%s" target="_blank">%s</a>',
                        esc_url($view_url),
                        esc_html__('View', 'poke-hub')
                    );
                }
                
                $actions['delete'] = sprintf(
                    '<a href="%s" class="submitdelete admin-lab-button-delete" onclick="return confirm(\'%s\');">%s</a>',
                    esc_url($delete_url),
                    esc_attr__('Are you sure you want to delete this profile?', 'poke-hub'),
                    esc_html__('Delete', 'poke-hub')
                );
                
                $title = sprintf(
                    '<strong>%s</strong><br><small>%s</small>',
                    esc_html($display_name),
                    esc_html($user_email)
                );
                
                return sprintf(
                    '%s %s',
                    $title,
                    $this->row_actions($actions)
                );
            }
        }
        
        // Pas d'utilisateur WordPress (peut-être seulement Discord ID)
        $discord_id = !empty($item->discord_id) ? esc_html($item->discord_id) : '';
        
        // Actions pour profil Discord uniquement
        $delete_url_args = [
            'page'        => 'poke-hub-user-profiles',
            'action'      => 'delete',
            'profile_id'  => $profile_id,
        ];
        $preserve_params = ['filter_team', 'filter_scatterbug_pattern', 's', 'paged', 'orderby', 'order'];
        foreach ($preserve_params as $param) {
            if (isset($_GET[$param]) && $_GET[$param] !== '') {
                $delete_url_args[$param] = sanitize_text_field($_GET[$param]);
            }
        }
        $delete_url = wp_nonce_url(
            add_query_arg($delete_url_args, admin_url('admin.php')),
            'delete_profile_' . $profile_id,
            '_wpnonce'
        );
        
        $actions = [
            'delete' => sprintf(
                '<a href="%s" class="submitdelete admin-lab-button-delete" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($delete_url),
                esc_attr__('Are you sure you want to delete this profile?', 'poke-hub'),
                esc_html__('Delete', 'poke-hub')
            ),
        ];
        
        return sprintf(
            '<em>%s</em>%s %s',
            __('Discord only', 'poke-hub'),
            $discord_id ? '<br><small>' . $discord_id . '</small>' : '',
            $this->row_actions($actions)
        );
    }

    /**
     * Colonne par défaut
     */
    public function column_default($item, $column_name) {
        $teams = poke_hub_get_teams();
        $scatterbug_patterns = poke_hub_get_scatterbug_patterns();
        $empty_dash = '—';
        
        switch ($column_name) {
            case 'team':
                $team = !empty($item->team) ? $item->team : '';
                if (!empty($team) && isset($teams[$team])) {
                    // Rendre cliquable pour filtrer
                    $filter_url = add_query_arg([
                        'page'          => 'poke-hub-user-profiles',
                        'filter_team'   => $team,
                    ], admin_url('admin.php'));
                    return sprintf(
                        '<a href="%s">%s</a>',
                        esc_url($filter_url),
                        esc_html($teams[$team])
                    );
                }
                return esc_html($empty_dash);

            case 'friend_code':
                if (!empty($item->friend_code)) {
                    $formatted_code = function_exists('poke_hub_format_friend_code') 
                        ? poke_hub_format_friend_code($item->friend_code) 
                        : $item->friend_code;
                    return esc_html($formatted_code);
                }
                return esc_html($empty_dash);

            case 'xp':
                if (!empty($item->xp) || $item->xp === '0' || $item->xp === 0) {
                    $formatted_xp = function_exists('poke_hub_format_xp') 
                        ? poke_hub_format_xp($item->xp) 
                        : number_format($item->xp, 0, ',', ' ');
                    return esc_html($formatted_xp);
                }
                return esc_html($empty_dash);

            case 'country':
                $country = '';
                $user_id = !empty($item->user_id) ? (int) $item->user_id : 0;
                if ($user_id > 0) {
                    $country = get_user_meta($user_id, 'country', true);
                }
                return !empty($country) ? esc_html($country) : esc_html($empty_dash);

            case 'pokemon_go_username':
                return !empty($item->pokemon_go_username) 
                    ? esc_html($item->pokemon_go_username) 
                    : esc_html($empty_dash);

            case 'scatterbug_pattern':
                $pattern = !empty($item->scatterbug_pattern) ? $item->scatterbug_pattern : '';
                if (!empty($pattern) && isset($scatterbug_patterns[$pattern])) {
                    // Rendre cliquable pour filtrer
                    $filter_url = add_query_arg([
                        'page'                    => 'poke-hub-user-profiles',
                        'filter_scatterbug_pattern' => $pattern,
                    ], admin_url('admin.php'));
                    return sprintf(
                        '<a href="%s">%s</a>',
                        esc_url($filter_url),
                        esc_html($scatterbug_patterns[$pattern])
                    );
                }
                return esc_html($empty_dash);

            case 'updated_at':
                if (!empty($item->updated_at)) {
                    $timestamp = strtotime($item->updated_at);
                    if ($timestamp) {
                        return esc_html(wp_date(
                            get_option('date_format') . ' ' . get_option('time_format'),
                            $timestamp,
                            wp_timezone()
                        ));
                    }
                }
                return esc_html($empty_dash);

            default:
                return '';
        }
    }

    /**
     * Filtres au-dessus du tableau
     */
    protected function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        $current_team = isset($_GET['filter_team']) ? sanitize_key($_GET['filter_team']) : '';
        $current_scatterbug = isset($_GET['filter_scatterbug_pattern']) ? sanitize_text_field($_GET['filter_scatterbug_pattern']) : '';

        echo '<div class="alignleft actions bulkactions">';

        // Filtre Team
        $teams = poke_hub_get_teams();
        echo '<label for="filter-by-team" class="screen-reader-text">' . esc_html__('Filter by team', 'poke-hub') . '</label>';
        echo '<select name="filter_team" id="filter-by-team" style="float: none;">';
        echo '<option value="">' . esc_html__('All teams', 'poke-hub') . '</option>';
        foreach ($teams as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($current_team, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';

        // Filtre Scatterbug Pattern
        $scatterbug_patterns = poke_hub_get_scatterbug_patterns();
        if (!empty($scatterbug_patterns)) {
            echo '<label for="filter-by-scatterbug-pattern" class="screen-reader-text">' . esc_html__('Filter by scatterbug pattern', 'poke-hub') . '</label>';
            echo '<select name="filter_scatterbug_pattern" id="filter-by-scatterbug-pattern" style="float: none;">';
            echo '<option value="">' . esc_html__('All patterns', 'poke-hub') . '</option>';
            foreach ($scatterbug_patterns as $value => $label) {
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr($value),
                    selected($current_scatterbug, $value, false),
                    esc_html($label)
                );
            }
            echo '</select>';
        }

        submit_button(__('Filter', 'poke-hub'), 'secondary', 'filter_action', false, [
            'id' => 'pokehub-filter-submit'
        ]);

        echo '</div>';
    }

    /**
     * Prépare les éléments pour l'affichage
     */
    public function prepare_items() {
        // Traiter les actions groupées en premier
        $this->process_bulk_action();
        
        global $wpdb;

        $per_page = $this->get_items_per_page('pokehub_user_profiles_per_page', 20);
        $current_page = $this->get_pagenum();

        // Récupération des paramètres de tri
        $orderby = !empty($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'updated_at';
        $order = (!empty($_GET['order']) && $_GET['order'] === 'asc') ? 'ASC' : 'DESC';

        // Récupération des filtres
        $search = isset($_REQUEST['s']) ? trim(wp_unslash($_REQUEST['s'])) : '';
        $filter_team = isset($_GET['filter_team']) ? sanitize_key($_GET['filter_team']) : '';
        $filter_scatterbug_pattern = isset($_GET['filter_scatterbug_pattern']) ? sanitize_text_field($_GET['filter_scatterbug_pattern']) : '';

        // Nom de la table
        $table_name = pokehub_get_table('user_profiles');
        if (empty($table_name)) {
            $this->items = [];
            $this->set_pagination_args([
                'total_items' => 0,
                'per_page'    => $per_page,
                'total_pages' => 0,
            ]);
            return;
        }

        // Construction de la requête
        // Toujours faire un LEFT JOIN avec wp_users pour récupérer les données utilisateur
        $join = "LEFT JOIN {$wpdb->users} AS u ON up.user_id = u.ID";
        $where = [];
        $where_values = [];

        // Filtre recherche
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = "(u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s OR up.pokemon_go_username LIKE %s OR up.friend_code LIKE %s OR up.discord_id LIKE %s)";
            $where_values[] = $search_like;
            $where_values[] = $search_like;
            $where_values[] = $search_like;
            $where_values[] = $search_like;
            $where_values[] = $search_like;
            $where_values[] = $search_like;
        }

        // Filtre team
        if (!empty($filter_team)) {
            $where[] = "up.team = %s";
            $where_values[] = $filter_team;
        }

        // Filtre scatterbug_pattern
        if (!empty($filter_scatterbug_pattern)) {
            $where[] = "up.scatterbug_pattern = %s";
            $where_values[] = $filter_scatterbug_pattern;
        }

        // Construction de la clause WHERE
        $where_clause = '';
        if (!empty($where)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
            if (!empty($where_values)) {
                $where_clause = $wpdb->prepare($where_clause, $where_values);
            }
        }

        // Colonnes à récupérer (toujours inclure les colonnes utilisateur)
        $select = "up.*, u.user_login, u.user_email, u.display_name";

        // Ordre de tri
        $allowed_orderby = ['team', 'xp', 'pokemon_go_username', 'scatterbug_pattern', 'updated_at', 'user_login'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'updated_at';
        }
        
        $orderby_column = ($orderby === 'user_login') ? 'u.user_login' : "up.{$orderby}";
        $orderby_clause = "ORDER BY {$orderby_column} {$order}";

        // Requête pour compter le total
        $count_query = "SELECT COUNT(*) FROM {$table_name} AS up {$join} {$where_clause}";
        $total_items = (int) $wpdb->get_var($count_query);

        // Requête pour récupérer les données
        $offset = ($current_page - 1) * $per_page;
        $data_query = "SELECT {$select} FROM {$table_name} AS up {$join} {$where_clause} {$orderby_clause} LIMIT %d OFFSET %d";
        $data_query = $wpdb->prepare($data_query, $per_page, $offset);
        
        $this->items = $wpdb->get_results($data_query);

        // Définition des en-têtes de colonnes
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        // Définition de la pagination
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }

    /**
     * Actions groupées (bulk actions)
     */
    public function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'poke-hub'),
        ];
    }

    /**
     * Traite les actions groupées
     */
    public function process_bulk_action() {
        // Vérifier l'action
        if ($this->current_action() !== 'delete') {
            return;
        }

        // Vérifier le nonce
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-' . $this->_args['plural'])) {
            return;
        }

        // Récupérer les IDs sélectionnés
        $profile_ids = isset($_REQUEST['profile_ids']) ? (array) $_REQUEST['profile_ids'] : [];
        $profile_ids = array_map('intval', $profile_ids);
        $profile_ids = array_filter($profile_ids, function($id) {
            return $id > 0;
        });

        if (empty($profile_ids)) {
            return;
        }

        // Supprimer les profils
        global $wpdb;
        $table_name = pokehub_get_table('user_profiles');
        
        if (empty($table_name)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($profile_ids), '%d'));
        $query = $wpdb->prepare(
            "DELETE FROM {$table_name} WHERE id IN ($placeholders)",
            $profile_ids
        );
        
        $wpdb->query($query);

        // Redirection avec message de succès
        $redirect_args = [
            'page' => 'poke-hub-user-profiles',
            'deleted' => count($profile_ids),
        ];
        
        // Préserver les filtres
        $preserve_params = ['filter_team', 'filter_scatterbug_pattern', 's', 'orderby', 'order'];
        foreach ($preserve_params as $param) {
            if (isset($_GET[$param]) && $_GET[$param] !== '') {
                $redirect_args[$param] = sanitize_text_field($_GET[$param]);
            }
        }

        wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
}

