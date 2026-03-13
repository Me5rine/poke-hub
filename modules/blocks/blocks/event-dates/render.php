<?php
/**
 * Rendu du bloc "Dates d'événement"
 *
 * @var array    $attributes Les attributs du bloc.
 * @var string   $content    Le contenu HTML du bloc.
 * @var WP_Block $block      L'instance du bloc.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Debug : vérifier si le render est appelé
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[POKEHUB] event-dates render.php appelé');
}

// Récupération robuste du post_id (compatible Elementor et autres contextes)
$post_id = 0;

// 1) Contexte Gutenberg (fiable même hors loop)
if (isset($block) && is_object($block) && !empty($block->context['postId'])) {
    $post_id = (int) $block->context['postId'];
}

// 2) Fallback loop
if (!$post_id) {
    $post_id = (int) get_the_ID();
}

// 3) Fallback requête courante
if (!$post_id) {
    $post_id = (int) get_queried_object_id();
}

// 4) Fallback global
if (!$post_id && !empty($GLOBALS['post']->ID)) {
    $post_id = (int) $GLOBALS['post']->ID;
}

if (!$post_id) {
    return '';
}

$auto_detect = $attributes['autoDetect'] ?? true;
$start_date = $attributes['startDate'] ?? '';
$end_date = $attributes['endDate'] ?? '';

// Vérifier que les fonctions sont disponibles
if (!function_exists('poke_hub_events_get_post_dates') || !function_exists('pokehub_render_event_dates')) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[POKEHUB] event-dates: Fonctions non disponibles - get_dates=' . (int) function_exists('poke_hub_events_get_post_dates') . ', render=' . (int) function_exists('pokehub_render_event_dates'));
    }
    return '';
}

// Si auto-détection activée, récupérer depuis les meta via le helper centralisé
if ($auto_detect) {
    $dates = poke_hub_events_get_post_dates($post_id);
    
    if (!$dates['start_ts'] || !$dates['end_ts']) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[POKEHUB] event-dates: Aucune date trouvée pour post_id=' . $post_id . ' (start_ts=' . ($dates['start_ts'] ?? 'null') . ', end_ts=' . ($dates['end_ts'] ?? 'null') . ')');
        }
        return '';
    }
    
    $start_ts = $dates['start_ts'];
    $end_ts = $dates['end_ts'];
} else {
    // Utiliser les dates fournies dans les attributs
    if (empty($start_date) || empty($end_date)) {
        return '';
    }

    $start_ts = strtotime($start_date);
    $end_ts = strtotime($end_date);

    if (!$start_ts || !$end_ts) {
        return '';
    }
}

// Récupérer le HTML du rendu
$dates_html = pokehub_render_event_dates($start_ts, $end_ts);

if (empty($dates_html)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[POKEHUB] event-dates: HTML vide pour post_id=' . $post_id . ' (start_ts=' . $start_ts . ', end_ts=' . $end_ts . ')');
    }
    return '';
}

// Wrapper avec les attributs du bloc
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-event-dates-block-wrapper']);

// Construire le HTML directement sans buffer pour éviter les conflits
$output = '<div ' . $wrapper_attributes . '>';
$output .= '<h2 class="pokehub-block-title">' . esc_html__('Date', 'poke-hub') . '</h2>';
$output .= $dates_html;
$output .= '</div>';

if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[POKEHUB] event-dates: HTML généré, longueur=' . strlen($output) . ' pour post_id=' . $post_id);
    if (empty($output)) {
        error_log('[POKEHUB] event-dates: ATTENTION - output est vide');
    }
}

return $output;

