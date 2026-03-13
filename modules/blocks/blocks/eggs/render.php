<?php
/**
 * Rendu du bloc "Oeufs Pokémon"
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

$source = isset($attributes['source']) ? $attributes['source'] : 'post';
$pool_id = isset($attributes['poolId']) ? (int) $attributes['poolId'] : 0;
$block_title = isset($attributes['title']) ? trim((string) $attributes['title']) : '';

if (!function_exists('pokehub_blocks_get_eggs_for_display')) {
    return '';
}

$eggs_data = pokehub_blocks_get_eggs_for_display($post_id, $source, $pool_id);

if (empty($eggs_data)) {
    return '';
}

$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-eggs-block-wrapper']);
$heading = $block_title !== '' ? $block_title : __('Eggs', 'poke-hub');

ob_start();
?>
<div <?php echo $wrapper_attributes; ?>>
    <h2 class="pokehub-block-title pokehub-eggs-block-title"><?php echo esc_html($heading); ?></h2>

    <?php
    if (function_exists('pokehub_render_egg_type_section')) {
        foreach ($eggs_data as $section) {
            echo pokehub_render_egg_type_section($section['egg_type'], $section['pokemon']);
        }
    } else {
        foreach ($eggs_data as $section) :
            $egg_type = $section['egg_type'];
            $pokemon_list = $section['pokemon'];
            $type_name = $egg_type->name_fr !== '' ? $egg_type->name_fr : $egg_type->name_en;
            $type_label = $type_name . ($egg_type->hatch_km > 0 ? ' (' . (int) $egg_type->hatch_km . ' km)' : '');
            ?>
            <div class="pokehub-eggs-type-section" data-egg-type="<?php echo esc_attr((string) $egg_type->id); ?>">
                <h3 class="pokehub-eggs-type-title">
                    <?php if (!empty($egg_type->icon_url)) : ?>
                        <img src="<?php echo esc_url($egg_type->icon_url); ?>" alt="" class="pokehub-eggs-type-icon" loading="lazy" />
                    <?php endif; ?>
                    <span><?php echo esc_html($type_label); ?></span>
                </h3>
                <ul class="pokehub-eggs-pokemon-list">
                    <?php foreach ($pokemon_list as $p) : ?>
                        <li class="pokehub-eggs-pokemon-item pokehub-eggs-rarity-<?php echo (int) $p['rarity']; ?>"
                            <?php if (!empty($p['cp_min']) || !empty($p['cp_max'])) : ?>
                                title="<?php
                                $cp_parts = [];
                                if (!empty($p['cp_min'])) $cp_parts[] = __('CP min', 'poke-hub') . ': ' . (int) $p['cp_min'];
                                if (!empty($p['cp_max'])) $cp_parts[] = __('CP max', 'poke-hub') . ': ' . (int) $p['cp_max'];
                                echo esc_attr(implode(' | ', $cp_parts));
                                ?>"
                            <?php endif; ?>>
                            <?php if (!empty($p['image_url'])) : ?>
                                <span class="pokehub-eggs-pokemon-image-wrap">
                                    <img src="<?php echo esc_url($p['image_url']); ?>" alt="<?php echo esc_attr($p['display_name']); ?>" class="pokehub-eggs-pokemon-image" loading="lazy" />
                                </span>
                            <?php endif; ?>
                            <span class="pokehub-eggs-pokemon-name"><?php echo esc_html($p['display_name']); ?></span>
                            <?php if (!empty($p['is_shiny'])) : ?>
                                <span class="pokehub-eggs-pokemon-shiny" title="<?php echo !empty($p['is_forced_shiny']) ? esc_attr__('Shiny forced', 'poke-hub') : esc_attr__('Shiny available', 'poke-hub'); ?>">✨</span>
                            <?php endif; ?>
                            <?php if (!empty($p['is_regional']) && empty($p['is_worldwide_override'])) : ?>
                                <span class="pokehub-eggs-pokemon-regional" title="<?php esc_attr_e('Regional', 'poke-hub'); ?>">🌍</span>
                            <?php endif; ?>
                            <?php if (!empty($p['is_worldwide_override'])) : ?>
                                <span class="pokehub-eggs-pokemon-worldwide" title="<?php esc_attr_e('Temporarily worldwide', 'poke-hub'); ?>">🌐</span>
                            <?php endif; ?>
                            <?php if (!empty($p['cp_min']) || !empty($p['cp_max'])) : ?>
                                <span class="pokehub-eggs-pokemon-cp">
                                    <?php
                                    $cp_text = [];
                                    if (!empty($p['cp_min'])) $cp_text[] = (int) $p['cp_min'];
                                    if (!empty($p['cp_max'])) $cp_text[] = (int) $p['cp_max'];
                                    echo esc_html(implode('–', $cp_text) . ' PC');
                                    ?>
                                </span>
                            <?php endif; ?>
                            <span class="pokehub-eggs-pokemon-rarity" aria-label="<?php echo esc_attr(sprintf(__('Rarity: %d egg(s)', 'poke-hub'), (int) $p['rarity'])); ?>">
                                <?php for ($r = 1; $r <= 5; $r++) : ?>
                                    <span class="pokehub-eggs-egg-dot <?php echo $r <= (int) $p['rarity'] ? 'filled' : ''; ?>">🥚</span>
                                <?php endfor; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php
        endforeach;
    }
    ?>
</div>
<?php
return ob_get_clean();
