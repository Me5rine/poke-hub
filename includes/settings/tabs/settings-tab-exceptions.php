<?php
// File: /includes/settings/tabs/settings-tab-exceptions.php

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    return;
}

if (!function_exists('pokehub_settings_exceptions_get_categories')) {
    echo '<div class="notice notice-error"><p>'
        . esc_html__('Settings Exceptions helper is not loaded.', 'poke-hub')
        . '</p></div>';
    return;
}

// Création paresseuse / seed si la table n'existe pas encore (cas où on arrive
// ici avant le passage admin_init principal).
if (function_exists('pokehub_settings_exceptions_bootstrap')) {
    pokehub_settings_exceptions_bootstrap();
}

$categories      = pokehub_settings_exceptions_get_categories();
$current_notice  = isset($_GET['poke_hub_notice']) ? sanitize_key((string) $_GET['poke_hub_notice']) : '';
$notice_messages = [
    'exception_added'         => ['type' => 'success', 'msg' => __('Exception added.', 'poke-hub')],
    'exception_skipped'       => ['type' => 'warning', 'msg' => __('Exception not added (already exists or invalid input).', 'poke-hub')],
    'exception_deleted'       => ['type' => 'success', 'msg' => __('Exception deleted.', 'poke-hub')],
    'exception_delete_failed' => ['type' => 'error',   'msg' => __('Could not delete this exception.', 'poke-hub')],
];

if (isset($notice_messages[$current_notice])) {
    echo '<div class="notice notice-' . esc_attr($notice_messages[$current_notice]['type']) . ' is-dismissible"><p>'
        . esc_html($notice_messages[$current_notice]['msg'])
        . '</p></div>';
}

?>
<style>
    .poke-hub-exceptions-category {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 24px 32px;
        align-items: start;
        margin-bottom: 2.5rem;
        padding-bottom: 2rem;
        border-bottom: 1px solid #c3c4c7;
    }
    .poke-hub-exceptions-category:last-of-type {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    .poke-hub-exceptions-category > div {
        min-width: 0;
    }
    .poke-hub-exceptions-add {
        background: #f6f7f7;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        padding: 16px;
        box-sizing: border-box;
    }
    .poke-hub-exceptions-add h3 {
        margin-top: 0;
        padding-top: 0;
    }
    .poke-hub-exceptions-add .form-table {
        margin-top: 0;
    }
    .poke-hub-exceptions-add .form-table th,
    .poke-hub-exceptions-add .form-table td {
        display: block;
        width: 100%;
        padding: 6px 0;
        box-sizing: border-box;
    }
    .poke-hub-exceptions-add .form-table th {
        padding-top: 12px;
    }
    .poke-hub-exceptions-add input[type="text"] {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }
    .poke-hub-exceptions-list table.widefat {
        margin-top: 0;
    }
    @media screen and (max-width: 1100px) {
        .poke-hub-exceptions-category {
            grid-template-columns: 1fr;
        }
    }
</style>

<p class="description">
    <?php esc_html_e('Special cases that used to live in plugin code. Each category controls a specific behavior; entries are stored in a dedicated table (not in wp_options) so they can be edited without code changes.', 'poke-hub'); ?>
</p>

<?php foreach ($categories as $category_slug => $meta): ?>
    <?php $entries = pokehub_settings_exceptions_get_entries($category_slug); ?>
    <h2><?php echo esc_html($meta['label']); ?></h2>
    <p class="description"><?php echo esc_html($meta['description']); ?></p>

    <div class="poke-hub-exceptions-category">
        <div class="poke-hub-exceptions-add">
            <h3><?php esc_html_e('Add exception', 'poke-hub'); ?></h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('poke_hub_settings_exceptions_add', 'poke_hub_settings_exceptions_nonce'); ?>
                <input type="hidden" name="action" value="poke_hub_settings_exceptions_add" />
                <input type="hidden" name="category" value="<?php echo esc_attr($category_slug); ?>" />

                <table class="form-table" role="presentation" style="margin-top:0;">
                    <tr>
                        <th scope="row">
                            <label for="slug_en_<?php echo esc_attr($category_slug); ?>">
                                <?php esc_html_e('English slug', 'poke-hub'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="slug_en_<?php echo esc_attr($category_slug); ?>"
                                   name="slug_en"
                                   class="regular-text"
                                   style="max-width:100%;"
                                   placeholder="<?php echo esc_attr($meta['example']); ?>"
                                   required />
                            <p class="description" style="margin-bottom:0;">
                                <?php
                                printf(
                                    /* translators: 1: example slug, 2: example PROTO */
                                    esc_html__('Lowercase, dashes only (e.g. %1$s). Becomes PROTO %2$s for import.', 'poke-hub'),
                                    '<code>' . esc_html($meta['example']) . '</code>',
                                    '<code>' . esc_html(pokehub_settings_exceptions_slug_to_proto($meta['example'])) . '</code>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="note_<?php echo esc_attr($category_slug); ?>">
                                <?php esc_html_e('Note (optional)', 'poke-hub'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="note_<?php echo esc_attr($category_slug); ?>"
                                   name="note"
                                   class="regular-text"
                                   style="max-width:100%;"
                                   maxlength="255" />
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Add', 'poke-hub'), 'primary', 'submit', false); ?>
            </form>
        </div>

        <div class="poke-hub-exceptions-list">
            <h3 class="screen-reader-text"><?php esc_html_e('Current entries', 'poke-hub'); ?></h3>
            <table class="widefat striped" style="width:100%;">
                <thead>
                    <tr>
                        <th scope="col" style="width:22%;"><?php esc_html_e('English slug', 'poke-hub'); ?></th>
                        <th scope="col" style="width:22%;"><?php esc_html_e('Game Master proto', 'poke-hub'); ?></th>
                        <th scope="col"><?php esc_html_e('Note', 'poke-hub'); ?></th>
                        <th scope="col" style="width:88px;"><?php esc_html_e('Source', 'poke-hub'); ?></th>
                        <th scope="col" style="width:88px;"><?php esc_html_e('Actions', 'poke-hub'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($entries === []): ?>
                        <tr>
                            <td colspan="5"><em><?php esc_html_e('No entries yet for this category.', 'poke-hub'); ?></em></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entries as $row): ?>
                            <tr>
                                <td><code><?php echo esc_html($row['slug_en']); ?></code></td>
                                <td><code><?php echo esc_html(pokehub_settings_exceptions_slug_to_proto($row['slug_en'])); ?></code></td>
                                <td><?php echo esc_html($row['note']); ?></td>
                                <td>
                                    <?php if ($row['is_seed']): ?>
                                        <span class="badge" style="background:#e0e7ff;border-radius:3px;padding:2px 6px;font-size:11px;">
                                            <?php esc_html_e('seed', 'poke-hub'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#e9fbe6;border-radius:3px;padding:2px 6px;font-size:11px;">
                                            <?php esc_html_e('manual', 'poke-hub'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post"
                                          action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                          onsubmit="return confirm('<?php echo esc_js(__('Delete this exception?', 'poke-hub')); ?>');"
                                          style="margin:0;">
                                        <?php wp_nonce_field('poke_hub_settings_exceptions_delete', 'poke_hub_settings_exceptions_nonce'); ?>
                                        <input type="hidden" name="action" value="poke_hub_settings_exceptions_delete" />
                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>" />
                                        <input type="hidden" name="category" value="<?php echo esc_attr($row['category']); ?>" />
                                        <button type="submit" class="button-link delete" style="color:#b32d2e;">
                                            <?php esc_html_e('Delete', 'poke-hub'); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endforeach; ?>
