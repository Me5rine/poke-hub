<?php
/**
 * Metabox article : éléments boutique avatar + image d’en-tête pour le bloc associé.
 */

if (!defined('ABSPATH')) {
    exit;
}

function pokehub_blocks_add_shop_avatar_metabox(): void {
    $screens = apply_filters('pokehub_shop_avatar_metabox_post_types', ['post', 'pokehub_event']);
    foreach ($screens as $screen) {
        add_meta_box(
            'pokehub_shop_avatar_block',
            __('Avatar shop (block)', 'poke-hub'),
            'pokehub_blocks_render_shop_avatar_metabox',
            $screen,
            'normal',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'pokehub_blocks_add_shop_avatar_metabox');

/**
 * @param WP_Post $post
 */
function pokehub_blocks_render_shop_avatar_metabox(WP_Post $post): void {
    wp_nonce_field('pokehub_save_shop_avatar_metabox', 'pokehub_shop_avatar_metabox_nonce');

    $hero_id = 0;
    $item_ids = [];
    if (function_exists('pokehub_content_get_shop_avatar')) {
        $data = pokehub_content_get_shop_avatar('post', (int) $post->ID);
        $hero_id = (int) ($data['hero_attachment_id'] ?? 0);
        $item_ids = isset($data['item_ids']) && is_array($data['item_ids']) ? array_map('intval', $data['item_ids']) : [];
    }

    $hero_url = $hero_id > 0 ? wp_get_attachment_image_url($hero_id, 'medium') : '';
    ?>
    <p class="description"><?php esc_html_e('Select existing shop items or use the form below to create one without leaving the editor (slug from names; add images in Shop admin). Order in the list is the display order.', 'poke-hub'); ?></p>
    <p>
        <label for="pokehub_shop_avatar_item_ids"><strong><?php esc_html_e('Shop items', 'poke-hub'); ?></strong></label><br>
        <select name="pokehub_shop_avatar_item_ids[]" id="pokehub_shop_avatar_item_ids" class="widefat pokehub-shop-avatar-items-select" style="width:100%;max-width:100%;" multiple="multiple" data-placeholder="<?php echo esc_attr(__('Search shop items…', 'poke-hub')); ?>">
            <?php
            if (!empty($item_ids) && function_exists('poke_hub_shop_avatar_get_items_by_ids')) {
                $rows = poke_hub_shop_avatar_get_items_by_ids($item_ids);
                foreach ($item_ids as $iid) {
                    if (empty($rows[$iid])) {
                        continue;
                    }
                    $r = $rows[$iid];
                    $lab = trim((string) $r->name_en) !== '' ? (string) $r->name_en : (string) $r->name_fr;
                    if ($lab === '') {
                        $lab = (string) $r->slug;
                    }
                    echo '<option value="' . esc_attr((string) (int) $iid) . '" selected>' . esc_html($lab) . '</option>';
                }
            }
            ?>
        </select>
    </p>
    <fieldset class="pokehub-shop-inline-create" style="margin:12px 0;padding:12px 14px;border:1px solid #c3c4c7;border-radius:4px;background:#f6f7f7;">
        <legend style="padding:0 6px;font-weight:600;"><?php esc_html_e('Quick create', 'poke-hub'); ?></legend>
        <?php if ((int) $post->ID <= 0) : ?>
            <p class="description" style="margin-top:0;"><?php esc_html_e('Save the post as a draft first to enable creating items from here.', 'poke-hub'); ?></p>
        <?php else : ?>
            <p style="margin:0 0 8px;">
                <label for="pokehub-shop-avatar-create-name-en"><strong><?php esc_html_e('English name', 'poke-hub'); ?></strong></label><br />
                <input type="text" class="widefat" id="pokehub-shop-avatar-create-name-en" autocomplete="off" />
            </p>
            <p style="margin:0 0 10px;">
                <label for="pokehub-shop-avatar-create-name-fr"><?php esc_html_e('French name (optional)', 'poke-hub'); ?></label><br />
                <input type="text" class="widefat" id="pokehub-shop-avatar-create-name-fr" autocomplete="off" />
            </p>
            <p style="margin:0;display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;">
                <button type="button" class="button button-primary" id="pokehub-shop-avatar-create-submit"><?php esc_html_e('Create and add to selection', 'poke-hub'); ?></button>
                <span id="pokehub-shop-avatar-create-inline-hint" class="description" style="display:none;flex:1 1 200px;"></span>
            </p>
            <?php if (current_user_can('manage_options')) : ?>
                <p class="description" style="margin:10px 0 0;">
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'poke-hub-shop-items', 'tab' => 'avatar'], admin_url('admin.php'))); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open Shop admin (images, categories…)', 'poke-hub'); ?></a>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </fieldset>
    <p>
        <strong><?php esc_html_e('Hero image', 'poke-hub'); ?></strong><br>
        <input type="hidden" name="pokehub_shop_avatar_hero_id" id="pokehub_shop_avatar_hero_id" value="<?php echo esc_attr((string) $hero_id); ?>" />
        <button type="button" class="button" id="pokehub-shop-avatar-hero-pick"><?php esc_html_e('Select image', 'poke-hub'); ?></button>
        <button type="button" class="button" id="pokehub-shop-avatar-hero-clear" <?php echo $hero_id ? '' : ' style="display:none"'; ?>><?php esc_html_e('Remove', 'poke-hub'); ?></button>
    </p>
    <p id="pokehub-shop-avatar-hero-preview-wrap" style="<?php echo $hero_url ? '' : 'display:none;'; ?>">
        <img id="pokehub-shop-avatar-hero-preview" src="<?php echo esc_url($hero_url); ?>" alt="" style="max-width:320px;height:auto;border-radius:6px;" />
    </p>
    <?php
}

