<?php
// File: includes/admin-tools.php
// Module temporaire : scripts / outils ponctuels (ex. import Pokekalos). À supprimer quand inutile.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Indique si le sous-menu et la page « Outils temporaires » sont activés (réglage Poké HUB).
 */
function poke_hub_temporary_tools_enabled(): bool {
    return (bool) get_option('poke_hub_temporary_tools_enabled', true);
}

/**
 * URL de la page Outils temporaires (optionnel : onglet actif).
 */
function poke_hub_admin_tools_url(string $tab = ''): string {
    $u = admin_url('admin.php?page=poke-hub-tools');
    if ($tab !== '') {
        $u = add_query_arg('tab', sanitize_key($tab), $u);
    }
    return $u;
}

/**
 * Helpers download images (admin tools).
 */
function poke_hub_tools_normalize_text($value): string {
    if ($value === null) {
        return '';
    }
    return trim((string) $value);
}

function poke_hub_tools_normalize_bool($value): bool {
    $v = strtolower(poke_hub_tools_normalize_text($value));
    return in_array($v, ['1', 'true', 'yes', 'y', 'oui'], true);
}

function poke_hub_tools_normalize_slug_token($value): string {
    $token = strtolower(poke_hub_tools_normalize_text($value));
    if ($token === '') {
        return '';
    }
    $token = preg_replace('/[^a-z0-9\-]+/', '-', $token);
    $token = trim((string) $token, '-');
    return $token;
}

function poke_hub_tools_add_missing_token(string $stem, string $token): string {
    if ($token === '') {
        return $stem;
    }
    if (preg_match('/(?:^|-)' . preg_quote($token, '/') . '(?:-|$)/', $stem)) {
        return $stem;
    }
    return $stem . '-' . $token;
}

function poke_hub_tools_build_image_stem(array $row): string {
    $slug = poke_hub_tools_normalize_slug_token($row['slug'] ?? '');
    if ($slug === '') {
        return '';
    }

    $gender      = poke_hub_tools_normalize_slug_token($row['gender'] ?? '');
    $mode        = poke_hub_tools_normalize_slug_token($row['mode'] ?? '');
    $form_slug   = poke_hub_tools_normalize_slug_token($row['form_slug'] ?? '');
    $costume_slug = poke_hub_tools_normalize_slug_token($row['costume_slug'] ?? '');

    $is_shiny     = poke_hub_tools_normalize_bool($row['is_shiny'] ?? false);
    $is_gigamax   = poke_hub_tools_normalize_bool($row['is_gigamax'] ?? false) || $mode === 'gigantamax';
    $is_dynamax   = poke_hub_tools_normalize_bool($row['is_dynamax'] ?? false) || $mode === 'dynamax';
    $is_mega      = poke_hub_tools_normalize_bool($row['is_mega'] ?? false) || $mode === 'mega';
    $is_shadow    = poke_hub_tools_normalize_bool($row['is_shadow'] ?? false) || $mode === 'shadow';
    $is_costume   = poke_hub_tools_normalize_bool($row['is_costume'] ?? false) || $mode === 'costume';

    $stem = $slug;
    if ($is_gigamax && strpos($stem, 'gigantamax-') !== 0) {
        $stem = 'gigantamax-' . $stem;
    } elseif ($is_dynamax && strpos($stem, 'dynamax-') !== 0) {
        $stem = 'dynamax-' . $stem;
    } elseif ($is_mega && strpos($stem, 'mega-') !== 0) {
        $stem = 'mega-' . $stem;
    }

    if ($gender === 'male' && !preg_match('/-male(?:-|$)/', $stem)) {
        $stem .= '-male';
    } elseif ($gender === 'female' && !preg_match('/-female(?:-|$)/', $stem)) {
        $stem .= '-female';
    }
    if ($is_shiny && !preg_match('/-shiny(?:-|$)/', $stem)) {
        $stem .= '-shiny';
    }
    if ($is_shadow && !preg_match('/-shadow(?:-|$)/', $stem)) {
        $stem .= '-shadow';
    }
    if ($is_costume) {
        $stem = poke_hub_tools_add_missing_token($stem, 'costume');
    }
    if ($costume_slug !== '') {
        $stem = poke_hub_tools_add_missing_token($stem, $costume_slug);
    }
    if ($form_slug !== '') {
        $stem = poke_hub_tools_add_missing_token($stem, $form_slug);
    }

    return $stem;
}

