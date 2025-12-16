<?php
// modules/pokemon/admin/forms/background-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit Background
 *
 * @param object|null $edit_row
 */
function poke_hub_pokemon_backgrounds_edit_form($edit_row = null) {
    if (!function_exists('pokehub_get_table')) {
        echo '<div class="wrap"><h1>' . esc_html__('Missing helper pokehub_get_table()', 'poke-hub') . '</h1></div>';
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to edit backgrounds.', 'poke-hub'));
    }

    global $wpdb;

    $is_edit = ($edit_row && isset($edit_row->id));

    // Valeurs par défaut / édition
    $title = '';
    $slug = '';
    $image_url = '';
    $event_id = 0;
    $event_type = '';
    $current_pokemon_ids = [];

    if ($is_edit) {
        $title = isset($edit_row->title) ? (string) $edit_row->title : '';
        $slug = isset($edit_row->slug) ? (string) $edit_row->slug : '';
        $image_url = isset($edit_row->image_url) ? (string) $edit_row->image_url : '';
        $event_id = isset($edit_row->event_id) ? (int) $edit_row->event_id : 0;
        $event_type = isset($edit_row->event_type) ? (string) $edit_row->event_type : '';

        // Récupérer les Pokémon liés
        $links_table = pokehub_get_table('pokemon_background_pokemon_links');
        if ($links_table) {
            $pokemon_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT pokemon_id FROM {$links_table} WHERE background_id = %d",
                    (int) $edit_row->id
                )
            );
            $current_pokemon_ids = array_map(function($row) {
                return (int) $row->pokemon_id;
            }, $pokemon_rows);
        }
    }

    // Récupérer tous les événements
    $all_events = [];
    if (function_exists('poke_hub_events_get_all_sources_by_status')) {
        $all_events = poke_hub_events_get_all_sources_by_status('all', []);
    }

    // Récupérer tous les Pokémon
    $pokemon_table = pokehub_get_table('pokemon');
    $all_pokemon = [];
    if ($pokemon_table) {
        $all_pokemon = $wpdb->get_results(
            "SELECT id, dex_number, name_fr, name_en
             FROM {$pokemon_table}
             ORDER BY dex_number ASC, name_fr ASC, name_en ASC"
        );
    }

    // Enqueue le script pour la médiathèque
    wp_enqueue_media();
    wp_enqueue_script(
        'pokehub-media-url',
        POKE_HUB_URL . 'assets/js/pokehub-media-url.js',
        ['jquery'],
        POKE_HUB_VERSION,
        true
    );

    wp_localize_script('pokehub-media-url', 'pokemonBackgroundsMedia', [
        'selectTitle' => __('Select or Upload Background Image', 'poke-hub'),
        'buttonText'  => __('Use this image', 'poke-hub'),
        'tabUrl'      => __('Insert from URL', 'poke-hub'),
        'inputLabel'  => __('Image URL:', 'poke-hub'),
        'inputDesc'   => __('Enter a direct image URL.', 'poke-hub'),
        'noImage'     => __('No image selected yet.', 'poke-hub'),
    ]);

    $back_url = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'backgrounds',
        ],
        admin_url('admin.php')
    );
    ?>
    <div class="wrap">
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit background', 'poke-hub')
                : esc_html__('Add background', 'poke-hub');
            ?>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">
                <?php esc_html_e('Back to list', 'poke-hub'); ?>
            </a>
        </h1>

        <form method="post">
            <?php wp_nonce_field('poke_hub_pokemon_edit_background'); ?>
            <input type="hidden" name="page" value="poke-hub-pokemon" />
            <input type="hidden" name="ph_section" value="backgrounds" />
            <input type="hidden" name="poke_hub_pokemon_action"
                   value="<?php echo $is_edit ? 'update_background' : 'add_background'; ?>" />
            <?php if ($is_edit) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $edit_row->id; ?>" />
            <?php endif; ?>

            <!-- Section: Basic Information -->
            <div class="pokehub-section">
                <h3><?php esc_html_e('Basic Information', 'poke-hub'); ?></h3>
                
                <div class="pokehub-form-row">
                    <div class="pokehub-form-col">
                        <div class="pokehub-form-group">
                            <label for="title"><?php esc_html_e('Title', 'poke-hub'); ?> *</label>
                            <input type="text" id="title" name="title" value="<?php echo esc_attr($title); ?>" required />
                            <p class="description"><?php esc_html_e('Example: "Halloween Background", "Christmas Background"…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="pokehub-form-col">
                        <div class="pokehub-form-group">
                            <label for="slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                            <input type="text" id="slug" name="slug" value="<?php echo esc_attr($slug); ?>" />
                            <p class="description"><?php esc_html_e('Leave empty to auto-generate from title.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Background Image -->
            <div class="pokehub-section">
                <h3><?php esc_html_e('Background Image', 'poke-hub'); ?></h3>
                
                <div id="pokehub-background-image-field">
                    <div class="pokehub-form-group">
                        <label for="image_url"><?php esc_html_e('Image URL', 'poke-hub'); ?></label>
                        <div style="display: flex; gap: 10px;">
                            <input type="url" id="image_url" name="image_url" value="<?php echo esc_attr($image_url); ?>" style="flex: 1;" />
                            <button type="button" class="button pokehub-select-background-image">
                                <?php esc_html_e('Choose from library', 'poke-hub'); ?>
                            </button>
                        </div>
                        <p class="description"><?php esc_html_e('Full URL to the background image.', 'poke-hub'); ?></p>
                        
                        <div class="image-preview" style="margin-top:15px;">
                            <?php if ($image_url) : ?>
                                <img src="<?php echo esc_url($image_url); ?>" 
                                     class="pokehub-background-image-preview" 
                                     style="max-width:300px;height:auto;display:block;border:1px solid #c3c4c7;padding:8px;background:#fff;border-radius:4px;" />
                                <button type="button" class="button pokehub-remove-background-image" style="margin-top:10px;">
                                    <?php esc_html_e('Remove image', 'poke-hub'); ?>
                                </button>
                            <?php else : ?>
                                <p class="description" style="margin:0;color:#999;">
                                    <?php esc_html_e('No image selected yet.', 'poke-hub'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Event Association -->
            <div class="pokehub-section">
                <h3><?php esc_html_e('Event Association', 'poke-hub'); ?></h3>
                
                <div class="pokehub-form-row">
                    <div class="pokehub-form-col-50">
                        <div class="pokehub-form-group">
                            <label for="event_type"><?php esc_html_e('Event Type', 'poke-hub'); ?></label>
                            <select name="event_type" id="event_type">
                                <option value=""><?php esc_html_e('None', 'poke-hub'); ?></option>
                                <option value="local_post" <?php selected($event_type, 'local_post'); ?>><?php esc_html_e('Local post', 'poke-hub'); ?></option>
                                <option value="remote_post" <?php selected($event_type, 'remote_post'); ?>><?php esc_html_e('Remote post', 'poke-hub'); ?></option>
                                <option value="special_local" <?php selected($event_type, 'special_local'); ?>><?php esc_html_e('Special event (local)', 'poke-hub'); ?></option>
                                <option value="special_remote" <?php selected($event_type, 'special_remote'); ?>><?php esc_html_e('Special event (remote)', 'poke-hub'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Type of event associated with this background.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="pokehub-form-col-50">
                        <div class="pokehub-form-group">
                            <label for="event_id"><?php esc_html_e('Event', 'poke-hub'); ?></label>
                            <select name="event_id" id="event_id" style="max-width: 100%;">
                                <option value="0"><?php esc_html_e('None', 'poke-hub'); ?></option>
                                <?php if (!empty($all_events)) : ?>
                                    <?php foreach ($all_events as $event) : ?>
                                        <?php
                                        $ev_id = isset($event->id) ? (int) $event->id : 0;
                                        $ev_source = isset($event->source) ? (string) $event->source : '';
                                        $ev_title = isset($event->title) ? (string) $event->title : '';
                                        $ev_slug = isset($event->slug) ? (string) $event->slug : '';

                                        // NE PAS filtrer côté serveur, laisser le JavaScript gérer
                                        $label = $ev_title ?: $ev_slug;
                                        if ($ev_source) {
                                            $label .= ' (' . esc_html(ucfirst(str_replace('_', ' ', $ev_source))) . ')';
                                        }
                                        ?>
                                        <option value="<?php echo $ev_id; ?>" 
                                                data-source="<?php echo esc_attr($ev_source); ?>"
                                                <?php selected($event_id, $ev_id); ?>
                                                style="display:none;">
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Filtered by event type. Select a type above to see available events.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Linked Pokémon -->
            <div class="pokehub-section">
                <h3><?php esc_html_e('Linked Pokémon', 'poke-hub'); ?></h3>
                
                <div class="pokehub-form-group">
                    <label for="pokemon_ids"><?php esc_html_e('Pokémon', 'poke-hub'); ?></label>
                    <select name="pokemon_ids[]" id="pokemon_ids" class="pokehub-pokemon-select" multiple="multiple" style="width:100%;">
                        <?php if (!empty($all_pokemon)) : ?>
                            <?php foreach ($all_pokemon as $pokemon) : ?>
                                <?php
                                $p_id = (int) $pokemon->id;
                                $p_dex = (int) $pokemon->dex_number;
                                $p_name = !empty($pokemon->name_fr) ? $pokemon->name_fr : $pokemon->name_en;
                                $label = sprintf('#%03d %s', $p_dex, esc_html($p_name));
                                ?>
                                <option value="<?php echo $p_id; ?>" <?php selected(in_array($p_id, $current_pokemon_ids, true)); ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Search and select one or more Pokémon that use this background.', 'poke-hub'); ?></p>
                </div>
            </div>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary"
                       value="<?php echo $is_edit ? esc_attr__('Update', 'poke-hub') : esc_attr__('Add', 'poke-hub'); ?>" />
                <a href="<?php echo esc_url($back_url); ?>" class="button">
                    <?php esc_html_e('Cancel', 'poke-hub'); ?>
                </a>
            </p>
        </form>
    </div>

    <script type="text/javascript">
    jQuery(function($) {
        // Initialiser Select2 sur le champ Pokémon
        if ($.fn.select2) {
            $('#pokemon_ids').select2({
                placeholder: '<?php echo esc_js(__('Search Pokémon...', 'poke-hub')); ?>',
                allowClear: true,
                width: '100%'
            });
        }
        // Gestion de la médiathèque pour l'image
        $(document).on('click', '.pokehub-select-background-image', function(e) {
            e.preventDefault();

            const $field = $('#pokehub-background-image-field');
            const $urlInput = $('#image_url');
            let $preview = $field.find('.pokehub-background-image-preview');
            const $remove = $field.find('.pokehub-remove-background-image');

            const frame = new wp.media.view.MediaFrame.PokeHubTypes({
                title: (window.pokemonBackgroundsMedia && pokemonBackgroundsMedia.selectTitle) || 'Select or Upload Image',
                button: {
                    text: (window.pokemonBackgroundsMedia && pokemonBackgroundsMedia.buttonText) || 'Use this image'
                },
                multiple: false
            });

            frame.on('open', function() {
                const state = frame.state('pokehub-types-url');
                if (state) {
                    state.props.set({
                        url: $urlInput.val() || ''
                    });
                }
            });

            frame.on('select', function() {
                const attachment = frame.state().get('selection').first();
                if (!attachment) return;

                const data = attachment.toJSON();
                if (!data.url) return;

                $urlInput.val(data.url);

                if (!$preview.length) {
                    $field.find('.image-preview').html(
                        '<img src="' + data.url + '" class="pokehub-background-image-preview" style="max-width:300px;height:auto;display:block;border:1px solid #c3c4c7;padding:8px;background:#fff;border-radius:4px;" />' +
                        '<button type="button" class="button pokehub-remove-background-image" style="margin-top:10px;"><?php echo esc_js(__('Remove image', 'poke-hub')); ?></button>'
                    );
                    $preview = $field.find('.pokehub-background-image-preview');
                } else {
                    $preview.attr('src', data.url).show();
                }

                $remove.show();
            });

            frame.on('insert', function(state) {
                if (!state || state.id !== 'pokehub-types-url') return;

                const url = state.props.get('url');
                if (!url) return;

                $urlInput.val(url);

                if (!$preview.length) {
                    $field.find('.image-preview').html(
                        '<img src="' + url + '" class="pokehub-background-image-preview" style="max-width:300px;height:auto;display:block;border:1px solid #c3c4c7;padding:8px;background:#fff;border-radius:4px;" />' +
                        '<button type="button" class="button pokehub-remove-background-image" style="margin-top:10px;"><?php echo esc_js(__('Remove image', 'poke-hub')); ?></button>'
                    );
                    $preview = $field.find('.pokehub-background-image-preview');
                } else {
                    $preview.attr('src', url).show();
                }

                $remove.show();
            });

            frame.open();
        });

        $(document).on('click', '.pokehub-remove-background-image', function(e) {
            e.preventDefault();

            const $field = $('#pokehub-background-image-field');
            const $urlInput = $('#image_url');
            const $preview = $field.find('.pokehub-background-image-preview');

            $urlInput.val('');

            if ($preview.length) {
                $preview.attr('src', '').hide();
            }

            $field.find('.image-preview').html(
                '<p class="description" style="margin:0;color:#999;"><?php echo esc_js(__('No image selected yet.', 'poke-hub')); ?></p>'
            );

            $(this).hide();
        });

        // Filtrer les événements selon le type sélectionné
        $('#event_type').on('change', function() {
            const selectedType = $(this).val();
            const $eventSelect = $('#event_id');
            const currentValue = $eventSelect.val();

            $eventSelect.find('option').each(function() {
                const $option = $(this);
                const optionSource = $option.data('source') || '';

                // Toujours afficher l'option "None"
                if ($option.val() === '0') {
                    $option.show();
                    return;
                }

                // Afficher les options qui correspondent au type sélectionné
                if (!selectedType || optionSource === selectedType) {
                    $option.show();
                } else {
                    $option.hide();
                }
            });

            // Si l'événement actuel ne correspond pas au nouveau type, réinitialiser
            const $selectedOption = $eventSelect.find('option:selected');
            if ($selectedOption.length && $selectedOption.val() !== '0') {
                const optionSource = $selectedOption.data('source') || '';
                if (selectedType && optionSource !== selectedType) {
                    $eventSelect.val('0');
                }
            }
        });

        // Déclencher le filtre au chargement
        $('#event_type').trigger('change');
    });
    </script>
    <?php
}
