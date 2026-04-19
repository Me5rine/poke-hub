<?php
// File: /includes/admin-ui.php

if (!defined('ABSPATH')) exit;

/**
 * Styles admin communs (barre « retour à la liste », etc.).
 */
function poke_hub_admin_enqueue_common_styles(string $hook): void {
    if (strpos($hook, 'poke-hub') === false) {
        return;
    }
    $handle = 'pokehub-admin-common';
    wp_register_style($handle, false);
    wp_enqueue_style($handle);
    wp_add_inline_style(
        $handle,
        '.pokehub-admin-back-bar{margin:0 0 1.25em;padding:.65em 1em;background:#f0f0f1;border-left:4px solid #2271b1;border-radius:2px;}'
        . '.pokehub-admin-back-bar .button{font-weight:600;}'
    );
}
add_action('admin_enqueue_scripts', 'poke_hub_admin_enqueue_common_styles', 5);

/**
 * Barre visible « retour à la liste » (sous-pages d’édition Poké HUB).
 *
 * @param string      $url   URL absolue de la liste.
 * @param string|null $label Libellé (défaut : chaîne traduite « Back to list »).
 */
function poke_hub_admin_back_to_list_bar(string $url, ?string $label = null): void {
    if ($label === null) {
        $label = __('Back to list', 'poke-hub');
    }
    echo '<div class="pokehub-admin-back-bar"><a href="' . esc_url($url) . '" class="button button-secondary">&larr; ' . esc_html($label) . '</a></div>';
}

/**
 * Liste des slugs de modules enregistrés. Dérivée de la source unique settings-modules.php.
 *
 * @return array<int,string>
 */
function poke_hub_registered_module_slugs(): array {
    if (function_exists('poke_hub_get_ordered_module_slugs')) {
        return poke_hub_get_ordered_module_slugs();
    }
    if (function_exists('poke_hub_get_modules_registry')) {
        return array_keys(poke_hub_get_modules_registry());
    }
    return [];
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