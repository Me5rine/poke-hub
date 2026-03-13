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
        'events'        => 'events/events.php',
        'bonus'         => 'bonus/bonus.php',
        'pokemon'       => 'pokemon/pokemon.php',
        'user-profiles' => 'user-profiles/user-profiles.php',
        'games'         => 'games/games.php',
        'eggs'          => 'eggs/eggs.php',
        'blocks'        => 'blocks/blocks.php',
        'collections'   => 'collections/collections.php',
    ];
}

/**
 * Libellés des modules (pour l'affichage dans les réglages).
 * Doit être synchronisé avec le registry : un module dans le registry doit avoir un libellé ici.
 *
 * @return array<string,string>
 */
function poke_hub_get_modules_labels(): array {
    return [
        'events'        => __('Events', 'poke-hub'),
        'bonus'         => __('Bonus', 'poke-hub'),
        'pokemon'       => __('Pokémon', 'poke-hub'),
        'user-profiles' => __('User Profiles', 'poke-hub'),
        'games'         => __('Games', 'poke-hub'),
        'eggs'          => __('Eggs', 'poke-hub'),
        'blocks'        => __('Blocks', 'poke-hub'),
        'collections'   => __('Collections Pokémon GO', 'poke-hub'),
    ];
}
