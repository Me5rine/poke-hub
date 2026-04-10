<?php
// modules/pokemon/includes/pokemon-biomes-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * IDs des biomes liés à un Pokémon (table pokemon_biome_pokemon_links).
 *
 * @param int $pokemon_id
 * @return int[]
 */
function poke_hub_pokemon_get_pokemon_biome_ids(int $pokemon_id): array {
    if ($pokemon_id <= 0 || !function_exists('pokehub_get_table')) {
        return [];
    }

    global $wpdb;
    $table = pokehub_get_table('pokemon_biome_pokemon_links');
    if (!$table) {
        return [];
    }

    $rows = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT biome_id FROM {$table} WHERE pokemon_id = %d ORDER BY biome_id ASC",
            $pokemon_id
        )
    );

    return array_map('intval', (array) $rows);
}

/**
 * Synchronise les liens Pokémon ↔ biomes depuis la fiche Pokémon.
 *
 * @param int   $pokemon_id
 * @param int[] $biome_ids
 */
function poke_hub_pokemon_sync_pokemon_biome_links(int $pokemon_id, array $biome_ids): void {
    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return;
    }

    $table = pokehub_get_table('pokemon_biome_pokemon_links');
    if (!$table) {
        return;
    }

    $biome_ids = array_map('intval', $biome_ids);
    $biome_ids = array_filter($biome_ids, static function ($v) {
        return $v > 0;
    });
    $biome_ids = array_values(array_unique($biome_ids));

    $wpdb->delete($table, ['pokemon_id' => $pokemon_id], ['%d']);

    foreach ($biome_ids as $bid) {
        $wpdb->insert(
            $table,
            [
                'biome_id'   => $bid,
                'pokemon_id' => $pokemon_id,
            ],
            ['%d', '%d']
        );
    }
}

/**
 * Synchronise les images de fond d'un biome (ordre = ordre du tableau).
 *
 * @param int      $biome_id
 * @param string[] $image_urls
 */
function poke_hub_pokemon_sync_biome_images(int $biome_id, array $image_urls): void {
    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    $biome_id = (int) $biome_id;
    if ($biome_id <= 0) {
        return;
    }

    $table = pokehub_get_table('pokemon_biome_images');
    if (!$table) {
        return;
    }

    $wpdb->delete($table, ['biome_id' => $biome_id], ['%d']);

    $order = 0;
    foreach ($image_urls as $raw) {
        $url = esc_url_raw(trim((string) $raw));
        if ($url === '') {
            continue;
        }
        $order++;
        $wpdb->insert(
            $table,
            [
                'biome_id'    => $biome_id,
                'image_url'   => $url,
                'sort_order'  => $order,
            ],
            ['%d', '%s', '%d']
        );
    }
}

/**
 * Synchronise les Pokémon d'un biome (depuis le formulaire biome).
 *
 * @param int   $biome_id
 * @param int[] $pokemon_ids
 */
function poke_hub_pokemon_sync_biome_pokemon_links(int $biome_id, array $pokemon_ids): void {
    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    $biome_id = (int) $biome_id;
    if ($biome_id <= 0) {
        return;
    }

    $table = pokehub_get_table('pokemon_biome_pokemon_links');
    if (!$table) {
        return;
    }

    $pokemon_ids = array_map('intval', $pokemon_ids);
    $pokemon_ids = array_filter($pokemon_ids, static function ($v) {
        return $v > 0;
    });
    $pokemon_ids = array_values(array_unique($pokemon_ids));

    $wpdb->delete($table, ['biome_id' => $biome_id], ['%d']);

    foreach ($pokemon_ids as $pid) {
        $wpdb->insert(
            $table,
            [
                'biome_id'   => $biome_id,
                'pokemon_id' => $pid,
            ],
            ['%d', '%d']
        );
    }
}

/**
 * URLs des images d'un biome, triées.
 *
 * @param int $biome_id
 * @return string[]
 */
function poke_hub_pokemon_get_biome_image_urls(int $biome_id): array {
    if ($biome_id <= 0 || !function_exists('pokehub_get_table')) {
        return [];
    }

    global $wpdb;
    $table = pokehub_get_table('pokemon_biome_images');
    if (!$table) {
        return [];
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT image_url FROM {$table} WHERE biome_id = %d ORDER BY sort_order ASC, id ASC",
            $biome_id
        )
    );

    $out = [];
    foreach ($rows as $row) {
        if (!empty($row->image_url)) {
            $out[] = (string) $row->image_url;
        }
    }

    return $out;
}
