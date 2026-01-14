<?php
// modules/games/functions/games-pages.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Automatic page creation for games
 * Checks the poke_hub_games_auto_create_pages option before creating
 */
function poke_hub_games_create_pages() {
    // Check if automatic creation is disabled
    $auto_create = get_option('poke_hub_games_auto_create_pages', true);
    if (!$auto_create) {
        return;
    }

    $default_pages = [
        'pokedle' => [
            'title'   => __('Pokedle', 'poke-hub'),
            'content' => '[pokedle]',
            'parent'  => 0
        ],
        'games-leaderboard' => [
            'title'   => __('Games Leaderboard', 'poke-hub'),
            'content' => '[games_leaderboard]',
            'parent'  => 0
        ]
    ];

    foreach ($default_pages as $slug => $data) {
        $option_key = 'poke_hub_games_page_' . $slug;
        $page_id = get_option($option_key);
        
        // If we have a stored ID and the page exists, continue
        if ($page_id && get_post_status($page_id)) {
            continue;
        }
        
        // Check if a page already exists with this slug
        $existing_page = get_page_by_path($slug);
        if ($existing_page) {
            update_option($option_key, $existing_page->ID);
            continue;
        }
        
        // Determine parent ID
        $parent_id = 0;
        if (!empty($data['parent'])) {
            if (is_numeric($data['parent'])) {
                $parent_id = (int) $data['parent'];
            } else {
                // It's a slug, get the parent page ID
                $parent_option_key = 'poke_hub_games_page_' . $data['parent'];
                $parent_page_id = get_option($parent_option_key);
                if ($parent_page_id && get_post_status($parent_page_id)) {
                    $parent_id = $parent_page_id;
                } else {
                    // Search for parent page by slug
                    $parent_page = get_page_by_path($data['parent']);
                    if ($parent_page) {
                        $parent_id = $parent_page->ID;
                        update_option($parent_option_key, $parent_id);
                    }
                }
            }
        }
        
        // Create the page
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
 * Delete automatically created pages (for uninstallation)
 */
function poke_hub_games_delete_pages() {
    $page_slugs = ['pokedle', 'games-leaderboard'];

    foreach ($page_slugs as $slug) {
        $option_key = 'poke_hub_games_page_' . $slug;
        $page_id = get_option($option_key);

        if ($page_id && get_post($page_id)) {
            wp_delete_post($page_id, true);
        }

        delete_option($option_key);
    }
}

