<?php
// modules/user-profiles/public/user-profiles-friend-codes-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render friend code add/edit form (reusable for both friend codes and vivillon pages)
 * 
 * @param array $args {
 *     @type string $context 'friend_codes' or 'vivillon'
 *     @type array $existing_profile Existing user profile data
 *     @type array $countries List of countries
 *     @type array $scatterbug_patterns List of scatterbug patterns
 *     @type array $teams List of teams
 *     @type bool $is_logged_in Whether user is logged in
 *     @type string $profile_url User profile URL (if logged in)
 *     @type string $form_message Form message to display
 *     @type string $form_message_type Message type ('success', 'error', 'warning')
 *     @type bool $needs_link_confirmation Whether link confirmation is needed
 *     @type array $pending_link_data Data for link confirmation
 *     @type bool $require_pattern Whether scatterbug pattern is required
 * }
 */
function poke_hub_render_friend_code_form($args = []) {
    $defaults = [
        'context' => 'friend_codes',
        'existing_profile' => [],
        'countries' => [],
        'scatterbug_patterns' => [],
        'teams' => [],
        'is_logged_in' => false,
        'profile_url' => '',
        'form_message' => '',
        'form_message_type' => '',
        'needs_link_confirmation' => false,
        'pending_link_data' => [],
        'require_pattern' => false,
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    // Determine form action and nonce names based on context
    $form_action = $args['context'] === 'vivillon' ? 'poke_hub_add_vivillon_code' : 'poke_hub_add_friend_code';
    $nonce_name = $args['context'] === 'vivillon' ? 'poke_hub_vivillon_code_nonce' : 'poke_hub_friend_code_nonce';
    $nonce_action = $args['context'] === 'vivillon' ? 'poke_hub_add_vivillon_code' : 'poke_hub_add_friend_code';
    
    // Determine title and button text
    if ($args['context'] === 'vivillon') {
        $title = $args['is_logged_in'] && !empty($args['existing_profile']['friend_code']) && !empty($args['existing_profile']['scatterbug_pattern'])
            ? __('Edit My Vivillon Friend Code', 'poke-hub')
            : __('Add My Vivillon Friend Code', 'poke-hub');
        $button_text = $args['is_logged_in'] && !empty($args['existing_profile']['friend_code']) && !empty($args['existing_profile']['scatterbug_pattern'])
            ? __('Update My Vivillon Friend Code', 'poke-hub')
            : __('Add My Vivillon Friend Code', 'poke-hub');
    } else {
        $title = __('Add My Friend Code', 'poke-hub');
        $button_text = __('Add My Friend Code', 'poke-hub');
    }
    
    // Get selected values from POST or existing profile
    $friend_code_value = '';
    if (isset($_POST['friend_code'])) {
        $friend_code_value = $_POST['friend_code'];
    } elseif (!empty($args['existing_profile']['friend_code'])) {
        $existing_code = $args['existing_profile']['friend_code'];
        $friend_code_value = chunk_split($existing_code, 4, ' ');
    }
    
    $pokemon_go_username_value = '';
    if (isset($_POST['pokemon_go_username'])) {
        $pokemon_go_username_value = $_POST['pokemon_go_username'];
    } elseif (!empty($args['existing_profile']['pokemon_go_username'])) {
        $pokemon_go_username_value = $args['existing_profile']['pokemon_go_username'];
    }
    
    $selected_country = '';
    if (isset($_POST['country'])) {
        $selected_country = $_POST['country'];
    } elseif (!empty($args['existing_profile']['country'])) {
        $selected_country = $args['existing_profile']['country'];
    }
    
    $selected_pattern = '';
    if (isset($_POST['scatterbug_pattern'])) {
        $selected_pattern = $_POST['scatterbug_pattern'];
    } elseif (!empty($args['existing_profile']['scatterbug_pattern'])) {
        $selected_pattern = $args['existing_profile']['scatterbug_pattern'];
    }
    
    $selected_team = '';
    if (isset($_POST['team'])) {
        $selected_team = $_POST['team'];
    } elseif (!empty($args['existing_profile']['team'])) {
        $selected_team = $args['existing_profile']['team'];
    }
    
    ?>
    <div class="me5rine-lab-form-block">
        <h3 class="me5rine-lab-title-medium"><?php echo esc_html($title); ?></h3>
        
        <?php if (!empty($args['form_message'])) : ?>
            <div class="me5rine-lab-form-message me5rine-lab-form-message-<?php echo esc_attr($args['form_message_type']); ?>">
                <p><?php echo esc_html($args['form_message']); ?></p>
                <?php if ($args['needs_link_confirmation']) : ?>
                    <form method="post" action="" class="user-profiles-friend-code-form-link-confirmation">
                        <?php wp_nonce_field($nonce_action, $nonce_name); ?>
                        <input type="hidden" name="link_existing_code" value="1">
                        <?php foreach ($args['pending_link_data'] as $key => $value) : ?>
                            <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                        <?php endforeach; ?>
                        <button type="submit" name="<?php echo esc_attr($form_action); ?>" class="me5rine-lab-form-button me5rine-lab-form-button-secondary">
                            <?php esc_html_e('Yes, link to my account', 'poke-hub'); ?>
                        </button>
                        <a href="<?php echo esc_url(remove_query_arg([])); ?>" class="me5rine-lab-form-button me5rine-lab-form-button-secondary">
                            <?php esc_html_e('Cancel', 'poke-hub'); ?>
                        </a>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$args['is_logged_in']) : ?>
            <?php
            // Get login URL with redirect to current page
            $login_url = function_exists('poke_hub_get_login_url_with_redirect') 
                ? poke_hub_get_login_url_with_redirect() 
                : wp_login_url(home_url($_SERVER['REQUEST_URI']));
            ?>
            <div class="me5rine-lab-form-message me5rine-lab-form-message-warning">
                <p>
                    <?php esc_html_e('You are not logged in. You can add or update your friend code once every 2 days. Log in for unlimited updates and more features!', 'poke-hub'); ?>
                    <a href="<?php echo esc_url($login_url); ?>" class="me5rine-lab-form-button me5rine-lab-form-button-secondary me5rine-lab-form-message-button me5rine-lab-form-message-warning-button user-profiles-friend-code-form-login-link">
                        <?php esc_html_e('Log In', 'poke-hub'); ?>
                    </a>
                </p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <?php wp_nonce_field($nonce_action, $nonce_name); ?>
            
            <!-- Row 1: Friend Code (full width) -->
            <div class="me5rine-lab-form-row me5rine-lab-form-col-gap">
                <div class="me5rine-lab-form-col">
                    <div class="me5rine-lab-form-field">
                        <label class="me5rine-lab-form-label" for="friend_code"><?php esc_html_e('Friend Code', 'poke-hub'); ?> <span class="user-profiles-friend-code-form-required">*</span></label>
                        <input type="text" 
                               name="friend_code" 
                               id="friend_code" 
                               class="me5rine-lab-form-input" 
                               placeholder="1234 5678 9012" 
                               maxlength="14" 
                               pattern="[0-9\s]{0,14}" 
                               value="<?php echo esc_attr($friend_code_value); ?>"
                               required>
                        <div class="me5rine-lab-form-description"><?php esc_html_e('Format: 12 digits (e.g. 1234 5678 9012)', 'poke-hub'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Row 2: Username and Country -->
            <div class="me5rine-lab-form-row me5rine-lab-form-col-gap">
                <div class="me5rine-lab-form-col">
                    <div class="me5rine-lab-form-field">
                        <label class="me5rine-lab-form-label" for="pokemon_go_username"><?php esc_html_e('Pokémon GO Username', 'poke-hub'); ?></label>
                        <input type="text" 
                               name="pokemon_go_username" 
                               id="pokemon_go_username" 
                               class="me5rine-lab-form-input" 
                               value="<?php echo esc_attr($pokemon_go_username_value); ?>">
                    </div>
                </div>
                
                <?php if (!empty($args['countries'])) : ?>
                    <div class="me5rine-lab-form-col">
                        <div class="me5rine-lab-form-field">
                            <label class="me5rine-lab-form-label" for="country"><?php esc_html_e('Country', 'poke-hub'); ?></label>
                            <select name="country" id="country" class="me5rine-lab-form-select">
                                <option value=""><?php esc_html_e('-- Select a country --', 'poke-hub'); ?></option>
                                <?php foreach ($args['countries'] as $code => $label) : ?>
                                    <option value="<?php echo esc_attr($label); ?>" <?php selected($selected_country, $label); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Row 3: Scatterbug Pattern and Team -->
            <div class="me5rine-lab-form-row me5rine-lab-form-col-gap">
                <?php if (!empty($args['scatterbug_patterns'])) : ?>
                    <div class="me5rine-lab-form-col">
                        <div class="me5rine-lab-form-field">
                            <label class="me5rine-lab-form-label" for="scatterbug_pattern">
                                <?php esc_html_e('Scatterbug Pattern', 'poke-hub'); ?>
                                <?php if ($args['require_pattern']) : ?>
                                    <span class="user-profiles-friend-code-form-required">*</span>
                                <?php endif; ?>
                            </label>
                            <select name="scatterbug_pattern" 
                                    id="scatterbug_pattern" 
                                    class="me5rine-lab-form-select"
                                    <?php echo $args['require_pattern'] ? 'required' : ''; ?>>
                                <option value=""><?php esc_html_e('-- Select a pattern --', 'poke-hub'); ?></option>
                                <?php foreach ($args['scatterbug_patterns'] as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($selected_pattern, $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($args['teams'])) : ?>
                    <div class="me5rine-lab-form-col">
                        <div class="me5rine-lab-form-field">
                            <label class="me5rine-lab-form-label" for="team"><?php esc_html_e('Team', 'poke-hub'); ?></label>
                            <select name="team" id="team" class="me5rine-lab-form-select">
                                <option value=""><?php esc_html_e('-- Select a team --', 'poke-hub'); ?></option>
                                <?php foreach ($args['teams'] as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($selected_team, $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="me5rine-lab-form-field">
                <button type="submit" name="<?php echo esc_attr($form_action); ?>" class="me5rine-lab-form-button">
                    <?php echo esc_html($button_text); ?>
                </button>
                <?php if ($args['is_logged_in'] && !empty($args['profile_url'])) : ?>
                    <p class="user-profiles-friend-code-form-profile-link">
                        <a href="<?php echo esc_url($args['profile_url']); ?>">
                            <?php esc_html_e('More customization options on my profile', 'poke-hub'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php
}

