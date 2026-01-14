<?php
// modules/games/public/games-shortcode-leaderboard.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode [games_leaderboard] to display the general leaderboard for mini-games
 * 
 * Attributes:
 * - period : daily, weekly, monthly, yearly, total (default: daily)
 * - limit : number of results (default: 10)
 */
function poke_hub_shortcode_games_leaderboard($atts) {
    $atts = shortcode_atts([
        'period' => 'daily',
        'limit' => 10,
    ], $atts, 'games_leaderboard');
    
    $period_type = sanitize_text_field($atts['period']);
    $limit = (int) $atts['limit'];
    
    // Valider le type de période
    $valid_periods = ['daily', 'weekly', 'monthly', 'yearly', 'total'];
    if (!in_array($period_type, $valid_periods, true)) {
        $period_type = 'daily';
    }
    
    // Calculer la date de début de période
    $today = current_time('Y-m-d');
    $date_obj = new DateTime($today);
    $period_start = $today;
    
    if ($period_type === 'weekly') {
        $date_obj->modify('monday this week');
        $period_start = $date_obj->format('Y-m-d');
    } elseif ($period_type === 'monthly') {
        $period_start = $date_obj->format('Y-m-01');
    } elseif ($period_type === 'yearly') {
        $period_start = $date_obj->format('Y-01-01');
    } elseif ($period_type === 'total') {
        $period_start = '1970-01-01';
    }
    
    // Récupérer le classement
    $leaderboard = poke_hub_games_get_leaderboard($period_type, $period_start, $limit);
    
    // Period labels
    $period_labels = [
        'daily' => __('Daily', 'poke-hub'),
        'weekly' => __('Weekly', 'poke-hub'),
        'monthly' => __('Monthly', 'poke-hub'),
        'yearly' => __('Yearly', 'poke-hub'),
        'total' => __('Total', 'poke-hub'),
    ];
    
    ob_start();
    ?>
    <div id="poke-hub-games-leaderboard" class="poke-hub-games-leaderboard">
        <div class="me5rine-lab-dashboard-header">
            <h2 class="me5rine-lab-title-large"><?php echo esc_html__('Games Leaderboard', 'poke-hub'); ?></h2>
        </div>
        
        <div class="me5rine-lab-filters">
            <div class="me5rine-lab-filter-group">
                <label for="leaderboard-period" class="me5rine-lab-form-label me5rine-lab-filter-label">
                    <?php echo esc_html__('Period', 'poke-hub'); ?>
                </label>
                <select id="leaderboard-period" class="me5rine-lab-form-select me5rine-lab-filter-select">
                    <?php foreach ($period_labels as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($period_type, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div id="leaderboard-content" class="poke-hub-leaderboard-content">
            <?php if (!empty($leaderboard)): ?>
                <div class="poke-hub-leaderboard-list">
                    <?php 
                    $position = 1;
                    foreach ($leaderboard as $entry): 
                        $rank_class = $position === 1 ? 'rank-1' : ($position === 2 ? 'rank-2' : ($position === 3 ? 'rank-3' : ''));
                    ?>
                        <div class="me5rine-lab-card poke-hub-leaderboard-item <?php echo esc_attr($rank_class); ?>">
                            <div class="poke-hub-leaderboard-rank"><?php echo esc_html($position++); ?></div>
                            <div class="poke-hub-leaderboard-avatar">
                                <?php echo esc_html(substr($entry['display_name'] ?: __('Anonymous', 'poke-hub'), 0, 2)); ?>
                            </div>
                            <div class="poke-hub-leaderboard-info">
                                <div class="poke-hub-leaderboard-name"><?php echo esc_html($entry['display_name'] ?: __('Anonymous', 'poke-hub')); ?></div>
                                <div class="poke-hub-leaderboard-stats">
                                    <span class="poke-hub-leaderboard-points">
                                        <strong><?php echo esc_html(number_format($entry['points'], 0, ',', ' ')); ?></strong> <?php echo esc_html__('points', 'poke-hub'); ?>
                                    </span>
                                    <span class="poke-hub-leaderboard-games">
                                        <?php echo esc_html($entry['games_succeeded']); ?>/<?php echo esc_html($entry['games_completed']); ?> <?php echo esc_html__('games succeeded', 'poke-hub'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="me5rine-lab-form-message"><?php echo esc_html__('No scores for this period.', 'poke-hub'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <script type="text/javascript">
    (function($) {
        'use strict';
        
        $(document).ready(function() {
            $('#leaderboard-period').on('change', function() {
                const period = $(this).val();
                const limit = <?php echo (int) $limit; ?>;
                const $content = $('#leaderboard-content');
                
                // Show loading state
                $content.html('<p class="me5rine-lab-form-message"><?php echo esc_js(__('Loading...', 'poke-hub')); ?></p>');
                
                $.ajax({
                    url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: {
                        action: 'poke_hub_games_get_general_leaderboard',
                        nonce: '<?php echo wp_create_nonce('poke_hub_pokedle_nonce'); ?>',
                        period_type: period,
                        limit: limit
                    },
                    success: function(response) {
                        if (response.success && response.data && response.data.leaderboard) {
                            let html = '';
                            if (response.data.leaderboard.length > 0) {
                                html = '<div class="poke-hub-leaderboard-list">';
                                response.data.leaderboard.forEach(function(entry, index) {
                                    const position = index + 1;
                                    const rankClass = position === 1 ? 'rank-1' : (position === 2 ? 'rank-2' : (position === 3 ? 'rank-3' : ''));
                                    const displayName = entry.display_name || '<?php echo esc_js(__('Anonymous', 'poke-hub')); ?>';
                                    const initials = displayName.substring(0, 2).toUpperCase();
                                    
                                    html += '<div class="me5rine-lab-card poke-hub-leaderboard-item ' + rankClass + '">';
                                    html += '<div class="poke-hub-leaderboard-rank">' + position + '</div>';
                                    html += '<div class="poke-hub-leaderboard-avatar">' + initials + '</div>';
                                    html += '<div class="poke-hub-leaderboard-info">';
                                    html += '<div class="poke-hub-leaderboard-name">' + displayName + '</div>';
                                    html += '<div class="poke-hub-leaderboard-stats">';
                                    html += '<span class="poke-hub-leaderboard-points"><strong>' + entry.points.toLocaleString() + '</strong> <?php echo esc_js(__('points', 'poke-hub')); ?></span>';
                                    html += '<span class="poke-hub-leaderboard-games">' + entry.games_succeeded + '/' + entry.games_completed + ' <?php echo esc_js(__('games succeeded', 'poke-hub')); ?></span>';
                                    html += '</div>';
                                    html += '</div>';
                                    html += '</div>';
                                });
                                html += '</div>';
                            } else {
                                html = '<p class="me5rine-lab-form-message"><?php echo esc_js(__('No scores for this period.', 'poke-hub')); ?></p>';
                            }
                            $content.html(html);
                        } else {
                            $content.html('<p class="me5rine-lab-form-message"><?php echo esc_js(__('Error loading leaderboard.', 'poke-hub')); ?></p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Leaderboard AJAX error:', status, error);
                        $content.html('<p class="me5rine-lab-form-message"><?php echo esc_js(__('Error loading leaderboard. Please try again.', 'poke-hub')); ?></p>');
                    }
                });
            });
        });
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('games_leaderboard', 'poke_hub_shortcode_games_leaderboard');

