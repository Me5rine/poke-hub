<?php
// File: modules/events/includes/events-queries.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper: compute status (current|upcoming|past) from timestamps.
 * Si une fonction globale poke_hub_events_compute_status() existe déjà,
 * on la réutilise (cohérence avec le reste du plugin).
 */
function poke_hub_events_compute_status_from_ts(int $start_ts, int $end_ts): string {
    if (function_exists('poke_hub_events_compute_status')) {
        return poke_hub_events_compute_status($start_ts, $end_ts);
    }

    // Timestamp "absolu" (UTC) → compatible avec nos $start_ts / $end_ts
    // Utiliser time() car les timestamps Unix sont toujours en UTC
    $now = time();

    if ($start_ts > $now) {
        return 'upcoming';
    }

    if ($end_ts < $now) {
        return 'past';
    }

    return 'current';
}

/**
 * Normalise une ligne brute d'événement en objet standard pour le front.
 *
 * Champs attendus dans $raw (selon la source, tout n'est pas obligatoire) :
 * - id             (int)
 * - title          (string)
 * - slug           (string)
 * - content        (string)
 * - start_ts       (int)
 * - end_ts         (int)
 * - event_type_slug (string)
 * - event_type_name (string)
 * - event_type_color (string)
 * - image_id       (int)
 * - image_url      (string)
 * - url            (string)  → lien vers l'article / la page
 * - source         (string)  → 'remote_post', 'local_post', 'special_local', 'special_remote', ...
 *
 * @param array $raw
 * @return object
 */
function poke_hub_events_normalize_event(array $raw): object {

    $start_ts = isset($raw['start_ts']) ? (int) $raw['start_ts'] : 0;
    $end_ts   = isset($raw['end_ts'])   ? (int) $raw['end_ts']   : 0;

    if ($start_ts <= 0 && !empty($raw['sort_start_ts'])) {
        $start_ts = (int) $raw['sort_start_ts'];
    }
    if ($end_ts <= 0 && !empty($raw['sort_end_ts'])) {
        $end_ts = (int) $raw['sort_end_ts'];
    }

    // Status calculé (current|upcoming|past)
    $status = poke_hub_events_compute_status_from_ts($start_ts, $end_ts);

    $title = '';
    if (!empty($raw['title'])) {
        $title = (string) $raw['title'];
    } elseif (!empty($raw['event_title'])) {
        $title = (string) $raw['event_title'];
    }

    $title = trim(wp_strip_all_tags($title));

    $event_type_slug  = !empty($raw['event_type_slug'])  ? (string) $raw['event_type_slug']  : '';
    $event_type_name  = !empty($raw['event_type_name'])  ? (string) $raw['event_type_name']  : '';
    $event_type_color = !empty($raw['event_type_color']) ? (string) $raw['event_type_color'] : '';

    $image_id  = !empty($raw['image_id'])  ? (int) $raw['image_id']  : 0;
    $image_url = !empty($raw['image_url']) ? (string) $raw['image_url'] : '';

    $url    = !empty($raw['url'])    ? (string) $raw['url']    : '';
    $source = !empty($raw['source']) ? (string) $raw['source'] : 'unknown';

    return (object) [
        'id'               => isset($raw['id']) ? (int) $raw['id'] : 0,
        'title'            => $title,
        'slug'             => !empty($raw['slug']) ? (string) $raw['slug'] : '',
        'content'          => !empty($raw['content']) ? (string) $raw['content'] : '',
        'start_ts'         => $start_ts,
        'end_ts'           => $end_ts,
        'status'           => $status,
        'event_type_slug'  => $event_type_slug,
        'event_type_name'  => $event_type_name,
        'event_type_color' => $event_type_color,
        'image_id'         => $image_id,
        'image_url'        => $image_url,
        'remote_url'       => $url,     // utilisé par le render
        'source'           => $source,  // pratique pour debug / badges si tu veux
    ];
}

/**
 * Récupère les metas de récurrence pour un event dans la table distante.
 *
 * Nouvelle logique Me5rine LAB :
 * - _event_mode = 'local' ou 'fixed'
 * - si mode=local  → _event_window_end_local = "YYYY-MM-DD HH:MM:SS" (heure WP)
 * - si mode=fixed  → _event_window_end       = ISO UTC
 *
 * @param int $post_id
 * @return array {
 *   'recurring'     => '0|1',
 *   'window_end_ts' => int|null,
 *   'freq'          => string (daily|weekly|monthly|...),
 *   'interval'      => int,
 *   'mode'          => 'local'|'fixed'
 * }
 */
function poke_hub_events_get_recurring_meta(int $post_id): array {
    global $wpdb;

    // Postmeta DISTANT via helper unifié
    $postmeta_table = pokehub_get_table('remote_postmeta');

    $meta_keys = [
        '_event_recurring',
        '_event_window_end',
        '_event_window_end_local',
        '_event_rrule_freq',
        '_event_rrule_interval',
        '_event_mode',
    ];

    $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));

    $sql = $wpdb->prepare(
        "
        SELECT meta_key, meta_value
        FROM {$postmeta_table}
        WHERE post_id = %d
          AND meta_key IN ($placeholders)
        ",
        array_merge([$post_id], $meta_keys)
    );

    $rows = $wpdb->get_results($sql);
    $meta = [];

    if ($rows) {
        foreach ($rows as $row) {
            $meta[$row->meta_key] = $row->meta_value;
        }
    }

    $recurring = isset($meta['_event_recurring']) ? (string) $meta['_event_recurring'] : '0';

    // Mode : local | fixed
    $mode = isset($meta['_event_mode']) && $meta['_event_mode'] !== ''
        ? $meta['_event_mode']
        : 'fixed';

    $window_end_ts = null;

    if ($mode === 'local') {
        // Nouvelle méta : _event_window_end_local = "YYYY-MM-DD HH:MM:SS" en timezone WP (site JV)
        if (!empty($meta['_event_window_end_local'])) {
            try {
                $tz = wp_timezone();
                $dt = new DateTime($meta['_event_window_end_local'], $tz);
                $window_end_ts = $dt->getTimestamp();
            } catch (Exception $e) {
                $window_end_ts = null;
            }
        }
    } else {
        // fixed : ancienne méta ISO UTC
        if (!empty($meta['_event_window_end'])) {
            $ts = strtotime($meta['_event_window_end']);
            if ($ts > 0) {
                $window_end_ts = $ts;
            }
        }
    }

    $freq     = isset($meta['_event_rrule_freq']) ? (string) $meta['_event_rrule_freq'] : 'weekly';
    $interval = isset($meta['_event_rrule_interval']) ? (int) $meta['_event_rrule_interval'] : 1;
    if ($interval < 1) {
        $interval = 1;
    }

    return [
        'recurring'      => $recurring,
        'window_end_ts'  => $window_end_ts,
        'freq'           => $freq,
        'interval'       => $interval,
        'mode'           => $mode,
    ];
}

/**
 * Ajoute un intervalle de récurrence à deux DateTime (daily/weekly/monthly).
 *
 * @param DateTime $s
 * @param DateTime $e
 * @param string   $freq
 * @param int      $interval
 * @return void
 */
function poke_hub_events_add_interval(DateTime &$s, DateTime &$e, string $freq, int $interval): void {
    $interval = max(1, (int) $interval);
    $spec     = 'P1D';

    switch ($freq) {
        case 'daily':
            $spec = "P{$interval}D";
            break;
        case 'weekly':
            $days = 7 * $interval;
            $spec = "P{$days}D";
            break;
        case 'monthly':
            $spec = "P{$interval}M";
            break;
        default:
            // fallback weekly
            $days = 7 * $interval;
            $spec = "P{$days}D";
            break;
    }

    $di = new DateInterval($spec);
    $s->add($di);
    $e->add($di);
}

/**
 * Génère toutes les occurrences d'un event récurrent à partir :
 * - de la première occurrence (base_start_ts/base_end_ts)
 * - de la fenêtre de fin (window_end_ts)
 * - de la fréquence (freq) et de l'intervalle (interval)
 *
 * IMPORTANT :
 * On travaille ici en UTC uniquement. Le fuseau WordPress est géré
 * plus tard à l'affichage via date_i18n(). Ça garantit un comportement
 * stable et prévisible pour les occurrences.
 *
 * @return array[] liste de ['start_ts' => int, 'end_ts' => int, 'index' => int]
 */
