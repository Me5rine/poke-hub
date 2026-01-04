<?php
// modules/user-profiles/public/user-profiles-shortcode.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render Pokémon GO user profile form/view
 * Similar pattern to admin_lab_render_participation_table
 * 
 * @param int $user_id User ID
 * @param bool $can_edit Whether user can edit the profile
 * @return string Profile HTML content
 */
function poke_hub_render_user_profile($user_id, $can_edit) {
    // Handle form submission
    $success_message = '';
    $error_message = '';
    if ($can_edit && isset($_POST['poke_hub_save_profile_front']) && wp_verify_nonce($_POST['poke_hub_profile_nonce'], 'poke_hub_save_profile_front')) {
        // Clean and validate friend code (must be exactly 12 digits)
        $friend_code_raw = isset($_POST['friend_code']) ? sanitize_text_field($_POST['friend_code']) : '';
        $friend_code = '';
        
        if (!empty($friend_code_raw)) {
            if (function_exists('poke_hub_clean_friend_code')) {
                $friend_code = poke_hub_clean_friend_code($friend_code_raw);
            } else {
                $cleaned = preg_replace('/[^0-9]/', '', $friend_code_raw);
                // Must be exactly 12 digits
                $friend_code = (strlen($cleaned) === 12) ? $cleaned : '';
            }
            
            // Validate: if friend code was provided but is invalid, show error
            if (empty($friend_code) && !empty($friend_code_raw)) {
                $error_message = __('The friend code must be exactly 12 digits (e.g., 1234 5678 9012).', 'poke-hub');
            }
        }
    
        // Only save if no validation errors
        if (empty($error_message)) {
            $profile = [
                'team'                => isset($_POST['team']) ? sanitize_text_field($_POST['team']) : '',
                'friend_code'         => $friend_code,
                'friend_code_public'  => isset($_POST['friend_code_public']) ? true : false,
                'xp'                  => isset($_POST['xp']) ? (function_exists('poke_hub_clean_xp') ? poke_hub_clean_xp($_POST['xp']) : absint(preg_replace('/[^0-9]/', '', $_POST['xp']))) : 0,
                'country'             => isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '',
                'pokemon_go_username' => isset($_POST['pokemon_go_username']) ? sanitize_text_field($_POST['pokemon_go_username']) : '',
                'scatterbug_pattern'  => isset($_POST['scatterbug_pattern']) ? sanitize_text_field($_POST['scatterbug_pattern']) : '',
                'reasons'             => isset($_POST['reasons']) && is_array($_POST['reasons']) ? array_map('sanitize_text_field', $_POST['reasons']) : [],
            ];

            if (function_exists('poke_hub_save_user_profile')) {
                $save_result = poke_hub_save_user_profile($user_id, $profile);
                if ($save_result) {
                    // Force Ultimate Member to refetch user data if available
                    // Note: poke_hub_save_user_profile() already purges UM cache internally
                    if (function_exists('poke_hub_purge_um_user_cache')) {
                        poke_hub_purge_um_user_cache($user_id);
                    }
                    $success_message = true;
                }
            }
        }
    }
    
    // Get profile data (always retrieve from database to get latest saved values)
    if (!function_exists('poke_hub_get_user_profile')) {
        return '<!-- poke_hub_get_user_profile function does not exist -->';
    }
    
    $profile = poke_hub_get_user_profile($user_id);
    $teams = function_exists('poke_hub_get_teams') ? poke_hub_get_teams() : [];
    $reasons = function_exists('poke_hub_get_reasons') ? poke_hub_get_reasons() : [];
    $scatterbug_patterns = function_exists('poke_hub_get_scatterbug_patterns') ? poke_hub_get_scatterbug_patterns() : [];
    $countries = function_exists('poke_hub_get_countries') ? poke_hub_get_countries() : [];
    
    
    // Ensure arrays
    if (empty($teams)) $teams = [];
    if (!is_array($reasons)) $reasons = [];
    if (empty($scatterbug_patterns)) $scatterbug_patterns = [];
    if (empty($profile) || !is_array($profile)) $profile = [];
    
    // Ensure profile reasons is an array (already normalized as strings in poke_hub_get_user_profile)
    if (!isset($profile['reasons']) || !is_array($profile['reasons'])) {
        $profile['reasons'] = [];
    }
    // Double-check: ensure all reasons are strings for comparison
    $profile['reasons'] = array_map('strval', $profile['reasons']);
    
    ob_start();
    ?>
    <?php if (!empty($success_message)) : ?>
        <div id="poke-hub-profile-message" class="me5rine-lab-form-message me5rine-lab-form-message-success">
            <p><?php esc_html_e('Pokémon GO profile updated successfully', 'poke-hub'); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)) : ?>
        <div id="poke-hub-profile-message" class="me5rine-lab-form-message me5rine-lab-form-message-error">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="me5rine-lab-form-container">
        <h3 class="me5rine-lab-title-medium"><?php esc_html_e('Pokémon GO Profile', 'poke-hub'); ?></h3>
        <?php if ($can_edit) : ?>
            <form method="post" action="" id="poke-hub-profile-form">
                <?php wp_nonce_field('poke_hub_save_profile_front', 'poke_hub_profile_nonce'); ?>
                
                <!-- Row 1: Username and Country -->
                <div class="me5rine-lab-form-row me5rine-lab-form-col-gap">
                    <div class="me5rine-lab-form-col">
                        <div class="me5rine-lab-form-field">
                            <label class="me5rine-lab-form-label" for="pokemon_go_username"><?php esc_html_e('Pokémon GO Username', 'poke-hub'); ?></label>
                            <input type="text" name="pokemon_go_username" id="pokemon_go_username" value="<?php echo esc_attr($profile['pokemon_go_username']); ?>" class="me5rine-lab-form-input" placeholder="<?php esc_attr_e('Your in-game username', 'poke-hub'); ?>">
                        </div>
                    </div>
                    <div class="me5rine-lab-form-col">
                        <div class="me5rine-lab-form-field">
                            <label class="me5rine-lab-form-label" for="country"><?php esc_html_e('Country', 'poke-hub'); ?></label>
                            <select name="country" id="country" class="me5rine-lab-form-select<?php echo empty($profile['country']) ? ' me5rine-lab-form-select-placeholder' : ''; ?>">
                                <option value=""<?php echo empty($profile['country']) ? ' selected' : ''; ?>><?php esc_html_e('-- Select a country --', 'poke-hub'); ?></option>
                                <?php foreach ($countries as $code => $label) : ?>
                                    <option value="<?php echo esc_attr($label); ?>" <?php selected($profile['country'], $label); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Team and Friend Code -->
                <div class="me5rine-lab-form-row me5rine-lab-form-col-gap">
                    <div class="me5rine-lab-form-col">
                        <div class="me5rine-lab-form-field">
                            <label class="me5rine-lab-form-label" for="team"><?php esc_html_e('Team', 'poke-hub'); ?></label>
                            <select name="team" id="team" class="me5rine-lab-form-select<?php echo empty($profile['team']) ? ' me5rine-lab-form-select-placeholder' : ''; ?>">
                                <option value=""<?php echo empty($profile['team']) ? ' selected' : ''; ?>><?php esc_html_e('-- Select a team --', 'poke-hub'); ?></option>
                                <?php foreach ($teams as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($profile['team'], $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="me5rine-lab-form-col">
                        <div class="me5rine-lab-form-field">
                            <label class="me5rine-lab-form-label" for="friend_code"><?php esc_html_e('Friend Code', 'poke-hub'); ?></label>
                            <?php 
                            // Format friend code for display in input (with spaces)
                            $formatted_friend_code = !empty($profile['friend_code']) && function_exists('poke_hub_format_friend_code')
                                ? poke_hub_format_friend_code($profile['friend_code'])
                                : $profile['friend_code'];
                            ?>
                            <input type="text" name="friend_code" id="friend_code" value="<?php echo esc_attr($formatted_friend_code); ?>" class="me5rine-lab-form-input" placeholder="1234 5678 9012" maxlength="14" pattern="[0-9\s]{0,14}" title="<?php esc_attr_e('The friend code must be exactly 12 digits (e.g., 1234 5678 9012)', 'poke-hub'); ?>">
                            <label class="me5rine-lab-form-checkbox-item" for="friend_code_public">
                                <input type="checkbox" name="friend_code_public" id="friend_code_public" value="1" class="me5rine-lab-form-checkbox" <?php checked($profile['friend_code_public'], true); ?>>
                                <span class="me5rine-lab-form-checkbox-icon">
                                    <i class="<?php echo $profile['friend_code_public'] ? 'um-icon-android-checkbox' : 'um-icon-android-checkbox-outline-blank'; ?>"></i>
                                </span>
                                <span class="me5rine-lab-form-checkbox-text"><?php esc_html_e('Display my friend code publicly on my profile', 'poke-hub'); ?></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Row 3: XP and Scatterbug Pattern -->
                <div class="me5rine-lab-form-row me5rine-lab-form-col-gap">
                    <div class="me5rine-lab-form-col">
                        <div class="me5rine-lab-form-field">
                            <label class="me5rine-lab-form-label" for="xp"><?php esc_html_e('XP', 'poke-hub'); ?></label>
                            <?php 
                            // Format XP for display in input (with spaces)
                            $formatted_xp = !empty($profile['xp']) && function_exists('poke_hub_format_xp')
                                ? poke_hub_format_xp($profile['xp'])
                                : $profile['xp'];
                            ?>
                            <input type="text" name="xp" id="xp" value="<?php echo esc_attr($formatted_xp); ?>" class="me5rine-lab-form-input" pattern="[0-9\s]*" placeholder="0">
                        </div>
                    </div>
                    <div class="me5rine-lab-form-col">
                        <div class="me5rine-lab-form-field">
                            <label class="me5rine-lab-form-label" for="scatterbug_pattern"><?php esc_html_e('Scatterbug Pattern', 'poke-hub'); ?></label>
                            <select name="scatterbug_pattern" id="scatterbug_pattern" class="me5rine-lab-form-select<?php echo empty($profile['scatterbug_pattern']) ? ' me5rine-lab-form-select-placeholder' : ''; ?>">
                                <option value=""<?php echo empty($profile['scatterbug_pattern']) ? ' selected' : ''; ?>><?php esc_html_e('-- Select a pattern --', 'poke-hub'); ?></option>
                                <?php foreach ($scatterbug_patterns as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($profile['scatterbug_pattern'], $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Row: Reasons (full width) -->
                <div class="me5rine-lab-form-row-full">
                    <div class="me5rine-lab-form-field">
                        <label class="me5rine-lab-form-label"><?php esc_html_e('Reasons', 'poke-hub'); ?></label>
                        <div class="me5rine-lab-form-checkbox-group">
                            <?php foreach ($reasons as $value => $label) : ?>
                                <?php
                                $value_str = (string) $value;
                                $is_checked = in_array($value_str, $profile['reasons'], true);
                                ?>
                                <label class="me5rine-lab-form-checkbox-item<?php echo $is_checked ? ' checked' : ''; ?>">
                                    <input type="checkbox" name="reasons[]" value="<?php echo esc_attr($value); ?>" class="me5rine-lab-form-checkbox" <?php checked($is_checked); ?>>
                                    <span class="me5rine-lab-form-checkbox-icon">
                                        <i class="<?php echo $is_checked ? 'um-icon-android-checkbox' : 'um-icon-android-checkbox-outline-blank'; ?>"></i>
                                    </span>
                                    <span class="me5rine-lab-form-checkbox-text"><?php echo esc_html($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="me5rine-lab-form-description"><?php esc_html_e('Select why you are here (you can select multiple options)', 'poke-hub'); ?></div>
                    </div>
                </div>

                <div class="me5rine-lab-form-field">
                    <input type="hidden" name="poke_hub_save_profile_front" value="1">
                    <button type="submit" class="me5rine-lab-form-button"><?php esc_html_e('Save Profile', 'poke-hub'); ?></button>
                </div>
            </form>
            <script>
            (function() {
                // Initialize Select2 if available (loaded via wp_enqueue_scripts)
                if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
                    jQuery(function($) {
                        // Use class selector to avoid ID conflicts, and set dropdownParent correctly
                        $('.me5rine-lab-form-select').each(function() {
                            var $select = $(this);
                            if (!$select.data('select2')) {
                                // Find the closest form field wrapper for dropdownParent
                                var $parent = $select.closest('.me5rine-lab-form-field');
                                if (!$parent.length) {
                                    $parent = $select.closest('.me5rine-lab-form-col');
                                }
                                if (!$parent.length) {
                                    $parent = $select.parent();
                                }
                                $select.select2({
                                    width: '100%',
                                    allowClear: true,
                                    placeholder: $select.find('option[value=""]').text() || 'Select...',
                                    dropdownParent: $parent.length ? $parent : $('body')
                                });
                            }
                        });
                    });
                }
            })();
            </script>
        <?php else : ?>
            <div class="me5rine-lab-form-view">
                <!-- Row 1: Username and Country -->
                <div class="me5rine-lab-form-view-row">
                    <div class="me5rine-lab-form-view-item me5rine-lab-form-col">
                        <span class="me5rine-lab-form-view-label"><?php esc_html_e('Pokémon GO Username', 'poke-hub'); ?></span>
                        <span class="me5rine-lab-form-view-value"><?php echo esc_html($profile['pokemon_go_username'] ?: '—'); ?></span>
                    </div>
                    <div class="me5rine-lab-form-view-item me5rine-lab-form-col">
                        <span class="me5rine-lab-form-view-label"><?php esc_html_e('Country', 'poke-hub'); ?></span>
                        <span class="me5rine-lab-form-view-value"><?php echo esc_html($profile['country'] ?: '—'); ?></span>
                    </div>
                </div>

                <!-- Row 2: Team and Friend Code -->
                <div class="me5rine-lab-form-view-row">
                    <div class="me5rine-lab-form-view-item me5rine-lab-form-col">
                        <span class="me5rine-lab-form-view-label"><?php esc_html_e('Team', 'poke-hub'); ?></span>
                        <span class="me5rine-lab-form-view-value"><?php echo esc_html(!empty($profile['team']) ? ($teams[$profile['team']] ?? $profile['team']) : '—'); ?></span>
                    </div>
                    <div class="me5rine-lab-form-view-item me5rine-lab-form-col">
                        <span class="me5rine-lab-form-view-label"><?php esc_html_e('Friend Code', 'poke-hub'); ?></span>
                        <span class="me5rine-lab-form-view-value"><?php 
                            if (!empty($profile['friend_code']) && !empty($profile['friend_code_public'])) {
                                $formatted_code = function_exists('poke_hub_format_friend_code') 
                                    ? poke_hub_format_friend_code($profile['friend_code']) 
                                    : $profile['friend_code'];
                                echo esc_html($formatted_code);
                            } else {
                                echo '—';
                            }
                        ?></span>
                    </div>
                </div>

                <!-- Row 3: XP and Scatterbug Pattern -->
                <div class="me5rine-lab-form-view-row">
                    <div class="me5rine-lab-form-view-item me5rine-lab-form-col">
                        <span class="me5rine-lab-form-view-label"><?php esc_html_e('XP', 'poke-hub'); ?></span>
                        <span class="me5rine-lab-form-view-value"><?php 
                            if (!empty($profile['xp']) || $profile['xp'] === '0' || $profile['xp'] === 0) {
                                $formatted_xp = function_exists('poke_hub_format_xp') 
                                    ? poke_hub_format_xp($profile['xp']) 
                                    : number_format($profile['xp'], 0, ',', ' ');
                                echo esc_html($formatted_xp);
                            } else {
                                echo '—';
                            }
                        ?></span>
                    </div>
                    <div class="me5rine-lab-form-view-item me5rine-lab-form-col">
                        <span class="me5rine-lab-form-view-label"><?php esc_html_e('Scatterbug Pattern', 'poke-hub'); ?></span>
                        <span class="me5rine-lab-form-view-value"><?php echo esc_html($profile['scatterbug_pattern'] ?: '—'); ?></span>
                    </div>
                </div>

                <!-- Row: Reasons (full width) -->
                <?php if (!empty($profile['reasons'])) : ?>
                    <div class="me5rine-lab-form-view-row-full">
                        <div class="me5rine-lab-form-view-item me5rine-lab-form-col-full">
                            <span class="me5rine-lab-form-view-label"><?php esc_html_e('Reasons', 'poke-hub'); ?></span>
                            <span class="me5rine-lab-form-view-value"><?php
                                $reason_labels = array_map(function($reason) use ($reasons) {
                                    return $reasons[$reason] ?? $reason;
                                }, $profile['reasons']);
                                echo esc_html(implode(', ', $reason_labels));
                            ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    $output = ob_get_clean();
    
    // Debug: check if output is empty
    if (empty(trim($output))) {
        return '<!-- poke_hub_render_user_profile: ob_get_clean() returned empty string. User ID: ' . esc_html($user_id) . ' -->';
    }
    
    return $output;
}

/**
 * Shortcode to display Pokémon GO user profile form/view
 * Pattern similar to admin_lab_participation_table shortcode
 * 
 * Usage: [poke_hub_user_profile] or [poke_hub_user_profile user_id="123"]
 * 
 * @param array $atts Shortcode attributes
 * @return string Profile HTML content
 */
add_shortcode('poke_hub_user_profile', function ($atts) {
    // Check if module is active
    if (!function_exists('poke_hub_is_module_active') || !poke_hub_is_module_active('user-profiles')) {
        return '';
    }
    
    // Parse attributes
    $atts = shortcode_atts([
        'user_id' => 0, // 0 = current user or profile user
        'mode' => 'auto', // 'auto', 'edit', 'view'
        'debug' => false, // Enable debug mode
    ], $atts, 'poke_hub_user_profile');
    
    // Get user ID
    $user_id = (int) $atts['user_id'];
    
    // If no user_id provided, try to get from context (like admin_lab_participation_table)
    if ($user_id <= 0) {
        // Try Ultimate Member requested user (available in templates)
        if (function_exists('um_get_requested_user')) {
            $um_user = um_get_requested_user();
            if (!empty($um_user)) {
                if (is_numeric($um_user)) {
                    $user_id = (int) $um_user;
                } elseif (is_object($um_user) && isset($um_user->ID)) {
                    $user_id = (int) $um_user->ID;
                } elseif (is_array($um_user) && isset($um_user['ID'])) {
                    $user_id = (int) $um_user['ID'];
                }
            }
        }
        
        // Try Ultimate Member context
        if ($user_id <= 0 && function_exists('um_profile_id')) {
            $user_id = um_profile_id();
        }
        
        // Fallback to current user
        if ($user_id <= 0 && is_user_logged_in()) {
            $user_id = get_current_user_id();
        }
    }
    
    // Debug mode
    if ($atts['debug'] === 'true' || $atts['debug'] === true || $atts['debug'] === '1') {
        $debug_info = [
            'module_active' => poke_hub_is_module_active('user-profiles'),
            'user_id_from_attr' => (int) $atts['user_id'],
            'user_id_detected' => $user_id,
            'um_profile_id' => function_exists('um_profile_id') ? um_profile_id() : 'N/A',
            'um_get_requested_user' => function_exists('um_get_requested_user') ? um_get_requested_user() : 'N/A',
            'current_user_id' => is_user_logged_in() ? get_current_user_id() : 0,
            'functions_available' => [
                'poke_hub_get_user_profile' => function_exists('poke_hub_get_user_profile'),
                'poke_hub_get_teams' => function_exists('poke_hub_get_teams'),
                'poke_hub_get_reasons' => function_exists('poke_hub_get_reasons'),
            ],
        ];
        return '<pre style="background: #f0f0f0; padding: 10px; border: 1px solid #ccc;">' . esc_html(print_r($debug_info, true)) . '</pre>';
    }
    
    if ($user_id <= 0) {
        return '';
    }
    
    // Determine mode
    $can_edit = false;
    if ($atts['mode'] === 'auto') {
        $can_edit = (is_user_logged_in() && (get_current_user_id() == $user_id || current_user_can('manage_options')));
    } elseif ($atts['mode'] === 'edit') {
        $can_edit = (is_user_logged_in() && (get_current_user_id() == $user_id || current_user_can('manage_options')));
    } else {
        $can_edit = false; // view mode
    }
    
    // Render profile (like admin_lab_render_participation_table)
    if (function_exists('poke_hub_render_user_profile')) {
        $output = poke_hub_render_user_profile($user_id, $can_edit);
        // If output is empty, return a comment for debugging
        if (empty(trim($output))) {
            return '<!-- poke_hub_render_user_profile returned empty. User ID: ' . $user_id . ', Can Edit: ' . ($can_edit ? 'yes' : 'no') . ' -->';
        }
        return $output;
    }
    
    return '<!-- poke_hub_render_user_profile function does not exist -->';
});
