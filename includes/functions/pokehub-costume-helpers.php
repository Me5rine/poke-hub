<?php
// includes/functions/pokehub-costume-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retourne les IDs des Pokémon considérés comme costumés :
 * - forme avec category = 'costume' (pokemon_form_variants),
 * - ou extra JSON avec is_event_costumed = true.
 *
 * @return int[] Liste d’IDs de Pokémon
 */
function poke_hub_get_pokemon_ids_with_costume(): array {
    global $wpdb;
    $pokemon_table       = pokehub_get_table('pokemon');
    $form_variants_table = pokehub_get_table('pokemon_form_variants');
    if (!$pokemon_table) {
        return [];
    }
    $conditions = [];
    $params     = [];

    if ($form_variants_table) {
        $conditions[] = "EXISTS (SELECT 1 FROM {$form_variants_table} fv WHERE fv.id = p.form_variant_id AND LOWER(TRIM(COALESCE(fv.category, ''))) = 'costume')";
    }
    $conditions[] = "(p.extra IS NOT NULL AND (p.extra LIKE %s OR p.extra LIKE %s))";
    $params[]    = '%"is_event_costumed":true%';
    $params[]    = '%"is_event_costumed": true%';

    $where = implode(' OR ', $conditions);
    $sql   = "SELECT DISTINCT p.id FROM {$pokemon_table} p WHERE {$where} ORDER BY p.id ASC";
    $ids   = $wpdb->get_col($params ? $wpdb->prepare($sql, $params) : $sql);

    return array_map('intval', is_array($ids) ? $ids : []);
}
