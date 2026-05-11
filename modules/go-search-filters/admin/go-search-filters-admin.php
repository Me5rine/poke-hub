<?php
// modules/go-search-filters/admin/go-search-filters-admin.php

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-poke-hub-go-search-filters-list-table.php';

/** Slug de la page parente « GO tools ». */
function poke_hub_go_tools_page_slug(): string {
    return 'poke-hub-go-tools';
}

/** Onglet par défaut pour les outils GO. */
function poke_hub_go_tools_default_tab(): string {
    return 'search-filters';
}

/**
 * Onglets (extensible : ajouter une clé + un bloc dans poke_hub_go_tools_render_admin_page()).
 *
 * @return array<string, string>
 */
function poke_hub_go_tools_get_tabs(): array {
    return [
        poke_hub_go_tools_default_tab() => __('Search filters', 'poke-hub'),
    ];
}

/**
 * URL admin GO tools avec surcharges GET.
 *
 * @param array<string, scalar> $query
 */
function poke_hub_go_tools_admin_url(array $query = []): string {
    $base = [
        'page' => poke_hub_go_tools_page_slug(),
        'tab'  => poke_hub_go_tools_default_tab(),
    ];

    return add_query_arg(array_merge($base, $query), admin_url('admin.php'));
}

/** Onglet courant validé contre la liste. */
function poke_hub_go_tools_current_tab(): string {
    $tabs      = poke_hub_go_tools_get_tabs();
    $requested = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : '';
    if ($requested !== '' && isset($tabs[ $requested ])) {
        return $requested;
    }

    return poke_hub_go_tools_default_tab();
}

/** Options d'écran — pagination liste filtres (uniquement onglet search-filters). */
function poke_hub_go_search_filters_load_list_screen(): void {
    if (poke_hub_go_tools_current_tab() !== poke_hub_go_tools_default_tab()) {
        return;
    }
    add_screen_option('per_page', [
        'label'   => __('Filters per page', 'poke-hub'),
        'default' => 25,
        'option'  => 'poke_hub_gsf_per_page',
    ]);
}
add_action('load-poke-hub_page_' . poke_hub_go_tools_page_slug(), 'poke_hub_go_search_filters_load_list_screen');

add_filter('set_screen_option_poke_hub_gsf_per_page', static function ($_screen_value, $_option_name, $value) {
    unset($_screen_value, $_option_name);

    return max(1, min(200, (int) $value));
}, 10, 3);

