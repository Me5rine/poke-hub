<?php
// modules/blocks/functions/blocks-register.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enregistre tous les blocs Gutenberg du module Blocks
 */
function pokehub_blocks_register_all() {
    // Vérifier que Gutenberg est disponible
    if (!function_exists('register_block_type')) {
        return;
    }

    // Liste des blocs à enregistrer
    $blocks = [
        'event-dates' => [
            'path' => POKE_HUB_BLOCKS_PATH . '/blocks/event-dates',
            'requires' => ['events'], // Nécessite le module events
            'has_js' => false, // Bloc PHP dynamique uniquement
        ],
        'bonus' => [
            'path' => POKE_HUB_BLOCKS_PATH . '/blocks/bonus',
            'requires' => ['bonus'], // Nécessite le module bonus
            'has_js' => false, // Bloc PHP dynamique uniquement
        ],
        'event-quests' => [
            'path' => POKE_HUB_BLOCKS_PATH . '/blocks/event-quests',
            'requires' => ['events'], // Nécessite le module events
            'has_js' => false, // Bloc PHP dynamique uniquement
        ],
        // Exemples de futurs blocs :
        // 'pokemon' => [
        //     'path' => POKE_HUB_BLOCKS_PATH . '/blocks/pokemon',
        //     'requires' => ['pokemon'],
        //     'has_js' => true, // Bloc avec JavaScript/React
        // ],
        // 'shiny' => [
        //     'path' => POKE_HUB_BLOCKS_PATH . '/blocks/shiny',
        //     'requires' => ['pokemon'],
        //     'has_js' => true,
        // ],
    ];

    foreach ($blocks as $block_slug => $block_config) {
        // Vérifier les dépendances
        $can_register = true;
        if (!empty($block_config['requires'])) {
            foreach ($block_config['requires'] as $required_module) {
                if (!poke_hub_is_module_active($required_module)) {
                    $can_register = false;
                    break;
                }
            }
        }

        if (!$can_register) {
            continue;
        }

        // Enregistrer le bloc depuis block.json
        $block_path = $block_config['path'];
        if (file_exists($block_path . '/block.json')) {
            // Enregistrer le bloc - WordPress charge automatiquement les assets depuis block.json
            $result = register_block_type($block_path);
            
            // Enregistrer manuellement les scripts JavaScript si nécessaire
            // WordPress devrait les charger automatiquement, mais on s'assure qu'ils sont bien enregistrés
            $block_json = json_decode(file_get_contents($block_path . '/block.json'), true);
            
            if ($result && isset($block_json['editorScript'])) {
                $js_file = str_replace('file:./', '', $block_json['editorScript']);
                $js_path = $block_path . '/' . $js_file;
                $js_url = POKE_HUB_BLOCKS_URL . 'blocks/' . $block_slug . '/' . $js_file;
                
                if (file_exists($js_path)) {
                    // Enregistrer le script pour l'éditeur
                    $script_handle = 'pokehub-' . $block_slug . '-block-editor';
                    wp_register_script(
                        $script_handle,
                        $js_url,
                        ['wp-blocks', 'wp-element', 'wp-i18n'],
                        POKE_HUB_VERSION,
                        true
                    );
                    
                    // S'assurer que le script est chargé dans l'éditeur
                    add_action('enqueue_block_editor_assets', function() use ($script_handle) {
                        wp_enqueue_script($script_handle);
                    });
                }
            }
            
            // Debug : vérifier que le bloc est bien enregistré
            if (WP_DEBUG && !$result) {
                error_log('Poké HUB: Échec de l\'enregistrement du bloc ' . $block_slug . ' depuis: ' . $block_path);
            }
        } else {
            if (WP_DEBUG) {
                error_log('Poké HUB: block.json non trouvé pour le bloc ' . $block_slug . ' dans: ' . $block_path);
            }
        }
    }
}
add_action('init', 'pokehub_blocks_register_all');

