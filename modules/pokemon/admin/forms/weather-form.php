<?php
// modules/pokemon/admin/forms/weather-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit Weather
 *
 * @param object|null $edit_row
 */
function poke_hub_pokemon_weathers_edit_form($edit_row = null) {
    if (!function_exists('pokehub_get_table')) {
        echo '<div class="wrap"><h1>' . esc_html__('Missing helper pokehub_get_table()', 'poke-hub') . '</h1></div>';
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to edit weathers.', 'poke-hub'));
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

        // Compatibilité rétro
        if ($name_fr === '' && $name_en === '' && isset($edit_row->name)) {
            $name_fr = (string) $edit_row->name;
        }
    }

    $slug = $is_edit ? (string) $edit_row->slug : '';

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
            'ph_section' => 'weathers',
        ],
        admin_url('admin.php')
    );
    ?>
    <div class="wrap">
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit weather', 'poke-hub')
                : esc_html__('Add weather', 'poke-hub');
            ?>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">
                <?php esc_html_e('Back to list', 'poke-hub'); ?>
            </a>
        </h1>

        <form method="post">
            <?php wp_nonce_field('poke_hub_pokemon_edit_weather'); ?>
            <input type="hidden" name="page" value="poke-hub-pokemon" />
            <input type="hidden" name="ph_section" value="weathers" />
            <input type="hidden" name="poke_hub_pokemon_action"
                   value="<?php echo $is_edit ? 'update_weather' : 'add_weather'; ?>" />
            <?php if ($is_edit) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $edit_row->id; ?>" />
            <?php endif; ?>

            <!-- Section: Basic Information -->
            <div class="pokehub-section">
                <h3><?php esc_html_e('Basic Information', 'poke-hub'); ?></h3>
                
                <!-- Name FR / Name EN -->
                <div class="pokehub-form-row">
                    <div class="pokehub-form-col-50">
                        <div class="pokehub-form-group">
                            <label for="name_fr"><?php esc_html_e('Name (French)', 'poke-hub'); ?> *</label>
                            <input type="text" id="name_fr" name="name_fr" value="<?php echo esc_attr($name_fr); ?>" />
                            <p class="description"><?php esc_html_e('Example: Pluie, Ensoleillé, Ciel couvert…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="pokehub-form-col-50">
                        <div class="pokehub-form-group">
                            <label for="name_en"><?php esc_html_e('Name (English)', 'poke-hub'); ?> *</label>
                            <input type="text" id="name_en" name="name_en" value="<?php echo esc_attr($name_en); ?>" />
                            <p class="description"><?php esc_html_e('Example: Rain, Sunny, Partly Cloudy…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Slug -->
                <div class="pokehub-form-group">
                    <label for="slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                    <input type="text" id="slug" name="slug" value="<?php echo esc_attr($slug); ?>" />
                    <p class="description"><?php esc_html_e('Used as a key (e.g. "rain", "sunny", "partly_cloudy"). Leave empty to auto-generate.', 'poke-hub'); ?></p>
                </div>
            </div>

            <!-- Section: Weather Image -->
            <div class="pokehub-section">
                <h3><?php esc_html_e('Weather Image', 'poke-hub'); ?></h3>
                
                <div class="pokehub-form-group">
                    <label for="image_url"><?php esc_html_e('Image URL', 'poke-hub'); ?></label>
                    <input type="url" id="image_url" name="image_url" value="<?php echo esc_attr($image_url); ?>" />
                    <p class="description"><?php esc_html_e('Full URL to the weather icon (e.g. S3 bucket, CDN…).', 'poke-hub'); ?></p>
                    
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
