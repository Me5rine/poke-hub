<?php
// File: includes/functions/pokehub-pokekalos-import.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lance l'import des dates de sortie Pokekalos (utilisable en CLI ou depuis l'admin).
 *
 * @param array $options  dry_run (bool), limit (int, 0 = toutes), delay (int), skip_existing (bool).
 * @return array  [ 'log' => string[], 'updated' => int, 'skipped' => int, 'errors' => int, 'species_count' => int ]
 */
function poke_hub_run_pokekalos_import(array $options = []): array {
    global $wpdb;

    $dry_run       = !empty($options['dry_run']);
    $limit         = isset($options['limit']) ? (int) $options['limit'] : 0;
    $delay         = isset($options['delay']) ? (int) $options['delay'] : 1;
    $skip_existing = !empty($options['skip_existing']);
    if ($delay < 0) {
        $delay = 1;
    }

    $log     = [];
    $updated = 0;
    $skipped = 0;
    $errors  = 0;

    if (!function_exists('pokehub_get_table') || !function_exists('poke_hub_parse_pokekalos_notes_release')) {
        $log[] = 'Plugin ou parser Pokekalos manquant.';
        return ['log' => $log, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'species_count' => 0];
    }

    $pokemon_table = pokehub_get_table('pokemon');
    if (!$pokemon_table) {
        $log[] = 'Table pokemon introuvable.';
        return ['log' => $log, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'species_count' => 0];
    }

    // Uniquement les formes de base (is_default = 1) : les fiches Pokekalos ciblent le Pokémon de base, pas les costumes / méga / etc.
    $sql = "SELECT p.id, p.dex_number, p.slug, p.name_fr, p.extra
            FROM {$pokemon_table} p
            INNER JOIN (
                SELECT dex_number, MIN(id) AS first_id
                FROM {$pokemon_table}
                WHERE name_fr IS NOT NULL AND TRIM(name_fr) != '' AND is_default = 1
                GROUP BY dex_number
            ) sub ON p.dex_number = sub.dex_number AND p.id = sub.first_id
            WHERE p.name_fr IS NOT NULL AND TRIM(p.name_fr) != '' AND p.is_default = 1
            ORDER BY p.dex_number ASC";
    if ($limit > 0) {
        $sql .= $wpdb->prepare(" LIMIT %d", $limit);
    }
    $species = $wpdb->get_results($sql);
    if (empty($species)) {
        $log[] = 'Aucun Pokémon avec nom français trouvé.';
        return ['log' => $log, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'species_count' => 0];
    }

    $base_url = 'https://www.pokekalos.fr/pokedex/pokemongo/';

    $fr_to_slug = static function ($name) {
        $s = trim($name);
        if ($s === '') {
            return '';
        }
        $s = mb_strtolower($s, 'UTF-8');
        $accents = ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'û' => 'u', 'ù' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae'];
        $s = strtr($s, $accents);
        $s = preg_replace('/[^a-z0-9]+/i', '-', $s);
        return trim($s, '-');
    };

    $log[] = 'Import des dates de sortie Pokekalos (uniquement Pokémon avec nom FR) — ' . count($species) . ' espèces'
        . ($dry_run ? ' [DRY RUN]' : '')
        . ($skip_existing ? ' — ne pas écraser les existants' : '');
    $log[] = '';

    foreach ($species as $row) {
        $dex_number = (int) $row->dex_number;
        $name_fr    = trim($row->name_fr ?? '');
        $url_slug   = $fr_to_slug($name_fr);

        if ($url_slug === '') {
            $log[] = "  [SKIP] dex={$dex_number} : nom FR vide ou invalide";
            $skipped++;
            continue;
        }

        $url = $base_url . $url_slug . '-' . $dex_number . '.html';

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'user-agent' => 'Poke-Hub/1.0 (WordPress; import dates Pokekalos)',
        ]);

        if (is_wp_error($response)) {
            $log[] = "  [ERR] dex={$dex_number} {$url_slug} : " . $response->get_error_message();
            $errors++;
            if ($delay > 0) {
                sleep($delay);
            }
            continue;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $log[] = "  [SKIP] dex={$dex_number} {$url_slug} : HTTP {$code}";
            $skipped++;
            if ($delay > 0) {
                sleep($delay);
            }
            continue;
        }

        $body   = wp_remote_retrieve_body($response);
        $release = poke_hub_parse_pokekalos_notes_release($body);

        $has_any = false;
        foreach ($release as $v) {
            if ($v !== '') {
                $has_any = true;
                break;
            }
        }
        if (!$has_any) {
            $log[] = "  [SKIP] dex={$dex_number} {$url_slug} : aucune date trouvée";
            $skipped++;
            if ($delay > 0) {
                sleep($delay);
            }
            continue;
        }

        // N'appliquer les dates qu'à la forme de base (pas aux costumes, méga, régionales, etc.)
        $all_with_dex = $wpdb->get_results($wpdb->prepare(
            "SELECT id, extra FROM {$pokemon_table} WHERE dex_number = %d AND is_default = 1",
            $dex_number
        ), OBJECT_K);

        foreach ($all_with_dex as $pokemon_id => $p) {
            $existing_extra = json_decode($p->extra ?? '{}', true);
            if (!is_array($existing_extra)) {
                $existing_extra = [];
            }
            $existing_release = isset($existing_extra['release']) && is_array($existing_extra['release'])
                ? $existing_extra['release']
                : [];

            $new_release = array_merge([
                'normal'     => '',
                'shiny'      => '',
                'shadow'     => '',
                'mega'       => '',
                'dynamax'    => '',
                'gigantamax' => '',
            ], $existing_release);

            foreach ($release as $key => $value) {
                if ($value !== '') {
                    // Skip : ne pas écraser une date déjà renseignée
                    if ($skip_existing && trim((string) ($existing_release[$key] ?? '')) !== '') {
                        continue;
                    }
                    // Extraire la date JJ/MM/AAAA puis normaliser en YYYY-MM-DD (format en base)
                    if (preg_match('#(\d{2}/\d{2}/\d{4})#', $value, $m)) {
                        $value = function_exists('poke_hub_normalize_release_date')
                            ? poke_hub_normalize_release_date($m[1])
                            : $m[1];
                    }
                    if ($value !== '') {
                        $new_release[$key] = $value;
                    }
                }
            }

            $existing_extra['release'] = $new_release;
            $extra_json = wp_json_encode($existing_extra, JSON_UNESCAPED_UNICODE);

            if (!$dry_run) {
                $wpdb->update(
                    $pokemon_table,
                    ['extra' => $extra_json],
                    ['id' => $pokemon_id],
                    ['%s'],
                    ['%d']
                );
            }
            $updated++;
        }

        $summary = [];
        foreach ($release as $k => $v) {
            if ($v !== '') {
                $summary[] = $k . '=' . substr($v, 0, 20) . (strlen($v) > 20 ? '…' : '');
            }
        }
        $log[] = "  [OK] dex={$dex_number} {$url_slug} : " . implode(', ', $summary);

        if ($delay > 0) {
            sleep($delay);
        }
    }

    // ----- Formes Méga : fiches dédiées sur Pokekalos (ex. mega-roucarnage-18m.html) -----
    $variants_table = pokehub_get_table('pokemon_form_variants');
    if ($variants_table) {
        $sql_mega = "SELECT p.id, p.dex_number, p.name_fr, p.extra
                FROM {$pokemon_table} p
                INNER JOIN {$variants_table} fv ON p.form_variant_id = fv.id
                WHERE p.is_default = 0 AND fv.category = 'mega'
                  AND p.name_fr IS NOT NULL AND TRIM(p.name_fr) != ''
                ORDER BY p.dex_number ASC";
        if ($limit > 0) {
            $sql_mega .= $wpdb->prepare(" LIMIT %d", $limit);
        }
        $mega_forms = $wpdb->get_results($sql_mega);
        if (!empty($mega_forms)) {
            $log[] = '';
            $log[] = '--- Formes Méga (fiches dédiées Pokekalos, ex. mega-roucarnage-18m) ---';
            foreach ($mega_forms as $row) {
                $pokemon_id = (int) $row->id;
                $dex_number = (int) $row->dex_number;
                $name_fr    = trim($row->name_fr ?? '');
                $url_slug   = $fr_to_slug($name_fr);
                if ($url_slug === '') {
                    $log[] = "  [SKIP] méga id={$pokemon_id} dex={$dex_number} : nom FR vide";
                    $skipped++;
                    continue;
                }
                $url = $base_url . $url_slug . '-' . $dex_number . 'm.html';
                $response = wp_remote_get($url, [
                    'timeout' => 15,
                    'user-agent' => 'Poke-Hub/1.0 (WordPress; import dates Pokekalos)',
                ]);
                if (is_wp_error($response)) {
                    $log[] = "  [ERR] méga {$url_slug}-{$dex_number}m : " . $response->get_error_message();
                    $errors++;
                    if ($delay > 0) {
                        sleep($delay);
                    }
                    continue;
                }
                $code = wp_remote_retrieve_response_code($response);
                if ($code !== 200) {
                    $log[] = "  [SKIP] méga {$url_slug}-{$dex_number}m : HTTP {$code}";
                    $skipped++;
                    if ($delay > 0) {
                        sleep($delay);
                    }
                    continue;
                }
                $body    = wp_remote_retrieve_body($response);
                $release = poke_hub_parse_pokekalos_notes_release($body);
                $has_any = false;
                foreach ($release as $v) {
                    if ($v !== '') {
                        $has_any = true;
                        break;
                    }
                }
                if (!$has_any) {
                    $log[] = "  [SKIP] méga {$url_slug}-{$dex_number}m : aucune date trouvée";
                    $skipped++;
                    if ($delay > 0) {
                        sleep($delay);
                    }
                    continue;
                }
                $p = $wpdb->get_row($wpdb->prepare("SELECT extra FROM {$pokemon_table} WHERE id = %d", $pokemon_id));
                if (!$p) {
                    continue;
                }
                $existing_extra   = json_decode($p->extra ?? '{}', true);
                $existing_extra   = is_array($existing_extra) ? $existing_extra : [];
                $existing_release = isset($existing_extra['release']) && is_array($existing_extra['release'])
                    ? $existing_extra['release']
                    : [];
                $new_release = array_merge([
                    'normal' => '', 'shiny' => '', 'shadow' => '', 'mega' => '', 'dynamax' => '', 'gigantamax' => '',
                ], $existing_release);
                foreach ($release as $key => $value) {
                    if ($value !== '') {
                        if ($skip_existing && trim((string) ($existing_release[$key] ?? '')) !== '') {
                            continue;
                        }
                        if (preg_match('#(\d{2}/\d{2}/\d{4})#', $value, $m)) {
                            $value = function_exists('poke_hub_normalize_release_date')
                                ? poke_hub_normalize_release_date($m[1])
                                : $m[1];
                        }
                        if ($value !== '') {
                            $new_release[$key] = $value;
                        }
                    }
                }
                $existing_extra['release'] = $new_release;
                $extra_json = wp_json_encode($existing_extra, JSON_UNESCAPED_UNICODE);
                if (!$dry_run) {
                    $wpdb->update($pokemon_table, ['extra' => $extra_json], ['id' => $pokemon_id], ['%s'], ['%d']);
                }
                $updated++;
                $summary = [];
                foreach ($release as $k => $v) {
                    if ($v !== '') {
                        $summary[] = $k . '=' . substr($v, 0, 20) . (strlen($v) > 20 ? '…' : '');
                    }
                }
                $log[] = "  [OK] méga {$url_slug}-{$dex_number}m : " . implode(', ', $summary);
                if ($delay > 0) {
                    sleep($delay);
                }
            }
        }
    }

    $log[] = '';
    $log[] = "Terminé. Mis à jour : {$updated}, ignorés : {$skipped}, erreurs : {$errors}";
    if ($dry_run) {
        $log[] = '(Aucune modification en base : mode dry-run)';
    }

    return [
        'log'           => $log,
        'updated'       => $updated,
        'skipped'       => $skipped,
        'errors'        => $errors,
        'species_count' => count($species),
    ];
}
