<?php
// modules/pokemon/includes/pokemon-images-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * URL de base principale des assets Pokémon (définie dans les settings).
 */
function poke_hub_pokemon_get_assets_base_url() {
    $opt = trim((string) get_option('poke_hub_pokemon_assets_base_url', ''));
    if ($opt !== '') {
        return rtrim($opt, '/');
    }

    if (defined('POKE_HUB_POKEMON_ASSETS_BASE_URL')) {
        return rtrim(POKE_HUB_POKEMON_ASSETS_BASE_URL, '/');
    }

    return '';
}

/**
 * URL de base fallback des assets Pokémon.
 */
function poke_hub_pokemon_get_assets_fallback_base_url() {
    $opt = trim((string) get_option('poke_hub_pokemon_assets_fallback_base_url', ''));
    if ($opt !== '') {
        return rtrim($opt, '/');
    }

    if (defined('POKE_HUB_POKEMON_ASSETS_FALLBACK_BASE_URL')) {
        return rtrim(POKE_HUB_POKEMON_ASSETS_FALLBACK_BASE_URL, '/');
    }

    // Pas de fallback configuré
    return '';
}

/**
 * Construit la "clé" d'image à partir du slug + shiny + genre.
 */
function poke_hub_pokemon_build_image_key_from_slug($slug, array $args = []) {
    $args = wp_parse_args($args, [
        'shiny'  => false,
        'gender' => null,
    ]);

    $slug = sanitize_title($slug);
    $key  = $slug;

    // Genre
    if ($args['gender'] === 'male') {
        if (!preg_match('/-male(?:-|$)/', $key)) {
            $key .= '-male';
        }
    } elseif ($args['gender'] === 'female') {
        if (!preg_match('/-female(?:-|$)/', $key)) {
            $key .= '-female';
        }
    }

    // Shiny
    if (!empty($args['shiny'])) {
        if (!preg_match('/-shiny(?:-|$)/', $key)) {
            $key .= '-shiny';
        }
    }

    return $key;
}

/**
 * Version simple : ne renvoie que l'URL principale (string).
 */
function poke_hub_pokemon_get_image_url($pokemon, array $args = []) {
    $sources = poke_hub_pokemon_get_image_sources($pokemon, $args);

    return $sources['primary'];
}

/**
 * Version complète : renvoie primary + fallback (même pattern slug).
 *
 * @return array {
 *   'primary'  => string, // peut être '' si pas de base url
 *   'fallback' => string, // peut être '' si pas configuré
 * }
 */
function poke_hub_pokemon_get_image_sources($pokemon, array $args = []) {
    $args = wp_parse_args($args, [
        'shiny'   => false,
        'gender'  => null,
        'variant' => 'sprite', // si un jour tu veux des sous-dossiers
    ]);

    $base_url     = poke_hub_pokemon_get_assets_base_url();
    $fallback_url = poke_hub_pokemon_get_assets_fallback_base_url();

    $slug = isset($pokemon->slug) ? $pokemon->slug : '';
    if ($slug === '') {
        $slug = sprintf('%03d', (int) $pokemon->dex_number);
    }

    $key  = poke_hub_pokemon_build_image_key_from_slug($slug, $args);

    // Si tu rajoutes des sous-dossiers par variant, adapte ici :
    // $path = 'sprites/' . $key . '.png';
    $path = $key . '.png';

    $primary  = '';
    $fallback = '';

    if ($base_url !== '') {
        $primary = $base_url . '/' . ltrim($path, '/');
    }
    if ($fallback_url !== '') {
        $fallback = $fallback_url . '/' . ltrim($path, '/');
    }

    $sources = [
        'primary'  => $primary,
        'fallback' => $fallback,
    ];

    /**
     * Filtre si tu veux personnaliser pour certains Pokémon / formes.
     */
    return apply_filters('poke_hub_pokemon_image_sources', $sources, $pokemon, $args);
}