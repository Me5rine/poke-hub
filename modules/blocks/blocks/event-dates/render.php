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

// Si auto-détection activée, récupérer depuis les meta via le helper centralisé
if ($auto_detect) {
    $dates = function_exists('poke_hub_events_get_post_dates')
        ? poke_hub_events_get_post_dates($post_id)
        : ['start_ts' => null, 'end_ts' => null];
    
    if (!$dates['start_ts'] || !$dates['end_ts']) {
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
$dates_html = function_exists('pokehub_render_event_dates')
    ? pokehub_render_event_dates($start_ts, $end_ts)
    : '';

if (empty($dates_html)) {
    return '';
}

// Wrapper avec les attributs du bloc
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-event-dates-block-wrapper']);

// Construire le HTML directement sans buffer pour éviter les conflits
$output = '<div ' . $wrapper_attributes . '>';
$output .= '<h2 class="pokehub-block-title">' . esc_html__('Date', 'poke-hub') . '</h2>';
$output .= $dates_html;
$output .= '</div>';

return $output;