function poke_hub_events_generate_occurrences_for_meta(
    int $base_start_ts,
    int $base_end_ts,
    array $meta,
    int $max = 100
): array {

    $freq          = $meta['freq'] ?? 'weekly';
    $interval      = isset($meta['interval']) ? (int) $meta['interval'] : 1;
    $interval      = max(1, $interval);
    $window_end_ts = $meta['window_end_ts'] ?? null;
    $mode          = $meta['mode'] ?? 'fixed';

    if (!$window_end_ts || $window_end_ts < $base_start_ts) {
        // Pas de fenêtre valide → on considère que seule la première occurrence existe.
        return [
            [
                'start_ts' => $base_start_ts,
                'end_ts'   => $base_end_ts,
                'index'    => 0,
            ],
        ];
    }

    try {

        if ($mode === 'local') {
            /**
             * 🔹 MODE LOCAL (floating time)
             *
             * On veut que l'heure "murale" reste la même (ex: 00:00)
             * même quand on traverse un changement d'heure.
             *
             * Donc on travaille en timezone du site (wp_timezone()).
             */
            $tz = wp_timezone();

            $start = new DateTime('@' . $base_start_ts);
            $end   = new DateTime('@' . $base_end_ts);

            // On se place dans le fuseau du site
            $start->setTimezone($tz);
            $end->setTimezone($tz);

        } else {
            /**
             * 🔹 MODE FIXED (instant global)
             *
             * On reste en UTC : chaque occurrence représente
             * le même instant absolu pour tout le monde.
             */
            $tz = new DateTimeZone('UTC');

            $start = new DateTime('@' . $base_start_ts);
            $end   = new DateTime('@' . $base_end_ts);

            $start->setTimezone($tz);
            $end->setTimezone($tz);
        }

    } catch (Exception $e) {
        return [];
    }

    $occurrences = [];
    $i           = 0;

    while ($i < $max) {
        $st = $start->getTimestamp();
        $et = $end->getTimestamp();

        if ($st > $window_end_ts) {
            break;
        }

        $occurrences[] = [
            'start_ts' => $st,
            'end_ts'   => $et,
            'index'    => $i,
        ];

        $i++;

        // Occurrence suivante
        poke_hub_events_add_interval($start, $end, $freq, $interval);
    }

    return $occurrences;
}

/**
 * Retourne la liste des types d’événements enfants (terms) pour un parent donné
 * dans la taxonomie event_type (BD distante JV Actu).
 *
 * @param string $parent_slug Slug du terme parent (ex: 'pvp')
 * @return array Liste d'objets (term_id, slug, name)
 */
function poke_hub_events_get_child_event_types(string $parent_slug): array {
    global $wpdb;

    $parent_slug = sanitize_title($parent_slug);
    if ($parent_slug === '') {
        return [];
    }

    // Tables DISTANTES via helper unifié
    $terms_table = pokehub_get_table('remote_terms');
    $tt_table    = pokehub_get_table('remote_term_taxonomy');

    // 1) term_taxonomy_id du parent
    $parent_ttid = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT tt.term_taxonomy_id
            FROM {$terms_table} t
            INNER JOIN {$tt_table} tt
                ON tt.term_id = t.term_id
            WHERE tt.taxonomy = %s
              AND t.slug = %s
            ",
            'event_type',
            $parent_slug
        )
    );

    if (!$parent_ttid) {
        return [];
    }

    // 2) Enfants de ce parent
    $sql = $wpdb->prepare(
        "
        SELECT t.term_id, t.slug, t.name
        FROM {$terms_table} t
        INNER JOIN {$tt_table} tt
            ON tt.term_id = t.term_id
        WHERE tt.taxonomy = %s
          AND tt.parent = %d
        ORDER BY t.name ASC
        ",
        'event_type',
        (int) $parent_ttid
    );

    $rows = $wpdb->get_results($sql);
    if (!$rows) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $out[] = (object) [
            'term_id' => (int) $row->term_id,
            'slug'    => $row->slug,
            'name'    => $row->name,
        ];
    }

    return $out;
}

/**
 * Retourne le type d’événement principal (event_type) d’un post JV Actu,
 * avec images par défaut + couleur depuis la termmeta DISTANTE.
 *
 * @param int $post_id
 * @return object|null {
 *   @type int    $term_id
 *   @type string $slug
 *   @type string $name
 *   @type int    $default_image_id
 *   @type string $default_image_url
 *   @type string $event_type_color
 * }
 */
function poke_hub_events_get_event_type_for_post(int $post_id): ?object {
    global $wpdb;

    // Tables distantes
    $terms_table    = pokehub_get_table('remote_terms');
    $tt_table       = pokehub_get_table('remote_term_taxonomy');
    $tr_table       = pokehub_get_table('remote_term_relationships');
    $termmeta_table = pokehub_get_table('remote_termmeta');

    // 1. Récupérer le terme principal (event_type) pour ce post dans la BDD DISTANTE
    $sql = $wpdb->prepare(
        "
        SELECT t.term_id, t.slug, t.name
        FROM {$terms_table} t
        INNER JOIN {$tt_table} tt
            ON tt.term_id = t.term_id
        INNER JOIN {$tr_table} tr
            ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE tt.taxonomy = %s
          AND tr.object_id = %d
        ORDER BY t.name ASC
        LIMIT 1
        ",
        'event_type',
        $post_id
    );

    $row = $wpdb->get_row($sql);
    if (!$row) {
        return null;
    }

    $term_id           = (int) $row->term_id;
    $default_image_id  = 0;
    $default_image_url = '';
    $event_color       = '';

    /*
     * 2. Lecture des métas DISTANTES (termmeta distante)
     */
    if ($termmeta_table) {
        $meta_rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT meta_key, meta_value
                FROM {$termmeta_table}
                WHERE term_id = %d
                ",
                $term_id
            )
        );

        $meta = [];
        if ($meta_rows) {
            foreach ($meta_rows as $m_row) {
                $meta[$m_row->meta_key] = $m_row->meta_value;
            }
        }

        // Variantes possibles pour l'ID d'image par défaut
        $id_keys = [
            'event_type_default_image_id',
            '_event_type_default_image_id',
        ];

        foreach ($id_keys as $k) {
            if (!empty($meta[$k])) {
                $default_image_id = (int) $meta[$k];
                break;
            }
        }

        // Variantes possibles pour l'URL d'image par défaut
        $url_keys = [
            'event_type_default_image_url',
            '_event_type_default_image_url',
        ];

        foreach ($url_keys as $k) {
            if (!empty($meta[$k])) {
                $default_image_url = (string) $meta[$k];
                break;
            }
        }

        // Couleur : event_type_color ou _event_type_color
        if (!empty($meta['event_type_color'])) {
            $event_color = trim((string) $meta['event_type_color']);
        } elseif (!empty($meta['_event_type_color'])) {
            $event_color = trim((string) $meta['_event_type_color']);
        }
    }

    /*
     * 3. Fallback local au cas où (pas obligatoire mais pratique en dev)
     */
    if (!$default_image_id && !$default_image_url && $event_color === '') {
        $local_default_id  = get_term_meta($term_id, 'event_type_default_image_id', true);
        $local_default_url = get_term_meta($term_id, 'event_type_default_image_url', true);
        $local_color       = get_term_meta($term_id, '_event_type_color', true);
        if (! $local_color) {
            $local_color = get_term_meta($term_id, 'event_type_color', true);
        }

        if ($local_default_id) {
            $default_image_id = (int) $local_default_id;
        }
        if ($local_default_url) {
            $default_image_url = (string) $local_default_url;
        }
        if ($local_color) {
            $event_color = trim((string) $local_color);
        }
    }

    /*
     * 4. Normalisation de la couleur (#xxxxxx)
     */
    if ($event_color !== '') {
        $event_color = trim($event_color);
        if ($event_color !== '' && $event_color[0] !== '#') {
            $event_color = '#' . $event_color;
        }
    }

    return (object) [
        'term_id'           => $term_id,
        'slug'              => (string) $row->slug,
        'name'              => (string) $row->name,
        'default_image_id'  => $default_image_id,
        'default_image_url' => $default_image_url,
        'event_type_color'  => $event_color,
    ];
}

/**
 * Retourne un type d’événement (event_type) à partir de son slug,
 * avec images par défaut + couleur depuis la termmeta DISTANTE.
 *
 * @param string $slug
 * @return object|null {
 *   @type int    $term_id
 *   @type string $slug
 *   @type string $name
 *   @type int    $default_image_id
 *   @type string $default_image_url
 *   @type string $event_type_color
 * }
 */
function poke_hub_events_get_event_type_by_slug(string $slug): ?object {
    global $wpdb;

    $slug = sanitize_title($slug);
    if ($slug === '') {
        return null;
    }

    $terms_table    = pokehub_get_table('remote_terms');
    $tt_table       = pokehub_get_table('remote_term_taxonomy');
    $termmeta_table = pokehub_get_table('remote_termmeta');

    // 1. On retrouve le term à partir du slug dans la taxonomie event_type
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT t.term_id, t.slug, t.name
            FROM {$terms_table} t
            INNER JOIN {$tt_table} tt
                ON tt.term_id = t.term_id
            WHERE tt.taxonomy = %s
              AND t.slug = %s
            LIMIT 1
            ",
            'event_type',
            $slug
        )
    );

    if (!$row) {
        return null;
    }

    $term_id           = (int) $row->term_id;
    $default_image_id  = 0;
    $default_image_url = '';
    $event_color       = '';

    // 2. Termmeta DISTANTE (comme pour poke_hub_events_get_event_type_for_post)
    if ($termmeta_table) {
        $meta_rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT meta_key, meta_value
                FROM {$termmeta_table}
                WHERE term_id = %d
                ",
                $term_id
            )
        );

        $meta = [];
        if ($meta_rows) {
            foreach ($meta_rows as $m_row) {
                $meta[$m_row->meta_key] = $m_row->meta_value;
            }
        }

        // Variantes pour l'ID d'image
        $id_keys = [
            'event_type_default_image_id',
            '_event_type_default_image_id',
        ];

        foreach ($id_keys as $k) {
            if (!empty($meta[$k])) {
                $default_image_id = (int) $meta[$k];
                break;
            }
        }

        // Variantes pour l'URL d'image
        $url_keys = [
            'event_type_default_image_url',
            '_event_type_default_image_url',
        ];

        foreach ($url_keys as $k) {
            if (!empty($meta[$k])) {
                $default_image_url = (string) $meta[$k];
                break;
            }
        }

        // Couleur
        if (!empty($meta['event_type_color'])) {
            $event_color = trim((string) $meta['event_type_color']);
        } elseif (!empty($meta['_event_type_color'])) {
            $event_color = trim((string) $meta['_event_type_color']);
        }
    }

    // 3. Fallback local (optionnel, comme dans l’autre fonction)
    if (!$default_image_id && !$default_image_url && $event_color === '') {
        $local_default_id  = get_term_meta($term_id, 'event_type_default_image_id', true);
        $local_default_url = get_term_meta($term_id, 'event_type_default_image_url', true);
        $local_color       = get_term_meta($term_id, '_event_type_color', true);
        if (!$local_color) {
            $local_color = get_term_meta($term_id, 'event_type_color', true);
        }

        if ($local_default_id) {
            $default_image_id = (int) $local_default_id;
        }
        if ($local_default_url) {
            $default_image_url = (string) $local_default_url;
        }
        if ($local_color) {
            $event_color = trim((string) $local_color);
        }
    }

    // Normalisation couleur
    if ($event_color !== '') {
        $event_color = trim($event_color);
        if ($event_color !== '' && $event_color[0] !== '#') {
            $event_color = '#' . $event_color;
        }
    }

    return (object) [
        'term_id'           => $term_id,
        'slug'              => (string) $row->slug,
        'name'              => (string) $row->name,
        'default_image_id'  => $default_image_id,
        'default_image_url' => $default_image_url,
        'event_type_color'  => $event_color,
    ];
}

