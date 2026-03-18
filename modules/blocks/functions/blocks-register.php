<?php
// modules/blocks/functions/blocks-register.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enregistre tous les blocs Poké HUB
 */
function pokehub_blocks_register_all() {
    // Blocs utilisables en mode local ou remote : la sauvegarde va dans les tables
    // de contenu (scope content_source = même préfixe que la source Pokémon).
    // On ne dépend pas du module Pokémon pour enregistrer les blocs.
    $blocks = [
        'event-dates' => [
            // Ne doit pas dépendre du module Events : le bloc lit les dates depuis les meta / tables de contenu.
            'requires' => [],
        ],
        'wild-pokemon' => [
            'requires' => [],
        ],
        'event-quests' => [
            'requires' => [],
        ],
        'bonus' => [
            'requires' => [], // Bloc utilisable même sans le module Bonus (table content_source)
        ],
        'habitats' => [
            'requires' => [],
        ],
        'new-pokemon-evolutions' => [
            'requires' => [],
        ],
        'collection-challenges' => [
            'requires' => [],
        ],
        'special-research' => [
            'requires' => [],
        ],
        'eggs' => [
            'requires' => [],
        ],
    ];
    
    foreach ($blocks as $block_name => $config) {
        // Vérifier que les modules requis sont actifs
        $requires = $config['requires'] ?? [];
        $all_active = true;
        
        foreach ($requires as $module) {
            if (!poke_hub_is_module_active($module)) {
                $all_active = false;
                break;
            }
        }
        
        if (!$all_active) {
            continue;
        }
        
        // Enregistrer le bloc avec protection du callback de rendu
        $block_path = POKE_HUB_BLOCKS_PATH . '/blocks/' . $block_name;
        $block_json = $block_path . '/block.json';
        
        if (file_exists($block_json)) {
            $block_json_data = json_decode(file_get_contents($block_json), true);
            
            if (!$block_json_data) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Poké HUB: Erreur de parsing JSON pour le bloc ' . $block_name . ' dans: ' . $block_json);
                }
                continue;
            }
            
            $render_file = $block_path . '/render.php';
            
            // Créer un render_callback qui inclut le fichier render.php
            $render_callback = null;
            if (file_exists($render_file)) {
                $render_callback = function($attributes, $content, $block) use ($render_file) {
                    if (!file_exists($render_file)) {
                        return '';
                    }
                    
                    // Capturer le output du fichier render.php
                    ob_start();
                    $result = include $render_file;
                    $output = ob_get_clean();
                    
                    // Si le fichier retourne une string, l'utiliser. Sinon, utiliser le buffer.
                    if (is_string($result) && !empty($result)) {
                        $output = $result;
                    }
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[POKEHUB] render_callback: retour capturé, longueur=' . strlen($output));
                    }
                    
                    return $output;
                };
            }
            
            // Préparer les arguments pour register_block_type
            $args = $block_json_data;
            if ($render_callback) {
                $args['render_callback'] = $render_callback;
                // Retirer 'render' du block.json si présent pour éviter les conflits
                unset($args['render']);
            }
            
            $result = register_block_type($block_path, $args);
            
            if (defined('WP_DEBUG') && WP_DEBUG && !$result) {
                error_log('Poké HUB: Échec de l\'enregistrement du bloc ' . $block_name . ' depuis: ' . $block_path);
            }
        }
    }
}
