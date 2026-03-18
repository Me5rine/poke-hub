<?php
// modules/events/admin/events-new-pokemon-metabox.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajoute la metabox pour les nouveaux Pokémon
 */
if (!function_exists('pokehub_add_new_pokemon_metabox')) {
function pokehub_add_new_pokemon_metabox() {
    $screens = apply_filters('pokehub_new_pokemon_post_types', [
        'post',
        'pokehub_event',
    ]);

    foreach ($screens as $screen) {
        add_meta_box(
            'pokehub_new_pokemon',
            __('New Pokémon', 'poke-hub'),
            'pokehub_render_new_pokemon_metabox',
            $screen,
            'normal',
            'default'
        );
    }
}
}
add_action('add_meta_boxes', 'pokehub_add_new_pokemon_metabox');

/**
 * Enqueue scripts et styles pour la metabox des nouveaux Pokémon
 */
if (!function_exists('pokehub_new_pokemon_metabox_assets')) {
function pokehub_new_pokemon_metabox_assets($hook) {
    global $post_type;
    
    $allowed_types = apply_filters('pokehub_new_pokemon_post_types', ['post', 'pokehub_event']);
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
        $saved_genders = get_post_meta($post->ID, '_pokehub_new_pokemon_genders', true);
        if (!is_array($saved_genders)) {
            $saved_genders = [];
        }
    }
    
    wp_localize_script('pokehub-admin-select2', 'pokehubQuestsData', [
        'pokemon' => $pokemon_list,
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pokehub_new_pokemon_ajax'),
        'rest_nonce' => wp_create_nonce('wp_rest'),
        'rest_pokemon_url' => rest_url('poke-hub/v1/pokemon-for-select'),
    ]);
    
    // Localiser les données pour la gestion des genres
    wp_localize_script('pokehub-admin-select2', 'pokehubNewPokemonGender', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pokehub_new_pokemon_ajax'),
        'saved_genders' => $saved_genders,
    ]);
}
}
add_action('admin_enqueue_scripts', 'pokehub_new_pokemon_metabox_assets');

/**
 * Récupère les nouveaux Pokémon d'un post (depuis les tables de contenu).
 */
if (!function_exists('pokehub_get_new_pokemon')) {
function pokehub_get_new_pokemon($post_id) {
    if (function_exists('pokehub_content_get_new_pokemon')) {
        $data = pokehub_content_get_new_pokemon('post', (int) $post_id);
        return isset($data['ids']) ? $data['ids'] : [];
    }
    return [];
}
}

/**
 * Rendu de la metabox des nouveaux Pokémon
 */
