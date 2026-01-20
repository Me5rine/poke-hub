<?php
// modules/games/functions/games-pokedle-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère le Pokémon du jour pour le pokedle
 * Le Pokémon est sélectionné de manière déterministe basée sur la date
 * En mode dev (option activée), utilise un timestamp pour réinitialiser à chaque refresh
 * 
 * @param string|null $date Date au format Y-m-d, null pour aujourd'hui
 * @return array|null
 */
function poke_hub_pokedle_get_daily_pokemon(?string $date = null): ?array {
    $all_pokemon = poke_hub_games_get_all_pokemon();
    return poke_hub_pokedle_get_daily_pokemon_from_list($all_pokemon, $date);
}

/**
 * Récupère le Pokémon du jour depuis une liste spécifique
 * 
 * @param array $pokemon_list Liste de Pokémon disponibles
 * @param string|null $date Date au format Y-m-d, null pour aujourd'hui
 * @return array|null
 */
function poke_hub_pokedle_get_daily_pokemon_from_list(array $pokemon_list, ?string $date = null): ?array {
    if (empty($pokemon_list)) {
        return null;
    }

    // Vérifier si le mode dev est activé dans les réglages
    $is_dev_mode = (bool) get_option('poke_hub_games_dev_mode', false);
    
    if ($is_dev_mode) {
        // En mode dev, utiliser le timestamp actuel (change à chaque refresh)
        $timestamp = time();
    } else {
        // En production, utiliser la date
        if ($date === null) {
            $date = current_time('Y-m-d');
        }
        $timestamp = strtotime($date);
    }

    // Utiliser le timestamp comme seed pour un générateur pseudo-aléatoire
    mt_srand($timestamp);

    // Sélectionner un Pokémon de manière déterministe
    $index = mt_rand(0, count($pokemon_list) - 1);
    $pokemon = $pokemon_list[$index];

    // Réinitialiser le seed pour ne pas affecter d'autres parties du code
    mt_srand();

    return $pokemon;
}

/**
 * Compare un Pokémon deviné avec le Pokémon mystère et retourne les indices
 * 
 * @param int $guessed_pokemon_id ID du Pokémon deviné
 * @param int $mystery_pokemon_id ID du Pokémon mystère
 * @return array Indices de comparaison
 */
