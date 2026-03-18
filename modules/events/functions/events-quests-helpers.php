<?php
// modules/events/functions/events-quests-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère les quêtes d'un événement (depuis les tables de contenu).
 */
function pokehub_get_event_quests(int $post_id): array {
    if (function_exists('pokehub_content_get_quests')) {
        return pokehub_content_get_quests('post', $post_id);
    }
    return [];
}

/**
 * Sauvegarde les quêtes d'un événement
 */
function pokehub_save_event_quests(int $post_id, array $quests): void {
    $cleaned_quests = function_exists('pokehub_quests_clean_from_request') ? pokehub_quests_clean_from_request($quests) : [];
    if (function_exists('pokehub_content_save_quests')) {
        pokehub_content_save_quests('post', $post_id, $cleaned_quests);
    }
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
