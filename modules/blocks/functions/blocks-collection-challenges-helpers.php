<?php
// modules/blocks/functions/blocks-collection-challenges-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sauvegarde les défis de collection
 */
function pokehub_save_collection_challenges(int $post_id, array $challenges): void {
    $cleaned_challenges = [];
    
    foreach ($challenges as $challenge) {
        // Ignorer si pas de nom
        if (empty($challenge['name'])) {
            continue;
        }
        
        $cleaned_challenge = [
            'name' => sanitize_text_field($challenge['name']),
            'color' => !empty($challenge['color']) ? sanitize_hex_color($challenge['color']) : '#333333',
            'use_global_dates' => !empty($challenge['use_global_dates']),
            'start_date' => !empty($challenge['start_date']) ? sanitize_text_field($challenge['start_date']) : '',
            'start_time' => !empty($challenge['start_time']) ? sanitize_text_field($challenge['start_time']) : '',
            'end_date' => !empty($challenge['end_date']) ? sanitize_text_field($challenge['end_date']) : '',
            'end_time' => !empty($challenge['end_time']) ? sanitize_text_field($challenge['end_time']) : '',
            'pokemon_catch' => [],
            'pokemon_shadow' => [],
            'pokemon_evolution' => [],
            'pokemon_hatch' => [],
            'pokemon_costume' => [],
            'pokemon_trade' => [],
            'rewards' => [],
        ];
        
        // Nettoyer les Pokémon par catégorie
        $categories = ['pokemon_catch', 'pokemon_shadow', 'pokemon_evolution', 'pokemon_hatch', 'pokemon_costume', 'pokemon_trade'];
        foreach ($categories as $category) {
            if (isset($challenge[$category]) && is_array($challenge[$category])) {
                $cleaned_challenge[$category] = function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')
                    ? pokehub_parse_post_pokemon_multiselect_tokens_with_genders($challenge[$category], null)['pokemon_ids']
                    : array_map('intval', array_filter($challenge[$category], function ($id) {
                        return !empty($id) && is_numeric($id);
                    }));
            }
        }
        
        // Nettoyer les récompenses
        if (isset($challenge['rewards']) && is_array($challenge['rewards'])) {
            foreach ($challenge['rewards'] as $reward) {
                $cleaned_reward = [
                    'type' => sanitize_key($reward['type'] ?? 'pokemon'),
                ];
                
                if ($cleaned_reward['type'] === 'pokemon') {
                    $raw_ids = isset($reward['pokemon_ids']) && is_array($reward['pokemon_ids']) ? $reward['pokemon_ids'] : [];
                    $raw_genders = isset($reward['pokemon_genders']) && is_array($reward['pokemon_genders']) ? $reward['pokemon_genders'] : [];
                    if (function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')) {
                        $parsed = pokehub_parse_post_pokemon_multiselect_tokens_with_genders($raw_ids, $raw_genders);
                        $cleaned_reward['pokemon_ids'] = $parsed['pokemon_ids'];
                        $cleaned_reward['pokemon_genders'] = $parsed['pokemon_genders'];
                    } else {
                        $cleaned_reward['pokemon_ids'] = array_map('intval', array_filter($raw_ids, function ($id) {
                            return !empty($id) && is_numeric($id);
                        }));
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
                    $cleaned_reward['quantity'] = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
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
                
                $cleaned_challenge['rewards'][] = $cleaned_reward;
            }
        }
        
        $cleaned_challenges[] = $cleaned_challenge;
    }

    if (function_exists('pokehub_content_save_collection_challenges')) {
        pokehub_content_save_collection_challenges('post', $post_id, $cleaned_challenges);
    }
}

