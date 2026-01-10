<?php
// modules/user-profiles/functions/user-profiles-pages.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Création automatique des pages pour les codes amis et vivillon
 * Vérifie l'option poke_hub_user_profiles_auto_create_pages avant de créer
 */
function poke_hub_user_profiles_create_pages() {
    // Vérifier si la création automatique est désactivée
    $auto_create = get_option('poke_hub_user_profiles_auto_create_pages', true);
    if (!$auto_create) {
        return;
    }
    // Utiliser directement les chaînes en anglais (traductions gérées par WordPress lors de l'affichage)
    $default_pages = [
        'pokemon-go' => [
            'title'   => 'Pokémon GO',
            'content' => '',
            'parent'  => 0
        ],
        'friend-codes' => [
            'title'   => 'Friend Codes',
            'content' => '[poke_hub_friend_codes]',
            'parent'  => 'pokemon-go'
        ],
        'vivillon' => [
            'title'   => 'Vivillon Patterns',
            'content' => '[poke_hub_vivillon]',
            'parent'  => 'pokemon-go'
        ]
    ];

    foreach ($default_pages as $slug => $data) {
        $option_key = 'poke_hub_user_profiles_page_' . $slug;
        $page_id = get_option($option_key);
        
        // Si on a un ID stocké et que la page existe, continuer
        if ($page_id && get_post_status($page_id)) {
            continue;
        }
        
        // Vérifier si une page existe déjà avec ce slug
        $existing_page = get_page_by_path($slug);
        if ($existing_page) {
            update_option($option_key, $existing_page->ID);
            continue;
        }
        
        // Déterminer l'ID du parent
        $parent_id = 0;
        if (!empty($data['parent'])) {
            if (is_numeric($data['parent'])) {
                $parent_id = (int) $data['parent'];
            } else {
                // C'est un slug, récupérer l'ID de la page parent
                $parent_option_key = 'poke_hub_user_profiles_page_' . $data['parent'];
                $parent_page_id = get_option($parent_option_key);
                if ($parent_page_id && get_post_status($parent_page_id)) {
                    $parent_id = $parent_page_id;
                } else {
                    // Chercher la page parent par slug
                    $parent_page = get_page_by_path($data['parent']);
                    if ($parent_page) {
                        $parent_id = $parent_page->ID;
                        update_option($parent_option_key, $parent_id);
                    }
                }
            }
        }
        
        // Créer la page
        $new_page_id = wp_insert_post([
            'post_title'     => $data['title'],
            'post_name'      => $slug,
            'post_content'   => $data['content'],
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_parent'    => $parent_id,
            'post_author'    => get_current_user_id() ?: 1,
            'comment_status' => 'closed'
        ]);
        
        if (!is_wp_error($new_page_id)) {
            update_option($option_key, $new_page_id);
        }
    }
    
    // Flush rewrite rules to ensure pages are accessible
    flush_rewrite_rules(false);
}

/**
 * Supprimer les pages créées automatiquement (pour désinstallation)
 */
function poke_hub_user_profiles_delete_pages() {
    $page_slugs = ['friend-codes', 'vivillon', 'pokemon-go'];

    foreach ($page_slugs as $slug) {
        $option_key = 'poke_hub_user_profiles_page_' . $slug;
        $page_id = get_option($option_key);

        if ($page_id && get_post($page_id)) {
            wp_delete_post($page_id, true);
        }

        delete_option($option_key);
    }
}
