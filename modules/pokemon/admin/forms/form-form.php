<?php
// File: modules/pokemon/admin/forms/form-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit d'une forme globale (pokemon_form_variants).
 *
 * @param object|null $edit_row Ligne existante de pokemon_form_variants ou null pour un ajout.
 */
function poke_hub_pokemon_forms_edit_form($edit_row = null) {
    if (!function_exists('pokehub_get_table')) {
        echo '<div class="wrap"><h1>' . esc_html__('Missing helper pokehub_get_table()', 'poke-hub') . '</h1></div>';
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to edit forms.', 'poke-hub'));
    }

    $is_edit = ($edit_row && isset($edit_row->id));

    // Valeurs initiales
    $form_slug        = '';
    $label            = '';
    $category         = 'normal';
    $group_key        = '';
    $parent_form_slug = '';
    $names            = ['fr' => '', 'en' => ''];
    $current_events   = [];

    if ($is_edit) {
        $form_slug        = isset($edit_row->form_slug) ? (string) $edit_row->form_slug : '';
        $label            = isset($edit_row->label) ? (string) $edit_row->label : '';
        $category         = isset($edit_row->category) ? (string) $edit_row->category : 'normal';
        $group_key        = isset($edit_row->group_key) ? (string) $edit_row->group_key : (isset($edit_row->group) ? (string) $edit_row->group : '');
        $parent_form_slug = isset($edit_row->parent_form_slug) ? (string) $edit_row->parent_form_slug : '';

        // Événements associés (plusieurs par forme / costume)
        if (function_exists('poke_hub_get_form_variant_events')) {
            $current_events = poke_hub_get_form_variant_events((int) $edit_row->id);
        }

        // Récupérer les traductions depuis extra
        if (!empty($edit_row->extra)) {
            $extra = json_decode($edit_row->extra, true);
            if (is_array($extra) && !empty($extra['names']) && is_array($extra['names'])) {
                $names['fr'] = isset($extra['names']['fr']) ? trim((string) $extra['names']['fr']) : '';
                $names['en'] = isset($extra['names']['en']) ? trim((string) $extra['names']['en']) : '';
            }
        }

        // Si pas de traduction FR dans extra, utiliser le label comme fallback
        if (empty($names['fr']) && !empty($label)) {
            $names['fr'] = $label;
        }
        if (empty($names['en']) && !empty($label)) {
            $names['en'] = $label;
        }
    }

    // Tous les événements pour le picker (recherche par nom, tous types)
    $all_events = function_exists('poke_hub_get_events_for_picker') ? poke_hub_get_events_for_picker() : [];

    global $wpdb;
    $table = pokehub_get_table('pokemon_form_variants');

    // Liste des autres formes possibles comme parent
    $parent_candidates = [];
    if ($table) {
        $parent_candidates = $wpdb->get_results(
            "SELECT form_slug, label, category FROM {$table} ORDER BY category ASC, label ASC",
            ARRAY_A
        );
    }

    $back_url = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'forms',
        ],
        admin_url('admin.php')
    );
    ?>
    <div class="wrap">
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit form', 'poke-hub')
                : esc_html__('Add form', 'poke-hub');
            ?>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">
                <?php esc_html_e('Back to list', 'poke-hub'); ?>
            </a>
        </h1>

        <form method="post">
            <?php wp_nonce_field('poke_hub_pokemon_edit_form'); ?>
            <input type="hidden" name="page" value="poke-hub-pokemon" />
            <input type="hidden" name="ph_section" value="forms" />
            <input type="hidden" name="poke_hub_pokemon_action"
                   value="<?php echo $is_edit ? 'update_form' : 'add_form'; ?>" />
            <?php if ($is_edit) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $edit_row->id; ?>" />
            <?php endif; ?>

            <!-- Section: Basic Information -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Basic Information', 'poke-hub'); ?></h3>
                
                <!-- Form Slug / Label -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="form_slug"><?php esc_html_e('Form Slug', 'poke-hub'); ?> *</label>
                            <input type="text" id="form_slug" name="form_slug" value="<?php echo esc_attr($form_slug); ?>" required />
                            <p class="description"><?php esc_html_e('Example: "armored", "fall-2019", "costume".', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="label"><?php esc_html_e('Label', 'poke-hub'); ?> *</label>
                            <input type="text" id="label" name="label" value="<?php echo esc_attr($label); ?>" required />
                            <p class="description"><?php esc_html_e('Example: "Armored", "Fall 2019", "Costume".', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Category / Group Key -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="category"><?php esc_html_e('Category', 'poke-hub'); ?></label>
                            <select name="category" id="category">
                                <?php
                                $variant_categories = [
                                    'normal'    => __('Normal', 'poke-hub'),
                                    'costume'   => __('Costume / Event', 'poke-hub'),
                                    'clone'     => __('Clone', 'poke-hub'),
                                    'regional'  => __('Regional', 'poke-hub'),
                                    'shadow'    => __('Shadow', 'poke-hub'),
                                    'purified'  => __('Purified', 'poke-hub'),
                                    'mega'      => __('Mega', 'poke-hub'),
                                    'alola'     => __('Alola', 'poke-hub'),
                                    'galar'     => __('Galar', 'poke-hub'),
                                    'hisui'     => __('Hisui', 'poke-hub'),
                                    'paldea'    => __('Paldea', 'poke-hub'),
                                ];
                                $category_value = in_array($category, array_keys($variant_categories), true) ? $category : 'normal';
                                foreach ($variant_categories as $cat_key => $cat_label) :
                                    ?>
                                    <option value="<?php echo esc_attr($cat_key); ?>" <?php selected($category_value, $cat_key); ?>>
                                        <?php echo esc_html($cat_label); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if (!in_array($category, array_keys($variant_categories), true) && $category !== '') : ?>
                                    <option value="<?php echo esc_attr($category); ?>" selected><?php echo esc_html(sprintf(__('Other: %s', 'poke-hub'), $category)); ?></option>
                                <?php endif; ?>
                            </select>
                            <p class="description"><?php esc_html_e('"Costume / Event" automatically marks Pokémon with this form as event/costumed.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="group_key"><?php esc_html_e('Group (e.g. series)', 'poke-hub'); ?></label>
                            <input type="text" id="group_key" name="group_key" value="<?php echo esc_attr($group_key); ?>" placeholder="<?php esc_attr_e('e.g. fall_2019, halloween_2024', 'poke-hub'); ?>" />
                            <p class="description"><?php esc_html_e('Optional: group forms of the same series (Fall 2019, etc.).', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Parent Form -->
                <div class="admin-lab-form-group">
                    <label for="parent_form_slug"><?php esc_html_e('Parent Form', 'poke-hub'); ?></label>
                    <select name="parent_form_slug" id="parent_form_slug">
                        <option value=""><?php esc_html_e('— No parent —', 'poke-hub'); ?></option>
                        <?php
                        if (!empty($parent_candidates)) :
                            foreach ($parent_candidates as $parent) :
                                if ($is_edit && $parent['form_slug'] === $form_slug) {
                                    continue;
                                }

                                $option_label = sprintf(
                                    '%s (%s)',
                                    $parent['label'] !== '' ? $parent['label'] : $parent['form_slug'],
                                    $parent['category'] !== '' ? $parent['category'] : 'normal'
                                );
                                ?>
                                <option value="<?php echo esc_attr($parent['form_slug']); ?>"
                                    <?php selected($parent_form_slug, $parent['form_slug']); ?>>
                                    <?php echo esc_html($option_label); ?>
                                </option>
                                <?php
                            endforeach;
                        endif;
                        ?>
                    </select>
                    <p class="description"><?php esc_html_e('Optional parent form to build hierarchies.', 'poke-hub'); ?></p>
                </div>
            </div>

            <!-- Section: Translations -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Translations', 'poke-hub'); ?></h3>
                <p class="description"><?php esc_html_e('These translations will be used in the front-end (selects, profiles, etc.). If not provided, the label will be used as fallback.', 'poke-hub'); ?></p>
                
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="name_fr"><?php esc_html_e('French Name', 'poke-hub'); ?></label>
                            <input type="text" id="name_fr" name="name_fr" value="<?php echo esc_attr($names['fr']); ?>" />
                            <p class="description"><?php esc_html_e('French translation (e.g., "Archipel", "Continental")', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="name_en"><?php esc_html_e('English Name', 'poke-hub'); ?></label>
                            <input type="text" id="name_en" name="name_en" value="<?php echo esc_attr($names['en']); ?>" />
                            <p class="description"><?php esc_html_e('English translation (e.g., "Archipelago", "Continental")', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Event Association (sélecteur unique : recherche par nom, tous types) -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Event Association', 'poke-hub'); ?></h3>
                <p class="description"><?php esc_html_e('Search and select one or more events by name. Event type is detected automatically. Useful to list which events feature this form or costume.', 'poke-hub'); ?></p>
                <div id="pokehub-form-events-list">
                    <?php
                    $event_index = 0;
                    foreach ($current_events as $ev) :
                        $ev_type = isset($ev['event_type']) ? (string) $ev['event_type'] : '';
                        $ev_id = isset($ev['event_id']) ? (int) $ev['event_id'] : 0;
                        if (function_exists('poke_hub_render_event_picker_row')) {
                            poke_hub_render_event_picker_row($event_index, $ev_id, $ev_type, $all_events, 'event_links', 'pokehub-form-event-row', null, 'pokehub-form-remove-event');
                        }
                        $event_index++;
                    endforeach;
                    ?>
                </div>
                <p><button type="button" class="button pokehub-form-add-event"><?php esc_html_e('Add event', 'poke-hub'); ?></button></p>
                <?php if (function_exists('poke_hub_render_event_picker_row')) : ?>
                <template id="pokehub-form-event-row-tpl">
                    <?php poke_hub_render_event_picker_row('__INDEX__', 0, '', $all_events, 'event_links', 'pokehub-form-event-row', null, 'pokehub-form-remove-event'); ?>
                </template>
                <?php endif; ?>
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
        // Sync champ caché event_type depuis l'option sélectionnée (data-source)
        $(document).on('change', '.pokehub-event-picker-select', function() {
            var $select = $(this);
            var $row = $select.closest('.pokehub-event-picker-row');
            var $hidden = $row.find('.pokehub-event-picker-type');
            var $opt = $select.find('option:selected');
            var src = $opt.length ? ($opt.data('source') || '') : '';
            $hidden.val(src);
        });
        $('#pokehub-form-events-list .pokehub-event-picker-select').each(function() {
            $(this).trigger('change');
        });

        var formEventRowIndex = <?php echo (int) count($current_events); ?>;
        $('.pokehub-form-add-event').on('click', function() {
            var tpl = document.getElementById('pokehub-form-event-row-tpl');
            if (!tpl || !tpl.content) return;
            var html = tpl.innerHTML.replace(/__INDEX__/g, formEventRowIndex);
            $('#pokehub-form-events-list').append(html);
            formEventRowIndex++;
            reindexFormEventRows();
            if ($.fn.select2) {
                $('#pokehub-form-events-list .pokehub-form-event-row').last().find('.pokehub-event-picker-select').select2({ placeholder: '<?php echo esc_js(__('Search event...', 'poke-hub')); ?>', allowClear: true, width: '100%' });
            }
        });
        $(document).on('click', '.pokehub-form-remove-event', function() {
            $(this).closest('.pokehub-form-event-row').remove();
            reindexFormEventRows();
        });
        function reindexFormEventRows() {
            $('#pokehub-form-events-list .pokehub-form-event-row').each(function(i) {
                $(this).find('.pokehub-event-picker-type').attr('name', 'event_links[' + i + '][event_type]');
                $(this).find('.pokehub-event-picker-select').attr('name', 'event_links[' + i + '][event_id]');
            });
        }
        if ($.fn.select2) {
            $('#pokehub-form-events-list .pokehub-event-picker-select').select2({ placeholder: '<?php echo esc_js(__('Search event...', 'poke-hub')); ?>', allowClear: true, width: '100%' });
        }
    });
    </script>
    <?php
}
