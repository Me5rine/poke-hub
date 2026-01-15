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
require_once POKE_HUB_BONUS_PATH . '/functions/bonus-cpt.php';       // CPT pokehub_bonus
require_once POKE_HUB_BONUS_PATH . '/functions/bonus-helpers.php';   // Helpers de récupération
require_once POKE_HUB_BONUS_PATH . '/functions/bonus-shortcodes.php';   // Helpers de récupération
require_once POKE_HUB_BONUS_PATH . '/admin/bonus-metabox.php';       // Metabox sur posts/events
// require_once POKE_HUB_BONUS_PATH . '/public/shortcode-bonus.php'; // (plus tard, si besoin)

/**
 * Assets front pour l'affichage des bonus.
 * Optionnel pour l'instant, mais prêt à être utilisé.
 */
function poke_hub_bonus_assets() {
    // CSS des blocs de bonus (vérifier l'existence du fichier avant de l'enregistrer)
    $css_file = POKE_HUB_PATH . 'assets/css/poke-hub-bonus-front.css';
    if (file_exists($css_file)) {
        wp_enqueue_style(
            'pokehub-bonus-style',
            POKE_HUB_URL . 'assets/css/poke-hub-bonus-front.css',
            [],
            POKE_HUB_VERSION
        );
    }
}
add_action('wp_enqueue_scripts', 'poke_hub_bonus_assets');
