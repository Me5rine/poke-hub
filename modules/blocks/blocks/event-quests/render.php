<?php
/**
 * Rendu du bloc "Quêtes d'événement"
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
    error_log('[POKEHUB] event-quests render.php appelé');
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

// Vérifier que les fonctions sont disponibles
if (!function_exists('pokehub_get_event_quests') || !function_exists('pokehub_render_event_quests')) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[POKEHUB] event-quests: Fonctions non disponibles - get_quests=' . (int) function_exists('pokehub_get_event_quests') . ', render=' . (int) function_exists('pokehub_render_event_quests'));
    }
    return '';
}

// Si auto-détection activée, récupérer depuis les meta
if ($auto_detect) {
    $quests = pokehub_get_event_quests($post_id);
} else {
    // Pour l'instant, on ne supporte que l'auto-détection
    $quests = [];
}

if (empty($quests)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[POKEHUB] event-quests: Aucune quête trouvée pour post_id=' . $post_id . ' (auto_detect=' . ($auto_detect ? '1' : '0') . ')');
    }
    return '';
}

// Récupérer le HTML du rendu
$quests_html = pokehub_render_event_quests($quests);

if (empty($quests_html)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[POKEHUB] event-quests: HTML vide pour post_id=' . $post_id);
    }
    return '';
}

// Wrapper avec les attributs du bloc
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-event-quests-block-wrapper']);

// Construire le HTML directement sans buffer pour éviter les conflits
$output = '<div ' . $wrapper_attributes . '>';
$output .= '<h2 class="pokehub-block-title">' . esc_html__('Field Research', 'poke-hub') . '</h2>';
$output .= $quests_html;
$output .= '</div>';

if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[POKEHUB] event-quests: HTML généré, longueur=' . strlen($output) . ' pour post_id=' . $post_id);
}

return $output;








