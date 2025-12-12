<?php
// modules/pokemon/admin/forms/item-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit Item
 *
 * @param object|null $edit_row
 */
function poke_hub_pokemon_items_edit_form($edit_row = null) {
    if (!function_exists('pokehub_get_table')) {
        echo '<div class="wrap"><h1>' . esc_html__('Missing helper pokehub_get_table()', 'poke-hub') . '</h1></div>';
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to edit items.', 'poke-hub'));
    }

    $is_edit = ($edit_row && isset($edit_row->id));

    // Noms multilingues actuels
    $name_fr = '';
    $name_en = '';

    if ($is_edit) {
        if (isset($edit_row->name_fr)) {
            $name_fr = (string) $edit_row->name_fr;
        }
        if (isset($edit_row->name_en)) {
            $name_en = (string) $edit_row->name_en;
        }

        // Compat rétro éventuelle
        if ($name_fr === '' && $name_en === '' && isset($edit_row->name)) {
            $name_fr = (string) $edit_row->name;
        }
    }

    $slug = $is_edit ? (string) $edit_row->slug : '';

    // Descriptions multilingues
    $description_fr = '';
    $description_en = '';

    if ($is_edit) {
        if (isset($edit_row->description_fr)) {
            $description_fr = (string) $edit_row->description_fr;
        }
        if (isset($edit_row->description_en)) {
            $description_en = (string) $edit_row->description_en;
        }
    }

    // Décodage extra pour récupérer l'image
    $extra     = [];
    $image_url = '';
    if ($is_edit && !empty($edit_row->extra)) {
        $decoded = json_decode($edit_row->extra, true);
        if (is_array($decoded)) {
            $extra = $decoded;
        }
    }
    if (isset($extra['image_url'])) {
        $image_url = (string) $extra['image_url'];
    }

    $back_url = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'items',
        ],
        admin_url('admin.php')
    );
    ?>
    <div class="wrap">
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit item', 'poke-hub')
                : esc_html__('Add item', 'poke-hub');
            ?>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">
                <?php esc_html_e('Back to list', 'poke-hub'); ?>
            </a>
        </h1>

        <form method="post">
            <?php wp_nonce_field('poke_hub_pokemon_edit_item'); ?>
            <input type="hidden" name="page" value="poke-hub-pokemon" />
            <input type="hidden" name="ph_section" value="items" />
            <input type="hidden" name="poke_hub_pokemon_action"
                   value="<?php echo $is_edit ? 'update_item' : 'add_item'; ?>" />
            <?php if ($is_edit) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $edit_row->id; ?>" />
            <?php endif; ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="name_fr"><?php esc_html_e('Name (French)', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text" class="regular-text" name="name_fr" id="name_fr"
                               value="<?php echo esc_attr($name_fr); ?>" />
                        <p class="description">
                            <?php esc_html_e('Example: Encens, Oeuf Chance, Passe de raid…', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="name_en"><?php esc_html_e('Name (English)', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text" class="regular-text" name="name_en" id="name_en"
                               value="<?php echo esc_attr($name_en); ?>" />
                        <p class="description">
                            <?php esc_html_e('Example: Incense, Lucky Egg, Raid Pass…', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="description_fr"><?php esc_html_e('Description (French)', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <textarea class="large-text" rows="4" name="description_fr" id="description_fr"><?php echo esc_textarea($description_fr); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('Optional: detailed description in French (what the item does in Pokémon GO).', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="description_en"><?php esc_html_e('Description (English)', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <textarea class="large-text" rows="4" name="description_en" id="description_en"><?php echo esc_textarea($description_en); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('Optional: detailed description in English.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text" class="regular-text" name="slug" id="slug"
                               value="<?php echo esc_attr($slug); ?>" />
                        <p class="description">
                            <?php esc_html_e('Used as a key (e.g. "incense", "lucky-egg"). Leave empty to auto-generate from the French or English name.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="image_url"><?php esc_html_e('Item image URL', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="url" class="regular-text" name="image_url" id="image_url"
                               value="<?php echo esc_attr($image_url); ?>" />
                        <p class="description">
                            <?php esc_html_e('Full URL to the item icon (e.g. S3 bucket, CDN…).', 'poke-hub'); ?>
                        </p>

                        <?php if ($image_url) : ?>
                            <p>
                                <img src="<?php echo esc_url($image_url); ?>" alt=""
                                     style="width:48px;height:48px;object-fit:contain;border:1px solid #ccd0d4;padding:2px;background:#fff;" />
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <?php
            submit_button(
                $is_edit
                    ? __('Update item', 'poke-hub')
                    : __('Add item', 'poke-hub')
            );
            ?>
        </form>
    </div>
    <?php
}
