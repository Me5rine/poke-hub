<?php
// modules/user-profiles/public/user-profiles-vivillon-shortcode.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode to display Vivillon/Lepidonilles friend codes filtered by pattern and country
 * 
 * Usage: [poke_hub_vivillon]
 * 
 * @param array $atts Shortcode attributes
 * @return string Vivillon friend codes HTML content
 */
add_shortcode('poke_hub_vivillon', function ($atts) {
    // Check if module is active
    if (!function_exists('poke_hub_is_module_active') || !poke_hub_is_module_active('user-profiles')) {
        return '';
    }
    
    // Load helpers
    if (!function_exists('poke_hub_get_public_friend_codes')) {
        if (defined('POKE_HUB_USER_PROFILES_PATH')) {
            require_once POKE_HUB_USER_PROFILES_PATH . '/functions/user-profiles-friend-codes-helpers.php';
        } else {
            require_once dirname(__FILE__) . '/../functions/user-profiles-friend-codes-helpers.php';
        }
    }
    
    // Parse attributes
    $atts = shortcode_atts([
        'per_page' => 20,
    ], $atts, 'poke_hub_vivillon');
    
    $per_page = max(1, min(100, (int) $atts['per_page']));
    
    // Handle form submission
    $form_message = '';
    $form_message_type = '';
    $needs_link_confirmation = false;
    $pending_link_data = [];
    
    if (isset($_POST['poke_hub_add_vivillon_code']) && wp_verify_nonce($_POST['poke_hub_vivillon_code_nonce'], 'poke_hub_add_vivillon_code')) {
        $is_logged_in = is_user_logged_in();
        
        $form_data = [
            'friend_code' => isset($_POST['friend_code']) ? sanitize_text_field($_POST['friend_code']) : '',
            'pokemon_go_username' => isset($_POST['pokemon_go_username']) ? sanitize_text_field($_POST['pokemon_go_username']) : '',
            'country' => isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '',
            'scatterbug_pattern' => isset($_POST['scatterbug_pattern']) ? sanitize_text_field($_POST['scatterbug_pattern']) : '',
            'team' => isset($_POST['team']) ? sanitize_text_field($_POST['team']) : '',
        ];
        
        // Scatterbug pattern is required for Vivillon page
        if (empty($form_data['scatterbug_pattern'])) {
            $form_message = __('Vivillon pattern is required.', 'poke-hub');
            $form_message_type = 'error';
        } else {
            $result = poke_hub_add_public_friend_code($form_data, $is_logged_in);
            
            if ($result['success']) {
                $form_message = $result['message'];
                $form_message_type = 'success';
                
                // Clear form fields on success
                $_POST = [];
            } elseif (isset($result['needs_link_confirmation']) && $result['needs_link_confirmation']) {
                // Code exists and needs to be linked
                $form_message = $result['message'];
                $form_message_type = 'warning';
                $needs_link_confirmation = true;
                $pending_link_data = $form_data; // Store data to resubmit with link confirmation
            } else {
                $form_message = $result['message'];
                $form_message_type = 'error';
            }
        }
    }
    
    // Get filters from URL
    $filter_country = isset($_GET['country']) ? sanitize_text_field($_GET['country']) : '';
    $filter_pattern = isset($_GET['pattern']) ? sanitize_text_field($_GET['pattern']) : '';
    $paged = isset($_GET['pg']) ? max(1, (int) $_GET['pg']) : 1;
    
    // Get friend codes filtered by pattern (required for vivillon)
    $friend_codes_args = [
        'country' => $filter_country,
        'scatterbug_pattern' => $filter_pattern, // Pattern is required for vivillon page
        'per_page' => $per_page,
        'paged' => $paged,
        'orderby' => 'created_at',
        'order' => 'DESC',
    ];
    
    $friend_codes = poke_hub_get_public_friend_codes($friend_codes_args);
    
    // Get options for selects
    $countries = function_exists('poke_hub_get_countries') ? poke_hub_get_countries() : [];
    $scatterbug_patterns = function_exists('poke_hub_get_scatterbug_patterns') ? poke_hub_get_scatterbug_patterns() : [];
    $teams = function_exists('poke_hub_get_teams') ? poke_hub_get_teams() : [];
    
    $is_logged_in = is_user_logged_in();
    
    // Get existing profile data for logged-in users to check if they have a vivillon code
    $existing_profile = [];
    $has_vivillon_code = false;
    $profile_url = '';
    if ($is_logged_in) {
        $user_id = get_current_user_id();
        if (function_exists('poke_hub_get_user_profile')) {
            $existing_profile = poke_hub_get_user_profile($user_id);
            // Check if user has friend code with scatterbug_pattern
            $has_vivillon_code = !empty($existing_profile['friend_code']) && !empty($existing_profile['scatterbug_pattern']);
        }
        
        // Get profile URL using the correct format
        $user = wp_get_current_user();
        if ($user && !empty($user->user_nicename)) {
            // Use configured base URL for profiles, or current site URL
            $base_url = function_exists('poke_hub_get_user_profiles_base_url') 
                ? poke_hub_get_user_profiles_base_url() 
                : home_url();
            $profile_url = $base_url . '/profil/' . $user->user_nicename . '/?tab=game-pokemon-go';
        }
    }
    
    ob_start();
    ?>
    <div class="vivillon-dashboard me5rine-lab-dashboard">
        <?php
        // Render the adaptive header (function is already loaded in user-profiles.php)
        if (function_exists('poke_hub_friend_codes_render_header')) {
            poke_hub_friend_codes_render_header('vivillon');
        }
        ?>
        
        <!-- Add/Edit Vivillon Friend Code Form -->
        <?php
        if (function_exists('poke_hub_render_friend_code_form')) {
            poke_hub_render_friend_code_form([
                'context' => 'vivillon',
                'existing_profile' => $existing_profile,
                'countries' => $countries,
                'scatterbug_patterns' => $scatterbug_patterns,
                'teams' => $teams,
                'is_logged_in' => $is_logged_in,
                'profile_url' => $profile_url,
                'form_message' => $form_message,
                'form_message_type' => $form_message_type,
                'needs_link_confirmation' => $needs_link_confirmation,
                'pending_link_data' => $pending_link_data,
                'require_pattern' => true, // Pattern is required for Vivillon
            ]);
        }
        ?>
        
        <!-- Filters -->
        <?php
        if (function_exists('poke_hub_render_friend_codes_filters')) {
            poke_hub_render_friend_codes_filters([
                'context' => 'vivillon',
                'countries' => $countries,
                'scatterbug_patterns' => $scatterbug_patterns,
                'filter_country' => $filter_country,
                'filter_pattern' => $filter_pattern,
            ]);
        }
        ?>
        
        <!-- Vivillon Friend Codes List -->
        <?php
        if (function_exists('poke_hub_render_friend_codes_list')) {
            poke_hub_render_friend_codes_list([
                'friend_codes' => $friend_codes,
                'paged' => $paged,
                'teams' => $teams,
                'context' => 'vivillon',
                'empty_message' => __('No friend code found for these criteria. Be the first to add one!', 'poke-hub'),
            ]);
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
});

