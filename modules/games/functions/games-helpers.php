<?php
// modules/games/functions/games-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère tous les Pokémon disponibles pour le jeu (formes normales uniquement)
 * 
 * @return array
 */
function poke_hub_games_get_all_pokemon(): array {
    global $wpdb;

    $pokemon_table = pokehub_get_table('pokemon');
    if (!$pokemon_table) {
        return [];
    }

    // Récupérer uniquement les formes par défaut pour éviter les doublons
    $rows = $wpdb->get_results(
        "SELECT id, dex_number, name_fr, name_en, slug
         FROM {$pokemon_table}
         WHERE is_default = 1
         ORDER BY dex_number ASC",
        ARRAY_A
    );

    return $rows ?: [];
}

/**
 * Récupère un Pokémon par son ID
 * 
 * @param int $pokemon_id
 * @return array|null
 */
function poke_hub_games_get_pokemon_by_id(int $pokemon_id): ?array {
    global $wpdb;

    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return null;
    }

    $pokemon_table = pokehub_get_table('pokemon');
    if (!$pokemon_table) {
        return null;
    }

    $pokemon = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$pokemon_table} WHERE id = %d LIMIT 1",
            $pokemon_id
        ),
        ARRAY_A
    );

    return $pokemon ?: null;
}

/**
 * Récupère les types d'un Pokémon
 * 
 * @param int $pokemon_id
 * @return array
 */
function poke_hub_games_get_pokemon_types(int $pokemon_id): array {
    global $wpdb;

    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return [];
    }

    $types_table = pokehub_get_table('pokemon_types');
    $type_links_table = pokehub_get_table('pokemon_type_links');
    
    if (!$types_table || !$type_links_table) {
        return [];
    }

    $types = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.id, t.slug, t.name_fr, t.name_en, t.color
             FROM {$types_table} t
             INNER JOIN {$type_links_table} ptl ON t.id = ptl.type_id
             WHERE ptl.pokemon_id = %d
             ORDER BY ptl.slot ASC",
            $pokemon_id
        ),
        ARRAY_A
    );

    return $types ?: [];
}

/**
 * Récupère la génération d'un Pokémon
 * 
 * @param int $pokemon_id
 * @return array|null
 */
function poke_hub_games_get_pokemon_generation(int $pokemon_id): ?array {
    global $wpdb;

    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return null;
    }

    $pokemon_table = pokehub_get_table('pokemon');
    $gens_table = pokehub_get_table('generations');
    
    if (!$pokemon_table || !$gens_table) {
        return null;
    }

    $gen = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT g.id, g.slug, g.name_fr, g.name_en, g.generation_number
             FROM {$gens_table} g
             INNER JOIN {$pokemon_table} p ON g.id = p.generation_id
             WHERE p.id = %d
             LIMIT 1",
            $pokemon_id
        ),
        ARRAY_A
    );

    return $gen ?: null;
}

/**
 * Récupère toutes les générations disponibles
 * 
 * @return array
 */
function poke_hub_games_get_all_generations(): array {
    global $wpdb;

    $gens_table = pokehub_get_table('generations');
    if (!$gens_table) {
        return [];
    }

    $gens = $wpdb->get_results(
        "SELECT id, slug, name_fr, name_en, generation_number
         FROM {$gens_table}
         ORDER BY generation_number ASC",
        ARRAY_A
    );

    return $gens ?: [];
}

/**
 * Récupère une génération par son slug ou ID
 * 
 * @param string|int $slug_or_id
 * @return array|null
 */
function poke_hub_games_get_generation_by_slug_or_id($slug_or_id): ?array {
    global $wpdb;

    $gens_table = pokehub_get_table('generations');
    if (!$gens_table) {
        return null;
    }

    if (is_numeric($slug_or_id)) {
        $gen = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, slug, name_fr, name_en, generation_number
                 FROM {$gens_table}
                 WHERE id = %d
                 LIMIT 1",
                (int) $slug_or_id
            ),
            ARRAY_A
        );
    } else {
        $gen = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, slug, name_fr, name_en, generation_number
                 FROM {$gens_table}
                 WHERE slug = %s
                 LIMIT 1",
                sanitize_text_field($slug_or_id)
            ),
            ARRAY_A
        );
    }

    return $gen ?: null;
}

/**
 * Récupère tous les Pokémon d'une génération spécifique
 * 
 * @param int $generation_id
 * @return array
 */
