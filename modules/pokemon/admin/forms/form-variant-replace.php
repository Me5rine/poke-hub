<?php
// modules/pokemon/admin/forms/form-variant-replace.php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Liste des lignes pokemon_form_variants pour sélecteurs (slug, libellé).
 *
 * @return array<int, object>
 */
function poke_hub_pokemon_fetch_form_variants_for_replace_ui(): array {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }
    global $wpdb;
    $table = pokehub_get_table('pokemon_form_variants');
    if (!$table) {
        return [];
    }
    $sql = "
        SELECT id, form_slug, label, category
        FROM {$table}
        ORDER BY form_slug ASC
    ";

    $rows = $wpdb->get_results($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared — aucun param dynamique dans la liste.
    return is_array($rows) ? $rows : [];
}

/**
 * Réglages rapides : retirer un routage depuis l’URL (GET + nonce).
 */
function poke_hub_pokemon_handle_variant_routing_route_clear(): void {
    if (!is_admin()) {
        return;
    }
    if (empty($_GET['poke_hub_route_clear']) || empty($_GET['from_slug'])) {
        return;
    }
    $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
    if ($page !== 'poke-hub-pokemon') {
        return;
    }
    $section = isset($_GET['ph_section']) ? sanitize_key((string) $_GET['ph_section']) : '';
    if ($section !== 'forms') {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    $from = sanitize_title(wp_unslash((string) $_GET['from_slug']));
    if ($from === '') {
        return;
    }
    check_admin_referer('poke_hub_clear_variant_route_' . $from);

    if (function_exists('poke_hub_clear_form_variant_routing_slug')) {
        poke_hub_clear_form_variant_routing_slug($from);
    }

    wp_safe_redirect(
        add_query_arg(
            [
                'page'            => 'poke-hub-pokemon',
                'ph_section'      => 'forms',
                'action'          => 'replace',
                'ph_variant_msg'  => 'routing_cleared',
            ],
            admin_url('admin.php')
        )
    );
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_variant_routing_route_clear', 9);

/**
 * POST : fusion / remplacement variant.
 */
function poke_hub_pokemon_handle_form_variant_replace_post(): void {
    if (!is_admin()) {
        return;
    }
    if (empty($_POST['poke_hub_replace_variant_do'])) {
        return;
    }
    if (empty($_POST['poke_hub_replace_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['poke_hub_replace_nonce'])), 'poke_hub_replace_form_variant')) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!function_exists('pokehub_get_table') || !function_exists('poke_hub_record_form_variant_routing_pair')) {
        return;
    }

    global $wpdb;

    $source_id   = isset($_POST['source_variant_id']) ? (int) $_POST['source_variant_id'] : 0;
    $target_id   = isset($_POST['target_variant_id']) ? (int) $_POST['target_variant_id'] : 0;
    $drop_source = !empty($_POST['remove_source_variant']);

    $redirect_err = static function (string $code) {
        wp_safe_redirect(
            add_query_arg(
                [
                    'page'           => 'poke-hub-pokemon',
                    'ph_section'     => 'forms',
                    'action'         => 'replace',
                    'ph_variant_msg' => $code,
                ],
                admin_url('admin.php')
            )
        );
        exit;
    };

    if ($source_id <= 0 || $target_id <= 0 || $source_id === $target_id) {
        $redirect_err('replace_invalid_ids');
    }

    $vtable = pokehub_get_table('pokemon_form_variants');
    $ptable = pokehub_get_table('pokemon');
    $etable = pokehub_get_table('pokemon_evolutions');
    $evlnk  = pokehub_get_table('pokemon_form_variant_events');

    if (!$vtable || !$ptable) {
        $redirect_err('replace_missing_tables');
    }

    $source_row = $wpdb->get_row($wpdb->prepare("SELECT id, form_slug FROM {$vtable} WHERE id = %d", $source_id));
    $target_row = $wpdb->get_row($wpdb->prepare("SELECT id, form_slug FROM {$vtable} WHERE id = %d", $target_id));

    $source_slug = $source_row && isset($source_row->form_slug) ? sanitize_title((string) $source_row->form_slug) : '';
    $target_slug = $target_row && isset($target_row->form_slug) ? sanitize_title((string) $target_row->form_slug) : '';

    if ($source_slug === '' || $target_slug === '' || $source_slug === $target_slug) {
        $redirect_err('replace_invalid_slug');
    }

    poke_hub_record_form_variant_routing_pair($source_slug, $target_slug);

    $counts = ['pokemon' => 0, 'evolutions_base' => 0, 'evolutions_target' => 0];

    $wpdb->query($wpdb->prepare("UPDATE {$ptable} SET form_variant_id = %d WHERE form_variant_id = %d", $target_id, $source_id));

    $counts['pokemon'] = (int) $wpdb->rows_affected;

    if ($etable) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$etable} SET base_form_variant_id = %d WHERE base_form_variant_id = %d",
            $target_id,
            $source_id
        ));
        $counts['evolutions_base'] = (int) $wpdb->rows_affected;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$etable} SET target_form_variant_id = %d WHERE target_form_variant_id = %d",
            $target_id,
            $source_id
        ));
        $counts['evolutions_target'] = (int) $wpdb->rows_affected;
    }

    if ($drop_source) {
        if ($evlnk && $source_id > 0) {
            $wpdb->delete($evlnk, ['form_variant_id' => $source_id], ['%d']);
        }
        if (function_exists('poke_hub_suppress_form_slug_from_gm_auto_create')) {
            poke_hub_suppress_form_slug_from_gm_auto_create($source_slug);
        }
        $wpdb->delete($vtable, ['id' => $source_id], ['%d']);
    }

    if (function_exists('poke_hub_flush_scatterbug_patterns_cache')) {
        poke_hub_flush_scatterbug_patterns_cache();
    }

    wp_safe_redirect(
        add_query_arg(
            [
                'page'       => 'poke-hub-pokemon',
                'ph_section' => 'forms',
                'repl_ok'    => '1',
                'repl_p'     => (string) $counts['pokemon'],
                'repl_eb'    => (string) $counts['evolutions_base'],
                'repl_et'    => (string) $counts['evolutions_target'],
                'repl_drop'  => $drop_source ? '1' : '0',
            ],
            admin_url('admin.php')
        )
    );
    exit;
}
add_action('admin_init', 'poke_hub_pokemon_handle_form_variant_replace_post', 18);

