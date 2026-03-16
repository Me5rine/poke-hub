<?php
/**
 * Script CLI d'import des dates de sortie Pokémon GO depuis Pokekalos.
 *
 * Non automatique : à lancer manuellement. Ne traite que les Pokémon avec nom français.
 *
 * Usage :
 *   php scripts/import-pokekalos-release-dates.php [--dry-run] [--limit=N] [--delay=N]
 */

$script_dir = __DIR__;
$plugin_dir = dirname($script_dir);
$wp_load    = null;
$search     = $plugin_dir;
for ($i = 0; $i < 5; $i++) {
    $search = dirname($search);
    $candidate = $search . '/wp-load.php';
    if (is_file($candidate)) {
        $wp_load = $candidate;
        break;
    }
}
if (!$wp_load || !is_file($wp_load)) {
    fwrite(STDERR, "wp-load.php introuvable. Exécutez ce script depuis la racine WordPress ou le dossier du plugin.\n");
    exit(1);
}
require_once $wp_load;

if (!function_exists('poke_hub_run_pokekalos_import')) {
    fwrite(STDERR, "Le plugin Poké HUB ou le module d'import Pokekalos est manquant.\n");
    exit(1);
}

$argv = $argv ?? [];
$dry_run       = in_array('--dry-run', $argv, true);
$skip_existing = in_array('--skip-existing', $argv, true);
$limit         = 0;
$delay         = 1;
foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int) substr($arg, 8);
    }
    if (strpos($arg, '--delay=') === 0) {
        $delay = (int) substr($arg, 8);
    }
}

$result = poke_hub_run_pokekalos_import([
    'dry_run'       => $dry_run,
    'limit'         => $limit,
    'delay'         => $delay,
    'skip_existing' => $skip_existing,
]);

foreach ($result['log'] as $line) {
    echo $line . "\n";
}
