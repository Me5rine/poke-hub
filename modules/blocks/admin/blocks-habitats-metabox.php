<?php
// modules/events/admin/events-habitats-metabox.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajoute la metabox pour les habitats d'événement
 */
if (!function_exists('pokehub_add_event_habitats_metabox')) {
function pokehub_add_event_habitats_metabox() {
    $screens = apply_filters('pokehub_event_habitats_post_types', [
        'post',
        'pokehub_event',
    ]);

    foreach ($screens as $screen) {
        add_meta_box(
            'pokehub_event_habitats',
            __('Event Habitats', 'poke-hub'),
            'pokehub_render_event_habitats_metabox',
            $screen,
            'normal',
            'default'
        );
    }
}
}
add_action('add_meta_boxes', 'pokehub_add_event_habitats_metabox');

/**
 * Enqueue scripts et styles pour la metabox des habitats
 */
if (!function_exists('pokehub_habitats_metabox_assets')) {
function pokehub_habitats_metabox_assets($hook) {
    global $post_type;
    
    $allowed_types = apply_filters('pokehub_event_habitats_post_types', ['post', 'pokehub_event']);
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
    
    // Script global pour Select2
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
    
    // Récupérer les genres sauvegardés
    global $post;
    $saved_genders = [];
    if ($post && $post->ID) {
    $habitats = function_exists('pokehub_content_get_habitats') ? pokehub_content_get_habitats('post', (int) $post->ID) : [];
    if (is_array($habitats)) {
        foreach ($habitats as $habitat_index => $habitat) {
            if (isset($habitat['pokemon_genders']) && is_array($habitat['pokemon_genders'])) {
                $saved_genders[$habitat_index] = $habitat['pokemon_genders'];
            }
        }
    }
    }
    
    wp_localize_script('pokehub-admin-select2', 'pokehubHabitatsData', [
        'pokemon' => $pokemon_list,
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pokehub_habitats_ajax'),
    ]);
    wp_localize_script('pokehub-admin-select2', 'pokehubQuestsData', [
        'pokemon' => $pokemon_list,
        'rest_nonce' => wp_create_nonce('wp_rest'),
        'rest_pokemon_url' => rest_url('poke-hub/v1/pokemon-for-select'),
    ]);

    // Localiser les données pour la gestion des genres
    wp_localize_script('pokehub-admin-select2', 'pokehubHabitatsGender', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pokehub_habitats_ajax'),
        'saved_genders' => $saved_genders,
    ]);
}
}
add_action('admin_enqueue_scripts', 'pokehub_habitats_metabox_assets');

/**
 * Sauvegarde des habitats
 */