function poke_hub_tools_resolve_row_url(array $row, string $template): string {
    $direct = poke_hub_tools_normalize_text($row['url'] ?? '');
    if ($direct !== '') {
        return esc_url_raw($direct);
    }
    if ($template === '') {
        return '';
    }

    $dex = (int) poke_hub_tools_normalize_text($row['dex'] ?? '0');
    $slug = poke_hub_tools_normalize_text($row['slug'] ?? '');
    $gender = poke_hub_tools_normalize_slug_token($row['gender'] ?? '');
    $form = poke_hub_tools_normalize_text($row['form'] ?? '');
    $form_slug = poke_hub_tools_normalize_slug_token($row['form_slug'] ?? '');
    $costume_slug = poke_hub_tools_normalize_slug_token($row['costume_slug'] ?? '');
    $mode = poke_hub_tools_normalize_slug_token($row['mode'] ?? '');
    $is_shiny = poke_hub_tools_normalize_bool($row['is_shiny'] ?? false);
    $is_gigamax = poke_hub_tools_normalize_bool($row['is_gigamax'] ?? false);
    $is_dynamax = poke_hub_tools_normalize_bool($row['is_dynamax'] ?? false);
    $is_mega = poke_hub_tools_normalize_bool($row['is_mega'] ?? false);
    $is_shadow = poke_hub_tools_normalize_bool($row['is_shadow'] ?? false);

    $stem = poke_hub_tools_build_image_stem($row);
    if ($stem === '') {
        return '';
    }

    $gender_suffix = $gender === 'male' ? '-male' : ($gender === 'female' ? '-female' : '');
    $shiny_suffix = $is_shiny ? '-shiny' : '';
    $form_suffix = $form !== '' ? '-' . strtolower($form) : '';
    $mode_prefix = '';
    if ($mode === 'gigantamax' || $is_gigamax) {
        $mode_prefix = 'gigantamax-';
    } elseif ($mode === 'dynamax' || $is_dynamax) {
        $mode_prefix = 'dynamax-';
    } elseif ($mode === 'mega' || $is_mega) {
        $mode_prefix = 'mega-';
    }
    $mode_suffix = $is_shadow || $mode === 'shadow' ? '-shadow' : '';

    $replacements = [
        '{dex}' => (string) $dex,
        '{dex3}' => sprintf('%03d', max(0, $dex)),
        '{slug}' => $slug,
        '{stem}' => $stem,
        '{gender}' => $gender,
        '{gender_suffix}' => $gender_suffix,
        '{shiny_suffix}' => $shiny_suffix,
        '{form}' => $form,
        '{form_suffix}' => $form_suffix,
        '{form_slug}' => $form_slug,
        '{costume_slug}' => $costume_slug,
        '{mode}' => $mode,
        '{mode_prefix}' => $mode_prefix,
        '{mode_suffix}' => $mode_suffix,
    ];

    $url = strtr($template, $replacements);
    return esc_url_raw($url);
}

