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
        poke_hub_save_user_profile($user_id, $profile);
        echo '<div class="um-notice um-notice-success"><p>' . esc_html__('Profile saved successfully.', 'poke-hub') . '</p></div>';
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

// Get country from Ultimate Member
if (function_exists('poke_hub_get_user_country')) {
    $current_country = poke_hub_get_user_country($user_id);
    if (empty($profile['country']) && !empty($current_country)) {
        $profile['country'] = $current_country;
    }
}
?>

<div class="um-profile-note pokehub-profile-tab">
    <?php if ($can_edit) : ?>
        <form method="post" action="" class="pokehub-profile-form">
            <?php wp_nonce_field('poke_hub_save_profile_front', 'poke_hub_profile_nonce'); ?>
            
            <div class="um-field">
                <div class="um-field-label">
                    <label for="team"><?php esc_html_e('Team', 'poke-hub'); ?></label>
                </div>
                <div class="um-field-area">
                    <select name="team" id="team" class="um-form-field">
                        <option value=""><?php esc_html_e('-- Select a team --', 'poke-hub'); ?></option>
                        <?php foreach ($teams as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($profile['team'], $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="um-field">
                <div class="um-field-label">
                    <label for="friend_code"><?php esc_html_e('Friend Code', 'poke-hub'); ?></label>
                </div>
                <div class="um-field-area">
                    <input type="text" name="friend_code" id="friend_code" value="<?php echo esc_attr($profile['friend_code']); ?>" class="um-form-field" placeholder="1234 5678 9012">
                    <div class="um-field-description"><?php esc_html_e('Your Pokémon GO friend code', 'poke-hub'); ?></div>
                </div>
            </div>

            <div class="um-field">
                <div class="um-field-label">
                    <label for="xp"><?php esc_html_e('XP', 'poke-hub'); ?></label>
                </div>
                <div class="um-field-area">
                    <input type="number" name="xp" id="xp" value="<?php echo esc_attr($profile['xp']); ?>" class="um-form-field" min="0" step="1">
                    <div class="um-field-description"><?php esc_html_e('Your total XP in Pokémon GO', 'poke-hub'); ?></div>
                </div>
            </div>

            <div class="um-field">
                <div class="um-field-label">
                    <label for="country"><?php esc_html_e('Country', 'poke-hub'); ?></label>
                </div>
                <div class="um-field-area">
                    <input type="text" name="country" id="country" value="<?php echo esc_attr($profile['country']); ?>" class="um-form-field">
                    <div class="um-field-description">
                        <?php esc_html_e('Country code (e.g., FR, US, GB). This will be synchronized with Ultimate Member.', 'poke-hub'); ?>
                    </div>
                </div>
            </div>

            <div class="um-field">
                <div class="um-field-label">
                    <label for="pokemon_go_username"><?php esc_html_e('Pokémon GO Username', 'poke-hub'); ?></label>
                </div>
                <div class="um-field-area">
                    <input type="text" name="pokemon_go_username" id="pokemon_go_username" value="<?php echo esc_attr($profile['pokemon_go_username']); ?>" class="um-form-field">
                    <div class="um-field-description"><?php esc_html_e('Your in-game username', 'poke-hub'); ?></div>
                </div>
            </div>

            <div class="um-field">
                <div class="um-field-label">
                    <label for="scatterbug_pattern"><?php esc_html_e('Scatterbug Pattern', 'poke-hub'); ?></label>
                </div>
                <div class="um-field-area">
                    <select name="scatterbug_pattern" id="scatterbug_pattern" class="um-form-field">
                        <option value=""><?php esc_html_e('-- Select a pattern --', 'poke-hub'); ?></option>
                        <?php foreach ($scatterbug_patterns as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($profile['scatterbug_pattern'], $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="um-field-description"><?php esc_html_e('The Scatterbug/Vivillon pattern for your region', 'poke-hub'); ?></div>
                </div>
            </div>

            <div class="um-field">
                <div class="um-field-label">
                    <label><?php esc_html_e('Reasons', 'poke-hub'); ?></label>
                </div>
                <div class="um-field-area">
                    <div class="um-field-checkbox-list">
                        <?php foreach ($reasons as $value => $label) : ?>
                            <?php
                            // Ensure value is string for comparison (profile['reasons'] is already normalized as strings)
                            $value_str = (string) $value;
                            $is_checked = in_array($value_str, $profile['reasons'], true);
                            ?>
                            <label class="um-field-checkbox">
                                <input type="checkbox" name="reasons[]" value="<?php echo esc_attr($value); ?>" <?php checked($is_checked); ?>>
                                <span class="um-field-checkbox-state"><i class="<?php echo $is_checked ? 'um-icon-android-checkbox' : 'um-icon-android-checkbox-outline-blank'; ?>"></i></span>
                                <span class="um-field-checkbox-option"><?php echo esc_html($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="um-field-description"><?php esc_html_e('Select why you are here (you can select multiple options)', 'poke-hub'); ?></div>
                </div>
            </div>

            <div class="um-field">
                <input type="hidden" name="poke_hub_save_profile_front" value="1">
                <input type="submit" value="<?php esc_attr_e('Save Profile', 'poke-hub'); ?>" class="um-button">
            </div>
        </form>
    <?php else : ?>
        <div class="um-profile-note">
            <div class="pokehub-profile-view">
                <div class="pokehub-profile-field">
                    <strong><?php esc_html_e('Team', 'poke-hub'); ?>:</strong>
                    <span><?php echo esc_html(!empty($profile['team']) ? ($teams[$profile['team']] ?? $profile['team']) : '—'); ?></span>
                </div>
                <div class="pokehub-profile-field">
                    <strong><?php esc_html_e('Friend Code', 'poke-hub'); ?>:</strong>
                    <span><?php echo esc_html($profile['friend_code'] ?: '—'); ?></span>
                </div>
                <div class="pokehub-profile-field">
                    <strong><?php esc_html_e('XP', 'poke-hub'); ?>:</strong>
                    <span><?php echo esc_html($profile['xp'] ? number_format($profile['xp'], 0, ',', ' ') : '—'); ?></span>
                </div>
                <div class="pokehub-profile-field">
                    <strong><?php esc_html_e('Country', 'poke-hub'); ?>:</strong>
                    <span><?php echo esc_html($profile['country'] ?: '—'); ?></span>
                </div>
                <div class="pokehub-profile-field">
                    <strong><?php esc_html_e('Pokémon GO Username', 'poke-hub'); ?>:</strong>
                    <span><?php echo esc_html($profile['pokemon_go_username'] ?: '—'); ?></span>
                </div>
                <div class="pokehub-profile-field">
                    <strong><?php esc_html_e('Scatterbug Pattern', 'poke-hub'); ?>:</strong>
                    <span><?php echo esc_html($profile['scatterbug_pattern'] ?: '—'); ?></span>
                </div>
                <?php if (!empty($profile['reasons'])) : ?>
                    <div class="pokehub-profile-field">
                        <strong><?php esc_html_e('Reasons', 'poke-hub'); ?>:</strong>
                        <span><?php
                            $reason_labels = array_map(function($reason) use ($reasons) {
                                return $reasons[$reason] ?? $reason;
                            }, $profile['reasons']);
                            echo esc_html(implode(', ', $reason_labels));
                        ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

