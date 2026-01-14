<?php
// modules/games/functions/games-points.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calcule les points pour un jeu terminé
 * 
 * @param string $game_type Type de jeu (ex: 'pokedle')
 * @param bool $is_success Si le joueur a réussi
 * @param int $attempts Nombre de tentatives
 * @param int $completion_time Temps de complétion en secondes
 * @return int Points attribués
 */
function poke_hub_games_calculate_points(string $game_type, bool $is_success, int $attempts = 0, int $completion_time = 0): int {
    $points = 0;
    
    // Points de base pour avoir terminé le jeu
    $points += 10; // 10 points pour avoir terminé
    
    // Points bonus pour avoir réussi
    if ($is_success) {
        $points += 50; // 50 points bonus pour avoir réussi
        
        // Points bonus selon le nombre de tentatives (moins de tentatives = plus de points)
        if ($attempts > 0) {
            $bonus_attempts = max(0, 50 - ($attempts * 5)); // 50 points pour 1 tentative, 45 pour 2, etc.
            $points += $bonus_attempts;
        }
        
        // Points bonus selon le temps (plus rapide = plus de points)
        if ($completion_time > 0) {
            $bonus_time = max(0, 30 - (int)($completion_time / 10)); // 30 points pour très rapide, décroissant
            $points += $bonus_time;
        }
    }
    
    // Permettre aux autres jeux de modifier les points
    return apply_filters('poke_hub_games_calculate_points', $points, $game_type, $is_success, $attempts, $completion_time);
}

/**
 * Met à jour les points d'un joueur pour une période donnée
 * 
 * @param int $user_id ID utilisateur
 * @param string $period_type Type de période ('daily', 'weekly', 'monthly', 'yearly', 'total')
 * @param string $period_start Date de début de la période (Y-m-d)
 * @param int $points Points à ajouter
 * @param bool $is_success Si le jeu a été réussi
 * @return bool
 */
function poke_hub_games_update_points(int $user_id, string $period_type, string $period_start, int $points, bool $is_success): bool {
    global $wpdb;
    
    $points_table = pokehub_get_table('games_points');
    if (!$points_table) {
        return false;
    }
    
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    
    $period_type = sanitize_text_field($period_type);
    $period_start = sanitize_text_field($period_start);
    $points = (int) $points;
    
    // Vérifier si un enregistrement existe déjà
    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$points_table}
             WHERE user_id = %d AND period_type = %s AND period_start = %s
             LIMIT 1",
            $user_id,
            $period_type,
            $period_start
        ),
        ARRAY_A
    );
    
    if ($existing) {
        // Mettre à jour les points existants
        $new_points = (int) $existing['points'] + $points;
        $new_completed = (int) $existing['games_completed'] + 1;
        $new_succeeded = (int) $existing['games_succeeded'] + ($is_success ? 1 : 0);
        
        $result = $wpdb->update(
            $points_table,
            [
                'points' => $new_points,
                'games_completed' => $new_completed,
                'games_succeeded' => $new_succeeded,
            ],
            [
                'id' => $existing['id']
            ],
            ['%d', '%d', '%d'],
            ['%d']
        );
        
        return $result !== false;
    } else {
        // Créer un nouvel enregistrement
        $period_end = null;
        if ($period_type === 'daily') {
            $period_end = $period_start;
        } elseif ($period_type === 'weekly') {
            $period_end = date('Y-m-d', strtotime($period_start . ' +6 days'));
        } elseif ($period_type === 'monthly') {
            $period_end = date('Y-m-t', strtotime($period_start));
        } elseif ($period_type === 'yearly') {
            $period_end = date('Y-12-31', strtotime($period_start));
        }
        // 'total' n'a pas de date de fin
        
        $result = $wpdb->insert(
            $points_table,
            [
                'user_id' => $user_id,
                'period_type' => $period_type,
                'period_start' => $period_start,
                'period_end' => $period_end,
                'points' => $points,
                'games_completed' => 1,
                'games_succeeded' => $is_success ? 1 : 0,
            ],
            ['%d', '%s', '%s', '%s', '%d', '%d', '%d']
        );
        
        return $result !== false;
    }
}