function poke_hub_pokedle_compare_pokemon(int $guessed_pokemon_id, int $mystery_pokemon_id): array {
    $guessed = poke_hub_games_get_pokemon_by_id($guessed_pokemon_id);
    $mystery = poke_hub_games_get_pokemon_by_id($mystery_pokemon_id);

    if (!$guessed || !$mystery) {
        return [
            'is_correct' => false,
            'hints' => []
        ];
    }

    $hints = [];

    // Nom du Pokémon
    $hints['pokemon'] = $guessed['name_fr'] ?: $guessed['name_en'];

    // Type 1 et Type 2 avec détection des types mal placés
    $guessed_types = poke_hub_games_get_pokemon_types($guessed_pokemon_id);
    $mystery_types = poke_hub_games_get_pokemon_types($mystery_pokemon_id);
    
    // Créer des tableaux d'IDs pour faciliter la comparaison
    $guessed_type_ids = [];
    $mystery_type_ids = [];
    $mystery_all_type_ids = []; // Tous les IDs de types du mystère (pour détecter les mal placés)
    
    if (!empty($guessed_types[0])) {
        $guessed_type_ids[0] = $guessed_types[0]['id'];
    }
    if (!empty($guessed_types[1])) {
        $guessed_type_ids[1] = $guessed_types[1]['id'];
    }
    
    if (!empty($mystery_types[0])) {
        $mystery_type_ids[0] = $mystery_types[0]['id'];
        $mystery_all_type_ids[] = $mystery_types[0]['id'];
    }
    if (!empty($mystery_types[1])) {
        $mystery_type_ids[1] = $mystery_types[1]['id'];
        $mystery_all_type_ids[] = $mystery_types[1]['id'];
    }
    
    $hints['type1'] = 'none';
    $hints['type2'] = 'none';
    
    // Type 1
    if (!empty($guessed_types[0])) {
        $hints['type1_name'] = $guessed_types[0]['name_fr'] ?: $guessed_types[0]['name_en'];
        $guessed_type1_id = $guessed_type_ids[0];
        
        if (!empty($mystery_types[0]) && $guessed_type1_id === $mystery_type_ids[0]) {
            // Type 1 correct (même position)
            $hints['type1'] = 'correct';
        } elseif (in_array($guessed_type1_id, $mystery_all_type_ids, true)) {
            // Type présent dans les types du mystère mais pas à la bonne position (mal placé)
            $hints['type1'] = 'misplaced';
        } else {
            // Type incorrect ou absent
            $hints['type1'] = 'wrong';
        }
    }
    
    // Type 2
    if (!empty($guessed_types[1])) {
        $hints['type2_name'] = $guessed_types[1]['name_fr'] ?: $guessed_types[1]['name_en'];
        $guessed_type2_id = $guessed_type_ids[1];
        
        if (!empty($mystery_types[1]) && $guessed_type2_id === $mystery_type_ids[1]) {
            // Type 2 correct (même position)
            $hints['type2'] = 'correct';
        } elseif (in_array($guessed_type2_id, $mystery_all_type_ids, true)) {
            // Type présent dans les types du mystère mais pas à la bonne position (mal placé)
            $hints['type2'] = 'misplaced';
        } else {
            // Type incorrect
            $hints['type2'] = 'wrong';
        }
    } elseif (empty($guessed_types[1]) && !empty($mystery_types[1])) {
        // Le Pokémon deviné n'a pas de Type 2 mais le mystère en a un
        $hints['type2'] = 'missing';
    }

    // ATK
    $atk_diff = $guessed['base_atk'] - $mystery['base_atk'];
    $hints['atk'] = $atk_diff === 0 ? 'correct' : ($atk_diff > 0 ? 'higher' : 'lower');
    $hints['atk_value'] = $guessed['base_atk'];

    // PV (STA)
    $sta_diff = $guessed['base_sta'] - $mystery['base_sta'];
    $hints['pv'] = $sta_diff === 0 ? 'correct' : ($sta_diff > 0 ? 'higher' : 'lower');
    $hints['pv_value'] = $guessed['base_sta'];

    // Defense
    $def_diff = $guessed['base_def'] - $mystery['base_def'];
    $hints['defense'] = $def_diff === 0 ? 'correct' : ($def_diff > 0 ? 'higher' : 'lower');
    $hints['defense_value'] = $guessed['base_def'];

    // Stade d'évolution
    $guessed_stage = poke_hub_games_get_pokemon_evolution_stage($guessed_pokemon_id);
    $mystery_stage = poke_hub_games_get_pokemon_evolution_stage($mystery_pokemon_id);
    
    if ($guessed_stage !== null && $mystery_stage !== null) {
        if ($guessed_stage === $mystery_stage) {
            $hints['evolution_stage'] = 'correct';
        } elseif ($guessed_stage > $mystery_stage) {
            $hints['evolution_stage'] = 'higher';
        } else {
            $hints['evolution_stage'] = 'lower';
        }
        $hints['evolution_stage_value'] = $guessed_stage;
    }

    // Hauteur
    $guessed_height = poke_hub_games_get_pokemon_height($guessed_pokemon_id);
    $mystery_height = poke_hub_games_get_pokemon_height($mystery_pokemon_id);
    
    if ($guessed_height !== null && $mystery_height !== null) {
        $height_diff = $guessed_height - $mystery_height;
        $hints['height'] = abs($height_diff) < 0.01 ? 'correct' : ($height_diff > 0 ? 'higher' : 'lower');
        $hints['height_value'] = $guessed_height;
    }

    // Poids
    $guessed_weight = poke_hub_games_get_pokemon_weight($guessed_pokemon_id);
    $mystery_weight = poke_hub_games_get_pokemon_weight($mystery_pokemon_id);
    
    if ($guessed_weight !== null && $mystery_weight !== null) {
        $weight_diff = $guessed_weight - $mystery_weight;
        $hints['weight'] = abs($weight_diff) < 0.01 ? 'correct' : ($weight_diff > 0 ? 'higher' : 'lower');
        $hints['weight_value'] = $guessed_weight;
    }

    // Génération (pour affichage dans les tentatives en mode toutes générations)
    $guessed_gen = poke_hub_games_get_pokemon_generation($guessed_pokemon_id);
    $mystery_gen = poke_hub_games_get_pokemon_generation($mystery_pokemon_id);
    
    if ($guessed_gen && $mystery_gen) {
        if ($guessed_gen['generation_number'] === $mystery_gen['generation_number']) {
            $hints['generation'] = 'correct';
        } elseif ($guessed_gen['generation_number'] > $mystery_gen['generation_number']) {
            $hints['generation'] = 'higher';
        } else {
            $hints['generation'] = 'lower';
        }
        $hints['generation_number'] = $guessed_gen['generation_number'];
    }

    return [
        'is_correct' => $guessed_pokemon_id === $mystery_pokemon_id,
        'hints' => $hints,
        'guessed_pokemon' => $guessed,
        'mystery_pokemon' => $mystery
    ];
}

