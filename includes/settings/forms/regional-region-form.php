<?php
// includes/settings/forms/regional-region-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit Regional Region
 *
 * @param array|null $edit_data
 */
function poke_hub_regional_region_edit_form($edit_data = null) {
    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to edit regional regions.', 'poke-hub'));
    }

    $is_edit = ($edit_data && isset($edit_data['id']));

    // Valeurs par défaut / édition
    $id = 0;
    $slug = '';
    $name_fr = '';
    $name_en = '';
    $countries = [];
    $description = '';

    if ($is_edit) {
        $id = (int) $edit_data['id'];
        $slug = isset($edit_data['slug']) ? (string) $edit_data['slug'] : '';
        $name_fr = isset($edit_data['name_fr']) ? (string) $edit_data['name_fr'] : '';
        $name_en = isset($edit_data['name_en']) ? (string) $edit_data['name_en'] : '';
        $countries = isset($edit_data['countries']) && is_array($edit_data['countries']) ? $edit_data['countries'] : [];
        $description = isset($edit_data['description']) ? (string) $edit_data['description'] : '';
    }

    // Get all countries for reference
    $all_countries = function_exists('poke_hub_get_countries') ? poke_hub_get_countries() : [];
    $all_countries_list = is_array($all_countries) ? array_values($all_countries) : [];

    // Determine back URL based on context (Settings or Pokemon admin)
    $back_url = '';
    if (isset($_GET['page']) && $_GET['page'] === 'poke-hub-pokemon') {
        // Allow filter to override back URL
        $back_url = apply_filters('poke_hub_regional_region_back_url', add_query_arg(
            [
                'page'       => 'poke-hub-pokemon',
                'ph_section' => 'regional_regions',
            ],
            admin_url('admin.php')
        ));
    } else {
        $back_url = add_query_arg(
            [
                'page'       => 'poke-hub-settings',
                'tab'        => 'regional-mapping',
                'subtab'     => 'regions',
            ],
            admin_url('admin.php')
        );
    }
    ?>
    <div class="wrap">
        <h1><?php echo $is_edit ? esc_html__('Edit Geographic Region', 'poke-hub') : esc_html__('Add Geographic Region', 'poke-hub'); ?></h1>
        
        <p><a href="<?php echo esc_url($back_url); ?>" class="button">&larr; <?php _e('Back to Regions', 'poke-hub'); ?></a></p>

        <form method="post" action="">
            <?php wp_nonce_field('poke_hub_regional_mapping_settings', 'poke_hub_regional_mapping_nonce'); ?>
            <input type="hidden" name="action_type" value="save_region">
            <input type="hidden" name="region_id" value="<?php echo esc_attr($id); ?>">
            <input type="hidden" name="poke_hub_regional_mapping_submit" value="1">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="region_slug"><?php _e('Slug', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="region_slug" id="region_slug" class="regular-text" value="<?php echo esc_attr($slug); ?>" <?php echo $is_edit ? 'readonly' : 'required'; ?>>
                        <p class="description"><?php _e('Unique identifier in English (e.g., "europe", "asia", "north-america")', 'poke-hub'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="region_name_fr"><?php _e('Name (French)', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="region_name_fr" id="region_name_fr" class="regular-text" value="<?php echo esc_attr($name_fr); ?>" required>
                        <p class="description"><?php _e('French name (e.g., "Europe", "Asie")', 'poke-hub'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="region_name_en"><?php _e('Name (English)', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="region_name_en" id="region_name_en" class="regular-text" value="<?php echo esc_attr($name_en); ?>" required>
                        <p class="description"><?php _e('English name (e.g., "Europe", "Asia")', 'poke-hub'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="region_countries"><?php _e('Countries', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <textarea name="region_countries" id="region_countries" rows="10" cols="80" class="large-text code" placeholder='["France", "Allemagne", "Espagne"]'><?php echo esc_textarea(wp_json_encode($countries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>
                        <p class="description">
                            <?php _e('JSON array of country labels (must match Ultimate Member country labels exactly).', 'poke-hub'); ?>
                            <br>
                            <button type="button" class="button button-small" id="format-region-countries"><?php _e('Format JSON', 'poke-hub'); ?></button>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="region_description"><?php _e('Description', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <textarea name="region_description" id="region_description" rows="3" class="large-text"><?php echo esc_textarea($description); ?></textarea>
                        <p class="description"><?php _e('Optional description (leave empty on import, add manually later).', 'poke-hub'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button($is_edit ? __('Update Region', 'poke-hub') : __('Add Region', 'poke-hub')); ?>
            <a href="<?php echo esc_url($back_url); ?>" class="button"><?php _e('Cancel', 'poke-hub'); ?></a>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#format-region-countries').on('click', function() {
            var $textarea = $('#region_countries');
            var jsonText = $textarea.val().trim();
            if (!jsonText) {
                alert('<?php echo esc_js(__('No JSON to format.', 'poke-hub')); ?>');
                return;
            }
            try {
                var parsed = JSON.parse(jsonText);
                $textarea.val(JSON.stringify(parsed, null, 2));
            } catch (err) {
                alert('<?php echo esc_js(__('Invalid JSON:', 'poke-hub')); ?> ' + err.message);
            }
        });
    });
    </script>
    <?php
}

