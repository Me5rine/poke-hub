<?php
// File: includes/functions/pokemon-public-helpers.php
// Helpers publics pour les données Pokémon (disponibles même si le module Pokémon n'est pas actif)
// Ces fonctions sont utilisées par d'autres modules (ex: user-profiles) et doivent être disponibles
// dès l'activation du plugin.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Scatterbug/Vivillon patterns from database.
 * Only returns patterns marked as regional (extra->regional->is_regional = true).
 * Patterns are stored as form variants for Scatterbug (dex_number 664) and Vivillon (dex_number 666).
 * 
 * This function is available even if the Pokémon module is not active, as it's used by
 * the user-profiles module to display Scatterbug pattern selection.
 *
 * @return array Associative array form_slug => label (French or English name)
 */
function poke_hub_pokemon_get_scatterbug_patterns(): array {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }

    global $wpdb;
    
    // Sécurité : vérifier que $wpdb est disponible
    if (!isset($wpdb) || !is_object($wpdb)) {
        return [];
    }

    // Vérifier si un préfixe Pokémon distant est configuré
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);
    
    // Si un préfixe distant est configuré ET différent du préfixe local, utiliser les tables distantes
    // On vérifie aussi que poke_hub_pokemon_get_table_prefix() retourne bien un préfixe différent
    $use_remote = false;
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        // Vérifier que la fonction retourne bien le préfixe distant
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            // Si le préfixe retourné est différent du préfixe local, on utilise les tables distantes
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote = true;
            }
        }
    }
    
    if ($use_remote) {
        $pokemon_table = pokehub_get_table('remote_pokemon');
        $form_variants_table = pokehub_get_table('remote_pokemon_form_variants');
    } else {
        // Sinon, utiliser les tables locales
        $pokemon_table = pokehub_get_table('pokemon');
        $form_variants_table = pokehub_get_table('pokemon_form_variants');
    }

    // Vérifier que les noms de tables sont valides
    if (empty($pokemon_table) || empty($form_variants_table)) {
        return [];
    }
    
    // Debug temporaire : vérifier les noms de tables (à retirer en production)
    // Utiliser error_log directement pour être sûr que ça s'affiche
    error_log('[POKE-HUB] Scatterbug - pokemon_table: ' . $pokemon_table);
    error_log('[POKE-HUB] Scatterbug - form_variants_table: ' . $form_variants_table);
    error_log('[POKE-HUB] Scatterbug - remote_prefix configuré: ' . ($pokemon_remote_prefix ?: 'vide'));
    error_log('[POKE-HUB] Scatterbug - wpdb->prefix: ' . $wpdb->prefix);
    error_log('[POKE-HUB] Scatterbug - utilise tables distantes: ' . ($use_remote ? 'OUI' : 'NON'));
    if ($use_remote && function_exists('poke_hub_pokemon_get_table_prefix')) {
        $actual_prefix = poke_hub_pokemon_get_table_prefix();
        error_log('[POKE-HUB] Scatterbug - actual_prefix retourné: ' . $actual_prefix);
    }

    // Note: On ne vérifie pas l'existence des tables distantes avec pokehub_table_exists()
    // car cette fonction ne peut vérifier que les tables locales (SHOW TABLES ne fonctionne pas pour les tables distantes)
    // On laisse la requête SQL échouer silencieusement si les tables n'existent pas

    // Get form variants for Scatterbug (664) and Vivillon (666)
    // Only those marked as regional (extra->regional->is_regional = true)
    // Utiliser esc_sql pour sécuriser les noms de tables
    $pokemon_table_escaped = esc_sql($pokemon_table);
    $form_variants_table_escaped = esc_sql($form_variants_table);
    
    $sql = "SELECT DISTINCT 
                fv.form_slug,
                fv.label,
                COALESCE(p.name_fr, p.name_en, '') AS pokemon_name
            FROM `{$pokemon_table_escaped}` AS p
            INNER JOIN `{$form_variants_table_escaped}` AS fv ON p.form_variant_id = fv.id
            WHERE p.dex_number IN (664, 666)
            AND p.form_variant_id > 0
            AND p.extra LIKE '%\"regional\":{\"is_regional\":true%'
            ORDER BY fv.label ASC, fv.form_slug ASC";
    
    $patterns = $wpdb->get_results($sql);
    
    // Debug temporaire : vérifier les résultats (à retirer en production)
    error_log('[POKE-HUB] Scatterbug - SQL: ' . $sql);
    error_log('[POKE-HUB] Scatterbug - last_error: ' . ($wpdb->last_error ?: 'aucune erreur'));
    error_log('[POKE-HUB] Scatterbug - patterns count: ' . (is_array($patterns) ? count($patterns) : 'pas un tableau'));
    if (is_array($patterns) && count($patterns) > 0) {
        error_log('[POKE-HUB] Scatterbug - premiers patterns: ' . print_r(array_slice($patterns, 0, 3), true));
    }
    
    // Vérifier s'il y a eu une erreur SQL
    if ($wpdb->last_error) {
        // En cas d'erreur SQL, retourner un tableau vide
        // Le fallback dans poke_hub_get_scatterbug_patterns() utilisera la liste par défaut
        return [];
    }
    
    // Si $patterns est false (erreur) ou null, retourner un tableau vide
    if ($patterns === false || $patterns === null) {
        return [];
    }

    $result = [];
    foreach ($patterns as $pattern) {
        // Vérifier que $pattern est un objet
        if (!is_object($pattern)) {
            continue;
        }
        
        $form_slug = (string) ($pattern->form_slug ?? '');
        if (empty($form_slug)) {
            continue;
        }

        // Use variant label, otherwise use pokemon name + form_slug
        $label = (string) ($pattern->label ?? '');
        if (empty($label)) {
            $label = ucwords(str_replace(['-', '_'], ' ', $form_slug));
        }

        $result[$form_slug] = $label;
    }

    // Si aucun pattern trouvé, retourner un tableau vide
    // Le fallback dans poke_hub_get_scatterbug_patterns() utilisera la liste par défaut
    return $result;
}