if (!function_exists('pokehub_save_event_habitats')) {
function pokehub_save_event_habitats($post_id) {
    // Vérifications de sécurité
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!isset($_POST['pokehub_habitats_nonce']) || !wp_verify_nonce($_POST['pokehub_habitats_nonce'], 'pokehub_save_event_habitats')) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Récupérer les habitats depuis POST
    $habitats = isset($_POST['pokehub_habitats']) && is_array($_POST['pokehub_habitats']) 
        ? $_POST['pokehub_habitats'] 
        : [];
    
    // Nettoyer les données
    $cleaned_habitats = [];
    foreach ($habitats as $habitat) {
        $name = sanitize_text_field($habitat['name'] ?? '');
        $slug = sanitize_title($habitat['slug'] ?? '');
        if (empty($slug) && $name !== '') {
            $slug = sanitize_title($name);
        }
        if (empty($name) || empty($slug)) {
            continue;
        }
        
        // Vérifier si "all_pokemon_available" est présent dans le POST (checkbox cochée = "1", non cochée = absente)
        $all_pokemon_available = isset($habitat['all_pokemon_available']) && $habitat['all_pokemon_available'] === '1';
        
        $cleaned_habitat = [
            'name' => $name,
            'slug' => $slug,
            'pokemon_ids' => [],
            'forced_shiny_ids' => [],
            'schedule' => [],
            'all_pokemon_available' => $all_pokemon_available,
        ];
        
        // Pokémon (+ tokens "id|male" depuis le multiselect)
        if (isset($habitat['pokemon_ids']) && is_array($habitat['pokemon_ids']) && function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')) {
            $parsed = pokehub_parse_post_pokemon_multiselect_tokens_with_genders(
                wp_unslash($habitat['pokemon_ids']),
                isset($habitat['pokemon_genders']) && is_array($habitat['pokemon_genders']) ? wp_unslash($habitat['pokemon_genders']) : null
            );
            $cleaned_habitat['pokemon_ids'] = $parsed['pokemon_ids'];
            $cleaned_habitat['pokemon_genders'] = $parsed['pokemon_genders'];
        } elseif (isset($habitat['pokemon_ids']) && is_array($habitat['pokemon_ids'])) {
            $cleaned_habitat['pokemon_ids'] = array_map('intval', array_filter($habitat['pokemon_ids'], 'is_numeric'));
            $pokemon_genders = [];
            if (isset($habitat['pokemon_genders']) && is_array($habitat['pokemon_genders'])) {
                foreach ($habitat['pokemon_genders'] as $pokemon_id => $gender) {
                    $pokemon_id = (int) $pokemon_id;
                    if ($pokemon_id > 0 && in_array($gender, ['male', 'female'], true)) {
                        $pokemon_genders[$pokemon_id] = sanitize_text_field($gender);
                    }
                }
            }
            $cleaned_habitat['pokemon_genders'] = $pokemon_genders;
        }
        if (!isset($cleaned_habitat['pokemon_genders'])) {
            $cleaned_habitat['pokemon_genders'] = [];
        }
        
        // Shiny forcés
        if (isset($habitat['forced_shiny_ids']) && is_array($habitat['forced_shiny_ids'])) {
            $cleaned_habitat['forced_shiny_ids'] = array_map('intval', array_filter($habitat['forced_shiny_ids'], 'is_numeric'));
        }
        
        // Horaires - Nouvelle structure : date + start_time + end_time par jour
        if (isset($habitat['schedule']) && is_array($habitat['schedule'])) {
            foreach ($habitat['schedule'] as $day) {
                if (empty($day['date'])) {
                    continue;
                }
                
                $cleaned_day = [
                    'date' => sanitize_text_field($day['date']),
                    'start_time' => sanitize_text_field($day['start_time'] ?? ''),
                    'end_time' => sanitize_text_field($day['end_time'] ?? ''),
                    'all_habitats' => !empty($day['all_habitats']),
                ];
                
                // Compatibilité avec l'ancienne structure (time_slots)
                // Si start_time/end_time sont vides mais qu'on a des time_slots, on prend le premier
                if (empty($cleaned_day['start_time']) && empty($cleaned_day['end_time']) && isset($day['time_slots']) && is_array($day['time_slots']) && !empty($day['time_slots'])) {
                    $first_slot = reset($day['time_slots']);
                    if (!empty($first_slot['start']) && !empty($first_slot['end'])) {
                        $cleaned_day['start_time'] = sanitize_text_field($first_slot['start']);
                        $cleaned_day['end_time'] = sanitize_text_field($first_slot['end']);
                    }
                }
                
                // Ne garder que les jours avec au moins une date
                if (!empty($cleaned_day['date'])) {
                    $cleaned_habitat['schedule'][] = $cleaned_day;
                }
            }
        }
        
        $cleaned_habitats[] = $cleaned_habitat;
    }
    
    if (function_exists('pokehub_content_save_habitats')) {
        pokehub_content_save_habitats('post', $post_id, $cleaned_habitats);
    }
}
}
add_action('save_post', 'pokehub_save_event_habitats');

/**
 * Rendu de la metabox des habitats
 */
