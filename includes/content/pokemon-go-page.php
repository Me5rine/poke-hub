<?php
// File: includes/content/pokemon-go-page.php
// Helpers pour la page parente "Pokémon GO" utilisée par user-profiles, events, eggs, collections.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère l'ID de la page parente pokemon-go (créée par user-profiles ou existante).
 *
 * @return int 0 si non trouvée.
 */
function poke_hub_get_pokemon_go_page_id() {
    $option_key = 'poke_hub_user_profiles_page_pokemon-go';
    $page_id = get_option($option_key);
    if ($page_id && get_post_status($page_id)) {
        return (int) $page_id;
    }
    $page = get_page_by_path('pokemon-go', OBJECT, 'page');
    if ($page) {
        update_option($option_key, $page->ID);
        return (int) $page->ID;
    }
    return 0;
}

/**
 * Crée la page pokemon-go si elle n'existe pas (pour que research/eggs/collections aient un parent).
 * Utilise la même logique que user-profiles (option partagée).
 *
 * @return int ID de la page pokemon-go, ou 0 en cas d'échec.
 */
function poke_hub_ensure_pokemon_go_page() {
    $parent_id = poke_hub_get_pokemon_go_page_id();
    if ($parent_id > 0) {
        return $parent_id;
    }
    $new_id = wp_insert_post([
        'post_title'     => 'Pokémon GO',
        'post_name'      => 'pokemon-go',
        'post_content'   => '',
        'post_status'    => 'publish',
        'post_type'      => 'page',
        'post_parent'    => 0,
        'post_author'    => get_current_user_id() ?: 1,
        'comment_status' => 'closed',
    ]);
    if (!is_wp_error($new_id)) {
        update_option('poke_hub_user_profiles_page_pokemon-go', $new_id);
        return (int) $new_id;
    }
    return 0;
}
