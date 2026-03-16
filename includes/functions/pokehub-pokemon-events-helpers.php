<?php
// includes/functions/pokehub-pokemon-events-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retourne les événements associés à un Pokémon (marqué événement/costumé).
 * Un Pokémon peut être associé à plusieurs événements (local_post, remote_post, special_local, special_remote).
 *
 * @param int $pokemon_id ID du Pokémon (pokemon.id)
 * @return array Liste de [ 'event_type' => string, 'event_id' => int ]
 */
function poke_hub_get_pokemon_events(int $pokemon_id): array {
    global $wpdb;
    $table = pokehub_get_table('pokemon_pokemon_events');
    if (!$table || $pokemon_id <= 0) {
        return [];
    }
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT event_type, event_id FROM {$table} WHERE pokemon_id = %d ORDER BY id ASC",
            $pokemon_id
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