/**
 * Enrichit un objet événement avec les infos de type (nom, couleur, image)
 * en se basant UNIQUEMENT sur le type distant (remote).
 *
 * - On lit $event->event_type_slug
 * - On va chercher le term distant via poke_hub_events_get_event_type_by_slug()
 * - On remplit :
 *     - event_type_name
 *     - event_type_color
 *     - image_url / image_id (si vide)
 *
 * @param object $event
 * @return object
 */
function poke_hub_events_enrich_type_from_remote(object $event): object {

    if (empty($event->event_type_slug)) {
        return $event;
    }

    if (!function_exists('poke_hub_events_get_event_type_by_slug')) {
        return $event;
    }

    $etype = poke_hub_events_get_event_type_by_slug($event->event_type_slug);
    if (!$etype) {
        return $event;
    }

    // Nom du type
    $event->event_type_name = $etype->name;

    // Couleur
    if (!empty($etype->event_type_color)) {
        $event->event_type_color = $etype->event_type_color;
    }

    // Image : on ne touche à rien si l’événement a déjà une image
    if (empty($event->image_url)) {

        if (!empty($etype->default_image_url)) {
            $event->image_url = $etype->default_image_url;

        } elseif (!empty($etype->default_image_id)
            && function_exists('poke_hub_events_get_remote_attachment_url')
        ) {
            $event->image_id  = (int) $etype->default_image_id;
            $event->image_url = poke_hub_events_get_remote_attachment_url($event->image_id);
        }
    }

    return $event;
}

/**
 * Récupère tous les posts marqués comme événements sur JV Actu.
 *
 * Nouvelle logique :
 * - on lit _event_sort_start / _event_sort_end (timestamps Unix)
 *   → valables pour les modes local & fixed
 * - plus besoin de parser _event_start / _event_end ici
 */
function poke_hub_events_fetch_all(): array {
    global $wpdb;
    
    // 🔹 Cache objet (Redis, etc.) – group "poke_hub_events"
    $cache_key = 'poke_hub_events_all';

    $cached = wp_cache_get($cache_key, 'poke_hub_events');
    if ($cached !== false && is_array($cached)) {
        return $cached;
    }

    // Tables distantes
    $posts_table    = pokehub_get_table('remote_posts');
    $postmeta_table = pokehub_get_table('remote_postmeta');

    // On garde _event_sort_start/_event_sort_end pour le tri,
    // mais on récupère aussi _event_mode + _event_start_local/_event_end_local
    $sql = "
        SELECT 
            p.ID,
            p.post_title,
            p.post_name,
            p.post_date,
            p.post_content,
            m_title.meta_value        AS event_title,
            m_sort_start.meta_value   AS sort_start_ts,
            m_sort_end.meta_value     AS sort_end_ts,
            m_mode.meta_value         AS event_mode,
            m_start_local.meta_value  AS start_local,
            m_end_local.meta_value    AS end_local,
            p.guid                    AS remote_url
        FROM {$posts_table} AS p
        INNER JOIN {$postmeta_table} AS m_enabled
            ON m_enabled.post_id = p.ID
           AND m_enabled.meta_key = '_event_enabled'
           AND m_enabled.meta_value = '1'
        INNER JOIN {$postmeta_table} AS m_sort_start
            ON m_sort_start.post_id = p.ID
           AND m_sort_start.meta_key = '_event_sort_start'
        INNER JOIN {$postmeta_table} AS m_sort_end
            ON m_sort_end.post_id = p.ID
           AND m_sort_end.meta_key = '_event_sort_end'
        LEFT JOIN {$postmeta_table} AS m_title
            ON m_title.post_id = p.ID
           AND m_title.meta_key = '_event_title'
        LEFT JOIN {$postmeta_table} AS m_mode
            ON m_mode.post_id = p.ID
           AND m_mode.meta_key = '_event_mode'
        LEFT JOIN {$postmeta_table} AS m_start_local
            ON m_start_local.post_id = p.ID
           AND m_start_local.meta_key = '_event_start_local'
        LEFT JOIN {$postmeta_table} AS m_end_local
            ON m_end_local.post_id = p.ID
           AND m_end_local.meta_key = '_event_end_local'
        WHERE p.post_status = 'publish'
          AND p.post_type   = 'post'
        ORDER BY m_sort_start.meta_value ASC
    ";

    $rows = $wpdb->get_results($sql);

    if (!$rows) {
        return [];
    }

    $events = [];

    foreach ($rows as $row) {

        $mode = isset($row->event_mode) && $row->event_mode !== ''
            ? trim((string) $row->event_mode)
            : 'fixed';

        $start_ts = 0;
        $end_ts   = 0;

        if ($mode === 'local') {
            // 🔸 MODE LOCAL : on ne doit PAS utiliser sort_start/sort_end pour l’affichage.
            // On repart des metas *_local, qui sont des heures "flottantes".
            $start_local = isset($row->start_local) ? trim((string) $row->start_local) : '';
            $end_local   = isset($row->end_local)   ? trim((string) $row->end_local)   : '';

            if ($start_local === '' || $end_local === '') {
                // Si pour une raison quelconque on n'a pas les metas locales,
                // on skippe l'événement plutôt que d'afficher n'importe quoi.
                continue;
            }

            try {
                $tz = wp_timezone(); // timezone du site Me5rine LAB (viewer)
                $dt_start = new DateTime($start_local, $tz);
                $dt_end   = new DateTime($end_local,   $tz);

                $start_ts = $dt_start->getTimestamp();
                $end_ts   = $dt_end->getTimestamp();
            } catch (Exception $e) {
                // En cas d’erreur de parsing, on ignore l’event.
                continue;
            }

        } else {
            // 🔹 MODE FIXED : instant global (ISO UTC côté JV Actu).
            // Ici, _event_sort_start/_event_sort_end sont déjà des timestamps absolus,
            // on peut les utiliser directement pour affichage + statut.
            $start_ts = isset($row->sort_start_ts) ? (int) $row->sort_start_ts : 0;
            $end_ts   = isset($row->sort_end_ts)   ? (int) $row->sort_end_ts   : 0;

            if (!$start_ts || !$end_ts) {
                continue;
            }
        }

        // Statut basé sur les timestamps unifiés (mode local ou fixed)
        $status = poke_hub_events_compute_status_from_ts($start_ts, $end_ts);

        // Titre : méta distante _event_title > post_title distant
        $raw_title = !empty($row->event_title) ? $row->event_title : $row->post_title;
        $title     = trim(wp_strip_all_tags((string) $raw_title));

        // Type d'événement principal (couleur + image par défaut distantes déjà gérées)
        $event_type  = poke_hub_events_get_event_type_for_post((int) $row->ID);

        // Image (thumbnail distante + fallback type)
        $event_image = poke_hub_events_get_event_image((int) $row->ID, $event_type);

        $events[] = (object) [
            'id'               => (int) $row->ID,
            'title'            => $title,
            'slug'             => $row->post_name,
            'content'          => $row->post_content,
            'start_ts'         => $start_ts,
            'end_ts'           => $end_ts,
            'status'           => $status,
            'event_type_slug'  => $event_type ? $event_type->slug : '',
            'event_type_name'  => $event_type ? $event_type->name : '',
            'event_type_color' => $event_type && !empty($event_type->event_type_color)
                ? $event_type->event_type_color
                : '',
            'image_id'         => $event_image['id'],
            'image_url'        => $event_image['url'],
            'remote_url'       => $row->remote_url,
        ];
    }

    // Stockage en cache pour 1 heure (3600s)
    wp_cache_set($cache_key, $events, 'poke_hub_events', 3600);

    return $events;
}

/**
 * Retourne les IDs de posts (object_id) qui ont un terme enfant
 * d’un terme parent donné (par slug) pour une taxonomie donnée.
 *
 * Ex: taxonomy = 'event_type', parent_slug = 'pvp'
 */