function poke_hub_tools_download_images_from_manifest(array $args): array {
    $defaults = [
        'manifest_csv' => '',
        'go_template' => '',
        'home_template' => '',
        'skip_existing' => true,
        'timeout' => 30,
    ];
    $args = wp_parse_args($args, $defaults);

    $manifest_csv = poke_hub_tools_normalize_text($args['manifest_csv']);
    if ($manifest_csv === '') {
        return ['log' => [__('Manifest CSV is required.', 'poke-hub')]];
    }

    $uploads = wp_upload_dir();
    $base_dir = trailingslashit($uploads['basedir']) . 'poke-hub/gamemaster';
    $go_dir = trailingslashit($base_dir) . 'pokemon-go/pokemon';
    $home_dir = trailingslashit($base_dir) . 'home/pokemon';
    wp_mkdir_p($go_dir);
    wp_mkdir_p($home_dir);

    $rows = array_map('str_getcsv', preg_split("/\r\n|\n|\r/", $manifest_csv));
    if (count($rows) < 2) {
        return ['log' => [__('Manifest CSV has no data rows.', 'poke-hub')]];
    }
    $headers = array_map(static fn($v) => trim((string) $v), (array) array_shift($rows));
    $log = [];
    $ok = 0;
    $fail = 0;
    $skip = 0;

    foreach ($rows as $i => $values) {
        if (!is_array($values) || count(array_filter($values, static fn($v) => trim((string) $v) !== '')) === 0) {
            continue;
        }
        $row = [];
        foreach ($headers as $idx => $key) {
            if ($key === '') {
                continue;
            }
            $row[$key] = $values[$idx] ?? '';
        }

        $slug = poke_hub_tools_normalize_text($row['slug'] ?? '');
        $source = strtolower(poke_hub_tools_normalize_text($row['source'] ?? ''));
        if ($slug === '' || !in_array($source, ['go', 'home'], true)) {
            $log[] = sprintf('Ligne %d ignorée: slug/source invalide.', $i + 2);
            $fail++;
            continue;
        }

        $stem = poke_hub_tools_build_image_stem($row);
        if ($stem === '') {
            $log[] = sprintf('Ligne %d ignorée: stem vide.', $i + 2);
            $fail++;
            continue;
        }

        $ext = strtolower(ltrim(poke_hub_tools_normalize_text($row['extension'] ?? 'png'), '.'));
        if ($ext === '') {
            $ext = 'png';
        }
        $out_dir = $source === 'go' ? $go_dir : $home_dir;
        $out_file = trailingslashit($out_dir) . $stem . '.' . $ext;

        if (!empty($args['skip_existing']) && file_exists($out_file)) {
            $skip++;
            $log[] = sprintf('[SKIP] %s', wp_basename($out_file));
            continue;
        }

        $tpl = $source === 'go' ? poke_hub_tools_normalize_text($args['go_template']) : poke_hub_tools_normalize_text($args['home_template']);
        $url = poke_hub_tools_resolve_row_url($row, $tpl);
        if ($url === '') {
            $log[] = sprintf('Ligne %d: aucune URL résolue.', $i + 2);
            $fail++;
            continue;
        }

        $res = wp_remote_get($url, [
            'timeout' => max(5, (int) $args['timeout']),
            'redirection' => 5,
            'user-agent' => 'PokeHubImageSync/1.0',
        ]);
        if (is_wp_error($res)) {
            $fail++;
            $log[] = sprintf('[FAIL] %s (%s)', $url, $res->get_error_message());
            continue;
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        if ($code < 200 || $code >= 300 || $body === '') {
            $fail++;
            $log[] = sprintf('[FAIL] %s (HTTP %d)', $url, $code);
            continue;
        }
        $written = @file_put_contents($out_file, $body);
        if ($written === false) {
            $fail++;
            $log[] = sprintf('[FAIL] Ecriture impossible: %s', $out_file);
            continue;
        }
        $ok++;
        $log[] = sprintf('[OK] %s -> %s', $url, wp_basename($out_file));
    }

    $log[] = '';
    $log[] = sprintf('Terminé. OK=%d | FAIL=%d | SKIP=%d', $ok, $fail, $skip);
    $log[] = sprintf('Dossier de sortie: %s', $base_dir);

    return ['log' => $log];
}

function poke_hub_tools_get_gamemaster_base_paths(): array {
    $uploads = wp_upload_dir();
    $base_dir = trailingslashit($uploads['basedir']) . 'poke-hub/gamemaster';
    $base_url = trailingslashit($uploads['baseurl']) . 'poke-hub/gamemaster';
    return [
        'dir' => $base_dir,
        'url' => $base_url,
    ];
}

function poke_hub_tools_create_gamemaster_zip(): array {
    $paths = poke_hub_tools_get_gamemaster_base_paths();
    $base_dir = (string) ($paths['dir'] ?? '');
    if ($base_dir === '' || !is_dir($base_dir)) {
        return [
            'log' => [__('Gamemaster folder not found. Run download first.', 'poke-hub')],
        ];
    }
    if (!class_exists('ZipArchive')) {
        return [
            'log' => [__('ZipArchive is not available on this server.', 'poke-hub')],
        ];
    }

    $uploads = wp_upload_dir();
    $zip_dir = trailingslashit($uploads['basedir']) . 'poke-hub/exports';
    $zip_url_base = trailingslashit($uploads['baseurl']) . 'poke-hub/exports';
    wp_mkdir_p($zip_dir);

    $zip_name = 'poke-hub-gamemaster-' . gmdate('Ymd-His') . '.zip';
    $zip_path = trailingslashit($zip_dir) . $zip_name;
    $zip_url = trailingslashit($zip_url_base) . $zip_name;

    $zip = new ZipArchive();
    $open = $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($open !== true) {
        return [
            'log' => [sprintf(__('Failed to create ZIP file (%s).', 'poke-hub'), (string) $open)],
        ];
    }

    $base_real = realpath($base_dir);
    if ($base_real === false) {
        $zip->close();
        return [
            'log' => [__('Invalid gamemaster path.', 'poke-hub')],
        ];
    }
    $base_real = rtrim($base_real, '\\/') . DIRECTORY_SEPARATOR;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_real, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $count = 0;
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }
        if (!$file->isFile()) {
            continue;
        }
        $file_path = $file->getRealPath();
        if ($file_path === false) {
            continue;
        }
        $local_name = str_replace('\\', '/', substr($file_path, strlen($base_real)));
        if ($local_name === '') {
            continue;
        }
        $zip->addFile($file_path, $local_name);
        $count++;
    }

    $zip->close();

    return [
        'log' => [
            sprintf(__('ZIP created with %d files.', 'poke-hub'), $count),
            sprintf(__('ZIP path: %s', 'poke-hub'), $zip_path),
        ],
        'download_url' => esc_url_raw($zip_url),
        'download_label' => $zip_name,
    ];
}

