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

    // Récupérer l'onglet actif
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'leaderboard';
    
    // Liste des onglets autorisés
    $allowed_tabs = ['leaderboard', 'settings'];
    if (!in_array($active_tab, $allowed_tabs, true)) {
        $active_tab = 'leaderboard';
    }
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Games', 'poke-hub'); ?></h1>
        
        <nav class="nav-tab-wrapper">
            <a href="?page=poke-hub-games&tab=leaderboard" class="nav-tab <?php echo $active_tab === 'leaderboard' ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html__('Leaderboards', 'poke-hub'); ?>
            </a>
            <a href="?page=poke-hub-games&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html__('Settings', 'poke-hub'); ?>
            </a>
        </nav>
        
        <div class="pokehub-games-admin">
            <?php if ($active_tab === 'leaderboard'): ?>
            <h2><?php echo esc_html__('Pokedle Leaderboards', 'poke-hub'); ?></h2>
            <p><?php echo esc_html__('Pokedle is a daily game where you have to guess the mystery Pokémon.', 'poke-hub'); ?></p>
            
            <h3><?php echo esc_html__('Today\'s Pokémon by Generation', 'poke-hub'); ?></h3>
            <?php
            $all_generations = poke_hub_games_get_all_generations();
            $today = current_time('Y-m-d');
            
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . esc_html__('Generation', 'poke-hub') . '</th>';
            echo '<th>' . esc_html__('Daily Pokémon', 'poke-hub') . '</th>';
            echo '<th>' . esc_html__('Successful Players', 'poke-hub') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            // Toutes générations
            $all_pokemon = poke_hub_games_get_all_pokemon();
            $daily_pokemon_all = poke_hub_pokedle_get_daily_pokemon_from_list($all_pokemon, $today);
            $count_all = poke_hub_pokedle_count_successful_players($today, null);
            echo '<tr>';
            echo '<td><strong>' . esc_html__('All Generations', 'poke-hub') . '</strong></td>';
            if ($daily_pokemon_all) {
                echo '<td>' . esc_html($daily_pokemon_all['name_fr'] ?: $daily_pokemon_all['name_en']) . '</td>';
            } else {
                echo '<td>' . esc_html__('No Pokémon available', 'poke-hub') . '</td>';
            }
            echo '<td>' . esc_html($count_all) . '</td>';
            echo '</tr>';
            
            // Par génération
            foreach ($all_generations as $gen) {
                $gen_pokemon = poke_hub_games_get_pokemon_by_generation((int) $gen['id']);
                if (empty($gen_pokemon)) {
                    continue;
                }
                $daily_pokemon_gen = poke_hub_pokedle_get_daily_pokemon_from_list($gen_pokemon, $today);
                $count_gen = poke_hub_pokedle_count_successful_players($today, (int) $gen['id']);
                echo '<tr>';
                echo '<td>' . esc_html($gen['name_fr'] ?: $gen['name_en']) . '</td>';
                if ($daily_pokemon_gen) {
                    echo '<td>' . esc_html($daily_pokemon_gen['name_fr'] ?: $daily_pokemon_gen['name_en']) . '</td>';
                } else {
                    echo '<td>' . esc_html__('No Pokémon available', 'poke-hub') . '</td>';
                }
                echo '<td>' . esc_html($count_gen) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            ?>
            
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
                echo '<th>' . esc_html__('Points', 'poke-hub') . '</th>';
                echo '<th>' . esc_html__('Attempts', 'poke-hub') . '</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                $position = 1;
                foreach ($leaderboard as $entry) {
                    echo '<tr>';
                    echo '<td>' . esc_html($position++) . '</td>';
                    echo '<td>' . esc_html($entry['display_name'] ?: __('Anonymous', 'poke-hub')) . '</td>';
                    echo '<td><strong>' . esc_html($entry['points'] ?? 0) . '</strong></td>';
                    echo '<td>' . esc_html($entry['attempts']) . '</td>';
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
            
            <?php elseif ($active_tab === 'settings'): ?>
            <h2><?php echo esc_html__('Games Settings', 'poke-hub'); ?></h2>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('poke_hub_games_settings');
                do_settings_sections('poke_hub_games_settings');
                ?>
                
                <h3><?php echo esc_html__('Mini Games', 'poke-hub'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Pokedle', 'poke-hub'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="poke_hub_games_pokedle_enabled" value="1" <?php checked(get_option('poke_hub_games_pokedle_enabled', true), true); ?> />
                                <?php echo esc_html__('Enable Pokedle game', 'poke-hub'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <?php endif; ?>
        </div>
    </div>
    <?php
}