function poke_hub_events_get_child_term_object_ids(string $taxonomy, string $parent_slug): array {
    global $wpdb;

    $taxonomy    = sanitize_key($taxonomy);
    $parent_slug = sanitize_title($parent_slug);

    if ($taxonomy === '' || $parent_slug === '') {
        return [];
    }

    $terms_table = pokehub_get_table('remote_terms');
    $tt_table    = pokehub_get_table('remote_term_taxonomy');
    $tr_table    = pokehub_get_table('remote_term_relationships');

    // 1) On récupère le term_taxonomy_id du parent
    $parent_ttid = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT tt.term_taxonomy_id
            FROM {$terms_table} t
            INNER JOIN {$tt_table} tt
                ON tt.term_id = t.term_id
            WHERE tt.taxonomy = %s
              AND t.slug = %s
            ",
            $taxonomy,
            $parent_slug
        )
    );

    if (!$parent_ttid) {
        return [];
    }

    // 2) On récupère tous les object_id qui ont un terme enfant de ce parent
    $sql = $wpdb->prepare(
        "
        SELECT DISTINCT tr.object_id
        FROM {$tt_table} child_tt
        INNER JOIN {$tr_table} tr
            ON tr.term_taxonomy_id = child_tt.term_taxonomy_id
        WHERE child_tt.taxonomy = %s
          AND child_tt.parent = %d
        ",
        $taxonomy,
        (int) $parent_ttid
    );

    $ids = $wpdb->get_col($sql);
    if (!$ids) {
        return [];
    }

    return array_map('intval', $ids);
}

/**
 * Filtre les événements par statut (current/upcoming/past/all)
 * et éventuellement par taxonomies (catégorie WP, type d'événement, etc.),
 * tout en dépliant les récurrences en plusieurs occurrences si besoin.
 *
 * @param string $status 'current'|'upcoming'|'past'|'all'
 * @param array  $args   [
 *   'taxonomy'          => 'event_type' ou 'category' etc. (optionnel, générique),
 *   'term'              => 'slug-du-terme' (optionnel, générique),
 *   'category'          => 'slug-de-categorie',         // catégorie WP
 *   'event_type'        => 'slug-de-type-evenement',    // taxo custom des événements
 *   'event_type_parent' => 'slug-du-parent',            // parent d’event_type
 *   'order'             => 'asc'|'desc',                // tri par date de début
 * ]
 *
 * @return array liste d'objets événement (une occurrence = un objet)
 */
function poke_hub_events_get_by_status(string $status = 'all', array $args = []): array {
    global $wpdb;

    $status = in_array($status, ['current', 'upcoming', 'past', 'all'], true)
        ? $status
        : 'all';

    $order = isset($args['order']) ? strtolower($args['order']) : 'asc';
    if (!in_array($order, ['asc', 'desc'], true)) {
        $order = 'asc';
    }

    // ---- 1) Construction des filtres taxonomiques ----

    $filters = [];

    // Filtre générique (pour usage interne/dev)
    if (!empty($args['taxonomy']) && !empty($args['term'])) {
        $filters[] = [
            'taxonomy' => sanitize_key($args['taxonomy']),
            'term'     => sanitize_title($args['term']),
        ];
    }

    // Filtre catégorie WP
    if (!empty($args['category'])) {
        $filters[] = [
            'taxonomy' => 'category',
            'term'     => sanitize_title($args['category']),
        ];
    }

    // 🔁 Filtre event_type (peut être string OU tableau de slugs)
    if (!empty($args['event_type'])) {
        $event_terms = (array) $args['event_type'];

        $event_terms = array_map(
            'sanitize_title',
            array_filter($event_terms, 'strlen')
        );

        if ($event_terms) {
            $filters[] = [
                'taxonomy' => 'event_type',
                // ⬇️ on stocke un tableau de slugs
                'terms'    => $event_terms,
            ];
        }
    }

    $allowed_ids = null;
    $event_type_filter_applied = false; // Flag pour savoir si un filtre event_type a été appliqué

    if (!empty($filters)) {
        $terms_table = pokehub_get_table('remote_terms');
        $tt_table    = pokehub_get_table('remote_term_taxonomy');
        $tr_table    = pokehub_get_table('remote_term_relationships');

        foreach ($filters as $filter) {
            $tax = $filter['taxonomy'];

            // 🎯 Cas 1 : filtre avec plusieurs slugs (event_type IN (...))
            if (!empty($filter['terms']) && is_array($filter['terms'])) {

                $terms = $filter['terms'];

                // Préparation des placeholders %s,%s,%s...
                $placeholders = implode(',', array_fill(0, count($terms), '%s'));

                // On passe [ $tax, ...$terms ] à prepare()
                $params = array_merge([$tax], $terms);

                $sql = $wpdb->prepare(
                    "
                    SELECT tr.object_id
                    FROM {$terms_table} t
                    INNER JOIN {$tt_table} tt
                        ON tt.term_id = t.term_id
                    INNER JOIN {$tr_table} tr
                        ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE tt.taxonomy = %s
                      AND t.slug IN ($placeholders)
                    ",
                    $params
                );

            } else {
                // Cas 2 : filtre simple (un seul slug)
                $term = isset($filter['term']) ? $filter['term'] : '';

                if ($term === '') {
                    // sécurité : on saute ce filtre vide
                    continue;
                }

                $sql = $wpdb->prepare(
                    "
                    SELECT tr.object_id
                    FROM {$terms_table} t
                    INNER JOIN {$tt_table} tt
                        ON tt.term_id = t.term_id
                    INNER JOIN {$tr_table} tr
                        ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE tt.taxonomy = %s
                      AND t.slug = %s
                    ",
                    $tax,
                    $term
                );
            }

            $ids = $wpdb->get_col($sql);

            if (!$ids) {
                // Aucun post pour ce filtre dans les posts distants
                if ($tax === 'event_type') {
                    // Pour event_type, on marque qu'aucun post distant ne correspond
                    // mais on continue pour chercher dans les special events locaux
                    $event_type_filter_applied = true;
                    $allowed_ids = []; // Tableau vide = aucun post distant ne correspond au filtre event_type
                    continue;
                } else {
                    // Pour les autres filtres (catégorie, etc.), on retourne vide
                    return [];
                }
            }

            $ids = array_map('intval', $ids);

            if ($allowed_ids === null) {
                $allowed_ids = $ids;
            } else {
                // Intersections successives pour gérer "catégorie + event_type + autre"
                $allowed_ids = array_values(array_intersect($allowed_ids, $ids));

                if (!$allowed_ids && $tax !== 'event_type') {
                    // Pour les autres filtres, si l'intersection est vide, on retourne vide
                    return [];
                }
                // Pour event_type, si l'intersection est vide, on continue (allowed_ids reste vide)
                if ($tax === 'event_type' && !$allowed_ids) {
                    $event_type_filter_applied = true;
                }
            }
        }
    }

    // 🔥 Filtre spécifique "event_type_parent" : enfants d'un type parent
    if (!empty($args['event_type_parent'])) {
        $parent_slug = sanitize_title($args['event_type_parent']);

        $child_ids = poke_hub_events_get_child_term_object_ids(
            'event_type',
            $parent_slug
        );

        if (!$child_ids) {
            // Si aucun enfant n'est trouvé pour le parent, on continue quand même
            // pour chercher dans les special events locaux (ils peuvent avoir le type directement)
            // On marque simplement qu'aucun post distant ne correspond
            $allowed_ids = [];
            $event_type_filter_applied = true;
        } else {
            if ($allowed_ids === null) {
                $allowed_ids = $child_ids;
            } else {
                $allowed_ids = array_values(array_intersect($allowed_ids, $child_ids));
                if (!$allowed_ids && !$event_type_filter_applied) {
                    // Si l'intersection est vide et qu'on n'a pas de filtre event_type,
                    // on retourne vide (comportement normal)
                    return [];
                }
                // Si on a un filtre event_type, on continue même si l'intersection est vide
            }
        }
    }

    // ---- 2) Récupération des événements de base (1 par post) ----

    $all_events = poke_hub_events_fetch_all();
    $out        = [];

    // Si $allowed_ids est un tableau vide (pas null) ET qu'un filtre event_type a été appliqué,
    // cela signifie qu'aucun post distant ne correspond au filtre
    // Dans ce cas, on ne retourne AUCUN post distant, seulement les special events locaux
    $skip_remote_posts = ($event_type_filter_applied && is_array($allowed_ids) && empty($allowed_ids));

    if (!$skip_remote_posts) {
        foreach ($all_events as $event) {

            // Filtre taxo : si une liste d'IDs est définie, on ne garde que ceux-là
            if ($allowed_ids !== null && is_array($allowed_ids) && !empty($allowed_ids) && !in_array((int) $event->id, $allowed_ids, true)) {
                continue;
            }

        // Métas de récurrence pour cet event
        $meta         = poke_hub_events_get_recurring_meta($event->id);
        $is_recurring = ($meta['recurring'] === '1');

        // Cas simple : non récurrent
        if (!$is_recurring) {
            $st = poke_hub_events_compute_status_from_ts($event->start_ts, $event->end_ts);
            if ($status === 'all' || $st === $status) {
                $event->status = $st;
                $out[]         = $event;
            }
            continue;
        }

        // Cas récurrent : on génère toutes les occurrences
        $occurrences = poke_hub_events_generate_occurrences_for_meta(
            $event->start_ts,
            $event->end_ts,
            $meta,
            100
        );

        if (!$occurrences) {
            // Fallback : on traite comme un non récurrent
            $st = poke_hub_events_compute_status_from_ts($event->start_ts, $event->end_ts);
            if ($status === 'all' || $st === $status) {
                $event->status = $st;
                $out[]         = $event;
            }
            continue;
        }

        foreach ($occurrences as $occ) {
            $st_ts = (int) $occ['start_ts'];
            $en_ts = (int) $occ['end_ts'];

            $st = poke_hub_events_compute_status_from_ts($st_ts, $en_ts);

            if ($status !== 'all' && $st !== $status) {
                continue;
            }

            $clone = clone $event;
            $clone->start_ts         = $st_ts;
            $clone->end_ts           = $en_ts;
            $clone->status           = $st;
            $clone->recurring        = true;
            $clone->occurrence_index = $occ['index'];

            $out[] = $clone;
        }
        }
    }

    // ---- 2bis) Ajout des special events (locaux Poké HUB) ----

    if (function_exists('poke_hub_special_events_query')) {

        // Gestion du filtre event_type :
        // - pour les remote events tu acceptes déjà string|array
        // - on fait pareil ici, et on laisse la fonction interne filtrer.
        $event_type_filter = null;
        if (!empty($args['event_type'])) {
            $event_type_filter = $args['event_type'];
        }

        $special_events = poke_hub_special_events_query([
            // null = pas de filtre de statut côté special_events_query
            'status'      => ($status === 'all') ? null : $status,
            'event_type'  => $event_type_filter,
            // pour l'instant on ne met pas de bornes de date (optionnel)
            'start_after' => null,
            'end_before'  => null,
        ]);

        if (!empty($special_events)) {
            foreach ($special_events as $sevent) {
                // $sevent est déjà un objet avec start_ts / end_ts / status / remote_url, etc.
                $out[] = $sevent;
            }
        }
    }

    // ---- 3) Tri global par date de début d’occurrence ----

    usort($out, function ($a, $b) {
        if ($a->start_ts === $b->start_ts) {
            return 0;
        }
        return ($a->start_ts < $b->start_ts) ? -1 : 1;
    });

    if ($order === 'desc') {
        $out = array_reverse($out);
    }

    return $out;
}

