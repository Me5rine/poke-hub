<?php
// modules/blocks/admin/collection-challenges-metabox.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajoute la meta box pour les défis de collection
 */
function pokehub_add_collection_challenges_metabox() {
    $screens = apply_filters('pokehub_collection_challenges_post_types', ['post', 'pokehub_event']);
    
    foreach ($screens as $screen) {
        add_meta_box(
            'pokehub_collection_challenges',
            __('Collection Challenges', 'poke-hub'),
            'pokehub_render_collection_challenges_metabox',
            $screen,
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'pokehub_add_collection_challenges_metabox');

/**
 * Charge les assets nécessaires pour la meta box
 */
function pokehub_collection_challenges_metabox_assets($hook) {
    global $post;
    
    $allowed_types = apply_filters('pokehub_collection_challenges_post_types', ['post', 'pokehub_event']);
    
    if (!in_array($hook, ['post.php', 'post-new.php']) || !in_array(get_post_type($post), $allowed_types)) {
        return;
    }
    
    // Charger Select2
    wp_enqueue_script('pokehub-admin-select2');
    wp_enqueue_style('pokehub-admin-select2');
    
    // Localiser les données pour Select2
    wp_localize_script('pokehub-admin-select2', 'pokehubQuestsData', [
        'pokemon' => function_exists('pokehub_get_pokemon_for_select') ? pokehub_get_pokemon_for_select() : [],
        'items' => function_exists('pokehub_get_items_for_select') ? pokehub_get_items_for_select() : [],
        'nonce' => wp_create_nonce('pokehub_quests_ajax'),
        'rest_nonce' => wp_create_nonce('wp_rest'),
        'rest_pokemon_url' => rest_url('poke-hub/v1/pokemon-for-select'),
    ]);
}
add_action('admin_enqueue_scripts', 'pokehub_collection_challenges_metabox_assets');

/**
 * Affiche la meta box
 */
function pokehub_render_collection_challenges_metabox($post) {
    wp_nonce_field('pokehub_save_collection_challenges', 'pokehub_collection_challenges_nonce');
    
    $challenges = function_exists('pokehub_content_get_collection_challenges') ? pokehub_content_get_collection_challenges('post', (int) $post->ID) : [];
    if (!is_array($challenges)) {
        $challenges = [];
    }
    ?>
    <div class="pokehub-collection-challenges-metabox">
        <p>
            <?php _e('Add collection challenges for this event. Each challenge can have Pokémon to catch, hatch, evolve, trade, get from Team GO Rocket, or catch in costume.', 'poke-hub'); ?>
        </p>
        
        <div id="pokehub-collection-challenges-list">
            <?php if (!empty($challenges)) : ?>
                <?php foreach ($challenges as $index => $challenge) : ?>
                    <?php pokehub_render_collection_challenge_editor_item($index, $challenge); ?>
                <?php endforeach; ?>
            <?php else : ?>
                <?php pokehub_render_collection_challenge_editor_item(0, []); ?>
            <?php endif; ?>
        </div>
        
        <button type="button" class="button button-secondary" id="pokehub-add-collection-challenge">
            <?php _e('Add Challenge', 'poke-hub'); ?>
        </button>
    </div>
    
    <script type="text/template" id="pokehub-collection-challenge-template">
        <?php pokehub_render_collection_challenge_editor_item('{{INDEX}}', []); ?>
    </script>
    
    <script>
    jQuery(document).ready(function($) {
        // Initialiser Select2 pour les nouveaux éléments dans un contexte spécifique
        function initSelect2(context) {
            var $ctx = context ? $(context) : $(document);
            // Ne pas initialiser les selects dans les templates
            var $container = $ctx.find ? $ctx : $($ctx);
            $container.find('script[type="text/template"]').each(function() {
                $(this).find('select').removeClass('pokehub-select-pokemon pokehub-select-item pokehub-select-pokemon-resource');
            });
            
            if (window.pokehubInitQuestPokemonSelect2) {
                window.pokehubInitQuestPokemonSelect2($ctx[0] || document);
            }
            if (window.pokehubInitQuestItemSelect2) {
                window.pokehubInitQuestItemSelect2($ctx[0] || document);
            }
        }
        
        // Initialiser uniquement dans la liste des défis, pas dans les templates
        initSelect2($('#pokehub-collection-challenges-list'));
        
        // Ajouter un nouveau défi
        $('#pokehub-add-collection-challenge').on('click', function() {
            var template = $('#pokehub-collection-challenge-template').html();
            var index = $('#pokehub-collection-challenges-list .pokehub-collection-challenge-item-editor').length;
            var $newChallenge = $(template.replace(/\{\{INDEX\}\}/g, index));
            $newChallenge.attr('data-challenge-index', index);
            $('#pokehub-collection-challenges-list').append($newChallenge);
            // Initialiser Select2 uniquement pour le nouveau défi
            initSelect2($newChallenge);
        });
        
        // Supprimer un défi
        $(document).on('click', '.pokehub-remove-challenge', function() {
            if (confirm('<?php echo esc_js(__('Are you sure you want to remove this challenge?', 'poke-hub')); ?>')) {
                var $challengeItem = $(this).closest('.pokehub-collection-challenge-item-editor');
                // Détruire Select2 avant de supprimer pour éviter les orphelins
                $challengeItem.find('select').each(function() {
                    if ($(this).data('select2')) {
                        $(this).select2('destroy');
                    }
                });
                $challengeItem.remove();
            }
        });
        
        // Ajouter une récompense
        $(document).on('click', '.pokehub-add-reward', function() {
            var challengeItem = $(this).closest('.pokehub-collection-challenge-item-editor');
            var rewardIndex = challengeItem.find('.pokehub-reward-editor').length;
            var challengeIndex = challengeItem.data('challenge-index') || 0;
            
            var rewardHtml = '<div class="pokehub-reward-editor" style="margin-top: 10px; padding: 10px; background: #f0f0f0; border: 1px solid #ddd;">' +
                '<label><?php echo esc_js(__('Reward Type', 'poke-hub')); ?>: ' +
                '<select name="pokehub_collection_challenges[' + challengeIndex + '][rewards][' + rewardIndex + '][type]" class="pokehub-reward-type">' +
                '<option value="pokemon"><?php echo esc_js(__('Pokémon', 'poke-hub')); ?></option>' +
                '<option value="stardust"><?php echo esc_js(__('Stardust', 'poke-hub')); ?></option>' +
                '<option value="xp"><?php echo esc_js(__('XP', 'poke-hub')); ?></option>' +
                '<option value="candy"><?php echo esc_js(__('Candy', 'poke-hub')); ?></option>' +
                '<option value="mega_energy"><?php echo esc_js(__('Mega Energy', 'poke-hub')); ?></option>' +
                '<option value="item"><?php echo esc_js(__('Item', 'poke-hub')); ?></option>' +
                '</select></label>' +
                '<div class="pokehub-reward-fields" style="margin-top: 10px;"></div>' +
                '<button type="button" class="button-link pokehub-remove-reward"><?php echo esc_js(__('Remove', 'poke-hub')); ?></button>' +
                '</div>';
            
            $(this).closest('.pokehub-collection-challenge-rewards-editor').append($(rewardHtml));
            initSelect2();
        });
        
        // Supprimer une récompense
        $(document).on('click', '.pokehub-remove-reward', function() {
            var $rewardEditor = $(this).closest('.pokehub-reward-editor');
            // Détruire Select2 avant de supprimer pour éviter les orphelins
            $rewardEditor.find('select').each(function() {
                if ($(this).data('select2')) {
                    $(this).select2('destroy');
                }
            });
            $rewardEditor.remove();
        });
        
        // Gérer le changement de type de récompense
        $(document).on('change', '.pokehub-reward-type', function() {
            var rewardEditor = $(this).closest('.pokehub-reward-editor');
            var type = $(this).val();
            var fieldsContainer = rewardEditor.find('.pokehub-reward-fields');
            var challengeIndex = rewardEditor.closest('.pokehub-collection-challenge-item-editor').data('challenge-index') || 0;
            var rewardIndex = rewardEditor.index();
            
            fieldsContainer.empty();
            
            if (type === 'pokemon') {
                fieldsContainer.html(
                    '<label><?php echo esc_js(__('Pokémon', 'poke-hub')); ?>: ' +
                    '<select name="pokehub_collection_challenges[' + challengeIndex + '][rewards][' + rewardIndex + '][pokemon_ids][]" class="pokehub-select-pokemon" multiple style="width: 100%;"></select></label>'
                );
            } else if (type === 'stardust' || type === 'xp') {
                fieldsContainer.html(
                    '<label><?php echo esc_js(__('Quantity', 'poke-hub')); ?>: ' +
                    '<input type="number" name="pokehub_collection_challenges[' + challengeIndex + '][rewards][' + rewardIndex + '][quantity]" value="1" min="1" /></label>'
                );
            } else if (type === 'item') {
                fieldsContainer.html(
                    '<label><?php echo esc_js(__('Item', 'poke-hub')); ?>: ' +
                    '<select name="pokehub_collection_challenges[' + challengeIndex + '][rewards][' + rewardIndex + '][item_id]" class="pokehub-select-item" style="width: 100%;"></select></label>' +
                    '<input type="hidden" name="pokehub_collection_challenges[' + challengeIndex + '][rewards][' + rewardIndex + '][item_name]" class="pokehub-item-name-field" />' +
                    '<label><?php echo esc_js(__('Quantity', 'poke-hub')); ?>: ' +
                    '<input type="number" name="pokehub_collection_challenges[' + challengeIndex + '][rewards][' + rewardIndex + '][quantity]" value="1" min="1" /></label>'
                );
            } else if (type === 'candy' || type === 'mega_energy') {
                fieldsContainer.html(
                    '<label><?php echo esc_js(__('Pokémon', 'poke-hub')); ?>: ' +
                    '<select name="pokehub_collection_challenges[' + challengeIndex + '][rewards][' + rewardIndex + '][pokemon_id]" class="pokehub-select-pokemon-resource" style="width: 100%;"></select></label>' +
                    '<label><?php echo esc_js(__('Quantity', 'poke-hub')); ?>: ' +
                    '<input type="number" name="pokehub_collection_challenges[' + challengeIndex + '][rewards][' + rewardIndex + '][quantity]" value="1" min="1" /></label>'
                );
            }
            
            // Initialiser Select2 uniquement pour les nouveaux champs de récompense
            initSelect2(fieldsContainer);
        });
        
        // Initialiser les champs de récompense existants
        $('.pokehub-reward-type').each(function() {
            $(this).trigger('change');
        });
        
        // Gérer l'affichage/masquage des dates pour les défis existants et nouveaux
        $(document).on('change', 'input[name*="[use_global_dates]"]', function() {
            var $datesFields = $(this).closest('.pokehub-collection-challenge-item-editor').find('.pokehub-challenge-dates-fields');
            if ($(this).is(':checked')) {
                $datesFields.hide();
            } else {
                $datesFields.show();
            }
        });
        
        // Initialiser l'état des dates pour les défis existants
        $('input[name*="[use_global_dates]"]').each(function() {
            if ($(this).is(':checked')) {
                $(this).closest('.pokehub-collection-challenge-item-editor').find('.pokehub-challenge-dates-fields').hide();
            }
        });
    });
    </script>
    <?php
}

/**
 * Affiche un item d'édition de défi
 */
function pokehub_render_collection_challenge_editor_item($index, $challenge) {
    ?>
    <div class="pokehub-collection-challenge-item-editor" data-challenge-index="<?php echo esc_attr($index); ?>">
        <h4>
            <?php _e('Challenge', 'poke-hub'); ?> #<?php echo is_numeric($index) ? ($index + 1) : $index; ?>
            <button type="button" class="button-link pokehub-remove-challenge" style="float:right;">
                <?php _e('Remove', 'poke-hub'); ?>
            </button>
        </h4>
        
        <label>
            <strong><?php _e('Challenge Name', 'poke-hub'); ?>:</strong><br>
            <input 
                type="text" 
                name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][name]" 
                value="<?php echo esc_attr($challenge['name'] ?? ''); ?>" 
                class="widefat"
                placeholder="<?php esc_attr_e('e.g., Team Up With Candela', 'poke-hub'); ?>"
            />
        </label>
        
        <label style="margin-top: 10px;">
            <strong><?php _e('Header Color', 'poke-hub'); ?>:</strong><br>
            <input 
                type="color" 
                name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][color]" 
                value="<?php echo esc_attr($challenge['color'] ?? '#333333'); ?>"
            />
        </label>
        
        <div style="margin-top: 15px;">
            <label>
                <input 
                    type="checkbox" 
                    name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][use_global_dates]" 
                    value="1"
                    <?php checked(!empty($challenge['use_global_dates'])); ?>
                />
                <?php _e('Use global event dates (hide dates in front)', 'poke-hub'); ?>
            </label>
        </div>
        
        <div style="margin-top: 10px; display: <?php echo !empty($challenge['use_global_dates']) ? 'none' : 'block'; ?>;" class="pokehub-challenge-dates-fields">
            <label>
                <strong><?php _e('Start Date', 'poke-hub'); ?>:</strong>
                <input 
                    type="date" 
                    name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][start_date]" 
                    value="<?php echo esc_attr($challenge['start_date'] ?? ''); ?>"
                />
            </label>
            <label style="margin-left: 10px;">
                <strong><?php _e('Start Time', 'poke-hub'); ?>:</strong>
                <input 
                    type="time" 
                    name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][start_time]" 
                    value="<?php echo esc_attr($challenge['start_time'] ?? ''); ?>"
                />
            </label>
            <br>
            <label style="margin-top: 10px;">
                <strong><?php _e('End Date', 'poke-hub'); ?>:</strong>
                <input 
                    type="date" 
                    name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][end_date]" 
                    value="<?php echo esc_attr($challenge['end_date'] ?? ''); ?>"
                />
            </label>
            <label style="margin-left: 10px;">
                <strong><?php _e('End Time', 'poke-hub'); ?>:</strong>
                <input 
                    type="time" 
                    name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][end_time]" 
                    value="<?php echo esc_attr($challenge['end_time'] ?? ''); ?>"
                />
            </label>
        </div>
        
        <div class="pokehub-collection-challenge-categories" style="margin-top: 15px;">
            <h5><?php _e('Pokémon Categories', 'poke-hub'); ?></h5>
            
            <div class="pokehub-collection-challenge-category">
                <label>
                    <strong><?php _e('Catch', 'poke-hub'); ?>:</strong><br>
                    <select 
                        name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][pokemon_catch][]" 
                        class="pokehub-select-pokemon" 
                        multiple
                        style="width: 100%; min-width: 250px;"
                    >
                        <?php
                        $selected_catch = isset($challenge['pokemon_catch']) && is_array($challenge['pokemon_catch']) 
                            ? array_map('intval', $challenge['pokemon_catch']) 
                            : [];
                        if (!empty($selected_catch) && function_exists('pokehub_get_pokemon_for_select')) {
                            $pokemon_list = pokehub_get_pokemon_for_select();
                            foreach ($pokemon_list as $pokemon_option) {
                                $is_selected = in_array((int) $pokemon_option['id'], $selected_catch, true);
                                echo '<option value="' . esc_attr($pokemon_option['id']) . '" ' . selected($is_selected, true, false) . '>' . esc_html($pokemon_option['text']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </label>
            </div>
            
            <div class="pokehub-collection-challenge-category">
                <label>
                    <strong><?php _e('Shadow (Team GO Rocket)', 'poke-hub'); ?>:</strong><br>
                    <select 
                        name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][pokemon_shadow][]" 
                        class="pokehub-select-pokemon" 
                        multiple
                        style="width: 100%; min-width: 250px;"
                    >
                        <?php
                        $selected_shadow = isset($challenge['pokemon_shadow']) && is_array($challenge['pokemon_shadow']) 
                            ? array_map('intval', $challenge['pokemon_shadow']) 
                            : [];
                        if (!empty($selected_shadow) && function_exists('pokehub_get_pokemon_for_select')) {
                            $pokemon_list = pokehub_get_pokemon_for_select();
                            foreach ($pokemon_list as $pokemon_option) {
                                $is_selected = in_array((int) $pokemon_option['id'], $selected_shadow, true);
                                echo '<option value="' . esc_attr($pokemon_option['id']) . '" ' . selected($is_selected, true, false) . '>' . esc_html($pokemon_option['text']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </label>
            </div>
            
            <div class="pokehub-collection-challenge-category">
                <label>
                    <strong><?php _e('Evolution', 'poke-hub'); ?>:</strong><br>
                    <select 
                        name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][pokemon_evolution][]" 
                        class="pokehub-select-pokemon" 
                        multiple
                        style="width: 100%; min-width: 250px;"
                    >
                        <?php
                        $selected_evolution = isset($challenge['pokemon_evolution']) && is_array($challenge['pokemon_evolution']) 
                            ? array_map('intval', $challenge['pokemon_evolution']) 
                            : [];
                        if (!empty($selected_evolution) && function_exists('pokehub_get_pokemon_for_select')) {
                            $pokemon_list = pokehub_get_pokemon_for_select();
                            foreach ($pokemon_list as $pokemon_option) {
                                $is_selected = in_array((int) $pokemon_option['id'], $selected_evolution, true);
                                echo '<option value="' . esc_attr($pokemon_option['id']) . '" ' . selected($is_selected, true, false) . '>' . esc_html($pokemon_option['text']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </label>
            </div>
            
            <div class="pokehub-collection-challenge-category">
                <label>
                    <strong><?php _e('Hatch', 'poke-hub'); ?>:</strong><br>
                    <select 
                        name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][pokemon_hatch][]" 
                        class="pokehub-select-pokemon" 
                        multiple
                        style="width: 100%; min-width: 250px;"
                    >
                        <?php
                        $selected_hatch = isset($challenge['pokemon_hatch']) && is_array($challenge['pokemon_hatch']) 
                            ? array_map('intval', $challenge['pokemon_hatch']) 
                            : [];
                        if (!empty($selected_hatch) && function_exists('pokehub_get_pokemon_for_select')) {
                            $pokemon_list = pokehub_get_pokemon_for_select();
                            foreach ($pokemon_list as $pokemon_option) {
                                $is_selected = in_array((int) $pokemon_option['id'], $selected_hatch, true);
                                echo '<option value="' . esc_attr($pokemon_option['id']) . '" ' . selected($is_selected, true, false) . '>' . esc_html($pokemon_option['text']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </label>
            </div>
            
            <div class="pokehub-collection-challenge-category">
                <label>
                    <strong><?php _e('Costume', 'poke-hub'); ?>:</strong><br>
                    <select 
                        name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][pokemon_costume][]" 
                        class="pokehub-select-pokemon" 
                        multiple
                        style="width: 100%; min-width: 250px;"
                    >
                        <?php
                        $selected_costume = isset($challenge['pokemon_costume']) && is_array($challenge['pokemon_costume']) 
                            ? array_map('intval', $challenge['pokemon_costume']) 
                            : [];
                        if (!empty($selected_costume) && function_exists('pokehub_get_pokemon_for_select')) {
                            $pokemon_list = pokehub_get_pokemon_for_select();
                            foreach ($pokemon_list as $pokemon_option) {
                                $is_selected = in_array((int) $pokemon_option['id'], $selected_costume, true);
                                echo '<option value="' . esc_attr($pokemon_option['id']) . '" ' . selected($is_selected, true, false) . '>' . esc_html($pokemon_option['text']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </label>
            </div>
            
            <div class="pokehub-collection-challenge-category">
                <label>
                    <strong><?php _e('Trade', 'poke-hub'); ?>:</strong><br>
                    <select 
                        name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][pokemon_trade][]" 
                        class="pokehub-select-pokemon" 
                        multiple
                        style="width: 100%; min-width: 250px;"
                    >
                        <?php
                        $selected_trade = isset($challenge['pokemon_trade']) && is_array($challenge['pokemon_trade']) 
                            ? array_map('intval', $challenge['pokemon_trade']) 
                            : [];
                        if (!empty($selected_trade) && function_exists('pokehub_get_pokemon_for_select')) {
                            $pokemon_list = pokehub_get_pokemon_for_select();
                            foreach ($pokemon_list as $pokemon_option) {
                                $is_selected = in_array((int) $pokemon_option['id'], $selected_trade, true);
                                echo '<option value="' . esc_attr($pokemon_option['id']) . '" ' . selected($is_selected, true, false) . '>' . esc_html($pokemon_option['text']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </label>
            </div>
        </div>
        
        <div class="pokehub-collection-challenge-rewards-editor" style="margin-top: 15px;">
            <strong><?php _e('Rewards', 'poke-hub'); ?>:</strong>
            
            <?php if (!empty($challenge['rewards']) && is_array($challenge['rewards'])) : ?>
                <?php foreach ($challenge['rewards'] as $reward_index => $reward) : ?>
                    <div class="pokehub-reward-editor" style="margin-top: 10px; padding: 10px; background: #f0f0f0; border: 1px solid #ddd;">
                        <label><?php _e('Reward Type', 'poke-hub'); ?>:
                            <select name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][type]" class="pokehub-reward-type">
                                <option value="pokemon" <?php selected($reward['type'] ?? 'pokemon', 'pokemon'); ?>><?php _e('Pokémon', 'poke-hub'); ?></option>
                                <option value="stardust" <?php selected($reward['type'] ?? '', 'stardust'); ?>><?php _e('Stardust', 'poke-hub'); ?></option>
                                <option value="xp" <?php selected($reward['type'] ?? '', 'xp'); ?>><?php _e('XP', 'poke-hub'); ?></option>
                                <option value="candy" <?php selected($reward['type'] ?? '', 'candy'); ?>><?php _e('Candy', 'poke-hub'); ?></option>
                                <option value="mega_energy" <?php selected($reward['type'] ?? '', 'mega_energy'); ?>><?php _e('Mega Energy', 'poke-hub'); ?></option>
                                <option value="item" <?php selected($reward['type'] ?? '', 'item'); ?>><?php _e('Item', 'poke-hub'); ?></option>
                            </select>
                        </label>
                        
                        <div class="pokehub-reward-fields" style="margin-top: 10px;">
                            <?php
                            $reward_type = $reward['type'] ?? 'pokemon';
                            if ($reward_type === 'pokemon') {
                                $selected_pokemon_ids = isset($reward['pokemon_ids']) && is_array($reward['pokemon_ids']) 
                                    ? array_map('intval', $reward['pokemon_ids']) 
                                    : [];
                                ?>
                                <label><?php _e('Pokémon', 'poke-hub'); ?>: 
                                    <select 
                                        name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][pokemon_ids][]" 
                                        class="pokehub-select-pokemon" 
                                        multiple
                                        style="width: 100%; min-width: 250px;"
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
                                <?php
                            } elseif ($reward_type === 'stardust' || $reward_type === 'xp') {
                                ?>
                                <label><?php _e('Quantity', 'poke-hub'); ?>: 
                                    <input type="number" name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][quantity]" value="<?php echo esc_attr($reward['quantity'] ?? 1); ?>" min="1" />
                                </label>
                                <?php
                            } elseif ($reward_type === 'item') {
                                $selected_item_id = isset($reward['item_id']) ? (int) $reward['item_id'] : 0;
                                ?>
                                <label><?php _e('Item', 'poke-hub'); ?>: 
                                    <select 
                                        name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][item_id]" 
                                        class="pokehub-select-item" 
                                        style="width: 100%; min-width: 250px;"
                                    >
                                        <option value=""><?php _e('Select an item', 'poke-hub'); ?></option>
                                        <?php
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
                                </label>
                                <input type="hidden" name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][item_name]" class="pokehub-item-name-field" value="<?php echo esc_attr($reward['item_name'] ?? ''); ?>" />
                                <label><?php _e('Quantity', 'poke-hub'); ?>: 
                                    <input type="number" name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][quantity]" value="<?php echo esc_attr($reward['quantity'] ?? 1); ?>" min="1" />
                                </label>
                                <?php
                            } elseif ($reward_type === 'candy' || $reward_type === 'mega_energy') {
                                $selected_pokemon_id = isset($reward['pokemon_id']) ? (int) $reward['pokemon_id'] : 0;
                                ?>
                                <label><?php _e('Pokémon', 'poke-hub'); ?>: 
                                    <select 
                                        name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][pokemon_id]" 
                                        class="pokehub-select-pokemon-resource" 
                                        style="width: 100%; min-width: 250px;"
                                    >
                                        <option value=""><?php _e('Select a Pokémon', 'poke-hub'); ?></option>
                                        <?php
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
                                <label><?php _e('Quantity', 'poke-hub'); ?>: 
                                    <input type="number" name="pokehub_collection_challenges[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][quantity]" value="<?php echo esc_attr($reward['quantity'] ?? 1); ?>" min="1" />
                                </label>
                                <?php
                            }
                            ?>
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
 * Sauvegarde des défis de collection
 */
function pokehub_save_collection_challenges_metabox($post_id) {
    // Vérifications de sécurité
    if (!isset($_POST['pokehub_collection_challenges_nonce']) || 
        !wp_verify_nonce($_POST['pokehub_collection_challenges_nonce'], 'pokehub_save_collection_challenges')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Sauvegarder les défis
    if (isset($_POST['pokehub_collection_challenges']) && is_array($_POST['pokehub_collection_challenges'])) {
        pokehub_save_collection_challenges($post_id, $_POST['pokehub_collection_challenges']);
    } elseif (function_exists('pokehub_content_save_collection_challenges')) {
        pokehub_content_save_collection_challenges('post', $post_id, []);
    }
}
add_action('save_post', 'pokehub_save_collection_challenges_metabox');

