<?php
// modules/bonus/bonus.php

if (!defined('ABSPATH')) {
    exit;
}

// Si le module "bonus" n'est pas activé dans ta config globale, on sort.
if (!function_exists('poke_hub_is_module_active') || !poke_hub_is_module_active('bonus')) {
    return;
}

/**
 * Constantes de chemin / URL du module Bonus
 */
define('POKE_HUB_BONUS_PATH', __DIR__);
define('POKE_HUB_BONUS_URL', POKE_HUB_URL . 'modules/bonus/');

/**
 * Chargement des fonctionnalités du module Bonus
 * (à créer si ce n'est pas déjà fait)
 */
require_once POKE_HUB_BONUS_PATH . '/functions/bonus-helpers.php';   // Helpers de récupération et rendu visuel
require_once POKE_HUB_BONUS_PATH . '/functions/bonus-shortcodes.php'; // Shortcodes
if (is_admin()) {
    require_once POKE_HUB_BONUS_PATH . '/admin/bonus-types-admin.php';
}
// Metabox bonus : chargée par le module Blocks uniquement (bloc bonus ne dépend pas du module Bonus)

/**
 * Assets front pour l'affichage des bonus.
 */
function poke_hub_bonus_assets() {
    poke_hub_enqueue_bundled_front_style('pokehub-bonus-style', 'poke-hub-bonus-front.css', []);
}
add_action('wp_enqueue_scripts', 'poke_hub_bonus_assets');