/** Traitement suppression / sauvegarde. */
function poke_hub_go_search_filters_admin_handle_requests(): void {
    if (! is_admin() || ! current_user_can('manage_options')) {
        return;
    }
    if (! isset($_GET['page']) || sanitize_key(wp_unslash((string) $_GET['page'])) !== poke_hub_go_tools_page_slug()) {
        return;
    }

    global $wpdb;
    $table = pokehub_get_table('go_search_filters');
    $base  = poke_hub_go_tools_admin_url();

    if (isset($_GET['poke_hub_gsf_delete'], $_GET['_wpnonce'])) {
        $id = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;
        if (! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_GET['_wpnonce'])), 'poke_hub_gsf_delete_' . $id)) {
            return;
        }
        if ($id <= 0 || ! poke_hub_go_search_filters_table_exists()) {
            wp_safe_redirect(add_query_arg('msg', 'err', $base));
            exit;
        }
        $row = poke_hub_go_search_filters_get_row($id);
        if (! $row || ! empty($row['is_system'])) {
            wp_safe_redirect(add_query_arg('msg', 'sys', $base));
            exit;
        }
        $wpdb->delete($table, [ 'id' => $id ], [ '%d' ]);
        if (function_exists('poke_hub_go_search_filters_bump_cache_revision')) {
            poke_hub_go_search_filters_bump_cache_revision();
        }
        wp_safe_redirect(add_query_arg('msg', 'deleted', $base));
        exit;
    }

    if (! isset($_POST['poke_hub_gsf_save'], $_POST['poke_hub_gsf_nonce'])) {
        return;
    }
    if (! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['poke_hub_gsf_nonce'])), 'poke_hub_gsf_save')) {
        return;
    }

    poke_hub_go_search_filters_ensure_table();
    if (! poke_hub_go_search_filters_table_exists()) {
        wp_safe_redirect(add_query_arg('msg', 'err', $base));
        exit;
    }

    $item_id = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
    $san     = poke_hub_go_search_filters_sanitize_post($_POST);
    $err     = poke_hub_go_search_filters_validate_row($san, $item_id > 0 ? $item_id : null);
    if ($err !== null) {
        set_transient('poke_hub_gsf_err_' . get_current_user_id(), $err, 60);
        $redir = $item_id > 0
            ? poke_hub_go_tools_admin_url([ 'item_id' => $item_id, 'msg' => 'valid' ])
            : poke_hub_go_tools_admin_url([ 'new' => '1', 'msg' => 'valid' ]);
        wp_safe_redirect($redir);
        exit;
    }

    if ($item_id > 0) {
        $existing = poke_hub_go_search_filters_get_row($item_id);
        if (! $existing) {
            wp_safe_redirect(add_query_arg('msg', 'err', $base));
            exit;
        }
        $code_for_db = ! empty($existing['is_system'])
            ? (string) ($existing['code'] ?? '')
            : (string) $san['code'];
        if ($code_for_db === '') {
            wp_safe_redirect(add_query_arg('msg', 'err', $base));
            exit;
        }
        $wpdb->update(
            $table,
            [
                'code'               => $code_for_db,
                'filter_fr'           => (string) $san['filter_fr'],
                'filter_en'           => (string) $san['filter_en'],
                'description_fr'       => (string) $san['description_fr'],
                'description_en'       => (string) $san['description_en'],
                'scope_pokemon'        => (int) $san['scope_pokemon'],
                'scope_friends'        => (int) $san['scope_friends'],
                'use_in_collections'   => (int) $san['use_in_collections'],
                'sort_order'           => (int) $san['sort_order'],
            ],
            [ 'id' => $item_id ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' ],
            [ '%d' ]
        );
    } else {
        $wpdb->insert(
            $table,
            [
                'code'               => (string) $san['code'],
                'filter_fr'           => (string) $san['filter_fr'],
                'filter_en'           => (string) $san['filter_en'],
                'description_fr'       => (string) $san['description_fr'],
                'description_en'       => (string) $san['description_en'],
                'scope_pokemon'        => (int) $san['scope_pokemon'],
                'scope_friends'        => (int) $san['scope_friends'],
                'use_in_collections'   => (int) $san['use_in_collections'],
                'is_system'            => 0,
                'sort_order'           => (int) $san['sort_order'],
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d' ]
        );
    }

    poke_hub_go_search_filters_bump_cache_revision();
    wp_safe_redirect(add_query_arg('msg', 'saved', $base));
    exit;
}
add_action('admin_init', 'poke_hub_go_search_filters_admin_handle_requests', 1);

function poke_hub_go_tools_register_menu(): void {
    add_submenu_page(
        'poke-hub',
        __('GO tools', 'poke-hub'),
        __('GO tools', 'poke-hub'),
        'manage_options',
        poke_hub_go_tools_page_slug(),
        'poke_hub_go_tools_render_admin_page'
    );
}
add_action('admin_menu', 'poke_hub_go_tools_register_menu', 17);

function poke_hub_go_tools_render_tab_nav(string $current_tab): void {
    echo '<nav class="nav-tab-wrapper wp-clearfix" aria-label="' . esc_attr__('GO tools sections', 'poke-hub') . '">';
    foreach (poke_hub_go_tools_get_tabs() as $slug => $label) {
        $url     = poke_hub_go_tools_admin_url([ 'tab' => $slug ]);
        $classes = 'nav-tab' . ( $slug === $current_tab ? ' nav-tab-active' : '' );
        printf(
            '<a href="%s" class="%s">%s</a>',
            esc_url($url),
            esc_attr($classes),
            esc_html($label)
        );
    }
    echo '</nav>';
}

function poke_hub_go_tools_render_admin_msg_notices(): void {
    $msg = isset($_GET['msg']) ? sanitize_key(wp_unslash((string) $_GET['msg'])) : '';
    if ($msg === 'saved') {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Saved.', 'poke-hub') . '</p></div>';

        return;
    }
    if ($msg === 'deleted') {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Deleted.', 'poke-hub') . '</p></div>';

        return;
    }
    if ($msg === 'sys') {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Built-in filters cannot be deleted.', 'poke-hub') . '</p></div>';

        return;
    }
    if ($msg === 'err') {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Something went wrong.', 'poke-hub') . '</p></div>';

        return;
    }
    if ($msg === 'valid') {
        $terr = get_transient('poke_hub_gsf_err_' . get_current_user_id());
        delete_transient('poke_hub_gsf_err_' . get_current_user_id());
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($terr ? (string) $terr : __('Invalid data.', 'poke-hub')) . '</p></div>';
    }
}

/** Page Poké HUB : GO tools. */
function poke_hub_go_tools_render_admin_page(): void {
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'poke-hub'));
    }

    $tab = poke_hub_go_tools_current_tab();

    echo '<div class="wrap poke-hub-go-tools">';
    echo '<h1>' . esc_html__('GO tools', 'poke-hub') . '</h1>';

    poke_hub_go_tools_render_tab_nav($tab);

    /* Autres onglets : étendre poke_hub_go_tools_get_tabs() puis router ici. */
    if ($tab === poke_hub_go_tools_default_tab()) {
        poke_hub_go_tools_render_tab_search_filters();
    }

    echo '</div>';
}

