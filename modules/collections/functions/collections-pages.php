<?php
// modules/collections/functions/collections-pages.php
// Création automatique de la page Collections à l'activation du module (respecte poke_hub_collections_auto_create_pages).

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Crée la page Collections Pokémon GO (enfant de pokemon-go) si l'option auto-create est activée.
 * Appelée à l'activation du module collections.
 */
function poke_hub_collections_create_pages() {
    if (!get_option('poke_hub_collections_auto_create_pages', true)) {
        return;
    }
    if (!function_exists('poke_hub_ensure_pokemon_go_page')) {
        return;
    }
    $parent_id = poke_hub_ensure_pokemon_go_page();
    if ($parent_id <= 0) {
        return;
    }
    $option_key = 'poke_hub_page_collections';
    $page_id   = get_option($option_key);
    if ($page_id && get_post_status($page_id)) {
        $page = get_post($page_id);
        if ($page && strpos($page->post_content, '[poke_hub_collections_page]') === false
            && (strpos($page->post_content, '[poke_hub_collection_view]') !== false || strpos($page->post_content, '[poke_hub_collections]') !== false)) {
            wp_update_post([
                'ID'           => $page_id,
                'post_content' => '[poke_hub_collections_page]',
            ]);
        }
        return;
    }
    $existing = get_page_by_path('collections', OBJECT, 'page');
    if ($existing) {
        if ((int) $existing->post_parent !== (int) $parent_id) {
            wp_update_post([
                'ID'          => $existing->ID,
                'post_parent' => $parent_id,
            ]);
        }
        if (strpos($existing->post_content, '[poke_hub_collections_page]') === false
            && (strpos($existing->post_content, '[poke_hub_collection_view]') !== false || strpos($existing->post_content, '[poke_hub_collections]') !== false)) {
            wp_update_post([
                'ID'           => $existing->ID,
                'post_content' => '[poke_hub_collections_page]',
            ]);
        }
        update_option($option_key, $existing->ID);
        return;
    }
    $content = "[poke_hub_collections_page]";
    $new_id  = wp_insert_post([
        'post_title'     => __('Collections Pokémon GO', 'poke-hub'),
        'post_name'      => 'collections',
        'post_content'   => $content,
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

add_action('poke_hub_collections_module_activated', 'poke_hub_collections_create_pages');

// Au chargement admin : s'assurer que la page existe si le module est actif (cas activation antérieure ou option activée plus tard)
add_action('admin_init', function () {
    if (!function_exists('poke_hub_is_module_active') || !poke_hub_is_module_active('collections')) {
        return;
    }
    if (get_transient('poke_hub_collections_page_checked')) {
        return;
    }
    set_transient('poke_hub_collections_page_checked', 1, 300); // 5 min
    poke_hub_collections_create_pages();
}, 20);
