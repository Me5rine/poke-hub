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
    $title_en = isset($_POST['title_en']) ? sanitize_text_field(wp_unslash($_POST['title_en'])) : '';
    $title_fr = isset($_POST['title_fr']) ? sanitize_text_field(wp_unslash($_POST['title_fr'])) : '';
    $slug_manual = isset($_POST['slug_manual']) && (string) wp_unslash($_POST['slug_manual']) === '1';
    $slug_raw = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
    if (!$slug_manual || $slug_raw === '') {
        $slug_raw = sanitize_title($title_en);
    }
    $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';

    if ($title_en === '' || $title_fr === '' || $slug_raw === '') {
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
        'title_en'      => $title_en,
        'title_fr'      => $title_fr,
        'slug'          => $slug_raw,
        'description'   => $description,
    ];

    if ($id > 0) {
        $wpdb->update($table, $data, ['id' => $id], ['%s', '%s', '%s', '%s'], ['%d']);
        wp_safe_redirect(pokehub_bonus_types_admin_url(['ph_bonus_msg' => 'updated']));
        exit;
    }

    $wpdb->insert($table, $data, ['%s', '%s', '%s', '%s']);
    $new_id = (int) $wpdb->insert_id;
    if ($new_id <= 0) {
        wp_safe_redirect(pokehub_bonus_types_admin_url(['ph_bonus_msg' => 'error']));
        exit;
    }
    wp_safe_redirect(pokehub_bonus_types_admin_url(['ph_bonus_msg' => 'created']));
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
        'missing'   => ['error', __('English name, French name and slug are required.', 'poke-hub')],
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
            .pokehub-bonus-types-admin .column-icon img,
            .pokehub-bonus-types-admin .column-icon .pokehub-bonus-icon-wrap { max-width: 48px; max-height: 48px; vertical-align: middle; }
            .pokehub-bonus-types-admin .column-icon img { height: auto; object-fit: contain; }
            .pokehub-bonus-types-admin .column-icon .pokehub-bonus-icon-wrap .pokehub-bonus-icon--svg svg { max-width: 48px; max-height: 48px; width: auto; height: auto; }
            .pokehub-bonus-types-admin .pokehub-bonus-type-form { max-width: 720px; margin-top: 1em; }
            .pokehub-bonus-types-admin .pokehub-bonus-type-form .description { color: #646970; }
            .pokehub-bonus-types-admin table.widefat td { vertical-align: middle; }
        </style>

        <?php if ($row || $is_new) : ?>
            <?php
            $f_title_en = $row ? (string) ($row->title_en ?? '') : '';
            $f_title_fr = $row ? (string) ($row->title_fr ?? '') : '';
            $f_slug = $row ? (string) $row->slug : '';
            $f_description = $row ? (string) $row->description : '';
            $preview_slug = $f_slug;
            $preview_alt = $f_title_fr !== '' ? $f_title_fr : ($f_title_en !== '' ? $f_title_en : $preview_slug);
            ?>
            <?php poke_hub_admin_back_to_list_bar(pokehub_bonus_types_admin_url()); ?>
            <h2><?php echo $edit_id > 0 ? esc_html__('Edit bonus type', 'poke-hub') : esc_html__('New bonus type', 'poke-hub'); ?></h2>

            <form method="post" class="pokehub-bonus-type-form" action="<?php echo esc_url(pokehub_bonus_types_admin_url()); ?>">
                <?php wp_nonce_field('pokehub_bonus_types_save'); ?>
                <input type="hidden" name="pokehub_bonus_types_action" value="save" />
                <input type="hidden" name="bonus_type_id" value="<?php echo esc_attr((string) $edit_id); ?>" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="pokehub_bonus_title_en"><?php echo esc_html__('Name (English)', 'poke-hub'); ?></label></th>
                        <td>
                            <input name="title_en" id="pokehub_bonus_title_en" type="text" class="regular-text" value="<?php echo esc_attr($f_title_en); ?>" required autocomplete="off" />
                            <p class="description"><?php echo esc_html__('Used to build the default slug (URL-safe). You can still edit the slug below.', 'poke-hub'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pokehub_bonus_title_fr"><?php echo esc_html__('Name (French)', 'poke-hub'); ?></label></th>
                        <td><input name="title_fr" id="pokehub_bonus_title_fr" type="text" class="regular-text" value="<?php echo esc_attr($f_title_fr); ?>" required autocomplete="off" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pokehub_bonus_slug"><?php echo esc_html__('Slug', 'poke-hub'); ?></label></th>
                        <td>
                            <input type="hidden" name="slug_manual" id="pokehub_bonus_slug_manual" value="<?php echo $edit_id > 0 ? '1' : '0'; ?>" />
                            <input name="slug" id="pokehub_bonus_slug" type="text" class="regular-text" value="<?php echo esc_attr($f_slug); ?>" required autocomplete="off" />
                            <p class="description"><?php echo esc_html__('Generated from the English name for new bonuses; you can change it manually. Same value is used as the image file base name on the assets bucket (see Settings > Sources).', 'poke-hub'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Preview (bucket)', 'poke-hub'); ?></th>
                        <td>
                            <?php
                            if ($preview_slug !== '' && function_exists('poke_hub_render_bonus_asset_markup')) {
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup contrôlé (SVG sanitisé ou img raster)
                                echo poke_hub_render_bonus_asset_markup($preview_slug, [
                                    'alt'       => $preview_alt,
                                    'icon_size' => 64,
                                    'class'     => 'pokehub-bonus-type-preview',
                                ]);
                            } else {
                                echo '<em>' . esc_html__('Enter a slug to preview the asset from the bucket.', 'poke-hub') . '</em>';
                            }
                            if ($preview_slug !== '' && function_exists('poke_hub_get_asset_url')) {
                                $svg_preview = poke_hub_get_asset_url('bonus', $preview_slug, 'svg');
                                if ($svg_preview !== '') {
                                    echo '<p class="description"><strong>SVG</strong> <code>' . esc_html($svg_preview) . '</code></p>';
                                }
                            }
                            if ($preview_slug !== '' && function_exists('poke_hub_get_raster_asset_url_chain')) {
                                $chain = poke_hub_get_raster_asset_url_chain('bonus', $preview_slug);
                                if ($chain !== []) {
                                    echo '<p class="description"><strong>Raster</strong> <code>' . esc_html($chain[0]) . '</code></p>';
                                }
                            }
                            ?>
                        </td>
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
            <?php if ($edit_id === 0) : ?>
                <script>
                (function () {
                    var en = document.getElementById('pokehub_bonus_title_en');
                    var slug = document.getElementById('pokehub_bonus_slug');
                    var manual = document.getElementById('pokehub_bonus_slug_manual');
                    if (!en || !slug || !manual) { return; }
                    function slugify(str) {
                        if (!str) { return ''; }
                        try {
                            str = str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                        } catch (e) { /* ignore */ }
                        return str.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
                    }
                    en.addEventListener('input', function () {
                        if (manual.value === '1') { return; }
                        slug.value = slugify(en.value);
                    });
                    slug.addEventListener('input', function () { manual.value = '1'; });
                })();
                </script>
            <?php endif; ?>
        <?php else : ?>
            <?php
            $items = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC");
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="column-icon"><?php echo esc_html__('Icon', 'poke-hub'); ?></th>
                        <th scope="col"><?php echo esc_html__('Name (French)', 'poke-hub'); ?></th>
                        <th scope="col"><?php echo esc_html__('Name (English)', 'poke-hub'); ?></th>
                        <th scope="col"><?php echo esc_html__('Slug', 'poke-hub'); ?></th>
                        <th scope="col"><?php echo esc_html__('Actions', 'poke-hub'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)) : ?>
                        <tr><td colspan="5"><?php echo esc_html__('No bonus types yet.', 'poke-hub'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($items as $item) : ?>
                            <?php
                            $img_slug = (string) $item->slug;
                            $list_title_fr = isset($item->title_fr) ? (string) $item->title_fr : '';
                            $list_title_en = isset($item->title_en) ? (string) $item->title_en : '';
                            $list_alt = $list_title_fr !== '' ? $list_title_fr : $list_title_en;
                            ?>
                            <tr>
                                <td class="column-icon">
                                    <?php
                                    if ($img_slug !== '' && function_exists('poke_hub_render_bonus_asset_markup')) {
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo poke_hub_render_bonus_asset_markup($img_slug, [
                                            'alt'       => $list_alt,
                                            'icon_size' => 48,
                                            'class'     => 'pokehub-bonus-type-list-icon',
                                        ]);
                                    } else {
                                        echo '&mdash;';
                                    }
                                    ?>
                                </td>
                                <td><strong><?php echo esc_html($list_title_fr); ?></strong></td>
                                <td><?php echo esc_html($list_title_en); ?></td>
                                <td><code><?php echo esc_html((string) $item->slug); ?></code></td>
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
