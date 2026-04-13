<?php
/**
 * Sauvegarde commune de la ligne special_events depuis le formulaire (champs event[...]).
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Insère ou met à jour un événement spécial à partir de $_POST['event'].
 *
 * @param string|null $force_event_type Si défini (ex. go-pass), remplace event[event_type].
 * @return int|string ID de l'événement ou message d'erreur (wp_die attendu par l'appelant).
 */
function pokehub_special_events_save_row_from_post(?string $force_event_type = null) {
    global $wpdb;

    $events_table = pokehub_get_table('special_events');
    $event_id     = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

    $event = isset($_POST['event']) && is_array($_POST['event']) ? wp_unslash($_POST['event']) : [];

    $title_en = isset($event['title_en']) ? sanitize_text_field($event['title_en']) : '';
    $title_fr = isset($event['title_fr']) ? sanitize_text_field($event['title_fr']) : '';
    $title    = $title_en;

    $image_id  = !empty($event['image_id']) ? absint($event['image_id']) : 0;
    $image_url = !empty($event['image_url']) ? esc_url_raw($event['image_url']) : '';

    $raw_slug = '';
    if (!empty($event['slug'])) {
        $raw_slug = (string) $event['slug'];
    } elseif (!empty($title_en)) {
        $raw_slug = $title_en;
    }

    if ($raw_slug === '') {
        return __('A title (EN) or slug is required to generate the event slug.', 'poke-hub');
    }

    $slug = pokehub_generate_unique_event_slug($raw_slug, $event_id);

    if ($force_event_type !== null && $force_event_type !== '') {
        $event_type = sanitize_title($force_event_type);
    } else {
        $event_type = isset($event['event_type']) ? sanitize_title($event['event_type']) : '';
    }

    if ($force_event_type !== null && $force_event_type !== '' && function_exists('pokehub_go_pass_event_type_slug')
        && sanitize_title((string) $force_event_type) === pokehub_go_pass_event_type_slug()) {
        $event['recurring']                  = '';
        $event['recurring_window_end']       = '';
        $event['recurring_window_end_date']  = '';
        $event['recurring_window_end_time'] = '';
    }

    $description = isset($event['description']) ? wp_kses_post($event['description']) : '';

    $mode = isset($event['mode']) ? sanitize_key($event['mode']) : 'local';
    if (!in_array($mode, ['local', 'fixed'], true)) {
        $mode = 'local';
    }
    $mode_for_dates = ($mode === 'fixed') ? 'fixed' : 'local';

    $start_ts = 0;
    $end_ts   = 0;

    if (function_exists('poke_hub_special_event_parse_date_time_for_save')) {
        $sd = isset($event['start_date']) ? trim((string) $event['start_date']) : '';
        $st = isset($event['start_time']) ? trim((string) $event['start_time']) : '';
        if ($sd !== '') {
            $start_ts = poke_hub_special_event_parse_date_time_for_save($sd, $st, $mode_for_dates);
        }
        $ed = isset($event['end_date']) ? trim((string) $event['end_date']) : '';
        $et = isset($event['end_time']) ? trim((string) $event['end_time']) : '';
        if ($ed !== '') {
            $end_ts = poke_hub_special_event_parse_date_time_for_save($ed, $et, $mode_for_dates);
        }
    }

    $start_raw = isset($event['start']) ? trim((string) $event['start']) : '';
    $end_raw   = isset($event['end']) ? trim((string) $event['end']) : '';
    if ($start_ts === 0 && $start_raw !== '' && function_exists('poke_hub_special_event_parse_datetime')) {
        $start_ts = poke_hub_special_event_parse_datetime($start_raw, $mode_for_dates);
    }
    if ($end_ts === 0 && $end_raw !== '' && function_exists('poke_hub_special_event_parse_datetime')) {
        $end_ts = poke_hub_special_event_parse_datetime($end_raw, $mode_for_dates);
    }

    $recurring = !empty($event['recurring']) ? 1 : 0;

    $recurring_freq = isset($event['recurring_freq']) ? sanitize_key($event['recurring_freq']) : 'weekly';
    if (!in_array($recurring_freq, ['daily', 'weekly', 'monthly'], true)) {
        $recurring_freq = 'weekly';
    }

    $recurring_interval = isset($event['recurring_interval']) ? (int) $event['recurring_interval'] : 1;
    if ($recurring_interval < 1) {
        $recurring_interval = 1;
    }

    $recurring_window_end_ts = 0;
    if ($recurring) {
        $rwd = isset($event['recurring_window_end_date']) ? trim((string) $event['recurring_window_end_date']) : '';
        $rwt = isset($event['recurring_window_end_time']) ? trim((string) $event['recurring_window_end_time']) : '';
        if ($rwd !== '' && function_exists('poke_hub_special_event_parse_date_time_for_save')) {
            $recurring_window_end_ts = poke_hub_special_event_parse_date_time_for_save($rwd, $rwt, $mode_for_dates);
        }
        $recurring_window_raw = isset($event['recurring_window_end']) ? trim((string) $event['recurring_window_end']) : '';
        if ($recurring_window_end_ts === 0 && $recurring_window_raw !== '' && function_exists('poke_hub_special_event_parse_datetime')) {
            $recurring_window_end_ts = poke_hub_special_event_parse_datetime($recurring_window_raw, $mode_for_dates);
        }
    }

    if (!$title_en || !$title_fr || !$slug || !$event_type || !$start_ts || !$end_ts) {
        return __('Missing required fields.', 'poke-hub');
    }

    $existing_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$events_table} WHERE slug = %s",
            $slug
        )
    );

    if ($existing_id && (int) $existing_id !== $event_id) {
        return __('This slug is already used by another event.', 'poke-hub');
    }

    $remote_posts = pokehub_get_table('remote_posts');

    $remote_slug_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ID FROM {$remote_posts} WHERE post_name = %s LIMIT 1",
            $slug
        )
    );

    if ($remote_slug_exists) {
        return __('This slug is already used by a remote event.', 'poke-hub');
    }

    $data = [
        'slug'                    => $slug,
        'title'                   => $title,
        'title_en'                => $title_en,
        'title_fr'                => $title_fr,
        'description'             => $description,
        'event_type'              => $event_type,
        'start_ts'                => $start_ts,
        'end_ts'                  => $end_ts,
        'mode'                    => $mode,
        'recurring'               => $recurring,
        'recurring_freq'          => $recurring_freq,
        'recurring_interval'      => $recurring_interval,
        'recurring_window_end_ts' => $recurring_window_end_ts,
        'image_id'                => $image_id ?: null,
        'image_url'               => $image_id ? '' : $image_url,
    ];

    $formats = [
        '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%d', '%d', '%s',
    ];

    if ($event_id > 0) {
        $wpdb->update(
            $events_table,
            $data,
            ['id' => $event_id],
            $formats,
            ['%d']
        );
    } else {
        $wpdb->insert(
            $events_table,
            $data,
            $formats
        );
        $event_id = (int) $wpdb->insert_id;
    }

    if ($event_id <= 0) {
        return __('Could not save event.', 'poke-hub');
    }

    return $event_id;
}