/**
 * Table admin : routages slug → slug (option poke_hub_form_variant_registry_routing).
 */
class Poke_Hub_Form_Variant_Routings_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'variant_routing',
            'plural'   => 'variant_routings',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'from_slug' => __('From slug (GM granular)', 'poke-hub'),
            'to_slug'   => __('To slug (registry)', 'poke-hub'),
        ];
    }

    protected function get_primary_column_name(): string {
        return 'from_slug';
    }

    public function get_sortable_columns() {
        return [
            'from_slug' => ['from_slug', true],
            'to_slug'   => ['to_slug', false],
        ];
    }

    public function no_items() {
        esc_html_e('No routings yet.', 'poke-hub');
    }

    /**
     * @param array{from_slug:string,to_slug:string} $item
     */
    public function column_from_slug($item) {
        $from = (string) ($item['from_slug'] ?? '');
        $nonce_action = 'poke_hub_clear_variant_route_' . $from;

        $clear_url = wp_nonce_url(
            add_query_arg(
                [
                    'page'                 => 'poke-hub-pokemon',
                    'ph_section'           => 'forms',
                    'poke_hub_route_clear' => '1',
                    'from_slug'            => $from,
                ],
                admin_url('admin.php')
            ),
            $nonce_action
        );

        $actions = [
            'remove' => sprintf(
                '<a href="%s" class="submitdelete">%s</a>',
                esc_url($clear_url),
                esc_html__('Remove routing', 'poke-hub')
            ),
        ];

        return sprintf(
            '<strong><code>%1$s</code></strong>%2$s',
            esc_html($from),
            $this->row_actions($actions)
        );
    }

    /**
     * @param array{from_slug:string,to_slug:string} $item
     */
    public function column_to_slug($item) {
        return '<code>' . esc_html((string) ($item['to_slug'] ?? '')) . '</code>';
    }

    public function column_default($item, $column_name) {
        return '';
    }

    public function prepare_items() {
        $map = function_exists('poke_hub_get_form_variant_registry_routing_map')
            ? poke_hub_get_form_variant_registry_routing_map()
            : [];

        $rows = [];
        foreach ($map as $from => $to) {
            $rows[] = [
                'from_slug' => (string) $from,
                'to_slug'   => (string) $to,
            ];
        }

        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['s'])) : '';
        $search_trim = trim($search);
        if ($search_trim !== '') {
            $needle = strtolower($search_trim);
            $rows   = array_values(
                array_filter(
                    $rows,
                    static function ($row) use ($needle) {
                        return false !== strpos(strtolower($row['from_slug']), $needle)
                            || false !== strpos(strtolower($row['to_slug']), $needle);
                    }
                )
            );
        }

        $orderby = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash((string) $_REQUEST['orderby'])) : 'from_slug';
        if (!is_string($orderby) || !in_array($orderby, ['from_slug', 'to_slug'], true)) {
            $orderby = 'from_slug';
        }
        $order = isset($_REQUEST['order']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_REQUEST['order']))) : 'ASC';
        if ($order !== 'DESC') {
            $order = 'ASC';
        }

        usort(
            $rows,
            static function ($a, $b) use ($orderby, $order) {
                $cmp = strcmp($a[$orderby], $b[$orderby]);
                return ('DESC' === $order) ? -$cmp : $cmp;
            }
        );

        $per_page     = $this->get_items_per_page('poke_hub_variant_routing_per_page', 20);
        $total_items = count($rows);
        $total_pages = $total_items > 0 ? (int) ceil($total_items / $per_page) : 1;
        $current_page = $this->get_pagenum();

        if ($current_page > $total_pages && $total_pages >= 1) {
            $current_page = $total_pages;
        }

        $offset = ($current_page - 1) * $per_page;
        $rows   = array_slice($rows, $offset, $per_page);

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
            $this->get_primary_column_name(),
        ];

        $this->items = $rows;

        $this->set_pagination_args(
            [
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => max(1, $total_pages),
            ]
        );
    }
}

