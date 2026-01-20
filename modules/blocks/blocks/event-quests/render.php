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

$post_id = get_the_ID();
if (!$post_id) {
    return '';
}

$auto_detect = $attributes['autoDetect'] ?? true;

// Si auto-détection activée, récupérer depuis les meta
if ($auto_detect) {
    $quests = pokehub_get_event_quests($post_id);
} else {
    // Pour l'instant, on ne supporte que l'auto-détection
    $quests = [];
}

if (empty($quests)) {
    return '';
}

// Récupérer le HTML du rendu
$quests_html = pokehub_render_event_quests($quests);

if (empty($quests_html)) {
    return '';
}

// Wrapper avec les attributs du bloc
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-event-quests-block-wrapper']);
?>
<div <?php echo $wrapper_attributes; ?>>
    <?php echo $quests_html; ?>
</div>




