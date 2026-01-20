<?php
// File: modules/events/includes/events-render.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Format a duration (future) as "X days Y hours Z mins".
 *
 * @param int $seconds
 * @return string
 */
function poke_hub_events_format_duration_dhm(int $seconds): string {
    $seconds = max(0, $seconds);

    $days    = intdiv($seconds, DAY_IN_SECONDS);
    $seconds = $seconds % DAY_IN_SECONDS;

    $hours   = intdiv($seconds, HOUR_IN_SECONDS);
    $seconds = $seconds % HOUR_IN_SECONDS;

    $minutes = intdiv($seconds, MINUTE_IN_SECONDS);

    $parts = [];

    if ($days > 0) {
        $parts[] = sprintf(
            _n('%d day', '%d days', $days, 'poke-hub'),
            $days
        );
    }

    if ($hours > 0) {
        $parts[] = sprintf(
            _n('%d hour', '%d hours', $hours, 'poke-hub'),
            $hours
        );
    }

    if ($minutes > 0 || !$parts) {
        // Toujours afficher au moins les minutes
        $parts[] = sprintf(
            _n('%d min', '%d mins', $minutes, 'poke-hub'),
            $minutes
        );
    }

    return implode(' ', $parts);
}

/**
 * Format a past duration as:
 * - "X days"   if < 1 month
 * - "X months" if >= 1 month and < 12 months
 * - "X years"  if >= 12 months
 *
 * @param int $seconds
 * @return string
 */
function poke_hub_events_format_past_since(int $seconds): string {
    $seconds = max(0, $seconds);
    $days    = (int) floor($seconds / DAY_IN_SECONDS);

    // Toujours au moins 1 jour si c'est passé
    if ($days < 1) {
        $days = 1;
    }

    if ($days < 30) {
        return sprintf(
            _n('%d day', '%d days', $days, 'poke-hub'),
            $days
        );
    }

    // 1 month ~ 30 days
    if ($days < 365) {
        $months = max(1, (int) floor($days / 30));
        return sprintf(
            _n('%d month', '%d months', $months, 'poke-hub'),
            $months
        );
    }

    // 1 year ~ 365 days
    $years = max(1, (int) floor($days / 365));
    return sprintf(
        _n('%d year', '%d years', $years, 'poke-hub'),
        $years
    );
}

/**
 * Formate un timestamp d'événement selon le fuseau du site (et la locale WP).
 *
 * @param int $timestamp
 * @param string $format
 * @return string
 */
function poke_hub_events_format_datetime(int $timestamp, string $format = 'd M Y H:i'): string {
    return wp_date($format, $timestamp, wp_timezone());
}

/**
 * Render a single JV Actu event card.
 *
 * @param object $event Object returned by poke_hub_events_get_by_status()
 *                      Propriétés attendues :
 *                      - title
 *                      - start_ts / end_ts
 *                      - status (current|upcoming|past)
 *                      - event_type_name (optionnel)
 *                      - image_url (optionnel)
 *                      - image_id  (optionnel)
 */