function poke_hub_games_get_pokemon_by_generation(int $generation_id): array {
    global $wpdb;

    $generation_id = (int) $generation_id;
    if ($generation_id <= 0) {
        return [];
    }

    $pokemon_table = pokehub_get_table('pokemon');
    if (!$pokemon_table) {
        return [];
    }

    // Récupérer uniquement les formes par défaut pour éviter les doublons
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, dex_number, name_fr, name_en, slug
             FROM {$pokemon_table}
             WHERE is_default = 1 AND generation_id = %d
             ORDER BY dex_number ASC",
            $generation_id
        ),
        ARRAY_A
    );

    return $rows ?: [];
}

/**
 * Récupère la hauteur d'un Pokémon (en mètres)
 * 
 * @param int $pokemon_id
 * @return float|null
 */
function poke_hub_games_get_pokemon_height(int $pokemon_id): ?float {
    $pokemon = poke_hub_games_get_pokemon_by_id($pokemon_id);
    if (!$pokemon || empty($pokemon['extra'])) {
        return null;
    }

    $extra = json_decode($pokemon['extra'], true);
    if (!is_array($extra)) {
        return null;
    }

    // Chercher dans games.pokemon_go.pokedex.height_m d'abord
    if (!empty($extra['games']['pokemon_go']['pokedex']['height_m'])) {
        return (float) $extra['games']['pokemon_go']['pokedex']['height_m'];
    }

    // Sinon chercher dans pokedex.height_m
    if (!empty($extra['pokedex']['height_m'])) {
        return (float) $extra['pokedex']['height_m'];
    }

    return null;
}

/**
 * Récupère le poids d'un Pokémon (en kg)
 * 
 * @param int $pokemon_id
 * @return float|null
 */
function poke_hub_games_get_pokemon_weight(int $pokemon_id): ?float {
    $pokemon = poke_hub_games_get_pokemon_by_id($pokemon_id);
    if (!$pokemon || empty($pokemon['extra'])) {
        return null;
    }

    $extra = json_decode($pokemon['extra'], true);
    if (!is_array($extra)) {
        return null;
    }

    // Chercher dans games.pokemon_go.pokedex.weight_kg d'abord
    if (!empty($extra['games']['pokemon_go']['pokedex']['weight_kg'])) {
        return (float) $extra['games']['pokemon_go']['pokedex']['weight_kg'];
    }

    // Sinon chercher dans pokedex.weight_kg
    if (!empty($extra['pokedex']['weight_kg'])) {
        return (float) $extra['pokedex']['weight_kg'];
    }

    return null;
}

/**
 * Détermine le stade d'évolution d'un Pokémon
 * 1 = Première forme (forme de base, la plus basse de la chaîne)
 * 2 = Deuxième forme (première évolution)
 * 3 = Troisième forme (seconde évolution)
 * etc.
 * 
 * @param int $pokemon_id
 * @return int|null
 */
function poke_hub_games_get_pokemon_evolution_stage(int $pokemon_id): ?int {
    global $wpdb;

    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return null;
    }

    $evolutions_table = pokehub_get_table('pokemon_evolutions');
    if (!$evolutions_table) {
        return 1; // Si pas de table, considérer comme stade 1 (base)
    }

    // Compter combien d'évolutions il faut pour arriver à ce Pokémon depuis la base
    // Le stade = nombre d'évolutions + 1 (1 = base, 2 = première évolution, etc.)
    $evolution_count = 0;
    $current_pokemon_id = $pokemon_id;
    $visited = []; // Pour éviter les boucles infinies

    while ($current_pokemon_id && !in_array($current_pokemon_id, $visited, true)) {
        $visited[] = $current_pokemon_id;

        // Chercher si ce Pokémon évolue depuis un autre
        $base_pokemon = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT base_pokemon_id 
                 FROM {$evolutions_table}
                 WHERE target_pokemon_id = %d
                 LIMIT 1",
                $current_pokemon_id
            )
        );

        if ($base_pokemon) {
            $evolution_count++;
            $current_pokemon_id = (int) $base_pokemon;
        } else {
            break; // On a atteint la base
        }
    }

    // Le stade = nombre d'évolutions + 1
    // Exemple : Bulbizarre (0 évolutions) = stade 1, Pikachu (1 évolution depuis Pichu) = stade 2
    return $evolution_count + 1;
}

