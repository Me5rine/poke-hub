<?php
// modules/eggs/functions/eggs-pages.php
// Création automatique de la page Eggs (enfant de pokemon-go) à l'activation du module eggs.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Crée ou met à jour la page Eggs selon les modules actifs.
 * Appelée à l'activation du module eggs (et par admin_init pour rattraper).
 */
function poke_hub_eggs_create_pages() {
    if (!function_exists('poke_hub_ensure_pokemon_go_page')) {
        return;
    }
    $parent_id = poke_hub_ensure_pokemon_go_page();
    if ($parent_id <= 0) {
        return;
    }

    $slug       = 'eggs';
    $option_key = 'poke_hub_page_' . $slug;
    $page_id    = get_option($option_key);
    if ($page_id && get_post_status($page_id)) {
        return;
    }

    $existing = get_page_by_path($slug, OBJECT, 'page');
    if ($existing) {
        if ((int) $existing->post_parent !== (int) $parent_id) {
            wp_update_post([
                'ID'          => $existing->ID,
                'post_parent' => $parent_id,
            ]);
        }
        update_option($option_key, $existing->ID);
        return;
    }

    $new_id = wp_insert_post([
        'post_title'     => 'Eggs',
        'post_name'      => $slug,
        'post_content'   => '[pokehub_all_eggs]',
        'post_status'    => 'publish',
        'post_type'      => 'page',
        'post_parent'    => $parent_id,
        'post_author'    => get_current_user_id() ?: 1,
        'comment_status' => 'closed',
    ]);
    if (!is_wp_error($new_id)) {
        update_option($option_key, $new_id);
    }
    flush_rewrite_rules(false);
}

add_action('poke_hub_eggs_module_activated', 'poke_hub_eggs_create_pages');

// Au chargement admin : s'assurer que la page existe si le module est actif
add_action('admin_init', function () {
    if (!function_exists('poke_hub_is_module_active') || !poke_hub_is_module_active('eggs')) {
        return;
    }
    if (get_transient('poke_hub_eggs_page_checked')) {
        return;
    }
    set_transient('poke_hub_eggs_page_checked', 1, 300); // 5 min
    poke_hub_eggs_create_pages();
}, 20);
