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

$post_id = get_the_ID();
if (!$post_id) {
    return '';
}

$auto_detect = $attributes['autoDetect'] ?? true;
$layout = $attributes['layout'] ?? 'cards';
$bonus_ids = $attributes['bonusIds'] ?? [];

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
?>
<div <?php echo $wrapper_attributes; ?>>
    <?php echo $bonuses_html; ?>
</div>

