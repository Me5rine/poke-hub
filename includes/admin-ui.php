<?php
// File: /includes/admin-ui.php

if (!defined('ABSPATH')) exit;

/**
 * Liste des slugs de modules enregistrés (synchro avec settings-modules.php).
 * Utilisé pour l’admin et la cohérence avec les réglages (General).
 *
 * @return array<int,string>
 */
function poke_hub_registered_module_slugs(): array {
    if (!function_exists('poke_hub_get_modules_registry')) {
        return ['events', 'bonus', 'pokemon', 'user-profiles', 'games', 'eggs', 'blocks', 'collections'];
    }
    return array_keys(poke_hub_get_modules_registry());
}

function poke_hub_dashboard_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Poké HUB', 'poke-hub') . '</h1>';
    echo '<p>' . esc_html__('Welcome to Poké HUB. This will be the main dashboard (to be designed).', 'poke-hub') . '</p>';
    echo '</div>';
}