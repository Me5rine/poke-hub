<?php
// includes/functions/pokehub-form-variant-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retourne les événements associés à une forme / costume (form variant).
 * Une forme peut être associée à plusieurs événements (local_event, remote_event, special_event).
 *
 * @param int $form_variant_id ID de la forme (pokemon_form_variants.id)
 * @return array Liste de [ 'event_type' => string, 'event_id' => int ]
 */
function poke_hub_get_form_variant_events(int $form_variant_id): array {
    global $wpdb;
    $table = pokehub_get_table('pokemon_form_variant_events');
    if (!$table || $form_variant_id <= 0) {
        return [];
    }
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT event_type, event_id FROM {$table} WHERE form_variant_id = %d ORDER BY id ASC",
            $form_variant_id
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
