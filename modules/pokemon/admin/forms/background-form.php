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
    $background_type = defined('POKE_HUB_BACKGROUND_TYPE_SPECIAL') ? POKE_HUB_BACKGROUND_TYPE_SPECIAL : 'special';
    $image_url = '';
    $current_events = [];
    $current_pokemon_ids = [];
    $current_shiny_locked_ids = [];
    $current_shiny_active_ids = [];

    if ($is_edit) {
        $title = isset($edit_row->title) ? (string) $edit_row->title : '';
        $slug = isset($edit_row->slug) ? (string) $edit_row->slug : '';
        $image_url = isset($edit_row->image_url) ? (string) $edit_row->image_url : '';
        if (isset($edit_row->background_type) && (string) $edit_row->background_type !== '') {
            $background_type = (string) $edit_row->background_type;
        }

        // Événements associés (plusieurs par fond)
        if (function_exists('poke_hub_get_background_events')) {
            $current_events = poke_hub_get_background_events((int) $edit_row->id);
        }
        if (empty($current_events) && isset($edit_row->event_id) && (int) $edit_row->event_id > 0 && !empty(trim((string) ($edit_row->event_type ?? '')))) {
            $current_events = [['event_type' => (string) $edit_row->event_type, 'event_id' => (int) $edit_row->event_id]];
        }

        // Pokémon liés
        $links_table = pokehub_get_table('pokemon_background_pokemon_links');
        if ($links_table) {
            $pokemon_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT pokemon_id FROM {$links_table} WHERE background_id = %d ORDER BY pokemon_id ASC",
                    (int) $edit_row->id
                )
            );
            $current_pokemon_ids = array_map(function ($row) {
                return (int) $row->pokemon_id;
            }, $pokemon_rows);
        }
        if (function_exists('poke_hub_get_background_shiny_locked_pokemon_ids')) {
            $current_shiny_locked_ids = poke_hub_get_background_shiny_locked_pokemon_ids((int) $edit_row->id);
        }
        // Pokémon shiny actif = liés au fond mais pas shiny lock (shiny disponible)
        $current_shiny_active_ids = array_values(array_diff($current_pokemon_ids, $current_shiny_locked_ids));
    }

    // Tous les événements pour le picker (recherche par nom, tous types)
    $all_events = function_exists('poke_hub_get_events_for_picker') ? poke_hub_get_events_for_picker() : [];

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
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Basic Information', 'poke-hub'); ?></h3>
                <div class="admin-lab-form-row" style="display: flex; gap: 1em;">
                    <div class="admin-lab-form-col" style="flex: 1; min-width: 0;">
                        <div class="admin-lab-form-group">
                            <label for="title"><?php esc_html_e('Title', 'poke-hub'); ?> *</label>
                            <input type="text" id="title" name="title" value="<?php echo esc_attr($title); ?>" required />
                            <p class="description"><?php esc_html_e('Example: "Halloween Background", "Christmas Background"…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col" style="flex: 1; min-width: 0;">
                        <div class="admin-lab-form-group">
                            <label for="slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                            <input type="text" id="slug" name="slug" value="<?php echo esc_attr($slug); ?>" />
                            <p class="description"><?php esc_html_e('Leave empty to auto-generate from title.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col" style="flex: 1; min-width: 0;">
                        <div class="admin-lab-form-group">
                            <label for="background_type"><?php esc_html_e('Background type', 'poke-hub'); ?></label>
                            <select id="background_type" name="background_type">
                                <?php
                                $background_types = function_exists('poke_hub_get_background_types') ? poke_hub_get_background_types() : ['location' => __('Location background', 'poke-hub'), 'special' => __('Special background', 'poke-hub')];
                                foreach ($background_types as $type_value => $type_label) :
                                    ?>
                                    <option value="<?php echo esc_attr($type_value); ?>" <?php selected($background_type, $type_value); ?>><?php echo esc_html($type_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Location or special (event/theme).', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Background Image -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Background Image', 'poke-hub'); ?></h3>
                
                <div id="pokehub-background-image-field">
                    <div class="admin-lab-form-group">
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

            <!-- Section: Event Association (sélecteur unique : recherche par nom, tous types) -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Event Association', 'poke-hub'); ?></h3>
                <p class="description"><?php esc_html_e('Search and select one or more events by name. Event type is detected automatically.', 'poke-hub'); ?></p>
                <div id="pokehub-background-events-list">
                    <?php
                    $event_index = 0;
                    foreach ($current_events as $ev) :
                        $ev_type = isset($ev['event_type']) ? (string) $ev['event_type'] : '';
                        $ev_id = isset($ev['event_id']) ? (int) $ev['event_id'] : 0;
                        if (function_exists('poke_hub_render_event_picker_row')) {
                            poke_hub_render_event_picker_row($event_index, $ev_id, $ev_type, $all_events, 'event_links', 'pokehub-background-event-row', null, 'pokehub-remove-event');
                        }
                        $event_index++;
                    endforeach;
                    ?>
                </div>
                <p><button type="button" class="button pokehub-add-event"><?php esc_html_e('Add event', 'poke-hub'); ?></button></p>
                <?php if (function_exists('poke_hub_render_event_picker_row')) : ?>
                <template id="pokehub-background-event-row-tpl">
                    <?php poke_hub_render_event_picker_row('__INDEX__', 0, '', $all_events, 'event_links', 'pokehub-background-event-row', null, 'pokehub-remove-event'); ?>
                </template>
                <?php endif; ?>
            </div>

            <!-- Section: Linked Pokémon (deux listes distinctes : shiny actif / shiny lock) -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Linked Pokémon', 'poke-hub'); ?></h3>
                <p class="description" style="margin-bottom:12px;"><?php esc_html_e('Two separate lists: Pokémon with shiny available for this background, and Pokémon that are shiny lock (background released before the shiny). A Pokémon can only appear in one of the two lists.', 'poke-hub'); ?></p>
                <div class="admin-lab-form-row" style="display: flex; gap: 1em; flex-wrap: wrap;">
                    <div class="admin-lab-form-col" style="flex: 1; min-width: 0; min-width: 280px;">
                        <div class="admin-lab-form-group">
                            <label for="pokemon_ids_shiny_active"><?php esc_html_e('Pokémon (shiny actif)', 'poke-hub'); ?></label>
                            <select name="pokemon_ids_shiny_active[]" id="pokemon_ids_shiny_active" class="pokehub-pokemon-select" multiple="multiple" style="width:100%;">
                                <?php if (!empty($all_pokemon)) : ?>
                                    <?php foreach ($all_pokemon as $pokemon) : ?>
                                        <?php
                                        $p_id = (int) $pokemon->id;
                                        $p_dex = (int) $pokemon->dex_number;
                                        $p_name = !empty($pokemon->name_fr) ? $pokemon->name_fr : $pokemon->name_en;
                                        $label = sprintf('#%03d %s', $p_dex, esc_html($p_name));
                                        ?>
                                        <option value="<?php echo $p_id; ?>"
                                                data-name-fr="<?php echo esc_attr(!empty($pokemon->name_fr) ? $pokemon->name_fr : ''); ?>"
                                                data-name-en="<?php echo esc_attr(!empty($pokemon->name_en) ? $pokemon->name_en : ''); ?>"
                                                data-label="<?php echo esc_attr($label); ?>"
                                                <?php selected(in_array($p_id, $current_shiny_active_ids, true)); ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Shiny available for this background.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col" style="flex: 1; min-width: 0; min-width: 280px;">
                        <div class="admin-lab-form-group">
                            <label for="shiny_locked_ids"><?php esc_html_e('Pokémon (shiny lock)', 'poke-hub'); ?></label>
                            <select name="shiny_locked_ids[]" id="shiny_locked_ids" class="pokehub-pokemon-select pokehub-shiny-lock-select" multiple="multiple" style="width:100%;">
                                <?php if (!empty($all_pokemon)) : ?>
                                    <?php foreach ($all_pokemon as $pokemon) : ?>
                                        <?php
                                        $p_id = (int) $pokemon->id;
                                        $p_dex = (int) $pokemon->dex_number;
                                        $p_name = !empty($pokemon->name_fr) ? $pokemon->name_fr : $pokemon->name_en;
                                        $label = sprintf('#%03d %s', $p_dex, esc_html($p_name));
                                        ?>
                                        <option value="<?php echo $p_id; ?>"
                                                data-name-fr="<?php echo esc_attr(!empty($pokemon->name_fr) ? $pokemon->name_fr : ''); ?>"
                                                data-name-en="<?php echo esc_attr(!empty($pokemon->name_en) ? $pokemon->name_en : ''); ?>"
                                                data-label="<?php echo esc_attr($label); ?>"
                                                <?php selected(in_array($p_id, $current_shiny_locked_ids, true)); ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Shiny lock (background released before the shiny).', 'poke-hub'); ?></p>
                        </div>
                    </div>
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
        var matcherFn = window.pokehubMultilingualMatcher || function(params, data) {
            if (!params.term || params.term.trim() === '') return data;
            var term = params.term.toLowerCase().trim();
            var text = (data.text || '').toLowerCase();
            if (text.indexOf(term) !== -1) return data;
            if (data.element) {
                var el = data.element;
                var nameFr = (el.getAttribute && el.getAttribute('data-name-fr') || '').toLowerCase();
                var nameEn = (el.getAttribute && el.getAttribute('data-name-en') || '').toLowerCase();
                if (nameFr && nameFr.indexOf(term) !== -1) return data;
                if (nameEn && nameEn.indexOf(term) !== -1) return data;
            }
            return null;
        };

        // Initialiser Select2 sur les deux champs Pokémon
        if ($.fn.select2) {
            $('#pokemon_ids_shiny_active').select2({
                placeholder: '<?php echo esc_js(__('Search Pokémon (shiny actif)...', 'poke-hub')); ?>',
                allowClear: true,
                width: '100%',
                matcher: matcherFn
            });
            $('#shiny_locked_ids').select2({
                placeholder: '<?php echo esc_js(__('Search Pokémon (shiny lock)...', 'poke-hub')); ?>',
                allowClear: true,
                width: '100%',
                matcher: matcherFn
            });
        }

        // Les deux selects sont exclusifs : un Pokémon ne peut être que dans l'un ou l'autre
        function removeFromOtherSelect(sourceSelectId, addedId) {
            var otherId = (sourceSelectId === 'pokemon_ids_shiny_active') ? 'shiny_locked_ids' : 'pokemon_ids_shiny_active';
            var $other = $('#' + otherId);
            var val = ($other.val() || []).filter(function(x) { return x != addedId; });
            if (val.length !== ($other.val() || []).length) {
                $other.val(val).trigger('change');
            }
        }
        $('#pokemon_ids_shiny_active').on('select2:select', function(e) {
            removeFromOtherSelect('pokemon_ids_shiny_active', e.params.data.id);
        });
        $('#shiny_locked_ids').on('select2:select', function(e) {
            removeFromOtherSelect('shiny_locked_ids', e.params.data.id);
        });

        // Sync champ caché event_type depuis l'option sélectionnée (data-source)
        $(document).on('change', '.pokehub-event-picker-select', function() {
            var $select = $(this);
            var $row = $select.closest('.pokehub-event-picker-row');
            var $hidden = $row.find('.pokehub-event-picker-type');
            var $opt = $select.find('option:selected');
            var src = $opt.length ? ($opt.data('source') || '') : '';
            $hidden.val(src);
        });
        $('#pokehub-background-events-list .pokehub-event-picker-select').each(function() {
            $(this).trigger('change');
        });

        // Événements : ajouter une ligne
        var eventRowIndex = <?php echo (int) count($current_events); ?>;
        $('.pokehub-add-event').on('click', function() {
            var tpl = document.getElementById('pokehub-background-event-row-tpl');
            if (!tpl || !tpl.content) return;
            var html = tpl.innerHTML.replace(/__INDEX__/g, eventRowIndex);
            $('#pokehub-background-events-list').append(html);
            eventRowIndex++;
            reindexEventRows();
            if ($.fn.select2) {
                $('#pokehub-background-events-list .pokehub-background-event-row').last().find('.pokehub-event-picker-select').select2({ placeholder: '<?php echo esc_js(__('Search event...', 'poke-hub')); ?>', allowClear: true, width: '100%' });
            }
        });
        $(document).on('click', '.pokehub-remove-event', function() {
            $(this).closest('.pokehub-background-event-row').remove();
            reindexEventRows();
        });
        function reindexEventRows() {
            $('#pokehub-background-events-list .pokehub-background-event-row').each(function(i) {
                $(this).find('.pokehub-event-picker-type').attr('name', 'event_links[' + i + '][event_type]');
                $(this).find('.pokehub-event-picker-select').attr('name', 'event_links[' + i + '][event_id]');
            });
        }
        if ($.fn.select2) {
            $('#pokehub-background-events-list .pokehub-event-picker-select').select2({ placeholder: '<?php echo esc_js(__('Search event...', 'poke-hub')); ?>', allowClear: true, width: '100%' });
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

    });
    </script>
    <?php
}
