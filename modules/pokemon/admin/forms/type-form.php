<?php
// modules/pokemon/admin/forms/type-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit Type Pokémon
 *
 * @param object|null $edit_row              Ligne existante (mode édition) ou null (mode ajout)
 * @param array       $all_weathers          Liste de toutes les météos disponibles (objets avec ->id, ->name_fr / ->name_en éventuellement ->name)
 * @param array       $current_weather_ids   Liste des IDs de météo déjà liées à ce type
 */
function poke_hub_pokemon_types_edit_form($edit_row = null, array $all_weathers = [], array $current_weather_ids = []) {

    if (!current_user_can('manage_options')) {
        return;
    }

    $is_edit = ($edit_row !== null);

    // Noms multilingues actuels
    $current_name_fr = '';
    $current_name_en = '';

    if ($is_edit) {
        if (isset($edit_row->name_fr)) {
            $current_name_fr = (string) $edit_row->name_fr;
        }
        if (isset($edit_row->name_en)) {
            $current_name_en = (string) $edit_row->name_en;
        }

        // Compat rétro : si les deux sont vides mais qu'on a encore "name"
        if ($current_name_fr === '' && $current_name_en === '' && isset($edit_row->name)) {
            $current_name_fr = (string) $edit_row->name;
        }
    }

    $current_slug  = $is_edit ? (string) $edit_row->slug       : '';
    $current_color = $is_edit ? (string) $edit_row->color      : '';
    $current_icon  = $is_edit ? (string) $edit_row->icon       : '';
    $current_sort  = $is_edit ? (int)    $edit_row->sort_order : 0;

    if ($current_color === '') {
        $current_color = '#ffffff';
    }

    $back_url = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'types',
        ],
        admin_url('admin.php')
    );
    ?>
    <div class="wrap">
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit type', 'poke-hub')
                : esc_html__('Add type', 'poke-hub');
            ?>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">
                <?php esc_html_e('Back to list', 'poke-hub'); ?>
            </a>
        </h1>

        <form method="post" action="">
            <?php wp_nonce_field('poke_hub_pokemon_form', 'poke_hub_pokemon_nonce'); ?>
            <input type="hidden" name="ph_section" value="types" />
            <input type="hidden" name="poke_hub_pokemon_action"
                   value="<?php echo $is_edit ? 'update_type' : 'add_type'; ?>" />
            <?php if ($is_edit) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $edit_row->id; ?>" />
            <?php endif; ?>

            <table class="form-table" role="presentation">
                <!-- Nom FR -->
                <tr>
                    <th scope="row">
                        <label for="type_name_fr"><?php esc_html_e('Name (French)', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               class="regular-text"
                               id="type_name_fr"
                               name="name_fr"
                               value="<?php echo esc_attr($current_name_fr); ?>" />
                        <p class="description">
                            <?php esc_html_e('Displayed label in French. Example: Feu, Eau, Plante…', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Nom EN -->
                <tr>
                    <th scope="row">
                        <label for="type_name_en"><?php esc_html_e('Name (English)', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               class="regular-text"
                               id="type_name_en"
                               name="name_en"
                               value="<?php echo esc_attr($current_name_en); ?>" />
                        <p class="description">
                            <?php esc_html_e('Displayed label in English. At least one name (FR or EN) will be required.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="type_slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               class="regular-text"
                               id="type_slug"
                               name="slug"
                               value="<?php echo esc_attr($current_slug); ?>" />
                        <p class="description">
                            <?php esc_html_e('Leave empty to auto-generate from French name, or English fallback.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="type_color"><?php esc_html_e('Color', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               class="regular-text pokehub-color-field"
                               id="type_color"
                               name="color"
                               value="<?php echo esc_attr($current_color); ?>" />
                        <p class="description">
                            <?php esc_html_e('Used as background color for this type (hex, rgb, etc.).', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Icon', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <div class="pokehub-type-icon-field">

                            <!-- Champ caché : stocke l’URL de l’icône -->
                            <input type="hidden"
                                   class="pokehub-type-icon-url"
                                   name="icon"
                                   value="<?php echo esc_attr($current_icon); ?>" />

                            <p class="description">
                                <?php esc_html_e('Choose an image from the media library or insert a URL from the custom tab in the media modal.', 'poke-hub'); ?>
                            </p>

                            <p>
                                <button type="button"
                                        class="button button-secondary pokehub-type-icon-select">
                                    <?php esc_html_e('Choose icon from media library', 'poke-hub'); ?>
                                </button>

                                <button type="button"
                                        class="button pokehub-type-icon-remove"
                                        <?php disabled(empty($current_icon)); ?>>
                                    <?php esc_html_e('Remove image', 'poke-hub'); ?>
                                </button>
                            </p>

                            <div class="pokehub-type-icon-preview-wrap" style="margin-top:8px;">
                                <img
                                    class="pokehub-type-icon-preview"
                                    src="<?php echo $current_icon ? esc_url($current_icon) : ''; ?>"
                                    alt=""
                                    style="max-width:80px;height:auto;border:1px solid #ddd;padding:2px;border-radius:3px;<?php echo $current_icon ? '' : 'display:none;'; ?>"
                                />
                            </div>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="type_sort_order"><?php esc_html_e('Order', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               class="small-text"
                               id="type_sort_order"
                               name="sort_order"
                               value="<?php echo esc_attr($current_sort); ?>" />
                        <p class="description">
                            <?php esc_html_e('Lower values appear first in lists.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Weather boosts (Pokémon GO)', 'poke-hub'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <?php esc_html_e('Boosted by weathers', 'poke-hub'); ?>
                    </th>
                    <td>
                        <?php if (!empty($all_weathers)) : ?>
                            <?php foreach ($all_weathers as $weather) : ?>
                                <?php
                                $wid = (int) $weather->id;
                                $checked = in_array($wid, $current_weather_ids, true);

                                // Label multi-lang avec fallback
                                $label_fr = isset($weather->name_fr) ? (string) $weather->name_fr : '';
                                $label_en = isset($weather->name_en) ? (string) $weather->name_en : '';
                                if ($label_fr !== '') {
                                    $w_label = $label_fr;
                                } elseif ($label_en !== '') {
                                    $w_label = $label_en;
                                } elseif (isset($weather->name)) {
                                    $w_label = (string) $weather->name;
                                } else {
                                    $w_label = '#' . $wid;
                                }
                                ?>
                                <label style="display:inline-block;margin-right:10px;margin-bottom:4px;">
                                    <input type="checkbox"
                                           name="weather_ids[]"
                                           value="<?php echo $wid; ?>"
                                           <?php checked($checked); ?> />
                                    <?php echo esc_html($w_label); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <em><?php esc_html_e('No weather entries found. Please add weathers first.', 'poke-hub'); ?></em>
                        <?php endif; ?>
                        <p class="description">
                            <?php esc_html_e('These weathers will automatically boost Pokémon of this type on the front-end.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php
            submit_button(
                $is_edit
                    ? __('Update type', 'poke-hub')
                    : __('Add type', 'poke-hub')
            );
            ?>
        </form>
    </div>
    <?php
}
