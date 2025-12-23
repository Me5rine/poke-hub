<?php
// File: modules/pokemon/includes/pokemon-auto-translations.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère automatiquement les traductions depuis Bulbapedia lors de l'ajout/modification d'un Pokémon.
 * 
 * @param int $pokemon_id ID du Pokémon
 * @param string $name_en Nom anglais
 * @param int $dex_number Numéro de Pokédex (optionnel)
 * @return bool True si des traductions ont été récupérées
 */
function poke_hub_pokemon_auto_fetch_translations($pokemon_id, $name_en, $dex_number = 0) {
    if (empty($name_en) || $pokemon_id <= 0) {
        return false;
    }

    if (!function_exists('poke_hub_pokemon_fetch_official_names_from_bulbapedia')) {
        return false;
    }

    global $wpdb;
    $table = pokehub_get_table('pokemon');
    if (!$table) {
        return false;
    }

    // Récupérer les données existantes
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT id, name_en, name_fr, extra FROM {$table} WHERE id = %d", $pokemon_id)
    );

    if (!$row) {
        return false;
    }

    // Si on n'a pas le dex_number, essayer de le récupérer
    if ($dex_number <= 0) {
        $dex_row = $wpdb->get_row(
            $wpdb->prepare("SELECT dex_number FROM {$table} WHERE id = %d", $pokemon_id)
        );
        if ($dex_row) {
            $dex_number = (int) $dex_row->dex_number;
        }
    }

    // Récupérer les noms officiels depuis Bulbapedia
    $official_names = poke_hub_pokemon_fetch_official_names_from_bulbapedia($dex_number, $name_en);
    
    if ($official_names === false || empty($official_names)) {
        return false;
    }

    // Récupérer les données extra existantes
    $extra = [];
    if (!empty($row->extra)) {
        $decoded = json_decode($row->extra, true);
        if (is_array($decoded)) {
            $extra = $decoded;
        }
    }

    if (!isset($extra['names']) || !is_array($extra['names'])) {
        $extra['names'] = [];
    }

    $has_updates = false;
    $update_data = [];

    // Mettre à jour les traductions
    foreach ($official_names as $lang => $name) {
        if ($lang === 'en') {
            continue; // Ne pas écraser le nom anglais
        }

        // Mettre à jour extra['names'][$lang]
        if (!isset($extra['names'][$lang]) || empty($extra['names'][$lang])) {
            $extra['names'][$lang] = $name;
            $has_updates = true;
        }

        // Mettre à jour name_fr si c'est le français
        if ($lang === 'fr' && (empty($row->name_fr) || $row->name_fr === $row->name_en)) {
            $update_data['name_fr'] = $name;
            $has_updates = true;
        }
    }

    if (!$has_updates) {
        return false;
    }

    // Mettre à jour la base de données
    $update_data['extra'] = wp_json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    $format = ['%s']; // extra
    if (isset($update_data['name_fr'])) {
        $format[] = '%s'; // name_fr
    }

    $result = $wpdb->update(
        $table,
        $update_data,
        ['id' => $pokemon_id],
        $format,
        ['%d']
    );

    return $result !== false;
}

/**
 * Récupère automatiquement les traductions depuis Bulbapedia lors de l'ajout/modification d'une attaque.
 * 
 * @param int $attack_id ID de l'attaque
 * @param string $name_en Nom anglais
 * @return bool True si des traductions ont été récupérées
 */