/**
 * Récupère le titre d'un événement.
 * Priorité à la méta personnalisée `_event_title`, sinon titre natif du post.
 *
 * @param int $post_id
 * @return string
 */
function poke_hub_events_get_event_title(int $post_id): string {
    $meta_title = get_post_meta($post_id, '_event_title', true);

    if (is_string($meta_title)) {
        $meta_title = trim($meta_title);
    } else {
        $meta_title = '';
    }

    if ($meta_title !== '') {
        return $meta_title;
    }

    $post = get_post($post_id);
    if (! $post) {
        return '';
    }

    return get_the_title($post);
}

/**
 * Récupère l'image d'un événement DISTANT (JV Actu) :
 * - d'abord la thumbnail distante (_thumbnail_id + 3cf_itasems)
 * - sinon l'image par défaut du type (event_type_default_image_id / event_type_default_image_url) distants.
 *
 * @param int         $post_id
 * @param object|null $event_type Objet retourné par poke_hub_events_get_event_type_for_post()
 *
 * @return array {
 *   @type int|null    $id   ID de l'attachment distant (si connu)
 *   @type string|null $url  URL distante (S3 / CDN)
 * }
 */
function poke_hub_events_get_event_image(int $post_id, ?object $event_type = null): array {
    global $wpdb;

    // postmeta DISTANT
    $postmeta_table = pokehub_get_table('remote_postmeta');

    $image_id  = null;
    $image_url = null;

    // 1. Thumbnail distante : meta _thumbnail_id dans la postmeta DISTANTE
    $thumb_id = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT meta_value
            FROM {$postmeta_table}
            WHERE post_id = %d
              AND meta_key = '_thumbnail_id'
            LIMIT 1
            ",
            $post_id
        )
    );

    if ($thumb_id) {
        $image_id  = (int) $thumb_id;
        $image_url = poke_hub_events_get_remote_attachment_url($image_id);
    }

    // 2. Fallback : image par défaut du type (distante)
    if (!$image_url && $event_type) {

        // 2a. URL brute directe (si tu stockes déjà une URL complète côté JV Actu)
        if (!empty($event_type->default_image_url)) {
            $image_url = esc_url_raw($event_type->default_image_url);
        }

        // 2b. Ou ID d'attachment distant → on reconstruit l'URL via as3cf_items
        if (!$image_url && !empty($event_type->default_image_id)) {
            $fallback_id = (int) $event_type->default_image_id;
            if ($fallback_id > 0) {
                $image_id  = $fallback_id;
                $image_url = poke_hub_events_get_remote_attachment_url($image_id);
            }
        }
    }

    return [
        'id'  => $image_id ?: null,
        'url' => $image_url ?: null,
    ];
}


/**
 * Construit l'URL distante d'un attachment JV Actu à partir de la table as3cf_items.
 *
 * @param int $attachment_id ID du post attachment distant (JV Actu)
 * @return string|null URL complète (souvent S3 ou domaine custom), ou null si introuvable
 */
function poke_hub_events_get_remote_attachment_url(int $attachment_id): ?string {
    global $wpdb;

    // Table as3cf distante via helper
    $as3cf_table = pokehub_get_table('remote_as3cf_items');
    $posts_table = pokehub_get_table('remote_posts');

    // On récupère la ligne as3cf pour cet attachment
    $item = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT provider, region, bucket, path
            FROM {$as3cf_table}
            WHERE source_id = %d
            LIMIT 1
            ",
            $attachment_id
        )
    );

    if ($item && !empty($item->bucket) && !empty($item->path)) {
        $bucket = rtrim((string) $item->bucket, '/');
        $path   = ltrim((string) $item->path, '/');

        // Cas le plus simple : le bucket est déjà un hostname complet (ce qui est ton cas)
        // ex: bucket.me5rine-lab.com → https://bucket.me5rine-lab.com/2025/11/.../image.png
        if (strpos($bucket, '.') !== false) {
            $url = 'https://' . $bucket . '/' . $path;
        } else {
            // Fallback générique AWS (au cas où)
            $region = !empty($item->region) ? $item->region : 'us-east-1';
            $url = sprintf(
                'https://%s.s3.%s.amazonaws.com/%s',
                $bucket,
                $region,
                $path
            );
        }

        return esc_url_raw($url);
    }

    // Fallback éventuel : on tente le guid distant si jamais as3cf_items ne connait pas cet ID
    $guid = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT guid
            FROM {$posts_table}
            WHERE ID = %d
            LIMIT 1
            ",
            $attachment_id
        )
    );

    if (!empty($guid)) {
        return esc_url_raw($guid);
    }

    return null;
}

/**
 * Retourne l'URL publique d'un special event.
 *
 * Exemple d'URL finale :
 *   /pokemon-go/events/mon-event/
 */
function poke_hub_special_event_get_url(string $slug): string {
    // Format : /pokemon-go/events/{slug}/
    return home_url('/pokemon-go/events/' . rawurlencode($slug) . '/');
}

/**
 * Récupère les Pokémon liés à un special event.
 *
 * @param int $event_id
 * @return int[] Liste d'IDs de Pokémon
 */
function poke_hub_special_event_get_pokemon(int $event_id): array {
    global $wpdb;

    $event_id = (int) $event_id;
    if ($event_id <= 0) {
        return [];
    }

    $table = pokehub_get_table('special_event_pokemon');

    $ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT pokemon_id
             FROM {$table}
             WHERE event_id = %d",
            $event_id
        )
    );

    if (!$ids) {
        return [];
    }

    return array_map('intval', $ids);
}

/**
 * Récupère les bonus liés à un special event.
 *
 * @param int $event_id
 * @return int[] Liste d'IDs de bonus
 */
function poke_hub_special_event_get_bonuses(int $event_id): array {
    global $wpdb;

    $event_id = (int) $event_id;
    if ($event_id <= 0) {
        return [];
    }

    $table = pokehub_get_table('special_event_bonus');

    $ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT bonus_id
             FROM {$table}
             WHERE event_id = %d",
            $event_id
        )
    );

    if (!$ids) {
        return [];
    }

    return array_map('intval', $ids);
}

/**
 * Retourne les lignes Pokémon pour un special event
 * (structure adaptée au formulaire admin).
 *
 * @param int $event_id
 * @return array[] [['pokemon_id' => int, 'attacks' => []], ...]
 */
function poke_hub_special_event_get_pokemon_rows(int $event_id): array {
    $ids = poke_hub_special_event_get_pokemon($event_id);
    $rows = [];

    foreach ($ids as $pid) {
        $rows[] = [
            'pokemon_id' => (int) $pid,
            'attacks'    => [], // Pour plus tard si tu veux pré-cocher les attaques
        ];
    }

    return $rows;
}

/**
 * Retourne les lignes bonus pour un special event
 * (id + description).
 *
 * @param int $event_id
 * @return array[] [['bonus_id' => int, 'description' => string], ...]
 */
