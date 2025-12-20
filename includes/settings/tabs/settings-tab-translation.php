<?php
// File: /includes/settings/tabs/settings-tab-translation.php

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    return;
}

// Charger les fonctions nécessaires
if (!function_exists('poke_hub_get_all_missing_translations')) {
    $helpers_file = POKE_HUB_MODULES_DIR . 'pokemon/includes/pokemon-translation-helpers.php';
    if (file_exists($helpers_file)) {
        require_once $helpers_file;
    }
}

if (!function_exists('poke_hub_pokemon_fetch_official_names_existing')) {
    $fetcher_file = POKE_HUB_MODULES_DIR . 'pokemon/includes/pokemon-official-names-fetcher.php';
    if (file_exists($fetcher_file)) {
        require_once $fetcher_file;
    }
}

$messages = [];
$selected_lang = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : 'fr';
$selected_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'pokemon';

$allowed_langs = ['fr', 'de', 'it', 'es', 'ja', 'ko'];
$allowed_types = ['pokemon', 'attacks', 'types'];

if (!empty($selected_lang) && !in_array($selected_lang, $allowed_langs, true)) {
    $selected_lang = 'fr';
}
if (!empty($selected_type) && !in_array($selected_type, $allowed_types, true)) {
    $selected_type = 'pokemon';
}

$lang_names = [
    'fr' => __('French', 'poke-hub'),
    'de' => __('German', 'poke-hub'),
    'it' => __('Italian', 'poke-hub'),
    'es' => __('Spanish', 'poke-hub'),
    'ja' => __('Japanese', 'poke-hub'),
    'ko' => __('Korean', 'poke-hub'),
];

// Traitement de la sauvegarde des traductions
if (!empty($_POST['poke_hub_save_translations'])) {
    check_admin_referer('poke_hub_translation_settings', 'poke_hub_translation_nonce');

    $translations = isset($_POST['translations']) && is_array($_POST['translations']) ? $_POST['translations'] : [];
    $saved_count = 0;
    $skipped_count = 0;

    if (!empty($translations)) {
        global $wpdb;

        foreach ($translations as $item_id => $data) {
            if (!is_array($data) || empty($data['type']) || empty($data['lang'])) {
                continue;
            }

            $item_id = (int) $item_id;
            $type = sanitize_text_field($data['type']);
            $lang = sanitize_text_field($data['lang']);
            $translation = isset($data['translation']) ? trim(sanitize_text_field($data['translation'])) : '';

            if ($translation === '') {
                $skipped_count++;
                continue;
            }

            if (!in_array($type, $allowed_types, true) || !in_array($lang, $allowed_langs, true) || $item_id <= 0) {
                continue;
            }

            // Déterminer la table selon le type
            $table = '';
            if ($type === 'pokemon') {
                $table = pokehub_get_table('pokemon');
            } elseif ($type === 'attacks') {
                $table = pokehub_get_table('attacks');
            } elseif ($type === 'types') {
                $table = pokehub_get_table('pokemon_types');
            }
            if (empty($table)) {
                continue;
            }

            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT id, name_en, name_fr, extra FROM {$table} WHERE id = %d", $item_id)
            );
            if (!$row) {
                continue;
            }

            $extra = [];
            if (!empty($row->extra)) {
                $decoded = json_decode($row->extra, true);
                if (is_array($decoded)) {
                    $extra = $decoded;
                }
            }
            if (!isset($extra['names']) || !is_array($extra['names'])) {
                $extra['names'] = [];
            }

            $extra['names'][$lang] = $translation;

            $update_data = [
                'extra' => wp_json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
            if ($lang === 'fr') {
                $update_data['name_fr'] = $translation;
            }

            $format = ['%s'];
            if (isset($update_data['name_fr'])) {
                $format[] = '%s';
            }

            $result = $wpdb->update(
                $table,
                $update_data,
                ['id' => $item_id],
                $format,
                ['%d']
            );

            if ($result !== false) {
                $saved_count++;
            } else {
                $skipped_count++;
            }
        }

        if ($saved_count > 0) {
            $messages[] = [
                'type' => 'success',
                'text' => sprintf(__(' %d translation(s) saved successfully.', 'poke-hub'), $saved_count),
            ];
        } elseif ($skipped_count > 0) {
            $messages[] = [
                'type' => 'info',
                'text' => __('No translations were saved. Please enter translations in the form fields.', 'poke-hub'),
            ];
        }
    }
}

