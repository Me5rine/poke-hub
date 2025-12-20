<?php
// modules/events/admin/events-admin-special-events-form.php

if (!defined('ABSPATH')) exit;

/**
 * Formulaire add/edit special event.
 *
 * @param string $mode         'add' ou 'edit'
 * @param object|null $event   Event spécial (edit)
 * @param array $pokemon_rows  [['pokemon_id' => int], ...]
 * @param array $bonus_rows    [['bonus_id' => int, 'description' => string], ...]
 */
function pokehub_render_special_event_form(
    string $mode = 'add',
    ?object $event = null,
    array $pokemon_rows = [],
    array $bonus_rows = []
) {
    $is_edit = ($mode === 'edit' && $event && !empty($event->id));

    $page_title = $is_edit
        ? __('Edit Special Event', 'poke-hub')
        : __('Add Special Event', 'poke-hub');

    $title       = $is_edit ? $event->title : '';
    $slug        = $is_edit ? $event->slug : '';
    $event_type  = $is_edit ? $event->event_type_slug : '';
    $description = $is_edit ? $event->description : '';

    $start_value = $is_edit && !empty($event->start_ts)
        ? date('Y-m-d\TH:i', $event->start_ts)
        : '';
    $end_value = $is_edit && !empty($event->end_ts)
        ? date('Y-m-d\TH:i', $event->end_ts)
        : '';

    $mode = $is_edit && !empty($event->mode) ? $event->mode : 'local';

    $recurring = $is_edit && !empty($event->recurring) ? (int) $event->recurring : 0;

    $recurring_freq = $is_edit && !empty($event->recurring_freq)
        ? $event->recurring_freq
        : 'weekly';

    $recurring_interval = $is_edit && !empty($event->recurring_interval)
        ? (int) $event->recurring_interval
        : 1;

    $recurring_end_value = ($is_edit && !empty($event->recurring_window_end_ts))
        ? date('Y-m-d\TH:i', $event->recurring_window_end_ts)
        : '';

    $image_id   = $is_edit && !empty($event->image_id) ? (int) $event->image_id : 0;
    $image_url  = $is_edit && !empty($event->image_url) ? $event->image_url : '';

    // URL pour l’aperçu
    $current_image_url = '';

    if ($image_id) {
        $img = wp_get_attachment_image_src($image_id, 'medium');
        if (!empty($img[0])) {
            $current_image_url = esc_url($img[0]);
        }
    } elseif ($image_url) {
        $current_image_url = esc_url($image_url);
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html($page_title); ?></h1>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="pokehub-special-event-form">
            <?php wp_nonce_field('pokehub_save_special_event', 'pokehub_special_event_nonce'); ?>
            <input type="hidden" name="action" value="pokehub_save_special_event">

            <?php if ($is_edit) : ?>
                <input type="hidden" name="event_id" value="<?php echo esc_attr($event->id); ?>">
            <?php endif; ?>
            
            <?php
            // Préserver les paramètres de filtrage depuis l'URL d'origine
            $preserve_params = ['event_status', 'event_source', 'event_type', 's', 'paged', 'orderby', 'order'];
            foreach ($preserve_params as $param) {
                if (isset($_GET[$param]) && $_GET[$param] !== '') {
                    echo '<input type="hidden" name="' . esc_attr($param) . '" value="' . esc_attr($_GET[$param]) . '">' . "\n";
                }
            }
            ?>

            <h2><?php esc_html_e('Main information', 'poke-hub'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><label for="event_title"><?php esc_html_e('Title', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="text"
                               class="regular-text"
                               id="event_title"
                               name="event[title]"
                               value="<?php echo esc_attr($title); ?>"
                               required>
                    </td>
                </tr>

                <tr>
                    <th><label for="event_slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="text"
                               class="regular-text"
                               id="event_slug"
                               name="event[slug]"
                               value="<?php echo esc_attr($slug); ?>"
                               placeholder="<?php esc_attr_e('Leave empty to auto-generate from title', 'poke-hub'); ?>">
                        <p class="description">
                            <?php esc_html_e('Leave empty to auto-generate from title (WordPress style)', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th><label for="event_type"><?php esc_html_e('Event type', 'poke-hub'); ?></label></th>
                    <td>
                        <select id="event_type" name="event[event_type]" required>
                            <option value=""><?php esc_html_e('Select a type', 'poke-hub'); ?></option>
                            <?php foreach (poke_hub_events_get_all_event_types() as $type) : ?>
                                <option value="<?php echo esc_attr($type->slug); ?>"
                                    <?php selected($event_type, $type->slug); ?>>
                                    <?php echo esc_html($type->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><label for="event_image"><?php esc_html_e('Event image', 'poke-hub'); ?></label></th>
                    <td>
                        <div class="pokehub-special-event-image-field" id="pokehub-special-event-image-field">

                            <div class="image-preview" style="margin-bottom:10px;">
                                <?php if ($current_image_url): ?>
                                    <img src="<?php echo esc_url($current_image_url); ?>"
                                        class="pokehub-event-image-preview"
                                        alt=""
                                        style="max-width:100%;height:auto;display:block;">
                                <?php else: ?>
                                    <p class="description" style="margin:0;">
                                        <?php esc_html_e('No image selected yet.', 'poke-hub'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <p>
                                <button type="button"
                                        class="button pokehub-select-event-image">
                                    <?php esc_html_e('Choose from Media Library', 'poke-hub'); ?>
                                </button>

                                <button type="button"
                                        class="button button-link-delete pokehub-remove-event-image"
                                        <?php if (!$current_image_url) echo 'style="display:none;"'; ?>>
                                    <?php esc_html_e('Remove image', 'poke-hub'); ?>
                                </button>
                            </p>

                            <!-- URL stockée mais pas éditable directement -->
                            <input type="hidden"
                                id="event_image_url"
                                name="event[image_url]"
                                value="<?php echo esc_attr($image_url); ?>">

                        </div>
                    </td>
                </tr>

                <tr>
                    <th><?php esc_html_e('Time mode', 'poke-hub'); ?></th>
                    <td>
                        <label>
                            <input type="radio" name="event[mode]" value="local"
                                <?php checked($mode, 'local'); ?>>
                            <?php esc_html_e('Local time (14:00–17:00 in each timezone)', 'poke-hub'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="event[mode]" value="fixed"
                                <?php checked($mode, 'fixed'); ?>>
                            <?php esc_html_e('Fixed UTC time', 'poke-hub'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th><label for="event_start"><?php esc_html_e('Start date/time', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="datetime-local"
                               id="event_start"
                               name="event[start]"
                               value="<?php echo esc_attr($start_value); ?>"
                               required>
                    </td>
                </tr>

                <tr>
                    <th><label for="event_end"><?php esc_html_e('End date/time', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="datetime-local"
                               id="event_end"
                               name="event[end]"
                               value="<?php echo esc_attr($end_value); ?>"
                               required>
                    </td>
                </tr>

                <tr>
                    <th><?php esc_html_e('Recurring event', 'poke-hub'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="event[recurring]" value="1"
                                <?php checked($recurring, 1); ?>>
                            <?php esc_html_e('This event repeats', 'poke-hub'); ?>
                        </label>

                        <div style="margin-top:8px; padding-left:18px;">
                            <label>
                                <?php esc_html_e('Frequency', 'poke-hub'); ?>
                                <select name="event[recurring_freq]">
                                    <option value="weekly"  <?php selected($recurring_freq, 'weekly');  ?>>
                                        <?php esc_html_e('Weekly', 'poke-hub'); ?>
                                    </option>
                                    <option value="daily"   <?php selected($recurring_freq, 'daily');   ?>>
                                        <?php esc_html_e('Daily', 'poke-hub'); ?>
                                    </option>
                                    <option value="monthly" <?php selected($recurring_freq, 'monthly'); ?>>
                                        <?php esc_html_e('Monthly', 'poke-hub'); ?>
                                    </option>
                                </select>
                            </label>
                            &nbsp;&times;&nbsp;
                            <label>
                                <?php esc_html_e('Interval', 'poke-hub'); ?>
                                <input type="number"
                                    name="event[recurring_interval]"
                                    value="<?php echo esc_attr($recurring_interval); ?>"
                                    min="1"
                                    step="1"
                                    style="width:60px;">
                            </label>
                            <br><br>
                            <label>
                                <?php esc_html_e('Repeat until', 'poke-hub'); ?>
                                <input type="datetime-local"
                                    name="event[recurring_window_end]"
                                    value="<?php echo esc_attr($recurring_end_value); ?>">
                            </label>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th><label for="event_description"><?php esc_html_e('Description', 'poke-hub'); ?></label></th>
                    <td>
                        <textarea id="event_description"
                                  name="event[description]"
                                  rows="5"
                                  class="large-text"><?php echo esc_textarea($description); ?></textarea>
                    </td>
                </tr>
            </table>

            <hr>

            <h2><?php esc_html_e('Pokémon & special moves', 'poke-hub'); ?></h2>

            <div id="pokehub-event-pokemon-wrapper">
                <!-- Ligne modèle -->
                <div class="pokehub-event-pokemon-row template" style="display:none;">
                    <select class="pokehub-pokemon-select" style="width: 100%;">
                        <option value=""><?php esc_html_e('Select a Pokémon', 'poke-hub'); ?></option>
                        <?php 
                        $pokemon_list = pokehub_get_all_pokemon_for_select();
                        if (empty($pokemon_list)) : ?>
                            <option value="" disabled><?php esc_html_e('No Pokémon found in database', 'poke-hub'); ?></option>
                        <?php else : ?>
                            <?php foreach ($pokemon_list as $p) : ?>
                                <option value="<?php echo esc_attr($p['id']); ?>"
                                        data-name-fr="<?php echo esc_attr($p['name_fr'] ?? ''); ?>"
                                        data-name-en="<?php echo esc_attr($p['name_en'] ?? ''); ?>">
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            '#%03d %s%s',
                                            $p['dex_number'],
                                            $p['name'],
                                            !empty($p['form']) ? ' (' . $p['form'] . ')' : ''
                                        )
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                    <div class="pokehub-pokemon-attacks">
                        <p class="description">
                            <?php esc_html_e('Select special moves for this Pokémon (if any).', 'poke-hub'); ?>
                        </p>
                    </div>

                    <button type="button" class="button link-button pokehub-remove-pokemon-row">
                        <?php esc_html_e('Remove', 'poke-hub'); ?>
                    </button>
                </div>

                <?php if ($is_edit && !empty($pokemon_rows)) : ?>
                    <?php 
                    $pokemon_list_edit = pokehub_get_all_pokemon_for_select();
                    foreach ($pokemon_rows as $prow) : ?>
                        <div class="pokehub-event-pokemon-row">
                            <select class="pokehub-pokemon-select" style="width: 100%;">
                                <option value=""><?php esc_html_e('Select a Pokémon', 'poke-hub'); ?></option>
                                <?php if (empty($pokemon_list_edit)) : ?>
                                    <option value="" disabled><?php esc_html_e('No Pokémon found in database', 'poke-hub'); ?></option>
                                <?php else : ?>
                                    <?php foreach ($pokemon_list_edit as $p) : ?>
                                        <option value="<?php echo esc_attr($p['id']); ?>"
                                                data-name-fr="<?php echo esc_attr($p['name_fr'] ?? ''); ?>"
                                                data-name-en="<?php echo esc_attr($p['name_en'] ?? ''); ?>"
                                            <?php selected($prow['pokemon_id'], $p['id']); ?>>
                                            <?php
                                            echo esc_html(
                                                sprintf(
                                                    '#%03d %s%s',
                                                    $p['dex_number'],
                                                    $p['name'],
                                                    !empty($p['form']) ? ' (' . $p['form'] . ')' : ''
                                                )
                                            );
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>

                            <div class="pokehub-pokemon-attacks">
                                <p class="description">
                                    <?php esc_html_e('Select special moves for this Pokémon (if any).', 'poke-hub'); ?>
                                </p>
                            </div>

                            <button type="button" class="button link-button pokehub-remove-pokemon-row">
                                <?php esc_html_e('Remove', 'poke-hub'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <p>
                <button type="button" class="button" id="pokehub-add-pokemon-row">
                    <?php esc_html_e('Add a Pokémon', 'poke-hub'); ?>
                </button>
            </p>

            <hr>

            <h2><?php esc_html_e('Bonuses', 'poke-hub'); ?></h2>

            <div id="pokehub-event-bonuses-wrapper">
                <!-- Modèle bonus -->
                <div class="pokehub-event-bonus-row template" style="display:none;">
                    <select class="pokehub-bonus-select">
                        <option value=""><?php esc_html_e('Select a bonus', 'poke-hub'); ?></option>
                        <?php foreach (pokehub_get_all_bonuses_for_select() as $b) : ?>
                            <option value="<?php echo esc_attr($b['id']); ?>">
                                <?php echo esc_html($b['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="text"
                           class="regular-text pokehub-bonus-description"
                           placeholder="<?php esc_attr_e('Bonus description (optional)', 'poke-hub'); ?>">

                    <button type="button" class="button link-button pokehub-remove-bonus-row">
                        <?php esc_html_e('Remove', 'poke-hub'); ?>
                    </button>
                </div>

                <?php if ($is_edit && !empty($bonus_rows)) : ?>
                    <?php foreach ($bonus_rows as $brow) : ?>
                        <div class="pokehub-event-bonus-row">
                            <select class="pokehub-bonus-select">
                                <option value=""><?php esc_html_e('Select a bonus', 'poke-hub'); ?></option>
                                <?php foreach (pokehub_get_all_bonuses_for_select() as $b) : ?>
                                    <option value="<?php echo esc_attr($b['id']); ?>"
                                        <?php selected($brow['bonus_id'], $b['id']); ?>>
                                        <?php echo esc_html($b['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input type="text"
                                   class="regular-text pokehub-bonus-description"
                                   value="<?php echo esc_attr($brow['description']); ?>"
                                   placeholder="<?php esc_attr_e('Bonus description (optional)', 'poke-hub'); ?>">

                            <button type="button" class="button link-button pokehub-remove-bonus-row">
                                <?php esc_html_e('Remove', 'poke-hub'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <p>
                <button type="button" class="button" id="pokehub-add-bonus-row">
                    <?php esc_html_e('Add a bonus', 'poke-hub'); ?>
                </button>
            </p>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo $is_edit
                        ? esc_html__('Update event', 'poke-hub')
                        : esc_html__('Save event', 'poke-hub'); ?>
                </button>
            </p>

            <!-- champs cachés remplis par le JS avant submit -->
            <input type="hidden" name="pokemon_payload" id="pokehub-pokemon-payload">
            <input type="hidden" name="bonuses_payload" id="pokehub-bonuses-payload">
        </form>
    </div>
    <?php
}
