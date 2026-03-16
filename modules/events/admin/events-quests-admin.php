<?php
// modules/events/admin/events-quests-admin.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Les quêtes et catégories de quêtes sont gérées uniquement par le module Quêtes (menu Poké HUB > Quêtes).
 * Le module Events n’enregistre plus de menus quêtes.
 */
function pokehub_add_quests_admin_page() {
    // Désactivé : plus de sous-menu Quests dans Events.
}

/**
 * Assets pour la page quêtes de saison : plus utilisée (menu désactivé, gestion des quêtes dans le module Quêtes).
 */
function pokehub_quests_admin_page_assets($hook) {
    // Page poke-hub-quests gérée par le module Quêtes uniquement.
}

/**
 * Rendu de la page admin des quêtes
 */
function pokehub_render_quests_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to access this page.', 'poke-hub'));
    }

    // Sauvegarde des quêtes
    if (isset($_POST['pokehub_save_season_quests']) && check_admin_referer('pokehub_save_season_quests')) {
        $season_quests = isset($_POST['pokehub_season_quests']) ? $_POST['pokehub_season_quests'] : [];
        $season_start = isset($_POST['pokehub_season_start']) ? sanitize_text_field($_POST['pokehub_season_start']) : '';
        $season_end = isset($_POST['pokehub_season_end']) ? sanitize_text_field($_POST['pokehub_season_end']) : '';
        
        // Nettoyer et sauvegarder les quêtes
        $cleaned_quests = [];
        if (is_array($season_quests)) {
            foreach ($season_quests as $quest) {
                // Permettre les quêtes sans intitulé si elles ont des récompenses
                $has_rewards = !empty($quest['rewards']) && is_array($quest['rewards']) && count($quest['rewards']) > 0;
                $has_task = !empty($quest['task']);
                
                // Ignorer si ni intitulé ni récompenses
                if (!$has_task && !$has_rewards) {
                    continue;
                }
                
                $cleaned_quest = [
                    'task'           => $has_task ? sanitize_text_field($quest['task']) : '',
                    'rewards'        => [],
                    'quest_group_id' => isset($quest['quest_group_id']) ? max(0, (int) $quest['quest_group_id']) : 0,
                ];
                
                if (isset($quest['rewards']) && is_array($quest['rewards'])) {
                    foreach ($quest['rewards'] as $reward) {
                        $cleaned_reward = [
                            'type' => sanitize_key($reward['type'] ?? 'pokemon'),
                        ];
                        
                        if ($cleaned_reward['type'] === 'pokemon') {
                            $cleaned_reward['pokemon_id'] = isset($reward['pokemon_id']) ? (int) $reward['pokemon_id'] : 0;
                            // Récupérer le nom depuis la base de données si on a l'ID
                            if ($cleaned_reward['pokemon_id'] > 0 && function_exists('pokehub_get_pokemon_data_by_id')) {
                                $pokemon_data = pokehub_get_pokemon_data_by_id($cleaned_reward['pokemon_id']);
                                if ($pokemon_data) {
                                    $cleaned_reward['pokemon_name'] = $pokemon_data['name_fr'] ?? $pokemon_data['name_en'] ?? '';
                                } else {
                                    $cleaned_reward['pokemon_name'] = sanitize_text_field($reward['pokemon_name'] ?? '');
                                }
                            } else {
                                $cleaned_reward['pokemon_name'] = sanitize_text_field($reward['pokemon_name'] ?? '');
                            }
                            $cleaned_reward['is_shiny'] = !empty($reward['is_shiny']);
                            $cleaned_reward['cp_min'] = isset($reward['cp_min']) ? (int) $reward['cp_min'] : 0;
                            $cleaned_reward['cp_max'] = isset($reward['cp_max']) ? (int) $reward['cp_max'] : 0;
                        } elseif ($cleaned_reward['type'] === 'item') {
                            $cleaned_reward['item_id'] = isset($reward['item_id']) ? (int) $reward['item_id'] : 0;
                            // Récupérer le nom depuis la base de données si on a l'ID
                            if ($cleaned_reward['item_id'] > 0 && function_exists('pokehub_get_item_data_by_id')) {
                                $item_data = pokehub_get_item_data_by_id($cleaned_reward['item_id']);
                                if ($item_data) {
                                    $cleaned_reward['item_name'] = $item_data['name_fr'] ?? $item_data['name_en'] ?? '';
                                } else {
                                    $cleaned_reward['item_name'] = sanitize_text_field($reward['item_name'] ?? '');
                                }
                            } else {
                                $cleaned_reward['item_name'] = sanitize_text_field($reward['item_name'] ?? '');
                            }
                            $cleaned_reward['quantity'] = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
                        } else {
                            $cleaned_reward['quantity'] = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
                        }
                        
                        $cleaned_quest['rewards'][] = $cleaned_reward;
                    }
                }
                
                $cleaned_quests[] = $cleaned_quest;
            }
        }
        
        update_option('pokehub_season_quests', $cleaned_quests);
        update_option('pokehub_season_start', $season_start);
        update_option('pokehub_season_end', $season_end);
        
        echo '<div class="notice notice-success"><p>' . __('Quests saved successfully!', 'poke-hub') . '</p></div>';
    }

    // Récupérer les quêtes actuelles
    $season_quests = get_option('pokehub_season_quests', []);
    $season_start = get_option('pokehub_season_start', '');
    $season_end = get_option('pokehub_season_end', '');
    
    ?>
    <div class="wrap">
        <h1><?php _e('Quests', 'poke-hub'); ?></h1>
        
        <p class="description">
            <?php _e('Manage Field Research tasks that are available throughout the season. These quests will be displayed on the global quests page along with event quests.', 'poke-hub'); ?>
        </p>
        
        <form method="post" action="">
            <?php wp_nonce_field('pokehub_save_season_quests'); ?>
            
            <h2><?php _e('Season Period', 'poke-hub'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="pokehub_season_start"><?php _e('Start Date', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input 
                            type="date" 
                            id="pokehub_season_start" 
                            name="pokehub_season_start" 
                            value="<?php echo esc_attr($season_start); ?>"
                            class="regular-text"
                        />
                        <p class="description">
                            <?php _e('The quests will be active from this date.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="pokehub_season_end"><?php _e('End Date', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input 
                            type="date" 
                            id="pokehub_season_end" 
                            name="pokehub_season_end" 
                            value="<?php echo esc_attr($season_end); ?>"
                            class="regular-text"
                        />
                        <p class="description">
                            <?php _e('The quests will be active until this date.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <h2><?php _e('Quests', 'poke-hub'); ?></h2>
            
            <div id="pokehub-season-quests-list">
                <?php if (!empty($season_quests)) : ?>
                    <?php foreach ($season_quests as $index => $quest) : ?>
                        <?php pokehub_render_quest_editor_item($index, $quest, 'season'); ?>
                    <?php endforeach; ?>
                <?php else : ?>
                    <?php pokehub_render_quest_editor_item(0, ['task' => '', 'rewards' => []], 'season'); ?>
                <?php endif; ?>
            </div>
            
            <p>
                <button type="button" class="button button-secondary" id="pokehub-add-season-quest">
                    <?php _e('Add Quest', 'poke-hub'); ?>
                </button>
            </p>
            
            <p class="submit">
                <input type="submit" name="pokehub_save_season_quests" class="button button-primary" value="<?php esc_attr_e('Save Quests', 'poke-hub'); ?>" />
            </p>
        </form>
    </div>
    
    <script type="text/template" id="pokehub-season-quest-template">
        <?php pokehub_render_quest_editor_item('{{INDEX}}', ['task' => '', 'rewards' => []], 'season'); ?>
    </script>
    
    <script>
    jQuery(document).ready(function($) {
        var questIndex = <?php echo count($season_quests); ?>;
        
        // Utiliser les fonctions globales pour initialiser Select2
        function initSelect2() {
            if (window.pokehubInitQuestPokemonSelect2) {
                window.pokehubInitQuestPokemonSelect2(document);
            }
            if (window.pokehubInitQuestItemSelect2) {
                window.pokehubInitQuestItemSelect2(document);
            }
        }
        
        // Initialiser au chargement
        initSelect2();
        
        // Ajouter une quête
        $('#pokehub-add-season-quest').on('click', function() {
            var template = $('#pokehub-season-quest-template').html();
            template = template.replace(/\{\{INDEX\}\}/g, questIndex);
            var $newQuest = $(template);
            $('#pokehub-season-quests-list').append($newQuest);
            questIndex++;
            // Réinitialiser Select2 sur la nouvelle quête
            setTimeout(initSelect2, 100);
        });
        
        // Supprimer une quête
        $(document).on('click', '.pokehub-remove-quest', function() {
            if (confirm('<?php echo esc_js(__('Delete this quest?', 'poke-hub')); ?>')) {
                $(this).closest('.pokehub-quest-item-editor').remove();
            }
        });
        
        // Ajouter une récompense
        $(document).on('click', '.pokehub-add-reward', function() {
            var questItem = $(this).closest('.pokehub-quest-item-editor');
            var rewardIndex = questItem.find('.pokehub-quest-reward-editor').length;
            var questIndex = questItem.data('quest-index');
            var prefix = questItem.data('quest-prefix') || 'pokehub_season_quests';
            
            var rewardHtml = '<div class="pokehub-quest-reward-editor">' +
                '<label><?php echo esc_js(__('Reward Type', 'poke-hub')); ?>: ' +
                '<select name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][type]" class="pokehub-reward-type">' +
                '<option value="pokemon"><?php echo esc_js(__('Pokémon', 'poke-hub')); ?></option>' +
                '<option value="stardust"><?php echo esc_js(__('Stardust', 'poke-hub')); ?></option>' +
                '<option value="xp"><?php echo esc_js(__('XP', 'poke-hub')); ?></option>' +
                '<option value="item"><?php echo esc_js(__('Item', 'poke-hub')); ?></option>' +
                '</select></label>' +
                '<div class="pokehub-reward-pokemon-fields" style="display:block;">' +
                '<label><?php echo esc_js(__('Pokémon', 'poke-hub')); ?>: ' +
                '<select name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][pokemon_id]" class="pokehub-select-pokemon" style="width: 100%; min-width: 250px;" data-reward-index="' + rewardIndex + '">' +
                '<option value=""><?php echo esc_js(__('Select a Pokémon', 'poke-hub')); ?></option>' +
                '</select>' +
                '<input type="hidden" name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][pokemon_name]" class="pokehub-pokemon-name-field" />' +
                '</label> ' +
                '<label><input type="checkbox" name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][is_shiny]" /> <?php echo esc_js(__('Shiny', 'poke-hub')); ?></label> ' +
                '<label><?php echo esc_js(__('CP Min', 'poke-hub')); ?>: <input type="number" name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][cp_min]" /></label> ' +
                '<label><?php echo esc_js(__('CP Max', 'poke-hub')); ?>: <input type="number" name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][cp_max]" /></label>' +
                '</div>' +
                '<div class="pokehub-reward-other-fields" style="display:none;">' +
                '<label class="pokehub-reward-quantity-field" style="display:none;"><?php echo esc_js(__('Quantity', 'poke-hub')); ?>: <input type="number" name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][quantity]" value="1" min="1" /></label>' +
                '<label class="pokehub-reward-item-name-field" style="display:none;"><?php echo esc_js(__('Item', 'poke-hub')); ?>: ' +
                '<select name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][item_id]" class="pokehub-select-item" style="width: 100%; min-width: 250px;">' +
                '<option value=""><?php echo esc_js(__('Select an item', 'poke-hub')); ?></option>' +
                '</select>' +
                '<input type="hidden" name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][item_name]" class="pokehub-item-name-field" />' +
                '</label>' +
                '</div>' +
                '<button type="button" class="button-link pokehub-remove-reward"><?php echo esc_js(__('Remove', 'poke-hub')); ?></button>' +
                '</div>';
            
            var $newReward = $(rewardHtml);
            $(this).closest('.pokehub-quest-rewards-editor').append($newReward);
            // Initialiser Select2 sur la nouvelle récompense avec les fonctions globales
            setTimeout(function() {
                if (window.pokehubInitQuestPokemonSelect2) {
                    window.pokehubInitQuestPokemonSelect2($newReward);
                }
                if (window.pokehubInitQuestItemSelect2) {
                    window.pokehubInitQuestItemSelect2($newReward);
                }
            }, 100);
        });
        
        // Supprimer une récompense
        $(document).on('click', '.pokehub-remove-reward', function() {
            $(this).closest('.pokehub-quest-reward-editor').remove();
        });
        
        // Changer le type de récompense
        $(document).on('change', '.pokehub-reward-type', function() {
            var rewardEditor = $(this).closest('.pokehub-quest-reward-editor');
            var rewardType = $(this).val();
            var isPokemon = rewardType === 'pokemon';
            var isItem = rewardType === 'item';
            var isStardust = rewardType === 'stardust';
            var isXp = rewardType === 'xp';
            
            rewardEditor.find('.pokehub-reward-pokemon-fields').toggle(isPokemon);
            rewardEditor.find('.pokehub-reward-other-fields').toggle(!isPokemon);
            rewardEditor.find('.pokehub-reward-item-name-field').toggle(isItem);
            rewardEditor.find('.pokehub-reward-quantity-field').toggle(isStardust || isXp || isItem);
        });
    });
    </script>
    <?php
}

/**
 * NOTE: La fonction pokehub_render_quest_editor_item() est définie dans events-quests-metabox.php
 * qui est chargé avant ce fichier. Elle est réutilisée ici pour la page admin.
 * 
 * Pas besoin de la redéfinir ici, elle est déjà disponible.
 */

