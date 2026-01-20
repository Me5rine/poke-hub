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
        return 'Accès refusé';
    }

    $output = [];
    $output[] = '<h3>🔍 Diagnostic - Blocs Gutenberg</h3>';
    $output[] = '<ul style="list-style: none; padding: 0;">';

    // 1. Vérifier que Gutenberg est disponible
    $gutenberg_available = function_exists('register_block_type');
    $output[] = '<li style="margin: 10px 0;"><strong>Gutenberg disponible :</strong> ' . ($gutenberg_available ? '✅ OUI' : '❌ NON') . '</li>';

    // 2. Vérifier si le module Blocks est activé
    $blocks_active = poke_hub_is_module_active('blocks');
    $output[] = '<li style="margin: 10px 0;"><strong>Module Blocks activé :</strong> ' . ($blocks_active ? '✅ OUI' : '❌ NON') . '</li>';

    // 3. Vérifier les modules requis
    $events_active = poke_hub_is_module_active('events');
    $bonus_active = poke_hub_is_module_active('bonus');
    $output[] = '<li style="margin: 10px 0;"><strong>Module Events activé :</strong> ' . ($events_active ? '✅ OUI' : '❌ NON') . '</li>';
    $output[] = '<li style="margin: 10px 0;"><strong>Module Bonus activé :</strong> ' . ($bonus_active ? '✅ OUI' : '❌ NON') . '</li>';

    // 4. Vérifier les chemins
    $block_path_events = POKE_HUB_BLOCKS_PATH . '/blocks/event-dates';
    $block_json_events = $block_path_events . '/block.json';
    $render_php_events = $block_path_events . '/render.php';
    
    $output[] = '<li style="margin: 10px 0;"><strong>Chemin bloc events :</strong> ' . $block_path_events . '</li>';
    $output[] = '<li style="margin: 10px 0;"><strong>block.json events existe :</strong> ' . (file_exists($block_json_events) ? '✅ OUI' : '❌ NON') . '</li>';
    $output[] = '<li style="margin: 10px 0;"><strong>render.php events existe :</strong> ' . (file_exists($render_php_events) ? '✅ OUI' : '❌ NON') . '</li>';

    $block_path_bonus = POKE_HUB_BLOCKS_PATH . '/blocks/bonus';
    $block_json_bonus = $block_path_bonus . '/block.json';
    $render_php_bonus = $block_path_bonus . '/render.php';
    
    $output[] = '<li style="margin: 10px 0;"><strong>Chemin bloc bonus :</strong> ' . $block_path_bonus . '</li>';
    $output[] = '<li style="margin: 10px 0;"><strong>block.json bonus existe :</strong> ' . (file_exists($block_json_bonus) ? '✅ OUI' : '❌ NON') . '</li>';
    $output[] = '<li style="margin: 10px 0;"><strong>render.php bonus existe :</strong> ' . (file_exists($render_php_bonus) ? '✅ OUI' : '❌ NON') . '</li>';

    // 5. Vérifier si les blocs sont enregistrés
    if (class_exists('WP_Block_Type_Registry')) {
        $registry = WP_Block_Type_Registry::get_instance();
        $event_block = $registry->is_registered('pokehub/event-dates');
        $bonus_block = $registry->is_registered('pokehub/bonus');
        
        $output[] = '<li style="margin: 10px 0;"><strong>Bloc pokehub/event-dates enregistré :</strong> ' . ($event_block ? '✅ OUI' : '❌ NON') . '</li>';
        $output[] = '<li style="margin: 10px 0;"><strong>Bloc pokehub/bonus enregistré :</strong> ' . ($bonus_block ? '✅ OUI' : '❌ NON') . '</li>';

        // Afficher tous les blocs enregistrés
        $all_blocks = $registry->get_all_registered();
        $pokehub_blocks = [];
        foreach ($all_blocks as $name => $block) {
            if (strpos($name, 'pokehub/') === 0) {
                $pokehub_blocks[] = $name;
            }
        }
        if (!empty($pokehub_blocks)) {
            $output[] = '<li style="margin: 10px 0;"><strong>Blocs Poké HUB enregistrés :</strong> ' . implode(', ', $pokehub_blocks) . '</li>';
        } else {
            $output[] = '<li style="margin: 10px 0;"><strong>Blocs Poké HUB enregistrés :</strong> ❌ AUCUN</li>';
        }
    } else {
        $output[] = '<li style="margin: 10px 0;"><strong>Registry des blocs :</strong> ❌ Non disponible</li>';
    }

    // 6. Vérifier la constante POKE_HUB_BLOCKS_PATH
    $output[] = '<li style="margin: 10px 0;"><strong>POKE_HUB_BLOCKS_PATH défini :</strong> ' . (defined('POKE_HUB_BLOCKS_PATH') ? '✅ OUI (' . POKE_HUB_BLOCKS_PATH . ')' : '❌ NON') . '</li>';

    // 7. Vérifier si la fonction d'enregistrement est appelée
    $output[] = '<li style="margin: 10px 0;"><strong>Fonction pokehub_blocks_register_all :</strong> ' . (function_exists('pokehub_blocks_register_all') ? '✅ Existe' : '❌ N\'existe pas') . '</li>';

    // 8. Vérifier si la catégorie est enregistrée
    $output[] = '<li style="margin: 10px 0;"><strong>Fonction pokehub_register_block_category :</strong> ' . (function_exists('pokehub_register_block_category') ? '✅ Existe' : '❌ N\'existe pas') . '</li>';

    $output[] = '</ul>';

    // Recommandations
    $output[] = '<h4>💡 Recommandations :</h4>';
    $output[] = '<ul style="list-style: disc; padding-left: 20px;">';
    
    if (!$blocks_active) {
        $output[] = '<li>Activez le module <strong>Blocks</strong> dans <strong>Poké HUB → Settings → General</strong></li>';
    }
    if (!$events_active) {
        $output[] = '<li>Activez le module <strong>Events</strong> (requis pour le bloc event-dates)</li>';
    }
    if (!$bonus_active) {
        $output[] = '<li>Activez le module <strong>Bonus</strong> (requis pour le bloc bonus)</li>';
    }
    if ($blocks_active && $events_active && $bonus_active) {
        $output[] = '<li>Videz le cache WordPress et rafraîchissez l\'éditeur (Ctrl+F5)</li>';
        $output[] = '<li>Vérifiez que vous êtes bien dans l\'éditeur Gutenberg (pas l\'éditeur classique)</li>';
    }
    
    $output[] = '</ul>';

    return implode("\n", $output);
}
add_shortcode('pokehub_debug_blocks', 'pokehub_debug_blocks_registration');


