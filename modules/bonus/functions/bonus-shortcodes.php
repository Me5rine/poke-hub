<?php
// modules/bonus/bonus-shortcodes.php

if (!defined('ABSPATH')) { exit; }

function pokehub_bonus_shortcode_handler($atts, $content = null) {
    $atts = shortcode_atts([
        'bonus' => '',
    ], $atts, 'pokehub-bonus');

    $raw = trim($atts['bonus']);
    if (!$raw) {
        return '';
    }

    // Découpe par virgules → ["xp: desc", "raids: desc"]
    $pairs = array_filter(array_map('trim', explode(',', $raw)));
    $bonuses = [];

    foreach ($pairs as $pair) {
        // Découper sur " : "
        $parts = array_map('trim', explode(':', $pair, 2));

        $slug_raw    = $parts[0] ?? '';
        $desc        = $parts[1] ?? '';

        if (!$slug_raw) {
            continue;
        }

        $slug = sanitize_title($slug_raw);
        $bonus = pokehub_get_bonus_by_slug($slug);

        if (!$bonus) {
            continue;
        }

        $bonus['event_description'] = $desc;
        $bonuses[] = $bonus;
    }

    if (empty($bonuses)) {
        return '';
    }

    ob_start();
    ?>
    <section class="pokehub-bonuses-shortcode">
        <?php foreach ($bonuses as $bonus) : ?>
            <article class="pokehub-bonus-item">
                <?php if (!empty($bonus['image_html'])) : ?>
                    <div class="pokehub-bonus-item-image">
                        <?php echo $bonus['image_html']; ?>
                    </div>
                <?php endif; ?>

                <div class="pokehub-bonus-item-content">
                    <h3><?php echo esc_html($bonus['title']); ?></h3>

                    <?php if (!empty($bonus['event_description'])) : ?>
                        <p><?php echo wp_kses_post($bonus['event_description']); ?></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
    <?php

    return ob_get_clean();
}
add_shortcode('pokehub-bonus', 'pokehub_bonus_shortcode_handler');

function pokehub_event_bonuses_shortcode($atts) {
    $atts = shortcode_atts([
        'post_id' => 0,
    ], $atts, 'pokehub-event-bonuses');

    $post_id = (int) $atts['post_id'];
    if (!$post_id) {
        $post_id = get_the_ID();
    }

    ob_start();
    pokehub_render_post_bonuses($post_id);
    return ob_get_clean();
}
add_shortcode('pokehub-event-bonuses', 'pokehub_event_bonuses_shortcode');

