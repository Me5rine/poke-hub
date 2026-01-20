<?php
// modules/user-profiles/public/user-profiles-friend-codes-shortcode.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode to display and manage public friend codes
 * 
 * Usage: [poke_hub_friend_codes]
 * 
 * @param array $atts Shortcode attributes
 * @return string Friend codes HTML content
 */
add_shortcode('poke_hub_friend_codes', function ($atts) {
    // Check if module is active
    if (!function_exists('poke_hub_is_module_active') || !poke_hub_is_module_active('user-profiles')) {
        return '';
    }
    
    // Load helpers (should already be loaded by module, but ensure they're available)
    if (!function_exists('poke_hub_add_public_friend_code')) {
        if (defined('POKE_HUB_USER_PROFILES_PATH')) {
            require_once POKE_HUB_USER_PROFILES_PATH . '/functions/user-profiles-friend-codes-helpers.php';
        } else {
            require_once dirname(__FILE__) . '/../functions/user-profiles-friend-codes-helpers.php';
        }
    }
    
    // Parse attributes
    $atts = shortcode_atts([
        'per_page' => 20,
    ], $atts, 'poke_hub_friend_codes');
    
    $per_page = max(1, min(100, (int) $atts['per_page']));
    
    // Handle form submission
    $form_message = '';
    $form_message_type = '';
    $needs_link_confirmation = false;
    $pending_link_data = [];
    
    if (isset($_POST['poke_hub_add_friend_code']) && wp_verify_nonce($_POST['poke_hub_friend_code_nonce'], 'poke_hub_add_friend_code')) {
        $is_logged_in = is_user_logged_in();
        
        $form_data = [
            'friend_code' => isset($_POST['friend_code']) ? sanitize_text_field($_POST['friend_code']) : '',
            'pokemon_go_username' => isset($_POST['pokemon_go_username']) ? sanitize_text_field($_POST['pokemon_go_username']) : '',
            'country' => isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '',
            'scatterbug_pattern' => isset($_POST['scatterbug_pattern']) ? sanitize_text_field($_POST['scatterbug_pattern']) : '',
            'team' => isset($_POST['team']) ? sanitize_text_field($_POST['team']) : '',
        ];
        
        // The validation country/pattern is now done inside poke_hub_add_public_friend_code()
        // so it returns a proper result with message like other validations
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
    
    // Get filters from URL
    $filter_country = isset($_GET['country']) ? sanitize_text_field($_GET['country']) : '';
    $filter_team = isset($_GET['team']) ? sanitize_text_field($_GET['team']) : '';
    $filter_reason = isset($_GET['reason']) ? sanitize_text_field($_GET['reason']) : '';
    $paged = isset($_GET['pg']) ? max(1, (int) $_GET['pg']) : 1;
    
    // Get friend codes
    $friend_codes_args = [
        'country' => $filter_country,
        'team' => $filter_team,
        'reason' => $filter_reason,
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
    $reasons = function_exists('poke_hub_get_reasons') ? poke_hub_get_reasons() : [];
    
    $is_logged_in = is_user_logged_in();
    
    // Get existing profile data for logged-in users to pre-fill the form
    $existing_profile = [];
    $profile_url = '';
    if ($is_logged_in) {
        $user_id = get_current_user_id();
        if (function_exists('poke_hub_get_user_profile')) {
            $existing_profile = poke_hub_get_user_profile($user_id);
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
    <div class="friend-codes-dashboard me5rine-lab-dashboard">
        <?php
        // Header adaptatif pour Friend Codes
        do_action('poke_hub_friend_codes_header', 'friend_codes');
        ?>
        
        <!-- Add Friend Code Form -->
        <?php
        if (function_exists('poke_hub_render_friend_code_form')) {
            poke_hub_render_friend_code_form([
                'context' => 'friend_codes',
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
                'require_pattern' => false,
            ]);
        }
        ?>
        
        <!-- Filters -->
        <?php
        if (function_exists('poke_hub_render_friend_codes_filters')) {
            poke_hub_render_friend_codes_filters([
                'context' => 'friend_codes',
                'countries' => $countries,
                'teams' => $teams,
                'reasons' => $reasons,
                'filter_country' => $filter_country,
                'filter_team' => $filter_team,
                'filter_reason' => $filter_reason,
            ]);
        }
        ?>
        
        <!-- Friend Codes List -->
        <?php
        if (function_exists('poke_hub_render_friend_codes_list')) {
            poke_hub_render_friend_codes_list([
                'friend_codes' => $friend_codes,
                'paged' => $paged,
                'teams' => $teams,
                'context' => 'friend_codes',
                'empty_message' => __('No friend code found. Be the first to add one!', 'poke-hub'),
            ]);
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
});

