<?php
// modules/games/admin/games-admin.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin interface for games
 */
function poke_hub_games_admin_ui() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'poke-hub'));
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Games', 'poke-hub'); ?></h1>
        
        <div class="pokehub-games-admin">
            <h2><?php echo esc_html__('Pokedle', 'poke-hub'); ?></h2>
            <p><?php echo esc_html__('Pokedle is a daily game where you have to guess the mystery Pokémon.', 'poke-hub'); ?></p>
            
            <h3><?php echo esc_html__('Statistics', 'poke-hub'); ?></h3>
            <p>
                <strong><?php echo esc_html__('Daily Pokémon:', 'poke-hub'); ?></strong>
                <?php
                $daily_pokemon = poke_hub_pokedle_get_daily_pokemon();
                if ($daily_pokemon) {
                    echo esc_html($daily_pokemon['name_fr'] ?: $daily_pokemon['name_en']);
                } else {
                    echo esc_html__('No Pokémon available', 'poke-hub');
                }
                ?>
            </p>
            
            <h3><?php echo esc_html__('Today\'s Leaderboard', 'poke-hub'); ?></h3>
            <?php
            $today = current_time('Y-m-d');
            $leaderboard = poke_hub_pokedle_get_leaderboard($today, 10);
            
            if (!empty($leaderboard)) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead>';
                echo '<tr>';
                echo '<th>' . esc_html__('Position', 'poke-hub') . '</th>';
                echo '<th>' . esc_html__('Player', 'poke-hub') . '</th>';
                echo '<th>' . esc_html__('Attempts', 'poke-hub') . '</th>';
                echo '<th>' . esc_html__('Time', 'poke-hub') . '</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                $position = 1;
                foreach ($leaderboard as $entry) {
                    echo '<tr>';
                    echo '<td>' . esc_html($position++) . '</td>';
                    echo '<td>' . esc_html($entry['display_name'] ?: __('Anonymous', 'poke-hub')) . '</td>';
                    echo '<td>' . esc_html($entry['attempts']) . '</td>';
                    echo '<td>' . esc_html(gmdate('i:s', $entry['completion_time'])) . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
            } else {
                echo '<p>' . esc_html__('No scores for today.', 'poke-hub') . '</p>';
            }
            ?>
            
            <h3><?php echo esc_html__('General Leaderboard (Points)', 'poke-hub'); ?></h3>
            <?php
            // Display leaderboards by period
            $periods = [
                'daily' => ['label' => __('Daily', 'poke-hub'), 'start' => current_time('Y-m-d')],
                'weekly' => ['label' => __('Weekly', 'poke-hub'), 'start' => date('Y-m-d', strtotime('monday this week'))],
                'monthly' => ['label' => __('Monthly', 'poke-hub'), 'start' => current_time('Y-m-01')],
                'yearly' => ['label' => __('Yearly', 'poke-hub'), 'start' => current_time('Y-01-01')],
                'total' => ['label' => __('Total', 'poke-hub'), 'start' => '1970-01-01'],
            ];
            
            foreach ($periods as $period_type => $period_info) {
                echo '<h4>' . esc_html($period_info['label']) . '</h4>';
                $leaderboard = poke_hub_games_get_leaderboard($period_type, $period_info['start'], 10);
                
                if (!empty($leaderboard)) {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th>' . esc_html__('Position', 'poke-hub') . '</th>';
                    echo '<th>' . esc_html__('Player', 'poke-hub') . '</th>';
                    echo '<th>' . esc_html__('Points', 'poke-hub') . '</th>';
                    echo '<th>' . esc_html__('Games Succeeded', 'poke-hub') . '</th>';
                    echo '<th>' . esc_html__('Games Completed', 'poke-hub') . '</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    
                    $position = 1;
                    foreach ($leaderboard as $entry) {
                        echo '<tr>';
                        echo '<td>' . esc_html($position++) . '</td>';
                        echo '<td>' . esc_html($entry['display_name'] ?: __('Anonymous', 'poke-hub')) . '</td>';
                        echo '<td><strong>' . esc_html(number_format($entry['points'], 0, ',', ' ')) . '</strong></td>';
                        echo '<td>' . esc_html($entry['games_succeeded']) . '</td>';
                        echo '<td>' . esc_html($entry['games_completed']) . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody>';
                    echo '</table>';
                } else {
                    echo '<p>' . esc_html__('No scores for this period.', 'poke-hub') . '</p>';
                }
            }
            ?>
        </div>
    </div>
    <?php
}
