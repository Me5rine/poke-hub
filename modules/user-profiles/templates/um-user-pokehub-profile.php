<?php
/**
 * Ultimate Member Profile Tab Template: Pokémon GO
 * 
 * To use this template:
 * 1. Copy this file to your theme's folder: your-theme/ultimate-member/templates/profile/pokehub-profile.php
 * 2. Or copy to: your-theme/ultimate-member/templates/um-user/pokehub-profile.php
 * 
 * The exact path depends on your Ultimate Member template structure
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current user ID from Ultimate Member
$user_id = um_profile_id();

if (!$user_id) {
    return;
}

// Check if user can view/edit
$can_edit = (is_user_logged_in() && (get_current_user_id() == $user_id || current_user_can('manage_options')));

// Handle form submission
if ($can_edit && isset($_POST['poke_hub_save_profile_front']) && wp_verify_nonce($_POST['poke_hub_profile_nonce'], 'poke_hub_save_profile_front')) {
    $profile = [
        'team'               => isset($_POST['team']) ? sanitize_text_field($_POST['team']) : '',
        'friend_code'        => isset($_POST['friend_code']) ? sanitize_text_field($_POST['friend_code']) : '',
        'xp'                 => isset($_POST['xp']) ? absint($_POST['xp']) : 0,
        'country'            => isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '',
        'pokemon_go_username' => isset($_POST['pokemon_go_username']) ? sanitize_text_field($_POST['pokemon_go_username']) : '',
        'scatterbug_pattern' => isset($_POST['scatterbug_pattern']) ? sanitize_text_field($_POST['scatterbug_pattern']) : '',
        'reasons'            => isset($_POST['reasons']) && is_array($_POST['reasons']) ? array_map('sanitize_text_field', $_POST['reasons']) : [],
    ];

    if (function_exists('poke_hub_save_user_profile')) {
        $save_result = poke_hub_save_user_profile($user_id, $profile);
        if ($save_result) {
            // Force Ultimate Member to refetch user data
            // Note: poke_hub_save_user_profile() already purges UM cache internally
            if (function_exists('poke_hub_purge_um_user_cache')) {
                poke_hub_purge_um_user_cache($user_id);
            }
            $profile_saved = true;
        }
    }
}

// Get user profile (check if functions are available)
if (!function_exists('poke_hub_get_user_profile')) {
    return;
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

// Ensure profile reasons is an array
if (!isset($profile['reasons']) || !is_array($profile['reasons'])) {
    $profile['reasons'] = [];
}
// Double-check: ensure all reasons are strings for comparison
$profile['reasons'] = array_map('strval', $profile['reasons']);

?>

<div class="me5rine-lab-profile-container">
    <?php if (isset($profile_saved) && $profile_saved) : ?>
        <div class="me5rine-lab-form-message me5rine-lab-form-message-success">
            <p><?php esc_html_e('Pokémon GO profile updated successfully', 'poke-hub'); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($can_edit) : ?>
        <form method="post" action="" class="me5rine-lab-form-section">
            <?php wp_nonce_field('poke_hub_save_profile_front', 'poke_hub_profile_nonce'); ?>
            
            <!-- Row 1: Username and Country -->
            <div class="me5rine-lab-form-row">
                <div class="me5rine-lab-form-col">
                    <div class="me5rine-lab-form-field">
                        <label class="me5rine-lab-form-label" for="pokemon_go_username"><?php esc_html_e('Pokémon GO Username', 'poke-hub'); ?></label>
                        <input type="text" name="pokemon_go_username" id="pokemon_go_username" value="<?php echo esc_attr($profile['pokemon_go_username'] ?? ''); ?>" class="me5rine-lab-form-input" placeholder="<?php esc_attr_e('Your in-game username', 'poke-hub'); ?>">
                    </div>
                </div>
                <div class="me5rine-lab-form-col">
                    <div class="me5rine-lab-form-field">
                        <label class="me5rine-lab-form-label" for="country"><?php esc_html_e('Country', 'poke-hub'); ?></label>
                        <select name="country" id="country" class="me5rine-lab-form-select<?php echo empty($profile['country']) ? ' me5rine-lab-form-select-placeholder' : ''; ?>">
                            <option value=""<?php echo empty($profile['country']) ? ' selected' : ''; ?>><?php esc_html_e('-- Select a country --', 'poke-hub'); ?></option>
                            <?php foreach ($countries as $code => $label) : ?>
                                <option value="<?php echo esc_attr($label); ?>" <?php selected($profile['country'] ?? '', $label); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="me5rine-lab-form-description"><?php esc_html_e('Your country. This will be synchronized with Ultimate Member.', 'poke-hub'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Row 2: Team and Friend Code -->
            <div class="me5rine-lab-form-row">
                <div class="me5rine-lab-form-col">
                    <div class="me5rine-lab-form-field">
                        <label class="me5rine-lab-form-label" for="team"><?php esc_html_e('Team', 'poke-hub'); ?></label>
                        <select name="team" id="team" class="me5rine-lab-form-select<?php echo empty($profile['team']) ? ' me5rine-lab-form-select-placeholder' : ''; ?>">
                            <option value=""<?php echo empty($profile['team']) ? ' selected' : ''; ?>><?php esc_html_e('-- Select a team --', 'poke-hub'); ?></option>
                            <?php foreach ($teams as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($profile['team'] ?? '', $value); ?>>
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
                        $formatted_friend_code = '';
                        if (!empty($profile['friend_code']) && function_exists('poke_hub_format_friend_code')) {
                            $formatted_friend_code = poke_hub_format_friend_code($profile['friend_code']);
                        } elseif (!empty($profile['friend_code'])) {
                            $formatted_friend_code = $profile['friend_code'];
                        }
                        ?>
                        <input type="text" name="friend_code" id="friend_code" value="<?php echo esc_attr($formatted_friend_code); ?>" class="me5rine-lab-form-input" placeholder="1234 5678 9012" maxlength="14" pattern="[0-9\s]{0,14}" title="<?php esc_attr_e('The friend code must be exactly 12 digits (e.g., 1234 5678 9012)', 'poke-hub'); ?>">
                        <div class="me5rine-lab-form-description"><?php esc_html_e('Your Pokémon GO friend code', 'poke-hub'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Row 3: XP and Scatterbug Pattern -->
            <div class="me5rine-lab-form-row">
                <div class="me5rine-lab-form-col">
                    <div class="me5rine-lab-form-field">
                        <label class="me5rine-lab-form-label" for="xp"><?php esc_html_e('XP', 'poke-hub'); ?></label>
                        <?php 
                        // Format XP for display in input (with spaces)
                        $formatted_xp = '';
                        if (!empty($profile['xp']) && function_exists('poke_hub_format_xp')) {
                            $formatted_xp = poke_hub_format_xp($profile['xp']);
                        } elseif (isset($profile['xp'])) {
                            $formatted_xp = $profile['xp'];
                        }
                        ?>
                        <input type="text" name="xp" id="xp" value="<?php echo esc_attr($formatted_xp); ?>" class="me5rine-lab-form-input" pattern="[0-9\s]*" placeholder="0">
                        <div class="me5rine-lab-form-description"><?php esc_html_e('Your total XP in Pokémon GO', 'poke-hub'); ?></div>
                    </div>
                </div>
                <div class="me5rine-lab-form-col">
                    <div class="me5rine-lab-form-field">
                        <label class="me5rine-lab-form-label" for="scatterbug_pattern"><?php esc_html_e('Scatterbug Pattern', 'poke-hub'); ?></label>
                        <select name="scatterbug_pattern" id="scatterbug_pattern" class="me5rine-lab-form-select<?php echo empty($profile['scatterbug_pattern']) ? ' me5rine-lab-form-select-placeholder' : ''; ?>">
                            <option value=""<?php echo empty($profile['scatterbug_pattern']) ? ' selected' : ''; ?>><?php esc_html_e('-- Select a pattern --', 'poke-hub'); ?></option>
                            <?php foreach ($scatterbug_patterns as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($profile['scatterbug_pattern'] ?? '', $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="me5rine-lab-form-description"><?php esc_html_e('The Scatterbug/Vivillon pattern for your region', 'poke-hub'); ?></div>
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
                            // Ensure value is string for comparison (profile['reasons'] is already normalized as strings)
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
    <?php else : ?>
        <div class="me5rine-lab-form-view">
            <!-- Row 1: Username and Country -->
            <div class="me5rine-lab-form-view-row">
                <div class="me5rine-lab-form-view-item me5rine-lab-form-col">
                    <span class="me5rine-lab-form-view-label"><?php esc_html_e('Pokémon GO Username', 'poke-hub'); ?></span>
                    <span class="me5rine-lab-form-view-value"><?php echo esc_html(!empty($profile['pokemon_go_username']) ? $profile['pokemon_go_username'] : '—'); ?></span>
                </div>
                <div class="me5rine-lab-form-view-item me5rine-lab-form-col">
                    <span class="me5rine-lab-form-view-label"><?php esc_html_e('Country', 'poke-hub'); ?></span>
                    <span class="me5rine-lab-form-view-value"><?php echo esc_html(!empty($profile['country']) ? $profile['country'] : '—'); ?></span>
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
                    <span class="me5rine-lab-form-view-value"><?php echo esc_html(!empty($profile['scatterbug_pattern']) ? $profile['scatterbug_pattern'] : '—'); ?></span>
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

