<?php
// modules/events/functions/events-quests-render.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rendu des quêtes d'événement
 * 
 * @param array $quests Tableau de quêtes
 * @return string HTML
 */
if (!function_exists('pokehub_render_event_quests')) {
function pokehub_render_event_quests(array $quests): string {
    if (empty($quests)) {
        return '';
    }

    ob_start();
    ?>
    <ul class="pokehub-event-quests-list">
        <?php foreach ($quests as $quest_index => $quest) : 
            $task = $quest['task'] ?? '';
            $rewards = $quest['rewards'] ?? [];
            
            if (empty($task) || empty($rewards)) {
                continue;
            }
            
            // Filtrer uniquement les récompenses Pokémon
            $pokemon_rewards = [];
            foreach ($rewards as $reward) {
                if (isset($reward['type']) && $reward['type'] === 'pokemon') {
                    $pokemon_ids = [];
                    if (isset($reward['pokemon_ids']) && is_array($reward['pokemon_ids'])) {
                        $pokemon_ids = array_filter(array_map('intval', $reward['pokemon_ids']), function($id) {
                            return $id > 0;
                        });
                    } elseif (isset($reward['pokemon_id']) && !empty($reward['pokemon_id'])) {
                        $pokemon_ids = [(int) $reward['pokemon_id']];
                    }
                    if (!empty($pokemon_ids)) {
                        $pokemon_rewards[] = [
                            'pokemon_ids' => $pokemon_ids,
                            'force_shiny' => !empty($reward['force_shiny']) || !empty($reward['is_shiny']),
                        ];
                    }
                }
            }
            
            if (empty($pokemon_rewards)) {
                continue;
            }
            
            // Compter le nombre total de Pokémon
            $total_pokemon = 0;
            foreach ($pokemon_rewards as $reward) {
                $total_pokemon += count($reward['pokemon_ids']);
            }
            $has_single_reward = $total_pokemon === 1;
            $quest_id = 'pokehub-quest-' . $quest_index;
        ?>
            <li class="pokehub-quest-item">
                <div class="pokehub-quest-main">
                    <div class="pokehub-quest-task"><?php echo esc_html($task); ?></div>
                    
                    <?php if (!empty($pokemon_rewards)) : ?>
                        <div class="pokehub-quest-rewards-preview">
                            <?php 
                            $preview_count = 0;
                            foreach ($pokemon_rewards as $reward) :
                                foreach ($reward['pokemon_ids'] as $pokemon_id) :
                                    if ($preview_count >= 3) break 2; // Max 3 icônes en preview
                                    
                                    $pokemon_id = (int) $pokemon_id;
                                    if ($pokemon_id <= 0) continue;
                                    
                                    // Récupérer l'image
                                    $image_url = '';
                                    if (function_exists('pokehub_get_quest_pokemon_image')) {
                                        $image_url = pokehub_get_quest_pokemon_image($pokemon_id, false);
                                    }
                                    
                                    // Vérifier shiny
                                    $is_shiny = !empty($reward['force_shiny']);
                                    if (!$is_shiny && function_exists('poke_hub_pokemon_get_shiny_info')) {
                                        $shiny_info = poke_hub_pokemon_get_shiny_info($pokemon_id, []);
                                        $is_shiny = !empty($shiny_info['is_shiny_available']);
                                    }
                            ?>
                                <div class="pokehub-quest-reward-preview-icon">
                                    <?php if ($image_url) : ?>
                                        <img src="<?php echo esc_url($image_url); ?>" alt="" loading="lazy">
                                    <?php endif; ?>
                                    <?php if ($is_shiny) : ?>
                                        <span class="pokehub-quest-shiny-badge">✨</span>
                                    <?php endif; ?>
                                </div>
                            <?php 
                                    $preview_count++;
                                endforeach;
                            endforeach; 
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <span class="pokehub-quest-toggle" aria-expanded="false" data-quest-id="<?php echo esc_attr($quest_id); ?>">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </div>
                
                <div class="pokehub-quest-details" id="<?php echo esc_attr($quest_id); ?>">
                    <div class="pokehub-quest-rewards-label">
                        <?php echo $has_single_reward ? esc_html__('REWARD', 'poke-hub') : esc_html__('POSSIBLE REWARDS', 'poke-hub'); ?>
                    </div>
                    
                    <div class="pokehub-quest-rewards-list">
                        <?php foreach ($pokemon_rewards as $reward) :
                            foreach ($reward['pokemon_ids'] as $pokemon_id) :
                                $pokemon_id = (int) $pokemon_id;
                                if ($pokemon_id <= 0) continue;
                                
                                // Récupérer les données du Pokémon
                                $pokemon_data = function_exists('pokehub_get_pokemon_data_by_id') 
                                    ? pokehub_get_pokemon_data_by_id($pokemon_id) 
                                    : null;
                                
                                if (!$pokemon_data) continue;
                                
                                // Récupérer l'image
                                $image_url = '';
                                if (function_exists('pokehub_get_quest_pokemon_image')) {
                                    $image_url = pokehub_get_quest_pokemon_image($pokemon_id, false);
                                }
                                
                                // Vérifier shiny
                                $is_shiny = !empty($reward['force_shiny']);
                                if (!$is_shiny && function_exists('poke_hub_pokemon_get_shiny_info')) {
                                    $shiny_info = poke_hub_pokemon_get_shiny_info($pokemon_id, []);
                                    $is_shiny = !empty($shiny_info['is_shiny_available']);
                                }
                                
                                // Récupérer les CP (niveau 15 pour les quêtes)
                                $cp_data = function_exists('pokehub_get_pokemon_cp_for_level') 
                                    ? pokehub_get_pokemon_cp_for_level($pokemon_id, 15) 
                                    : null;
                                
                                $max_cp = $cp_data['max_cp'] ?? null;
                                $min_cp = $cp_data['min_cp'] ?? null;
                                
                                $pokemon_name = $pokemon_data['name_fr'] ?? $pokemon_data['name_en'] ?? '';
                        ?>
                            <div class="pokehub-quest-reward-item">
                                <div class="pokehub-quest-reward-image">
                                    <?php if ($image_url) : ?>
                                        <img src="<?php echo esc_url($image_url); ?>" 
                                             alt="<?php echo esc_attr($pokemon_name); ?>"
                                             loading="lazy">
                                    <?php endif; ?>
                                    <?php if ($is_shiny) : ?>
                                        <span class="pokehub-quest-shiny-badge">✨</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="pokehub-quest-reward-info">
                                    <div class="pokehub-quest-reward-name"><?php echo esc_html($pokemon_name); ?></div>
                                    
                                    <?php if ($max_cp !== null || $min_cp !== null) : ?>
                                        <div class="pokehub-quest-reward-cp">
                                            <?php if ($max_cp !== null) : ?>
                                                <div class="pokehub-quest-cp-box">
                                                    <div class="pokehub-quest-cp-label"><?php esc_html_e('Max CP', 'poke-hub'); ?></div>
                                                    <div class="pokehub-quest-cp-value"><?php echo esc_html($max_cp); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($min_cp !== null) : ?>
                                                <div class="pokehub-quest-cp-box">
                                                    <div class="pokehub-quest-cp-label"><?php esc_html_e('Min CP', 'poke-hub'); ?></div>
                                                    <div class="pokehub-quest-cp-value"><?php echo esc_html($min_cp); ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php 
                            endforeach;
                        endforeach; ?>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
    
    return ob_get_clean();
}
}
