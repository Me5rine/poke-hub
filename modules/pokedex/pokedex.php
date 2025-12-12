<?php
// File: modules/pokedex/pokedex.php

if (!defined('ABSPATH')) {
    exit;
}

// Double sécurité : ne rien faire si le module n'est pas marqué comme actif
if (!poke_hub_is_module_active('pokedex')) {
    return;
}

/**
 * Ici on mettra :
 * - enregistrement du CPT pour les Pokémon (si besoin)
 * - taxonomies (types, régions, générations...)
 * - shortcodes / endpoints / REST API pour le Pokédex
 */

// Exemple de hook futur
add_action('init', function () {
    // TODO: register_post_type('pokemon', [...]);
});
