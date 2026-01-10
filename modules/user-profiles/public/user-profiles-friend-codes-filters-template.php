<?php
// modules/user-profiles/public/user-profiles-friend-codes-filters-template.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template réutilisable pour les filtres de codes amis
 * 
 * @param array $args {
 *     @type string $context Context ('friend_codes' or 'vivillon')
 *     @type array $countries List of countries
 *     @type array $scatterbug_patterns List of scatterbug patterns (for vivillon)
 *     @type array $teams List of teams (for friend_codes)
 *     @type array $reasons List of reasons (for friend_codes)
 *     @type string $filter_country Current country filter value
 *     @type string $filter_pattern Current pattern filter value (for vivillon)
 *     @type string $filter_team Current team filter value (for friend_codes)
 *     @type string $filter_reason Current reason filter value (for friend_codes)
 * }
 */
function poke_hub_render_friend_codes_filters($args = []) {
    $defaults = [
        'context' => 'friend_codes',
        'countries' => [],
        'scatterbug_patterns' => [],
        'teams' => [],
        'reasons' => [],
        'filter_country' => '',
        'filter_pattern' => '',
        'filter_team' => '',
        'filter_reason' => '',
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    // Determine title and filter keys based on context
    if ($args['context'] === 'vivillon') {
        $title = __('Filter by Pattern and Country', 'poke-hub');
        $exclude_keys = ['country', 'pattern', 'pg'];
        $reset_keys = ['country', 'pattern', 'pg'];
    } else {
        $title = __('Filter Codes', 'poke-hub');
        $exclude_keys = ['country', 'team', 'reason', 'pg'];
        $reset_keys = ['country', 'team', 'reason', 'pg'];
    }
    
    // Check if any filter is active
    $has_active_filters = !empty($args['filter_country']) || 
                         !empty($args['filter_pattern']) || 
                         !empty($args['filter_team']) || 
                         !empty($args['filter_reason']);
    
    ?>
    <div class="me5rine-lab-form-block">
        <h3 class="me5rine-lab-title-medium"><?php echo esc_html($title); ?></h3>
        <form method="get" action="">
            <?php
            // Preserve other query vars
            foreach ($_GET as $key => $value) {
                if (!in_array($key, $exclude_keys)) {
                    echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                }
            }
            ?>
            
            <div class="me5rine-lab-form-row me5rine-lab-form-col-gap">
                <?php if ($args['context'] === 'vivillon' && !empty($args['scatterbug_patterns'])) : ?>
                    <div class="me5rine-lab-form-col">
                        <div class="me5rine-lab-form-field">
                            <label class="me5rine-lab-form-label" for="filter_pattern"><?php esc_html_e('Vivillon Pattern', 'poke-hub'); ?></label>
                            <select name="pattern" id="filter_pattern" class="me5rine-lab-form-select">
                                <option value=""><?php esc_html_e('-- All patterns --', 'poke-hub'); ?></option>
                                <?php foreach ($args['scatterbug_patterns'] as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($args['filter_pattern'], $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($args['countries'])) : ?>
                    <div class="me5rine-lab-form-col">
                        <div class="me5rine-lab-form-field">
                            <label class="me5rine-lab-form-label" for="filter_country"><?php esc_html_e('Country', 'poke-hub'); ?></label>
                            <select name="country" id="filter_country" class="me5rine-lab-form-select">
                                <option value=""><?php esc_html_e('-- All countries --', 'poke-hub'); ?></option>
                                <?php foreach ($args['countries'] as $code => $label) : ?>
                                    <option value="<?php echo esc_attr($label); ?>" <?php selected($args['filter_country'], $label); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($args['context'] === 'friend_codes' && !empty($args['teams'])) : ?>
                    <div class="me5rine-lab-form-col">
                        <div class="me5rine-lab-form-field">
                            <label class="me5rine-lab-form-label" for="filter_team"><?php esc_html_e('Team', 'poke-hub'); ?></label>
                            <select name="team" id="filter_team" class="me5rine-lab-form-select">
                                <option value=""><?php esc_html_e('-- All teams --', 'poke-hub'); ?></option>
                                <?php foreach ($args['teams'] as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($args['filter_team'], $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($args['context'] === 'friend_codes' && !empty($args['reasons'])) : ?>
                    <div class="me5rine-lab-form-col">
                        <div class="me5rine-lab-form-field">
                            <label class="me5rine-lab-form-label" for="filter_reason"><?php esc_html_e('Reason', 'poke-hub'); ?></label>
                            <select name="reason" id="filter_reason" class="me5rine-lab-form-select">
                                <option value=""><?php esc_html_e('-- All reasons --', 'poke-hub'); ?></option>
                                <?php foreach ($args['reasons'] as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($args['filter_reason'], $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="me5rine-lab-form-field">
                <button type="submit" class="me5rine-lab-form-button me5rine-lab-form-button-secondary">
                    <?php esc_html_e('Filter', 'poke-hub'); ?>
                </button>
                <?php if ($has_active_filters) : ?>
                    <a href="<?php echo esc_url(remove_query_arg($reset_keys)); ?>" class="me5rine-lab-form-button me5rine-lab-form-button-secondary">
                        <?php esc_html_e('Reset', 'poke-hub'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php
}

