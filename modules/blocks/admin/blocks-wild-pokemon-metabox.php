<?php
// modules/events/admin/events-wild-pokemon-metabox.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajoute la metabox pour les Pokémon sauvages
 */
if (!function_exists('pokehub_add_wild_pokemon_metabox')) {
function pokehub_add_wild_pokemon_metabox() {
    $screens = apply_filters('pokehub_wild_pokemon_post_types', [
        'post',
        'pokehub_event',
    ]);

    foreach ($screens as $screen) {
        add_meta_box(
            'pokehub_wild_pokemon',
            __('Wild Encounters', 'poke-hub'),
            'pokehub_render_wild_pokemon_metabox',
            $screen,
            'normal',
            'default'
        );
    }
}
}
add_action('add_meta_boxes', 'pokehub_add_wild_pokemon_metabox');

/**
 * Enqueue scripts et styles pour la metabox des Pokémon sauvages
 */
if (!function_exists('pokehub_wild_pokemon_metabox_assets')) {
function pokehub_wild_pokemon_metabox_assets($hook) {
    global $post_type;
    
    $allowed_types = apply_filters('pokehub_wild_pokemon_post_types', ['post', 'pokehub_event']);
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
    
    // Localiser les données pour Select2 (utiliser la même structure que les quêtes)
    $pokemon_list = function_exists('pokehub_get_pokemon_for_select') 
        ? pokehub_get_pokemon_for_select() 
        : [];
    
    // Récupérer les genres sauvegardés
    global $post;
    $saved_genders = [];
    if ($post && $post->ID) {
        $saved_genders = get_post_meta($post->ID, '_pokehub_wild_pokemon_genders', true);
        if (!is_array($saved_genders)) {
            $saved_genders = [];
        }
    }
    
    wp_localize_script('pokehub-admin-select2', 'pokehubQuestsData', [
        'pokemon' => $pokemon_list,
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pokehub_wild_pokemon_ajax'),
        'rest_nonce' => wp_create_nonce('wp_rest'),
        'rest_pokemon_url' => rest_url('poke-hub/v1/pokemon-for-select'),
    ]);
    
    // Localiser les données pour la gestion des genres
    wp_localize_script('pokehub-admin-select2', 'pokehubWildPokemonGender', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pokehub_wild_pokemon_ajax'),
        'saved_genders' => $saved_genders,
    ]);
}
}
add_action('admin_enqueue_scripts', 'pokehub_wild_pokemon_metabox_assets');

/**
 * Récupère les Pokémon sauvages d'un post (depuis les tables de contenu).
 */
if (!function_exists('pokehub_get_wild_pokemon')) {
    function pokehub_get_wild_pokemon($post_id) {
        if (function_exists('pokehub_content_get_wild_pokemon')) {
            $list = pokehub_content_get_wild_pokemon('post', (int) $post_id);
            $out = [];
            foreach ($list as $w) {
                $out[] = [
                    'pokemon_id'   => (int) $w['pokemon_id'],
                    'is_rare'      => !empty($w['is_rare']),
                    'force_shiny'  => !empty($w['force_shiny']),
                ];
            }
            return $out;
        }
        return [];
    }
}

/**
 * Rendu de la metabox des Pokémon sauvages
 */
