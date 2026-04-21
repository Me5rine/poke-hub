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

        // Compat rétro
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

    $auto_image_url = '';
    if ($is_edit && function_exists('pokehub_get_item_data_by_id')) {
        $item_data = pokehub_get_item_data_by_id((int) $edit_row->id);
        if (is_array($item_data) && !empty($item_data['image_url'])) {
            $auto_image_url = (string) $item_data['image_url'];
        }
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
        <?php poke_hub_admin_back_to_list_bar($back_url); ?>
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit item', 'poke-hub')
                : esc_html__('Add item', 'poke-hub');
            ?>
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

            <!-- Section: Basic Information -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Basic Information', 'poke-hub'); ?></h3>
                
                <!-- Name FR / Name EN -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="name_fr"><?php esc_html_e('Name (French)', 'poke-hub'); ?> *</label>
                            <input type="text" id="name_fr" name="name_fr" value="<?php echo esc_attr($name_fr); ?>" />
                            <p class="description"><?php esc_html_e('Example: Encens, Oeuf Chance, Passe de raid…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="name_en"><?php esc_html_e('Name (English)', 'poke-hub'); ?> *</label>
                            <input type="text" id="name_en" name="name_en" value="<?php echo esc_attr($name_en); ?>" />
                            <p class="description"><?php esc_html_e('Example: Incense, Lucky Egg, Raid Pass…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Slug -->
                <div class="admin-lab-form-group">
                    <label for="slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                    <input type="text" id="slug" name="slug" value="<?php echo esc_attr($slug); ?>" />
                    <p class="description"><?php esc_html_e('Used as a key (e.g. "incense", "lucky-egg"). Leave empty to auto-generate.', 'poke-hub'); ?></p>
                </div>
            </div>

            <!-- Section: Descriptions -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Descriptions', 'poke-hub'); ?></h3>
                
                <!-- Description FR / Description EN -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="description_fr"><?php esc_html_e('Description (French)', 'poke-hub'); ?></label>
                            <textarea id="description_fr" name="description_fr" rows="5" style="width: 100%;"><?php echo esc_textarea($description_fr); ?></textarea>
                            <p class="description"><?php esc_html_e('Optional: what the item does in Pokémon GO.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="description_en"><?php esc_html_e('Description (English)', 'poke-hub'); ?></label>
                            <textarea id="description_en" name="description_en" rows="5" style="width: 100%;"><?php echo esc_textarea($description_en); ?></textarea>
                            <p class="description"><?php esc_html_e('Optional: detailed description in English.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Item Image -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Item Image', 'poke-hub'); ?></h3>
                
                <div class="admin-lab-form-group">
                    <p class="description"><?php esc_html_e('Image URL is generated automatically from image sources and item slug: slug.webp (fallback slug.png).', 'poke-hub'); ?></p>
                    <?php if (!empty($slug)) : ?>
                        <p><code><?php echo esc_html($slug); ?>.webp</code> → <code><?php echo esc_html($slug); ?>.png</code></p>
                    <?php endif; ?>

                    <?php if ($auto_image_url) : ?>
                        <div style="margin-top: 10px;">
                            <img src="<?php echo esc_url($auto_image_url); ?>" alt=""
                                 style="width:64px;height:64px;object-fit:contain;border:1px solid #c3c4c7;padding:8px;background:#fff;border-radius:4px;" />
                        </div>
                    <?php else : ?>
                        <p class="description"><?php esc_html_e('Preview unavailable until the item is saved with a slug.', 'poke-hub'); ?></p>
                    <?php endif; ?>
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
    <?php
}