/**
 * Sauvegarde un score de pokedle
 * 
 * @param int $user_id ID utilisateur (null pour anonyme)
 * @param string $game_date Date du jeu (Y-m-d)
 * @param int $pokemon_id ID du Pokémon deviné
 * @param int $attempts Nombre de tentatives
 * @param bool $is_success Si le joueur a réussi
 * @param int $completion_time Temps de complétion en secondes
 * @param array $score_data Données supplémentaires (JSON)
 * @return int|false ID de l'enregistrement ou false en cas d'erreur (int pour compatibilité PHP < 8.0)
 */
function poke_hub_pokedle_save_score(
    ?int $user_id,
    string $game_date,
    int $pokemon_id,
    int $attempts,
    bool $is_success,
    int $completion_time = 0,
    array $score_data = []
) {
    global $wpdb;

    $scores_table = pokehub_get_table('games_scores');
    if (!$scores_table) {
        return false;
    }

    // Pour les utilisateurs anonymes, utiliser NULL
    // On garde 0 pour les anonymes mais on accepte aussi NULL
    if ($user_id !== null && $user_id <= 0) {
        $user_id = null;
    }
    $game_date = sanitize_text_field($game_date);
    $pokemon_id = (int) $pokemon_id;
    $attempts = (int) $attempts;
    $is_success = $is_success ? 1 : 0;
    $completion_time = (int) $completion_time;
    $score_data_json = !empty($score_data) ? wp_json_encode($score_data) : null;

    // Vérifier si un score existe déjà pour cet utilisateur et cette date
    if ($user_id === null) {
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$scores_table}
                 WHERE user_id IS NULL AND game_type = 'pokedle' AND game_date = %s",
                $game_date
            )
        );
    } else {
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$scores_table}
                 WHERE user_id = %d AND game_type = 'pokedle' AND game_date = %s",
                $user_id,
                $game_date
            )
        );
    }

    $was_success_before = false;
    if ($existing) {
        // Récupérer l'ancien état pour savoir si on doit mettre à jour les points
        $old_score = $wpdb->get_row(
            $wpdb->prepare("SELECT is_success FROM {$scores_table} WHERE id = %d", $existing),
            ARRAY_A
        );
        $was_success_before = $old_score && (int) $old_score['is_success'] === 1;
        
        // Mettre à jour le score existant
        $result = $wpdb->update(
            $scores_table,
            [
                'pokemon_id' => $pokemon_id,
                'attempts' => $attempts,
                'is_success' => $is_success,
                'completion_time' => $completion_time,
                'score_data' => $score_data_json,
            ],
            [
                'id' => $existing
            ],
            ['%d', '%d', '%d', '%d', '%s'],
            ['%d']
        );

        $score_id = $result !== false ? (int) $existing : false;
    } else {
        // Créer un nouveau score
        $result = $wpdb->insert(
            $scores_table,
            [
                'user_id' => $user_id,
                'game_type' => 'pokedle',
                'game_date' => $game_date,
                'pokemon_id' => $pokemon_id,
                'attempts' => $attempts,
                'is_success' => $is_success,
                'completion_time' => $completion_time,
                'score_data' => $score_data_json,
            ],
            ['%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s']
        );

        $score_id = $result !== false ? (int) $wpdb->insert_id : false;
    }
    
    // Mettre à jour les points si l'utilisateur est connecté
    // On met à jour seulement si c'est un nouveau score ou si le succès a changé
    if ($score_id && $user_id && $user_id > 0) {
        if (!$existing || ($was_success_before !== $is_success)) {
            // Si c'est un nouveau score ou si le statut de succès a changé, mettre à jour les points
            // Pour un update, on doit recalculer tous les points depuis le début de la période
            // Pour simplifier, on met à jour seulement si c'est un nouveau score
            if (!$existing) {
                // Extraire les données des indices depuis score_data
                $hints_used = 0;
                $hints_enabled = true;
                if (!empty($score_data)) {
                    if (is_string($score_data)) {
                        $score_data = json_decode($score_data, true);
                    }
                    if (is_array($score_data)) {
                        $hints_used = isset($score_data['hints_used']) ? (int) $score_data['hints_used'] : 0;
                        $hints_enabled = isset($score_data['hints_enabled']) ? filter_var($score_data['hints_enabled'], FILTER_VALIDATE_BOOLEAN) : true;
                    }
                }
                
                $score_data_array = [
                    'hints_used' => $hints_used,
                    'hints_enabled' => $hints_enabled
                ];
                
                poke_hub_games_add_points($user_id, $game_date, 'pokedle', (bool) $is_success, $attempts, $completion_time, $score_data_array);
            }
        }
    }
    
    return $score_id;
}

