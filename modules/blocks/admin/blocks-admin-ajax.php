<?php
// modules/blocks/admin/blocks-admin-ajax.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX admin : vérifie si un Pokémon a une dimorphie de genre.
 *
 * Les metabox Blocks (wild / new / habitats / field research) utilisent cette action via
 * `action=pokehub_check_pokemon_gender_dimorphism`. Elle doit donc être enregistrée même si
 * le module `events` est désactivé.
 */
add_action('wp_ajax_pokehub_check_pokemon_gender_dimorphism', function () {
    // Accepter plusieurs nonces possibles (mêmes clés que celles utilisées par les metabox).
    $valid_nonce = false;
    $nonce_actions = [
        'pokehub_special_events_nonce',
        'pokehub_wild_pokemon_ajax',
        'pokehub_new_pokemon_ajax',
        'pokehub_habitats_ajax',
        'pokehub_quests_ajax',
    ];

    foreach ($nonce_actions as $action) {
        if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], $action)) {
            $valid_nonce = true;
            break;
        }
    }

    if (!$valid_nonce) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }

    $pokemon_id = isset($_POST['pokemon_id']) ? (int) $_POST['pokemon_id'] : 0;
    if (!$pokemon_id) {
        wp_send_json_error(['message' => 'Invalid pokemon_id']);
    }

    $profile = function_exists('poke_hub_pokemon_get_gender_profile')
        ? poke_hub_pokemon_get_gender_profile($pokemon_id)
        : [
            'has_gender_dimorphism'   => false,
            'gender_ratio'            => ['male' => 0.0, 'female' => 0.0],
            'available_genders'       => [],
            'spawn_available_genders' => [],
            'default_gender'          => null,
        ];

    wp_send_json_success([
        'has_gender_dimorphism'   => !empty($profile['has_gender_dimorphism']),
        'gender_ratio'            => is_array($profile['gender_ratio'] ?? null) ? $profile['gender_ratio'] : ['male' => 0.0, 'female' => 0.0],
        'available_genders'       => is_array($profile['available_genders'] ?? null) ? array_values($profile['available_genders']) : [],
        'spawn_available_genders' => is_array($profile['spawn_available_genders'] ?? null) ? array_values($profile['spawn_available_genders']) : [],
        'default_gender'          => isset($profile['default_gender']) ? $profile['default_gender'] : null,
    ]);
});

