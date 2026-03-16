<?php
// modules/bonus/bonus-cpt.php
if (!defined('ABSPATH')) { exit; }

function pokehub_register_bonus_cpt() {

    $labels = [
        'name'               => _x('Bonus', 'post type general name', 'poke-hub'),
        'singular_name'      => _x('Bonus', 'post type singular name', 'poke-hub'),
        'add_new'            => __('Add bonus', 'poke-hub'),
        'add_new_item'       => __('Add new bonus', 'poke-hub'),
        'edit_item'          => __('Edit bonus', 'poke-hub'),
        'new_item'           => __('New bonus', 'poke-hub'),
        'view_item'          => __('View bonus', 'poke-hub'),
        'search_items'       => __('Search bonus', 'poke-hub'),
        'not_found'          => __('No bonus found', 'poke-hub'),
        'not_found_in_trash' => __('No bonus in trash', 'poke-hub'),
    ];

    register_post_type('pokehub_bonus', [
        'labels'             => $labels,
        'public'             => false,          // pas de pages publiques
        'publicly_queryable' => false,
        'show_ui'            => !(function_exists('pokehub_bonus_use_remote_source') && pokehub_bonus_use_remote_source()),           // visible dans l’admin
        'show_in_menu'       => false,
        'supports'           => ['title', 'thumbnail', 'editor'],
        'has_archive'        => false,
        'show_in_rest'       => false,
        'menu_position'      => 25,
        'menu_icon'          => 'dashicons-plus-alt',
    ]);
}
add_action('init', 'pokehub_register_bonus_cpt');

/**
 * Sync le CPT pokehub_bonus vers la table catalogue bonus_types (site principal uniquement).
 * Les sites distants lisent cette table via le préfixe Pokémon ; on n'écrit que localement.
 */
function pokehub_sync_bonus_cpt_to_table($post_id) {
    if (get_post_type($post_id) !== 'pokehub_bonus') {
        return;
    }
    if (function_exists('pokehub_bonus_use_remote_source') && pokehub_bonus_use_remote_source()) {
        return;
    }
    $table = function_exists('pokehub_get_bonus_types_table') ? pokehub_get_bonus_types_table() : '';
    if ($table === '' || !function_exists('pokehub_get_table')) {
        return;
    }
    global $wpdb;
    $post = get_post($post_id);
    if (!$post || $post->post_status === 'trash' || $post->post_status === 'auto-draft') {
        pokehub_delete_bonus_type_row($post_id);
        return;
    }
    if ($post->post_status !== 'publish') {
        return;
    }
    $title       = $post->post_title;
    $slug        = $post->post_name ?: sanitize_title($title);
    $description = $post->post_content;
    $image_slug  = $post->post_name ?: '';
    $sort_order  = (int) $post->menu_order;

    $wpdb->replace(
        $table,
        [
            'id'          => (int) $post_id,
            'title'       => $title,
            'slug'        => $slug,
            'description' => $description,
            'image_slug'  => $image_slug,
            'sort_order'  => $sort_order,
        ],
        ['%d', '%s', '%s', '%s', '%s', '%d']
    );
}

/**
 * Supprime une entrée de la table bonus_types (quand un bonus est mis à la corbeille/supprimé).
 */
function pokehub_delete_bonus_type_row($post_id) {
    if (get_post_type($post_id) !== 'pokehub_bonus') {
        return;
    }
    if (function_exists('pokehub_bonus_use_remote_source') && pokehub_bonus_use_remote_source()) {
        return;
    }
    $table = function_exists('pokehub_get_bonus_types_table') ? pokehub_get_bonus_types_table() : '';
    if ($table === '') {
        return;
    }
    global $wpdb;
    $wpdb->delete($table, ['id' => (int) $post_id], ['%d']);
}

add_action('save_post_pokehub_bonus', 'pokehub_sync_bonus_cpt_to_table', 20, 1);
add_action('trashed_post', 'pokehub_delete_bonus_type_row', 10, 1);
add_action('before_delete_post', 'pokehub_delete_bonus_type_row', 10, 1);