function poke_hub_events_render_event(object $event): void {

    $start = $event->start_ts;
    $end   = $event->end_ts;

    $image_url = property_exists($event, 'image_url') ? (string) $event->image_url : '';
    $color     = !empty($event->event_type_color) ? $event->event_type_color : '#880051';

    // URL vers l'événement
    $link = '';
    
    // Pour les événements spéciaux locaux, utiliser l'URL locale
    if (!empty($event->source) && ($event->source === 'special_local' || $event->source === 'special')) {
        if (!empty($event->slug) && function_exists('poke_hub_special_event_get_url')) {
            $link = poke_hub_special_event_get_url($event->slug);
        }
    }
    // Pour les événements spéciaux remote, utiliser remote_url s'il existe
    elseif (!empty($event->source) && $event->source === 'special_remote') {
        if (!empty($event->remote_url)) {
            $link = $event->remote_url;
        } elseif (!empty($event->slug) && function_exists('poke_hub_special_event_get_url')) {
            // Fallback : utiliser l'URL locale si pas de remote_url
            $link = poke_hub_special_event_get_url($event->slug);
        }
    }
    // Pour les autres événements (posts), utiliser remote_url
    elseif (!empty($event->remote_url)) {
        $link = $event->remote_url;
    }

    $event_type_name = !empty($event->event_type_name) ? $event->event_type_name : '';

    // Utiliser time() au lieu de current_time('timestamp') car les timestamps Unix
    // (start_ts, end_ts) sont toujours en UTC et doivent être comparés avec time() (UTC)
    $now         = time();
    $time_label  = '';
    $time_status = $event->status ?? 'current';

    if ($time_status === 'current') {
        $diff = $end - $now;
        if ($diff > 0) {
            $time_label = sprintf(
                esc_html__('Ends in %s', 'poke-hub'),
                poke_hub_events_format_duration_dhm($diff)
            );
        } else {
            $time_label = esc_html__('Ended', 'poke-hub');
        }
    } elseif ($time_status === 'upcoming') {
        $diff = $start - $now;
        if ($diff > 0) {
            $time_label = sprintf(
                esc_html__('Starts in %s', 'poke-hub'),
                poke_hub_events_format_duration_dhm($diff)
            );
        } else {
            $time_label = esc_html__('Starting soon', 'poke-hub');
        }
    } else {
        $diff  = $now - $end;
        $since = poke_hub_events_format_past_since($diff);
        $time_label = sprintf(
            esc_html__('Ended since %s', 'poke-hub'),
            $since
        );
    }

    // Helpers simples pour découper la date en utilisant le helper central
    $start_day_short   = poke_hub_events_format_datetime($start, 'D');
    $start_day_num     = poke_hub_events_format_datetime($start, 'j');
    $start_month_short = poke_hub_events_format_datetime($start, 'M');
    $start_time        = poke_hub_events_format_datetime($start, 'H:i');

    $end_day_short     = poke_hub_events_format_datetime($end, 'D');
    $end_day_num       = poke_hub_events_format_datetime($end, 'j');
    $end_month_short   = poke_hub_events_format_datetime($end, 'M');
    $end_time          = poke_hub_events_format_datetime($end, 'H:i');

    $start_label = sprintf('%s %s %s %s',
        $start_day_short,
        $start_day_num,
        $start_month_short,
        $start_time
    );

    $end_label = sprintf('%s %s %s %s',
        $end_day_short,
        $end_day_num,
        $end_month_short,
        $end_time
    );

    ?>
    <div class="pokehub-event-card pokehub-event-status-<?php echo esc_attr($event->status); ?>">

        <?php
        $wrapper_tag   = $link ? 'a' : 'div';
        $wrapper_attrs = $link
            ? 'href="' . esc_url($link) . '" rel="noopener"'
            : '';
        ?>

        <<?php echo $wrapper_tag; ?>
            <?php echo $wrapper_attrs; ?>
            class="pokehub-event-card-inner"
            style="--event-color: <?php echo esc_attr($color); ?>;<?php echo $image_url ? ' --event-image: url(' . esc_url($image_url) . ');' : ''; ?>"
        >

            <div class="event-inner-left">
                <?php if ($event_type_name): ?>
                    <span class="event-type-badge">
                        <?php echo esc_html($event_type_name); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="event-inner-center">
                <h3 class="event-title">
                    <?php echo esc_html($event->title); ?>
                </h3>

                <div class="event-dates-row">
                    <div class="event-date-chip event-date-chip--start">
                        <span class="event-date-dot event-date-dot--start"></span>
                        <span class="event-date-text"><?php echo esc_html($start_label); ?></span>
                    </div>

                    <span class="event-date-middle">···</span>

                    <div class="event-date-chip event-date-chip--end">
                        <span class="event-date-dot event-date-dot--end"></span>
                        <span class="event-date-text"><?php echo esc_html($end_label); ?></span>
                    </div>
                </div>
            </div>

            <div class="event-inner-right">
                <span class="event-status-label event-status-label--<?php echo esc_attr($event->status); ?>">
                    <?php
                    echo match ($event->status) {
                        'current'  => esc_html__('Ongoing', 'poke-hub'),
                        'upcoming' => esc_html__('Upcoming', 'poke-hub'),
                        'past'     => esc_html__('Ended', 'poke-hub'),
                        default    => esc_html__('Event', 'poke-hub'),
                    };
                    ?>
                </span>

                <?php if ($time_label): ?>
                    <span class="event-time-remaining">
                        <?php echo esc_html($time_label); ?>
                    </span>
                <?php endif; ?>

                <?php if ($link): ?>
                    <span class="event-more-button">
                        <?php esc_html_e('See more', 'poke-hub'); ?>
                    </span>
                <?php endif; ?>
            </div>

        </<?php echo $wrapper_tag; ?>>

    </div>
    <?php
}

