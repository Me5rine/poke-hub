<?php
// modules/pokemon/admin/forms/egg-type-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit Egg Type
 *
 * @param object|null $edit_row
 */
function poke_hub_pokemon_egg_types_edit_form($edit_row = null) {
    if (!function_exists('pokehub_get_table')) {
        echo '<div class="wrap"><h1>' . esc_html__('Missing helper pokehub_get_table()', 'poke-hub') . '</h1></div>';
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to edit egg types.', 'poke-hub'));
    }

    $is_edit = ($edit_row && isset($edit_row->id));

    $name_fr = '';
    $name_en = '';

    if ($is_edit) {
        if (isset($edit_row->name_fr)) {
            $name_fr = (string) $edit_row->name_fr;
        }
        if (isset($edit_row->name_en)) {
            $name_en = (string) $edit_row->name_en;
        }
    }

    $slug = $is_edit ? (string) $edit_row->slug : '';
    $hatch_km = $is_edit && isset($edit_row->hatch_distance_km) ? (int) $edit_row->hatch_distance_km : 2;

    $extra = [];
    $image_url = '';
    $is_adventure_sync = false;
    if ($is_edit && !empty($edit_row->extra)) {
        $decoded = json_decode($edit_row->extra, true);
        if (is_array($decoded)) {
            $extra = $decoded;
        }
    }
    if (isset($extra['image_url'])) {
        $image_url = (string) $extra['image_url'];
    }
    if (isset($extra['is_adventure_sync'])) {
        $is_adventure_sync = !empty($extra['is_adventure_sync']);
    }

    $back_url = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'egg_types',
        ],
        admin_url('admin.php')
    );
    ?>
    <div class="wrap">
        <?php poke_hub_admin_back_to_list_bar($back_url); ?>
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit egg type', 'poke-hub')
                : esc_html__('Add egg type', 'poke-hub');
            ?>
        </h1>

        <form method="post">
            <?php wp_nonce_field('poke_hub_pokemon_edit_egg_type'); ?>
            <input type="hidden" name="page" value="poke-hub-pokemon" />
            <input type="hidden" name="ph_section" value="egg_types" />
            <input type="hidden" name="poke_hub_pokemon_action"
                   value="<?php echo $is_edit ? 'update_egg_type' : 'add_egg_type'; ?>" />
            <?php if ($is_edit) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $edit_row->id; ?>" />
            <?php endif; ?>

            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Basic Information', 'poke-hub'); ?></h3>

                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="name_fr"><?php esc_html_e('Name (French)', 'poke-hub'); ?> *</label>
                            <input type="text" id="name_fr" name="name_fr" value="<?php echo esc_attr($name_fr); ?>" />
                            <p class="description"><?php esc_html_e('Example: 2 km, 7 km, 10 km…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="name_en"><?php esc_html_e('Name (English)', 'poke-hub'); ?> *</label>
                            <input type="text" id="name_en" name="name_en" value="<?php echo esc_attr($name_en); ?>" />
                            <p class="description"><?php esc_html_e('Used by default for slug if empty.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                            <input type="text" id="slug" name="slug" value="<?php echo esc_attr($slug); ?>" />
                            <p class="description"><?php esc_html_e('Key (e.g. 2km, 7km). Leave empty to auto-generate from English name.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="hatch_distance_km"><?php esc_html_e('Hatch distance (km)', 'poke-hub'); ?></label>
                            <input type="number" id="hatch_distance_km" name="hatch_distance_km" value="<?php echo esc_attr($hatch_km); ?>" min="0" step="1" />
                            <p class="description"><?php esc_html_e('Distance to hatch (2, 5, 7, 10, 12, etc.).', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="is_adventure_sync">
                                <input type="checkbox" id="is_adventure_sync" name="is_adventure_sync" value="1" <?php checked($is_adventure_sync); ?> />
                                <?php esc_html_e('Adventure Sync / Suivi d\'exploration', 'poke-hub'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('For 5 km and 10 km eggs: display in "Adventure Sync" section after normal eggs.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Egg icon', 'poke-hub'); ?></h3>

                <div class="admin-lab-form-group">
                    <label for="image_url"><?php esc_html_e('Icon URL', 'poke-hub'); ?></label>
                    <input type="url" id="image_url" name="image_url" value="<?php echo esc_attr($image_url); ?>" />
                    <p class="description"><?php esc_html_e('Full URL to the egg icon.', 'poke-hub'); ?></p>

                    <?php if ($image_url) : ?>
                        <div style="margin-top: 10px;">
                            <img src="<?php echo esc_url($image_url); ?>" alt=""
                                 style="width:64px;height:64px;object-fit:contain;border:1px solid #c3c4c7;padding:8px;background:#fff;border-radius:4px;" />
                        </div>
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
