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
    } else {
        $result = null;
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
            <?php endif; ?>
        </div>

        <?php if ($result !== null) : ?>
            <div class="card" style="max-width: 900px; margin-top: 20px;">
                <h3><?php esc_html_e('Result', 'poke-hub'); ?></h3>
                <pre style="background: #f5f5f5; padding: 12px; overflow: auto; max-height: 400px; white-space: pre-wrap;"><?php
                    echo esc_html(implode("\n", $result['log']));
                ?></pre>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