if (!function_exists('pokehub_render_event_habitats_metabox')) {
function pokehub_render_event_habitats_metabox($post) {
    wp_nonce_field('pokehub_save_event_habitats', 'pokehub_habitats_nonce');

    $habitats = function_exists('pokehub_content_get_habitats') ? pokehub_content_get_habitats('post', (int) $post->ID) : [];
    if (!is_array($habitats)) {
        $habitats = [];
    }
    
    ?>
    <div class="pokehub-habitats-metabox">
        <p class="description">
            <?php _e('Add habitats for this event. Each habitat can have multiple Pokémon and time schedules.', 'poke-hub'); ?>
        </p>
        
        <div id="pokehub-habitats-list">
            <?php if (!empty($habitats)) : ?>
                <?php foreach ($habitats as $index => $habitat) : ?>
                    <?php pokehub_render_habitat_editor_item($index, $habitat); ?>
                <?php endforeach; ?>
            <?php else : ?>
                <?php pokehub_render_habitat_editor_item(0, ['name' => '', 'slug' => '', 'pokemon_ids' => [], 'forced_shiny_ids' => [], 'schedule' => [], 'all_pokemon_available' => false]); ?>
            <?php endif; ?>
        </div>
        
        <button type="button" class="button button-secondary" id="pokehub-add-habitat">
            <?php _e('Add Habitat', 'poke-hub'); ?>
        </button>
    </div>
    
    <script type="text/template" id="pokehub-habitat-template">
        <?php pokehub_render_habitat_editor_item('{{INDEX}}', ['name' => '', 'slug' => '', 'pokemon_ids' => [], 'forced_shiny_ids' => [], 'schedule' => [], 'all_pokemon_available' => false]); ?>
    </script>
    
    <script>
    jQuery(document).ready(function($) {
        var habitatIndex = <?php echo count($habitats); ?>;
        
        // Initialiser Select2
        function initSelect2() {
            if (window.pokehubInitQuestPokemonSelect2) {
                window.pokehubInitQuestPokemonSelect2(document);
            }
        }
        
        initSelect2();
        
        // Ajouter un habitat
        $('#pokehub-add-habitat').on('click', function() {
            var template = $('#pokehub-habitat-template').html();
            var numericIndex = typeof habitatIndex === 'number' ? habitatIndex : 0;
            template = template.replace(/\{\{INDEX\}\}/g, numericIndex);
            var $newHabitat = $(template);
            $('#pokehub-habitats-list').append($newHabitat);
            habitatIndex = (typeof habitatIndex === 'number' ? habitatIndex : 0) + 1;
            setTimeout(function() {
                initSelect2();
                // Initialiser l'état de la checkbox pour le nouvel habitat
                var $newHabitat = $('#pokehub-habitats-list .pokehub-habitat-item-editor').last();
                var $checkbox = $newHabitat.find('.pokehub-habitat-all-pokemon-checkbox');
                if ($checkbox.is(':checked')) {
                    $newHabitat.find('.pokehub-habitats-pokemon-selects').addClass('pokehub-habitats-hidden');
                }
            }, 100);
        });
        
        // Supprimer un habitat
        $(document).on('click', '.pokehub-remove-habitat', function() {
            if (confirm('<?php echo esc_js(__('Delete this habitat?', 'poke-hub')); ?>')) {
                $(this).closest('.pokehub-habitat-item-editor').remove();
            }
        });
        
        // Ajouter un jour
        $(document).on('click', '.pokehub-add-day', function() {
            var habitatItem = $(this).closest('.pokehub-habitat-item-editor');
            var dayIndex = habitatItem.find('.pokehub-habitat-day-editor').length;
            var habitatIndex = habitatItem.data('habitat-index');
            
            var dayHtml = '<div class="pokehub-habitat-day-editor">' +
                '<label><?php echo esc_js(__('Date', 'poke-hub')); ?>: <input type="date" name="pokehub_habitats[' + habitatIndex + '][schedule][' + dayIndex + '][date]" /></label> ' +
                '<label style="margin-left: 10px;"><?php echo esc_js(__('Start time', 'poke-hub')); ?>: <input type="time" name="pokehub_habitats[' + habitatIndex + '][schedule][' + dayIndex + '][start_time]" /></label> ' +
                '<label style="margin-left: 10px;"><?php echo esc_js(__('End time', 'poke-hub')); ?>: <input type="time" name="pokehub_habitats[' + habitatIndex + '][schedule][' + dayIndex + '][end_time]" /></label> ' +
                '<label style="margin-left: 10px;"><input type="checkbox" name="pokehub_habitats[' + habitatIndex + '][schedule][' + dayIndex + '][all_habitats]" /> <?php echo esc_js(__('All habitats available', 'poke-hub')); ?></label> ' +
                '<button type="button" class="button-link pokehub-remove-day" style="margin-left: 10px;"><?php echo esc_js(__('Remove', 'poke-hub')); ?></button>' +
                '</div>';
            
            $(this).closest('.pokehub-habitat-schedule-editor').find('.pokehub-habitat-days').append(dayHtml);
        });
        
        // Supprimer un jour
        $(document).on('click', '.pokehub-remove-day', function() {
            $(this).closest('.pokehub-habitat-day-editor').remove();
        });
        
        // Gérer l'option "tous les habitats" par habitat - masquer uniquement les selects de Pokémon de l'habitat concerné
        $(document).on('change', '.pokehub-habitat-all-pokemon-checkbox', function() {
            var $habitatItem = $(this).closest('.pokehub-habitat-item-editor');
            var isChecked = $(this).is(':checked');
            
            if (isChecked) {
                $habitatItem.find('.pokehub-habitats-pokemon-selects').addClass('pokehub-habitats-hidden');
            } else {
                $habitatItem.find('.pokehub-habitats-pokemon-selects').removeClass('pokehub-habitats-hidden');
            }
        });
        
        // Initialiser l'état au chargement pour chaque habitat
        $('.pokehub-habitat-all-pokemon-checkbox').each(function() {
            var $habitatItem = $(this).closest('.pokehub-habitat-item-editor');
            var isChecked = $(this).is(':checked');
            
            if (isChecked) {
                $habitatItem.find('.pokehub-habitats-pokemon-selects').addClass('pokehub-habitats-hidden');
            }
        });
        
        // Fonction pour mettre à jour les champs genre pour un select de pokémon dans un habitat
        function updateHabitatsGenderFields($select, genderContainerSelector, genderListSelector) {
            var $container = $select.closest('.pokehub-habitats-pokemon-selects').find(genderContainerSelector);
            var $list = $container.find(genderListSelector);
            var selectedIds = $select.val() || [];
            var habitatIndex = $select.data('habitat-index');
            
            if (selectedIds.length === 0) {
                $container.hide();
                $list.empty();
                return;
            }
            
            $list.empty();
            var promises = [];
            var hasAnyDimorphic = false;
            
            selectedIds.forEach(function(pokemonId) {
                var promise = $.post(pokehubHabitatsGender.ajax_url, {
                    action: 'pokehub_check_pokemon_gender_dimorphism',
                    nonce: pokehubHabitatsGender.nonce,
                    pokemon_id: pokemonId
                });
                
                promise.done(function(resp) {
                    if (!(resp && resp.success && resp.data)) {
                        return;
                    }
                    var data = resp.data;
                    var availableGenders = Array.isArray(data.spawn_available_genders) && data.spawn_available_genders.length
                        ? data.spawn_available_genders
                        : (Array.isArray(data.available_genders) ? data.available_genders : []);
                    if (!(data.has_gender_dimorphism && availableGenders.length > 1)) {
                        return;
                    }

                    hasAnyDimorphic = true;
                    var savedGender = '';
                    if (pokehubHabitatsGender.saved_genders && pokehubHabitatsGender.saved_genders[habitatIndex] && pokehubHabitatsGender.saved_genders[habitatIndex][pokemonId]) {
                        savedGender = pokehubHabitatsGender.saved_genders[habitatIndex][pokemonId];
                    }
                    var defaultGender = (data.default_gender && availableGenders.indexOf(data.default_gender) !== -1)
                        ? data.default_gender
                        : availableGenders[0];
                    var genderLabels = {
                        male: '<?php echo esc_js(__('Male', 'poke-hub')); ?>',
                        female: '<?php echo esc_js(__('Female', 'poke-hub')); ?>'
                    };
                    
                    var $genderRow = $('<div style="margin-bottom: 10px;"></div>');
                    var $label = $('<label style="display: block; margin-bottom: 4px;"></label>');
                    $label.text('Pokémon #' + pokemonId + ':');
                    var $selectGender = $('<select name="pokehub_habitats[' + habitatIndex + '][pokemon_genders][' + pokemonId + ']" style="width: 200px; margin-left: 10px;"></select>');
                    $selectGender.append('<option value=""><?php echo esc_js(__('Default', 'poke-hub')); ?> (' + (genderLabels[defaultGender] || defaultGender) + ')</option>');
                    availableGenders.forEach(function(gender) {
                        if (gender !== 'male' && gender !== 'female') {
                            return;
                        }
                        var selectedAttr = savedGender === gender ? ' selected' : '';
                        $selectGender.append('<option value="' + gender + '"' + selectedAttr + '>' + genderLabels[gender] + '</option>');
                    });
                    
                    $genderRow.append($label);
                    $genderRow.append($selectGender);
                    $list.append($genderRow);
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
        
        // Écouter les changements sur les selects de pokémon dans les habitats
        $(document).on('change', '.pokehub-habitats-pokemon-select', function() {
            var $select = $(this);
            var $habitatItem = $select.closest('.pokehub-habitat-item-editor');
            setTimeout(function() {
                updateHabitatsGenderFields($select, '.pokehub-habitats-pokemon-genders', '.pokehub-habitats-pokemon-genders-list');
            }, 100);
        });
        
        $(document).on('change', '.pokehub-habitats-shiny-select', function() {
            var $select = $(this);
            var $habitatItem = $select.closest('.pokehub-habitat-item-editor');
            setTimeout(function() {
                updateHabitatsGenderFields($select, '.pokehub-habitats-shiny-genders', '.pokehub-habitats-shiny-genders-list');
            }, 100);
        });
        
        // Initialiser les champs genre au chargement
        setTimeout(function() {
            $('.pokehub-habitats-pokemon-select').each(function() {
                updateHabitatsGenderFields($(this), '.pokehub-habitats-pokemon-genders', '.pokehub-habitats-pokemon-genders-list');
            });
            $('.pokehub-habitats-shiny-select').each(function() {
                updateHabitatsGenderFields($(this), '.pokehub-habitats-shiny-genders', '.pokehub-habitats-shiny-genders-list');
            });
        }, 500);
    });
    </script>
    <?php
}
}

/**
 * Rendu d'un item d'habitat dans l'éditeur
 */
if (!function_exists('pokehub_render_habitat_editor_item')) {
function pokehub_render_habitat_editor_item($index, $habitat) {
    $is_numeric = is_numeric($index);
    $display_index = $is_numeric ? ($index + 1) : $index;
    ?>
    <div class="pokehub-habitat-item-editor" data-habitat-index="<?php echo esc_attr($index); ?>">
        <h4>
            <?php _e('Habitat', 'poke-hub'); ?> #<?php echo esc_html($display_index); ?>
            <button type="button" class="button-link pokehub-remove-habitat" style="float:right;">
                <?php _e('Remove', 'poke-hub'); ?>
            </button>
        </h4>
        
        <label>
            <strong><?php _e('Name', 'poke-hub'); ?>:</strong><br>
            <input 
                type="text" 
                name="pokehub_habitats[<?php echo esc_attr($index); ?>][name]" 
                value="<?php echo esc_attr($habitat['name'] ?? ''); ?>" 
                class="widefat"
                placeholder="<?php esc_attr_e('e.g., Ocean Beach', 'poke-hub'); ?>"
            />
        </label>
        
        <label style="margin-top: 10px; display: block;">
            <strong><?php _e('Slug', 'poke-hub'); ?>:</strong><br>
            <input 
                type="text" 
                name="pokehub_habitats[<?php echo esc_attr($index); ?>][slug]" 
                value="<?php echo esc_attr($habitat['slug'] ?? ''); ?>" 
                class="widefat"
                placeholder="<?php esc_attr_e('e.g., ocean-beach', 'poke-hub'); ?>"
            />
            <small><?php _e('Used for the icon filename (e.g., ocean-beach.png)', 'poke-hub'); ?></small>
        </label>
        
        <div style="margin-top: 15px; padding: 10px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 4px;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                <input 
                    type="checkbox" 
                    class="pokehub-habitat-all-pokemon-checkbox" 
                    name="pokehub_habitats[<?php echo esc_attr($index); ?>][all_pokemon_available]" 
                    value="1"
                    <?php 
                    // Vérifier si all_pokemon_available est activé (gère bool, string "1", int 1, etc.)
                    $is_checked = !empty($habitat['all_pokemon_available']);
                    checked($is_checked, true); 
                    ?>
                />
                <strong><?php _e('All Pokémon from all habitats are available for this habitat', 'poke-hub'); ?></strong>
            </label>
            <p class="description" style="margin: 5px 0 0 0; font-size: 12px;">
                <?php _e('If checked, this will hide the Pokémon selectors below for this habitat.', 'poke-hub'); ?>
            </p>
        </div>
        
        <div class="pokehub-habitats-pokemon-selects" style="margin-top: 15px;">
            <strong><?php _e('Pokémon', 'poke-hub'); ?>:</strong>
            <select 
                name="pokehub_habitats[<?php echo esc_attr($index); ?>][pokemon_ids][]" 
                class="pokehub-select-pokemon pokehub-habitats-pokemon-select" 
                data-habitat-index="<?php echo esc_attr($index); ?>"
                style="width: 100%; min-width: 250px;"
                multiple
            >
                <?php
                $selected_pokemon_ids = isset($habitat['pokemon_ids']) && is_array($habitat['pokemon_ids']) 
                    ? array_map('intval', $habitat['pokemon_ids']) 
                    : [];
                
                if (!empty($selected_pokemon_ids) && function_exists('pokehub_get_pokemon_for_select')) {
                    $pokemon_list = pokehub_get_pokemon_for_select();
                    foreach ($pokemon_list as $pokemon_option) {
                        $is_selected = in_array((int) $pokemon_option['id'], $selected_pokemon_ids, true);
                        echo '<option value="' . esc_attr($pokemon_option['id']) . '" ' . selected($is_selected, true, false) . '>' . esc_html($pokemon_option['text']) . '</option>';
                    }
                }
                ?>
            </select>
            
            <div class="pokehub-habitats-pokemon-genders" data-habitat-index="<?php echo esc_attr($index); ?>" style="margin-top: 10px; display: none;">
                <strong><?php _e('Genders (optional)', 'poke-hub'); ?></strong>
                <p class="description" style="margin: 5px 0; font-size: 12px;">
                    <?php _e('For Pokémon with gender dimorphism, you can force a specific gender. By default, the male image will be used.', 'poke-hub'); ?>
                </p>
                <div class="pokehub-habitats-pokemon-genders-list" data-habitat-index="<?php echo esc_attr($index); ?>"></div>
            </div>
        </div>
        
        <div class="pokehub-habitats-pokemon-selects" style="margin-top: 15px;">
            <strong><?php _e('Forced Shiny Pokémon', 'poke-hub'); ?>:</strong>
            <select 
                name="pokehub_habitats[<?php echo esc_attr($index); ?>][forced_shiny_ids][]" 
                class="pokehub-select-pokemon pokehub-habitats-shiny-select" 
                data-habitat-index="<?php echo esc_attr($index); ?>"
                style="width: 100%; min-width: 250px;"
                multiple
            >
                <?php
                $selected_shiny_ids = isset($habitat['forced_shiny_ids']) && is_array($habitat['forced_shiny_ids']) 
                    ? array_map('intval', $habitat['forced_shiny_ids']) 
                    : [];
                
                if (!empty($selected_shiny_ids) && function_exists('pokehub_get_pokemon_for_select')) {
                    $pokemon_list = pokehub_get_pokemon_for_select();
                    foreach ($pokemon_list as $pokemon_option) {
                        $is_selected = in_array((int) $pokemon_option['id'], $selected_shiny_ids, true);
                        echo '<option value="' . esc_attr($pokemon_option['id']) . '" ' . selected($is_selected, true, false) . '>' . esc_html($pokemon_option['text']) . '</option>';
                    }
                }
                ?>
            </select>
            
            <div class="pokehub-habitats-shiny-genders" data-habitat-index="<?php echo esc_attr($index); ?>" style="margin-top: 10px; display: none;">
                <strong><?php _e('Genders (optional)', 'poke-hub'); ?></strong>
                <p class="description" style="margin: 5px 0; font-size: 12px;">
                    <?php _e('For Pokémon with gender dimorphism, you can force a specific gender. By default, the male image will be used.', 'poke-hub'); ?>
                </p>
                <div class="pokehub-habitats-shiny-genders-list" data-habitat-index="<?php echo esc_attr($index); ?>"></div>
            </div>
        </div>
        
        <div class="pokehub-habitat-schedule-editor" style="margin-top: 15px;">
            <strong><?php _e('Schedule', 'poke-hub'); ?>:</strong>
            <p class="description" style="margin: 5px 0;">
                <?php _e('Add one or more days with start and end times for this habitat.', 'poke-hub'); ?>
            </p>
            <div class="pokehub-habitat-days">
                <?php if (!empty($habitat['schedule']) && is_array($habitat['schedule'])) : ?>
                    <?php foreach ($habitat['schedule'] as $day_index => $day) : 
                        // Compatibilité avec l'ancienne structure (time_slots)
                        $start_time = $day['start_time'] ?? '';
                        $end_time = $day['end_time'] ?? '';
                        
                        // Si les nouvelles valeurs sont vides, essayer de récupérer depuis time_slots
                        if (empty($start_time) && empty($end_time) && !empty($day['time_slots']) && is_array($day['time_slots']) && !empty($day['time_slots'])) {
                            $first_slot = reset($day['time_slots']);
                            $start_time = $first_slot['start'] ?? '';
                            $end_time = $first_slot['end'] ?? '';
                        }
                    ?>
                        <div class="pokehub-habitat-day-editor">
                            <label>
                                <?php _e('Date', 'poke-hub'); ?>: 
                                <input 
                                    type="date" 
                                    name="pokehub_habitats[<?php echo esc_attr($index); ?>][schedule][<?php echo esc_attr($day_index); ?>][date]" 
                                    value="<?php echo esc_attr($day['date'] ?? ''); ?>"
                                />
                            </label>
                            <label style="margin-left: 10px;">
                                <?php _e('Start time', 'poke-hub'); ?>: 
                                <input 
                                    type="time" 
                                    name="pokehub_habitats[<?php echo esc_attr($index); ?>][schedule][<?php echo esc_attr($day_index); ?>][start_time]" 
                                    value="<?php echo esc_attr($start_time); ?>"
                                />
                            </label>
                            <label style="margin-left: 10px;">
                                <?php _e('End time', 'poke-hub'); ?>: 
                                <input 
                                    type="time" 
                                    name="pokehub_habitats[<?php echo esc_attr($index); ?>][schedule][<?php echo esc_attr($day_index); ?>][end_time]" 
                                    value="<?php echo esc_attr($end_time); ?>"
                                />
                            </label>
                            <label style="margin-left: 10px;">
                                <input 
                                    type="checkbox" 
                                    name="pokehub_habitats[<?php echo esc_attr($index); ?>][schedule][<?php echo esc_attr($day_index); ?>][all_habitats]" 
                                    <?php checked(!empty($day['all_habitats'])); ?>
                                /> 
                                <?php _e('All habitats available', 'poke-hub'); ?>
                            </label>
                            <button type="button" class="button-link pokehub-remove-day" style="margin-left: 10px;">
                                <?php _e('Remove', 'poke-hub'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="button-link pokehub-add-day" style="margin-top: 10px;">
                <?php _e('Add Day', 'poke-hub'); ?>
            </button>
        </div>
    </div>
    <?php
}
}

