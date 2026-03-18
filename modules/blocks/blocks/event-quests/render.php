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

// Récupération des quêtes : priorité aux tables de contenu (indépendant du module events)
$quests = [];
if ($auto_detect) {
    if (function_exists('pokehub_content_get_quests')) {
        $quests = pokehub_content_get_quests('post', (int) $post_id);
    } elseif (function_exists('pokehub_get_event_quests')) {
        // Fallback compat (si le module events est actif)
        $quests = pokehub_get_event_quests((int) $post_id);
    }
}
if (!is_array($quests)) {
    $quests = [];
}

if (empty($quests)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[POKEHUB] event-quests: Aucune quête trouvée pour post_id=' . $post_id . ' (auto_detect=' . ($auto_detect ? '1' : '0') . ')');
    }
    return '';
}

// Récupérer le HTML du rendu (events si dispo, sinon fallback Blocks)
$quests_html = '';
if (function_exists('pokehub_render_event_quests')) {
    $quests_html = pokehub_render_event_quests($quests);
} elseif (function_exists('pokehub_blocks_render_event_quests')) {
    $quests_html = pokehub_blocks_render_event_quests($quests);
}

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








