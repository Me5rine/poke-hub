<?php
// File: includes/settings/settings-modules.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Source unique d'enregistrement des modules Poké HUB.
 * Tout le plugin s'appuie sur cette liste : réglages (General), chargement, sanitize.
 * Pour ajouter un module : ajouter une entrée ici (path + label). Aucun autre fichier à modifier.
 *
 * @return array<string,array{path: string, label: string}>
 */
function poke_hub_get_modules_config(): array {
    return [
        'events'        => ['path' => 'events/events.php',        'label' => __('Events', 'poke-hub')],
        'bonus'         => ['path' => 'bonus/bonus.php',          'label' => __('Bonus', 'poke-hub')],
        'pokemon'       => ['path' => 'pokemon/pokemon.php',      'label' => __('Pokémon', 'poke-hub')],
        'quests'        => ['path' => 'quests/quests.php',       'label' => __('Quests', 'poke-hub')],
        'user-profiles' => ['path' => 'user-profiles/user-profiles.php', 'label' => __('User Profiles', 'poke-hub')],
        'games'         => ['path' => 'games/games.php',         'label' => __('Games', 'poke-hub')],
        'eggs'          => ['path' => 'eggs/eggs.php',            'label' => __('Eggs', 'poke-hub')],
        'blocks'        => ['path' => 'blocks/blocks.php',        'label' => __('Blocks', 'poke-hub')],
        'collections'   => ['path' => 'collections/collections.php', 'label' => __('Pokémon GO Collections', 'poke-hub')],
    ];
}

/**
 * Registre des modules : slug => chemin relatif depuis modules/.
 * Dérivé de poke_hub_get_modules_config().
 *
 * @return array<string,string>
 */
function poke_hub_get_modules_registry(): array {
    $config = poke_hub_get_modules_config();
    $out = [];
    foreach ($config as $slug => $data) {
        $out[$slug] = $data['path'];
    }
    return $out;
}

/**
 * Libellés des modules : slug => libellé affiché.
 * Dérivé de poke_hub_get_modules_config().
 *
 * @return array<string,string>
 */
function poke_hub_get_modules_labels(): array {
    $config = poke_hub_get_modules_config();
    $out = [];
    foreach ($config as $slug => $data) {
        $out[$slug] = $data['label'];
    }
    return $out;
}

/**
 * Liste ordonnée des slugs (même ordre que la config).
 * Utile pour l’affichage (onglet General) et les boucles.
 *
 * @return array<int,string>
 */
function poke_hub_get_ordered_module_slugs(): array {
    return array_keys(poke_hub_get_modules_config());
}
