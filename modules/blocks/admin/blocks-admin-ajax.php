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

    global $wpdb;
    $table = pokehub_get_table('pokemon');
    if (!$table) {
        wp_send_json_success(['has_gender_dimorphism' => false]);
    }

    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT extra FROM {$table} WHERE id = %d", $pokemon_id)
    );

    $has_gender_dimorphism = false;
    if ($row && !empty($row->extra)) {
        $extra = json_decode($row->extra, true);
        if (is_array($extra) && !empty($extra['has_gender_dimorphism'])) {
            $has_gender_dimorphism = true;
        }
    }

    wp_send_json_success(['has_gender_dimorphism' => $has_gender_dimorphism]);
});