function poke_hub_special_event_get_bonus_rows(int $event_id): array {
    global $wpdb;

    $event_id = (int) $event_id;
    if ($event_id <= 0) {
        return [];
    }

    $table = pokehub_get_table('special_event_bonus');

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT bonus_id, description
             FROM {$table}
             WHERE event_id = %d",
            $event_id
        ),
        ARRAY_A
    );

    if (!$rows) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'bonus_id'    => (int) $row['bonus_id'],
            'description' => (string) $row['description'],
        ];
    }

    return $out;
}

/**
 * Normalise une ligne de la table special_events vers le format
 * utilisé par le calendrier + la liste admin.
 *
 * @param array $row
 * @return object
 */
function poke_hub_special_event_normalize_row(array $row): object {
    $start_ts = (int) $row['start_ts'];
    $end_ts   = (int) $row['end_ts'];

    $status = poke_hub_events_compute_status_from_ts($start_ts, $end_ts);
    $slug   = (string) $row['slug'];
    // Normaliser le event_type pour s'assurer qu'il correspond aux filtres
    $etype  = sanitize_title((string) ($row['event_type'] ?? ''));

    // 🔹 Récupération mode + récurrence bruts
    $mode      = !empty($row['mode']) ? $row['mode'] : 'local';
    $recurring = !empty($row['recurring']) ? (int) $row['recurring'] : 0;

    // 🔹 Image spécifique du special event (nouvelle colonne)
    //    Si tu n’utilises pas image_id, il restera null.
    $image_id  = !empty($row['image_id']) ? (int) $row['image_id'] : null;
    $image_url = !empty($row['image_url']) ? (string) $row['image_url'] : '';

    // 🔹 On récupère le term event_type complet (nom + couleur + image par défaut)
    $event_type_obj = poke_hub_events_get_event_type_by_slug($etype);

    $event_type_name  = $etype;
    $event_type_color = '';

    if ($event_type_obj) {
        $event_type_name  = $event_type_obj->name;
        $event_type_color = $event_type_obj->event_type_color ?? '';

        // ⚠️ On ne touche à rien si le special event a déjà une image
        if ($image_url === '') {

            if (!empty($event_type_obj->default_image_url)) {
                $image_url = $event_type_obj->default_image_url;

            } elseif (!empty($event_type_obj->default_image_id)) {
                $image_id  = (int) $event_type_obj->default_image_id;
                $image_url = poke_hub_events_get_remote_attachment_url($image_id);
            }
        }
    }

    // Déterminer le titre selon la langue
    $title = (string) $row['title'];
    if (isset($row['title_en']) && isset($row['title_fr'])) {
        // Utiliser title_fr si disponible, sinon title_en, sinon title (compatibilité)
        $locale = get_locale();
        if (strpos($locale, 'fr') === 0 && !empty($row['title_fr'])) {
            $title = (string) $row['title_fr'];
        } elseif (!empty($row['title_en'])) {
            $title = (string) $row['title_en'];
        }
    }

    return (object) [
        'id'               => (int) $row['id'],
        'source'           => 'special',

        'event_type'       => $etype,
        'event_type_slug'  => $etype,
        'event_type_name'  => $event_type_name,
        'event_type_color' => $event_type_color,

        'title'            => $title,
        'slug'             => $slug,

        'start_ts'         => $start_ts,
        'end_ts'           => $end_ts,
        'status'           => $status,

        'remote_url'       => poke_hub_special_event_get_url($slug),

        'image_id'         => $image_id,
        'image_url'        => $image_url,

        'pokemon_id'       => isset($row['pokemon_id']) ? (int) $row['pokemon_id'] : null,
        'bonus_id'         => isset($row['bonus_id']) ? (int) $row['bonus_id'] : null,

        // 🔹 Ajouts pour affichage / logique commune
        'mode'             => $mode,          // 'local' ou 'fixed'
        'recurring'        => (bool) $recurring,
        'occurrence_index' => 0,              // sera éventuellement écrasé par la génération
    ];
}

/**
 * Récupère les special events, filtrables par status / type / dates,
 * et les retourne sous forme d’objets normalisés.
 *
 * @param array $args {
 *   @type string|null $status      'current'|'upcoming'|'past'|'all'
 *   @type string|null $event_type  Filtre sur un type d'événement
 *   @type int|null    $start_after Timestamp min (optionnel)
 *   @type int|null    $end_before  Timestamp max (optionnel)
 * }
 *
 * @return object[]
 */
function poke_hub_special_events_query(array $args = []): array {
    global $wpdb;

    $defaults = [
        'status'      => null,   // 'current'|'upcoming'|'past'|'all'|null
        'event_type'  => null,
        'start_after' => null,
        'end_before'  => null,
        'order'       => 'asc',  // nouveau : ordre de tri
    ];
    $args = wp_parse_args($args, $defaults);

    $order = strtolower((string) $args['order']);
    if (!in_array($order, ['asc', 'desc'], true)) {
        $order = 'asc';
    }

    $table = pokehub_get_table('special_events');
    
    // Vérifier que la table existe
    if (function_exists('pokehub_table_exists') && !pokehub_table_exists($table)) {
        return [];
    }

    // Construire la requête SQL avec filtrage event_type si nécessaire
    $where_clauses = [];
    
    if (!empty($args['event_type'])) {
        $filter_types = is_array($args['event_type']) 
            ? array_map('sanitize_title', array_filter($args['event_type'], 'is_string'))
            : [sanitize_title($args['event_type'])];
        
        if (!empty($filter_types)) {
            // Construire la clause IN avec échappement sécurisé
            // Utiliser esc_sql() pour chaque valeur (méthode recommandée pour IN clauses)
            $escaped_types = array_map('esc_sql', $filter_types);
            $where_clauses[] = "event_type IN ('" . implode("','", $escaped_types) . "')";
        }
    }
    
    // Construire la requête SQL
    // Essayer d'abord avec SELECT * (inclut title_en/title_fr si elles existent)
    $sql = "SELECT * FROM {$table}";
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    $rows = $wpdb->get_results($sql, ARRAY_A);
    
    // Vérifier si la requête a échoué (erreur SQL) - peut-être que les colonnes title_en/title_fr n'existent pas
    if ($rows === false && !empty($wpdb->last_error)) {
        // Erreur SQL - réessayer avec une requête explicite sans les colonnes optionnelles
        $sql = "SELECT id, slug, title, title_en, title_fr, event_type, description, start_ts, end_ts, mode, recurring, recurring_freq, recurring_interval, recurring_window_end_ts, image_id, image_url FROM {$table}";
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }
        $rows = $wpdb->get_results($sql, ARRAY_A);
    }
    
    // Si toujours une erreur ou pas de résultats, retourner vide
    if ($rows === false || !is_array($rows) || empty($rows)) {
        return [];
    }

    $events = [];

    foreach ($rows as $row) {
        // Normaliser event_type dans la row avant normalisation pour s'assurer de la correspondance
        if (isset($row['event_type'])) {
            $row['event_type'] = sanitize_title($row['event_type']);
        }
        
        // Filtrage supplémentaire après normalisation (au cas où la valeur en base n'était pas normalisée)
        if (!empty($args['event_type'])) {
            $filter_types = is_array($args['event_type']) 
                ? array_map('sanitize_title', array_filter($args['event_type'], 'is_string'))
                : [sanitize_title($args['event_type'])];
            
            if (!empty($filter_types) && !in_array($row['event_type'], $filter_types, true)) {
                continue;
            }
        }
        
        $base = poke_hub_special_event_normalize_row($row);

        $recurring = !empty($row['recurring']) ? '1' : '0';

        // 🔸 Pas récurrent → comportement simple
        if ($recurring !== '1') {
            $status = $base->status;

            if ($args['status'] && 'all' !== $args['status'] && $status !== $args['status']) {
                continue;
            }

            if ($args['start_after'] && $base->end_ts < $args['start_after']) {
                continue;
            }
            if ($args['end_before'] && $base->start_ts > $args['end_before']) {
                continue;
            }

            $events[] = $base;
            continue;
        }

        // 🔁 Récurrent → on génère les occurrences
        $mode            = !empty($row['mode']) ? $row['mode'] : 'local';
        $freq            = !empty($row['recurring_freq']) ? $row['recurring_freq'] : 'weekly';
        $interval        = !empty($row['recurring_interval']) ? (int) $row['recurring_interval'] : 1;
        $window_end_ts_raw = isset($row['recurring_window_end_ts']) ? (int) $row['recurring_window_end_ts'] : 0;

        if ($interval < 1) {
            $interval = 1;
        }

        $meta = [
            'recurring'     => '1',
            'mode'          => $mode,
            'freq'          => $freq,
            'interval'      => $interval,
            'window_end_ts' => $window_end_ts_raw ?: null,
        ];

        $occurrences = poke_hub_events_generate_occurrences_for_meta(
            $base->start_ts,
            $base->end_ts,
            $meta,
            100
        );

        if (!$occurrences) {
            // Fallback : comme non récurrent
            $status = poke_hub_events_compute_status_from_ts($base->start_ts, $base->end_ts);

            if ($args['status'] && 'all' !== $args['status'] && $status !== $args['status']) {
                continue;
            }

            $base->status = $status;
            $events[]     = $base;
            continue;
        }

        foreach ($occurrences as $occ) {
            $st_ts = (int) $occ['start_ts'];
            $en_ts = (int) $occ['end_ts'];

            $status = poke_hub_events_compute_status_from_ts($st_ts, $en_ts);

            if ($args['status'] && 'all' !== $args['status'] && $status !== $args['status']) {
                continue;
            }

            if ($args['start_after'] && $en_ts < $args['start_after']) {
                continue;
            }
            if ($args['end_before'] && $st_ts > $args['end_before']) {
                continue;
            }

            $clone = clone $base;
            $clone->start_ts         = $st_ts;
            $clone->end_ts           = $en_ts;
            $clone->status           = $status;
            $clone->recurring        = true;
            $clone->occurrence_index = $occ['index'];

            $events[] = $clone;
        }
    }

    // 🔹 Tri chrono comme pour les remote events
    usort($events, function ($a, $b) {
        if ($a->start_ts === $b->start_ts) {
            return 0;
        }
        return ($a->start_ts < $b->start_ts) ? -1 : 1;
    });

    if ('desc' === $order) {
        $events = array_reverse($events);
    }

    return $events;
}

