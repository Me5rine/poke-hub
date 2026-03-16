<?php
// modules/events/admin/events-quest-groups-admin.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Les catégories de quêtes sont gérées uniquement par le module Quêtes (Poké HUB > Quêtes > Catégories de quêtes).
 * Le module Events n’enregistre plus de sous-menu Quest Groups.
 */
function pokehub_add_quest_groups_admin_page() {
    // Désactivé : plus de sous-menu Quest Groups dans Events.
}

/**
 * Rendu de la page : liste + formulaire add/edit
 */
function pokehub_render_quest_groups_admin_page() {
    if (!current_user_can('manage_options') || !function_exists('pokehub_get_quest_groups')) {
        return;
    }
    $action = isset($_GET['ph_action']) ? sanitize_key($_GET['ph_action']) : 'list';
    $edit_id = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;

    if ($action === 'delete' && $edit_id > 0 && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'pokehub_delete_quest_group_' . $edit_id)) {
        pokehub_delete_quest_group($edit_id);
        wp_safe_redirect(admin_url('admin.php?page=poke-hub-quest-groups&deleted=1'));
        exit;
    }

    if ($action === 'edit' && $edit_id > 0) {
        $group = pokehub_get_quest_group($edit_id);
        if (!$group) {
            $action = 'list';
            $edit_id = 0;
        }
    }

    $groups = pokehub_get_quest_groups();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Quest Groups', 'poke-hub'); ?></h1>
        <p class="description"><?php esc_html_e('Categories for the Research page (e.g. Catching Tasks, Throwing Tasks). Used as section headers with optional color.', 'poke-hub'); ?></p>

        <?php if (isset($_GET['deleted'])) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Group deleted.', 'poke-hub'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['added'])) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Group added.', 'poke-hub'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['updated'])) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Group updated.', 'poke-hub'); ?></p></div>
        <?php endif; ?>

        <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
            <div style="min-width: 280px;">
                <h2><?php echo $edit_id > 0 ? esc_html__('Edit group', 'poke-hub') : esc_html__('Add group', 'poke-hub'); ?></h2>
                <?php pokehub_render_quest_group_form($edit_id > 0 ? $group : null); ?>
            </div>
            <div style="flex: 1;">
                <h2><?php esc_html_e('Groups', 'poke-hub'); ?></h2>
                <?php if (empty($groups)) : ?>
                    <p><?php esc_html_e('No groups yet. Add one above.', 'poke-hub'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Title (FR)', 'poke-hub'); ?></th>
                                <th><?php esc_html_e('Title (EN)', 'poke-hub'); ?></th>
                                <th><?php esc_html_e('Color', 'poke-hub'); ?></th>
                                <th><?php esc_html_e('Order', 'poke-hub'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $g) : ?>
                                <tr>
                                    <td><?php echo esc_html($g->title_fr); ?></td>
                                    <td><?php echo esc_html($g->title_en); ?></td>
                                    <td>
                                        <?php if (!empty($g->color)) : ?>
                                            <span style="display:inline-block;width:20px;height:20px;background:<?php echo esc_attr($g->color); ?>;border:1px solid #ccc;vertical-align:middle;"></span>
                                            <?php echo esc_html($g->color); ?>
                                        <?php else : ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (int) $g->sort_order; ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=poke-hub-quest-groups&ph_action=edit&id=' . (int) $g->id)); ?>"><?php esc_html_e('Edit', 'poke-hub'); ?></a>
                                        |
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=poke-hub-quest-groups&ph_action=delete&id=' . (int) $g->id), 'pokehub_delete_quest_group_' . (int) $g->id)); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this group?', 'poke-hub')); ?>');"><?php esc_html_e('Delete', 'poke-hub'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Formulaire add/edit groupe
 */
function pokehub_render_quest_group_form($group = null) {
    $is_edit = $group && isset($group->id);
    $title_en = $is_edit ? $group->title_en : '';
    $title_fr = $is_edit ? $group->title_fr : '';
    $color = $is_edit ? $group->color : '';
    $sort_order = $is_edit ? (int) $group->sort_order : 0;
    $action_url = admin_url('admin.php?page=poke-hub-quest-groups');
    if ($is_edit) {
        $action_url = add_query_arg(['ph_action' => 'edit', 'id' => (int) $group->id], $action_url);
    }
    ?>
    <form method="post" action="<?php echo esc_url($action_url); ?>">
        <?php wp_nonce_field('pokehub_save_quest_group'); ?>
        <input type="hidden" name="pokehub_quest_group_action" value="<?php echo $is_edit ? 'update' : 'add'; ?>" />
        <?php if ($is_edit) : ?>
            <input type="hidden" name="id" value="<?php echo (int) $group->id; ?>" />
        <?php endif; ?>
        <table class="form-table">
            <tr>
                <th><label for="title_en"><?php esc_html_e('Title (EN)', 'poke-hub'); ?></label></th>
                <td><input type="text" id="title_en" name="title_en" value="<?php echo esc_attr($title_en); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="title_fr"><?php esc_html_e('Title (FR)', 'poke-hub'); ?></label></th>
                <td><input type="text" id="title_fr" name="title_fr" value="<?php echo esc_attr($title_fr); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="color"><?php esc_html_e('Color', 'poke-hub'); ?></label></th>
                <td>
                    <input type="text" id="color" name="color" value="<?php echo esc_attr($color); ?>" class="small-text" placeholder="#hex" />
                    <p class="description"><?php esc_html_e('Optional. Hex color for the section header (e.g. #3498db).', 'poke-hub'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="sort_order"><?php esc_html_e('Order', 'poke-hub'); ?></label></th>
                <td><input type="number" id="sort_order" name="sort_order" value="<?php echo esc_attr($sort_order); ?>" min="0" /></td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" class="button button-primary"><?php echo $is_edit ? esc_html__('Update', 'poke-hub') : esc_html__('Add group', 'poke-hub'); ?></button>
        </p>
    </form>
    <?php
}

/**
 * Traitement du formulaire
 */
function pokehub_handle_quest_group_form() {
    if (!isset($_POST['pokehub_quest_group_action']) || !current_user_can('manage_options') || !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'pokehub_save_quest_group')) {
        return;
    }
    $action = sanitize_key($_POST['pokehub_quest_group_action']);
    $id = isset($_POST['id']) ? max(0, (int) $_POST['id']) : 0;
    $data = [
        'title_en'   => isset($_POST['title_en']) ? sanitize_text_field(wp_unslash($_POST['title_en'])) : '',
        'title_fr'   => isset($_POST['title_fr']) ? sanitize_text_field(wp_unslash($_POST['title_fr'])) : '',
        'color'      => isset($_POST['color']) ? sanitize_hex_color(wp_unslash($_POST['color'])) : null,
        'sort_order' => isset($_POST['sort_order']) ? max(0, (int) $_POST['sort_order']) : 0,
    ];
    if ($action === 'update' && $id > 0) {
        pokehub_save_quest_group($data, $id);
        wp_safe_redirect(admin_url('admin.php?page=poke-hub-quest-groups&ph_action=edit&id=' . $id . '&updated=1'));
    } else {
        pokehub_save_quest_group($data, 0);
        wp_safe_redirect(admin_url('admin.php?page=poke-hub-quest-groups&added=1'));
    }
    exit;
}
add_action('admin_init', 'pokehub_handle_quest_group_form');