/**
 * Render a list of events.
 *
 * @param array $events
 */
function poke_hub_events_render_list(array $events): void {

    echo '<div class="pokehub-events-grid">';

    if (!$events) {
        echo '<p>' . esc_html__('No events found.', 'poke-hub') . '</p>';
    } else {
        foreach ($events as $event) {
            poke_hub_events_render_event($event);
        }
    }

    // Script de mise à jour des compteurs toutes les 60s
    ?>
<script>
(function() {
    // Timestamp serveur (PHP)
    var serverNow = <?php echo time(); ?>;
    var clientNow = Math.floor(Date.now() / 1000);
    var offset    = serverNow - clientNow;

    // Libellés traduits côté PHP
    var labelCurrentTpl     = <?php echo json_encode(esc_html__('Ends in %s', 'poke-hub')); ?>;
    var labelEnded          = <?php echo json_encode(esc_html__('Ended', 'poke-hub')); ?>;
    var labelUpcomingTpl    = <?php echo json_encode(esc_html__('Starts in %s', 'poke-hub')); ?>;
    var labelSoon           = <?php echo json_encode(esc_html__('Starting soon', 'poke-hub')); ?>;
    var labelEndedSinceTpl  = <?php echo json_encode(esc_html__('Ended since %s', 'poke-hub')); ?>;

    // Meta events envoyés par PHP dans l'ordre d'affichage
    var eventsMeta = [
        <?php foreach ($events as $event): ?>
        {
            start: <?php echo (int) $event->start_ts; ?>,
            end:   <?php echo (int) $event->end_ts; ?>,
            status: <?php echo json_encode($event->status ?? 'current'); ?>
        },
        <?php endforeach; ?>
    ];

    function formatDuration(seconds) {
        seconds = Math.max(0, seconds);

        var days   = Math.floor(seconds / 86400);
        seconds   %= 86400;
        var hours  = Math.floor(seconds / 3600);
        seconds   %= 3600;
        var minutes = Math.floor(seconds / 60);

        var parts = [];
        if (days > 0)  parts.push(days + 'd');
        if (hours > 0) parts.push(hours + 'h');
        if (minutes > 0 || !parts.length) parts.push(minutes + 'min');

        return parts.join(' ');
    }

    function formatPast(seconds) {
        seconds = Math.max(0, seconds);
        var days = Math.floor(seconds / 86400);
        if (days < 1) days = 1;

        if (days < 30) {
            return days + 'd';
        }
        if (days < 365) {
            var months = Math.max(1, Math.floor(days / 30));
            return months + 'm';
        }
        var years = Math.max(1, Math.floor(days / 365));
        return years + 'y';
    }

    // On se limite au conteneur qui contient CE script
    var scriptEl  = document.currentScript;
    var container = scriptEl ? scriptEl.parentElement : document;

    function refreshEventTimers() {
        var now   = Math.floor(Date.now() / 1000) + offset;
        var cards = container.querySelectorAll('.pokehub-event-card');

        cards.forEach(function(card, idx) {
            var el = card.querySelector('.event-time-remaining');
            if (!el) return;

            var meta = eventsMeta[idx];
            if (!meta) return;

            var start  = meta.start;
            var end    = meta.end;
            var status;

            if (now < start) {
                status = 'upcoming';
            } else if (now > end) {
                status = 'past';
            } else {
                status = 'current';
            }
            meta.status = status;

            var label = '';

            if (status === 'current') {
                var diff = end - now;
                if (diff > 0) {
                    label = labelCurrentTpl.replace('%s', formatDuration(diff));
                } else {
                    label = labelEnded;
                }
            } else if (status === 'upcoming') {
                var diff2 = start - now;
                if (diff2 > 0) {
                    label = labelUpcomingTpl.replace('%s', formatDuration(diff2));
                } else {
                    label = labelSoon;
                }
            } else { // past
                var diff3 = now - end;
                label = labelEndedSinceTpl.replace('%s', formatPast(diff3));
            }

            el.textContent = label;
        });
    }

    // Premier calcul immédiat
    refreshEventTimers();
    // Puis toutes les 60s
    setInterval(refreshEventTimers, 60000);
})();
</script>

    <?php

    echo '</div>';
}