/**
 * Variante de poke_hub_special_events_query() pour la table distante
 * "remote_special_events" (même schéma que special_events, mais
 * préfixe JV Actu via pokehub_get_table('remote_special_events')).
 *
 * @param array $args
 * @return object[]
 */
function poke_hub_special_events_query_remote(array $args = []): array {
    global $wpdb;

    $defaults = [
        'status'      => null,   // 'current'|'upcoming'|'past'|'all'|null
        'event_type'  => null,
        'start_after' => null,
        'end_before'  => null,
        'order'       => 'asc',
    ];
    $args = wp_parse_args($args, $defaults);

    $order = strtolower((string) $args['order']);
    if (!in_array($order, ['asc', 'desc'], true)) {
        $order = 'asc';
    }

    // 🔹 Table distante
    $table = pokehub_get_table('remote_special_events');
    if (!$table) {
        return [];
    }

    if (function_exists('pokehub_table_exists') && !pokehub_table_exists($table)) {
        return [];
    }

    $rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
    if (!$rows) {
        return [];
    }

    $events = [];

    foreach ($rows as $row) {
        $base = poke_hub_special_event_normalize_row($row);
        // Tag spécifique pour les distants
        $base->source = 'special_remote';

        // Filtre event_type (sur le type "brut")
        // Peut être un tableau ou une chaîne
        if (!empty($args['event_type'])) {
            $filter_types = is_array($args['event_type']) 
                ? array_map('sanitize_title', array_filter($args['event_type'], 'is_string'))
                : [sanitize_title($args['event_type'])];
            
            if (!empty($filter_types) && !in_array($base->event_type, $filter_types, true)) {
                continue;
            }
        }

        $recurring = !empty($row['recurring']) ? '1' : '0';

        // 🔸 Pas récurrent → comportement simple
        if ($recurring !== '1') {
            $status = $base->status;

            if ($args['status'] && 'all' !== $args['status'] && $status !== $args['status']) {
                continue;
            }

            if ($args['start_after'] && $base->end_ts < $args['start_after']) {
                continue;
            }
            if ($args['end_before'] && $base->start_ts > $args['end_before']) {
                continue;
            }

            $events[] = $base;
            continue;
        }

        // 🔁 Récurrent → on génère les occurrences
        $mode              = !empty($row['mode']) ? $row['mode'] : 'local';
        $freq              = !empty($row['recurring_freq']) ? $row['recurring_freq'] : 'weekly';
        $interval          = !empty($row['recurring_interval']) ? (int) $row['recurring_interval'] : 1;
        $window_end_ts_raw = isset($row['recurring_window_end_ts']) ? (int) $row['recurring_window_end_ts'] : 0;

        if ($interval < 1) {
            $interval = 1;
        }

        $meta = [
            'recurring'     => '1',
            'mode'          => $mode,
            'freq'          => $freq,
            'interval'      => $interval,
            'window_end_ts' => $window_end_ts_raw ?: null,
        ];

        $occurrences = poke_hub_events_generate_occurrences_for_meta(
            $base->start_ts,
            $base->end_ts,
            $meta,
            100
        );

        if (!$occurrences) {
            // Fallback : comme non récurrent
            $status = poke_hub_events_compute_status_from_ts($base->start_ts, $base->end_ts);

            if ($args['status'] && 'all' !== $args['status'] && $status !== $args['status']) {
                continue;
            }

            $base->status = $status;
            $events[]     = $base;
            continue;
        }

        foreach ($occurrences as $occ) {
            $st_ts = (int) $occ['start_ts'];
            $en_ts = (int) $occ['end_ts'];

            $status = poke_hub_events_compute_status_from_ts($st_ts, $en_ts);

            if ($args['status'] && 'all' !== $args['status'] && $status !== $args['status']) {
                continue;
            }

            if ($args['start_after'] && $en_ts < $args['start_after']) {
                continue;
            }
            if ($args['end_before'] && $st_ts > $args['end_before']) {
                continue;
            }

            $clone = clone $base;
            $clone->start_ts         = $st_ts;
            $clone->end_ts           = $en_ts;
            $clone->status           = $status;
            $clone->recurring        = true;
            $clone->occurrence_index = $occ['index'];

            $events[] = $clone;
        }
    }

    // 🔹 Tri chrono comme pour les remote events
    usort($events, function ($a, $b) {
        if ($a->start_ts === $b->start_ts) {
            return 0;
        }
        return ($a->start_ts < $b->start_ts) ? -1 : 1;
    });

    if ('desc' === $order) {
        $events = array_reverse($events);
    }

    return $events;
}

/**
 * Retourne le type d'événement LOCAL pour un post,
 * avec nom, slug, couleur et image par défaut.
 *
 * @param int $post_id
 * @return object|null
 */
function poke_hub_events_get_local_event_type_for_post(int $post_id): ?object {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return null;
    }

    // On prend le premier terme "event_type" attaché au post
    $terms = wp_get_post_terms($post_id, 'event_type');
    if (is_wp_error($terms) || empty($terms)) {
        return null;
    }

    $term    = $terms[0];
    $term_id = (int) $term->term_id;
    $slug    = (string) $term->slug;
    $name    = (string) $term->name;

    // Image par défaut (ID + URL)
    $default_image_id  = (int) get_term_meta($term_id, 'event_type_default_image_id', true);
    $default_image_url = (string) get_term_meta($term_id, 'event_type_default_image_url', true);

    if (!$default_image_url && $default_image_id) {
        $url = wp_get_attachment_image_url($default_image_id, 'large');
        if ($url) {
            $default_image_url = $url;
        }
    }

    // Couleur
    $color = get_term_meta($term_id, '_event_type_color', true);
    if ($color === '' || $color === null) {
        $color = get_term_meta($term_id, 'event_type_color', true);
    }
    $color = is_string($color) ? trim($color) : '';
    if ($color !== '' && $color[0] !== '#') {
        $color = '#' . $color;
    }

    return (object) [
        'term_id'           => $term_id,
        'slug'              => $slug,
        'name'              => $name,
        'default_image_id'  => $default_image_id,
        'default_image_url' => $default_image_url,
        'event_type_color'  => $color,
    ];
}

/**
 * Retourne les événements "post" en local, déjà filtrés / normalisés.
 *
 * Convention de métas (à aligner avec ton remote) :
 *  - _event_enabled      = 1
 *  - _event_sort_start   = timestamp début (int)
 *  - _event_sort_end     = timestamp fin (int)
 *  - _event_title        = titre override (optionnel)
 *  - _event_type_slug    = slug type d'événement (optionnel)
 *  - _event_type_name    = label type (optionnel)
 *  - _event_type_color   = couleur type (optionnel)
 *
 * En plus : si les métas _event_type_* ne sont pas remplies, on
 * retombe sur la taxonomie locale "event_type" + ses term_meta
 * (couleur + image par défaut).
 *
 * @param string $status  current|upcoming|past|all
 * @param array  $args
 * @return array<object>
 */
