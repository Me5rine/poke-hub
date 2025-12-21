<?php
// File: modules/events/admin/events-columns.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enregistre la colonne "Event Dates" sur les post types souhaités.
 *
 * Par défaut : post, pokehub_event
 * Tu peux filtrer via : pokehub_events_date_column_post_types
 */
function pokehub_events_register_date_column() {

    $post_types = apply_filters('pokehub_events_date_column_post_types', [
        'post',
        'pokehub_event',
    ]);

    if (!is_array($post_types) || empty($post_types)) {
        return;
    }

    foreach ($post_types as $post_type) {
        $post_type = sanitize_key($post_type);

        // Ajout de la colonne
        add_filter("manage_{$post_type}_posts_columns", 'pokehub_events_add_date_column');

        // Rendu de la colonne
        add_action("manage_{$post_type}_posts_custom_column", 'pokehub_events_render_date_column', 10, 2);
    }
}
add_action('init', 'pokehub_events_register_date_column');

/**
 * Ajoute la colonne "Event Dates" aux colonnes de la liste.
 *
 * @param array $columns
 * @return array
 */
function pokehub_events_add_date_column(array $columns): array {
    $columns['event_dates'] = __('Event Dates', 'poke-hub');
    return $columns;
}

/**
 * Affiche les dates de l’événement dans la colonne custom.
 *
 * @param string $column
 * @param int    $post_id
 */
function pokehub_events_render_date_column(string $column, int $post_id): void {

    if ($column !== 'event_dates') {
        return;
    }

    // Metas héritées d’Admin Lab (source d’événements)
    $start = get_post_meta($post_id, '_admin_lab_event_start', true);
    $end   = get_post_meta($post_id, '_admin_lab_event_end', true);

    if ($start && $end) {
        $start_ts = (int) $start;
        $end_ts   = (int) $end;

        // Formatage avec la locale du site
        $date_format = get_option('date_format');

        $start_date = wp_date($date_format, $start_ts, wp_timezone());
        $end_date   = wp_date($date_format, $end_ts, wp_timezone());

        echo esc_html($start_date . ' → ' . $end_date);
    }
}
