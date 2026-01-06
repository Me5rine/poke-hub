<?php
// modules/pokemon/admin/forms/type-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit Type Pokémon
 */
function poke_hub_pokemon_types_edit_form($edit_row = null, array $all_weathers = [], array $current_weather_ids = [], array $all_types = [], array $current_weakness_ids = [], array $current_resistance_ids = [], array $current_immune_ids = [], array $current_offensive_super_effective_ids = [], array $current_offensive_not_very_effective_ids = [], array $current_offensive_no_effect_ids = []) {

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

            <!-- Section: Basic Information -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Basic Information', 'poke-hub'); ?></h3>
                
                <!-- Name FR / Name EN -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="type_name_fr"><?php esc_html_e('Name (French)', 'poke-hub'); ?> *</label>
                            <input type="text" id="type_name_fr" name="name_fr" value="<?php echo esc_attr($current_name_fr); ?>" />
                            <p class="description"><?php esc_html_e('Example: Feu, Eau, Plante…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="type_name_en"><?php esc_html_e('Name (English)', 'poke-hub'); ?> *</label>
                            <input type="text" id="type_name_en" name="name_en" value="<?php echo esc_attr($current_name_en); ?>" />
                            <p class="description"><?php esc_html_e('At least one name is required.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Slug / Color / Sort Order -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col">
                        <div class="admin-lab-form-group">
                            <label for="type_slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                            <input type="text" id="type_slug" name="slug" value="<?php echo esc_attr($current_slug); ?>" />
                            <p class="description"><?php esc_html_e('Leave empty to auto-generate.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col">
                        <div class="admin-lab-form-group">
                            <label for="type_color"><?php esc_html_e('Color', 'poke-hub'); ?></label>
                            <input type="text" id="type_color" name="color" value="<?php echo esc_attr($current_color); ?>" 
                                   class="pokehub-color-field" style="max-width: 150px;" />
                            <p class="description"><?php esc_html_e('Background color (hex).', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col">
                        <div class="admin-lab-form-group">
                            <label for="type_sort_order"><?php esc_html_e('Order', 'poke-hub'); ?></label>
                            <input type="number" id="type_sort_order" name="sort_order" value="<?php echo esc_attr($current_sort); ?>" 
                                   style="max-width: 150px;" />
                            <p class="description"><?php esc_html_e('Display order.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Icon -->
                <div class="admin-lab-form-group">
                    <label><?php esc_html_e('Icon', 'poke-hub'); ?></label>
                    <div class="pokehub-type-icon-field">
                        <input type="hidden" class="pokehub-type-icon-url" name="icon" value="<?php echo esc_attr($current_icon); ?>" />
                        
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <button type="button" class="button button-secondary pokehub-type-icon-select">
                                <?php esc_html_e('Choose icon from library', 'poke-hub'); ?>
                            </button>
                            <button type="button" class="button pokehub-type-icon-remove" <?php disabled(empty($current_icon)); ?>>
                                <?php esc_html_e('Remove image', 'poke-hub'); ?>
                            </button>
                        </div>
                        
                        <div class="pokehub-type-icon-preview-wrap" style="margin-top:10px;">
                            <img class="pokehub-type-icon-preview"
                                 src="<?php echo $current_icon ? esc_url($current_icon) : ''; ?>"
                                 alt=""
                                 style="max-width:64px;height:auto;border:1px solid #c3c4c7;padding:8px;border-radius:4px;background:#fff;<?php echo $current_icon ? '' : 'display:none;'; ?>" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Defensive Properties (When this type is attacked) -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Defensive Properties (When Attacked)', 'poke-hub'); ?></h3>
                <p class="description" style="margin-top: 0;"><?php esc_html_e('How this type reacts to incoming attacks.', 'poke-hub'); ?></p>
                
                <!-- Weak to (×2) -->
                <div class="admin-lab-form-group">
                    <label><?php esc_html_e('Weak to (×2)', 'poke-hub'); ?></label>
                    <?php if (!empty($all_types) && is_array($all_types)) : ?>
                        <div class="pokehub-types-grid">
                            <?php foreach ($all_types as $type) : ?>
                                <?php
                                $tid = (int) $type->id;
                                $checked = in_array($tid, $current_weakness_ids, true);
                                $t_label = !empty($type->name_fr) ? $type->name_fr : (!empty($type->name_en) ? $type->name_en : ($type->name ?? '#'.$tid));
                                ?>
                                <label class="pokehub-type-checkbox">
                                    <input type="checkbox" name="weakness_ids[]" value="<?php echo $tid; ?>" <?php checked($checked); ?> />
                                    <span><?php echo esc_html($t_label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p class="description" style="color: #d63638;">
                            <?php 
                            if (empty($all_types)) {
                                esc_html_e('No types found. Please create at least one type first, or check that types are being loaded correctly.', 'poke-hub');
                            } else {
                                esc_html_e('Error: types data format is incorrect.', 'poke-hub');
                            }
                            ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Resists (×½) -->
                <div class="admin-lab-form-group">
                    <label><?php esc_html_e('Resists (×½)', 'poke-hub'); ?></label>
                    <?php if (!empty($all_types)) : ?>
                        <div class="pokehub-types-grid">
                            <?php foreach ($all_types as $type) : ?>
                                <?php
                                $tid = (int) $type->id;
                                $checked = in_array($tid, $current_resistance_ids, true);
                                $t_label = !empty($type->name_fr) ? $type->name_fr : (!empty($type->name_en) ? $type->name_en : $type->name ?? '#'.$tid);
                                ?>
                                <label class="pokehub-type-checkbox">
                                    <input type="checkbox" name="resistance_ids[]" value="<?php echo $tid; ?>" <?php checked($checked); ?> />
                                    <span><?php echo esc_html($t_label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Immune to (×0) -->
                <div class="admin-lab-form-group">
                    <label><?php esc_html_e('Immune to (×0)', 'poke-hub'); ?></label>
                    <?php if (!empty($all_types)) : ?>
                        <div class="pokehub-types-grid">
                            <?php foreach ($all_types as $type) : ?>
                                <?php
                                $tid = (int) $type->id;
                                $checked = in_array($tid, $current_immune_ids, true);
                                $t_label = !empty($type->name_fr) ? $type->name_fr : (!empty($type->name_en) ? $type->name_en : $type->name ?? '#'.$tid);
                                ?>
                                <label class="pokehub-type-checkbox">
                                    <input type="checkbox" name="immune_ids[]" value="<?php echo $tid; ?>" <?php checked($checked); ?> />
                                    <span><?php echo esc_html($t_label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section: Offensive Properties (When this type attacks) -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Offensive Properties (When Attacking)', 'poke-hub'); ?></h3>
                <p class="description" style="margin-top: 0;"><?php esc_html_e('How effective this type is when attacking other types.', 'poke-hub'); ?></p>
                
                <!-- Super effective (×2) -->
                <div class="admin-lab-form-group">
                    <label><?php esc_html_e('Super Effective against (×2)', 'poke-hub'); ?></label>
                    <?php if (!empty($all_types)) : ?>
                        <div class="pokehub-types-grid">
                            <?php foreach ($all_types as $type) : ?>
                                <?php
                                $tid = (int) $type->id;
                                $checked = in_array($tid, $current_offensive_super_effective_ids, true);
                                $t_label = !empty($type->name_fr) ? $type->name_fr : (!empty($type->name_en) ? $type->name_en : $type->name ?? '#'.$tid);
                                ?>
                                <label class="pokehub-type-checkbox">
                                    <input type="checkbox" name="offensive_super_effective_ids[]" value="<?php echo $tid; ?>" <?php checked($checked); ?> />
                                    <span><?php echo esc_html($t_label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Not very effective (×½) -->
                <div class="admin-lab-form-group">
                    <label><?php esc_html_e('Not Very Effective against (×½)', 'poke-hub'); ?></label>
                    <?php if (!empty($all_types)) : ?>
                        <div class="pokehub-types-grid">
                            <?php foreach ($all_types as $type) : ?>
                                <?php
                                $tid = (int) $type->id;
                                $checked = in_array($tid, $current_offensive_not_very_effective_ids, true);
                                $t_label = !empty($type->name_fr) ? $type->name_fr : (!empty($type->name_en) ? $type->name_en : $type->name ?? '#'.$tid);
                                ?>
                                <label class="pokehub-type-checkbox">
                                    <input type="checkbox" name="offensive_not_very_effective_ids[]" value="<?php echo $tid; ?>" <?php checked($checked); ?> />
                                    <span><?php echo esc_html($t_label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- No effect (×0) -->
                <div class="admin-lab-form-group">
                    <label><?php esc_html_e('No Effect against (×0)', 'poke-hub'); ?></label>
                    <?php if (!empty($all_types)) : ?>
                        <div class="pokehub-types-grid">
                            <?php foreach ($all_types as $type) : ?>
                                <?php
                                $tid = (int) $type->id;
                                $checked = in_array($tid, $current_offensive_no_effect_ids, true);
                                $t_label = !empty($type->name_fr) ? $type->name_fr : (!empty($type->name_en) ? $type->name_en : $type->name ?? '#'.$tid);
                                ?>
                                <label class="pokehub-type-checkbox">
                                    <input type="checkbox" name="offensive_no_effect_ids[]" value="<?php echo $tid; ?>" <?php checked($checked); ?> />
                                    <span><?php echo esc_html($t_label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section: Associated Weathers -->
            <?php if (!empty($all_weathers)) : ?>
                <div class="admin-lab-form-section">
                    <h3><?php esc_html_e('Associated Weathers', 'poke-hub'); ?></h3>
                    <div class="admin-lab-form-group">
                        <div class="pokehub-types-grid">
                            <?php foreach ($all_weathers as $w) : ?>
                                <?php
                                $wid = (int) $w->id;
                                $checked = in_array($wid, $current_weather_ids, true);
                                $w_label = !empty($w->name_fr) ? $w->name_fr : (!empty($w->name_en) ? $w->name_en : $w->name ?? $w->slug ?? '#'.$wid);
                                ?>
                                <label class="pokehub-type-checkbox">
                                    <input type="checkbox" name="weather_ids[]" value="<?php echo $wid; ?>" <?php checked($checked); ?> />
                                    <span><?php echo esc_html($w_label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

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
