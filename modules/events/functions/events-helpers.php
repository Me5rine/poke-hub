<?php
// File: modules/events/functions/events-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

function poke_hub_event_get_meta($post_id, $key, $default = null) {
    $val = get_post_meta($post_id, $key, true);
    return ($val !== '' && $val !== null) ? $val : $default;
}

function poke_hub_event_get_status($post_id) {
    // Utiliser time() au lieu de current_time('timestamp') car les timestamps Unix
    // sont toujours en UTC et doivent être comparés avec time() (UTC)
    $now   = (int) time();
    $start = (int) poke_hub_event_get_meta($post_id, '_admin_lab_event_start', 0);
    $end   = (int) poke_hub_event_get_meta($post_id, '_admin_lab_event_end', 0);

    if (!$start || !$end) {
        return 'past';
    }

    if ($start <= $now && $end >= $now) {
        return 'current';
    }
    if ($start > $now) {
        return 'upcoming';
    }

    return 'past';
}

function poke_hub_event_get_duration($post_id) {
    $start = (int) poke_hub_event_get_meta($post_id, '_admin_lab_event_start', 0);
    $end   = (int) poke_hub_event_get_meta($post_id, '_admin_lab_event_end', 0);

    if (!$start || !$end || $end <= $start) {
        return '';
    }

    return human_time_diff($start, $end);
}

/**
 * Détermine le statut de l'événement (current / upcoming / past)
 * à partir des timestamps.
 */
function poke_hub_events_compute_status(int $start_ts, int $end_ts): string {
    // Utiliser time() au lieu de current_time('timestamp') car les timestamps Unix
    // sont toujours en UTC et doivent être comparés avec time() (UTC)
    $now = (int) time();

    if ($start_ts <= $now && $end_ts >= $now) {
        return 'current';
    }
    if ($start_ts > $now) {
        return 'upcoming';
    }
    return 'past';
}

if (!function_exists('poke_hub_special_event_parse_datetime')) {
    /**
     * Convertit un champ <input type="datetime-local"> en timestamp,
     * selon le mode :
     *
     * - local : interprété dans le fuseau du site (wp_timezone())
     * - fixed : interprété en UTC (instant global)
     *
     * @param string $raw  ex: "2025-12-31T23:00"
     * @param string $mode 'local'|'fixed'
     * @return int timestamp Unix ou 0 en cas d’erreur
     */
    function poke_hub_special_event_parse_datetime(string $raw, string $mode = 'local'): int {
        $raw  = trim($raw);
        $mode = ($mode === 'fixed') ? 'fixed' : 'local';

        if ($raw === '') {
            return 0;
        }

        try {
            if ($mode === 'local') {
                // Interprété comme heure “murale” du site
                $tz = wp_timezone();
            } else {
                // Interprété comme heure UTC
                $tz = new DateTimeZone('UTC');
            }

            // Le format de datetime-local est typiquement "Y-m-d\TH:i"
            $dt = new DateTime($raw, $tz);

            return $dt->getTimestamp();
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('poke_hub_special_event_format_datetime')) {
    /**
     * Convertit un timestamp en format datetime-local pour affichage,
     * selon le mode :
     *
     * - local : convertit le timestamp vers le timezone du site (wp_timezone())
     * - fixed : convertit le timestamp vers UTC
     *
     * @param int    $timestamp Timestamp Unix
     * @param string $mode      'local'|'fixed'
     * @return string Format "Y-m-d\TH:i" ou '' si erreur
     */
    function poke_hub_special_event_format_datetime(int $timestamp, string $mode = 'local'): string {
        if ($timestamp <= 0) {
            return '';
        }

        $mode = ($mode === 'fixed') ? 'fixed' : 'local';

        try {
            if ($mode === 'local') {
                // Convertir le timestamp vers le timezone du site
                $tz = wp_timezone();
                $dt = new DateTime('@' . $timestamp);
                $dt->setTimezone($tz);
            } else {
                // Convertir le timestamp vers UTC
                $tz = new DateTimeZone('UTC');
                $dt = new DateTime('@' . $timestamp);
                $dt->setTimezone($tz);
            }

            return $dt->format('Y-m-d\TH:i');
        } catch (Exception $e) {
            return '';
        }
    }
}
