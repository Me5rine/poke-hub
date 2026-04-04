<?php
/**
 * Administration du catalogue des types de bonus (table bonus_types).
 * Remplace l’ancien CPT : CRUD complet, aperçu des icônes depuis le bucket (réglages Sources).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return string URL de la page admin liste.
 */
function pokehub_bonus_types_admin_url(array $args = []): string {
    $base = add_query_arg('page', 'poke-hub-bonus-types', admin_url('admin.php'));
    return empty($args) ? $base : add_query_arg($args, $base);
}

/**
 * Sauvegarde / suppression (POST et GET sécurisé pour delete).
 */
function pokehub_bonus_types_admin_handle_requests(): void {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    if (function_exists('pokehub_bonus_use_remote_source') && pokehub_bonus_use_remote_source()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ($page !== 'poke-hub-bonus-types') {
        return;
    }

    $table = function_exists('pokehub_get_bonus_types_table') ? pokehub_get_bonus_types_table() : '';
    if ($table === '' || !function_exists('pokehub_table_exists') || !pokehub_table_exists($table)) {
        return;
    }

    global $wpdb;

    // Suppression
    if (!empty($_GET['action']) && sanitize_key(wp_unslash($_GET['action'])) === 'delete') {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0 || empty($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'pokehub_delete_bonus_type_' . $id)) {
            wp_safe_redirect(pokehub_bonus_types_admin_url(['ph_bonus_msg' => 'nonce']));
            exit;
        }
        $wpdb->delete($table, ['id' => $id], ['%d']);
        wp_safe_redirect(pokehub_bonus_types_admin_url(['ph_bonus_msg' => 'deleted']));
        exit;
    }

    if (empty($_POST['pokehub_bonus_types_action']) || empty($_POST['_wpnonce'])) {
        return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'pokehub_bonus_types_save')) {
        wp_safe_redirect(pokehub_bonus_types_admin_url(['ph_bonus_msg' => 'nonce']));
        exit;
    }

    $action = sanitize_key(wp_unslash($_POST['pokehub_bonus_types_action']));
    if ($action !== 'save') {
        return;
    }

    $id = isset($_POST['bonus_type_id']) ? (int) $_POST['bonus_type_id'] : 0;
    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $slug_raw = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
    if ($slug_raw === '' && $title !== '') {
        $slug_raw = sanitize_title($title);
    }
    $image_slug = isset($_POST['image_slug']) ? trim((string) wp_unslash($_POST['image_slug'])) : '';
    $image_slug = $image_slug !== '' ? sanitize_title($image_slug) : '';
    $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';
    $sort_order = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;

    if ($title === '' || $slug_raw === '') {
        wp_safe_redirect(pokehub_bonus_types_admin_url(['ph_bonus_msg' => 'missing', 'edit' => $id > 0 ? $id : 'new']));
        exit;
    }

    // Unicité du slug (hors ligne courante si édition)
    $dup = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE slug = %s AND id != %d LIMIT 1",
        $slug_raw,
        $id
    ));
    if ($dup > 0) {
        wp_safe_redirect(pokehub_bonus_types_admin_url(['ph_bonus_msg' => 'duplicate', 'edit' => $id > 0 ? $id : 'new']));
        exit;
    }

    $data = [
        'title'       => $title,
        'slug'        => $slug_raw,
        'description' => $description,
        'image_slug'  => $image_slug !== '' ? $image_slug : '',
        'sort_order'  => $sort_order,
    ];

    if ($id > 0) {
        $wpdb->update($table, $data, ['id' => $id], ['%s', '%s', '%s', '%s', '%d'], ['%d']);
        wp_safe_redirect(pokehub_bonus_types_admin_url(['ph_bonus_msg' => 'updated', 'edit' => $id]));
        exit;
    }

    $wpdb->insert($table, $data, ['%s', '%s', '%s', '%s', '%d']);
    $new_id = (int) $wpdb->insert_id;
    if ($new_id <= 0) {
        wp_safe_redirect(pokehub_bonus_types_admin_url(['ph_bonus_msg' => 'error']));
        exit;
    }
    wp_safe_redirect(pokehub_bonus_types_admin_url(['ph_bonus_msg' => 'created', 'edit' => $new_id]));
    exit;
}
add_action('admin_init', 'pokehub_bonus_types_admin_handle_requests');

/**
 * Rendu de la page Poké HUB > Bonus.
 */