/**
 * Récupère le classement des meilleurs scores pour une date donnée
 * 
 * @param string $game_date Date du jeu (Y-m-d)
 * @param int $limit Nombre de résultats à retourner
 * @return array
 */
function poke_hub_pokedle_get_leaderboard(string $game_date, int $limit = 10): array {
    global $wpdb;

    $scores_table = pokehub_get_table('games_scores');
    if (!$scores_table) {
        return [];
    }

    $game_date = sanitize_text_field($game_date);
    $limit = (int) $limit;

    // Récupérer les scores avec calcul des points basés sur les indices
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT s.*, u.user_login, u.display_name
             FROM {$scores_table} s
             LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
             WHERE s.game_type = 'pokedle'
             AND s.game_date = %s
             AND s.is_success = 1
             ORDER BY s.attempts ASC, s.completion_time ASC
             LIMIT %d",
            $game_date,
            $limit
        ),
        ARRAY_A
    );
    
    // Calculer les points pour chaque entrée
    foreach ($results as &$entry) {
        $score_data = !empty($entry['score_data']) ? json_decode($entry['score_data'], true) : [];
        $points = poke_hub_games_calculate_points('pokedle', true, (int) $entry['attempts'], (int) $entry['completion_time'], $score_data);
        $entry['points'] = $points;
    }
    unset($entry);
    
    // Trier par points décroissants
    usort($results, function($a, $b) {
        $points_a = isset($a['points']) ? (int) $a['points'] : 0;
        $points_b = isset($b['points']) ? (int) $b['points'] : 0;
        if ($points_a !== $points_b) {
            return $points_b <=> $points_a;
        }
        // En cas d'égalité, trier par tentatives puis temps
        $attempts_a = (int) $a['attempts'];
        $attempts_b = (int) $b['attempts'];
        if ($attempts_a !== $attempts_b) {
            return $attempts_a <=> $attempts_b;
        }
        return ((int) $a['completion_time']) <=> ((int) $b['completion_time']);
    });

    return $results ?: [];
}

