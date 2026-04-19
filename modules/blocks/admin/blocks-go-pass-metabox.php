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
 * Libellé d’affichage (FR prioritaire) pour une ligne special_events.
 *
 * @param array<string, mixed> $row
 */
function pokehub_blocks_go_pass_metabox_row_label(array $row): string {
    $id       = (int) ($row['id'] ?? 0);
    $label_fr = isset($row['title_fr']) ? (string) $row['title_fr'] : '';
    $label_en = isset($row['title_en']) ? (string) $row['title_en'] : '';
    $label    = $label_fr !== '' ? $label_fr : ($label_en !== '' ? $label_en : (string) ($row['title'] ?? ''));
    if ($label === '' && $id > 0) {
        $label = '#' . $id;
    }

    return $label;
}

/**
 * @return array<string, mixed>|null
 */
function pokehub_blocks_go_pass_metabox_get_go_pass_row(int $event_id): ?array {
    global $wpdb;
    if ($event_id <= 0 || !function_exists('pokehub_get_table')) {
        return null;
    }
    $table = pokehub_get_table('special_events');
    if ($table === '' || (function_exists('pokehub_table_exists') && !pokehub_table_exists($table))) {
        return null;
    }
    $slug = pokehub_blocks_go_pass_metabox_type_slug();
    $row  = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, title, title_en, title_fr FROM {$table} WHERE id = %d AND event_type = %s LIMIT 1",
            $event_id,
            $slug
        ),
        ARRAY_A
    );

    return is_array($row) ? $row : null;
}

/**
 * Titre temporaire pour un Pass GO créé depuis la metabox : événement (meta / Me5rine) avant le titre d’article WP.
 *
 * @param int    $post_id
 * @param string $lab_event_title_from_client Valeur lue dans le DOM (#admin_lab_event_box), éventuellement non encore enregistrée.
 * @param string $article_title_from_client   Titre écran édition (fallback).
 */
function pokehub_blocks_go_pass_metabox_resolve_stub_title(int $post_id, string $lab_event_title_from_client = '', string $article_title_from_client = ''): string {
    $lab = trim(wp_strip_all_tags($lab_event_title_from_client));
    if ($lab !== '') {
        return (string) apply_filters('pokehub_go_pass_metabox_stub_title', $lab, $post_id, 'lab_dom');
    }

    $meta = '';
    if (function_exists('poke_hub_events_get_event_meta_title')) {
        $meta = poke_hub_events_get_event_meta_title($post_id);
    } else {
        $keys = apply_filters(
            'pokehub_go_pass_metabox_stub_title_meta_keys',
            [
                '_admin_lab_event_title_fr',
                '_admin_lab_event_title_en',
                '_admin_lab_event_title',
                '_admin_lab_event_name',
                '_event_title',
            ],
            $post_id
        );
        if (is_array($keys)) {
            foreach ($keys as $key) {
                if (!is_string($key) || $key === '') {
                    continue;
                }
                $raw = get_post_meta($post_id, $key, true);
                if (!is_string($raw)) {
                    continue;
                }
                $raw = trim(wp_strip_all_tags($raw));
                if ($raw !== '') {
                    $meta = $raw;
                    break;
                }
            }
        }
    }

    if ($meta !== '') {
        return (string) apply_filters('pokehub_go_pass_metabox_stub_title', $meta, $post_id, 'post_meta');
    }

    $headline = trim(wp_strip_all_tags($article_title_from_client));
    if ($headline !== '') {
        return (string) apply_filters('pokehub_go_pass_metabox_stub_title', $headline, $post_id, 'article_headline');
    }

    $pt = get_post($post_id);
    $wp = $pt ? wp_strip_all_tags(get_the_title($pt)) : '';

    return (string) apply_filters('pokehub_go_pass_metabox_stub_title', $wp, $post_id, 'wp_post_title');
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

    $event_id = 0;
    $mode     = 'summary';
    if (function_exists('pokehub_go_pass_host_link_get_for_post')) {
        $link = pokehub_go_pass_host_link_get_for_post((int) $post->ID);
        if ($link) {
            $event_id = (int) $link['special_event_id'];
            $mode     = ($link['display_mode'] === 'full') ? 'full' : 'summary';
        }
    }

    $selected_row = $event_id > 0 ? pokehub_blocks_go_pass_metabox_get_go_pass_row($event_id) : null;
    ?>
    <p>
        <label for="pokehub_go_pass_special_event_id"><strong><?php esc_html_e('GO Pass event', 'poke-hub'); ?></strong></label><br>
        <select name="pokehub_go_pass_special_event_id" id="pokehub_go_pass_special_event_id" class="widefat pokehub-go-pass-linked-select" style="width:100%;max-width:100%;" data-placeholder="<?php echo esc_attr(__('Search GO Pass by name…', 'poke-hub')); ?>">
            <option value="0"><?php esc_html_e('— None —', 'poke-hub'); ?></option>
            <?php if ($selected_row) : ?>
                <option value="<?php echo esc_attr((string) (int) ($selected_row['id'] ?? 0)); ?>" selected>
                    <?php echo esc_html(pokehub_blocks_go_pass_metabox_row_label($selected_row)); ?>
                </option>
            <?php endif; ?>
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
        <button type="button" class="button button-secondary" id="pokehub-go-pass-create-inline">
            <?php esc_html_e('Create a new GO Pass', 'poke-hub'); ?>
        </button>
        <span id="pokehub-go-pass-create-inline-hint" class="description" style="display:none;margin-left:8px;"></span>
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

    $table_ok = function_exists('pokehub_get_table') && function_exists('pokehub_table_exists')
        && pokehub_get_table('go_pass_host_links') !== ''
        && pokehub_table_exists(pokehub_get_table('go_pass_host_links'));

    if (!$table_ok || !function_exists('pokehub_go_pass_host_link_save')) {
        return;
    }

    $ptype = get_post_type($post_id);
    if ($ptype === false) {
        $ptype = '';
    }
    pokehub_go_pass_host_link_save('local_post', $post_id, $eid, $mode, (string) $ptype);
}
add_action('save_post', 'pokehub_blocks_save_go_pass_metabox');

/**
 * Select2 + AJAX (recherche par nom) + création inline — écran d’édition d’article.
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

    wp_enqueue_script(
        'pokehub-go-pass-metabox-admin',
        POKE_HUB_URL . 'assets/js/pokehub-go-pass-metabox-admin.js',
        ['jquery', 'select2'],
        defined('POKE_HUB_VERSION') ? POKE_HUB_VERSION : '1.0',
        true
    );

    global $post;
    $post_id = isset($post->ID) ? (int) $post->ID : 0;

    wp_localize_script(
        'pokehub-go-pass-metabox-admin',
        'pokehubGoPassMetabox',
        [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('pokehub_go_pass_metabox_ajax'),
            'postId'      => $post_id,
            'placeholder' => __('Search GO Pass by name…', 'poke-hub'),
            'strings'     => [
                'noResults'    => __('No results', 'poke-hub'),
                'searching'    => __('Searching…', 'poke-hub'),
                'needsPostId'  => __('Save the post as a draft first, then try again.', 'poke-hub'),
                'createFailed' => __('Could not create or link the GO Pass.', 'poke-hub'),
                'editPassLabel' => __('Edit this GO Pass (opens in a new tab)', 'poke-hub'),
            ],
        ]
    );
}
add_action('admin_enqueue_scripts', 'pokehub_blocks_go_pass_metabox_enqueue_assets', 20);
