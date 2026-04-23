<?php
// modules/eggs/public/eggs-shortcode.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode [pokehub_all_eggs] : affiche tous les œufs actifs au moment de la consultation.
 * Liste par distance (2, 5, 7, 10 km…), normaux puis suivi d'exploration pour 5/10 km, par rareté.
 */
function pokehub_all_eggs_shortcode($atts) {
    if (!function_exists('pokehub_get_all_active_eggs_for_display') || !function_exists('pokehub_render_egg_type_section')) {
        return '<p>' . esc_html__('Eggs data is not available.', 'poke-hub') . '</p>';
    }
    if (function_exists('poke_hub_enqueue_bundled_front_style')) {
        poke_hub_enqueue_bundled_front_style('pokehub-eggs-front', 'poke-hub-eggs-front.css', []);
    }
    $sections = pokehub_get_all_active_eggs_for_display();
    if (empty($sections)) {
        return '<p>' . esc_html__('No eggs available at the moment.', 'poke-hub') . '</p>';
    }
    $title = isset($atts['title']) && (string) $atts['title'] !== '' ? trim((string) $atts['title']) : __('Eggs', 'poke-hub');
    ob_start();
    ?>
    <div class="pokehub-all-eggs-page pokehub-eggs-block-wrapper">
        <h1 class="pokehub-page-title pokehub-eggs-block-title"><?php echo esc_html($title); ?></h1>
        <?php foreach ($sections as $section) : ?>
            <?php echo pokehub_render_egg_type_section($section['egg_type'], $section['pokemon']); ?>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('pokehub_all_eggs', 'pokehub_all_eggs_shortcode');
