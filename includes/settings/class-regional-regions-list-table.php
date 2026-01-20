<?php
// includes/settings/class-regional-regions-list-table.php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Poke_Hub_Regional_Regions_List_Table extends WP_List_Table {

    public function __construct() {
        // Determine screen based on context (Settings or Pokemon admin)
        $screen_id = 'poke-hub_page_poke-hub-settings';
        if (isset($_GET['page']) && $_GET['page'] === 'poke-hub-pokemon') {
            $screen_id = 'poke-hub_page_poke-hub-pokemon';
        }
        
        parent::__construct([
            'singular' => 'regional_region',
            'plural'   => 'regional_regions',
            'ajax'     => false,
            'screen'   => $screen_id,
        ]);
    }
    
    /**
     * Override get_pagenum() pour utiliser un paramètre de pagination unique
     */
    public function get_pagenum() {
        $key = 'paged_regions';
        $pagenum = isset($_REQUEST[$key]) ? absint($_REQUEST[$key]) : 1;
        return max(1, $pagenum);
    }

    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            'slug'       => __('Slug', 'poke-hub'),
            'name_fr'    => __('Name (FR)', 'poke-hub'),
            'name_en'    => __('Name (EN)', 'poke-hub'),
            'countries'  => __('Countries', 'poke-hub'),
            'description' => __('Description', 'poke-hub'),
        ];
    }

    public function get_sortable_columns() {
        return [
            'slug'    => ['slug', true],
            'name_fr' => ['name_fr', true],
            'name_en' => ['name_en', true],
        ];
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="ids[]" value="%d" />',
            (int) $item['id']
        );
    }

    public function column_slug($item) {
        // Determine base URL based on context (Settings or Pokemon admin)
        if (isset($_GET['page']) && $_GET['page'] === 'poke-hub-pokemon') {
            $edit_url = add_query_arg(
                [
                    'page'       => 'poke-hub-pokemon',
                    'ph_section' => 'regional_regions',
                    'action'     => 'edit',
                    'id'         => (int) $item['id'],
                ],
                admin_url('admin.php')
            );

            $delete_url = wp_nonce_url(
                add_query_arg(
                    [
                        'page'       => 'poke-hub-pokemon',
                        'ph_section' => 'regional_regions',
                        'action'     => 'delete',
                        'id'         => (int) $item['id'],
                    ],
                    admin_url('admin.php')
                ),
                'poke_hub_delete_regional_region_' . (int) $item['id']
            );
        } else {
            $edit_url = add_query_arg(
                [
                    'page'   => 'poke-hub-settings',
                    'tab'    => 'regional-mapping',
                    'subtab' => 'regions',
                    'action' => 'edit',
                    'id'     => (int) $item['id'],
                ],
                admin_url('admin.php')
            );

            $delete_url = wp_nonce_url(
                add_query_arg(
                    [
                        'page'   => 'poke-hub-settings',
                        'tab'    => 'regional-mapping',
                        'subtab' => 'regions',
                        'action' => 'delete',
                        'id'     => (int) $item['id'],
                    ],
                    admin_url('admin.php')
                ),
                'poke_hub_delete_regional_region_' . (int) $item['id']
            );
        }

        $slug = esc_html($item['slug']);

        $actions = [];
        $actions['edit'] = sprintf('<a href="%s">%s</a>', esc_url($edit_url), esc_html__('Edit', 'poke-hub'));
        $actions['delete'] = sprintf(
            '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
            esc_url($delete_url),
            esc_attr__('Are you sure you want to delete this region?', 'poke-hub'),
            esc_html__('Delete', 'poke-hub')
        );

        return sprintf(
            '<strong><a href="%1$s">%2$s</a></strong> %3$s',
            esc_url($edit_url),
            $slug,
            $this->row_actions($actions)
        );
    }

    public function column_name_fr($item) {
        return esc_html($item['name_fr'] ?? '');
    }

    public function column_name_en($item) {
        return esc_html($item['name_en'] ?? '');
    }

    public function column_countries($item) {
        $countries = $item['countries'] ?? [];
        $count = is_array($countries) ? count($countries) : 0;
        return sprintf(
            '%d %s',
            $count,
            _n('country', 'countries', $count, 'poke-hub')
        );
    }

    public function column_description($item) {
        return esc_html($item['description'] ?? '');
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

        check_admin_referer('bulk-regional_regions');

        if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
            return;
        }

        $ids = array_map('intval', $_POST['ids']);
        $ids = array_filter($ids);

        if (!$ids) {
            return;
        }

        foreach ($ids as $id) {
            if (function_exists('poke_hub_pokemon_delete_regional_region')) {
                poke_hub_pokemon_delete_regional_region($id);
            }
        }
    }

    public function prepare_items() {
        if (!function_exists('poke_hub_pokemon_get_regional_regions_from_db')) {
            $this->items = [];
            return;
        }

        $regions = poke_hub_pokemon_get_regional_regions_from_db();

        $per_page     = $this->get_items_per_page('pokehub_regional_regions_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'name_fr';
        $order   = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        // Search filter
        $search = isset($_REQUEST['s']) ? trim(wp_unslash($_REQUEST['s'])) : '';

        // Convert to array of arrays for sorting and filtering
        $items = [];
        foreach ($regions as $region) {
            // Apply search filter
            if (!empty($search)) {
                $search_lower = strtolower($search);
                $slug = strtolower($region['slug'] ?? '');
                $name_fr = strtolower($region['name_fr'] ?? '');
                $name_en = strtolower($region['name_en'] ?? '');
                $description = strtolower($region['description'] ?? '');
                
                // Check if search term matches any field
                if (
                    strpos($slug, $search_lower) === false &&
                    strpos($name_fr, $search_lower) === false &&
                    strpos($name_en, $search_lower) === false &&
                    strpos($description, $search_lower) === false
                ) {
                    continue; // Skip this item if it doesn't match search
                }
            }
            
            $items[] = $region;
        }

        // Sort
        usort($items, function($a, $b) use ($orderby, $order) {
            $a_val = $a[$orderby] ?? '';
            $b_val = $b[$orderby] ?? '';
            $result = strcasecmp($a_val, $b_val);
            return $order === 'ASC' ? $result : -$result;
        });

        $total_items = count($items);
        $this->items = array_slice($items, $offset, $per_page);

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
    
    /**
     * Override pagination() pour utiliser le paramètre paged_regions au lieu de paged
     */
    protected function pagination($which) {
        if (empty($this->_pagination_args)) {
            return;
        }

        $total_items = $this->_pagination_args['total_items'];
        $total_pages = $this->_pagination_args['total_pages'];
        $infinite_scroll = false;
        if (isset($this->_pagination_args['infinite_scroll'])) {
            $infinite_scroll = $this->_pagination_args['infinite_scroll'];
        }

        if ('top' === $which && $total_pages > 1) {
            $this->screen->render_screen_reader_content('heading_pagination');
        }

        $output = '<span class="displaying-num">' . sprintf(
            /* translators: %s: Number of items. */
            _n('%s item', '%s items', $total_items, 'poke-hub'),
            number_format_i18n($total_items)
        ) . '</span>';

        $current = $this->get_pagenum();
        
        // Construire l'URL de base en préservant tous les paramètres GET sauf paged_regions
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : admin_url('admin.php');
        $base_url = remove_query_arg('paged_regions', $request_uri);

        $page_links = [];

        $total_pages_before = '<span class="paging-input">';
        $total_pages_after  = '</span></span>';

        $disable_first = $current === 1;
        $disable_last  = $current === $total_pages;
        $disable_prev  = $current === 1;
        $disable_next  = $current === $total_pages;

        if ($disable_first) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='first-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(remove_query_arg('paged_regions', $base_url)),
                __('First page', 'poke-hub'),
                '&laquo;'
            );
        }

        if ($disable_prev) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
        } else {
            $prev_page = $current - 1;
            if ($prev_page < 1) {
                $prev_page = 1;
            }
            $page_links[] = sprintf(
                "<a class='prev-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(add_query_arg('paged_regions', $prev_page, $base_url)),
                __('Previous page', 'poke-hub'),
                '&lsaquo;'
            );
        }

        if ('bottom' === $which) {
            $html_current_page  = $current;
            $total_pages_before = '<span class="screen-reader-text">' . __('Current Page', 'poke-hub') . '</span><span id="table-paging" class="paging-input"><span class="tablenav-paging-text">';
        } else {
            $html_current_page = sprintf(
                "%s<input class='current-page' id='current-page-selector' type='text' name='paged_regions' value='%s' size='%d' aria-describedby='table-paging' /><span class='tablenav-paging-text'>",
                '<label for="current-page-selector" class="screen-reader-text">' . __('Current Page', 'poke-hub') . '</label>',
                $current,
                strlen($total_pages)
            );
        }
        $html_total_pages = sprintf("<span class='total-pages'>%s</span>", number_format_i18n($total_pages));
        $page_links[]     = $total_pages_before . sprintf(
            /* translators: 1: Current page, 2: Total pages. */
            _x('%1$s of %2$s', 'paging', 'poke-hub'),
            $html_current_page,
            $html_total_pages
        ) . $total_pages_after;

        if ($disable_next) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
        } else {
            $next_page = $current + 1;
            if ($next_page > $total_pages) {
                $next_page = $total_pages;
            }
            $page_links[] = sprintf(
                "<a class='next-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(add_query_arg('paged_regions', $next_page, $base_url)),
                __('Next page', 'poke-hub'),
                '&rsaquo;'
            );
        }

        if ($disable_last) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='last-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(add_query_arg('paged_regions', $total_pages, $base_url)),
                __('Last page', 'poke-hub'),
                '&raquo;'
            );
        }

        $pagination_links_class = 'pagination-links';
        if (!empty($infinite_scroll)) {
            $pagination_links_class = ' hide-if-js';
        }
        $output .= "\n<span class='$pagination_links_class'>" . implode("\n", $page_links) . '</span>';

        if ($total_pages) {
            $page_class = $total_pages < 2 ? ' one-page' : '';
        } else {
            $page_class = ' no-pages';
        }
        $this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";

        echo $this->_pagination;
    }
    
    /**
     * Display search box
     */
    public function search_box($text, $input_id) {
        if (empty($_REQUEST['s']) && !$this->has_items()) {
            return;
        }

        $input_id = $input_id . '-search-input';

        if (!empty($_REQUEST['orderby'])) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr($_REQUEST['orderby']) . '" />';
        }
        if (!empty($_REQUEST['order'])) {
            echo '<input type="hidden" name="order" value="' . esc_attr($_REQUEST['order']) . '" />';
        }
        if (!empty($_REQUEST['page'])) {
            echo '<input type="hidden" name="page" value="' . esc_attr($_REQUEST['page']) . '" />';
        }
        // For Pokemon admin, use ph_section instead of tab/subtab
        if (isset($_REQUEST['ph_section'])) {
            echo '<input type="hidden" name="ph_section" value="' . esc_attr($_REQUEST['ph_section']) . '" />';
        }
        // For Settings, use tab/subtab
        if (!empty($_REQUEST['tab'])) {
            echo '<input type="hidden" name="tab" value="' . esc_attr($_REQUEST['tab']) . '" />';
        }
        if (!empty($_REQUEST['subtab'])) {
            echo '<input type="hidden" name="subtab" value="' . esc_attr($_REQUEST['subtab']) . '" />';
        }
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo $text; ?>:</label>
            <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s" value="<?php _admin_search_query(); ?>" />
            <?php submit_button($text, 'button', '', false, ['id' => 'search-submit']); ?>
        </p>
        <?php
    }
}

