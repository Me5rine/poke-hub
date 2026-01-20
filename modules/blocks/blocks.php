<?php
// modules/blocks/blocks.php

if (!defined('ABSPATH')) {
    exit;
}

// Si le module "blocks" n'est pas activé dans la config globale, on sort.
if (!function_exists('poke_hub_is_module_active') || !poke_hub_is_module_active('blocks')) {
    return;
}

/**
 * Constantes de chemin / URL du module Blocks
 */
define('POKE_HUB_BLOCKS_PATH', __DIR__);
define('POKE_HUB_BLOCKS_URL', POKE_HUB_URL . 'modules/blocks/');

/**
 * Chargement des fonctionnalités du module Blocks
 */
require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-register.php';   // Enregistrement des blocs
require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-helpers.php';    // Helpers pour les blocs
// Debug file is optional - only load if needed for troubleshooting
// Uncomment the line below if you need to debug block registration:
// require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-debug.php';

/**
 * Enregistre la catégorie de blocs Poké HUB
 */
function pokehub_register_block_category($categories, $editor_context) {
    if (!empty($editor_context->post)) {
        array_unshift(
            $categories,
            [
                'slug' => 'pokehub',
                'title' => __('Poké HUB', 'poke-hub'),
                'icon' => null,
            ]
        );
    }
    return $categories;
}
add_filter('block_categories_all', 'pokehub_register_block_category', 10, 2);