/** Onglet Search filters — liste ou édition. */
function poke_hub_go_tools_render_tab_search_filters(): void {
    poke_hub_go_search_filters_ensure_table();
    if (function_exists('poke_hub_go_search_filters_seed_if_empty')) {
        poke_hub_go_search_filters_seed_if_empty();
    }

    $is_new  = isset($_GET['new']) && (string) $_GET['new'] === '1';
    $item_id = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;

    if ($item_id || $is_new) {
        poke_hub_go_search_filters_render_form($item_id, $is_new);

        return;
    }

    echo '<div class="poke-hub-go-tools-tab poke-hub-go-tools-tab--search-filters">';

    echo '<h2 class="title">' . esc_html(__('Search filters', 'poke-hub')) . '</h2>';

    poke_hub_go_tools_render_admin_msg_notices();

    echo '<p class="description">'
        . esc_html__('Document in-game French / English keywords, where they apply (Pokémon search, friend lists, etc.). Rows marked for Collections inject tokens into generated collection search phrases.', 'poke-hub')
        . '</p>';

    if (poke_hub_go_search_filters_table_exists()) {
        echo '<p class="description">'
            . esc_html__('Regional Pokémon phrases remain configured under Pokémon → Regions (game regions).', 'poke-hub')
            . '</p>';
    }

    if (! poke_hub_go_search_filters_table_exists()) {
        echo '<p>' . esc_html__('The catalogue table could not be created. Check database permissions.', 'poke-hub') . '</p></div>';

        return;
    }

    $url_new = poke_hub_go_tools_admin_url([ 'new' => '1' ]);
    echo '<p><a class="button button-primary" href="' . esc_url($url_new) . '">' . esc_html__('Add filter', 'poke-hub') . '</a></p>';

    $search     = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['s'])) : '';
    $list_table = new Poke_Hub_Go_Search_Filters_List_Table();
    $list_table->set_search($search);
    $list_table->prepare_items();

    echo '<form id="poke-hub-go-search-filters-list-form" method="get">';
    echo '<input type="hidden" name="page" value="' . esc_attr(poke_hub_go_tools_page_slug()) . '" />';
    echo '<input type="hidden" name="tab" value="' . esc_attr(poke_hub_go_tools_default_tab()) . '" />';
    $list_table->search_box(__('Search filters', 'poke-hub'), 'poke-hub-gsf-search');
    $list_table->display();
    echo '</form>';
    echo '</div>';
}

