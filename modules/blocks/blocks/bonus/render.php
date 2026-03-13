<?php
/**
 * Rendu du bloc "Bonus"
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
    error_log('[POKEHUB] bonus render.php appelé');
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
$layout = $attributes['layout'] ?? 'cards';
$bonus_ids = $attributes['bonusIds'] ?? [];

// Vérifier que les fonctions sont disponibles
if (!function_exists('pokehub_get_bonuses_for_post') || !function_exists('pokehub_get_bonus_data') || !function_exists('pokehub_render_bonuses_visual')) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[POKEHUB] bonus: Fonctions non disponibles - get_bonuses=' . (int) function_exists('pokehub_get_bonuses_for_post') . ', get_data=' . (int) function_exists('pokehub_get_bonus_data') . ', render=' . (int) function_exists('pokehub_render_bonuses_visual'));
    }
    return '';
}

// Si auto-détection activée, récupérer depuis les meta
if ($auto_detect) {
    $bonuses = pokehub_get_bonuses_for_post($post_id);
} else {
    // Utiliser les IDs fournis dans les attributs
    if (empty($bonus_ids)) {
        return '';
    }

    $bonuses = [];
    foreach ($bonus_ids as $bonus_id) {
        $bonus = pokehub_get_bonus_data((int) $bonus_id);
        if ($bonus) {
            $bonuses[] = $bonus;
        }
    }
}

if (empty($bonuses)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[POKEHUB] bonus: Aucun bonus trouvé pour post_id=' . $post_id . ' (auto_detect=' . ($auto_detect ? '1' : '0') . ')');
    }
    return '';
}

// Récupérer le HTML du rendu
$bonuses_html = pokehub_render_bonuses_visual($bonuses, $layout);

if (empty($bonuses_html)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[POKEHUB] bonus: HTML vide pour post_id=' . $post_id);
    }
    return '';
}

// Wrapper avec les attributs du bloc
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-bonus-block-wrapper']);

// Construire le HTML directement sans buffer pour éviter les conflits
$output = '<div ' . $wrapper_attributes . '>';
$output .= '<h2 class="pokehub-block-title">' . esc_html__('Bonus', 'poke-hub') . '</h2>';
$output .= $bonuses_html;
$output .= '</div>';

if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[POKEHUB] bonus: HTML généré, longueur=' . strlen($output) . ' pour post_id=' . $post_id);
}

return $output;