/**
 * Écran Replace variant (+ liste des routages persistants).
 */
function poke_hub_pokemon_render_form_variant_replace_screen(): void {
    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to replace form variants.', 'poke-hub'));
    }

    $items = poke_hub_pokemon_fetch_form_variants_for_replace_ui();
    $pre   = isset($_GET['replace_source_id']) ? (int) $_GET['replace_source_id'] : 0;

    $back = add_query_arg(
        ['page' => 'poke-hub-pokemon', 'ph_section' => 'forms'],
        admin_url('admin.php')
    );

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Replace form variant', 'poke-hub'); ?></h1>
        <p>
            <?php esc_html_e('Moves all Pokémon (and evolution rows) using the source variant to the target variant, records a slug routing for future Game Master imports, and optionally deletes the obsolete variant row.', 'poke-hub'); ?>
        </p>
        <p>
            <a class="button" href="<?php echo esc_url($back); ?>"><?php esc_html_e('← Back to form variants', 'poke-hub'); ?></a>
        </p>

        <?php echo poke_hub_pokemon_form_variant_replace_notice_html(); ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <?php wp_nonce_field('poke_hub_replace_form_variant', 'poke_hub_replace_nonce'); ?>
            <input type="hidden" name="poke_hub_replace_variant_do" value="1" />
            <input type="hidden" name="page" value="poke-hub-pokemon" />
            <input type="hidden" name="ph_section" value="forms" />

            <style>
                .pokehub-replace-variant-compact .pokehub-replace-variant-row {
                    display: grid;
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    column-gap: 16px;
                    row-gap: 12px;
                    align-items: end;
                    margin: 12px 0;
                }
                .pokehub-replace-variant-compact .pokehub-replace-variant-row > div {
                    min-width: 0;
                }
                .pokehub-replace-variant-compact .pokehub-replace-variant-row select.pokehub-variant-select-search {
                    width: 100%;
                    max-width: 100%;
                    box-sizing: border-box;
                }
                .pokehub-replace-variant-compact .pokehub-replace-variant-row .select2-container {
                    width: 100% !important;
                    max-width: 100%;
                    box-sizing: border-box;
                }
                .pokehub-replace-variant-compact .pokehub-replace-variant-row label[for='remove_source_variant'] span {
                    display: inline-block;
                }
                @media (max-width: 960px) {
                    .pokehub-replace-variant-compact .pokehub-replace-variant-row {
                        grid-template-columns: 1fr;
                    }
                }
            </style>
            <div class="pokehub-replace-variant-compact">
                <div class="pokehub-replace-variant-row">
                    <div>
                        <label for="source_variant_id"><?php esc_html_e('Source variant', 'poke-hub'); ?></label><br />
                        <select name="source_variant_id" id="source_variant_id" class="pokehub-variant-select-search" required>
                            <option value=""><?php esc_html_e('— Select —', 'poke-hub'); ?></option>
                            <?php foreach ($items as $row) : ?>
                                <option value="<?php echo (int) $row->id; ?>" <?php selected($pre, (int) $row->id); ?>>
                                    <?php echo esc_html($row->form_slug . ' — ' . $row->label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="target_variant_id"><?php esc_html_e('Target variant', 'poke-hub'); ?></label><br />
                        <select name="target_variant_id" id="target_variant_id" class="pokehub-variant-select-search" required>
                            <option value=""><?php esc_html_e('— Select —', 'poke-hub'); ?></option>
                            <?php foreach ($items as $row) : ?>
                                <option value="<?php echo (int) $row->id; ?>">
                                    <?php echo esc_html($row->form_slug . ' — ' . $row->label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <strong style="display:block;font-weight:600;line-height:1.3;margin-bottom:4px;">
                            <?php esc_html_e('Delete source row', 'poke-hub'); ?>
                        </strong>
                        <label for="remove_source_variant" style="display:inline-flex;align-items:flex-start;gap:6px;margin:0;line-height:1.35;font-weight:inherit;">
                            <input type="checkbox" name="remove_source_variant" id="remove_source_variant" value="1" checked style="margin-top:4px;" />
                            <span><?php esc_html_e('Remove the source variant from the registry after repointing (recommended).', 'poke-hub'); ?></span>
                        </label>
                    </div>
                </div>
                <p class="submit" style="margin-top:4px;margin-bottom:0;">
                    <?php submit_button(__('Run replacement', 'poke-hub'), 'primary', 'submit', false); ?>
                </p>
            </div>
        </form>

        <h2><?php esc_html_e('Saved import routings (slug → slug)', 'poke-hub'); ?></h2>
        <p class="description">
            <?php esc_html_e('These mappings are applied on Game Master import together with builtin defaults (e.g. mega-x → mega). The filter poke_hub_gm_variant_registry_slug_aliases_map may still override entries. Use this screen for merges such as Go Tour years.', 'poke-hub'); ?>
        </p>
        <?php
        $routing_table = new Poke_Hub_Form_Variant_Routings_List_Table();
        ?>
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="poke-hub-pokemon" />
            <input type="hidden" name="ph_section" value="forms" />
            <input type="hidden" name="action" value="replace" />
            <?php if ($pre > 0) : ?>
                <input type="hidden" name="replace_source_id" value="<?php echo (int) $pre; ?>" />
            <?php endif; ?>
            <?php
            $routing_table->prepare_items();
            $routing_table->search_box(__('Search routings', 'poke-hub'), 'variant-routing-search');
            $routing_table->display();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Notices sur l’écran list / replace.
 */
function poke_hub_pokemon_form_variant_replace_notice_html(): string {
    if (!empty($_GET['repl_ok'])) {
        $p = isset($_GET['repl_p']) ? (int) $_GET['repl_p'] : 0;
        $b = isset($_GET['repl_eb']) ? (int) $_GET['repl_eb'] : 0;
        $t = isset($_GET['repl_et']) ? (int) $_GET['repl_et'] : 0;
        $d = ! empty($_GET['repl_drop']);

        $text = sprintf(
            /* translators: 1: Pokémon rows, 2: evolution base rows, 3: evolution target rows, 4: yes/no */
            __('Replacement done. Pokémon rows updated: %1$d. Evolution rows (base / target): %2$d / %3$d. Source variant removed: %4$s.', 'poke-hub'),
            $p,
            $b,
            $t,
            $d ? __('yes', 'poke-hub') : __('no', 'poke-hub')
        );

        return '<div class="notice notice-success is-dismissible"><p>' . esc_html($text) . '</p></div>';
    }

    if (empty($_GET['ph_variant_msg'])) {
        return '';
    }
    $raw = sanitize_key(wp_unslash((string) $_GET['ph_variant_msg']));
    if ($raw === 'routing_cleared') {
        return '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Routing entry removed.', 'poke-hub') . '</p></div>';
    }
    if ($raw === 'replace_invalid_ids' || $raw === 'replace_missing_tables' || $raw === 'replace_invalid_slug') {
        return '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Replacement could not run: check the selected variants.', 'poke-hub') . '</p></div>';
    }

    return '';
}
