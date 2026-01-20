<?php
// modules/events/admin/events-quests-metabox.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajoute la metabox pour les quêtes d'événement
 */
function pokehub_add_event_quests_metabox() {
    $screens = apply_filters('pokehub_event_quests_post_types', [
        'post',
        'pokehub_event',
    ]);

    foreach ($screens as $screen) {
        add_meta_box(
            'pokehub_event_quests',
            __('Event Quests (Field Research)', 'poke-hub'),
            'pokehub_render_event_quests_metabox',
            $screen,
            'normal',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'pokehub_add_event_quests_metabox');

/**
 * Enqueue scripts et styles pour la metabox des quêtes
 */
function pokehub_quests_metabox_assets($hook) {
    global $post_type;
    
    $allowed_types = apply_filters('pokehub_event_quests_post_types', ['post', 'pokehub_event']);
    if (!in_array($post_type, $allowed_types, true)) {
        return;
    }
    
    // Select2 CSS
    wp_enqueue_style(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
        [],
        '4.1.0'
    );
    
    // Select2 JS
    wp_enqueue_script(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        ['jquery'],
        '4.1.0',
        true
    );
    
    // Script global pour Select2 (utilise les fonctions existantes)
    wp_enqueue_script(
        'pokehub-admin-select2',
        POKE_HUB_URL . 'assets/js/pokehub-admin-select2.js',
        ['jquery', 'select2'],
        POKE_HUB_VERSION,
        true
    );
    
    // Localiser les données pour Select2
    $pokemon_list = function_exists('pokehub_get_pokemon_for_select') 
        ? pokehub_get_pokemon_for_select() 
        : [];
    $mega_pokemon_list = function_exists('pokehub_get_mega_pokemon_for_select') 
        ? pokehub_get_mega_pokemon_for_select() 
        : [];
    $base_pokemon_list = function_exists('pokehub_get_base_pokemon_for_select') 
        ? pokehub_get_base_pokemon_for_select()
        : [];
    $items_list = function_exists('pokehub_get_items_for_select') 
        ? pokehub_get_items_for_select() 
        : [];
    
    wp_localize_script('pokehub-admin-select2', 'pokehubQuestsData', [
        'pokemon' => $pokemon_list,
        'mega_pokemon' => $mega_pokemon_list,
        'base_pokemon' => $base_pokemon_list,
        'items' => $items_list,
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pokehub_quests_ajax'),
    ]);
}
add_action('admin_enqueue_scripts', 'pokehub_quests_metabox_assets');

/**
 * Rendu de la metabox des quêtes
 */
function pokehub_render_event_quests_metabox($post) {
    wp_nonce_field('pokehub_save_event_quests', 'pokehub_event_quests_nonce');

    $quests = pokehub_get_event_quests($post->ID);
    
    ?>
    <div class="pokehub-quests-metabox">
        <p class="description">
            <?php _e('Add Field Research tasks for this event. Each quest can have multiple possible rewards.', 'poke-hub'); ?>
        </p>
        
        <div id="pokehub-quests-list">
            <?php if (!empty($quests)) : ?>
                <?php foreach ($quests as $index => $quest) : ?>
                    <?php pokehub_render_quest_editor_item($index, $quest); ?>
                <?php endforeach; ?>
            <?php else : ?>
                <?php pokehub_render_quest_editor_item(0, ['task' => '', 'rewards' => []]); ?>
            <?php endif; ?>
        </div>
        
        <button type="button" class="button button-secondary" id="pokehub-add-quest">
            <?php _e('Add Quest', 'poke-hub'); ?>
        </button>
    </div>
    
    <script type="text/template" id="pokehub-quest-template">
        <?php pokehub_render_quest_editor_item('{{INDEX}}', ['task' => '', 'rewards' => []]); ?>
    </script>
    
    <style>
        .pokehub-quest-item-editor {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            background: #fff;
        }
        .pokehub-quest-item-editor h4 {
            margin-top: 0;
        }
        .pokehub-quest-reward-editor {
            border: 1px solid #eee;
            padding: 10px;
            margin: 5px 0;
            background: #f9f9f9;
        }
        .pokehub-remove-quest,
        .pokehub-remove-reward {
            color: #a00;
            cursor: pointer;
        }
        .pokehub-remove-quest:hover,
        .pokehub-remove-reward:hover {
            color: #dc3232;
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        var questIndex = <?php echo count($quests); ?>;
        
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
        $('#pokehub-add-quest').on('click', function() {
            var template = $('#pokehub-quest-template').html();
            template = template.replace(/\{\{INDEX\}\}/g, questIndex);
            var $newQuest = $(template);
            $('#pokehub-quests-list').append($newQuest);
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
            var prefix = questItem.data('quest-prefix') || 'pokehub_quests';
            
            var rewardHtml = '<div class="pokehub-quest-reward-editor">' +
                '<label><?php echo esc_js(__('Reward Type', 'poke-hub')); ?>: ' +
                '<select name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][type]" class="pokehub-reward-type">' +
                '<option value="pokemon"><?php echo esc_js(__('Pokémon', 'poke-hub')); ?></option>' +
                '<option value="stardust"><?php echo esc_js(__('Stardust', 'poke-hub')); ?></option>' +
                '<option value="xp"><?php echo esc_js(__('XP', 'poke-hub')); ?></option>' +
                '<option value="candy"><?php echo esc_js(__('Candy', 'poke-hub')); ?></option>' +
                '<option value="mega_energy"><?php echo esc_js(__('Mega Energy', 'poke-hub')); ?></option>' +
                '<option value="item"><?php echo esc_js(__('Item', 'poke-hub')); ?></option>' +
                '</select></label>' +
                '<div class="pokehub-reward-pokemon-fields" style="display:block;">' +
                '<label><?php echo esc_js(__('Pokémon', 'poke-hub')); ?>: ' +
                '<select name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][pokemon_ids][]" class="pokehub-select-pokemon" style="width: 100%; min-width: 250px;" multiple data-reward-index="' + rewardIndex + '">' +
                '</select>' +
                '</label> ' +
                '<label title="<?php echo esc_js(__('Forcer le shiny uniquement si le Pokémon est shiny-lock. Sinon, le statut shiny sera récupéré depuis la base de données.', 'poke-hub')); ?>">' +
                '<input type="checkbox" name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][force_shiny]" /> ' +
                '<?php echo esc_js(__('Force Shiny (si shiny-lock)', 'poke-hub')); ?>' +
                '<small style="display: block; color: #666; margin-top: 3px;"><?php echo esc_js(__('Uniquement pour les Pokémon shiny-lock. Sinon, le statut est récupéré depuis la base de données.', 'poke-hub')); ?></small>' +
                '</label>' +
                '</div>' +
                '<div class="pokehub-reward-pokemon-resource-fields" style="display:none;">' +
                '<label><?php echo esc_js(__('Pokémon', 'poke-hub')); ?>: ' +
                '<select name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][pokemon_id]" class="pokehub-select-pokemon-resource" style="width: 100%; min-width: 250px;" data-reward-index="' + rewardIndex + '">' +
                '<option value=""><?php echo esc_js(__('Select a Pokémon', 'poke-hub')); ?></option>' +
                '</select>' +
                '</label>' +
                '<label class="pokehub-reward-quantity-field" style="display:none;"><?php echo esc_js(__('Quantity', 'poke-hub')); ?>: <input type="number" name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][quantity]" value="1" min="1" /></label>' +
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
                // Trigger change to set initial visibility of fields
                $newReward.find('.pokehub-reward-type').trigger('change');
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
            var isCandy = rewardType === 'candy';
            var isMegaEnergy = rewardType === 'mega_energy';
            var isPokemonResource = isCandy || isMegaEnergy;
            
            rewardEditor.find('.pokehub-reward-pokemon-fields').toggle(isPokemon);
            rewardEditor.find('.pokehub-reward-pokemon-resource-fields').toggle(isPokemonResource);
            rewardEditor.find('.pokehub-reward-other-fields').toggle(!isPokemon && !isPokemonResource);
            rewardEditor.find('.pokehub-reward-item-name-field').toggle(isItem);
            rewardEditor.find('.pokehub-reward-quantity-field').toggle(isStardust || isXp || isItem || isCandy || isMegaEnergy);
        });
    });
    </script>
    <?php
}

/**
 * Rendu d'un item de quête dans l'éditeur
 * 
 * @param int|string $index Index de la quête
 * @param array $quest Données de la quête
 * @param string $prefix Préfixe pour les noms de champs ('event' ou 'season')
 */
function pokehub_render_quest_editor_item($index, $quest, $prefix = 'event') {
    $name_prefix = $prefix === 'season' ? 'pokehub_season_quests' : 'pokehub_quests';
    ?>
    <div class="pokehub-quest-item-editor" data-quest-index="<?php echo esc_attr($index); ?>" data-quest-prefix="<?php echo esc_attr($name_prefix); ?>">
        <h4>
            <?php _e('Quest', 'poke-hub'); ?> #<?php echo is_numeric($index) ? ($index + 1) : $index; ?>
            <button type="button" class="button-link pokehub-remove-quest" style="float:right;">
                <?php _e('Remove', 'poke-hub'); ?>
            </button>
        </h4>
        
        <label>
            <strong><?php _e('Task', 'poke-hub'); ?>:</strong><br>
            <input 
                type="text" 
                name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][task]" 
                value="<?php echo esc_attr($quest['task'] ?? ''); ?>" 
                class="widefat"
                placeholder="<?php esc_attr_e('e.g., Catch 5 Pokémon', 'poke-hub'); ?>"
            />
        </label>
        
        <div class="pokehub-quest-rewards-editor" style="margin-top: 15px;">
            <strong><?php _e('Rewards', 'poke-hub'); ?>:</strong>
            
            <?php if (!empty($quest['rewards'])) : ?>
                <?php foreach ($quest['rewards'] as $reward_index => $reward) : ?>
                    <div class="pokehub-quest-reward-editor">
                        <label><?php _e('Reward Type', 'poke-hub'); ?>:
                            <select name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][type]" class="pokehub-reward-type">
                                <option value="pokemon" <?php selected($reward['type'] ?? 'pokemon', 'pokemon'); ?>><?php _e('Pokémon', 'poke-hub'); ?></option>
                                <option value="stardust" <?php selected($reward['type'] ?? '', 'stardust'); ?>><?php _e('Stardust', 'poke-hub'); ?></option>
                                <option value="xp" <?php selected($reward['type'] ?? '', 'xp'); ?>><?php _e('XP', 'poke-hub'); ?></option>
                                <option value="candy" <?php selected($reward['type'] ?? '', 'candy'); ?>><?php _e('Candy', 'poke-hub'); ?></option>
                                <option value="mega_energy" <?php selected($reward['type'] ?? '', 'mega_energy'); ?>><?php _e('Mega Energy', 'poke-hub'); ?></option>
                                <option value="item" <?php selected($reward['type'] ?? '', 'item'); ?>><?php _e('Item', 'poke-hub'); ?></option>
                            </select>
                        </label>
                        
                        <?php 
                        $reward_type = $reward['type'] ?? 'pokemon';
                        $is_pokemon = $reward_type === 'pokemon';
                        $is_candy = $reward_type === 'candy';
                        $is_mega_energy = $reward_type === 'mega_energy';
                        $selected_pokemon_ids = isset($reward['pokemon_ids']) && is_array($reward['pokemon_ids']) 
                            ? array_map('intval', $reward['pokemon_ids']) 
                            : (isset($reward['pokemon_id']) ? [(int) $reward['pokemon_id']] : []);
                        ?>
                        <div class="pokehub-reward-pokemon-fields" style="display:<?php echo $is_pokemon ? 'block' : 'none'; ?>;">
                            <label><?php _e('Pokémon', 'poke-hub'); ?>: 
                                <select 
                                    name="pokehub_quests[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][pokemon_ids][]" 
                                    class="pokehub-select-pokemon" 
                                    style="width: 100%; min-width: 250px;"
                                    multiple
                                    data-reward-index="<?php echo esc_attr($reward_index); ?>"
                                >
                                    <?php
                                    if (!empty($selected_pokemon_ids) && function_exists('pokehub_get_pokemon_for_select')) {
                                        $pokemon_list = pokehub_get_pokemon_for_select();
                                        foreach ($pokemon_list as $pokemon_option) {
                                            $is_selected = in_array((int) $pokemon_option['id'], $selected_pokemon_ids, true);
                                            echo '<option value="' . esc_attr($pokemon_option['id']) . '" ' . selected($is_selected, true, false) . '>' . esc_html($pokemon_option['text']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </label>
                            <label title="<?php esc_attr_e('Forcer le shiny uniquement si le Pokémon est shiny-lock. Sinon, le statut shiny sera récupéré depuis la base de données.', 'poke-hub'); ?>">
                                <input type="checkbox" name="pokehub_quests[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][force_shiny]" <?php checked(!empty($reward['force_shiny'])); ?> />
                                <?php _e('Force Shiny (si shiny-lock)', 'poke-hub'); ?>
                                <small style="display: block; color: #666; margin-top: 3px;">
                                    <?php _e('Uniquement pour les Pokémon shiny-lock. Sinon, le statut est récupéré depuis la base de données.', 'poke-hub'); ?>
                                </small>
                            </label>
                        </div>
                        
                        <div class="pokehub-reward-pokemon-resource-fields" style="display:<?php echo ($is_candy || $is_mega_energy) ? 'block' : 'none'; ?>;">
                            <label><?php _e('Pokémon', 'poke-hub'); ?>: 
                                <select 
                                    name="pokehub_quests[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][pokemon_id]" 
                                    class="pokehub-select-pokemon-resource" 
                                    style="width: 100%; min-width: 250px;"
                                    data-reward-index="<?php echo esc_attr($reward_index); ?>"
                                >
                                    <option value=""><?php _e('Select a Pokémon', 'poke-hub'); ?></option>
                                    <?php
                                    $selected_pokemon_id = isset($reward['pokemon_id']) ? (int) $reward['pokemon_id'] : 0;
                                    if ($selected_pokemon_id > 0 && function_exists('pokehub_get_pokemon_data_by_id')) {
                                        $pokemon_data = pokehub_get_pokemon_data_by_id($selected_pokemon_id);
                                        if ($pokemon_data) {
                                            $dex_number = isset($pokemon_data['dex_number']) ? (int) $pokemon_data['dex_number'] : 0;
                                            $name = $pokemon_data['name'] ?? ($pokemon_data['name_fr'] ?? $pokemon_data['name_en'] ?? '');
                                            $form = !empty($pokemon_data['form']) ? ' (' . $pokemon_data['form'] . ')' : '';
                                            $text = $name;
                                            if ($dex_number > 0) {
                                                $text .= ' #' . str_pad((string) $dex_number, 3, '0', STR_PAD_LEFT);
                                            }
                                            $text .= $form;
                                            echo '<option value="' . esc_attr($selected_pokemon_id) . '" selected>' . esc_html($text) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </label>
                            <label class="pokehub-reward-quantity-field" style="display:<?php echo ($is_candy || $is_mega_energy) ? 'block' : 'none'; ?>;">
                                <?php _e('Quantity', 'poke-hub'); ?>: 
                                <input type="number" name="pokehub_quests[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][quantity]" value="<?php echo esc_attr($reward['quantity'] ?? 1); ?>" min="1" />
                            </label>
                        </div>
                        
                        <div class="pokehub-reward-other-fields" style="display:<?php echo ($is_pokemon || $is_candy || $is_mega_energy) ? 'none' : 'block'; ?>;">
                            <?php 
                            $reward_type = $reward['type'] ?? '';
                            $is_stardust = $reward_type === 'stardust';
                            $is_xp = $reward_type === 'xp';
                            $is_item = $reward_type === 'item';
                            $is_candy_reward = $reward_type === 'candy';
                            $is_mega_energy_reward = $reward_type === 'mega_energy';
                            ?>
                            <label class="pokehub-reward-quantity-field" style="display:<?php echo ($is_stardust || $is_xp || $is_item || $is_candy_reward || $is_mega_energy_reward) ? 'block' : 'none'; ?>;">
                                <?php _e('Quantity', 'poke-hub'); ?>: 
                                <input type="number" name="pokehub_quests[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][quantity]" value="<?php echo esc_attr($reward['quantity'] ?? 1); ?>" min="1" />
                            </label>
                            <label class="pokehub-reward-item-name-field" style="display:<?php echo $is_item ? 'block' : 'none'; ?>;">
                                <?php _e('Item', 'poke-hub'); ?>: 
                                <select 
                                    name="pokehub_quests[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][item_id]" 
                                    class="pokehub-select-item" 
                                    style="width: 100%; min-width: 250px;"
                                >
                                    <option value=""><?php _e('Select an item', 'poke-hub'); ?></option>
                                    <?php
                                    $selected_item_id = isset($reward['item_id']) ? (int) $reward['item_id'] : 0;
                                    if ($selected_item_id > 0 && function_exists('pokehub_get_item_data_by_id')) {
                                        $item_data = pokehub_get_item_data_by_id($selected_item_id);
                                        if ($item_data) {
                                            $name_fr = $item_data['name_fr'] ?? '';
                                            $name_en = $item_data['name_en'] ?? '';
                                            $text = $name_fr;
                                            if ($name_en && $name_fr !== $name_en) {
                                                $text .= ' (' . $name_en . ')';
                                            } elseif (!$name_fr && $name_en) {
                                                $text = $name_en;
                                            }
                                            echo '<option value="' . esc_attr($selected_item_id) . '" selected>' . esc_html($text) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <input type="hidden" name="pokehub_quests[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][item_name]" class="pokehub-item-name-field" value="<?php echo esc_attr($reward['item_name'] ?? ''); ?>" />
                            </label>
                        </div>
                        
                        <button type="button" class="button-link pokehub-remove-reward"><?php _e('Remove', 'poke-hub'); ?></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <button type="button" class="button button-small pokehub-add-reward">
                <?php _e('Add Reward', 'poke-hub'); ?>
            </button>
        </div>
    </div>
    <?php
}

/**
 * Sauvegarde des quêtes
 */
function pokehub_save_event_quests_metabox($post_id) {
    // Vérifications de sécurité
    if (!isset($_POST['pokehub_event_quests_nonce']) || 
        !wp_verify_nonce($_POST['pokehub_event_quests_nonce'], 'pokehub_save_event_quests')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Sauvegarder les quêtes
    if (isset($_POST['pokehub_quests']) && is_array($_POST['pokehub_quests'])) {
        pokehub_save_event_quests($post_id, $_POST['pokehub_quests']);
    } else {
        // Si aucune quête, supprimer la meta
        delete_post_meta($post_id, '_pokehub_event_quests');
    }
}
add_action('save_post', 'pokehub_save_event_quests_metabox');

