<?php
// File: modules/pokemon/functions/pokemon-items-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retourne un item à partir de son proto_id + game_key.
 *
 * @param string $proto_id Ex: ITEM_KINGS_ROCK
 * @param string $game_key Ex: pokemon_go
 * @return array|null
 */
function poke_hub_items_get_by_proto(string $proto_id, string $game_key = 'pokemon_go'): ?array {
    $proto_id = trim($proto_id);
    if ($proto_id === '') {
        return null;
    }

    if (!function_exists('pokehub_get_table')) {
        return null;
    }

    global $wpdb;
    $table = pokehub_get_table('items');
    if (!$table) {
        return null;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE proto_id = %s AND game_key = %s LIMIT 1",
            $proto_id,
            $game_key
        ),
        ARRAY_A
    );

    return $row ?: null;
}

/**
 * Retourne un item existant ou le crée si besoin à partir d’un proto.
 *
 * @param string $proto_id
 * @param array  $labels ['en' => ..., 'fr' => ...]
 * @param string $category
 * @param string $subtype
 * @param string $game_key
 * @return array|null
 */
function poke_hub_items_get_or_create_from_proto(
    string $proto_id,
    array $labels = [],
    string $category = 'evolution_item',
    string $subtype = '',
    string $game_key = 'pokemon_go'
): ?array {
    $proto_id = trim($proto_id);
    if ($proto_id === '') {
        return null;
    }

    $existing = poke_hub_items_get_by_proto($proto_id, $game_key);
    if ($existing) {
        return $existing;
    }

    if (!function_exists('pokehub_get_table')) {
        return null;
    }

    global $wpdb;
    $table = pokehub_get_table('items');
    if (!$table) {
        return null;
    }

    // Slug basique depuis le proto : ITEM_KINGS_ROCK -> kings-rock
    $base = strtolower(preg_replace('/^ITEM_/', '', $proto_id));
    $slug = sanitize_title(str_replace('_', ' ', $base));

    $name_en = $labels['en'] ?? str_replace('_', ' ', ucfirst($base));
    $name_fr = $labels['fr'] ?? $name_en;

    $data = [
        'slug'        => $slug,
        'proto_id'    => $proto_id,
        'category'    => $category,
        'subtype'     => $subtype,
        'name_en'     => $name_en,
        'name_fr'     => $name_fr,
        'description_en' => '',
        'description_fr' => '',
        'image_id'    => null,
        'game_key'    => $game_key,
        'extra'       => null,
    ];

    $format = [
        '%s', // slug
        '%s', // proto_id
        '%s', // category
        '%s', // subtype
        '%s', // name_en
        '%s', // name_fr
        '%s', // description_en
        '%s', // description_fr
        '%d', // image_id
        '%s', // game_key
        '%s', // extra
    ];

    $wpdb->insert($table, $data, $format);

    $id = (int) $wpdb->insert_id;
    if ($id <= 0) {
        return null;
    }

    $data['id'] = $id;

    return $data;
}
