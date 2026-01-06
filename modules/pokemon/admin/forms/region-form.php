<?php
// modules/pokemon/admin/forms/region-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit région
 *
 * @param object|null $edit_row Ligne existante (mode édition) ou null (mode ajout)
 */
function poke_hub_pokemon_regions_edit_form($edit_row = null) {
    if (!function_exists('pokehub_get_table')) {
        echo '<div class="wrap"><h1>' . esc_html__('Missing helper pokehub_get_table()', 'poke-hub') . '</h1></div>';
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

    $back_url = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'regions',
        ],
        admin_url('admin.php')
    );
    ?>
    <div class="wrap">
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit region', 'poke-hub')
                : esc_html__('Add region', 'poke-hub');
            ?>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">
                <?php esc_html_e('Back to list', 'poke-hub'); ?>
            </a>
        </h1>

        <form method="post" action="">
            <?php wp_nonce_field('poke_hub_pokemon_region_form', 'poke_hub_pokemon_region_nonce'); ?>
            <input type="hidden" name="ph_section" value="regions" />
            <input type="hidden" name="poke_hub_pokemon_action"
                   value="<?php echo $is_edit ? 'update_region' : 'add_region'; ?>" />
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
                            <label for="region_name_fr"><?php esc_html_e('Name (French)', 'poke-hub'); ?> *</label>
                            <input type="text" id="region_name_fr" name="name_fr" value="<?php echo esc_attr($current_name_fr); ?>" />
                            <p class="description"><?php esc_html_e('Example: Kanto, Johto, Hoenn…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="region_name_en"><?php esc_html_e('Name (English)', 'poke-hub'); ?> *</label>
                            <input type="text" id="region_name_en" name="name_en" value="<?php echo esc_attr($current_name_en); ?>" />
                            <p class="description"><?php esc_html_e('At least one name (FR or EN) is required.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Slug & Sort Order -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col">
                        <div class="admin-lab-form-group">
                            <label for="region_slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                            <input type="text" id="region_slug" name="slug" value="<?php echo esc_attr($is_edit ? $edit_row->slug : ''); ?>" />
                            <p class="description"><?php esc_html_e('Leave empty to auto-generate from name.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col">
                        <div class="admin-lab-form-group">
                            <label for="region_sort_order"><?php esc_html_e('Sort Order', 'poke-hub'); ?></label>
                            <input type="number" id="region_sort_order" name="sort_order" 
                                   value="<?php echo esc_attr($is_edit ? (int) $edit_row->sort_order : '0'); ?>" 
                                   style="max-width: 150px;" />
                            <p class="description"><?php esc_html_e('Lower = appears first in lists.', 'poke-hub'); ?></p>
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
    <?php
}
