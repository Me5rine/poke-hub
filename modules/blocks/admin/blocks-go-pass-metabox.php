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
        <?php esc_html_e('If the GO Pass block does not pick an event, these settings are used as a fallback for this post.', 'poke-hub'); ?>
    </p>
    <p>
        <label for="pokehub_go_pass_special_event_id"><strong><?php esc_html_e('GO Pass event', 'poke-hub'); ?></strong></label><br>
        <select name="pokehub_go_pass_special_event_id" id="pokehub_go_pass_special_event_id" class="widefat">
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
    <p class="description">
        <?php esc_html_e('Add the “GO Pass” block (Poké HUB category) to the content. The block editor can also link another pass or override this fallback.', 'poke-hub'); ?>
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