if (!function_exists('pokehub_render_wild_pokemon_metabox')) {
function pokehub_render_wild_pokemon_metabox($post) {
    wp_nonce_field('pokehub_save_wild_pokemon', 'pokehub_wild_pokemon_nonce');

    // Lire depuis les tables de contenu
    $pokemon_ids = [];
    $rare_ids = [];
    $forced_shiny_ids = [];
    $pokemon_genders = [];
    if (function_exists('pokehub_content_get_wild_pokemon')) {
        $list = pokehub_content_get_wild_pokemon('post', (int) $post->ID);
        foreach ($list as $w) {
            $pid = (int) $w['pokemon_id'];
            $pokemon_ids[] = $pid;
            if (!empty($w['is_rare'])) {
                $rare_ids[] = $pid;
            }
            if (!empty($w['force_shiny'])) {
                $forced_shiny_ids[] = $pid;
            }
            if (!empty($w['gender'])) {
                $pokemon_genders[$pid] = $w['gender'];
            }
        }
        $pokemon_ids = array_values(array_unique($pokemon_ids));
        $rare_ids = array_values(array_unique($rare_ids));
        $forced_shiny_ids = array_values(array_unique($forced_shiny_ids));
    }
    
    ?>
    <div class="pokehub-wild-pokemon-metabox">
        <p class="description">
            <?php _e('Sélectionnez les Pokémon disponibles dans la nature pour cet événement. Vous pouvez optionnellement marquer certains comme rares et forcer le shiny.', 'poke-hub'); ?>
        </p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="pokehub-wild-pokemon-select">
                        <?php _e('Pokémon Sauvages', 'poke-hub'); ?>
                    </label>
                </th>
                <td>
                    <select 
                        id="pokehub-wild-pokemon-select"
                        name="pokehub_wild_pokemon_ids[]" 
                        class="pokehub-select-pokemon" 
                        style="width: 100%;"
                        multiple
                        data-exclude-selects="pokehub-forced-shiny-select,pokehub-rare-pokemon-select"
                    >
                        <?php
                        if (function_exists('pokehub_get_pokemon_for_select')) {
                            $pokemon_list = pokehub_get_pokemon_for_select();
                            $all_selected_ids = array_merge($rare_ids, $forced_shiny_ids);
                            foreach ($pokemon_list as $p) {
                                $pokemon_id = (int) $p['id'];
                                $selected = in_array($pokemon_id, $pokemon_ids, true);
                                $disabled = in_array($pokemon_id, $all_selected_ids, true);
                                echo '<option value="' . esc_attr($p['id']) . '" ' . selected($selected, true, false) . ' ' . disabled($disabled, true, false) . '>' . esc_html($p['text']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <p class="description">
                        <?php _e('Sélectionnez les Pokémon sauvages classiques disponibles dans la nature.', 'poke-hub'); ?>
                    </p>
                    
                    <div id="pokehub-wild-pokemon-genders" style="margin-top: 15px; display: none;">
                        <strong><?php _e('Genders (optional)', 'poke-hub'); ?></strong>
                        <p class="description">
                            <?php _e('For Pokémon with gender dimorphism, you can force a specific gender. By default, the male image will be used.', 'poke-hub'); ?>
                        </p>
                        <div id="pokehub-wild-pokemon-genders-list"></div>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="pokehub-forced-shiny-select">
                        <?php _e('Shiny Forcés (optionnel)', 'poke-hub'); ?>
                    </label>
                </th>
                <td>
                    <select 
                        id="pokehub-forced-shiny-select"
                        name="pokehub_forced_shiny_ids[]" 
                        class="pokehub-select-pokemon" 
                        style="width: 100%;"
                        multiple
                        data-exclude-selects="pokehub-wild-pokemon-select,pokehub-rare-pokemon-select"
                    >
                        <?php
                        if (function_exists('pokehub_get_pokemon_for_select')) {
                            $pokemon_list = pokehub_get_pokemon_for_select();
                            $all_selected_ids = array_merge($pokemon_ids, $rare_ids);
                            foreach ($pokemon_list as $p) {
                                $pokemon_id = (int) $p['id'];
                                $selected = in_array($pokemon_id, $forced_shiny_ids, true);
                                $disabled = in_array($pokemon_id, $all_selected_ids, true);
                                echo '<option value="' . esc_attr($p['id']) . '" ' . selected($selected, true, false) . ' ' . disabled($disabled, true, false) . '>' . esc_html($p['text']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <p class="description">
                        <?php _e('Sélectionnez les Pokémon pour lesquels vous voulez forcer l\'affichage shiny.', 'poke-hub'); ?>
                    </p>
                    
                    <div id="pokehub-forced-shiny-genders" style="margin-top: 15px; display: none;">
                        <strong><?php _e('Genders (optional)', 'poke-hub'); ?></strong>
                        <p class="description">
                            <?php _e('For Pokémon with gender dimorphism, you can force a specific gender. By default, the male image will be used.', 'poke-hub'); ?>
                        </p>
                        <div id="pokehub-forced-shiny-genders-list"></div>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="pokehub-rare-pokemon-select">
                        <?php _e('Pokémon Rares (optionnel)', 'poke-hub'); ?>
                    </label>
                </th>
                <td>
                    <select 
                        id="pokehub-rare-pokemon-select"
                        name="pokehub_rare_pokemon_ids[]" 
                        class="pokehub-select-pokemon" 
                        style="width: 100%;"
                        multiple
                        data-exclude-selects="pokehub-wild-pokemon-select,pokehub-forced-shiny-select"
                    >
                        <?php
                        if (function_exists('pokehub_get_pokemon_for_select')) {
                            $pokemon_list = pokehub_get_pokemon_for_select();
                            $all_selected_ids = array_merge($pokemon_ids, $forced_shiny_ids);
                            foreach ($pokemon_list as $p) {
                                $pokemon_id = (int) $p['id'];
                                $selected = in_array($pokemon_id, $rare_ids, true);
                                $disabled = in_array($pokemon_id, $all_selected_ids, true);
                                echo '<option value="' . esc_attr($p['id']) . '" ' . selected($selected, true, false) . ' ' . disabled($disabled, true, false) . '>' . esc_html($p['text']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <p class="description">
                        <?php _e('Sélectionnez les Pokémon qui apparaissent plus rarement. Ils seront affichés dans une section séparée.', 'poke-hub'); ?>
                    </p>
                    
                    <div id="pokehub-rare-pokemon-genders" style="margin-top: 15px; display: none;">
                        <strong><?php _e('Genders (optional)', 'poke-hub'); ?></strong>
                        <p class="description">
                            <?php _e('For Pokémon with gender dimorphism, you can force a specific gender. By default, the male image will be used.', 'poke-hub'); ?>
                        </p>
                        <div id="pokehub-rare-pokemon-genders-list"></div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Initialiser Select2 pour tous les selects
        function initSelect2() {
            if (window.pokehubInitQuestPokemonSelect2) {
                window.pokehubInitQuestPokemonSelect2(document);
            }
        }
        
        // Initialiser au chargement
        initSelect2();
        
        // Fonction pour mettre à jour les options exclusives
        function updateExclusiveSelects(changedSelectId) {
            var $changedSelect = $('#' + changedSelectId);
            var selectedIds = $changedSelect.val() || [];
            var excludeSelects = $changedSelect.attr('data-exclude-selects');
            
            if (!excludeSelects) {
                return;
            }
            
            var excludeIds = excludeSelects.split(',');
            
            // Mettre à jour les autres selects pour désactiver les options sélectionnées
            excludeIds.forEach(function(excludeId) {
                var $excludeSelect = $('#' + excludeId.trim());
                if ($excludeSelect.length) {
                    var excludeSelectedIds = $excludeSelect.val() || [];
                    
                    // Retirer les IDs qui viennent d'être sélectionnés dans l'autre select
                    var filteredExcludeIds = excludeSelectedIds.filter(function(id) {
                        return selectedIds.indexOf(id) === -1;
                    });
                    
                    // Mettre à jour les options
                    $excludeSelect.find('option').each(function() {
                        var optionId = $(this).val();
                        if (optionId && selectedIds.indexOf(optionId) !== -1) {
                            $(this).prop('disabled', true);
                        } else {
                            $(this).prop('disabled', false);
                        }
                    });
                    
                    // Si Select2 est initialisé, mettre à jour
                    if ($excludeSelect.data('select2')) {
                        $excludeSelect.trigger('change.select2');
                        
                        // Retirer les valeurs déjà sélectionnées dans l'autre select
                        if (filteredExcludeIds.length !== excludeSelectedIds.length) {
                            $excludeSelect.val(filteredExcludeIds).trigger('change');
                        }
                    }
                }
            });
        }
        
        // Écouter les changements sur tous les selects
        $(document).on('change', '#pokehub-wild-pokemon-select, #pokehub-forced-shiny-select, #pokehub-rare-pokemon-select', function() {
            var selectId = $(this).attr('id');
            updateExclusiveSelects(selectId);
        });
        
        // Initialiser au chargement (après Select2)
        setTimeout(function() {
            updateExclusiveSelects('pokehub-wild-pokemon-select');
            updateExclusiveSelects('pokehub-forced-shiny-select');
            updateExclusiveSelects('pokehub-rare-pokemon-select');
            updateGenderFields();
        }, 500);
        
        // Fonction pour mettre à jour les champs genre
        function updateGenderFields() {
            updateGenderFieldsForSelect('#pokehub-wild-pokemon-select', '#pokehub-wild-pokemon-genders', '#pokehub-wild-pokemon-genders-list', 'pokehub_wild_pokemon_genders');
            updateGenderFieldsForSelect('#pokehub-forced-shiny-select', '#pokehub-forced-shiny-genders', '#pokehub-forced-shiny-genders-list', 'pokehub_wild_pokemon_genders');
            updateGenderFieldsForSelect('#pokehub-rare-pokemon-select', '#pokehub-rare-pokemon-genders', '#pokehub-rare-pokemon-genders-list', 'pokehub_wild_pokemon_genders');
        }
        
        function updateGenderFieldsForSelect(selectSelector, containerSelector, listSelector, genderPrefix) {
            var $select = $(selectSelector);
            var $container = $(containerSelector);
            var $list = $(listSelector);
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
                var promise = $.post(pokehubWildPokemonGender.ajax_url, {
                    action: 'pokehub_check_pokemon_gender_dimorphism',
                    nonce: pokehubWildPokemonGender.nonce,
                    pokemon_id: pokemonId
                });
                
                promise.done(function(resp) {
                    if (resp && resp.success && resp.data && resp.data.has_gender_dimorphism) {
                        hasAnyDimorphic = true;
                        var savedGender = pokehubWildPokemonGender.saved_genders && pokehubWildPokemonGender.saved_genders[pokemonId] ? pokehubWildPokemonGender.saved_genders[pokemonId] : '';
                        
                        var $genderRow = $('<div style="margin-bottom: 10px;"></div>');
                        var $label = $('<label style="display: block; margin-bottom: 4px;"></label>');
                        $label.text('Pokémon #' + pokemonId + ':');
                        var $selectGender = $('<select name="' + genderPrefix + '[' + pokemonId + ']" style="width: 200px; margin-left: 10px;"></select>');
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
            
            // Afficher le conteneur si au moins un pokémon a un dysmorphisme
            $.when.apply($, promises).done(function() {
                if (hasAnyDimorphic) {
                    $container.show();
                } else {
                    $container.hide();
                }
            });
        }
        
        // Écouter les changements sur les selects pour mettre à jour les champs genre
        $(document).on('change', '#pokehub-wild-pokemon-select, #pokehub-forced-shiny-select, #pokehub-rare-pokemon-select', function() {
            var selectId = $(this).attr('id');
            updateExclusiveSelects(selectId);
            
            // Mettre à jour les champs genre après un court délai pour laisser Select2 se mettre à jour
            setTimeout(function() {
                updateGenderFields();
            }, 100);
        });
    });
    </script>
    <?php
}
}


/**
 * Sauvegarde des Pokémon sauvages
 */
if (!function_exists('pokehub_save_wild_pokemon_metabox')) {
function pokehub_save_wild_pokemon_metabox($post_id) {
    // Vérifications de sécurité
    if (!isset($_POST['pokehub_wild_pokemon_nonce']) || 
        !wp_verify_nonce($_POST['pokehub_wild_pokemon_nonce'], 'pokehub_save_wild_pokemon')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Sauvegarder les Pokémon sauvages (mutuellement exclusifs)
    $pokemon_ids = isset($_POST['pokehub_wild_pokemon_ids']) && is_array($_POST['pokehub_wild_pokemon_ids']) 
        ? array_map('intval', $_POST['pokehub_wild_pokemon_ids']) 
        : [];
    $forced_shiny_ids = isset($_POST['pokehub_forced_shiny_ids']) && is_array($_POST['pokehub_forced_shiny_ids']) 
        ? array_map('intval', $_POST['pokehub_forced_shiny_ids']) 
        : [];
    $rare_ids = isset($_POST['pokehub_rare_pokemon_ids']) && is_array($_POST['pokehub_rare_pokemon_ids']) 
        ? array_map('intval', $_POST['pokehub_rare_pokemon_ids']) 
        : [];
    
    // Filtrer les valeurs invalides
    $pokemon_ids = array_filter($pokemon_ids, function($id) { return $id > 0; });
    $forced_shiny_ids = array_filter($forced_shiny_ids, function($id) { return $id > 0; });
    $rare_ids = array_filter($rare_ids, function($id) { return $id > 0; });
    
    // Réindexer les tableaux
    $pokemon_ids = array_values($pokemon_ids);
    $forced_shiny_ids = array_values($forced_shiny_ids);
    $rare_ids = array_values($rare_ids);
    
    // Sauvegarder les genres
    $pokemon_genders = [];
    if (isset($_POST['pokehub_wild_pokemon_genders']) && is_array($_POST['pokehub_wild_pokemon_genders'])) {
        foreach ($_POST['pokehub_wild_pokemon_genders'] as $pokemon_id => $gender) {
            $pokemon_id = (int) $pokemon_id;
            if ($pokemon_id > 0 && in_array($gender, ['male', 'female'], true)) {
                $pokemon_genders[$pokemon_id] = sanitize_text_field($gender);
            }
        }
    }
    
    $all_ids = array_values(array_unique(array_merge($pokemon_ids, $forced_shiny_ids, $rare_ids)));
    $wild_list = [];
    foreach ($all_ids as $pid) {
        $wild_list[] = [
            'pokemon_id'  => $pid,
            'is_rare'     => in_array($pid, $rare_ids, true),
            'force_shiny' => in_array($pid, $forced_shiny_ids, true),
            'gender'      => isset($pokemon_genders[$pid]) ? $pokemon_genders[$pid] : null,
        ];
    }
    if (function_exists('pokehub_content_save_wild_pokemon')) {
        pokehub_content_save_wild_pokemon('post', $post_id, $wild_list);
    }
}
}
add_action('save_post', 'pokehub_save_wild_pokemon_metabox');