function poke_hub_attack_auto_fetch_translations($attack_id, $name_en) {
    if (empty($name_en) || $attack_id <= 0) {
        return false;
    }

    if (!function_exists('poke_hub_pokemon_fetch_move_official_names_from_bulbapedia')) {
        return false;
    }

    global $wpdb;
    $table = pokehub_get_table('attacks');
    if (!$table) {
        return false;
    }

    // Récupérer les données existantes
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT id, name_en, name_fr, extra FROM {$table} WHERE id = %d", $attack_id)
    );

    if (!$row) {
        return false;
    }

    // Récupérer les noms officiels depuis Bulbapedia
    $official_names = poke_hub_pokemon_fetch_move_official_names_from_bulbapedia($name_en);
    
    if ($official_names === false || empty($official_names)) {
        return false;
    }

    // Récupérer les données extra existantes
    $extra = [];
    if (!empty($row->extra)) {
        $decoded = json_decode($row->extra, true);
        if (is_array($decoded)) {
            $extra = $decoded;
        }
    }

    if (!isset($extra['names']) || !is_array($extra['names'])) {
        $extra['names'] = [];
    }

    $has_updates = false;
    $update_data = [];

    // Mettre à jour les traductions
    foreach ($official_names as $lang => $name) {
        if ($lang === 'en') {
            continue; // Ne pas écraser le nom anglais
        }

        // Mettre à jour extra['names'][$lang]
        if (!isset($extra['names'][$lang]) || empty($extra['names'][$lang])) {
            $extra['names'][$lang] = $name;
            $has_updates = true;
        }

        // Mettre à jour name_fr si c'est le français
        if ($lang === 'fr' && (empty($row->name_fr) || $row->name_fr === $row->name_en)) {
            $update_data['name_fr'] = $name;
            $has_updates = true;
        }
    }

    if (!$has_updates) {
        return false;
    }

    // Mettre à jour la base de données
    $update_data['extra'] = wp_json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    $format = ['%s']; // extra
    if (isset($update_data['name_fr'])) {
        $format[] = '%s'; // name_fr
    }

    $result = $wpdb->update(
        $table,
        $update_data,
        ['id' => $attack_id],
        $format,
        ['%d']
    );

    return $result !== false;
}

/**
 * Récupère automatiquement les traductions depuis Bulbapedia lors de l'ajout/modification d'un type.
 * 
 * @param int $type_id ID du type
 * @param string $name_en Nom anglais
 * @return bool True si des traductions ont été récupérées
 */
function poke_hub_type_auto_fetch_translations($type_id, $name_en) {
    if (empty($name_en) || $type_id <= 0) {
        return false;
    }

    if (!function_exists('poke_hub_pokemon_fetch_type_official_names_from_bulbapedia')) {
        return false;
    }

    global $wpdb;
    $table = pokehub_get_table('pokemon_types');
    if (!$table) {
        return false;
    }

    // Récupérer les données existantes
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT id, name_en, name_fr, extra FROM {$table} WHERE id = %d", $type_id)
    );

    if (!$row) {
        return false;
    }

    // Récupérer les noms officiels depuis Bulbapedia
    $official_names = poke_hub_pokemon_fetch_type_official_names_from_bulbapedia($name_en);
    
    if ($official_names === false || empty($official_names)) {
        return false;
    }

    // Récupérer les données extra existantes
    $extra = [];
    if (!empty($row->extra)) {
        $decoded = json_decode($row->extra, true);
        if (is_array($decoded)) {
            $extra = $decoded;
        }
    }

    if (!isset($extra['names']) || !is_array($extra['names'])) {
        $extra['names'] = [];
    }

    $has_updates = false;
    $update_data = [];

    // Mettre à jour les traductions
    foreach ($official_names as $lang => $name) {
        if ($lang === 'en') {
            continue; // Ne pas écraser le nom anglais
        }

        // Mettre à jour extra['names'][$lang]
        if (!isset($extra['names'][$lang]) || empty($extra['names'][$lang])) {
            $extra['names'][$lang] = $name;
            $has_updates = true;
        }

        // Mettre à jour name_fr si c'est le français
        if ($lang === 'fr' && (empty($row->name_fr) || $row->name_fr === $row->name_en)) {
            $update_data['name_fr'] = $name;
            $has_updates = true;
        }
    }

    if (!$has_updates) {
        return false;
    }

    // Mettre à jour la base de données
    $update_data['extra'] = wp_json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    $format = ['%s']; // extra
    if (isset($update_data['name_fr'])) {
        $format[] = '%s'; // name_fr
    }

    $result = $wpdb->update(
        $table,
        $update_data,
        ['id' => $type_id],
        $format,
        ['%d']
    );

    return $result !== false;
}

/**
 * Récupère automatiquement les traductions depuis Bulbapedia lors de l'ajout/modification d'un objet.
 * Note: Bulbapedia peut ne pas avoir de page dédiée pour tous les objets, donc cette fonction peut échouer.
 * 
 * @param int $item_id ID de l'objet
 * @param string $name_en Nom anglais
 * @return bool True si des traductions ont été récupérées
 */
function poke_hub_item_auto_fetch_translations($item_id, $name_en) {
    // Pour les objets, on peut essayer de chercher sur Bulbapedia mais ce n'est pas garanti
    // Pour l'instant, on retourne false car Bulbapedia n'a pas toujours de pages pour les objets
    // TODO: Implémenter une recherche alternative ou laisser vide pour l'instant
    return false;
}