function poke_hub_events_get_local_posts_by_status(string $status, array $args = []): array {
    global $wpdb;

    $status = in_array($status, ['current', 'upcoming', 'past', 'all'], true)
        ? $status
        : 'current';

    $posts_table    = $wpdb->posts;
    $postmeta_table = $wpdb->postmeta;

    // Filtre éventuel par event_type (tableau de slugs)
    $filter_event_types = [];
    if (!empty($args['event_type'])) {
        $filter_event_types = (array) $args['event_type'];
        $filter_event_types = array_map(
            'sanitize_title',
            array_filter($filter_event_types, 'strlen')
        );
    }

    // Par précaution (même si ces tables existent toujours normalement)
    if (function_exists('pokehub_table_exists')) {
        if (!pokehub_table_exists($posts_table) || !pokehub_table_exists($postmeta_table)) {
            return [];
        }
    }

    $sql = "
        SELECT 
            p.ID,
            p.post_title,
            p.post_name,
            p.post_content,
            m_sort_start.meta_value   AS sort_start_ts,
            m_sort_end.meta_value     AS sort_end_ts,
            m_title.meta_value        AS event_title,
            m_type_slug.meta_value    AS event_type_slug,
            m_type_name.meta_value    AS event_type_name,
            m_type_color.meta_value   AS event_type_color
        FROM {$posts_table} AS p
        INNER JOIN {$postmeta_table} AS m_enabled
            ON m_enabled.post_id = p.ID
           AND m_enabled.meta_key = '_event_enabled'
           AND m_enabled.meta_value = '1'
        INNER JOIN {$postmeta_table} AS m_sort_start
            ON m_sort_start.post_id = p.ID
           AND m_sort_start.meta_key = '_event_sort_start'
        INNER JOIN {$postmeta_table} AS m_sort_end
            ON m_sort_end.post_id = p.ID
           AND m_sort_end.meta_key = '_event_sort_end'
        LEFT JOIN {$postmeta_table} AS m_title
            ON m_title.post_id = p.ID
           AND m_title.meta_key = '_event_title'
        LEFT JOIN {$postmeta_table} AS m_type_slug
            ON m_type_slug.post_id = p.ID
           AND m_type_slug.meta_key = '_event_type_slug'
        LEFT JOIN {$postmeta_table} AS m_type_name
            ON m_type_name.post_id = p.ID
           AND m_type_name.meta_key = '_event_type_name'
        LEFT JOIN {$postmeta_table} AS m_type_color
            ON m_type_color.post_id = p.ID
           AND m_type_color.meta_key = '_event_type_color'
        WHERE p.post_type = 'post'
          AND p.post_status = 'publish'
    ";

    $rows = $wpdb->get_results($sql);
    if (!$rows) {
        return [];
    }

    $events = [];

    foreach ($rows as $row) {

        // Métas éventuelles (_event_type_*)
        $event_type_slug  = $row->event_type_slug ? sanitize_title($row->event_type_slug) : '';
        $event_type_name  = $row->event_type_name ?: '';
        $event_type_color = $row->event_type_color ?: '';

        // Fallback : taxonomie locale "event_type"
        $local_type = poke_hub_events_get_local_event_type_for_post((int) $row->ID);

        if ($local_type) {
            if ($event_type_slug === '') {
                $event_type_slug = $local_type->slug;
            }
            if ($event_type_name === '') {
                $event_type_name = $local_type->name;
            }
            if ($event_type_color === '') {
                $event_type_color = $local_type->event_type_color;
            }
        }

        // Filtre par event_type si demandé
        if (!empty($filter_event_types)) {
            if ($event_type_slug === '' || !in_array($event_type_slug, $filter_event_types, true)) {
                continue;
            }
        }

        // Image : thumbnail local > image par défaut du type
        $thumb_id  = get_post_thumbnail_id($row->ID);
        $image_id  = $thumb_id ?: ($local_type->default_image_id ?? 0);
        $image_url = get_the_post_thumbnail_url($row->ID, 'large');

        if (!$image_url && $local_type && !empty($local_type->default_image_url)) {
            $image_url = $local_type->default_image_url;
        } elseif (!$image_url && $image_id) {
            $tmp = wp_get_attachment_image_url($image_id, 'large');
            if ($tmp) {
                $image_url = $tmp;
            }
        }

        $raw = [
            'id'               => (int) $row->ID,
            'title'            => $row->event_title ?: $row->post_title,
            'slug'             => $row->post_name,
            'content'          => $row->post_content,
            'sort_start_ts'    => (int) $row->sort_start_ts,
            'sort_end_ts'      => (int) $row->sort_end_ts,

            // On utilise le slug calculé (métas ou taxonomie)
            'event_type_slug'  => $event_type_slug,
            // name / color seront toujours résolus côté DISTANT
            'event_type_name'  => $event_type_name,
            'event_type_color' => $event_type_color,

            'image_id'         => $image_id,
            'image_url'        => $image_url,
            'url'              => get_permalink($row->ID),
            'source'           => 'local_post',
        ];

        $event = poke_hub_events_normalize_event($raw);

        // 🔥 enrichissement depuis le site distant
        if (function_exists('poke_hub_events_enrich_type_from_remote')) {
            $event = poke_hub_events_enrich_type_from_remote($event);
        }

        // Filtre par status si $status ≠ 'all'
        if ($status !== 'all' && $event->status !== $status) {
            continue;
        }

        $events[] = $event;

        // Filtre par status si $status ≠ 'all'
        if ($status !== 'all' && $event->status !== $status) {
            continue;
        }

        $events[] = $event;
    }

    return $events;
}

/**
 * Retourne les special events locaux (table custom), normalisés.
 *
 * @param string $status current|upcoming|past|all
 * @param array  $args
 * @return array<object>
 */
function poke_hub_special_events_get_local(string $status, array $args = []): array {

    global $wpdb;

    $status = in_array($status, ['current', 'upcoming', 'past', 'all'], true)
        ? $status
        : 'current';

    $table = pokehub_get_table('special_events');

    if (function_exists('pokehub_table_exists') && !pokehub_table_exists($table)) {
        return [];
    }

    $rows = $wpdb->get_results("SELECT * FROM {$table}");
    if (!$rows) {
        return [];
    }

    // Filtre par event_type si fourni
    // Peut être un tableau ou une chaîne
    $filter_event_types = [];
    if (!empty($args['event_type'])) {
        if (is_array($args['event_type'])) {
            // Si c'est un tableau, on sanitize tous les éléments
            $filter_event_types = array_map('sanitize_title', array_filter($args['event_type'], 'is_string'));
        } else {
            $filter_event_types = [sanitize_title($args['event_type'])];
        }
    }

    $events = [];

    foreach ($rows as $row) {
        $event_type_slug = $row->event_type ?? '';
        
        // Filtrer par type d'événement si demandé
        if (!empty($filter_event_types) && !in_array($event_type_slug, $filter_event_types, true)) {
            continue;
        }
        
        $raw = [
            'id'               => (int) $row->id,
            'title'            => $row->title ?? '',
            'slug'             => $row->slug ?? '',
            'sort_start_ts'    => isset($row->start_ts) ? (int) $row->start_ts : 0,
            'sort_end_ts'      => isset($row->end_ts)   ? (int) $row->end_ts   : 0,

            // On garde juste le slug, les infos viendront du remote
            'event_type_slug'  => $event_type_slug,
            'event_type_name'  => '',
            'event_type_color' => '',

            'image_id'         => !empty($row->image_id) ? (int) $row->image_id : 0,
            'image_url'        => !empty($row->image_id)
                ? wp_get_attachment_image_url((int) $row->image_id, 'large')
                : '',
            'url'              => !empty($row->link_url) ? $row->link_url : '',
            'remote_url'       => !empty($row->slug) && function_exists('poke_hub_special_event_get_url')
                ? poke_hub_special_event_get_url($row->slug)
                : '',
            'source'           => 'special_local',
        ];

        $event = poke_hub_events_normalize_event($raw);

        // 🔥 enrichissement depuis le site distant
        if (function_exists('poke_hub_events_enrich_type_from_remote')) {
            $event = poke_hub_events_enrich_type_from_remote($event);
        }

        if ($status !== 'all' && $event->status !== $status) {
            continue;
        }

        $events[] = $event;
    }

    return $events;
}

/**
 * Special events distants, en s'appuyant sur poke_hub_special_events_query_remote()
 * (qui gère aussi la récurrence + event_type + couleur + image).
 *
 * @param string $status current|upcoming|past|all
 * @param array  $args
 * @return array<object>
 */
function poke_hub_special_events_get_remote(string $status, array $args = []): array {

    $status = in_array($status, ['current', 'upcoming', 'past', 'all'], true)
        ? $status
        : 'current';

    $query_args = [
        'status'     => ($status === 'all') ? null : $status,
        'event_type' => $args['event_type'] ?? null,
        'order'      => $args['order'] ?? 'asc',
        // start_after / end_before possibles plus tard
    ];

    if (!function_exists('poke_hub_special_events_query_remote')) {
        return [];
    }

    $events = poke_hub_special_events_query_remote($query_args);

    // (par sécurité) on garde bien le tag source
    foreach ($events as $ev) {
        if (empty($ev->source)) {
            $ev->source = 'special_remote';
        }
    }

    return $events;
}

/**
 * Wrapper central : fusionne toutes les sources d'événements :
 *  - posts distants (+ special events locaux, déjà gérés dans get_by_status)
 *  - posts locaux
 *  - special events distants
 *
 * @param string $status current|upcoming|past|all
 * @param array  $args
 * @return array<object>
 */
function poke_hub_events_get_all_sources_by_status(string $status, array $args = []): array {

    $status = in_array($status, ['current', 'upcoming', 'past', 'all'], true)
        ? $status
        : 'current';

    $events = [];

    // 1) Posts distants
    if (function_exists('poke_hub_events_get_by_status')) {
        $remote_posts = poke_hub_events_get_by_status($status, $args);
        if (is_array($remote_posts)) {
            foreach ($remote_posts as $ev) {
                if (is_object($ev) && empty($ev->source)) {
                    $ev->source = 'remote_post';
                }
                $events[] = $ev;
            }
        }
    }

    // 2) Posts locaux
    if (function_exists('poke_hub_events_get_local_posts_by_status')) {
        $local_posts = poke_hub_events_get_local_posts_by_status($status, $args);
        if (is_array($local_posts)) {
            foreach ($local_posts as $ev) {
                $events[] = $ev;
            }
        }
    }

    // Note: Les special events locaux sont déjà inclus dans poke_hub_events_get_by_status
    // (via poke_hub_special_events_query), donc pas besoin de les ajouter à nouveau ici.

    // 3) Special events distants
    if (function_exists('poke_hub_special_events_get_remote')) {
        $special_remote = poke_hub_special_events_get_remote($status, $args);
        if (is_array($special_remote)) {
            foreach ($special_remote as $ev) {
                $events[] = $ev;
            }
        }
    }

    return $events;
}