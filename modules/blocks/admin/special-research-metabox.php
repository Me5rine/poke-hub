<?php
// modules/blocks/admin/special-research-metabox.php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pokehub_special_research_extract_reward_pokemon_ids')) {
    /**
     * IDs Pokémon d’une récompense (debug metabox) — gère les tokens `id|genre`.
     *
     * @param array $r
     * @return int[]
     */
    function pokehub_special_research_extract_reward_pokemon_ids(array $r): array {
        if (function_exists('pokehub_quests_parse_pokemon_ids_from_reward_input')) {
            return pokehub_quests_parse_pokemon_ids_from_reward_input($r);
        }
        $ids = [];
        if (isset($r['pokemon_ids'])) {
            $ids = is_array($r['pokemon_ids'])
                ? array_map('intval', array_filter($r['pokemon_ids']))
                : (is_string($r['pokemon_ids']) ? array_map('intval', array_filter(explode(',', $r['pokemon_ids']))) : []);
        } elseif (isset($r['pokemon_id']) && is_numeric($r['pokemon_id']) && (int) $r['pokemon_id'] > 0) {
            $ids = [(int) $r['pokemon_id']];
        }
        return $ids;
    }
}

/**
 * Ajoute la meta box pour les études spéciales
 */
function pokehub_add_special_research_metabox() {
    $screens = apply_filters('pokehub_special_research_post_types', ['post', 'pokehub_event']);
    
    foreach ($screens as $screen) {
        add_meta_box(
            'pokehub_special_research',
            __('Special Research', 'poke-hub'),
            'pokehub_render_special_research_metabox',
            $screen,
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'pokehub_add_special_research_metabox');

/**
 * Charge les assets nécessaires pour la meta box
 */
function pokehub_special_research_metabox_assets($hook) {
    global $post;
    
    $allowed_types = apply_filters('pokehub_special_research_post_types', ['post', 'pokehub_event']);
    
    if (!in_array($hook, ['post.php', 'post-new.php']) || !in_array(get_post_type($post), $allowed_types)) {
        return;
    }
    
    // Charger Select2
    wp_enqueue_script('pokehub-admin-select2');
    wp_enqueue_style('pokehub-admin-select2');
    
    // Liste Pokémon nécessaire pour que l’init Select2 ne reconstruise pas à partir d’un tableau vide (sinon il écrase les <option selected>).
    // Liste vide : Select2 utilisera l'AJAX (recherche). Les options présélectionnées viennent du HTML ou de data-selected-ids + fallback BDD.
    $pokemon_list = function_exists('pokehub_get_pokemon_for_select') ? pokehub_get_pokemon_for_select() : [];
    wp_localize_script('pokehub-admin-select2', 'pokehubQuestsData', [
        'pokemon' => $pokemon_list,
        'items' => function_exists('pokehub_get_items_for_select') ? pokehub_get_items_for_select() : [],
        'nonce' => wp_create_nonce('pokehub_quests_ajax'),
        'rest_nonce' => wp_create_nonce('wp_rest'),
        'rest_pokemon_url' => rest_url('poke-hub/v1/pokemon-for-select'),
    ]);
    wp_localize_script('pokehub-admin-select2', 'pokehubPokemonGenderConfig', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('pokehub_check_pokemon_gender_dimorphism_nonce'),
    ]);
}
add_action('admin_enqueue_scripts', 'pokehub_special_research_metabox_assets', 9999);

/**
 * Affiche la meta box
 */
function pokehub_render_special_research_metabox($post) {
    wp_nonce_field('pokehub_save_special_research', 'pokehub_special_research_nonce');
    
    $research = [];
    $research_type = 'special';
    if (function_exists('pokehub_content_get_special_research')) {
        $data = pokehub_content_get_special_research('post', (int) $post->ID);
        $research = isset($data['steps']) ? $data['steps'] : [];
        $research_type = isset($data['research_type']) ? $data['research_type'] : 'special';
    }
    if (empty($research_type)) {
        $research_type = 'special';
    }

    // Liste complète des Pokémon pour les selects (même principe que Pokémon sauvages)
    $GLOBALS['pokehub_sr_pokemon_list'] = function_exists('pokehub_get_pokemon_for_select') ? pokehub_get_pokemon_for_select() : [];

    // Récupérer les pokemon_ids depuis la BDD brute pour chaque reward (fallback si la normalisation n'a pas rempli)
    $debug_db_rewards_pokemon = [];
    if (function_exists('pokehub_content_get_special_research_row') && function_exists('pokehub_get_table')) {
        global $wpdb;
        $row = pokehub_content_get_special_research_row('post', (int) $post->ID);
        if ($row) {
            $steps_tbl = pokehub_get_table('content_special_research_steps');
            if ($steps_tbl) {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT step_data FROM {$steps_tbl} WHERE content_research_id = %d ORDER BY sort_order ASC, id ASC",
                    (int) $row->id
                ));
                foreach ($rows as $ri => $step_row) {
                    if (empty($step_row->step_data)) {
                        continue;
                    }
                    $raw = is_string($step_row->step_data) ? json_decode($step_row->step_data, true) : $step_row->step_data;
                    if (is_object($raw)) {
                        $raw = json_decode(wp_json_encode($raw), true);
                    }
                    if (!is_array($raw)) {
                        continue;
                    }
                    foreach (['common_initial_steps' => '', 'common_final_steps' => 'f_'] as $step_key => $key_prefix) {
                        if (empty($raw[$step_key]) || !is_array($raw[$step_key])) {
                            continue;
                        }
                        foreach ($raw[$step_key] as $si => $step) {
                            if (is_object($step)) {
                                $step = json_decode(wp_json_encode($step), true);
                            }
                            if (empty($step['quests']) || !is_array($step['quests'])) {
                                continue;
                            }
                            foreach ($step['quests'] as $qi => $quest) {
                                if (is_object($quest)) {
                                    $quest = json_decode(wp_json_encode($quest), true);
                                }
                                if (empty($quest['rewards']) || !is_array($quest['rewards'])) {
                                    continue;
                                }
                                foreach ($quest['rewards'] as $rwi => $r) {
                                    if (is_object($r)) {
                                        $r = json_decode(wp_json_encode($r), true);
                                    }
                                    if (!is_array($r) || (isset($r['type']) && $r['type'] !== 'pokemon')) {
                                        continue;
                                    }
                                    $ids = pokehub_special_research_extract_reward_pokemon_ids($r);
                                    if (!empty($ids)) {
                                        $debug_db_rewards_pokemon[$ri . '_' . $key_prefix . $si . '_' . $qi . '_' . $rwi] = implode(',', $ids);
                                    }
                                }
                            }
                        }
                    }
                    if (isset($raw['paths']) && is_array($raw['paths'])) {
                        foreach ($raw['paths'] as $pi => $path) {
                            if (empty($path['steps']) || !is_array($path['steps'])) {
                                continue;
                            }
                            foreach ($path['steps'] as $si => $step) {
                                if (is_object($step)) {
                                    $step = json_decode(wp_json_encode($step), true);
                                }
                                if (empty($step['quests']) || !is_array($step['quests'])) {
                                    continue;
                                }
                                foreach ($step['quests'] as $qi => $quest) {
                                    if (empty($quest['rewards']) || !is_array($quest['rewards'])) {
                                        continue;
                                    }
                                    foreach ($quest['rewards'] as $rwi => $r) {
                                        if (is_object($r)) {
                                            $r = json_decode(wp_json_encode($r), true);
                                        }
                                        if (!is_array($r) || (isset($r['type']) && $r['type'] !== 'pokemon')) {
                                            continue;
                                        }
                                        $ids = pokehub_special_research_extract_reward_pokemon_ids($r);
                                        if (!empty($ids)) {
                                            $debug_db_rewards_pokemon[$ri . '_p' . $pi . '_' . $si . '_' . $qi . '_' . $rwi] = implode(',', $ids);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    $debug_db_pokemon_ids = isset($debug_db_rewards_pokemon['0_0_0_0']) ? $debug_db_rewards_pokemon['0_0_0_0'] : '';
    $GLOBALS['pokehub_sr_debug_db_rewards_pokemon'] = $debug_db_rewards_pokemon;
    ?>
    <div class="pokehub-special-research-metabox" data-debug-db-pokemon-ids="<?php echo esc_attr($debug_db_pokemon_ids); ?>">
        <p>
            <?php _e('Add special research studies for this event. Each study can have multiple steps with quests, and paths can split the scenario.', 'poke-hub'); ?>
        </p>
        
        <div>
            <label>
                <strong><?php _e('Research Type', 'poke-hub'); ?>:</strong>
                <select name="pokehub_special_research_type">
                    <option value="timed" <?php selected($research_type, 'timed'); ?>><?php _e('Timed Research', 'poke-hub'); ?></option>
                    <option value="special" <?php selected($research_type, 'special'); ?>><?php _e('Special Research', 'poke-hub'); ?></option>
                    <option value="masterwork" <?php selected($research_type, 'masterwork'); ?>><?php _e('Masterwork Research', 'poke-hub'); ?></option>
                </select>
            </label>
        </div>
        
        <div id="pokehub-special-research-list">
            <?php if (!empty($research)) : ?>
                <?php foreach ($research as $index => $research_item) : ?>
                    <?php pokehub_render_special_research_editor_item($index, $research_item); ?>
                <?php endforeach; ?>
            <?php else : ?>
                <?php pokehub_render_special_research_editor_item(0, []); ?>
            <?php endif; ?>
        </div>
        
        <button type="button" class="button button-secondary" id="pokehub-add-special-research">
            <?php _e('Add Research', 'poke-hub'); ?>
        </button>
    </div>
    
    <script type="text/template" id="pokehub-special-research-template">
        <?php pokehub_render_special_research_editor_item('{{INDEX}}', []); ?>
    </script>
    
    <script>
    jQuery(document).ready(function($) {
        // Réappliquer la pré-sélection après init Select2 (Select2 peut avoir vidé/remplacé les options)
        function applyPreselectedPokemon(root) {
            var $root = root ? $(root) : $(document);
            $root.find('.pokehub-special-research-metabox select.pokehub-select-pokemon').each(function() {
                var $select = $(this);
                var raw = ($select.attr('data-selected-ids') || '').trim();
                if (!raw) return;
                var ids = raw.split(',').map(function(v) { return String(parseInt(v, 10)); })
                    .filter(function(v) { return v !== 'NaN' && v !== '0'; });
                if (!ids.length) return;
                ids.forEach(function(id) {
                    if ($select.find('option[value="' + id + '"]').length === 0) {
                        $select.append(new Option('#' + id, id, true, true));
                    }
                });
                $select.val(ids).trigger('change');
            });
        }

        // Initialiser Select2 puis réappliquer les présélections (fix : forcer l’état final même si l’init a reconstruit)
        function initSelect2(ctx) {
            var $sr = $('.pokehub-special-research-metabox');
            var root = (ctx) ? ctx : ($sr.length ? $sr[0] : document);
            applyPreselectedPokemon(root);
            if (window.pokehubInitQuestPokemonSelect2) {
                window.pokehubInitQuestPokemonSelect2(root);
            }
            if (window.pokehubInitQuestItemSelect2) {
                window.pokehubInitQuestItemSelect2(root);
            }
            if (window.pokehubInitPokemonGenderSelectors) {
                window.pokehubInitPokemonGenderSelectors(root);
            }
            applyPreselectedPokemon(root);
            setTimeout(function() { applyPreselectedPokemon(root); }, 100);
            setTimeout(function() { applyPreselectedPokemon(root); }, 400);
            // Réappliquer la valeur après init Select2 (au cas où Select2 a recréé le DOM)
            setTimeout(function() {
                var $root = root ? $(root) : $(document);
                $root.find('.pokehub-special-research-metabox select.pokehub-select-pokemon').each(function() {
                    var $s = $(this);
                    if (!$s.data('select2')) return;
                    var raw = ($s.attr('data-selected-ids') || '').trim();
                    if (!raw) return;
                    var ids = raw.split(',').map(function(v) { return String(parseInt(v, 10)); }).filter(function(v) { return v !== 'NaN' && v !== '0'; });
                    if (ids.length) $s.val(ids).trigger('change');
                });
            }, 200);
        }

        initSelect2();
        // Ré-init si la metabox est rendue plus tard (éditeur de blocs / panneau chargé après document.ready)
        setTimeout(function() { initSelect2(); }, 800);
        setTimeout(function() { initSelect2(); }, 2000);

        // Ajouter une nouvelle étude
        $('#pokehub-add-special-research').on('click', function() {
            var template = $('#pokehub-special-research-template').html();
            var index = $('#pokehub-special-research-list .pokehub-special-research-item-editor').length;
            var $newResearch = $(template.replace(/\{\{INDEX\}\}/g, index));
            $('#pokehub-special-research-list').append($newResearch);
            initSelect2($newResearch[0]);
        });
        
        // Supprimer une étude
        $(document).on('click', '.pokehub-remove-research', function() {
            if (confirm('<?php echo esc_js(__('Are you sure you want to remove this research?', 'poke-hub')); ?>')) {
                $(this).closest('.pokehub-special-research-item-editor').remove();
            }
        });
        
        // Fonction helper pour générer le HTML d'une étape
        function generateStepHtml(researchIndex, stepIndex, context) {
            var namePrefix = 'pokehub_special_research[' + researchIndex + ']';
            if (context === 'common_initial') {
                namePrefix += '[common_initial_steps][' + stepIndex + ']';
            } else if (context === 'common_final') {
                namePrefix += '[common_final_steps][' + stepIndex + ']';
            } else if (context.indexOf('path_') === 0) {
                var pathIndex = context.replace('path_', '');
                namePrefix += '[paths][' + pathIndex + '][steps][' + stepIndex + ']';
            } else {
                namePrefix += '[steps][' + stepIndex + ']';
            }
            
            return '<div class="pokehub-special-research-step-editor" data-step-index="' + stepIndex + '" data-context="' + context + '">' +
                '<h5><?php echo esc_js(__('Step', 'poke-hub')); ?> #' + (stepIndex + 1) + 
                ' <button type="button" class="button-link pokehub-remove-step"><?php echo esc_js(__('Remove', 'poke-hub')); ?></button></h5>' +
                '<input type="hidden" name="' + namePrefix + '[type]" value="quest" />' +
                '<div class="pokehub-step-quests-editor" style="margin-top: 10px;">' +
                '<strong><?php echo esc_js(__('Quests', 'poke-hub')); ?>:</strong>' +
                '<div class="pokehub-step-quests-list"></div>' +
                '<button type="button" class="button button-small pokehub-add-quest"><?php echo esc_js(__('Add Quest', 'poke-hub')); ?></button>' +
                '</div>' +
                '<div class="pokehub-step-rewards-editor" style="margin-top: 15px;">' +
                '<strong><?php echo esc_js(__('Step Rewards', 'poke-hub')); ?>:</strong>' +
                '<div class="pokehub-step-rewards-list"></div>' +
                '<button type="button" class="button button-small pokehub-add-step-reward"><?php echo esc_js(__('Add Reward', 'poke-hub')); ?></button>' +
                '</div>' +
                '</div>';
        }
        
        // Ajouter une étape commune initiale
        $(document).on('click', '.pokehub-add-common-initial-step', function() {
            var researchItem = $(this).closest('.pokehub-special-research-item-editor');
            var stepIndex = researchItem.find('.pokehub-common-initial-steps-list .pokehub-special-research-step-editor').length;
            var researchIndex = researchItem.data('research-index') || 0;
            var stepHtml = generateStepHtml(researchIndex, stepIndex, 'common_initial');
            $(this).siblings('.pokehub-common-initial-steps-list').append($(stepHtml));
            initSelect2();
        });
        
        // Ajouter une étape commune finale
        $(document).on('click', '.pokehub-add-common-final-step', function() {
            var researchItem = $(this).closest('.pokehub-special-research-item-editor');
            var stepIndex = researchItem.find('.pokehub-common-final-steps-list .pokehub-special-research-step-editor').length;
            var researchIndex = researchItem.data('research-index') || 0;
            var stepHtml = generateStepHtml(researchIndex, stepIndex, 'common_final');
            $(this).siblings('.pokehub-common-final-steps-list').append($(stepHtml));
            initSelect2();
        });
        
        // Ajouter un chemin
        $(document).on('click', '.pokehub-add-path', function() {
            var researchItem = $(this).closest('.pokehub-special-research-item-editor');
            var pathIndex = researchItem.find('.pokehub-special-research-path-editor').length;
            var researchIndex = researchItem.data('research-index') || 0;
            
            var pathHtml = '<div class="pokehub-special-research-path-editor" data-path-index="' + pathIndex + '" style="margin-top: 10px; padding: 10px; background: #fff; border: 2px solid #4a90e2;">' +
                '<h5 style="margin-top: 0;"><?php echo esc_js(__('Path', 'poke-hub')); ?> #' + (pathIndex + 1) + 
                ' <button type="button" class="button-link pokehub-remove-path"><?php echo esc_js(__('Remove', 'poke-hub')); ?></button></h5>' +
                '<label><strong><?php echo esc_js(__('Path Name', 'poke-hub')); ?>:</strong><br>' +
                '<input type="text" name="pokehub_special_research[' + researchIndex + '][paths][' + pathIndex + '][name]" class="widefat" /></label>' +
                '<label style="margin-top: 10px;"><strong><?php echo esc_js(__('Path Image URL', 'poke-hub')); ?>:</strong><br>' +
                '<input type="url" name="pokehub_special_research[' + researchIndex + '][paths][' + pathIndex + '][image_url]" class="widefat" /></label>' +
                '<label style="margin-top: 10px; display: block;"><strong><?php echo esc_js(__('Path Color', 'poke-hub')); ?>:</strong><br>' +
                '<input type="color" name="pokehub_special_research[' + researchIndex + '][paths][' + pathIndex + '][color]" value="#ff6b6b" style="width: 100px; height: 40px; margin-top: 5px; cursor: pointer;" />' +
                '<span style="margin-left: 10px; color: #666; vertical-align: middle;"><?php echo esc_js(__('Color for the path header', 'poke-hub')); ?></span></label>' +
                '<div class="pokehub-path-steps-editor" style="margin-top: 15px;">' +
                '<strong><?php echo esc_js(__('Path Steps', 'poke-hub')); ?>:</strong>' +
                '<div class="pokehub-path-steps-list"></div>' +
                '<button type="button" class="button button-small pokehub-add-path-step"><?php echo esc_js(__('Add Path Step', 'poke-hub')); ?></button>' +
                '</div>' +
                '</div>';
            
            $(this).siblings('.pokehub-paths-list').append($(pathHtml));
            initSelect2();
        });
        
        // Supprimer un chemin
        $(document).on('click', '.pokehub-remove-path', function() {
            $(this).closest('.pokehub-special-research-path-editor').remove();
        });
        
        // Ajouter une étape dans un chemin
        $(document).on('click', '.pokehub-add-path-step', function() {
            var pathEditor = $(this).closest('.pokehub-special-research-path-editor');
            var researchItem = pathEditor.closest('.pokehub-special-research-item-editor');
            var stepIndex = pathEditor.find('.pokehub-path-steps-list .pokehub-special-research-step-editor').length;
            var researchIndex = researchItem.data('research-index') || 0;
            var pathIndex = pathEditor.data('path-index') || 0;
            var stepHtml = generateStepHtml(researchIndex, stepIndex, 'path_' + pathIndex);
            $(this).siblings('.pokehub-path-steps-list').append($(stepHtml));
            initSelect2();
        });
        
        // Supprimer une étape
        $(document).on('click', '.pokehub-remove-step', function() {
            $(this).closest('.pokehub-special-research-step-editor').remove();
        });
        
        // Fonction helper pour générer le nom de base selon le contexte
        function getNameBase(researchIndex, stepIndex, context, questIndex) {
            var nameBase = 'pokehub_special_research[' + researchIndex + ']';
            if (context === 'common_initial') {
                nameBase += '[common_initial_steps][' + stepIndex + ']';
            } else if (context === 'common_final') {
                nameBase += '[common_final_steps][' + stepIndex + ']';
            } else if (context.indexOf('path_') === 0) {
                var pathIndex = context.replace('path_', '');
                nameBase += '[paths][' + pathIndex + '][steps][' + stepIndex + ']';
            } else {
                nameBase += '[steps][' + stepIndex + ']';
            }
            if (questIndex !== undefined && questIndex !== null) {
                nameBase += '[quests][' + questIndex + ']';
            }
            return nameBase;
        }
        
        // Ajouter une quête
        $(document).on('click', '.pokehub-add-quest', function() {
            var stepEditor = $(this).closest('.pokehub-special-research-step-editor');
            var questIndex = stepEditor.find('.pokehub-quest-editor').length;
            var researchIndex = stepEditor.closest('.pokehub-special-research-item-editor').data('research-index') || 0;
            var stepIndex = stepEditor.data('step-index') || 0;
            var context = stepEditor.data('context') || 'common_initial';
            var nameBase = getNameBase(researchIndex, stepIndex, context, questIndex);
            
            var questHtml = '<div class="pokehub-quest-editor" style="margin-top: 10px; padding: 10px; background: #f0f0f0; border: 1px solid #ddd;" data-context="' + context + '" data-quest-index="' + questIndex + '">' +
                '<label><?php echo esc_js(__('Task', 'poke-hub')); ?>: ' +
                '<input type="text" name="' + nameBase + '[quests][' + questIndex + '][task]" class="widefat" /></label>' +
                '<div class="pokehub-quest-rewards-editor" style="margin-top: 10px;">' +
                '<strong><?php echo esc_js(__('Quest Rewards', 'poke-hub')); ?>:</strong>' +
                '<div class="pokehub-quest-rewards-list"></div>' +
                '<button type="button" class="button button-small pokehub-add-quest-reward"><?php echo esc_js(__('Add Reward', 'poke-hub')); ?></button>' +
                '</div>' +
                '<button type="button" class="button-link pokehub-remove-quest"><?php echo esc_js(__('Remove', 'poke-hub')); ?></button>' +
                '</div>';
            
            stepEditor.find('.pokehub-step-quests-list').append($(questHtml));
            initSelect2();
        });
        
        // Supprimer une quête
        $(document).on('click', '.pokehub-remove-quest', function() {
            $(this).closest('.pokehub-quest-editor').remove();
        });
        
        // Ajouter une récompense de quête
        $(document).on('click', '.pokehub-add-quest-reward', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var questEditor = $(this).closest('.pokehub-quest-editor');
            var rewardsList = questEditor.find('.pokehub-quest-rewards-list');
            var rewardIndex = rewardsList.find('.pokehub-quest-reward-editor').length;
            var researchItem = questEditor.closest('.pokehub-special-research-item-editor');
            var stepEditor = questEditor.closest('.pokehub-special-research-step-editor');
            var researchIndex = researchItem.data('research-index') || 0;
            var stepIndex = stepEditor.data('step-index') || 0;
            var context = stepEditor.data('context') || 'common_initial';
            
            // Récupérer l'index de la quête depuis l'attribut data ou calculer
            var questIndex = questEditor.data('quest-index');
            if (questIndex === undefined || questIndex === null) {
                var questsList = questEditor.closest('.pokehub-step-quests-list');
                questIndex = questsList.find('.pokehub-quest-editor').index(questEditor);
                if (questIndex < 0) {
                    questIndex = questEditor.siblings('.pokehub-quest-editor').length;
                }
            }
            
            var nameBase = getNameBase(researchIndex, stepIndex, context, questIndex);
            
            var rewardHtml = '<div class="pokehub-quest-reward-editor" style="margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #ccc;">' +
                '<label><?php echo esc_js(__('Reward Type', 'poke-hub')); ?>: ' +
                '<select name="' + nameBase + '[rewards][' + rewardIndex + '][type]" class="pokehub-reward-type" data-context="' + context + '" data-quest-index="' + questIndex + '">' +
                '<option value="pokemon"><?php echo esc_js(__('Pokémon', 'poke-hub')); ?></option>' +
                '<option value="stardust"><?php echo esc_js(__('Stardust', 'poke-hub')); ?></option>' +
                '<option value="xp"><?php echo esc_js(__('XP', 'poke-hub')); ?></option>' +
                '<option value="item"><?php echo esc_js(__('Item', 'poke-hub')); ?></option>' +
                '</select></label>' +
                '<div class="pokehub-reward-fields" style="margin-top: 10px;"></div>' +
                '<button type="button" class="button-link pokehub-remove-reward"><?php echo esc_js(__('Remove', 'poke-hub')); ?></button>' +
                '</div>';
            
            rewardsList.append($(rewardHtml));
            $('.pokehub-reward-type').last().trigger('change');
            initSelect2();
        });
        
        // Ajouter une récompense d'étape
        $(document).on('click', '.pokehub-add-step-reward', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var stepEditor = $(this).closest('.pokehub-special-research-step-editor');
            var rewardsList = stepEditor.find('.pokehub-step-rewards-list');
            var rewardIndex = rewardsList.find('.pokehub-step-reward-editor').length;
            var researchItem = stepEditor.closest('.pokehub-special-research-item-editor');
            var researchIndex = researchItem.data('research-index') || 0;
            var stepIndex = stepEditor.data('step-index') || 0;
            var context = stepEditor.data('context') || 'common_initial';
            var nameBase = getNameBase(researchIndex, stepIndex, context, null);
            
            var rewardHtml = '<div class="pokehub-step-reward-editor" style="margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #ccc;">' +
                '<label><?php echo esc_js(__('Reward Type', 'poke-hub')); ?>: ' +
                '<select name="' + nameBase + '[rewards][' + rewardIndex + '][type]" class="pokehub-reward-type" data-context="' + context + '">' +
                '<option value="pokemon"><?php echo esc_js(__('Pokémon', 'poke-hub')); ?></option>' +
                '<option value="stardust"><?php echo esc_js(__('Stardust', 'poke-hub')); ?></option>' +
                '<option value="xp"><?php echo esc_js(__('XP', 'poke-hub')); ?></option>' +
                '<option value="item"><?php echo esc_js(__('Item', 'poke-hub')); ?></option>' +
                '</select></label>' +
                '<div class="pokehub-reward-fields" style="margin-top: 10px;"></div>' +
                '<button type="button" class="button-link pokehub-remove-reward"><?php echo esc_js(__('Remove', 'poke-hub')); ?></button>' +
                '</div>';
            
            rewardsList.append($(rewardHtml));
            $('.pokehub-reward-type').last().trigger('change');
            initSelect2();
        });
        
        // Supprimer une récompense
        $(document).on('click', '.pokehub-remove-reward', function() {
            $(this).closest('.pokehub-quest-reward-editor, .pokehub-step-reward-editor').remove();
        });
        
        // Gérer le changement de type de récompense
        $(document).on('change', '.pokehub-reward-type', function() {
            var rewardEditor = $(this).closest('.pokehub-quest-reward-editor, .pokehub-step-reward-editor');
            var type = $(this).val();
            var fieldsContainer = rewardEditor.find('.pokehub-reward-fields');
            var nameAttr = $(this).attr('name');

            // Sauvegarder les ids actuellement sélectionnés AVANT de vider (évite d'écraser ce que PHP a rendu)
            var prevIds = [];
            var prevGenders = {};
            var $prevPokemonSelect = fieldsContainer.find('select.pokehub-select-pokemon');
            if ($prevPokemonSelect.length) {
                var rawPrev = ($prevPokemonSelect.attr('data-selected-ids') || '').trim();
                if (rawPrev) {
                    prevIds = rawPrev.split(',').map(function(v) { return String(parseInt(v, 10)); }).filter(function(v) { return v !== 'NaN' && v !== '0'; });
                } else {
                    var v = $prevPokemonSelect.val();
                    if (Array.isArray(v) && v.length) prevIds = v.map(function(x) { return String(x); });
                }
                fieldsContainer.find('.pokehub-pokemon-gender-options select[data-pokemon-id]').each(function() {
                    var pid = String(parseInt($(this).attr('data-pokemon-id') || '0', 10));
                    var g = String($(this).val() || '');
                    if (pid !== '0' && pid !== 'NaN' && (g === 'male' || g === 'female')) {
                        prevGenders[pid] = g;
                    }
                });
            }

            // Extraire les indices depuis le name
            var matches = nameAttr.match(/\[(\d+)\](?:\[common_initial_steps\]|\[common_final_steps\]|\[paths\]\[(\d+)\]\[steps\]|\[steps\])\[(\d+)\](?:\[quests\]\[(\d+)\])?\[rewards\]\[(\d+)\]/);

            if (!matches) return;

            var researchIndex = matches[1];
            var pathIndex = matches[2];
            var stepIndex = matches[3];
            var questIndex = matches[4];
            var rewardIndex = matches[5];
            var context = $(this).data('context') || 'common_initial';
            if (pathIndex !== undefined) {
                context = 'path_' + pathIndex;
            } else if (nameAttr.indexOf('[common_initial_steps]') !== -1) {
                context = 'common_initial';
            } else if (nameAttr.indexOf('[common_final_steps]') !== -1) {
                context = 'common_final';
            }

            var nameBase = getNameBase(researchIndex, stepIndex, context, questIndex);
            nameBase += '[rewards][' + rewardIndex + ']';

            fieldsContainer.empty();

            if (type === 'pokemon') {
                var idsAttr = prevIds.length ? prevIds.join(',') : '';
                var gendersAttr = JSON.stringify(prevGenders);
                fieldsContainer.html(
                    '<div class="pokehub-gender-field-group">' +
                    '<label><?php echo esc_js(__('Pokémon', 'poke-hub')); ?>: ' +
                    '<select name="' + nameBase + '[pokemon_ids][]" class="pokehub-select-pokemon pokehub-sr-reward-pokemon pokehub-gender-driven-select" multiple style="width: 100%;" data-selected-ids="' + idsAttr + '" data-gender-name-template="' + nameBase + '[pokemon_genders][__POKEMON_ID__]" data-gender-scope="available" data-existing-genders=\'' + gendersAttr + '\'></select></label>' +
                    '<div class="pokehub-pokemon-gender-options" style="display:none;margin-top:8px;"></div>' +
                    '</div>'
                );
            } else if (type === 'stardust' || type === 'xp') {
                fieldsContainer.html(
                    '<label><?php echo esc_js(__('Quantity', 'poke-hub')); ?>: ' +
                    '<input type="number" name="' + nameBase + '[quantity]" value="1" min="1" /></label>'
                );
            } else if (type === 'item') {
                fieldsContainer.html(
                    '<label><?php echo esc_js(__('Item', 'poke-hub')); ?>: ' +
                    '<select name="' + nameBase + '[item_id]" class="pokehub-select-item" style="width: 100%;"></select></label>' +
                    '<input type="hidden" name="' + nameBase + '[item_name]" class="pokehub-item-name-field" />' +
                    '<label><?php echo esc_js(__('Quantity', 'poke-hub')); ?>: ' +
                    '<input type="number" name="' + nameBase + '[quantity]" value="1" min="1" /></label>'
                );
            }

            initSelect2(fieldsContainer[0]);
        });
    });
    </script>
    <?php
}

/**
 * Affiche un item d'édition d'étude spéciale
 */
function pokehub_render_special_research_editor_item($index, $research_item) {
    ?>
    <div class="pokehub-special-research-item-editor" data-research-index="<?php echo esc_attr($index); ?>">
        <h4>
            <?php _e('Research', 'poke-hub'); ?> #<?php echo is_numeric($index) ? ($index + 1) : $index; ?>
            <button type="button" class="button-link pokehub-remove-research" style="float:right;">
                <?php _e('Remove', 'poke-hub'); ?>
            </button>
        </h4>
        
        <label>
            <strong><?php _e('Research Name', 'poke-hub'); ?>:</strong><br>
            <input 
                type="text" 
                name="pokehub_special_research[<?php echo esc_attr($index); ?>][name]" 
                value="<?php echo esc_attr($research_item['name'] ?? ''); ?>" 
                class="widefat"
                placeholder="<?php esc_attr_e('e.g., Team Up With Candela', 'poke-hub'); ?>"
            />
        </label>
        
        <div class="pokehub-special-research-structure-editor" style="margin-top: 15px;">
            <strong><?php _e('Research Structure', 'poke-hub'); ?>:</strong>
            
            <!-- Étapes communes initiales -->
            <div class="pokehub-common-initial-steps" style="margin-top: 10px; padding: 10px; background: #f0f8ff; border: 1px solid #b0d4f1;">
                <h5 style="margin-top: 0;">
                    <?php _e('Common Initial Steps', 'poke-hub'); ?>
                    <small style="font-weight: normal; color: #666;"><?php _e('(Steps all users must complete before path selection)', 'poke-hub'); ?></small>
                </h5>
                <div class="pokehub-common-initial-steps-list">
                    <?php if (!empty($research_item['common_initial_steps']) && is_array($research_item['common_initial_steps'])) : ?>
                        <?php foreach ($research_item['common_initial_steps'] as $step_index => $step) : ?>
                            <?php pokehub_render_special_research_step_editor($index, $step_index, $step, 'common_initial'); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="button button-small pokehub-add-common-initial-step">
                    <?php _e('Add Common Initial Step', 'poke-hub'); ?>
                </button>
            </div>
            
            <!-- Sélection de chemin -->
            <div class="pokehub-path-selection" style="margin-top: 15px; padding: 10px; background: #fff8dc; border: 1px solid #f0e68c;">
                <h5 style="margin-top: 0;">
                    <?php _e('Path Selection', 'poke-hub'); ?>
                    <small style="font-weight: normal; color: #666;"><?php _e('(Where the research splits into different paths)', 'poke-hub'); ?></small>
                </h5>
                <div class="pokehub-paths-list">
                    <?php if (!empty($research_item['paths']) && is_array($research_item['paths'])) : ?>
                        <?php foreach ($research_item['paths'] as $path_index => $path) : ?>
                            <?php pokehub_render_special_research_path_editor($index, $path_index, $path); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="button button-small pokehub-add-path">
                    <?php _e('Add Path', 'poke-hub'); ?>
                </button>
            </div>
            
            <!-- Étapes communes finales -->
            <div class="pokehub-common-final-steps" style="margin-top: 15px; padding: 10px; background: #f0fff0; border: 1px solid #90ee90;">
                <h5 style="margin-top: 0;">
                    <?php _e('Common Final Steps', 'poke-hub'); ?>
                    <small style="font-weight: normal; color: #666;"><?php _e('(Steps all users complete after finishing a path)', 'poke-hub'); ?></small>
                </h5>
                <div class="pokehub-common-final-steps-list">
                    <?php if (!empty($research_item['common_final_steps']) && is_array($research_item['common_final_steps'])) : ?>
                        <?php foreach ($research_item['common_final_steps'] as $step_index => $step) : ?>
                            <?php pokehub_render_special_research_step_editor($index, $step_index, $step, 'common_final'); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="button button-small pokehub-add-common-final-step">
                    <?php _e('Add Common Final Step', 'poke-hub'); ?>
                </button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Affiche un éditeur d'étape
 */
function pokehub_render_special_research_step_editor($research_index, $step_index, $step, $context = 'common_initial') {
    $step_type = $step['type'] ?? 'quest';
    $name_prefix = 'pokehub_special_research[' . $research_index . ']';
    
    // Déterminer le préfixe selon le contexte
    if ($context === 'common_initial') {
        $name_prefix .= '[common_initial_steps][' . $step_index . ']';
    } elseif ($context === 'common_final') {
        $name_prefix .= '[common_final_steps][' . $step_index . ']';
    } else {
        $name_prefix .= '[steps][' . $step_index . ']';
    }
    ?>
    <div class="pokehub-special-research-step-editor" data-step-index="<?php echo esc_attr($step_index); ?>" data-context="<?php echo esc_attr($context); ?>">
        <h5>
            <?php _e('Step', 'poke-hub'); ?> #<?php echo ($step_index + 1); ?>
            <button type="button" class="button-link pokehub-remove-step"><?php _e('Remove', 'poke-hub'); ?></button>
        </h5>
        
        <input type="hidden" name="<?php echo esc_attr($name_prefix); ?>[type]" value="quest" />
        
        <div class="pokehub-step-quests-editor" style="margin-top: 10px;">
            <strong><?php _e('Quests', 'poke-hub'); ?>:</strong>
            <div class="pokehub-step-quests-list">
                <?php if (!empty($step['quests']) && is_array($step['quests'])) : ?>
                    <?php foreach ($step['quests'] as $quest_index => $quest) : ?>
                        <?php pokehub_render_special_research_quest_editor($research_index, $step_index, $quest_index, $quest, $context); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="button button-small pokehub-add-quest">
                <?php _e('Add Quest', 'poke-hub'); ?>
            </button>
        </div>
        
        <div class="pokehub-step-rewards-editor" style="margin-top: 15px;">
            <strong><?php _e('Step Rewards', 'poke-hub'); ?>:</strong>
            <div class="pokehub-step-rewards-list">
                <?php if (!empty($step['rewards']) && is_array($step['rewards'])) : ?>
                    <?php foreach ($step['rewards'] as $reward_index => $reward) : ?>
                        <?php pokehub_render_special_research_reward_editor($research_index, $step_index, $reward_index, $reward, 'step', null, $context); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="button button-small pokehub-add-step-reward">
                <?php _e('Add Reward', 'poke-hub'); ?>
            </button>
        </div>
    </div>
    <?php
}

/**
 * Affiche un éditeur de chemin avec ses étapes
 */
function pokehub_render_special_research_path_editor($research_index, $path_index, $path) {
    ?>
    <div class="pokehub-special-research-path-editor" data-path-index="<?php echo esc_attr($path_index); ?>" style="margin-top: 10px; padding: 10px; background: #fff; border: 2px solid #4a90e2;">
        <h5 style="margin-top: 0;">
            <?php _e('Path', 'poke-hub'); ?> #<?php echo ($path_index + 1); ?>
            <button type="button" class="button-link pokehub-remove-path"><?php _e('Remove', 'poke-hub'); ?></button>
        </h5>
        
        <label>
            <strong><?php _e('Path Name', 'poke-hub'); ?>:</strong><br>
            <input 
                type="text" 
                name="pokehub_special_research[<?php echo esc_attr($research_index); ?>][paths][<?php echo esc_attr($path_index); ?>][name]" 
                value="<?php echo esc_attr($path['name'] ?? ''); ?>" 
                class="widefat"
                placeholder="<?php esc_attr_e('e.g., Team Up With Candela', 'poke-hub'); ?>"
            />
        </label>
        
        <label style="margin-top: 10px;">
            <strong><?php _e('Path Image URL', 'poke-hub'); ?>:</strong><br>
            <input 
                type="url" 
                name="pokehub_special_research[<?php echo esc_attr($research_index); ?>][paths][<?php echo esc_attr($path_index); ?>][image_url]" 
                value="<?php echo esc_url($path['image_url'] ?? ''); ?>" 
                class="widefat"
            />
        </label>
        
        <label style="margin-top: 10px; display: block;">
            <strong><?php _e('Path Color', 'poke-hub'); ?>:</strong><br>
            <input 
                type="color" 
                name="pokehub_special_research[<?php echo esc_attr($research_index); ?>][paths][<?php echo esc_attr($path_index); ?>][color]" 
                value="<?php echo esc_attr($path['color'] ?? '#ff6b6b'); ?>" 
                style="width: 100px; height: 40px; margin-top: 5px; cursor: pointer;"
            />
            <span style="margin-left: 10px; color: #666; vertical-align: middle;"><?php _e('Color for the path header', 'poke-hub'); ?></span>
        </label>
        
        <div class="pokehub-path-steps-editor" style="margin-top: 15px;">
            <strong><?php _e('Path Steps', 'poke-hub'); ?>:</strong>
            <div class="pokehub-path-steps-list">
                <?php if (!empty($path['steps']) && is_array($path['steps'])) : ?>
                    <?php foreach ($path['steps'] as $step_index => $step) : ?>
                        <?php pokehub_render_special_research_step_editor($research_index, $step_index, $step, 'path_' . $path_index); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="button button-small pokehub-add-path-step">
                <?php _e('Add Path Step', 'poke-hub'); ?>
            </button>
        </div>
    </div>
    <?php
}

/**
 * Affiche un éditeur de quête
 */
function pokehub_render_special_research_quest_editor($research_index, $step_index, $quest_index, $quest, $context = 'common_initial') {
    $name_prefix = 'pokehub_special_research[' . $research_index . ']';
    
    // Déterminer le préfixe selon le contexte
    if ($context === 'common_initial') {
        $name_prefix .= '[common_initial_steps][' . $step_index . ']';
    } elseif ($context === 'common_final') {
        $name_prefix .= '[common_final_steps][' . $step_index . ']';
    } elseif (strpos($context, 'path_') === 0) {
        // Extraire l'index du chemin
        $path_index = (int) str_replace('path_', '', $context);
        $name_prefix .= '[paths][' . $path_index . '][steps][' . $step_index . ']';
    } else {
        $name_prefix .= '[steps][' . $step_index . ']';
    }
    ?>
    <div class="pokehub-quest-editor" style="margin-top: 10px; padding: 10px; background: #f0f0f0; border: 1px solid #ddd;" data-context="<?php echo esc_attr($context); ?>" data-quest-index="<?php echo esc_attr($quest_index); ?>">
        <label>
            <?php _e('Task', 'poke-hub'); ?>:
            <input 
                type="text" 
                name="<?php echo esc_attr($name_prefix); ?>[quests][<?php echo esc_attr($quest_index); ?>][task]" 
                value="<?php echo esc_attr($quest['task'] ?? ''); ?>" 
                class="widefat"
            />
        </label>
        
        <div class="pokehub-quest-rewards-editor" style="margin-top: 10px;">
            <strong><?php _e('Quest Rewards', 'poke-hub'); ?>:</strong>
            <div class="pokehub-quest-rewards-list">
                <?php if (!empty($quest['rewards']) && is_array($quest['rewards'])) : ?>
                    <?php foreach ($quest['rewards'] as $reward_index => $reward) : ?>
                        <?php pokehub_render_special_research_reward_editor($research_index, $step_index, $reward_index, $reward, 'quest', $quest_index, $context); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="button button-small pokehub-add-quest-reward">
                <?php _e('Add Reward', 'poke-hub'); ?>
            </button>
        </div>
        
        <button type="button" class="button-link pokehub-remove-quest"><?php _e('Remove', 'poke-hub'); ?></button>
    </div>
    <?php
}

/**
 * Affiche un éditeur de récompense
 */
function pokehub_render_special_research_reward_editor($research_index, $step_index, $reward_index, $reward, $context = 'step', $quest_index = null, $step_context = 'common_initial') {
    if (is_object($reward)) {
        $reward = json_decode(wp_json_encode($reward), true);
    }
    $reward = is_array($reward) ? $reward : [];
    $reward_type = $reward['type'] ?? 'pokemon';
    $name_base = 'pokehub_special_research[' . $research_index . ']';
    
    // Déterminer le préfixe selon le contexte de l'étape
    if ($step_context === 'common_initial') {
        $name_base .= '[common_initial_steps][' . $step_index . ']';
    } elseif ($step_context === 'common_final') {
        $name_base .= '[common_final_steps][' . $step_index . ']';
    } elseif (strpos($step_context, 'path_') === 0) {
        // Extraire l'index du chemin
        $path_index = (int) str_replace('path_', '', $step_context);
        $name_base .= '[paths][' . $path_index . '][steps][' . $step_index . ']';
    } else {
        $name_base .= '[steps][' . $step_index . ']';
    }
    
    if ($context === 'quest' && $quest_index !== null) {
        $name_base .= '[quests][' . $quest_index . ']';
    }
    $name_base .= '[rewards][' . $reward_index . ']';
    ?>
    <div class="pokehub-<?php echo esc_attr($context); ?>-reward-editor" style="margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #ccc;">
        <label>
            <?php _e('Reward Type', 'poke-hub'); ?>:
            <select name="<?php echo esc_attr($name_base); ?>[type]" class="pokehub-reward-type">
                <option value="pokemon" <?php selected($reward_type, 'pokemon'); ?>><?php _e('Pokémon', 'poke-hub'); ?></option>
                <option value="stardust" <?php selected($reward_type, 'stardust'); ?>><?php _e('Stardust', 'poke-hub'); ?></option>
                <option value="xp" <?php selected($reward_type, 'xp'); ?>><?php _e('XP', 'poke-hub'); ?></option>
                <option value="item" <?php selected($reward_type, 'item'); ?>><?php _e('Item', 'poke-hub'); ?></option>
            </select>
        </label>
        
        <div class="pokehub-reward-fields" style="margin-top: 10px;">
            <?php
            if ($reward_type === 'pokemon') {
                // Extraire les IDs (tokens multiselect `id|male` / `id|female` inclus).
                $gender_seed = isset($reward['pokemon_genders']) && is_array($reward['pokemon_genders'])
                    ? $reward['pokemon_genders']
                    : [];
                $raw_tokens = [];
                if (isset($reward['pokemon_ids'])) {
                    $saved = $reward['pokemon_ids'];
                    if (is_object($saved)) {
                        $saved = json_decode(wp_json_encode($saved), true);
                    }
                    if (is_string($saved)) {
                        foreach (array_map('trim', explode(',', $saved)) as $part) {
                            if ($part !== '') {
                                $raw_tokens[] = $part;
                            }
                        }
                    } elseif (is_array($saved)) {
                        foreach ($saved as $v) {
                            if ($v === '' || $v === null) {
                                continue;
                            }
                            $raw_tokens[] = is_string($v) ? $v : (string) $v;
                        }
                    } else {
                        foreach ((array) $saved as $v) {
                            if ($v === '' || $v === null) {
                                continue;
                            }
                            $raw_tokens[] = (string) $v;
                        }
                    }
                } elseif (isset($reward['pokemon_id']) && $reward['pokemon_id'] !== '' && $reward['pokemon_id'] !== null) {
                    $raw_tokens = [(string) $reward['pokemon_id']];
                }
                $selected_pokemon_ids = [];
                $selected_pokemon_genders = $gender_seed;
                if ($raw_tokens !== [] && function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')) {
                    $parsed_sr = pokehub_parse_post_pokemon_multiselect_tokens_with_genders($raw_tokens, $gender_seed);
                    $selected_pokemon_ids = $parsed_sr['pokemon_ids'];
                    $selected_pokemon_genders = $parsed_sr['pokemon_genders'];
                } elseif ($raw_tokens !== []) {
                    foreach ($raw_tokens as $t) {
                        if (preg_match('/^(\d+)\|(male|female)$/i', (string) $t, $m)) {
                            $id = (int) $m[1];
                            if ($id > 0) {
                                $selected_pokemon_ids[] = $id;
                                $selected_pokemon_genders[(string) $id] = strtolower((string) $m[2]);
                            }
                        } elseif (is_numeric($t)) {
                            $id = (int) $t;
                            if ($id > 0) {
                                $selected_pokemon_ids[] = $id;
                            }
                        }
                    }
                    $selected_pokemon_ids = array_values(array_unique($selected_pokemon_ids));
                }
                // Fallback : si la BDD brute contient des IDs pour ce reward mais que la normalisation n'a pas rempli (objet/tableau perdu)
                $fallback_map = isset($GLOBALS['pokehub_sr_debug_db_rewards_pokemon']) ? $GLOBALS['pokehub_sr_debug_db_rewards_pokemon'] : [];
                if (empty($selected_pokemon_ids) && !empty($fallback_map)) {
                    $keys_to_try = [];
                    if (strpos($step_context, 'path_') === 0) {
                        $path_idx = (int) str_replace('path_', '', $step_context);
                        $keys_to_try[] = $research_index . '_p' . $path_idx . '_' . $step_index . '_' . $quest_index . '_' . $reward_index;
                    } elseif ($step_context === 'common_final') {
                        $keys_to_try[] = $research_index . '_f_' . $step_index . '_' . $quest_index . '_' . $reward_index;
                    } else {
                        $keys_to_try[] = $research_index . '_' . $step_index . '_' . $quest_index . '_' . $reward_index;
                        $keys_to_try[] = (int) $research_index . '_' . (int) $step_index . '_' . (int) $quest_index . '_' . (int) $reward_index;
                    }
                    foreach ($keys_to_try as $key) {
                        if (isset($fallback_map[$key])) {
                            $fallback = trim((string) $fallback_map[$key]);
                            if ($fallback !== '') {
                                $selected_pokemon_ids = array_values(array_filter(array_map('intval', explode(',', $fallback)), function ($id) {
                                    return $id > 0;
                                }));
                                break;
                            }
                        }
                    }
                }
                ?>
                <div class="pokehub-gender-field-group">
                <label>
                    <?php _e('Pokémon', 'poke-hub'); ?>:
                    <select
                        name="<?php echo esc_attr($name_base); ?>[pokemon_ids][]"
                        class="pokehub-select-pokemon pokehub-sr-reward-pokemon pokehub-gender-driven-select"
                        multiple
                        style="width: 100%; min-width: 250px;"
                        data-selected-ids="<?php echo esc_attr(implode(',', $selected_pokemon_ids)); ?>"
                        data-gender-name-template="<?php echo esc_attr($name_base); ?>[pokemon_genders][__POKEMON_ID__]"
                        data-gender-scope="available"
                        data-existing-genders="<?php echo esc_attr(wp_json_encode($selected_pokemon_genders)); ?>"
                    >
                        <?php
                        // Options des Pokémon déjà enregistrés (libellés). La liste complète est en JS (pokehubQuestsData) pour Select2.
                        $sr_pokemon_list = isset($GLOBALS['pokehub_sr_pokemon_list']) ? $GLOBALS['pokehub_sr_pokemon_list'] : [];
                        if (empty($sr_pokemon_list) && function_exists('pokehub_get_pokemon_for_select')) {
                            $sr_pokemon_list = pokehub_get_pokemon_for_select();
                        }
                        $by_id = [];
                        foreach ($sr_pokemon_list as $p) {
                            $by_id[(int) $p['id']] = $p['text'];
                        }
                        foreach ($selected_pokemon_ids as $id) {
                            $label = isset($by_id[$id]) ? $by_id[$id] : '#' . $id;
                            echo '<option value="' . esc_attr($id) . '" selected="selected">' . esc_html($label) . '</option>';
                        }
                        ?>
                    </select>
                </label>
                <div class="pokehub-pokemon-gender-options" style="display:none;margin-top:8px;"></div>
                </div>
                <?php
            } elseif ($reward_type === 'stardust' || $reward_type === 'xp') {
                ?>
                <label>
                    <?php _e('Quantity', 'poke-hub'); ?>: 
                    <input 
                        type="number" 
                        name="<?php echo esc_attr($name_base); ?>[quantity]" 
                        value="<?php echo esc_attr($reward['quantity'] ?? 1); ?>" 
                        min="1" 
                    />
                </label>
                <?php
            } elseif ($reward_type === 'item') {
                $selected_item_id = isset($reward['item_id']) ? (int) $reward['item_id'] : 0;
                ?>
                <label>
                    <?php _e('Item', 'poke-hub'); ?>: 
                    <select 
                        name="<?php echo esc_attr($name_base); ?>[item_id]" 
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
                <input type="hidden" name="<?php echo esc_attr($name_base); ?>[item_name]" class="pokehub-item-name-field" value="<?php echo esc_attr($reward['item_name'] ?? ''); ?>" />
                <label>
                    <?php _e('Quantity', 'poke-hub'); ?>: 
                    <input 
                        type="number" 
                        name="<?php echo esc_attr($name_base); ?>[quantity]" 
                        value="<?php echo esc_attr($reward['quantity'] ?? 1); ?>" 
                        min="1" 
                    />
                </label>
                <?php
            }
            ?>
        </div>
        
        <button type="button" class="button-link pokehub-remove-reward"><?php _e('Remove', 'poke-hub'); ?></button>
    </div>
    <?php
}

/**
 * Sauvegarde des études spéciales
 */
function pokehub_save_special_research_metabox($post_id) {
    // Vérifications de sécurité
    if (!isset($_POST['pokehub_special_research_nonce']) || 
        !wp_verify_nonce($_POST['pokehub_special_research_nonce'], 'pokehub_save_special_research')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $research_type = isset($_POST['pokehub_special_research_type']) ? sanitize_text_field($_POST['pokehub_special_research_type']) : 'special';

    if (isset($_POST['pokehub_special_research']) && is_array($_POST['pokehub_special_research'])) {
        pokehub_save_special_research($post_id, $_POST['pokehub_special_research'], $research_type);
    } elseif (function_exists('pokehub_content_save_special_research')) {
        pokehub_content_save_special_research('post', $post_id, ['research_type' => $research_type, 'steps' => []]);
    }
}
add_action('save_post', 'pokehub_save_special_research_metabox');