function pokehub_render_bonus_types_admin_page(): void {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'poke-hub'));
    }

    if (function_exists('pokehub_bonus_use_remote_source') && pokehub_bonus_use_remote_source()) {
        echo '<div class="wrap"><p>' . esc_html__('Bonus types are managed on the main site (remote Pokémon prefix).', 'poke-hub') . '</p></div>';
        return;
    }

    $table = function_exists('pokehub_get_bonus_types_table') ? pokehub_get_bonus_types_table() : '';
    if ($table === '' || !function_exists('pokehub_table_exists') || !pokehub_table_exists($table)) {
        echo '<div class="wrap"><p>' . esc_html__('Bonus types table is not available.', 'poke-hub') . '</p></div>';
        return;
    }

    global $wpdb;

    $msg = isset($_GET['ph_bonus_msg']) ? sanitize_key(wp_unslash($_GET['ph_bonus_msg'])) : '';
    $notices = [
        'created'   => ['success', __('Bonus type saved.', 'poke-hub')],
        'updated'   => ['success', __('Bonus type updated.', 'poke-hub')],
        'deleted'   => ['success', __('Bonus type deleted.', 'poke-hub')],
        'missing'   => ['error', __('Title and slug are required.', 'poke-hub')],
        'duplicate' => ['error', __('This slug is already in use.', 'poke-hub')],
        'nonce'     => ['error', __('Security check failed.', 'poke-hub')],
        'error'     => ['error', __('Could not save.', 'poke-hub')],
    ];

    $edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
    $row = null;
    if ($edit_id > 0) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $edit_id));
    }
    if ($edit_id > 0 && (!$row || (int) $row->id !== $edit_id)) {
        $row = null;
        $edit_id = 0;
    }

    $is_new = ($edit_id === 0 && isset($_GET['edit']) && sanitize_key(wp_unslash($_GET['edit'])) === 'new') || ($msg === 'missing' && isset($_GET['edit']) && sanitize_key(wp_unslash($_GET['edit'])) === 'new');
    if ($msg === 'duplicate' && isset($_GET['edit'])) {
        $dup_edit = (int) $_GET['edit'];
        if ($dup_edit > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $dup_edit));
            $edit_id = $dup_edit;
        } else {
            $is_new = true;
        }
    }

    ?>
    <div class="wrap pokehub-bonus-types-admin">
        <h1 class="wp-heading-inline"><?php echo esc_html__('Bonus types', 'poke-hub'); ?></h1>
        <?php if (!$row && !$is_new) : ?>
            <a href="<?php echo esc_url(pokehub_bonus_types_admin_url(['edit' => 'new'])); ?>" class="page-title-action"><?php echo esc_html__('Add bonus type', 'poke-hub'); ?></a>
        <?php endif; ?>
        <hr class="wp-header-end" />

        <?php
        if ($msg && isset($notices[$msg])) {
            printf(
                '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                esc_attr($notices[$msg][0]),
                esc_html($notices[$msg][1])
            );
        }
        ?>

        <style>
            .pokehub-bonus-types-admin .column-icon { width: 64px; text-align: center; }
            .pokehub-bonus-types-admin .column-icon img { max-width: 48px; max-height: 48px; height: auto; vertical-align: middle; object-fit: contain; }
            .pokehub-bonus-types-admin .pokehub-bonus-type-form { max-width: 720px; margin-top: 1em; }
            .pokehub-bonus-types-admin .pokehub-bonus-type-form .description { color: #646970; }
            .pokehub-bonus-types-admin table.widefat td { vertical-align: middle; }
        </style>

        <?php if ($row || $is_new) : ?>
            <?php
            $f_title = $row ? (string) $row->title : '';
            $f_slug = $row ? (string) $row->slug : '';
            $f_image_slug = $row && $row->image_slug !== null && $row->image_slug !== '' ? (string) $row->image_slug : '';
            $f_description = $row ? (string) $row->description : '';
            $f_sort = $row ? (int) $row->sort_order : 0;
            $preview_slug = $f_image_slug !== '' ? $f_image_slug : $f_slug;
            ?>
            <h2><?php echo $edit_id > 0 ? esc_html__('Edit bonus type', 'poke-hub') : esc_html__('New bonus type', 'poke-hub'); ?></h2>
            <p><a href="<?php echo esc_url(pokehub_bonus_types_admin_url()); ?>">&larr; <?php echo esc_html__('Back to list', 'poke-hub'); ?></a></p>

            <form method="post" class="pokehub-bonus-type-form" action="<?php echo esc_url(pokehub_bonus_types_admin_url()); ?>">
                <?php wp_nonce_field('pokehub_bonus_types_save'); ?>
                <input type="hidden" name="pokehub_bonus_types_action" value="save" />
                <input type="hidden" name="bonus_type_id" value="<?php echo esc_attr((string) $edit_id); ?>" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="pokehub_bonus_title"><?php echo esc_html__('Title', 'poke-hub'); ?></label></th>
                        <td><input name="title" id="pokehub_bonus_title" type="text" class="regular-text" value="<?php echo esc_attr($f_title); ?>" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pokehub_bonus_slug"><?php echo esc_html__('Slug', 'poke-hub'); ?></label></th>
                        <td>
                            <input name="slug" id="pokehub_bonus_slug" type="text" class="regular-text" value="<?php echo esc_attr($f_slug); ?>" required />
                            <p class="description"><?php echo esc_html__('URL-safe identifier. Used as default image file name on the assets bucket if image slug is empty.', 'poke-hub'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pokehub_bonus_image_slug"><?php echo esc_html__('Image slug (optional)', 'poke-hub'); ?></label></th>
                        <td>
                            <input name="image_slug" id="pokehub_bonus_image_slug" type="text" class="regular-text" value="<?php echo esc_attr($f_image_slug); ?>" />
                            <p class="description"><?php echo esc_html__('Override file base name on the CDN (same path as in Settings > Sources). Leave empty to use the slug.', 'poke-hub'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Preview (bucket)', 'poke-hub'); ?></th>
                        <td>
                            <?php
                            if ($preview_slug !== '' && function_exists('poke_hub_render_bucket_raster_img')) {
                                echo wp_kses_post(poke_hub_render_bucket_raster_img('bonus', $preview_slug, [
                                    'alt'   => $f_title !== '' ? $f_title : $preview_slug,
                                    'class' => 'pokehub-bonus-type-preview-img',
                                    'width' => 64,
                                    'height'=> 64,
                                ]));
                            } else {
                                echo '<em>' . esc_html__('Save a slug to preview.', 'poke-hub') . '</em>';
                            }
                            if ($preview_slug !== '' && function_exists('poke_hub_get_raster_asset_url_chain')) {
                                $chain = poke_hub_get_raster_asset_url_chain('bonus', $preview_slug);
                                if ($chain !== []) {
                                    echo '<p class="description"><code>' . esc_html($chain[0]) . '</code></p>';
                                }
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pokehub_bonus_sort"><?php echo esc_html__('Sort order', 'poke-hub'); ?></label></th>
                        <td><input name="sort_order" id="pokehub_bonus_sort" type="number" class="small-text" value="<?php echo esc_attr((string) $f_sort); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pokehub_bonus_description"><?php echo esc_html__('Description', 'poke-hub'); ?></label></th>
                        <td>
                            <?php
                            wp_editor(
                                $f_description,
                                'pokehub_bonus_description',
                                [
                                    'textarea_name' => 'description',
                                    'textarea_rows' => 8,
                                    'media_buttons' => false,
                                    'teeny'         => true,
                                ]
                            );
                            ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button($edit_id > 0 ? __('Update', 'poke-hub') : __('Add bonus type', 'poke-hub')); ?>
            </form>
        <?php else : ?>
            <?php
            $items = $wpdb->get_results("SELECT * FROM {$table} ORDER BY sort_order ASC, title ASC");
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="column-icon"><?php echo esc_html__('Icon', 'poke-hub'); ?></th>
                        <th scope="col"><?php echo esc_html__('Title', 'poke-hub'); ?></th>
                        <th scope="col"><?php echo esc_html__('Slug', 'poke-hub'); ?></th>
                        <th scope="col"><?php echo esc_html__('Image slug', 'poke-hub'); ?></th>
                        <th scope="col"><?php echo esc_html__('Order', 'poke-hub'); ?></th>
                        <th scope="col"><?php echo esc_html__('Actions', 'poke-hub'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)) : ?>
                        <tr><td colspan="6"><?php echo esc_html__('No bonus types yet.', 'poke-hub'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($items as $item) : ?>
                            <?php
                            $img_slug = ($item->image_slug !== null && $item->image_slug !== '') ? (string) $item->image_slug : (string) $item->slug;
                            ?>
                            <tr>
                                <td class="column-icon">
                                    <?php
                                    if ($img_slug !== '' && function_exists('poke_hub_render_bucket_raster_img')) {
                                        echo wp_kses_post(poke_hub_render_bucket_raster_img('bonus', $img_slug, [
                                            'alt'   => (string) $item->title,
                                            'class' => 'pokehub-bonus-type-list-icon',
                                            'width' => 48,
                                            'height'=> 48,
                                        ]));
                                    } else {
                                        echo '&mdash;';
                                    }
                                    ?>
                                </td>
                                <td><strong><?php echo esc_html((string) $item->title); ?></strong></td>
                                <td><code><?php echo esc_html((string) $item->slug); ?></code></td>
                                <td><?php echo $item->image_slug !== null && $item->image_slug !== '' ? '<code>' . esc_html((string) $item->image_slug) . '</code>' : '&mdash;'; ?></td>
                                <td><?php echo esc_html((string) (int) $item->sort_order); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(pokehub_bonus_types_admin_url(['edit' => (int) $item->id])); ?>"><?php echo esc_html__('Edit', 'poke-hub'); ?></a>
                                    |
                                    <?php
                                    $del_url = wp_nonce_url(
                                        pokehub_bonus_types_admin_url(['action' => 'delete', 'id' => (int) $item->id]),
                                        'pokehub_delete_bonus_type_' . (int) $item->id
                                    );
                                    ?>
                                    <a href="<?php echo esc_url($del_url); ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js(__('Delete this bonus type?', 'poke-hub')); ?>');"><?php echo esc_html__('Delete', 'poke-hub'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
