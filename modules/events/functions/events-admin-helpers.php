<?php
// File: modules/events/functions/events-admin-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tous les types d'événements (event_type) disponibles (distants).
 */
function poke_hub_events_get_all_event_types(): array {
    global $wpdb;

    // Tables distantes via le nouveau helper
    $terms_table = pokehub_get_table('remote_terms');
    $tt_table    = pokehub_get_table('remote_term_taxonomy');

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT t.term_id, t.slug, t.name
            FROM {$terms_table} t
            INNER JOIN {$tt_table} tt
                ON tt.term_id = t.term_id
            WHERE tt.taxonomy = %s
            ORDER BY t.name ASC
            ",
            'event_type'
        )
    );

    return $rows ?: [];
}

/**
 * URL d'édition des événements distants (site JV).
 *
 * $item->id = ID du post distant.
 */
add_filter('pokehub_remote_events_edit_url', function ($url, $item) {
    // 🔧 ADAPTE ce domaine à ton site distant
    $remote_admin_base = 'https://jv-actu.com/wp-admin/';

    return $remote_admin_base . 'post.php?post=' . (int) $item->id . '&action=edit';
}, 10, 2);

/**
 * URL d'édition des special events distants (site JV).
 *
 * $item->id = ID du special event distant.
 */
add_filter('pokehub_remote_special_events_edit_url', function ($url, $item) {
    // 🔧 ADAPTE ce domaine à ton site distant
    $remote_admin_base = 'http://jv-actu.local:8080/wp-admin/';

    return $remote_admin_base . 'admin.php?page=poke-hub-events&action=edit_special&event_id=' . (int) $item->id;
}, 10, 2);

/**
 * Génère un slug unique parmi :
 * - les special events (table locale special_events)
 * - les posts distants (table remote_posts, via pokehub_get_table())
 *
 * @param string $base_slug  Slug initial (titre ou slug saisi)
 * @param int    $exclude_id ID d'un special event à exclure (en édition)
 * @return string Slug unique
 */
function pokehub_generate_unique_event_slug(string $base_slug, int $exclude_id = 0): string {
    global $wpdb;

    $slug = sanitize_title($base_slug);
    if ($slug === '') {
        $slug = 'event';
    }

    $unique_slug = $slug;
    $i           = 1;

    // Table des special events (locale)
    $events_table = pokehub_get_table('special_events');

    // Table des posts DISTANTS (events JV Actu)
    // via notre helper générique
    $remote_posts_table = pokehub_get_table('remote_posts');

    // Fallback de sécurité si jamais le helper renvoie une chaîne vide
    if ($remote_posts_table === '') {
        $remote_posts_table = $wpdb->posts;
    }

    while (true) {
        // 1) Slug déjà utilisé par un special event (sauf l'event en cours d'édition)
        $exists_special = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id 
                 FROM {$events_table}
                 WHERE slug = %s
                   AND id != %d
                 LIMIT 1",
                $unique_slug,
                $exclude_id
            )
        );

        // 2) Slug déjà utilisé comme post_name dans la table distante
        $exists_remote = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID 
                 FROM {$remote_posts_table}
                 WHERE post_name = %s
                 LIMIT 1",
                $unique_slug
            )
        );

        if (!$exists_special && !$exists_remote) {
            // 🎯 Slug vraiment libre → on le renvoie
            return $unique_slug;
        }

        // Sinon on incrémente : slug, slug-1, slug-2, etc.
        $unique_slug = $slug . '-' . $i;
        $i++;
    }
}
