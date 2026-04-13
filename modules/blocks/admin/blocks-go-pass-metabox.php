<?php
/**
 * Metabox article : Pass GO par défaut pour le bloc « Pass GO ».
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return string
 */
function pokehub_blocks_go_pass_metabox_type_slug(): string {
    if (function_exists('pokehub_go_pass_event_type_slug')) {
        return pokehub_go_pass_event_type_slug();
    }

    return (string) apply_filters('pokehub_go_pass_event_type_slug', 'go-pass');
}

/**
 * @return array<int, array{id:int, label:string}>
 */
function pokehub_blocks_go_pass_metabox_event_options(): array {
    global $wpdb;
    if (!function_exists('pokehub_get_table')) {
        return [];
    }
    $table = pokehub_get_table('special_events');
    if ($table === '' || (function_exists('pokehub_table_exists') && !pokehub_table_exists($table))) {
        return [];
    }
    $slug = pokehub_blocks_go_pass_metabox_type_slug();
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, title, title_en, title_fr FROM {$table} WHERE event_type = %s ORDER BY start_ts DESC LIMIT 200",
            $slug
        ),
        ARRAY_A
    );
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $label_fr = isset($row['title_fr']) ? (string) $row['title_fr'] : '';
        $label_en = isset($row['title_en']) ? (string) $row['title_en'] : '';
        $label    = $label_fr !== '' ? $label_fr : ($label_en !== '' ? $label_en : (string) ($row['title'] ?? ''));
        if ($label === '') {
            $label = '#' . $id;
        }
        $out[] = ['id' => $id, 'label' => $label];
    }

    return $out;
}

function pokehub_blocks_add_go_pass_metabox(): void {
    $screens = apply_filters('pokehub_go_pass_metabox_post_types', ['post', 'pokehub_event']);
    foreach ($screens as $screen) {
        add_meta_box(
            'pokehub_go_pass_block',
            __('GO Pass (block)', 'poke-hub'),
            'pokehub_blocks_render_go_pass_metabox',
            $screen,
            'normal',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'pokehub_blocks_add_go_pass_metabox');

/**
 * @param WP_Post $post
 */
function pokehub_blocks_render_go_pass_metabox(WP_Post $post): void {
    wp_nonce_field('pokehub_save_go_pass_metabox', 'pokehub_go_pass_metabox_nonce');

    $event_id = (int) get_post_meta($post->ID, '_pokehub_go_pass_special_event_id', true);
    $mode     = get_post_meta($post->ID, '_pokehub_go_pass_display_mode', true);
    $mode     = ($mode === 'full') ? 'full' : 'summary';

    $options = pokehub_blocks_go_pass_metabox_event_options();
    ?>
    <p class="description">
        <?php esc_html_e('These settings apply to the “GO Pass” block in the content. Search the list by typing a name.', 'poke-hub'); ?>
    </p>
    <p>
        <label for="pokehub_go_pass_special_event_id"><strong><?php esc_html_e('GO Pass event', 'poke-hub'); ?></strong></label><br>
        <select name="pokehub_go_pass_special_event_id" id="pokehub_go_pass_special_event_id" class="widefat pokehub-go-pass-linked-select" style="width:100%;max-width:100%;">
            <option value="0"><?php esc_html_e('— None —', 'poke-hub'); ?></option>
            <?php foreach ($options as $opt) : ?>
                <option value="<?php echo esc_attr((string) (int) $opt['id']); ?>" <?php selected($event_id, (int) $opt['id']); ?>>
                    <?php echo esc_html((string) $opt['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label for="pokehub_go_pass_display_mode"><strong><?php esc_html_e('Default display', 'poke-hub'); ?></strong></label><br>
        <select name="pokehub_go_pass_display_mode" id="pokehub_go_pass_display_mode" class="widefat">
            <option value="summary" <?php selected($mode, 'summary'); ?>><?php esc_html_e('Summary card', 'poke-hub'); ?></option>
            <option value="full" <?php selected($mode, 'full'); ?>><?php esc_html_e('Full reward grid', 'poke-hub'); ?></option>
        </select>
    </p>
    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=poke-hub-events&action=add_go_pass')); ?>" class="button button-secondary" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('Create a new GO Pass', 'poke-hub'); ?>
        </a>
    </p>
    <p class="description">
        <?php esc_html_e('Add the “GO Pass” block from the Poké HUB category in the editor; it will use the choices above.', 'poke-hub'); ?>
    </p>
    <?php
}

/**
 * @param int|string $post_id
 */
function pokehub_blocks_save_go_pass_metabox($post_id): void {
    $post_id = (int) $post_id;
    if (!isset($_POST['pokehub_go_pass_metabox_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pokehub_go_pass_metabox_nonce'])), 'pokehub_save_go_pass_metabox')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $eid = isset($_POST['pokehub_go_pass_special_event_id']) ? (int) $_POST['pokehub_go_pass_special_event_id'] : 0;
    $mode = isset($_POST['pokehub_go_pass_display_mode']) ? sanitize_key((string) wp_unslash($_POST['pokehub_go_pass_display_mode'])) : 'summary';
    if (!in_array($mode, ['summary', 'full'], true)) {
        $mode = 'summary';
    }

    update_post_meta($post_id, '_pokehub_go_pass_special_event_id', $eid);
    update_post_meta($post_id, '_pokehub_go_pass_display_mode', $mode);
}
add_action('save_post', 'pokehub_blocks_save_go_pass_metabox');

/**
 * Select2 sur le choix du Pass GO (recherche par nom) — écran d’édition d’article.
 */
function pokehub_blocks_go_pass_metabox_enqueue_assets(string $hook): void {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }
    $screens = apply_filters('pokehub_go_pass_metabox_post_types', ['post', 'pokehub_event']);
    $screen  = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || !in_array($screen->post_type, $screens, true)) {
        return;
    }

    wp_enqueue_style(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
        [],
        '4.1.0'
    );
    wp_enqueue_script(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        ['jquery'],
        '4.1.0',
        true
    );

    wp_add_inline_script(
        'select2',
        'jQuery(function($) {
            var \$sel = $("#pokehub_go_pass_special_event_id");
            if (!\$sel.length || typeof \$sel.select2 !== "function") { return; }
            \$sel.select2({
                width: "100%",
                language: {
                    noResults: function() { return "' . esc_js(__('No results', 'poke-hub')) . '"; },
                    searching: function() { return "' . esc_js(__('Searching…', 'poke-hub')) . '"; }
                }
            });
        });',
        'after'
    );
}
add_action('admin_enqueue_scripts', 'pokehub_blocks_go_pass_metabox_enqueue_assets', 20);
