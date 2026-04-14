<?php
// modules/blocks/functions/blocks-special-research-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sauvegarde les études spéciales (tables de contenu).
 *
 * @param int         $post_id
 * @param array      $research
 * @param string     $research_type Optionnel. 'timed'|'special'|'masterwork'
 */
function pokehub_save_special_research(int $post_id, array $research, string $research_type = 'special'): void {
    $cleaned_research = [];
    
    foreach ($research as $research_item) {
        // Ignorer si pas de nom
        if (empty($research_item['name'])) {
            continue;
        }
        
        $cleaned_item = [
            'name' => sanitize_text_field($research_item['name']),
            'common_initial_steps' => [],
            'paths' => [],
            'common_final_steps' => [],
        ];
        
        // Nettoyer les étapes communes initiales
        if (isset($research_item['common_initial_steps']) && is_array($research_item['common_initial_steps'])) {
            foreach ($research_item['common_initial_steps'] as $step) {
                $cleaned_item['common_initial_steps'][] = pokehub_clean_research_step($step);
            }
        }
        
        // Nettoyer les chemins
        if (isset($research_item['paths']) && is_array($research_item['paths'])) {
            foreach ($research_item['paths'] as $path) {
                $cleaned_path = [
                    'name' => !empty($path['name']) ? sanitize_text_field($path['name']) : '',
                    'image_url' => !empty($path['image_url']) ? esc_url_raw($path['image_url']) : '',
                    'color' => !empty($path['color']) ? sanitize_hex_color($path['color']) : '#ff6b6b',
                    'steps' => [],
                ];
                
                // Nettoyer les étapes du chemin
                if (isset($path['steps']) && is_array($path['steps'])) {
                    foreach ($path['steps'] as $step) {
                        $cleaned_path['steps'][] = pokehub_clean_research_step($step);
                    }
                }
                
                $cleaned_item['paths'][] = $cleaned_path;
            }
        }
        
        // Nettoyer les étapes communes finales
        if (isset($research_item['common_final_steps']) && is_array($research_item['common_final_steps'])) {
            foreach ($research_item['common_final_steps'] as $step) {
                $cleaned_item['common_final_steps'][] = pokehub_clean_research_step($step);
            }
        }
        
        $cleaned_research[] = $cleaned_item;
    }

    if (function_exists('pokehub_content_save_special_research')) {
        pokehub_content_save_special_research('post', $post_id, [
            'research_type' => $research_type,
            'steps'        => $cleaned_research,
        ]);
    }
}

/**
 * Nettoie une étape d'étude spéciale
 */
function pokehub_clean_research_step(array $step): array {
    $cleaned_step = [
        'type' => 'quest',
        'quests' => [],
        'rewards' => [],
    ];
    
    // Nettoyer les quêtes (intitulé = task ; récompenses = rewards)
    if (isset($step['quests']) && is_array($step['quests'])) {
        foreach ($step['quests'] as $quest) {
            if (!is_array($quest)) {
                continue;
            }
            $task = '';
            if (isset($quest['task']) && is_string($quest['task'])) {
                $task = sanitize_text_field(wp_unslash($quest['task']));
            } elseif (isset($quest['title']) && is_string($quest['title'])) {
                $task = sanitize_text_field(wp_unslash($quest['title']));
            }
            $cleaned_quest = [
                'task'    => $task,
                'rewards' => [],
            ];

            if (isset($quest['rewards']) && is_array($quest['rewards'])) {
                foreach ($quest['rewards'] as $reward) {
                    $cleaned_quest['rewards'][] = pokehub_clean_research_reward($reward);
                }
            }

            $cleaned_step['quests'][] = $cleaned_quest;
        }
    }
    
    // Nettoyer les récompenses d'étape
    if (isset($step['rewards']) && is_array($step['rewards'])) {
        foreach ($step['rewards'] as $reward) {
            $cleaned_step['rewards'][] = pokehub_clean_research_reward($reward);
        }
    }
    
    return $cleaned_step;
}

/**
 * Nettoie une récompense d'étude spéciale
 */
function pokehub_clean_research_reward(array $reward): array {
    $cleaned_reward = [
        'type' => sanitize_key($reward['type'] ?? 'pokemon'),
    ];
    
    if ($cleaned_reward['type'] === 'pokemon') {
        $pokemon_ids = isset($reward['pokemon_ids']) ? (array) $reward['pokemon_ids'] : [];
        $raw_genders = isset($reward['pokemon_genders']) && is_array($reward['pokemon_genders']) ? $reward['pokemon_genders'] : [];
        if (function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')) {
            $parsed = pokehub_parse_post_pokemon_multiselect_tokens_with_genders($pokemon_ids, $raw_genders);
            $cleaned_reward['pokemon_ids'] = $parsed['pokemon_ids'];
            $cleaned_reward['pokemon_genders'] = $parsed['pokemon_genders'];
        } else {
            $cleaned_reward['pokemon_ids'] = array_values(array_unique(array_map('intval', array_filter($pokemon_ids, function ($id) {
                return $id !== '' && $id !== null && is_numeric($id) && (int) $id > 0;
            }))));
            $cleaned_reward['pokemon_genders'] = [];
            foreach ($raw_genders as $pid => $gender) {
                $pid = (int) $pid;
                $gender = is_string($gender) ? sanitize_key($gender) : '';
                if ($pid > 0 && in_array($pid, $cleaned_reward['pokemon_ids'], true) && in_array($gender, ['male', 'female'], true)) {
                    $cleaned_reward['pokemon_genders'][(string) $pid] = $gender;
                }
            }
        }
    } elseif (in_array($cleaned_reward['type'], ['candy', 'xl_candy', 'mega_energy'], true)) {
        $raw_pid = isset($reward['pokemon_id']) ? (string) $reward['pokemon_id'] : '';
        if (function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')) {
            $parsed_pid = pokehub_parse_post_pokemon_multiselect_tokens_with_genders(
                $raw_pid !== '' ? [$raw_pid] : [],
                null
            );
            $cleaned_reward['pokemon_id'] = (int) ($parsed_pid['pokemon_ids'][0] ?? 0);
        } else {
            $cleaned_reward['pokemon_id'] = isset($reward['pokemon_id']) ? (int) $reward['pokemon_id'] : 0;
        }
        $cleaned_reward['quantity']   = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
    } elseif ($cleaned_reward['type'] === 'item') {
        $cleaned_reward['item_id'] = isset($reward['item_id']) ? (int) $reward['item_id'] : 0;
        if ($cleaned_reward['item_id'] > 0 && function_exists('pokehub_get_item_data_by_id')) {
            $item_data = pokehub_get_item_data_by_id($cleaned_reward['item_id']);
            if ($item_data) {
                $cleaned_reward['item_name'] = $item_data['name_fr'] ?? $item_data['name_en'] ?? '';
            } else {
                $cleaned_reward['item_name'] = sanitize_text_field($reward['item_name'] ?? '');
            }
        } else {
            $cleaned_reward['item_name'] = sanitize_text_field($reward['item_name'] ?? '');
        }
        $cleaned_reward['quantity'] = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
    } else {
        $cleaned_reward['quantity'] = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
    }
    
    return $cleaned_reward;
}