// Traitement de la récupération en masse depuis Bulbapedia
if (!empty($_POST['poke_hub_fetch_missing_translations'])) {
    check_admin_referer('poke_hub_translation_settings', 'poke_hub_translation_nonce');

    $fetch_type = isset($_POST['fetch_type']) ? sanitize_text_field($_POST['fetch_type']) : '';

    $limit_raw = isset($_POST['fetch_limit']) ? trim((string) $_POST['fetch_limit']) : '';
    $limit = is_numeric($limit_raw) ? (int) $limit_raw : 0;

    if ($limit <= 0) {
        $limit = 5;
    }

    $force = isset($_POST['fetch_force']) && $_POST['fetch_force'] === '1';

    if (in_array($fetch_type, $allowed_types, true)) {
        $result = [];

        if ($fetch_type === 'pokemon' && function_exists('poke_hub_pokemon_fetch_official_names_existing')) {
            $result = poke_hub_pokemon_fetch_official_names_existing($limit, $force);
        } elseif ($fetch_type === 'attacks' && function_exists('poke_hub_attacks_fetch_existing_official_names')) {
            $result = poke_hub_attacks_fetch_existing_official_names($limit, $force);
        } elseif ($fetch_type === 'types' && function_exists('poke_hub_types_fetch_existing_official_names')) {
            $result = poke_hub_types_fetch_existing_official_names($limit, $force);
        }

        if (!empty($result)) {
            $messages[] = [
                'type' => 'success',
                'text' => sprintf(
                    __('Fetched translations: %d updated, %d skipped, %d errors out of %d items. (Limit requested: %d)', 'poke-hub'),
                    $result['updated'] ?? 0,
                    $result['skipped'] ?? 0,
                    $result['errors'] ?? 0,
                    $result['total'] ?? 0,
                    $limit
                ),
            ];
        }
    }
}

if (function_exists('poke_hub_trlog_write')) {
    poke_hub_trlog_write('settings_tab: bulk fetch request', [
        'fetch_type' => $fetch_type ?? null,
        'limit_raw' => $limit_raw ?? null,
        'limit' => $limit ?? null,
        'force' => $force ?? null,
        'user' => get_current_user_id(),
    ]);
}

// Récupérer les traductions manquantes
$missing_items = [];
if (function_exists('poke_hub_get_all_missing_translations')) {
    $filters = ['lang' => $selected_lang];
    $all_missing = poke_hub_get_all_missing_translations($filters);

    if (isset($all_missing[$selected_type][$selected_lang])) {
        $missing_items = $all_missing[$selected_type][$selected_lang];
    }
}

// Stats
$stats = [];
foreach ($allowed_langs as $lang) {
    $stats[$lang] = ['pokemon' => 0, 'attacks' => 0, 'types' => 0];
}

$all_missing_for_stats = [];
if (function_exists('poke_hub_get_all_missing_translations')) {
    $all_missing_for_stats = poke_hub_get_all_missing_translations([]);
    foreach ($all_missing_for_stats as $type => $by_lang) {
        foreach ($by_lang as $lang => $items) {
            if (isset($stats[$lang][$type])) {
                $stats[$lang][$type] = count($items);
            }
        }
    }
}

// Affichage des messages
foreach ($messages as $msg) {
    $class = 'notice';
    if ('success' === $msg['type']) {
        $class .= ' notice-success';
    } elseif ('error' === $msg['type']) {
        $class .= ' notice-error';
    } else {
        $class .= ' notice-info';
    }

    printf(
        '<div class="%1$s is-dismissible"><p>%2$s</p></div>',
        esc_attr($class),
        esc_html($msg['text'])
    );
}
?>

<h2><?php esc_html_e('Translation Management', 'poke-hub'); ?></h2>
<p><?php esc_html_e('Manage missing translations for Pokémon, attacks, and types. Translations are automatically fetched from Bulbapedia when adding or editing items.', 'poke-hub'); ?></p>

