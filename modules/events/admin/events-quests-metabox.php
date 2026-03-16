<?php
// modules/events/admin/events-quests-metabox.php

if (!defined('ABSPATH')) {
    exit;
}

require_once POKE_HUB_PATH . 'includes/content/content-quests-editor.php';

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
    
    // Récupérer les genres sauvegardés
    global $post;
    $saved_genders = [];
    if ($post && $post->ID) {
        $quests = pokehub_get_event_quests($post->ID);
        if (is_array($quests)) {
            foreach ($quests as $quest_index => $quest) {
                if (isset($quest['rewards']) && is_array($quest['rewards'])) {
                    foreach ($quest['rewards'] as $reward_index => $reward) {
                        if (isset($reward['pokemon_genders']) && is_array($reward['pokemon_genders'])) {
                            $saved_genders[$quest_index][$reward_index] = $reward['pokemon_genders'];
                        }
                    }
                }
            }
        }
    }
    
    wp_localize_script('pokehub-admin-select2', 'pokehubQuestsData', [
        'pokemon' => $pokemon_list,
        'mega_pokemon' => $mega_pokemon_list,
        'base_pokemon' => $base_pokemon_list,
        'items' => $items_list,
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pokehub_quests_ajax'),
    ]);
    
    // Localiser les données pour la gestion des genres
    wp_localize_script('pokehub-admin-select2', 'pokehubQuestsGender', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pokehub_quests_ajax'),
        'saved_genders' => $saved_genders,
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
                '<select name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][pokemon_ids][]" class="pokehub-select-pokemon pokehub-quest-pokemon-select" style="width: 100%; min-width: 250px;" multiple data-quest-index="' + questIndex + '" data-reward-index="' + rewardIndex + '">' +
                '</select>' +
                '</label> ' +
                '<label title="<?php echo esc_js(__('Force shiny only if the Pokémon is shiny-lock. Otherwise, shiny status will be retrieved from the database.', 'poke-hub')); ?>">' +
                '<input type="checkbox" name="' + prefix + '[' + questIndex + '][rewards][' + rewardIndex + '][force_shiny]" /> ' +
                '<?php echo esc_js(__('Force Shiny (if shiny-lock)', 'poke-hub')); ?>' +
                '<small style="display: block; color: #666; margin-top: 3px;"><?php echo esc_js(__('Only for shiny-lock Pokémon. Otherwise, status is retrieved from the database.', 'poke-hub')); ?></small>' +
                '</label>' +
                '<div class="pokehub-quest-pokemon-genders" data-quest-index="' + questIndex + '" data-reward-index="' + rewardIndex + '" style="margin-top: 10px; display: none;">' +
                '<strong><?php echo esc_js(__('Genders (optional)', 'poke-hub')); ?></strong>' +
                '<p class="description" style="margin: 5px 0; font-size: 12px;"><?php echo esc_js(__('For Pokémon with gender dimorphism, you can force a specific gender. By default, the male image will be used.', 'poke-hub')); ?></p>' +
                '<div class="pokehub-quest-pokemon-genders-list" data-quest-index="' + questIndex + '" data-reward-index="' + rewardIndex + '"></div>' +
                '</div>' +
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
                
                // Initialiser les champs genre si c'est une récompense pokémon
                setTimeout(function() {
                    var $pokemonSelect = $newReward.find('.pokehub-quest-pokemon-select');
                    if ($pokemonSelect.length) {
                        updateQuestGenderFields($pokemonSelect);
                    }
                }, 200);
            }, 100);
        });
        
        // Supprimer une récompense
        $(document).on('click', '.pokehub-remove-reward', function() {
            $(this).closest('.pokehub-quest-reward-editor').remove();
        });
        
        // Fonction pour mettre à jour les champs genre pour un select de pokémon dans une quête
        function updateQuestGenderFields($select) {
            var questIndex = $select.data('quest-index');
            var rewardIndex = $select.data('reward-index');
            var $container = $select.closest('.pokehub-reward-pokemon-fields').find('.pokehub-quest-pokemon-genders[data-quest-index="' + questIndex + '"][data-reward-index="' + rewardIndex + '"]');
            var $list = $container.find('.pokehub-quest-pokemon-genders-list');
            var selectedIds = $select.val() || [];
            
            if (selectedIds.length === 0) {
                $container.hide();
                $list.empty();
                return;
            }
            
            $list.empty();
            var promises = [];
            var hasAnyDimorphic = false;
            
            selectedIds.forEach(function(pokemonId) {
                var promise = $.post(pokehubQuestsGender.ajax_url, {
                    action: 'pokehub_check_pokemon_gender_dimorphism',
                    nonce: pokehubQuestsGender.nonce,
                    pokemon_id: pokemonId
                });
                
                promise.done(function(resp) {
                    if (resp && resp.success && resp.data && resp.data.has_gender_dimorphism) {
                        hasAnyDimorphic = true;
                        var savedGender = '';
                        if (pokehubQuestsGender.saved_genders && pokehubQuestsGender.saved_genders[questIndex] && pokehubQuestsGender.saved_genders[questIndex][rewardIndex] && pokehubQuestsGender.saved_genders[questIndex][rewardIndex][pokemonId]) {
                            savedGender = pokehubQuestsGender.saved_genders[questIndex][rewardIndex][pokemonId];
                        }
                        
                        var $genderRow = $('<div style="margin-bottom: 10px;"></div>');
                        var $label = $('<label style="display: block; margin-bottom: 4px;"></label>');
                        $label.text('Pokémon #' + pokemonId + ':');
                        var $selectGender = $('<select name="pokehub_quests[' + questIndex + '][rewards][' + rewardIndex + '][pokemon_genders][' + pokemonId + ']" style="width: 200px; margin-left: 10px;"></select>');
                        $selectGender.append('<option value=""><?php echo esc_js(__('Default (Male)', 'poke-hub')); ?></option>');
                        $selectGender.append('<option value="male"' + (savedGender === 'male' ? ' selected' : '') + '><?php echo esc_js(__('Male', 'poke-hub')); ?></option>');
                        $selectGender.append('<option value="female"' + (savedGender === 'female' ? ' selected' : '') + '><?php echo esc_js(__('Female', 'poke-hub')); ?></option>');
                        
                        $genderRow.append($label);
                        $genderRow.append($selectGender);
                        $list.append($genderRow);
                    }
                });
                
                promises.push(promise);
            });
            
            $.when.apply($, promises).done(function() {
                if (hasAnyDimorphic) {
                    $container.show();
                } else {
                    $container.hide();
                }
            });
        }
        
        // Écouter les changements sur les selects de pokémon dans les quêtes
        $(document).on('change', '.pokehub-quest-pokemon-select', function() {
            setTimeout(function() {
                updateQuestGenderFields($(this));
            }.bind(this), 100);
        });
        
        // Initialiser les champs genre au chargement
        setTimeout(function() {
            $('.pokehub-quest-pokemon-select').each(function() {
                updateQuestGenderFields($(this));
            });
        }, 500);
        
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

