<?php
// modules/blocks/functions/blocks-debug.php
// Fichier de diagnostic pour le troubleshooting des blocs Gutenberg
// Ce fichier n'est chargé que si explicitement requis (voir blocks.php)

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode de diagnostic pour vérifier l'enregistrement des blocs
 */
function pokehub_debug_blocks_registration() {
    if (!current_user_can('manage_options')) {
        return esc_html__('Access denied', 'poke-hub');
    }

    $output = [];
    $output[] = '<h3>🔍 ' . esc_html__('Diagnostic - Gutenberg Blocks', 'poke-hub') . '</h3>';
    $output[] = '<ul style="list-style: none; padding: 0;">';

    $yes = '✅ ' . _x('Yes', 'debug diagnostic', 'poke-hub');
    $no = '❌ ' . _x('No', 'debug diagnostic', 'poke-hub');

    $gutenberg_available = function_exists('register_block_type');
    $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('Gutenberg available', 'poke-hub') . ':</strong> ' . ($gutenberg_available ? $yes : $no) . '</li>';

    $blocks_active = poke_hub_is_module_active('blocks');
    $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('Blocks module active', 'poke-hub') . ':</strong> ' . ($blocks_active ? $yes : $no) . '</li>';

    $events_active = poke_hub_is_module_active('events');
    $bonus_active = poke_hub_is_module_active('bonus');
    $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('Events module active', 'poke-hub') . ':</strong> ' . ($events_active ? $yes : $no) . '</li>';
    $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('Bonus module active', 'poke-hub') . ':</strong> ' . ($bonus_active ? $yes : $no) . '</li>';

    $block_path_events = POKE_HUB_BLOCKS_PATH . '/blocks/event-dates';
    $block_json_events = $block_path_events . '/block.json';
    $render_php_events = $block_path_events . '/render.php';
    $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('Event block path', 'poke-hub') . ':</strong> ' . esc_html($block_path_events) . '</li>';
    $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('event-dates block.json exists', 'poke-hub') . ':</strong> ' . (file_exists($block_json_events) ? $yes : $no) . '</li>';
    $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('event-dates render.php exists', 'poke-hub') . ':</strong> ' . (file_exists($render_php_events) ? $yes : $no) . '</li>';

    $block_path_bonus = POKE_HUB_BLOCKS_PATH . '/blocks/bonus';
    $block_json_bonus = $block_path_bonus . '/block.json';
    $render_php_bonus = $block_path_bonus . '/render.php';
    $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('Bonus block path', 'poke-hub') . ':</strong> ' . esc_html($block_path_bonus) . '</li>';
    $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('bonus block.json exists', 'poke-hub') . ':</strong> ' . (file_exists($block_json_bonus) ? $yes : $no) . '</li>';
    $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('bonus render.php exists', 'poke-hub') . ':</strong> ' . (file_exists($render_php_bonus) ? $yes : $no) . '</li>';

    if (class_exists('WP_Block_Type_Registry')) {
        $registry = WP_Block_Type_Registry::get_instance();
        $event_block = $registry->is_registered('pokehub/event-dates');
        $bonus_block = $registry->is_registered('pokehub/bonus');
        $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('Block pokehub/event-dates registered', 'poke-hub') . ':</strong> ' . ($event_block ? $yes : $no) . '</li>';
        $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('Block pokehub/bonus registered', 'poke-hub') . ':</strong> ' . ($bonus_block ? $yes : $no) . '</li>';

        $all_blocks = $registry->get_all_registered();
        $pokehub_blocks = [];
        foreach ($all_blocks as $name => $block) {
            if (strpos($name, 'pokehub/') === 0) {
                $pokehub_blocks[] = $name;
            }
        }
        if (!empty($pokehub_blocks)) {
            $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('Poké HUB blocks registered', 'poke-hub') . ':</strong> ' . esc_html(implode(', ', $pokehub_blocks)) . '</li>';
        } else {
            $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('Poké HUB blocks registered', 'poke-hub') . ':</strong> ❌ ' . esc_html__('None', 'poke-hub') . '</li>';
        }
    } else {
        $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('Block registry', 'poke-hub') . ':</strong> ' . $no . ' ' . esc_html__('not available', 'poke-hub') . '</li>';
    }

    $output[] = '<li style="margin: 10px 0;"><strong>POKE_HUB_BLOCKS_PATH ' . esc_html__('defined', 'poke-hub') . ':</strong> ' . (defined('POKE_HUB_BLOCKS_PATH') ? $yes . ' (' . esc_html(POKE_HUB_BLOCKS_PATH) . ')' : $no) . '</li>';
    $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('Function pokehub_blocks_register_all', 'poke-hub') . ':</strong> ' . (function_exists('pokehub_blocks_register_all') ? '✅ ' . esc_html__('Exists', 'poke-hub') : '❌ ' . esc_html__('Does not exist', 'poke-hub')) . '</li>';
    $output[] = '<li style="margin: 10px 0;"><strong>' . esc_html__('Function pokehub_register_block_category', 'poke-hub') . ':</strong> ' . (function_exists('pokehub_register_block_category') ? '✅ ' . esc_html__('Exists', 'poke-hub') : '❌ ' . esc_html__('Does not exist', 'poke-hub')) . '</li>';
    $output[] = '</ul>';

    $output[] = '<h4>💡 ' . esc_html__('Recommendations', 'poke-hub') . ':</h4>';
    $output[] = '<ul style="list-style: disc; padding-left: 20px;">';
    if (!$blocks_active) {
        $output[] = '<li>' . esc_html__('Enable the Blocks module in Poké HUB → Settings → General', 'poke-hub') . '</li>';
    }
    if (!$events_active) {
        $output[] = '<li>' . esc_html__('Events module is optional. If an event block shows no data, make sure the post has dates/content saved (metabox) so it is synchronized into the content tables.', 'poke-hub') . '</li>';
    }
    if ($blocks_active && $events_active) {
        $output[] = '<li>' . esc_html__('Clear WordPress cache and refresh the editor (Ctrl+F5)', 'poke-hub') . '</li>';
        $output[] = '<li>' . esc_html__('Make sure you are in the Gutenberg editor (not the classic editor)', 'poke-hub') . '</li>';
    }
    $output[] = '</ul>';

    return implode("\n", $output);
}
add_shortcode('pokehub_debug_blocks', 'pokehub_debug_blocks_registration');


