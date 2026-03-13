<?php
// modules/blocks/functions/blocks-quests-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère les quêtes d'un événement depuis les post meta
 * Version indépendante du module events pour les blocs
 * 
 * @param int $post_id ID du post
 * @return array Tableau de quêtes
 */
function pokehub_blocks_get_event_quests(int $post_id): array {
    $quests = function_exists('pokehub_get_event_quests') ? pokehub_get_event_quests($post_id) : [];
    return is_array($quests) ? $quests : [];
}

/**
 * Rendu des quêtes d'événement pour les blocs
 * Version indépendante du module events
 * 
 * @param array $quests Tableau de quêtes
 * @return string HTML
 */
function pokehub_blocks_render_event_quests(array $quests): string {
    if (empty($quests)) {
        return '';
    }

    ob_start();
    ?>
    <div class="pokehub-event-quests">
        <?php foreach ($quests as $quest_index => $quest) : 
            $task = $quest['task'] ?? '';
            $rewards = $quest['rewards'] ?? [];
            
            // Filtrer uniquement les récompenses Pokémon
            $pokemon_rewards = array_filter($rewards, function($reward) {
                return isset($reward['type']) && $reward['type'] === 'pokemon';
            });
            
            if (empty($pokemon_rewards)) {
                continue;
            }
            
            $has_single_reward = count($pokemon_rewards) === 1;
            $quest_id = 'pokehub-quest-' . $quest_index;
        ?>
            <div class="pokehub-quest-card">
                <div class="pokehub-quest-header">
                    <h3 class="pokehub-quest-task"><?php echo esc_html($task); ?></h3>
                    <button 
                        type="button" 
                        class="pokehub-quest-toggle" 
                        aria-expanded="true"
                        aria-controls="<?php echo esc_attr($quest_id); ?>"
                    >
                        <span class="pokehub-quest-toggle-icon">▲</span>
                    </button>
                </div>
                
                <div class="pokehub-quest-content" id="<?php echo esc_attr($quest_id); ?>">
                    <div class="pokehub-quest-rewards-label">
                        <?php echo $has_single_reward ? esc_html__('REWARD', 'poke-hub') : esc_html__('POSSIBLE REWARDS', 'poke-hub'); ?>
                    </div>
                    
                    <div class="pokehub-quest-rewards-list">
                        <?php foreach ($pokemon_rewards as $reward) : 
                            $pokemon_ids = $reward['pokemon_ids'] ?? [];
                            if (empty($pokemon_ids)) {
                                continue;
                            }
                            
                            // Récupérer les genres pour cette récompense
                            $pokemon_genders = $reward['pokemon_genders'] ?? [];
                            
                            foreach ($pokemon_ids as $pokemon_id) :
                                $pokemon_id = (int) $pokemon_id;
                                if ($pokemon_id <= 0) {
                                    continue;
                                }
                                
                                // Récupérer les données du Pokémon
                                $pokemon_data = function_exists('pokehub_get_pokemon_data_by_id') 
                                    ? pokehub_get_pokemon_data_by_id($pokemon_id) 
                                    : null;
                                
                                if (!$pokemon_data) {
                                    continue;
                                }
                                
                                // Récupérer le genre pour ce pokémon dans cette récompense
                                $gender = $pokemon_genders[$pokemon_id] ?? null;
                                
                                // Récupérer l'image du Pokémon (utilise les helpers publics)
                                $pokemon_image_url = '';
                                if (function_exists('poke_hub_pokemon_get_image_url')) {
                                    // Utiliser directement la fonction des helpers publics
                                    $pokemon = (object) $pokemon_data;
                                    $pokemon_image_url = poke_hub_pokemon_get_image_url($pokemon, [
                                        'shiny' => false,
                                        'gender' => $gender,
                                    ]);
                                }
                                
                                // Vérifier si le Pokémon peut être shiny (utilise les helpers publics)
                                $is_shiny_available = false;
                                if (function_exists('pokehub_pokemon_can_be_shiny')) {
                                    // Utiliser la fonction des helpers publics
                                    $is_shiny_available = pokehub_pokemon_can_be_shiny($pokemon_id);
                                }
                                
                                // Si force_shiny est activé, afficher comme shiny
                                $force_shiny = !empty($reward['force_shiny']);
                                $is_shiny = $force_shiny || $is_shiny_available;
                                
                                // Récupérer les CP (niveau 15 pour les quêtes)
                                $cp_data = function_exists('pokehub_get_pokemon_cp_for_level') 
                                    ? pokehub_get_pokemon_cp_for_level($pokemon_id, 15) 
                                    : null;
                                
                                $max_cp = $cp_data['max_cp'] ?? null;
                                $min_cp = $cp_data['min_cp'] ?? null;
                                
                                $pokemon_name = $pokemon_data['name'] ?? ($pokemon_data['name_fr'] ?? $pokemon_data['name_en'] ?? '');
                        ?>
                            <div class="pokehub-quest-reward-item">
                                <?php if ($pokemon_image_url) : ?>
                                    <div class="pokehub-quest-reward-image">
                                        <img 
                                            src="<?php echo esc_url($pokemon_image_url); ?>" 
                                            alt="<?php echo esc_attr($pokemon_name); ?>"
                                            loading="lazy"
                                        />
                                        <?php if ($is_shiny) : ?>
                                            <span class="pokehub-quest-reward-shiny-badge" title="<?php esc_attr_e('Shiny', 'poke-hub'); ?>">★</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="pokehub-quest-reward-info">
                                    <div class="pokehub-quest-reward-name"><?php echo esc_html($pokemon_name); ?></div>
                                    
                                    <?php if ($max_cp !== null || $min_cp !== null) : ?>
                                        <div class="pokehub-quest-reward-cp">
                                            <?php if ($max_cp !== null) : ?>
                                                <span class="pokehub-quest-cp-box pokehub-quest-cp-max">
                                                    <span class="pokehub-quest-cp-label"><?php esc_html_e('Max CP', 'poke-hub'); ?></span>
                                                    <span class="pokehub-quest-cp-value"><?php echo esc_html($max_cp); ?></span>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($min_cp !== null) : ?>
                                                <span class="pokehub-quest-cp-box pokehub-quest-cp-min">
                                                    <span class="pokehub-quest-cp-label"><?php esc_html_e('Min CP', 'poke-hub'); ?></span>
                                                    <span class="pokehub-quest-cp-value"><?php echo esc_html($min_cp); ?></span>
                                                </span>
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
            </div>
        <?php endforeach; ?>
    </div>
    
    <style>
        .pokehub-event-quests {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .pokehub-quest-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .pokehub-quest-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid #eee;
        }
        
        .pokehub-quest-task {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #333;
            flex: 1;
        }
        
        .pokehub-quest-toggle {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 8px;
            color: #666;
            font-size: 12px;
            transition: transform 0.2s;
        }
        
        .pokehub-quest-toggle:hover {
            color: #333;
        }
        
        .pokehub-quest-toggle[aria-expanded="false"] .pokehub-quest-toggle-icon {
            transform: rotate(180deg);
        }
        
        .pokehub-quest-content {
            padding: 16px;
        }
        
        .pokehub-quest-content[hidden] {
            display: none;
        }
        
        .pokehub-quest-rewards-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }
        
        .pokehub-quest-rewards-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .pokehub-quest-reward-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .pokehub-quest-reward-image {
            position: relative;
            flex-shrink: 0;
        }
        
        .pokehub-quest-reward-image img {
            width: 64px;
            height: 64px;
            object-fit: contain;
        }
        
        .pokehub-quest-reward-shiny-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ffd700;
            color: #333;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }
        
        .pokehub-quest-reward-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .pokehub-quest-reward-name {
            font-size: 16px;
            font-weight: 500;
            color: #333;
        }
        
        .pokehub-quest-reward-cp {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pokehub-quest-cp-box {
            display: inline-flex;
            flex-direction: column;
            padding: 6px 10px;
            background: #f5f5f5;
            border-radius: 4px;
            gap: 2px;
        }
        
        .pokehub-quest-cp-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #666;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .pokehub-quest-cp-value {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }
    </style>
    
    <script>
    (function() {
        document.addEventListener('DOMContentLoaded', function() {
            var toggles = document.querySelectorAll('.pokehub-quest-toggle');
            toggles.forEach(function(toggle) {
                toggle.addEventListener('click', function() {
                    var content = document.getElementById(this.getAttribute('aria-controls'));
                    var isExpanded = this.getAttribute('aria-expanded') === 'true';
                    
                    if (content) {
                        if (isExpanded) {
                            content.hidden = true;
                            this.setAttribute('aria-expanded', 'false');
                        } else {
                            content.hidden = false;
                            this.setAttribute('aria-expanded', 'true');
                        }
                    }
                });
            });
        });
    })();
    </script>
    <?php
    
    return ob_get_clean();
}

