<?php
// File: modules/events/functions/events-admin-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lit l’URL de base du WordPress distant depuis la table `{préfixe événements}_options`
 * (option WordPress standard `siteurl`), comme pour tout site WP partageant la même base.
 *
 * @return string URL sans slash final, ou chaîne vide si introuvable.
 */
function pokehub_events_get_remote_wp_base_url(): string {
    global $wpdb;

    static $resolved = false;
    static $base     = '';

    if ($resolved) {
        return $base;
    }
    $resolved = true;

    if (!function_exists('poke_hub_events_get_table_prefix')) {
        $base = (string) apply_filters('pokehub_remote_events_site_base_url', '');
        return $base;
    }

    $prefix = poke_hub_events_get_table_prefix('events');
    $prefix = trim((string) $prefix);
    if ($prefix === '' || !preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
        $base = (string) apply_filters('pokehub_remote_events_site_base_url', '');
        return $base;
    }

    $options_table = $prefix . 'options';
    if (function_exists('pokehub_table_exists') && !pokehub_table_exists($options_table)) {
        $base = (string) apply_filters('pokehub_remote_events_site_base_url', '');
        return $base;
    }

    $options_table_esc = esc_sql($options_table);
    $raw               = $wpdb->get_var(
        "SELECT option_value FROM `{$options_table_esc}` WHERE option_name = 'siteurl' LIMIT 1"
    );
    $raw = is_string($raw) ? trim($raw) : '';
    if ($raw === '') {
        $base = (string) apply_filters('pokehub_remote_events_site_base_url', '');
        return $base;
    }

    $parsed = wp_parse_url($raw);
    if (empty($parsed['scheme']) || empty($parsed['host'])) {
        $base = (string) apply_filters('pokehub_remote_events_site_base_url', '');
        return $base;
    }

    $base = untrailingslashit(esc_url_raw($raw));
    /**
     * URL de base du site WordPress distant (événements / actu), dérivée de `siteurl` ou surchargée.
     *
     * @param string $base URL sans slash final.
     */
    $base = (string) apply_filters('pokehub_remote_events_site_base_url', $base);

    return $base;
}

/**
 * URL « Nouvel article » sur le site distant (événements / actu).
 * Dérivée du préfixe tables (option `siteurl` du site cible) ou filtre `pokehub_remote_events_new_url`.
 */
function pokehub_events_get_remote_new_post_url(): string {
    $base = pokehub_events_get_remote_wp_base_url();
    if ($base !== '') {
        return trailingslashit($base) . 'wp-admin/post-new.php';
    }

    return (string) apply_filters(
        'pokehub_remote_events_new_url',
        'https://jv-actu.com/wp-admin/post-new.php'
    );
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
    $base = pokehub_events_get_remote_wp_base_url();
    if ($base === '') {
        return $url;
    }

    return trailingslashit($base) . 'wp-admin/post.php?post=' . (int) $item->id . '&action=edit';
}, 10, 2);

/**
 * Génère un slug unique parmi :
 * - les special events (table special_events, préfixe source Pokémon)
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

/**
 * URL de la page admin « liste des événements » (conserve les filtres présents dans l’URL).
 */
function pokehub_events_admin_list_url(): string {
    $url = add_query_arg(['page' => 'poke-hub-events'], admin_url('admin.php'));
    $preserve = ['event_status', 'event_source', 'event_type', 's', 'paged', 'orderby', 'order'];
    foreach ($preserve as $param) {
        if (!isset($_GET[$param]) || $_GET[$param] === '' || $_GET[$param] === '-1') {
            continue;
        }
        if ($param === 's') {
            $url = add_query_arg($param, sanitize_text_field(wp_unslash((string) $_GET[$param])), $url);
        } elseif (in_array($param, ['paged', 'orderby', 'order'], true)) {
            $url = add_query_arg($param, sanitize_key((string) $_GET[$param]), $url);
        } else {
            $url = add_query_arg($param, sanitize_text_field(wp_unslash((string) $_GET[$param])), $url);
        }
    }
    return $url;
}