/**
 * Récupère le classement global des meilleurs scores (tous les temps)
 * Une seule entrée par joueur (la meilleure)
 * 
 * @param int $limit Nombre de résultats à retourner
 * @return array
 */
function poke_hub_pokedle_get_global_leaderboard(int $limit = 10): array {
    global $wpdb;

    $scores_table = pokehub_get_table('games_scores');
    if (!$scores_table) {
        return [];
    }

    $limit = (int) $limit;

    // Récupérer tous les scores réussis des utilisateurs connectés
    $all_scores = $wpdb->get_results(
        "SELECT s.*, u.user_login, u.display_name
         FROM {$scores_table} s
         LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
         WHERE s.game_type = 'pokedle'
         AND s.is_success = 1
         AND s.user_id > 0
         ORDER BY s.user_id ASC, s.attempts ASC, s.completion_time ASC",
        ARRAY_A
    );

    if (empty($all_scores)) {
        return [];
    }

    // Garder uniquement le meilleur score de chaque utilisateur
    // Meilleur = moins de tentatives, puis meilleur temps
    $unique_users = [];
    foreach ($all_scores as $row) {
        $user_id = (int) $row['user_id'];
        if (!isset($unique_users[$user_id])) {
            // Premier score trouvé pour cet utilisateur (déjà trié par tentatives puis temps)
            $unique_users[$user_id] = $row;
        }
    }

    // Réordonner par tentatives puis temps
    usort($unique_users, function($a, $b) {
        $attempts_a = (int) $a['attempts'];
        $attempts_b = (int) $b['attempts'];
        if ($attempts_a !== $attempts_b) {
            return $attempts_a <=> $attempts_b;
        }
        $time_a = (int) $a['completion_time'];
        $time_b = (int) $b['completion_time'];
        return $time_a <=> $time_b;
    });

    // Limiter au nombre demandé
    return array_slice($unique_users, 0, $limit);
}

/**
 * Sauvegarde ou récupère le Pokémon du jour pour une date et génération données
 * 
 * @param string $game_date Date du jeu (Y-m-d)
 * @param int|null $generation_id ID de la génération (NULL pour toutes générations)
 * @param int $pokemon_id ID du Pokémon du jour
 * @return int|false ID de l'enregistrement ou false en cas d'erreur (int pour compatibilité PHP < 8.0)
 */
function poke_hub_pokedle_save_daily_pokemon(string $game_date, ?int $generation_id, int $pokemon_id) {
    global $wpdb;

    $pokedle_daily_table = pokehub_get_table('pokedle_daily');
    if (!$pokedle_daily_table) {
        return false;
    }

    $game_date = sanitize_text_field($game_date);
    $pokemon_id = (int) $pokemon_id;
    $generation_id = $generation_id !== null && $generation_id > 0 ? (int) $generation_id : null;

    // Vérifier si un enregistrement existe déjà pour cette date/génération
    if ($generation_id === null) {
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$pokedle_daily_table}
                 WHERE game_date = %s 
                 AND generation_id IS NULL
                 LIMIT 1",
                $game_date
            )
        );
    } else {
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$pokedle_daily_table}
                 WHERE game_date = %s 
                 AND generation_id = %d
                 LIMIT 1",
                $game_date,
                $generation_id
            )
        );
    }

    if ($existing) {
        // Mettre à jour le Pokémon si nécessaire
        $wpdb->update(
            $pokedle_daily_table,
            ['pokemon_id' => $pokemon_id],
            ['id' => $existing],
            ['%d'],
            ['%d']
        );
        return (int) $existing;
    }

    // Créer un nouvel enregistrement
    $result = $wpdb->insert(
        $pokedle_daily_table,
        [
            'game_date' => $game_date,
            'generation_id' => $generation_id,
            'pokemon_id' => $pokemon_id,
        ],
        ['%s', '%d', '%d']
    );

    return $result !== false ? (int) $wpdb->insert_id : false;
}

