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

$post_id = get_the_ID();
if (!$post_id) {
    return '';
}

$auto_detect = $attributes['autoDetect'] ?? true;
$start_date = $attributes['startDate'] ?? '';
$end_date = $attributes['endDate'] ?? '';

// Si auto-détection activée, récupérer depuis les meta via le helper centralisé
if ($auto_detect) {
    $dates = poke_hub_events_get_post_dates($post_id);
    
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
$dates_html = pokehub_render_event_dates($start_ts, $end_ts);

if (empty($dates_html)) {
    return '';
}

// Wrapper avec les attributs du bloc
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-event-dates-block-wrapper']);
?>
<div <?php echo $wrapper_attributes; ?>>
    <?php echo $dates_html; ?>
</div>