/**
 * Rendu des dates d'événement avec feux verts/rouges
 * 
 * @param int $start_ts Timestamp de début
 * @param int $end_ts Timestamp de fin
 * @return string HTML
 */
function pokehub_render_event_dates($start_ts, $end_ts) {
    if (!$start_ts || !$end_ts) {
        return '';
    }

    // Formatage des dates
    $start_day_short = poke_hub_events_format_datetime($start_ts, 'D');
    $start_day_num = poke_hub_events_format_datetime($start_ts, 'j');
    $start_month_short = poke_hub_events_format_datetime($start_ts, 'M');
    $start_time = poke_hub_events_format_datetime($start_ts, 'H:i');

    $end_day_short = poke_hub_events_format_datetime($end_ts, 'D');
    $end_day_num = poke_hub_events_format_datetime($end_ts, 'j');
    $end_month_short = poke_hub_events_format_datetime($end_ts, 'M');
    $end_time = poke_hub_events_format_datetime($end_ts, 'H:i');

    $start_label = sprintf(
        '%s %s %s %s',
        $start_day_short,
        $start_day_num,
        $start_month_short,
        $start_time
    );

    $end_label = sprintf(
        '%s %s %s %s',
        $end_day_short,
        $end_day_num,
        $end_month_short,
        $end_time
    );

    ob_start();
    ?>
    <div class="pokehub-event-dates-block">
        <div class="event-dates-row">
            <div class="event-date-chip event-date-chip--start">
                <span class="event-date-dot event-date-dot--start"></span>
                <span class="event-date-text"><?php echo esc_html($start_label); ?></span>
            </div>

            <span class="event-date-middle">···</span>

            <div class="event-date-chip event-date-chip--end">
                <span class="event-date-dot event-date-dot--end"></span>
                <span class="event-date-text"><?php echo esc_html($end_label); ?></span>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Ajoute automatiquement les dates d'événement au début du contenu
 * pour certains post types (ex: post, pokehub_event).
 */
function pokehub_events_append_dates_to_content($content) {
    static $in_dates_filter = false;

    // Si on est déjà en train de traiter les dates, on ne refait rien
    if ($in_dates_filter) {
        return $content;
    }

    // Pas dans l'admin ou les feeds
    if (is_admin() || is_feed()) {
        return $content;
    }

    if (!in_the_loop() || !is_main_query()) {
        return $content;
    }

    global $post;
    if (!$post) {
        return $content;
    }

    $post_type = get_post_type($post);

    // Post types sur lesquels on active l'injection auto
    $allowed_post_types = apply_filters('pokehub_events_dates_auto_post_types', [
        'post',
        'pokehub_event',
    ]);

    if (!in_array($post_type, $allowed_post_types, true)) {
        return $content;
    }

    // Vérifier si les dates existent via le helper centralisé
    $dates = poke_hub_events_get_post_dates($post->ID);
    
    if (!$dates['start_ts'] || !$dates['end_ts']) {
        return $content;
    }
    
    $start_ts = $dates['start_ts'];
    $end_ts = $dates['end_ts'];

    $in_dates_filter = true;
    $dates_html = pokehub_render_event_dates($start_ts, $end_ts);
    $in_dates_filter = false;

    if (empty($dates_html)) {
        return $content;
    }

    // Ajout au début du contenu
    return $dates_html . $content;
}
add_filter('the_content', 'pokehub_events_append_dates_to_content', 10);