/** Formulaire création / édition. */
function poke_hub_go_search_filters_render_form(int $item_id, bool $is_new): void {
    $row = null;
    if ($item_id > 0) {
        $row = poke_hub_go_search_filters_get_row($item_id);
    }
    if ($item_id > 0 && ! $row) {
        echo '<div class="poke-hub-go-tools-tab"><p>' . esc_html(__('Filter not found.', 'poke-hub')) . '</p></div>';

        return;
    }

    $code               = $row ? (string) ($row['code'] ?? '') : '';
    $filter_fr          = $row ? (string) ($row['filter_fr'] ?? '') : '';
    $filter_en          = $row ? (string) ($row['filter_en'] ?? '') : '';
    $description_fr     = $row ? (string) ($row['description_fr'] ?? '') : '';
    $description_en     = $row ? (string) ($row['description_en'] ?? '') : '';
    $scope_pokemon      = $row ? ! empty($row['scope_pokemon']) : true;
    $scope_friends      = $row ? ! empty($row['scope_friends']) : false;
    $use_in_collections = $row ? ! empty($row['use_in_collections']) : false;
    $sort_order         = $row ? (int) ($row['sort_order'] ?? 0) : 0;
    $is_system          = $row ? ! empty($row['is_system']) : false;

    $list_url = poke_hub_go_tools_admin_url();
    $title    = $is_new
        ? __('Add search filter', 'poke-hub')
        : __('Edit search filter', 'poke-hub');

    echo '<div class="poke-hub-go-tools-tab poke-hub-go-tools-tab--search-filters-form">';

    poke_hub_go_tools_render_admin_msg_notices();

    echo '<h2 class="title">' . esc_html($title) . '</h2>';
    echo '<p><a href="' . esc_url($list_url) . '">← ' . esc_html(__('Back to Search filters', 'poke-hub')) . '</a></p>';

    echo '<p class="description">'
        . esc_html__('French / English values are pasted into Pokémon GO exactly as searchable tokens when building phrases.', 'poke-hub')
        . '</p>';

    echo '<form method="post" action="' . esc_url($list_url) . '">';
    wp_nonce_field('poke_hub_gsf_save', 'poke_hub_gsf_nonce');
    printf('<input type="hidden" name="item_id" value="%d" />', $item_id);
    echo '<input type="hidden" name="poke_hub_gsf_save" value="1" />';

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr><th scope="row"><label for="poke_hub_gsf_code">' . esc_html__('Internal code', 'poke-hub') . '</label></th><td>';
    if ($is_new) {
        printf(
            '<input type="text" class="regular-text code" id="poke_hub_gsf_code" name="code" value="%s" required autocomplete="off" />',
            esc_attr($code)
        );
        echo '<p class="description">' . esc_html(__('Lowercase identifier (e.g. giftable). Used programmatically.', 'poke-hub')) . '</p>';
    } else {
        printf(
            '<input type="text" class="regular-text code" id="poke_hub_gsf_code" value="%s" readonly disabled />',
            esc_attr($code)
        );
        printf('<input type="hidden" name="code" value="%s" />', esc_attr($code));
        if ($is_system) {
            echo '<p class="description">' . esc_html(__('Bundled filters keep a stable code for the Collections shortcode.', 'poke-hub')) . '</p>';
        }
    }
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="poke_hub_gsf_fr">' . esc_html__('French keyword(s)', 'poke-hub') . '</label></th><td>';
    printf(
        '<input type="text" class="large-text code" id="poke_hub_gsf_fr" name="filter_fr" value="%s" autocomplete="off" />',
        esc_attr($filter_fr)
    );
    echo '<p class="description">' . esc_html(__('No « & » at the end unless you intentionally stack several tokens in one field.', 'poke-hub')) . '</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="poke_hub_gsf_en">' . esc_html__('English keyword(s)', 'poke-hub') . '</label></th><td>';
    printf(
        '<input type="text" class="large-text code" id="poke_hub_gsf_en" name="filter_en" value="%s" autocomplete="off" />',
        esc_attr($filter_en)
    );
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="poke_hub_gsf_defr">' . esc_html__('Description (French)', 'poke-hub') . '</label></th><td>';
    printf(
        '<textarea class="large-text" rows="3" id="poke_hub_gsf_defr" name="description_fr">%s</textarea>',
        esc_textarea($description_fr)
    );
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="poke_hub_gsf_deen">' . esc_html__('Description (English)', 'poke-hub') . '</label></th><td>';
    printf(
        '<textarea class="large-text" rows="3" id="poke_hub_gsf_deen" name="description_en">%s</textarea>',
        esc_textarea($description_en)
    );
    echo '</td></tr>';

    echo '<tr><th scope="row">' . esc_html(__('Applies to', 'poke-hub')) . '</th><td>';
    printf(
        '<label><input type="checkbox" name="scope_pokemon" value="1" %s /> %s</label><br />',
        checked($scope_pokemon, true, false),
        esc_html__('Pokémon search / inventory context', 'poke-hub')
    );
    printf(
        '<label><input type="checkbox" name="scope_friends" value="1" %s /> %s</label>',
        checked($scope_friends, true, false),
        esc_html__('Friend list / related player search', 'poke-hub')
    );
    echo '</td></tr>';

    echo '<tr><th scope="row">' . esc_html(__('Collections module', 'poke-hub')) . '</th><td>';
    printf(
        '<label><input type="checkbox" name="use_in_collections" value="1" %s /> %s</label>',
        checked($use_in_collections, true, false),
        esc_html__('Expose this row as {code}_fr / {code}_en in automatic collection phrases', 'poke-hub')
    );
    echo '<p class="description">' . esc_html(__('Only change this for custom codes if you also teach the front-end to read them.', 'poke-hub')) . '</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="poke_hub_gsf_order">' . esc_html__('Sort order', 'poke-hub') . '</label></th><td>';
    printf(
        '<input type="number" id="poke_hub_gsf_order" name="sort_order" value="%d" class="small-text" />',
        (int) $sort_order
    );
    echo '</td></tr>';

    echo '</tbody></table>';

    submit_button(__('Save', 'poke-hub'));
    echo '</form>';
    echo '</div>';
}
