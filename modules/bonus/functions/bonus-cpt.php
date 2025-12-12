<?php
// modules/bonus/bonus-cpt.php
if (!defined('ABSPATH')) { exit; }

function pokehub_register_bonus_cpt() {

    $labels = [
        'name'               => 'Bonus',
        'singular_name'      => 'Bonus',
        'add_new'            => 'Ajouter un bonus',
        'add_new_item'       => 'Ajouter un nouveau bonus',
        'edit_item'          => 'Modifier le bonus',
        'new_item'           => 'Nouveau bonus',
        'view_item'          => 'Voir le bonus',
        'search_items'       => 'Rechercher un bonus',
        'not_found'          => 'Aucun bonus trouvé',
        'not_found_in_trash' => 'Aucun bonus dans la corbeille',
    ];

    register_post_type('pokehub_bonus', [
        'labels'             => $labels,
        'public'             => false,          // pas de pages publiques
        'publicly_queryable' => false,
        'show_ui'            => true,           // visible dans l’admin
        'show_in_menu'       => false,
        'supports'           => ['title', 'thumbnail', 'editor'],
        'has_archive'        => false,
        'show_in_rest'       => false,
        'menu_position'      => 25,
        'menu_icon'          => 'dashicons-plus-alt',
    ]);
}
add_action('init', 'pokehub_register_bonus_cpt');
