<?php
// modules/blocks/functions/blocks-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper pour vérifier si un bloc est disponible
 */
function pokehub_block_is_available($block_name) {
    if (!function_exists('WP_Block_Type_Registry')) {
        return false;
    }
    
    $registry = WP_Block_Type_Registry::get_instance();
    return $registry->is_registered($block_name);
}

/**
 * Helper pour obtenir la liste des blocs disponibles
 */
function pokehub_get_available_blocks() {
    if (!function_exists('WP_Block_Type_Registry')) {
        return [];
    }
    
    $registry = WP_Block_Type_Registry::get_instance();
    $all_blocks = $registry->get_all_registered();
    
    // Filtrer uniquement les blocs Poké HUB
    $pokehub_blocks = [];
    foreach ($all_blocks as $name => $block) {
        if (strpos($name, 'pokehub/') === 0) {
            $pokehub_blocks[$name] = $block;
        }
    }
    
    return $pokehub_blocks;
}




