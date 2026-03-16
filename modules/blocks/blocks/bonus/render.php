<?php
/**
 * Rendu du bloc "Bonus"
 * Fonctionne en local et en remote selon le préfixe des sources Pokémon.
 * Les helpers sont chargés ici si le module Bonus n'est pas activé.
 *
 * @var array    $attributes Les attributs du bloc.
 * @var string   $content    Le contenu HTML du bloc.
 * @var WP_Block $block      L'instance du bloc.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Charger les helpers bonus si le module Bonus n'est pas actif (bloc utilisable sans le module)
if (!function_exists('pokehub_render_bonuses_visual')) {
    $bonus_helpers = defined('POKE_HUB_PATH') ? POKE_HUB_PATH . 'modules/bonus/functions/bonus-helpers.php' : '';
    if ($bonus_helpers && file_exists($bonus_helpers)) {
        require_once $bonus_helpers;
    }
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

// Vérifier que les fonctions sont disponibles (table bonus locale ou distante selon préfixe)
if (!function_exists('pokehub_get_bonuses_for_post') || !function_exists('pokehub_get_bonus_data') || !function_exists('pokehub_render_bonuses_visual')) {
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
    return '';
}

// Récupérer le HTML du rendu
$bonuses_html = pokehub_render_bonuses_visual($bonuses, $layout);

if (empty($bonuses_html)) {
    return '';
}

// Wrapper avec les attributs du bloc
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-bonus-block-wrapper']);

// Construire le HTML directement sans buffer pour éviter les conflits
$output = '<div ' . $wrapper_attributes . '>';
$output .= '<h2 class="pokehub-block-title">' . esc_html__('Bonus', 'poke-hub') . '</h2>';
$output .= $bonuses_html;
$output .= '</div>';

return $output;