if (!function_exists('pokehub_render_new_pokemon_metabox')) {
function pokehub_render_new_pokemon_metabox($post) {
    wp_nonce_field('pokehub_save_new_pokemon', 'pokehub_new_pokemon_nonce');

    $pokemon_ids = [];
    $pokemon_genders = [];
    if (function_exists('pokehub_content_get_new_pokemon')) {
        $data = pokehub_content_get_new_pokemon('post', (int) $post->ID);
        $pokemon_ids = isset($data['ids']) ? array_map('intval', $data['ids']) : [];
        $pokemon_genders = isset($data['genders']) && is_array($data['genders']) ? $data['genders'] : [];
    }
    
    ?>
    <div class="pokehub-new-pokemon-metabox">
        <p class="description">
            <?php _e('Sélectionnez les nouveaux Pokémon qui apparaissent dans cet événement. Ces Pokémon seront affichés avec leur lignée d\'évolution complète dans le bloc "Nouveaux Pokémon - Lignées d\'évolution".', 'poke-hub'); ?>
        </p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="pokehub-new-pokemon-select">
                        <?php _e('Nouveaux Pokémon', 'poke-hub'); ?>
                    </label>
                </th>
                <td>
                    <select 
                        id="pokehub-new-pokemon-select"
                        name="pokehub_new_pokemon_ids[]" 
                        class="pokehub-select-pokemon" 
                        style="width: 100%;"
                        multiple
                    >
                        <?php
                        if (function_exists('pokehub_get_pokemon_for_select')) {
                            $pokemon_list = pokehub_get_pokemon_for_select();
                            foreach ($pokemon_list as $p) {
                                $pokemon_id = (int) $p['id'];
                                $selected = in_array($pokemon_id, $pokemon_ids, true);
                                echo '<option value="' . esc_attr($p['id']) . '" ' . selected($selected, true, false) . '>' . esc_html($p['text']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <p class="description">
                        <?php _e('Sélectionnez un ou plusieurs nouveaux Pokémon. Le bloc affichera automatiquement leur lignée d\'évolution complète.', 'poke-hub'); ?>
                    </p>
                    
                    <div id="pokehub-new-pokemon-genders" style="margin-top: 15px; display: none;">
                        <strong><?php _e('Genders (optional)', 'poke-hub'); ?></strong>
                        <p class="description">
                            <?php _e('For Pokémon with gender dimorphism, you can force a specific gender. By default, the male image will be used.', 'poke-hub'); ?>
                        </p>
                        <div id="pokehub-new-pokemon-genders-list"></div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Initialiser Select2 pour le select
        function initSelect2() {
            if (window.pokehubInitQuestPokemonSelect2) {
                window.pokehubInitQuestPokemonSelect2(document);
            }
        }
        
        // Initialiser au chargement
        initSelect2();
        
        // Fonction pour mettre à jour les champs genre
        function updateGenderFields() {
            var $select = $('#pokehub-new-pokemon-select');
            var $container = $('#pokehub-new-pokemon-genders');
            var $list = $('#pokehub-new-pokemon-genders-list');
            var selectedIds = $select.val() || [];
            
            if (selectedIds.length === 0) {
                $container.hide();
                $list.empty();
                return;
            }
            
            $list.empty();
            var promises = [];
            
            selectedIds.forEach(function(pokemonId) {
                var promise = $.post(pokehubNewPokemonGender.ajax_url, {
                    action: 'pokehub_check_pokemon_gender_dimorphism',
                    nonce: pokehubNewPokemonGender.nonce,
                    pokemon_id: pokemonId
                });
                promises.push(promise);
                
                promise.done(function(resp) {
                    if (resp && resp.success && resp.data && resp.data.has_gender_dimorphism) {
                        var savedGender = pokehubNewPokemonGender.saved_genders && pokehubNewPokemonGender.saved_genders[pokemonId] ? pokehubNewPokemonGender.saved_genders[pokemonId] : '';
                        
                        var $genderRow = $('<div style="margin-bottom: 10px;"></div>');
                        var $label = $('<label style="display: block; margin-bottom: 4px;"></label>');
                        $label.text('Pokémon #' + pokemonId + ':');
                        var $selectGender = $('<select name="pokehub_new_pokemon_genders[' + pokemonId + ']" style="width: 200px; margin-left: 10px;"></select>');
                        $selectGender.append('<option value=""><?php echo esc_js(__('Default (Male)', 'poke-hub')); ?></option>');
                        $selectGender.append('<option value="male"' + (savedGender === 'male' ? ' selected' : '') + '><?php echo esc_js(__('Male', 'poke-hub')); ?></option>');
                        $selectGender.append('<option value="female"' + (savedGender === 'female' ? ' selected' : '') + '><?php echo esc_js(__('Female', 'poke-hub')); ?></option>');
                        
                        $genderRow.append($label);
                        $genderRow.append($selectGender);
                        $list.append($genderRow);
                        $container.show();
                    }
                });
            });
        }
        
        // Écouter les changements sur le select
        $(document).on('change', '#pokehub-new-pokemon-select', function() {
            setTimeout(function() {
                updateGenderFields();
            }, 100);
        });
        
        // Initialiser au chargement
        setTimeout(function() {
            updateGenderFields();
        }, 500);
    });
    </script>
    <?php
}
}

/**
 * Sauvegarde des nouveaux Pokémon
 */
if (!function_exists('pokehub_save_new_pokemon_metabox')) {
function pokehub_save_new_pokemon_metabox($post_id) {
    // Vérifications de sécurité
    if (!isset($_POST['pokehub_new_pokemon_nonce']) || 
        !wp_verify_nonce($_POST['pokehub_new_pokemon_nonce'], 'pokehub_save_new_pokemon')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Sauvegarder les nouveaux Pokémon
    $pokemon_ids = isset($_POST['pokehub_new_pokemon_ids']) && is_array($_POST['pokehub_new_pokemon_ids']) 
        ? array_map('intval', $_POST['pokehub_new_pokemon_ids']) 
        : [];
    
    // Filtrer les valeurs invalides
    $pokemon_ids = array_filter($pokemon_ids, function($id) { return $id > 0; });
    
    // Réindexer le tableau
    $pokemon_ids = array_values($pokemon_ids);
    
    // Sauvegarder les genres
    $pokemon_genders = [];
    if (isset($_POST['pokehub_new_pokemon_genders']) && is_array($_POST['pokehub_new_pokemon_genders'])) {
        foreach ($_POST['pokehub_new_pokemon_genders'] as $pokemon_id => $gender) {
            $pokemon_id = (int) $pokemon_id;
            if ($pokemon_id > 0 && in_array($gender, ['male', 'female'], true)) {
                $pokemon_genders[$pokemon_id] = sanitize_text_field($gender);
            }
        }
    }
    
    if (function_exists('pokehub_content_save_new_pokemon')) {
        pokehub_content_save_new_pokemon('post', $post_id, $pokemon_ids, $pokemon_genders);
    }
}
}
add_action('save_post', 'pokehub_save_new_pokemon_metabox');

