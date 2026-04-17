<?php
// File: includes/admin-tools.php
// Module temporaire : scripts / outils ponctuels (ex. import Pokekalos). À supprimer quand inutile.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enregistre le sous-menu "Outils temporaires" sous Poké HUB.
 */
function poke_hub_admin_menu_tools() {
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

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Temporary tools', 'poke-hub'); ?></h1>
        <p class="description"><?php esc_html_e('One-off scripts (imports, migrations…). This menu can be removed once operations are complete.', 'poke-hub'); ?></p>

        <div class="card" style="max-width: 640px; margin-top: 20px;">
            <h2 class="title"><?php esc_html_e('Import Pokekalos release dates', 'poke-hub'); ?></h2>
            <p><?php esc_html_e('Fetches release dates (normal, shiny, shadow, dynamax, gigantamax) from Pokekalos Pokémon GO Pokédex pages and updates the database. Only Pokémon with a French name are processed.', 'poke-hub'); ?></p>

            <form method="post" action="">
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
