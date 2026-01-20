<?php
// modules/events/functions/events-quests-render.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rendu des quêtes d'un événement
 *
 * @param array $quests Liste des quêtes
 * @return string HTML
 */
/**
 * Rendu des quêtes d'un événement
 *
 * @param array $quests Liste des quêtes
 * @return string HTML
 */
function pokehub_render_event_quests($quests) {
    if (empty($quests)) {
        return '';
    }
    
    // Collecter les couleurs de types uniques utilisées dans ces quêtes
    $type_colors = [];
    foreach ($quests as $quest) {
        if (empty($quest['rewards'])) {
            continue;
        }
        foreach ($quest['rewards'] as $reward) {
            if ($reward['type'] === 'pokemon') {
                $pokemon_ids = [];
                if (isset($reward['pokemon_ids']) && is_array($reward['pokemon_ids'])) {
                    $pokemon_ids = array_filter(array_map('intval', $reward['pokemon_ids']), function($id) {
                        return $id > 0;
                    });
                } elseif (isset($reward['pokemon_id']) && !empty($reward['pokemon_id'])) {
                    $pokemon_ids = [(int) $reward['pokemon_id']];
                }
                foreach ($pokemon_ids as $pokemon_id) {
                    if ($pokemon_id > 0) {
                        $type_color = pokehub_get_pokemon_type_color($pokemon_id);
                        if (!empty($type_color)) {
                            $type_slug = pokehub_get_pokemon_first_type_slug($pokemon_id);
                            if (!empty($type_slug)) {
                                $type_colors[$type_slug] = $type_color;
                            }
                        }
                    }
                }
            }
        }
    }
    
    ob_start();
    
    // Générer les styles CSS dynamiques pour les couleurs de types
    if (!empty($type_colors)) {
        echo '<style id="pokehub-quest-type-colors">';
        foreach ($type_colors as $type_slug => $color) {
            echo '.event-field-research-list .reward-bubble.' . esc_attr($type_slug) . '{background-color:' . esc_attr($color) . ';}';
        }
        echo '</style>';
    }
    ?>
    <ul class="event-field-research-list">
        <?php foreach ($quests as $quest_index => $quest) : ?>
            <li>
                <div class="quest-header">
                    <?php if (!empty($quest['task'])) : ?>
                        <span class="task"><?php echo esc_html($quest['task']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($quest['rewards'])) : ?>
                        <span class="quest-toggle" data-quest-index="<?php echo esc_attr($quest_index); ?>" aria-expanded="true">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($quest['rewards'])) : ?>
                    <div class="reward-list" id="reward-list-<?php echo esc_attr($quest_index); ?>">
                        <span class="rewards-header">
                            <?php echo count($quest['rewards']) > 1 ? __('POSSIBLE REWARDS', 'poke-hub') : __('REWARD', 'poke-hub'); ?>
                        </span>
                        
                        <?php foreach ($quest['rewards'] as $reward) : ?>
                            <?php if ($reward['type'] === 'pokemon') : ?>
                                <?php
                                // Gérer pokemon_ids (array) ou pokemon_id (single) pour rétrocompatibilité
                                $pokemon_ids = [];
                                if (isset($reward['pokemon_ids']) && is_array($reward['pokemon_ids'])) {
                                    $pokemon_ids = array_filter(array_map('intval', $reward['pokemon_ids']), function($id) {
                                        return $id > 0;
                                    });
                                } elseif (isset($reward['pokemon_id']) && !empty($reward['pokemon_id'])) {
                                    $pokemon_ids = [(int) $reward['pokemon_id']];
                                }
                                $force_shiny = !empty($reward['force_shiny']) || !empty($reward['is_shiny']); // Rétrocompatibilité
                                ?>
                                <?php foreach ($pokemon_ids as $pokemon_id) : ?>
                                    <?php
                                    $pokemon_name = '';
                                    $pokemon_type_slug = '';
                                    $cp_max = null;
                                    $cp_min = null;
                                    
                                    // Récupérer le nom et le type depuis la base de données
                                    if ($pokemon_id > 0 && function_exists('pokehub_get_pokemon_data_by_id')) {
                                        $pokemon_data = pokehub_get_pokemon_data_by_id($pokemon_id);
                                        if ($pokemon_data) {
                                            $pokemon_name = $pokemon_data['name_fr'] ?? $pokemon_data['name_en'] ?? '';
                                            $is_shiny = $force_shiny; // Pour l'instant, utiliser force_shiny
                                        }
                                        
                                        // Récupérer le premier type pour la classe CSS
                                        $pokemon_type_slug = pokehub_get_pokemon_first_type_slug($pokemon_id);
                                        
                                        // Récupérer les CP pour le niveau 15 (standard pour les quêtes)
                                        if (function_exists('pokehub_get_pokemon_cp_for_level')) {
                                            $cp_data = pokehub_get_pokemon_cp_for_level($pokemon_id, 15);
                                            if ($cp_data) {
                                                $cp_max = $cp_data['max_cp'];
                                                $cp_min = $cp_data['min_cp'];
                                            }
                                        }
                                    }
                                    
                                    // Toujours récupérer l'image normale (pas shiny)
                                    $image_url = '';
                                    if ($pokemon_id && function_exists('pokehub_get_quest_pokemon_image')) {
                                        $image_url = pokehub_get_quest_pokemon_image($pokemon_id, false);
                                    }
                                    
                                    // Vérifier si le Pokémon peut être shiny (depuis extra->release->shiny)
                                    $can_be_shiny = false;
                                    if ($pokemon_id > 0 && function_exists('pokehub_pokemon_can_be_shiny')) {
                                        $can_be_shiny = pokehub_pokemon_can_be_shiny($pokemon_id);
                                    }
                                    
                                    // Afficher l'icône shiny si force_shiny OU si le Pokémon peut être shiny
                                    $show_shiny_icon = $force_shiny || $can_be_shiny;
                                    
                                    // Classe CSS pour le type (water, electric, etc.)
                                    $type_class = !empty($pokemon_type_slug) ? esc_attr($pokemon_type_slug) : '';
                                    // Récupérer la couleur du type depuis la base de données
                                    $type_color = pokehub_get_pokemon_type_color($pokemon_id);
                                    $data_color = !empty($type_color) ? ' data-type-color="' . esc_attr($type_color) . '"' : '';
                                    ?>
                                    <div class="reward">
                                        <span class="reward-bubble <?php echo $type_class; ?>"<?php echo $data_color; ?>>
                                            <?php if ($image_url) : ?>
                                                <img class="reward-image" src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($pokemon_name); ?>">
                                            <?php endif; ?>
                                            <?php if ($show_shiny_icon) : ?>
                                                <?php 
                                                // URL de l'icône shiny
                                                $shiny_icon_url = apply_filters('pokehub_shiny_icon_url', POKE_HUB_URL . 'assets/img/icons/shiny-icon.png', $pokemon_id);
                                                ?>
                                                <img class="shiny-icon" src="<?php echo esc_url($shiny_icon_url); ?>" alt="shiny">
                                            <?php endif; ?>
                                        </span>
                                        <span class="reward-label">
                                            <span><?php echo esc_html($pokemon_name); ?></span>
                                        </span>
                                        <?php if ($cp_max !== null || $cp_min !== null) : ?>
                                            <span class="cp-values <?php echo $type_class; ?>">
                                                <?php if ($cp_max !== null) : ?>
                                                    <span class="max-cp">
                                                        <div><?php _e('Max CP', 'poke-hub'); ?></div>
                                                        <?php echo esc_html($cp_max); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($cp_min !== null) : ?>
                                                    <span class="min-cp">
                                                        <div><?php _e('Min CP', 'poke-hub'); ?></div>
                                                        <?php echo esc_html($cp_min); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php elseif ($reward['type'] === 'candy') : ?>
                                <?php
                                $pokemon_id = isset($reward['pokemon_id']) ? (int) $reward['pokemon_id'] : 0;
                                $pokemon_name = '';
                                if ($pokemon_id > 0 && function_exists('pokehub_get_pokemon_data_by_id')) {
                                    $pokemon_data = pokehub_get_pokemon_data_by_id($pokemon_id);
                                    if ($pokemon_data) {
                                        $pokemon_name = $pokemon_data['name_fr'] ?? $pokemon_data['name_en'] ?? '';
                                    }
                                }
                                $quantity = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
                                ?>
                                <div class="reward">
                                    <span class="reward-label">
                                        <span>🍬 <?php printf(_n('%d %s Candy', '%d %s Candy', $quantity, 'poke-hub'), $quantity, esc_html($pokemon_name)); ?></span>
                                    </span>
                                </div>
                            <?php elseif ($reward['type'] === 'mega_energy') : ?>
                                <?php
                                $pokemon_id = isset($reward['pokemon_id']) ? (int) $reward['pokemon_id'] : 0;
                                $pokemon_name = '';
                                if ($pokemon_id > 0 && function_exists('pokehub_get_pokemon_data_by_id')) {
                                    $pokemon_data = pokehub_get_pokemon_data_by_id($pokemon_id);
                                    if ($pokemon_data) {
                                        $pokemon_name = $pokemon_data['name_fr'] ?? $pokemon_data['name_en'] ?? '';
                                    }
                                }
                                $quantity = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
                                ?>
                                <div class="reward">
                                    <span class="reward-label">
                                        <span>⚡ <?php printf(_n('%d %s Mega Energy', '%d %s Mega Energy', $quantity, 'poke-hub'), $quantity, esc_html($pokemon_name)); ?></span>
                                    </span>
                                </div>
                            <?php elseif ($reward['type'] === 'stardust') : ?>
                                <div class="reward">
                                    <span class="reward-label">
                                        <span>⭐ <?php printf(_n('%d Stardust', '%d Stardust', $reward['quantity'], 'poke-hub'), $reward['quantity']); ?></span>
                                    </span>
                                </div>
                            <?php elseif ($reward['type'] === 'xp') : ?>
                                <div class="reward">
                                    <span class="reward-label">
                                        <span>✨ <?php printf(_n('%d XP', '%d XP', $reward['quantity'], 'poke-hub'), $reward['quantity']); ?></span>
                                    </span>
                                </div>
                            <?php elseif ($reward['type'] === 'item') : ?>
                                <?php
                                $item_id = $reward['item_id'] ?? 0;
                                $item_name = $reward['item_name'] ?? '';
                                
                                // Si on a l'ID mais pas le nom, récupérer depuis la base de données
                                if ($item_id > 0 && empty($item_name) && function_exists('pokehub_get_item_data_by_id')) {
                                    $item_data = pokehub_get_item_data_by_id($item_id);
                                    if ($item_data) {
                                        $item_name = $item_data['name_fr'] ?? $item_data['name_en'] ?? '';
                                    }
                                }
                                ?>
                                <div class="reward">
                                    <span class="reward-label">
                                        <span><?php echo esc_html($item_name ?: $reward['type']); ?>
                                        <?php if (isset($reward['quantity']) && $reward['quantity'] > 1) : ?>
                                            ×<?php echo esc_html($reward['quantity']); ?>
                                        <?php endif; ?></span>
                                    </span>
                                </div>
                            <?php else : ?>
                                <div class="reward">
                                    <span class="reward-label">
                                        <span><?php echo esc_html($reward['type']); ?>
                                        <?php if (isset($reward['quantity']) && $reward['quantity'] > 1) : ?>
                                            ×<?php echo esc_html($reward['quantity']); ?>
                                        <?php endif; ?></span>
                                    </span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
    return ob_get_clean();
}

