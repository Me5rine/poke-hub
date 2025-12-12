<?php
// File: /includes/admin-ui.php

if (!defined('ABSPATH')) exit;

function poke_hub_dashboard_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Poké HUB', 'poke-hub') . '</h1>';
    echo '<p>' . esc_html__('Welcome to Poké HUB. This will be the main dashboard (to be designed).', 'poke-hub') . '</p>';
    echo '</div>';
}