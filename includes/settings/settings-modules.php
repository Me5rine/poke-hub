<?php
// File: includes/settings/settings-modules.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Liste des modules disponibles dans Poké HUB.
 *
 * Slug => chemin relatif depuis modules/
 *
 * Exemple d’utilisation :
 *   $registry = poke_hub_get_modules_registry();
 *   $pokedex_file = POKE_HUB_MODULES_DIR . $registry['pokedex'];
 *
 * @return array<string,string>
 */
function poke_hub_get_modules_registry(): array {
    return [
        'events'  => 'events/events.php',
        'bonus'   => 'bonus/bonus.php',
        'pokemon' => 'pokemon/pokemon.php',
    ];
}
