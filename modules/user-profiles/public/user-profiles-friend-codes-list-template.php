<?php
// modules/user-profiles/public/user-profiles-friend-codes-list-template.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template réutilisable pour afficher la liste des codes amis
 * 
 * @param array $args {
 *     @type array $friend_codes Array of friend codes with items, total, total_pages
 *     @type int $paged Current page number
 *     @type array $teams List of teams for labels
 *     @type string $context Context ('friend_codes' or 'vivillon') for specific classes
 *     @type string $empty_message Message to display when no codes found
 * }
 */
function poke_hub_render_friend_codes_list($args = []) {
    $defaults = [
        'friend_codes' => [
            'items' => [],
            'total' => 0,
            'total_pages' => 0,
        ],
        'paged' => 1,
        'teams' => [],
        'context' => 'friend_codes',
        'empty_message' => __('No friend code found. Be the first to add one!', 'poke-hub'),
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    $dashboard_class = $args['context'] === 'vivillon' ? 'vivillon-dashboard' : 'friend-codes-dashboard';
    $list_class = $args['context'] === 'vivillon' ? 'user-profiles-vivillon-codes-list' : 'user-profiles-friend-codes-list';
    
    ?>
    <div class="me5rine-lab-form-block">
        <h3 class="me5rine-lab-title-medium"><?php esc_html_e('Available Friend Codes', 'poke-hub'); ?></h3>
        
        <?php if (empty($args['friend_codes']['items'])) : ?>
            <p class="me5rine-lab-form-description user-profiles-friend-codes-empty">
                <?php echo esc_html($args['empty_message']); ?>
            </p>
        <?php else : ?>
            <div class="me5rine-lab-card-list <?php echo esc_attr($list_class); ?>">
                <?php foreach ($args['friend_codes']['items'] as $code) : ?>
                    <?php
                    $formatted_code = function_exists('poke_hub_format_friend_code') 
                        ? poke_hub_format_friend_code($code['friend_code']) 
                        : chunk_split($code['friend_code'], 4, ' ');
                    $qr_url = function_exists('poke_hub_generate_friend_code_qr') 
                        ? poke_hub_generate_friend_code_qr($code['friend_code']) 
                        : '';
                    ?>
                    <div class="me5rine-lab-card me5rine-lab-card-bordered user-profiles-friend-code-card">
                        <div class="user-profiles-friend-code-content">
                            <div class="user-profiles-friend-code-header">
                                <strong><?php esc_html_e('Code:', 'poke-hub'); ?></strong>
                                <span class="poke-hub-friend-code-value" data-code="<?php echo esc_attr($code['friend_code']); ?>">
                                    <?php echo esc_html($formatted_code); ?>
                                </span>
                                <button type="button" 
                                        class="poke-hub-friend-code-copy" 
                                        data-code="<?php echo esc_attr(preg_replace('/[^0-9]/', '', $code['friend_code'])); ?>"
                                        title="<?php esc_attr_e('Copy code', 'poke-hub'); ?>"
                                        aria-label="<?php esc_attr_e('Copy code', 'poke-hub'); ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                </button>
                            </div>
                            
                            <?php if (!empty($code['pokemon_go_username'])) : ?>
                                <div class="user-profiles-friend-code-username">
                                    <strong><?php esc_html_e('Name:', 'poke-hub'); ?></strong>
                                    <?php echo esc_html($code['pokemon_go_username']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="user-profiles-friend-code-meta">
                                <?php if (!empty($code['country'])) : ?>
                                    <span class="user-profiles-friend-code-meta-item">
                                        <strong><?php esc_html_e('Country:', 'poke-hub'); ?></strong>
                                        <?php echo esc_html($code['country']); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($code['scatterbug_pattern'])) : ?>
                                    <span class="user-profiles-friend-code-meta-item">
                                        <strong><?php echo $args['context'] === 'vivillon' ? esc_html__('Pattern:', 'poke-hub') : esc_html__('Scatterbug Pattern:', 'poke-hub'); ?></strong>
                                        <?php echo esc_html($code['scatterbug_pattern']); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($code['team'])) : ?>
                                    <span class="user-profiles-friend-code-meta-item">
                                        <strong><?php esc_html_e('Team:', 'poke-hub'); ?></strong>
                                        <?php 
                                        $team_label = isset($args['teams'][$code['team']]) ? $args['teams'][$code['team']] : $code['team'];
                                        echo esc_html($team_label); 
                                        ?>
                                    </span>
                                <?php endif; ?>
                                
                                <span class="user-profiles-friend-code-meta-item">
                                    <?php 
                                    printf(
                                        esc_html__('Added on %s', 'poke-hub'),
                                        date_i18n(get_option('date_format'), strtotime($code['created_at']))
                                    );
                                    ?>
                                </span>
                            </div>
                            
                            <div class="user-profiles-friend-code-time">
                                <?php
                                $created_timestamp = strtotime($code['created_at']);
                                $time_ago = human_time_diff($created_timestamp, current_time('timestamp'));
                                printf(
                                    esc_html__('%s ago', 'poke-hub'),
                                    $time_ago
                                );
                                ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($qr_url)) : ?>
                            <div class="user-profiles-friend-code-qr">
                                <img src="<?php echo esc_url($qr_url); ?>" 
                                     alt="<?php esc_attr_e('QR Code', 'poke-hub'); ?>"
                                     class="user-profiles-friend-code-qr-image">
                                <small class="user-profiles-friend-code-qr-caption"><?php esc_html_e('Scan to add', 'poke-hub'); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($args['friend_codes']['total_pages'] > 1) : ?>
                <?php
                if (function_exists('poke_hub_render_pagination')) {
                    echo poke_hub_render_pagination([
                        'total_items' => $args['friend_codes']['total'],
                        'paged' => $args['paged'],
                        'total_pages' => $args['friend_codes']['total_pages'],
                        'page_var' => 'pg',
                        'text_domain' => 'poke-hub',
                    ]);
                }
                ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

