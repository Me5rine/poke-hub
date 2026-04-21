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
        // IMPORTANT : ne pas appeler __() ici (ce fichier est utilisé tôt, ex: plugins_loaded)
        // sinon WordPress déclenche _load_textdomain_just_in_time "too early".
        // La traduction est appliquée plus tard dans poke_hub_get_modules_labels().
        'events'        => ['path' => 'events/events.php',        'label' => 'Events'],
        'bonus'         => ['path' => 'bonus/bonus.php',          'label' => 'Bonus'],
        'pokemon'       => ['path' => 'pokemon/pokemon.php',      'label' => 'Pokémon'],
        'quests'        => ['path' => 'quests/quests.php',        'label' => 'Quests'],
        'user-profiles' => ['path' => 'user-profiles/user-profiles.php', 'label' => 'User Profiles'],
        'games'         => ['path' => 'games/games.php',          'label' => 'Games'],
        'eggs'          => ['path' => 'eggs/eggs.php',            'label' => 'Eggs'],
        'blocks'        => ['path' => 'blocks/blocks.php',        'label' => 'Blocks'],
        'collections'   => ['path' => 'collections/collections.php', 'label' => 'Pokémon GO Collections'],
        'shop-items'      => ['path' => 'shop-items/shop-items.php', 'label' => 'Avatar shop items'],
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
        $label = isset($data['label']) ? (string) $data['label'] : '';
        $out[$slug] = $label !== '' ? __($label, 'poke-hub') : '';
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
