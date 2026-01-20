<?php
// modules/events/functions/events-quests-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère les quêtes d'un événement depuis les post meta
 */
function pokehub_get_event_quests(int $post_id): array {
    $quests = get_post_meta($post_id, '_pokehub_event_quests', true);
    return is_array($quests) ? $quests : [];
}

/**
 * Sauvegarde les quêtes d'un événement
 */
function pokehub_save_event_quests(int $post_id, array $quests): void {
    // Nettoyer les quêtes
    $cleaned_quests = [];
    foreach ($quests as $quest) {
        // Permettre les quêtes sans intitulé si elles ont des récompenses
        $has_rewards = !empty($quest['rewards']) && is_array($quest['rewards']) && count($quest['rewards']) > 0;
        $has_task = !empty($quest['task']);
        
        // Ignorer si ni intitulé ni récompenses
        if (!$has_task && !$has_rewards) {
            continue;
        }
        
        $cleaned_quest = [
            'task' => $has_task ? sanitize_text_field($quest['task']) : '',
            'rewards' => [],
        ];
        
        if (isset($quest['rewards']) && is_array($quest['rewards'])) {
            foreach ($quest['rewards'] as $reward) {
                $cleaned_reward = [
                    'type' => sanitize_key($reward['type'] ?? 'pokemon'),
                ];
                
                if ($cleaned_reward['type'] === 'pokemon') {
                    // Gérer le multiselect (array de pokemon_ids)
                    if (isset($reward['pokemon_ids']) && is_array($reward['pokemon_ids'])) {
                        $cleaned_reward['pokemon_ids'] = array_map('intval', array_filter($reward['pokemon_ids'], function($id) {
                            return !empty($id) && is_numeric($id);
                        }));
                    } elseif (isset($reward['pokemon_id'])) {
                        // Rétrocompatibilité : si pokemon_id existe, le convertir en array
                        $pokemon_id = (int) $reward['pokemon_id'];
                        $cleaned_reward['pokemon_ids'] = $pokemon_id > 0 ? [$pokemon_id] : [];
                    } else {
                        $cleaned_reward['pokemon_ids'] = [];
                    }
                    $cleaned_reward['force_shiny'] = !empty($reward['force_shiny']);
                } elseif ($cleaned_reward['type'] === 'candy' || $cleaned_reward['type'] === 'mega_energy') {
                    $cleaned_reward['pokemon_id'] = isset($reward['pokemon_id']) ? (int) $reward['pokemon_id'] : 0;
                    $cleaned_reward['quantity'] = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
                } elseif ($cleaned_reward['type'] === 'item') {
                    $cleaned_reward['item_id'] = isset($reward['item_id']) ? (int) $reward['item_id'] : 0;
                    // Récupérer le nom depuis la base de données si on a l'ID
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
                
                $cleaned_quest['rewards'][] = $cleaned_reward;
            }
        }
        
        $cleaned_quests[] = $cleaned_quest;
    }
    
    update_post_meta($post_id, '_pokehub_event_quests', $cleaned_quests);
}

/**
 * Récupère les quêtes d'un post (alias pour compatibilité)
 */
function pokehub_events_get_post_quests(int $post_id): array {
    return pokehub_get_event_quests($post_id);
}

/**
 * Récupère tous les Pokémon pour les sélecteurs (Select2)
 * 
 * @deprecated Utiliser pokehub_get_pokemon_for_select() à la place
 * @return array Format: [['id' => 1, 'text' => 'Pikachu (#025)'], ...]
 */
function pokehub_quests_get_pokemon_for_select(): array {
    // Déléguer à la fonction globale
    if (function_exists('pokehub_get_pokemon_for_select')) {
        return pokehub_get_pokemon_for_select();
    }
    return [];
}

/**
 * Récupère tous les items pour les sélecteurs (Select2)
 * 
 * @deprecated Utiliser pokehub_get_items_for_select() à la place
 * @return array Format: [['id' => 1, 'text' => 'Pierre Évolutive'], ...]
 */
function pokehub_quests_get_items_for_select(): array {
    // Déléguer à la fonction globale
    if (function_exists('pokehub_get_items_for_select')) {
        return pokehub_get_items_for_select();
    }
    return [];
}

/**
 * Récupère les données d'un Pokémon par son ID
 * 
 * @deprecated Utiliser pokehub_get_pokemon_data_by_id() à la place
 */
function pokehub_quests_get_pokemon_data(int $pokemon_id): ?array {
    // Déléguer à la fonction globale
    if (function_exists('pokehub_get_pokemon_data_by_id')) {
        return pokehub_get_pokemon_data_by_id($pokemon_id);
    }
    return null;
}

/**
 * Récupère les Pokémon méga pour les sélecteurs Select2 (méga énergie)
 * 
 * @deprecated Utiliser pokehub_get_mega_pokemon_for_select() à la place
 * @return array Format: [['id' => 1, 'text' => 'Charizard (Mega X)', ...], ...]
 */
function pokehub_quests_get_mega_pokemon_for_select(): array {
    // Déléguer à la fonction globale
    if (function_exists('pokehub_get_mega_pokemon_for_select')) {
        return pokehub_get_mega_pokemon_for_select();
    }
    return [];
}

/**
 * Récupère les Pokémon de base (pour les bonbons) pour les sélecteurs Select2
 * 
 * @deprecated Utiliser pokehub_get_base_pokemon_for_select() à la place
 * @return array Format: [['id' => 1, 'text' => 'Pikachu (#025)', ...], ...]
 */
function pokehub_quests_get_base_pokemon_for_select(): array {
    // Déléguer à la fonction globale
    if (function_exists('pokehub_get_base_pokemon_for_select')) {
        return pokehub_get_base_pokemon_for_select();
    }
    return [];
}

/**
 * Récupère les données d'un item par son ID
 * 
 * @deprecated Utiliser pokehub_get_item_data_by_id() à la place
 */
function pokehub_quests_get_item_data(int $item_id): ?array {
    // Déléguer à la fonction globale
    if (function_exists('pokehub_get_item_data_by_id')) {
        return pokehub_get_item_data_by_id($item_id);
    }
    return null;
}

/**
 * Récupère l'URL de l'image d'un Pokémon par son ID
 * 
 * @param int $pokemon_id ID du Pokémon
 * @param bool $is_shiny Si true, récupère la version shiny (mais on utilise toujours false pour l'image)
 * @return string URL de l'image ou chaîne vide
 */
function pokehub_get_quest_pokemon_image($pokemon_id, $is_shiny = false) {
    // Les helpers d'images sont maintenant dans pokemon-public-helpers.php
    // et sont disponibles même si le module Pokémon n'est pas actif
    if (!function_exists('poke_hub_pokemon_get_image_url')) {
        error_log('[POKE-HUB] pokehub_get_quest_pokemon_image - poke_hub_pokemon_get_image_url n\'existe pas');
        return '';
    }
    
    // Récupérer les données complètes du Pokémon depuis la base (avec slug)
    if (!function_exists('pokehub_get_pokemon_data_by_id')) {
        error_log('[POKE-HUB] pokehub_get_quest_pokemon_image - pokehub_get_pokemon_data_by_id n\'existe pas');
        return '';
    }
    
    $pokemon_data = pokehub_get_pokemon_data_by_id($pokemon_id);
    if (!$pokemon_data) {
        error_log('[POKE-HUB] pokehub_get_quest_pokemon_image - pas de données pour pokemon_id: ' . $pokemon_id);
        return '';
    }
    
    // Debug: vérifier les données récupérées
    error_log('[POKE-HUB] pokehub_get_quest_pokemon_image - pokemon_id: ' . $pokemon_id . ', slug: ' . ($pokemon_data['slug'] ?? 'VIDE') . ', dex_number: ' . ($pokemon_data['dex_number'] ?? 'VIDE'));
    
    // Convertir en objet pour compatibilité avec poke_hub_pokemon_get_image_url
    $pokemon = (object) $pokemon_data;
    
    // Vérifier l'URL de base des assets
    $base_url = '';
    if (function_exists('poke_hub_pokemon_get_assets_base_url')) {
        $base_url = poke_hub_pokemon_get_assets_base_url();
    }
    error_log('[POKE-HUB] pokehub_get_quest_pokemon_image - base_url: ' . ($base_url ?: 'VIDE'));
    
    // Toujours récupérer l'image normale (pas shiny) - le paramètre is_shiny n'est pas utilisé ici
    $image_url = poke_hub_pokemon_get_image_url($pokemon, ['shiny' => false]);
    
    // Debug: vérifier l'URL générée
    error_log('[POKE-HUB] pokehub_get_quest_pokemon_image - image_url finale: ' . ($image_url ?: 'VIDE'));
    
    return $image_url;
}