/**
 * Ajoute les points pour un jeu terminé
 * Met à jour toutes les périodes pertinentes (quotidien, semaine, mois, année, total)
 * 
 * @param int $user_id ID utilisateur
 * @param string $game_date Date du jeu (Y-m-d)
 * @param string $game_type Type de jeu
 * @param bool $is_success Si le jeu a été réussi
 * @param int $attempts Nombre de tentatives
 * @param int $completion_time Temps de complétion en secondes
 * @return bool
 */
function poke_hub_games_add_points(int $user_id, string $game_date, string $game_type, bool $is_success, int $attempts = 0, int $completion_time = 0): bool {
    if ($user_id <= 0) {
        return false;
    }
    
    // Calculer les points
    $points = poke_hub_games_calculate_points($game_type, $is_success, $attempts, $completion_time);
    
    if ($points <= 0) {
        return false;
    }
    
    $game_date = sanitize_text_field($game_date);
    $date_obj = new DateTime($game_date);
    
    // Mettre à jour les points pour chaque période
    $success = true;
    
    // Quotidien
    $daily_start = $date_obj->format('Y-m-d');
    $success = $success && poke_hub_games_update_points($user_id, 'daily', $daily_start, $points, $is_success);
    
    // Semaine (lundi de la semaine)
    $week_start = clone $date_obj;
    $week_start->modify('monday this week');
    $success = $success && poke_hub_games_update_points($user_id, 'weekly', $week_start->format('Y-m-d'), $points, $is_success);
    
    // Mois
    $month_start = $date_obj->format('Y-m-01');
    $success = $success && poke_hub_games_update_points($user_id, 'monthly', $month_start, $points, $is_success);
    
    // Année
    $year_start = $date_obj->format('Y-01-01');
    $success = $success && poke_hub_games_update_points($user_id, 'yearly', $year_start, $points, $is_success);
    
    // Total
    $total_start = '1970-01-01'; // Date de référence pour le total
    $success = $success && poke_hub_games_update_points($user_id, 'total', $total_start, $points, $is_success);
    
    return $success;
}

/**
 * Récupère le classement pour une période donnée
 * 
 * @param string $period_type Type de période ('daily', 'weekly', 'monthly', 'yearly', 'total')
 * @param string $period_start Date de début de la période (Y-m-d)
 * @param int $limit Nombre de résultats à retourner
 * @return array
 */
function poke_hub_games_get_leaderboard(string $period_type, string $period_start, int $limit = 10): array {
    global $wpdb;
    
    $points_table = pokehub_get_table('games_points');
    if (!$points_table) {
        return [];
    }
    
    $period_type = sanitize_text_field($period_type);
    $period_start = sanitize_text_field($period_start);
    $limit = (int) $limit;
    
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT p.*, u.user_login, u.display_name
             FROM {$points_table} p
             LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
             WHERE p.period_type = %s AND p.period_start = %s
             ORDER BY p.points DESC, p.games_succeeded DESC, p.games_completed DESC
             LIMIT %d",
            $period_type,
            $period_start,
            $limit
        ),
        ARRAY_A
    );
    
    return $results ?: [];
}

/**
 * Récupère les points d'un utilisateur pour une période donnée
 * 
 * @param int $user_id ID utilisateur
 * @param string $period_type Type de période
 * @param string $period_start Date de début de la période
 * @return array|null
 */
function poke_hub_games_get_user_points(int $user_id, string $period_type, string $period_start): ?array {
    global $wpdb;
    
    $points_table = pokehub_get_table('games_points');
    if (!$points_table) {
        return null;
    }
    
    $user_id = (int) $user_id;
    $period_type = sanitize_text_field($period_type);
    $period_start = sanitize_text_field($period_start);
    
    $result = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$points_table}
             WHERE user_id = %d AND period_type = %s AND period_start = %s
             LIMIT 1",
            $user_id,
            $period_type,
            $period_start
        ),
        ARRAY_A
    );
    
    return $result ?: null;
}

