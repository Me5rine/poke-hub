<?php
/**
 * Bloc « Avatar shop highlights »
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = 0;
if (isset($block) && is_object($block) && !empty($block->context['postId'])) {
    $post_id = (int) $block->context['postId'];
}
if (!$post_id) {
    $post_id = (int) get_the_ID();
}
if (!$post_id) {
    $post_id = (int) get_queried_object_id();
}
if (!$post_id && !empty($GLOBALS['post']->ID)) {
    $post_id = (int) $GLOBALS['post']->ID;
}
if (!$post_id) {
    return '';
}

$auto_detect = $attributes['autoDetect'] ?? true;
if (!$auto_detect) {
    return '';
}

if (!function_exists('pokehub_content_get_shop_avatar') || !function_exists('poke_hub_shop_avatar_get_items_by_ids')) {
    return '';
}

$data = pokehub_content_get_shop_avatar('post', $post_id);
$item_ids = isset($data['item_ids']) && is_array($data['item_ids']) ? $data['item_ids'] : [];
$hero_id = (int) ($data['hero_attachment_id'] ?? 0);

if (empty($item_ids) && $hero_id <= 0) {
    return '';
}

$items_by_id = !empty($item_ids) ? poke_hub_shop_avatar_get_items_by_ids($item_ids) : [];
$ordered_items = [];
foreach ($item_ids as $iid) {
    if (!empty($items_by_id[$iid])) {
        $ordered_items[] = $items_by_id[$iid];
    }
}

$has_hero = $hero_id > 0;
$has_items = !empty($ordered_items);

$hero_alt = '';
if ($has_hero) {
    $hero_alt = (string) get_post_meta($hero_id, '_wp_attachment_image_alt', true);
    if ($hero_alt === '') {
        $hero_alt = sprintf(
            /* translators: %s: post title. */
            __('Cover for "%s"', 'poke-hub'),
            wp_strip_all_tags(get_the_title($post_id))
        );
    }
}

$intro_html = '';
if ($has_items && function_exists('pokehub_shop_highlights_resolve_event_label') && function_exists('pokehub_shop_highlights_collect_item_display_names')) {
    $raw_names = pokehub_shop_highlights_collect_item_display_names($ordered_items);
    if (!empty($raw_names)) {
        $names_joined = implode(', ', array_map('esc_html', $raw_names));
        $event_label  = pokehub_shop_highlights_resolve_event_label($post_id);
        $intro_html   = sprintf(
            /* translators: 1: comma-separated avatar shop item names, 2: event name. */
            __('The following avatar shop items — %1$s — will be available in the in-game shop for the event "%2$s".', 'poke-hub'),
            $names_joined,
            esc_html($event_label)
        );
    }
}

$show_lead = $has_hero || $intro_html !== '';

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'pokehub-shop-avatar-highlights-block pokehub-shop-highlights',
]);

ob_start();
?>
<div <?php echo $wrapper_attributes; ?>>
    <?php echo function_exists('pokehub_render_block_title')
        ? pokehub_render_block_title(__('New items in the avatar shop', 'poke-hub'), 'shop-avatar-highlights')
        : '<h2 class="pokehub-block-title">' . esc_html__('New items in the avatar shop', 'poke-hub') . '</h2>'; ?>

    <?php if ($has_hero || $has_items) : ?>
        <div class="pokehub-shop-highlights-panel">
            <?php if ($show_lead) : ?>
                <div class="pokehub-shop-highlights-lead">
                    <?php if ($has_hero) : ?>
                        <figure class="pokehub-shop-highlights-lead__figure pokehub-shop-highlights-hero pokehub-shop-highlights-hero--avatar" aria-label="<?php esc_attr_e('Cover image', 'poke-hub'); ?>">
                            <?php
                            echo wp_get_attachment_image(
                                $hero_id,
                                'large',
                                false,
                                [
                                    'class'   => 'pokehub-shop-highlights-hero-img',
                                    'alt'     => $hero_alt,
                                    'loading' => 'lazy',
                                    'sizes'   => '(max-width: 767px) 100vw, min(40vw, 420px)',
                                ]
                            );
                            ?>
                        </figure>
                    <?php endif; ?>
                    <?php if ($intro_html !== '') : ?>
                        <p class="pokehub-shop-highlights-lead__text"><?php echo $intro_html; ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($has_items) : ?>
                <div class="pokehub-shop-highlights-panel__tiles">
                    <h3 class="pokehub-shop-highlights-panel__tiles-title"><?php esc_html_e('Avatar items in this event', 'poke-hub'); ?></h3>
                    <div class="pokehub-wild-pokemon-grid pokehub-shop-highlights-items pokehub-shop-avatar-items-grid">
                        <?php foreach ($ordered_items as $row) : ?>
                            <?php
                            $urls = poke_hub_shop_avatar_get_item_image_urls($row);
                            $display = trim((string) $row->name_fr) !== '' ? (string) $row->name_fr : (string) $row->name_en;
                            if ($display === '') {
                                $display = (string) $row->slug;
                            }
                            ?>
                            <div class="pokehub-wild-pokemon-card pokehub-shop-avatar-item-card">
                                <div class="pokehub-wild-pokemon-card-inner">
                                    <?php if ($urls['primary'] !== '') : ?>
                                        <div class="pokehub-wild-pokemon-image-wrapper">
                                            <picture>
                                                <source type="image/webp" srcset="<?php echo esc_url($urls['primary']); ?>" />
                                                <img src="<?php echo esc_url($urls['fallback']); ?>"
                                                     alt="<?php echo esc_attr($display); ?>"
                                                     class="pokehub-wild-pokemon-image"
                                                     loading="lazy"
                                                     onerror="this.style.display='none';" />
                                            </picture>
                                        </div>
                                    <?php endif; ?>
                                    <div class="pokehub-wild-pokemon-name"><?php echo esc_html($display); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php
return (string) ob_get_clean();
