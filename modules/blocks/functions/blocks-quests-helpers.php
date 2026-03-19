<?php
// modules/blocks/functions/blocks-quests-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère les quêtes d'un post (tables de contenu).
 *
 * @param int $post_id ID du post
 * @return array Tableau de quêtes
 */
function pokehub_blocks_get_event_quests(int $post_id): array {
    $quests = function_exists('pokehub_get_event_quests') ? pokehub_get_event_quests($post_id) : [];
    return is_array($quests) ? $quests : [];
}