/**
 * Après sauvegarde : purge cache + redirection liste événements.
 *
 * @param int  $event_id ID final.
 * @param bool $was_new  true si création.
 */
function pokehub_special_events_redirect_after_save(int $event_id, bool $was_new): void {
    if (function_exists('poke_hub_purge_module_cache')) {
        poke_hub_purge_module_cache(
            ['poke_hub_events'],
            'poke_hub_events',
            'poke_hub_events_all'
        );
    }

    $redirect_args = [
        'page' => 'poke-hub-events',
    ];

    if ($was_new) {
        $redirect_args['added'] = 1;
    } else {
        $redirect_args['updated'] = 1;
    }

    $preserve_params = ['event_status', 'event_source', 'event_type', 's', 'paged', 'orderby', 'order'];

    foreach ($preserve_params as $param) {
        if (isset($_POST[$param]) && $_POST[$param] !== '') {
            if ($param === 's') {
                $redirect_args[$param] = sanitize_text_field(wp_unslash((string) $_POST[$param]));
            } elseif (in_array($param, ['paged', 'orderby', 'order'], true)) {
                $redirect_args[$param] = sanitize_key((string) $_POST[$param]);
            } else {
                $redirect_args[$param] = sanitize_text_field(wp_unslash((string) $_POST[$param]));
            }
        }
    }

    $referer = wp_get_referer();
    if ($referer) {
        $referer_parsed = parse_url($referer);
        if (!empty($referer_parsed['query'])) {
            parse_str((string) $referer_parsed['query'], $referer_params);
            foreach ($preserve_params as $param) {
                if (isset($redirect_args[$param])) {
                    continue;
                }
                if (isset($referer_params[$param]) && $referer_params[$param] !== '') {
                    if ($param === 's') {
                        $redirect_args[$param] = sanitize_text_field((string) $referer_params[$param]);
                    } elseif (in_array($param, ['paged', 'orderby', 'order'], true)) {
                        $redirect_args[$param] = sanitize_key((string) $referer_params[$param]);
                    } else {
                        $redirect_args[$param] = sanitize_text_field((string) $referer_params[$param]);
                    }
                }
            }
        }
    }

    $redirect_args = array_filter(
        $redirect_args,
        static function ($value, $key) {
            if ($value === '' || $value === null || $value === '-1') {
                return false;
            }
            if (strpos((string) $key, '_wp') === 0 || $key === 'action' || $key === 'action2' || $key === 'filter_action') {
                return false;
            }
            return true;
        },
        ARRAY_FILTER_USE_BOTH
    );

    wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
    exit;
}