<div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-top: 20px;">
    <h3><?php esc_html_e('Missing Translations Statistics', 'poke-hub'); ?></h3>

    <table class="widefat" style="margin-top: 15px;">
        <thead>
            <tr>
                <th><?php esc_html_e('Language', 'poke-hub'); ?></th>
                <th><?php esc_html_e('Pokémon', 'poke-hub'); ?></th>
                <th><?php esc_html_e('Attacks', 'poke-hub'); ?></th>
                <th><?php esc_html_e('Types', 'poke-hub'); ?></th>
                <th><?php esc_html_e('Total', 'poke-hub'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allowed_langs as $lang): ?>
                <?php
                $total = $stats[$lang]['pokemon'] + $stats[$lang]['attacks'] + $stats[$lang]['types'];
                $row_attr = $total > 0 ? '' : 'style="opacity: 0.5;"';
                ?>
                <tr <?php echo $row_attr; ?>>
                    <td><strong><?php echo esc_html($lang_names[$lang] ?? strtoupper($lang)); ?></strong></td>
                    <td><?php echo (int) $stats[$lang]['pokemon']; ?></td>
                    <td><?php echo (int) $stats[$lang]['attacks']; ?></td>
                    <td><?php echo (int) $stats[$lang]['types']; ?></td>
                    <td><strong><?php echo (int) $total; ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-top: 20px;">
    <h3><?php esc_html_e('Bulk Fetch Missing Translations', 'poke-hub'); ?></h3>
    <p><?php esc_html_e('Fetch missing translations from Bulbapedia in bulk. This will update the database with official names.', 'poke-hub'); ?></p>

    <form method="post" action="" style="margin-top: 15px;">
        <?php wp_nonce_field('poke_hub_translation_settings', 'poke_hub_translation_nonce'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="fetch_type"><?php esc_html_e('Type', 'poke-hub'); ?></label></th>
                <td>
                    <select id="fetch_type" name="fetch_type" required>
                        <option value=""><?php esc_html_e('Select...', 'poke-hub'); ?></option>
                        <option value="pokemon"><?php esc_html_e('Pokémon', 'poke-hub'); ?></option>
                        <option value="attacks"><?php esc_html_e('Attacks', 'poke-hub'); ?></option>
                        <option value="types"><?php esc_html_e('Types', 'poke-hub'); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="fetch_limit"><?php esc_html_e('Limit', 'poke-hub'); ?></label></th>
                <td>
                    <input type="number" id="fetch_limit" name="fetch_limit" value="5" min="1" max="100" class="small-text" />
                    <p class="description">
                        <?php esc_html_e('Number of items to fetch (1-100 recommended). Bulbapedia has rate limits and can be slow, so start with a small number (5-10).', 'poke-hub'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="fetch_force"><?php esc_html_e('Force update', 'poke-hub'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="fetch_force" name="fetch_force" value="1" />
                        <?php esc_html_e('Update even if translations already exist (will replace existing translations).', 'poke-hub'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Fetch Missing Translations', 'poke-hub'), 'primary', 'poke_hub_fetch_missing_translations'); ?>
    </form>
</div>

<div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-top: 20px;">
    <h3><?php esc_html_e('Edit Missing Translations', 'poke-hub'); ?></h3>
    <p><?php esc_html_e('Enter translations for missing items. Only items with entered translations will be saved.', 'poke-hub'); ?></p>

    <form method="get" action="" style="margin-top: 15px; margin-bottom: 20px; padding: 15px; background: #f0f0f1; border-radius: 4px;">
        <input type="hidden" name="page" value="poke-hub-settings" />
        <input type="hidden" name="tab" value="translation" />

        <label for="filter_lang" style="margin-right: 15px;">
            <strong><?php esc_html_e('Language:', 'poke-hub'); ?></strong>
            <select id="filter_lang" name="lang" onchange="this.form.submit();" style="margin-left: 5px;">
                <?php foreach ($allowed_langs as $lang): ?>
                    <option value="<?php echo esc_attr($lang); ?>" <?php selected($selected_lang, $lang); ?>>
                        <?php echo esc_html($lang_names[$lang] ?? strtoupper($lang)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label for="filter_type" style="margin-left: 15px;">
            <strong><?php esc_html_e('Type:', 'poke-hub'); ?></strong>
            <select id="filter_type" name="type" onchange="this.form.submit();" style="margin-left: 5px;">
                <option value="pokemon" <?php selected($selected_type, 'pokemon'); ?>><?php esc_html_e('Pokémon', 'poke-hub'); ?></option>
                <option value="attacks" <?php selected($selected_type, 'attacks'); ?>><?php esc_html_e('Attacks', 'poke-hub'); ?></option>
                <option value="types" <?php selected($selected_type, 'types'); ?>><?php esc_html_e('Types', 'poke-hub'); ?></option>
            </select>
        </label>
    </form>

    <?php if (empty($missing_items)): ?>
        <div class="notice notice-success inline" style="margin-top: 15px;">
            <p>
                <?php
                printf(
                    esc_html__('No missing translations found for %1$s %2$s. All items have translations!', 'poke-hub'),
                    esc_html($lang_names[$selected_lang] ?? strtoupper($selected_lang)),
                    esc_html(ucfirst($selected_type))
                );
                ?>
            </p>
        </div>
    <?php else: ?>
        <form method="post" action="" id="translations-form">
            <?php wp_nonce_field('poke_hub_translation_settings', 'poke_hub_translation_nonce'); ?>

            <div style="margin-bottom: 15px;">
                <p>
                    <strong>
                        <?php
                        printf(
                            esc_html__('Found %d missing translation(s) for %s %s', 'poke-hub'),
                            count($missing_items),
                            esc_html($lang_names[$selected_lang] ?? strtoupper($selected_lang)),
                            esc_html(ucfirst($selected_type))
                        );
                        ?>
                    </strong>
                </p>
                <p class="description"><?php esc_html_e('Enter translations in the fields below. Only fields with content will be saved.', 'poke-hub'); ?></p>
            </div>

            <table class="widefat striped" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th style="width: 50px;"><?php esc_html_e('ID', 'poke-hub'); ?></th>
                        <?php if ($selected_type === 'pokemon'): ?>
                            <th style="width: 80px;"><?php esc_html_e('Dex #', 'poke-hub'); ?></th>
                        <?php endif; ?>
                        <th style="width: 25%;"><?php esc_html_e('English Name', 'poke-hub'); ?></th>
                        <th style="width: 25%;"><?php esc_html_e('Current Translation', 'poke-hub'); ?></th>
                        <th><?php printf(esc_html__('%s Translation', 'poke-hub'), esc_html($lang_names[$selected_lang] ?? strtoupper($selected_lang))); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($missing_items as $item): ?>
                        <?php
                        $item_id = (int) ($item['id'] ?? 0);
                        $current_translation = '';

                        if ($selected_lang === 'fr' && !empty($item['name_fr'])) {
                            $current_translation = (string) $item['name_fr'];
                        } elseif (!empty($item['extra'])) {
                            $decoded = json_decode((string) $item['extra'], true);
                            if (is_array($decoded) && !empty($decoded['names'][$selected_lang])) {
                                $current_translation = (string) $decoded['names'][$selected_lang];
                            }
                        } elseif (!empty($item['names']) && is_array($item['names']) && !empty($item['names'][$selected_lang])) {
                            $current_translation = (string) $item['names'][$selected_lang];
                        }
                        ?>
                        <tr>
                            <td><?php echo (int) $item_id; ?></td>

                            <?php if ($selected_type === 'pokemon'): ?>
                                <td><?php echo isset($item['dex_number']) ? (int) $item['dex_number'] : '-'; ?></td>
                            <?php endif; ?>

                            <td><strong><?php echo esc_html($item['name_en'] ?? ''); ?></strong></td>

                            <td>
                                <?php if ($current_translation !== ''): ?>
                                    <em style="color: #666;"><?php echo esc_html($current_translation); ?></em>
                                <?php else: ?>
                                    <span style="color: #d63638;"><?php esc_html_e('Missing', 'poke-hub'); ?></span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <input type="hidden" name="translations[<?php echo (int) $item_id; ?>][type]" value="<?php echo esc_attr($selected_type); ?>" />
                                <input type="hidden" name="translations[<?php echo (int) $item_id; ?>][lang]" value="<?php echo esc_attr($selected_lang); ?>" />
                                <input
                                    type="text"
                                    name="translations[<?php echo (int) $item_id; ?>][translation]"
                                    value="<?php echo esc_attr($current_translation); ?>"
                                    class="regular-text"
                                    placeholder="<?php printf(esc_attr__('Enter %s translation...', 'poke-hub'), esc_attr($lang_names[$selected_lang] ?? strtoupper($selected_lang))); ?>"
                                />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top: 20px;">
                <?php submit_button(__('Save Translations', 'poke-hub'), 'primary', 'poke_hub_save_translations', false); ?>
                <span class="description" style="margin-left: 10px;"><?php esc_html_e('Only translations with entered values will be saved.', 'poke-hub'); ?></span>
            </p>
        </form>
    <?php endif; ?>
</div>