/**
 * Enregistre le sous-menu "Outils temporaires" sous Poké HUB.
 */
function poke_hub_admin_menu_tools() {
    if (!poke_hub_temporary_tools_enabled()) {
        return;
    }
    add_submenu_page(
        'poke-hub',
        __('Temporary tools', 'poke-hub'),
        __('Temporary tools', 'poke-hub'),
        'manage_options',
        'poke-hub-tools',
        'poke_hub_admin_tools_page'
    );
}
add_action('admin_menu', 'poke_hub_admin_menu_tools', 25);

/**
 * Affiche la page Outils temporaires (formulaires + résultat d’exécution).
 */
function poke_hub_admin_tools_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!poke_hub_temporary_tools_enabled()) {
        wp_die(
            esc_html__('Temporary tools are disabled. Enable them under Poké HUB → Settings → General.', 'poke-hub'),
            esc_html__('Temporary tools', 'poke-hub'),
            403
        );
    }

    $result = null;
    $run_pokekalos = isset($_POST['poke_hub_tools_pokekalos']) && $_POST['poke_hub_tools_pokekalos'] === '1';
    if ($run_pokekalos && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'poke_hub_tools_pokekalos')) {
        $dry_run       = !empty($_POST['poke_hub_pokekalos_dry_run']);
        $limit         = isset($_POST['poke_hub_pokekalos_limit']) ? (int) $_POST['poke_hub_pokekalos_limit'] : 0;
        $delay         = isset($_POST['poke_hub_pokekalos_delay']) ? (int) $_POST['poke_hub_pokekalos_delay'] : 1;
        $skip_existing = !empty($_POST['poke_hub_pokekalos_skip_existing']);
        if ($delay < 0) {
            $delay = 1;
        }
        set_time_limit(0);
        $result = poke_hub_run_pokekalos_import([
            'dry_run'       => $dry_run,
            'limit'         => $limit,
            'delay'         => $delay,
            'skip_existing' => $skip_existing,
        ]);
    }

    $run_images_sync = isset($_POST['poke_hub_tools_images_sync']) && $_POST['poke_hub_tools_images_sync'] === '1';
    if ($run_images_sync && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'poke_hub_tools_images_sync')) {
        $manifest_csv = isset($_POST['poke_hub_images_manifest_csv']) ? (string) wp_unslash($_POST['poke_hub_images_manifest_csv']) : '';
        $go_template  = isset($_POST['poke_hub_images_go_template']) ? (string) wp_unslash($_POST['poke_hub_images_go_template']) : '';
        $home_template = isset($_POST['poke_hub_images_home_template']) ? (string) wp_unslash($_POST['poke_hub_images_home_template']) : '';
        $skip_existing = !empty($_POST['poke_hub_images_skip_existing']);
        $timeout = isset($_POST['poke_hub_images_timeout']) ? (int) $_POST['poke_hub_images_timeout'] : 30;

        set_time_limit(0);
        $result = poke_hub_tools_download_images_from_manifest([
            'manifest_csv' => $manifest_csv,
            'go_template' => $go_template,
            'home_template' => $home_template,
            'skip_existing' => $skip_existing,
            'timeout' => $timeout,
        ]);
    }

    $run_images_zip = isset($_POST['poke_hub_tools_images_zip']) && $_POST['poke_hub_tools_images_zip'] === '1';
    if ($run_images_zip && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'poke_hub_tools_images_zip')) {
        $result = poke_hub_tools_create_gamemaster_zip();
    }

    $events_on  = function_exists('poke_hub_is_module_active') && poke_hub_is_module_active('events');
    $pokemon_on = function_exists('poke_hub_is_module_active') && poke_hub_is_module_active('pokemon');

    $tab_defs = [
        'pokekalos' => [
            'label' => __('Dates (Pokekalos)', 'poke-hub'),
            'show'  => true,
        ],
        'raid-hour' => [
            'label' => __('Heure de raids (Fandom)', 'poke-hub'),
            'show'  => $events_on && function_exists('pokehub_fandom_recurring_render_card'),
        ],
        'spotlight-hour' => [
            'label' => __('Heure vedette (Fandom)', 'poke-hub'),
            'show'  => $events_on && function_exists('pokehub_fandom_recurring_render_card'),
        ],
        'max-monday' => [
            'label' => __('Lundi Max (Fandom)', 'poke-hub'),
            'show'  => $events_on && function_exists('pokehub_render_max_monday_import_section'),
        ],
        'gamemaster' => [
            'label' => __('Game Master', 'poke-hub'),
            'show'  => $pokemon_on,
        ],
        'translation' => [
            'label' => __('Translation', 'poke-hub'),
            'show'  => $pokemon_on,
        ],
        'images-sync' => [
            'label' => __('Images sync', 'poke-hub'),
            'show'  => $pokemon_on,
        ],
    ];

    $allowed_tabs = array_keys(array_filter($tab_defs, static function (array $d): bool {
        return !empty($d['show']);
    }));

    $current_tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash((string) $_GET['tab'])) : '';
    if ($current_tab === '' || !in_array($current_tab, $allowed_tabs, true)) {
        $current_tab = $allowed_tabs[0] ?? 'pokekalos';
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Temporary tools', 'poke-hub'); ?></h1>
        <p class="description"><?php esc_html_e('One-off scripts (imports, migrations…). This menu can be removed once operations are complete.', 'poke-hub'); ?></p>

        <h2 class="nav-tab-wrapper" style="padding-top:8px;margin-bottom:0;border-bottom:1px solid #c3c4c7;">
            <?php foreach ($tab_defs as $slug => $def) : ?>
                <?php if (empty($def['show'])) {
                    continue;
                } ?>
                <a href="<?php echo esc_url(poke_hub_admin_tools_url($slug)); ?>"
                   class="nav-tab<?php echo $current_tab === $slug ? ' nav-tab-active' : ''; ?>"
                   id="pokehub-tools-tab-<?php echo esc_attr($slug); ?>">
                    <?php echo esc_html((string) $def['label']); ?>
                </a>
            <?php endforeach; ?>
        </h2>

        <div class="pokehub-tools-tab-panels" style="border:1px solid #c3c4c7;border-top:none;background:#fff;padding:16px 20px 24px;max-width:1100px;">
            <div id="pokehub-tools-panel-pokekalos" class="pokehub-tools-panel" style="<?php echo $current_tab !== 'pokekalos' ? 'display:none;' : ''; ?>">
        <div class="card" style="max-width: 640px; margin-top: 0;">
            <h2 class="title"><?php esc_html_e('Import Pokekalos release dates', 'poke-hub'); ?></h2>
            <p><?php esc_html_e('Fetches release dates (normal, shiny, shadow, dynamax, gigantamax) from Pokekalos Pokémon GO Pokédex pages and updates the database. Only Pokémon with a French name are processed.', 'poke-hub'); ?></p>

            <form method="post" action="<?php echo esc_url(poke_hub_admin_tools_url('pokekalos')); ?>">
                <?php wp_nonce_field('poke_hub_tools_pokekalos'); ?>
                <input type="hidden" name="poke_hub_tools_pokekalos" value="1" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Simulation mode', 'poke-hub'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="poke_hub_pokekalos_dry_run" value="1" />
                                <?php esc_html_e('Dry-run (show results without changing the database)', 'poke-hub'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="poke_hub_pokekalos_limit"><?php esc_html_e('Number to process', 'poke-hub'); ?></label></th>
                        <td>
                            <input type="number" name="poke_hub_pokekalos_limit" id="poke_hub_pokekalos_limit" value="0" min="0" step="1" class="small-text" />
                            <span class="description"><?php esc_html_e('0 = all. Otherwise: first X rows in database order (with French name).', 'poke-hub'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Skip existing entries', 'poke-hub'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="poke_hub_pokekalos_skip_existing" value="1" />
                                <?php esc_html_e('Only Pokémon with no release date at all', 'poke-hub'); ?>
                            </label>
                            <span class="description"><?php esc_html_e('If checked: process only the first X without release date (DB order). Otherwise: process the first X overall.', 'poke-hub'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="poke_hub_pokekalos_delay"><?php esc_html_e('Delay (seconds)', 'poke-hub'); ?></label></th>
                        <td>
                            <input type="number" name="poke_hub_pokekalos_delay" id="poke_hub_pokekalos_delay" value="1" min="0" step="1" class="small-text" />
                            <span class="description"><?php esc_html_e('Between each request to Pokekalos.', 'poke-hub'); ?></span>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Run Pokekalos import', 'poke-hub'); ?></button>
                </p>
            </form>
        </div>
            </div>

        <?php if ($events_on) : ?>
            <div id="pokehub-tools-panel-raid-hour" class="pokehub-tools-panel" style="<?php echo $current_tab !== 'raid-hour' ? 'display:none;' : ''; ?>">
                <?php
                if (function_exists('pokehub_fandom_recurring_render_card')) {
                    pokehub_fandom_recurring_render_card('raid_hour');
                }
                ?>
            </div>
            <div id="pokehub-tools-panel-spotlight-hour" class="pokehub-tools-panel" style="<?php echo $current_tab !== 'spotlight-hour' ? 'display:none;' : ''; ?>">
                <?php
                if (function_exists('pokehub_fandom_recurring_render_card')) {
                    pokehub_fandom_recurring_render_card('spotlight_hour');
                }
                ?>
            </div>
            <div id="pokehub-tools-panel-max-monday" class="pokehub-tools-panel" style="<?php echo $current_tab !== 'max-monday' ? 'display:none;' : ''; ?>">
                <?php
                if (function_exists('pokehub_render_max_monday_import_section')) {
                    pokehub_render_max_monday_import_section();
                }
                ?>
            </div>
        <?php endif; ?>

            <?php if ($pokemon_on) : ?>
                <div id="pokehub-tools-panel-gamemaster" class="pokehub-tools-panel" style="<?php echo $current_tab !== 'gamemaster' ? 'display:none;' : ''; ?>">
                    <?php
                    $gm_tab_file = __DIR__ . '/settings/tabs/settings-tab-gamemaster.php';
                    if (file_exists($gm_tab_file)) {
                        require $gm_tab_file;
                    }
                    ?>
                </div>
                <div id="pokehub-tools-panel-translation" class="pokehub-tools-panel" style="<?php echo $current_tab !== 'translation' ? 'display:none;' : ''; ?>">
                    <?php
                    if (!defined('POKE_HUB_TRANSLATION_TAB_CONTEXT')) {
                        define('POKE_HUB_TRANSLATION_TAB_CONTEXT', 'tools');
                    }
                    $tr_tab_file = __DIR__ . '/settings/tabs/settings-tab-translation.php';
                    if (file_exists($tr_tab_file)) {
                        require $tr_tab_file;
                    }
                    ?>
                </div>
                <div id="pokehub-tools-panel-images-sync" class="pokehub-tools-panel" style="<?php echo $current_tab !== 'images-sync' ? 'display:none;' : ''; ?>">
                    <div class="card" style="max-width: 980px; margin-top: 0;">
                        <h2 class="title"><?php esc_html_e('Download Pokemon images (GO / HOME)', 'poke-hub'); ?></h2>
                        <p><?php esc_html_e('Paste a CSV manifest then run download. Names are normalized with project rules: mode (costume/mega/dynamax/gigantamax/shadow), gender, shiny, and form/costume slugs.', 'poke-hub'); ?></p>
                        <?php
                        $gm_paths = poke_hub_tools_get_gamemaster_base_paths();
                        $gm_dir = (string) ($gm_paths['dir'] ?? '');
                        ?>
                        <p class="description"><?php echo esc_html(sprintf(__('Local storage folder: %s', 'poke-hub'), $gm_dir)); ?></p>

                        <form method="post" action="<?php echo esc_url(poke_hub_admin_tools_url('images-sync')); ?>">
                            <?php wp_nonce_field('poke_hub_tools_images_sync'); ?>
                            <input type="hidden" name="poke_hub_tools_images_sync" value="1" />
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="poke_hub_images_manifest_csv"><?php esc_html_e('Manifest CSV', 'poke-hub'); ?></label></th>
                                    <td>
                                        <textarea name="poke_hub_images_manifest_csv" id="poke_hub_images_manifest_csv" rows="12" class="large-text code" placeholder="source,dex,slug,form,form_slug,costume_slug,mode,gender,is_shiny,is_gigamax,is_dynamax,is_mega,is_shadow,is_costume,extension,url"></textarea>
                                        <p class="description"><?php esc_html_e('One row per image. If URL is empty, template URL below is used.', 'poke-hub'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="poke_hub_images_go_template"><?php esc_html_e('GO URL template', 'poke-hub'); ?></label></th>
                                    <td>
                                        <input type="text" name="poke_hub_images_go_template" id="poke_hub_images_go_template" class="regular-text code" value="" placeholder="https://example.com/go/{stem}.png" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="poke_hub_images_home_template"><?php esc_html_e('HOME URL template', 'poke-hub'); ?></label></th>
                                    <td>
                                        <input type="text" name="poke_hub_images_home_template" id="poke_hub_images_home_template" class="regular-text code" value="" placeholder="https://example.com/home/{stem}.png" />
                                        <p class="description"><?php esc_html_e('Placeholders: {dex}, {dex3}, {slug}, {stem}, {gender}, {gender_suffix}, {shiny_suffix}, {form}, {form_suffix}, {form_slug}, {costume_slug}, {mode}, {mode_prefix}, {mode_suffix}', 'poke-hub'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Skip existing', 'poke-hub'); ?></th>
                                    <td>
                                        <label><input type="checkbox" name="poke_hub_images_skip_existing" value="1" checked="checked" /> <?php esc_html_e('Do not overwrite files already present in uploads/poke-hub/gamemaster.', 'poke-hub'); ?></label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="poke_hub_images_timeout"><?php esc_html_e('HTTP timeout (seconds)', 'poke-hub'); ?></label></th>
                                    <td>
                                        <input type="number" name="poke_hub_images_timeout" id="poke_hub_images_timeout" value="30" min="5" step="1" class="small-text" />
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Run image download', 'poke-hub'); ?></button>
                            </p>
                        </form>

                        <hr />
                        <form method="post" action="<?php echo esc_url(poke_hub_admin_tools_url('images-sync')); ?>">
                            <?php wp_nonce_field('poke_hub_tools_images_zip'); ?>
                            <input type="hidden" name="poke_hub_tools_images_zip" value="1" />
                            <p class="submit" style="margin-top: 0;">
                                <button type="submit" class="button"><?php esc_html_e('Create and download ZIP', 'poke-hub'); ?></button>
                            </p>
                            <p class="description"><?php esc_html_e('Creates a ZIP from uploads/poke-hub/gamemaster and stores it in uploads/poke-hub/exports.', 'poke-hub'); ?></p>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($result !== null) : ?>
            <div class="card" style="max-width: 900px; margin-top: 20px;">
                <h3><?php esc_html_e('Result', 'poke-hub'); ?></h3>
                <?php if (!empty($result['download_url'])) : ?>
                    <p>
                        <a class="button button-secondary" href="<?php echo esc_url((string) $result['download_url']); ?>">
                            <?php
                            $label = !empty($result['download_label']) ? (string) $result['download_label'] : __('Download ZIP', 'poke-hub');
                            echo esc_html($label);
                            ?>
                        </a>
                    </p>
                <?php endif; ?>
                <pre style="background: #f5f5f5; padding: 12px; overflow: auto; max-height: 400px; white-space: pre-wrap;"><?php
                    echo esc_html(implode("\n", $result['log']));
                ?></pre>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