function pokehub_blocks_save_shop_avatar_metabox(int $post_id): void {
    if (!isset($_POST['pokehub_shop_avatar_metabox_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pokehub_shop_avatar_metabox_nonce'])), 'pokehub_save_shop_avatar_metabox')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    $item_ids = isset($_POST['pokehub_shop_avatar_item_ids']) && is_array($_POST['pokehub_shop_avatar_item_ids'])
        ? array_map('intval', wp_unslash($_POST['pokehub_shop_avatar_item_ids']))
        : [];
    $hero_id = isset($_POST['pokehub_shop_avatar_hero_id']) ? (int) $_POST['pokehub_shop_avatar_hero_id'] : 0;

    if (function_exists('pokehub_content_save_shop_avatar')) {
        pokehub_content_save_shop_avatar('post', $post_id, $item_ids, $hero_id);
    }
}
add_action('save_post', 'pokehub_blocks_save_shop_avatar_metabox');

function pokehub_blocks_shop_avatar_metabox_enqueue_assets(string $hook): void {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }
    $screens = apply_filters('pokehub_shop_avatar_metabox_post_types', ['post', 'pokehub_event']);
    $screen  = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || !in_array($screen->post_type, $screens, true)) {
        return;
    }

    wp_enqueue_media();

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

    $metabox_js = (defined('POKE_HUB_BLOCKS_URL') ? POKE_HUB_BLOCKS_URL : POKE_HUB_URL . 'modules/blocks/')
        . 'admin/js/pokehub-shop-avatar-metabox-admin.js';
    wp_enqueue_script(
        'pokehub-shop-avatar-metabox-admin',
        $metabox_js,
        ['jquery', 'select2', 'media-upload'],
        defined('POKE_HUB_VERSION') ? POKE_HUB_VERSION : '1.0',
        true
    );

    global $post;
    $post_id = isset($post->ID) ? (int) $post->ID : 0;

    wp_localize_script(
        'pokehub-shop-avatar-metabox-admin',
        'pokehubShopAvatarMetabox',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('pokehub_shop_avatar_metabox_ajax'),
            'postId'  => $post_id,
            'i18n'    => [
                'searchPlaceholder' => __('Search shop items…', 'poke-hub'),
                'nameEnRequired'    => __('English name is required.', 'poke-hub'),
                'createFailed'      => __('Could not create the item.', 'poke-hub'),
                'createSuccess'     => __('Item created and added to the selection.', 'poke-hub'),
                'selectHero'        => __('Choose hero image', 'poke-hub'),
                'useHero'           => __('Use this image', 'poke-hub'),
            ],
        ]
    );
}
add_action('admin_enqueue_scripts', 'pokehub_blocks_shop_avatar_metabox_enqueue_assets', 20);