/**
 * Récupère le Pokémon du jour pour une date et génération données
 * 
 * @param string $game_date Date du jeu (Y-m-d)
 * @param int|null $generation_id ID de la génération (NULL pour toutes générations)
 * @return array|null Enregistrement avec pokemon_id ou null
 */
function poke_hub_pokedle_get_daily_pokemon_record(string $game_date, ?int $generation_id = null): ?array {
    global $wpdb;

    $pokedle_daily_table = pokehub_get_table('pokedle_daily');
    if (!$pokedle_daily_table) {
        return null;
    }

    $game_date = sanitize_text_field($game_date);
    $generation_id = $generation_id !== null && $generation_id > 0 ? (int) $generation_id : null;

    if ($generation_id === null) {
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$pokedle_daily_table}
                 WHERE game_date = %s 
                 AND generation_id IS NULL
                 LIMIT 1",
                $game_date
            ),
            ARRAY_A
        );
    } else {
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$pokedle_daily_table}
                 WHERE game_date = %s 
                 AND generation_id = %d
                 LIMIT 1",
                $game_date,
                $generation_id
            ),
            ARRAY_A
        );
    }

    return $record ?: null;
}

/**
 * Récupère le score d'un utilisateur pour une date donnée
 * 
 * @param int|null $user_id ID utilisateur (null pour anonyme)
 * @param string $game_date Date du jeu (Y-m-d)
 * @return array|null
 */
function poke_hub_pokedle_get_user_score(?int $user_id, string $game_date): ?array {
    global $wpdb;

    $scores_table = pokehub_get_table('games_scores');
    if (!$scores_table) {
        return null;
    }

    // Pour les utilisateurs anonymes, utiliser NULL
    if ($user_id !== null && $user_id <= 0) {
        $user_id = null;
    }
    $game_date = sanitize_text_field($game_date);

    // Gérer NULL différemment dans la requête
    if ($user_id === null) {
        $score = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$scores_table}
                 WHERE user_id IS NULL AND game_type = 'pokedle' AND game_date = %s
                 LIMIT 1",
                $game_date
            ),
            ARRAY_A
        );
    } else {
        $score = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$scores_table}
                 WHERE user_id = %d AND game_type = 'pokedle' AND game_date = %s
                 LIMIT 1",
                $user_id,
                $game_date
            ),
            ARRAY_A
        );
    }

    return $score ?: null;
}

/**
 * Compte le nombre de joueurs ayant réussi un pokedle pour une date et génération données
 * 
 * @param string $game_date Date du jeu (Y-m-d)
 * @param int|null $generation_id ID de la génération (NULL pour toutes générations)
 * @return int Nombre de joueurs ayant réussi
 */
function poke_hub_pokedle_count_successful_players(string $game_date, ?int $generation_id = null): int {
    global $wpdb;

    $scores_table = pokehub_get_table('games_scores');
    if (!$scores_table) {
        return 0;
    }

    $game_date = sanitize_text_field($game_date);
    
    // Pour l'instant, on compte tous les scores réussis pour cette date
    // On pourrait filtrer par génération si on stocke cette info dans games_scores
    // Pour l'instant, on compte simplement tous les scores réussis pour cette date
    $count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id)
             FROM {$scores_table}
             WHERE game_type = 'pokedle'
             AND game_date = %s
             AND is_success = 1
             AND user_id IS NOT NULL
             AND user_id > 0",
            $game_date
        )
    );

    return (int) $count;
}

