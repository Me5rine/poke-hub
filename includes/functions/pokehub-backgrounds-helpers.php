<?php
// includes/functions/pokehub-backgrounds-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/** Types de fonds Pokémon GO : lieu vs spécial */
const POKE_HUB_BACKGROUND_TYPE_LOCATION = 'location';
const POKE_HUB_BACKGROUND_TYPE_SPECIAL = 'special';

/**
 * Retourne les types de fonds disponibles (fonds de lieux / fonds spéciaux).
 *
 * @return array [ 'slug' => 'Label traduit', ... ]
 */
function poke_hub_get_background_types(): array {
    return [
        POKE_HUB_BACKGROUND_TYPE_LOCATION => __('Location background', 'poke-hub'),
        POKE_HUB_BACKGROUND_TYPE_SPECIAL  => __('Special background', 'poke-hub'),
    ];
}

/**
 * Retourne le libellé d'un type de fond.
 *
 * @param string $type 'location' ou 'special'
 * @return string
 */
function poke_hub_get_background_type_label(string $type): string {
    $types = poke_hub_get_background_types();
    return $types[$type] ?? $type;
}

/**
 * Retourne les IDs des Pokémon liés à un fond donné.
 *
 * @param int $background_id ID du fond (pokemon_backgrounds.id)
 * @return int[] Liste d’IDs de Pokémon
 */
function poke_hub_get_pokemon_ids_for_background(int $background_id): array {
    global $wpdb;
    $links_table = pokehub_get_table('pokemon_background_pokemon_links');
    if (!$links_table || $background_id <= 0) {
        return [];
    }
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT pokemon_id FROM {$links_table} WHERE background_id = %d ORDER BY pokemon_id ASC",
        $background_id
    ));
    return array_map('intval', is_array($ids) ? $ids : []);
}

/**
 * Retourne les événements associés à un fond (un fond peut avoir plusieurs événements).
 *
 * @param int $background_id ID du fond (pokemon_backgrounds.id)
 * @return array Liste de [ 'event_type' => string, 'event_id' => int ]
 */
function poke_hub_get_background_events(int $background_id): array {
    global $wpdb;
    $table = pokehub_get_table('pokemon_background_events');
    if (!$table || $background_id <= 0) {
        return [];
    }
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT event_type, event_id FROM {$table} WHERE background_id = %d ORDER BY id ASC",
            $background_id
        ),
        ARRAY_A
    );
    if (!is_array($rows)) {
        return [];
    }
    return array_map(function ($row) {
        return [
            'event_type' => (string) ($row['event_type'] ?? ''),
            'event_id'   => (int) ($row['event_id'] ?? 0),
        ];
    }, $rows);
}

/**
 * Retourne les IDs des Pokémon shiny lock pour un fond (fond sorti avant le shiny).
 *
 * @param int $background_id ID du fond
 * @return int[] Liste d'IDs de Pokémon
 */
function poke_hub_get_background_shiny_locked_pokemon_ids(int $background_id): array {
    global $wpdb;
    $links_table = pokehub_get_table('pokemon_background_pokemon_links');
    if (!$links_table || $background_id <= 0) {
        return [];
    }
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT pokemon_id FROM {$links_table} WHERE background_id = %d AND is_shiny_locked = 1 ORDER BY pokemon_id ASC",
        $background_id
    ));
    return array_map('intval', is_array($ids) ? $ids : []);
}

/**
 * Indique si un Pokémon est shiny lock pour un fond donné (fond sorti avant le shiny).
 *
 * @param int $background_id ID du fond
 * @param int $pokemon_id ID du Pokémon
 * @return bool
 */
function poke_hub_is_background_pokemon_shiny_locked(int $background_id, int $pokemon_id): bool {
    global $wpdb;
    $links_table = pokehub_get_table('pokemon_background_pokemon_links');
    if (!$links_table || $background_id <= 0 || $pokemon_id <= 0) {
        return false;
    }
    $val = $wpdb->get_var($wpdb->prepare(
        "SELECT is_shiny_locked FROM {$links_table} WHERE background_id = %d AND pokemon_id = %d",
        $background_id,
        $pokemon_id
    ));
    return (int) $val === 1;
}

/**
 * Retourne les IDs des Pokémon liés à au moins un des fonds donnés (union).
 *
 * @param int[] $background_ids IDs des fonds
 * @return int[] Liste d’IDs de Pokémon, sans doublon
 */
function poke_hub_get_pokemon_ids_for_backgrounds(array $background_ids): array {
    global $wpdb;
    $links_table = pokehub_get_table('pokemon_background_pokemon_links');
    if (!$links_table) {
        return [];
    }
    $background_ids = array_filter(array_map('intval', $background_ids));
    if (empty($background_ids)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($background_ids), '%d'));
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT pokemon_id FROM {$links_table} WHERE background_id IN ({$placeholders}) ORDER BY pokemon_id ASC",
        ...$background_ids
    ));
    return array_map('intval', is_array($ids) ? $ids : []);
}

/**
 * Retourne les IDs de tous les Pokémon qui ont au moins un fond lié.
 *
 * @return int[] Liste d’IDs de Pokémon
 */
function poke_hub_get_pokemon_ids_with_any_background(): array {
    global $wpdb;
    $links_table = pokehub_get_table('pokemon_background_pokemon_links');
    if (!$links_table) {
        return [];
    }
    $ids = $wpdb->get_col("SELECT DISTINCT pokemon_id FROM {$links_table} ORDER BY pokemon_id ASC");
    return array_map('intval', is_array($ids) ? $ids : []);
}
