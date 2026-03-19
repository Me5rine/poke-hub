<?php
// includes/functions/pokehub-events-public-helpers.php
//
// Helpers "publics" liés aux events, chargés même si le module Events est désactivé.
// Objectif : éviter toute dépendance fatale des autres modules (Blocks, Content, etc.)
// à l'activation du module Events.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère les timestamps de début et fin d'un événement depuis les meta d'un post.
 * Fallback global (utilisé si le module Events est inactif).
 *
 * Formats gérés :
 * - mode "fixed" : _event_sort_start / _event_sort_end (timestamps)
 * - mode "local" : _event_start_local / _event_end_local (datetime-local dans le timezone du site)
 *
 * @return array{start_ts:int|null, end_ts:int|null}
 */
if (!function_exists('poke_hub_events_get_post_dates')) {
    function poke_hub_events_get_post_dates(int $post_id): array {
        $mode = get_post_meta($post_id, '_event_mode', true);
        $mode = ($mode === 'local') ? 'local' : 'fixed';

        if ($mode === 'local') {
            $start_local = (string) get_post_meta($post_id, '_event_start_local', true);
            $end_local   = (string) get_post_meta($post_id, '_event_end_local', true);

            if ($start_local !== '' && $end_local !== '') {
                try {
                    $tz = wp_timezone();
                    $dt_start = new DateTime($start_local, $tz);
                    $dt_end   = new DateTime($end_local, $tz);
                    return [
                        'start_ts' => (int) $dt_start->getTimestamp(),
                        'end_ts'   => (int) $dt_end->getTimestamp(),
                    ];
                } catch (Exception $e) {
                    return ['start_ts' => null, 'end_ts' => null];
                }
            }
        } else {
            $start_ts = get_post_meta($post_id, '_event_sort_start', true);
            $end_ts   = get_post_meta($post_id, '_event_sort_end', true);

            if ($start_ts && $end_ts) {
                return [
                    'start_ts' => (int) $start_ts,
                    'end_ts'   => (int) $end_ts,
                ];
            }
        }

        // Compat : certains sites ajoutent leurs propres formats
        $custom_dates = apply_filters('pokehub_events_get_custom_dates', null, $post_id);
        if (is_array($custom_dates) && isset($custom_dates['start'], $custom_dates['end'])) {
            $start = $custom_dates['start'];
            $end   = $custom_dates['end'];
            $start_ts = is_numeric($start) ? (int) $start : (int) strtotime((string) $start);
            $end_ts   = is_numeric($end) ? (int) $end : (int) strtotime((string) $end);
            if ($start_ts > 0 && $end_ts > 0) {
                return ['start_ts' => $start_ts, 'end_ts' => $end_ts];
            }
        }

        return ['start_ts' => null, 'end_ts' => null];
    }
}

/**
 * Rendu des dates d'événement (HTML) avec classes attendues côté CSS.
 * Fallback global si le module Events est inactif.
 */
if (!function_exists('pokehub_render_event_dates')) {
    function pokehub_render_event_dates($start_ts, $end_ts) {
        $start_ts = (int) $start_ts;
        $end_ts   = (int) $end_ts;
        if ($start_ts <= 0 || $end_ts <= 0) {
            return '';
        }

        $start_label = date_i18n('D j M H:i', $start_ts);
        $end_label   = date_i18n('D j M H:i', $end_ts);

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
}

/**
 * Quêtes d'événement (source = post) via tables de contenu.
 * Exposée globalement pour les blocs et compat.
 */
if (!function_exists('pokehub_get_event_quests')) {
    function pokehub_get_event_quests(int $post_id): array {
        if (function_exists('pokehub_content_get_quests')) {
            $quests = pokehub_content_get_quests('post', $post_id);
            return is_array($quests) ? $quests : [];
        }
        return [];
    }
}

/**
 * Pokémon sauvages d'un post via tables de contenu.
 * Format: [ ['pokemon_id'=>int,'is_rare'=>bool,'force_shiny'=>bool,'gender'=>?string], ... ]
 */
if (!function_exists('pokehub_get_wild_pokemon')) {
    function pokehub_get_wild_pokemon(int $post_id): array {
        if (function_exists('pokehub_content_get_wild_pokemon')) {
            $wild = pokehub_content_get_wild_pokemon('post', $post_id);
            return is_array($wild) ? $wild : [];
        }
        return [];
    }
}

