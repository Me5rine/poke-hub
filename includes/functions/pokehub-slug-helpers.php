<?php
/**
 * Slugs : base depuis libellés, unicité sur une ou plusieurs tables, ou prédicat personnalisé.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base sanitize_title : première chaîne non vide, puis la seconde, puis repli.
 */
function pokehub_slug_base_from_two_strings(string $prefer_first, string $prefer_second, string $fallback = 'item'): string {
    $slug = sanitize_title(trim($prefer_first));
    if ($slug === '') {
        $slug = sanitize_title(trim($prefer_second));
    }
    if ($slug === '') {
        $slug = sanitize_title($fallback);
    }
    if ($slug === '') {
        $slug = $fallback;
    }
    return (string) $slug;
}

/**
 * Base slug : nom anglais, sinon français, sinon repli (shop, bonus, etc.).
 */
function pokehub_slug_base_from_names(string $name_en, string $name_fr, string $fallback = 'item'): string {
    return pokehub_slug_base_from_two_strings($name_en, $name_fr, $fallback);
}

/**
 * Indique si une ligne existe déjà avec ce slug (hors ligne ignorée à l’édition).
 */
function pokehub_slug_row_exists_in_table(
    string $table,
    string $slug_candidate,
    int $ignore_row_id = 0,
    string $slug_column = 'slug',
    string $id_column = 'id'
): bool {
    global $wpdb;

    if ($table === '' || !preg_match('/^[a-z0-9_]+$/i', $slug_column) || !preg_match('/^[a-z0-9_]+$/i', $id_column)) {
        return false;
    }

    $slug = sanitize_title($slug_candidate);
    if ($slug === '') {
        return false;
    }

    if ($ignore_row_id > 0) {
        $found = $wpdb->get_var($wpdb->prepare(
            "SELECT {$id_column} FROM {$table} WHERE {$slug_column} = %s AND {$id_column} <> %d LIMIT 1",
            $slug,
            $ignore_row_id
        ));
    } else {
        $found = $wpdb->get_var($wpdb->prepare(
            "SELECT {$id_column} FROM {$table} WHERE {$slug_column} = %s LIMIT 1",
            $slug
        ));
    }

    return (bool) $found;
}

/**
 * Trouve un slug libre : teste le candidat puis base-N, base-(N+1)…
 *
 * @param callable(string):bool $slug_already_used Retourne true si le slug est pris.
 * @param int                   $first_suffix       Premier suffixe si le slug de base est pris (1 → foo-1, puis foo-2… partout dans le plugin).
 */
function pokehub_unique_slug_with_predicate(
    callable $slug_already_used,
    string $slug_candidate,
    string $empty_fallback = 'item',
    int $first_suffix = 1
): string {
    $slug = sanitize_title($slug_candidate);
    if ($slug === '') {
        $slug = sanitize_title($empty_fallback);
    }
    if ($slug === '') {
        $slug = $empty_fallback;
    }

    $base      = $slug;
    $candidate = $base;
    $n         = $first_suffix;

    while (true) {
        if (!$slug_already_used($candidate)) {
            return $candidate;
        }
        $candidate = $base . '-' . $n;
        $n++;
    }
}

/**
 * Slug unique dans une table (slug, slug-1, slug-2… par défaut). Ignore une ligne (édition).
 *
 * @param string $table Nom de table SQL (issu de pokehub_get_table, non fourni par l’utilisateur).
 * @param int    $first_suffix Premier suffixe si le slug de base est pris (défaut 1 → foo-1, foo-2…).
 */
function pokehub_unique_slug_for_table(
    string $table,
    string $slug_candidate,
    int $ignore_row_id = 0,
    string $slug_column = 'slug',
    string $id_column = 'id',
    string $empty_fallback = 'item',
    int $first_suffix = 1
): string {
    if ($table === '') {
        $slug = sanitize_title($empty_fallback);
        return $slug !== '' ? $slug : $empty_fallback;
    }

    if (!preg_match('/^[a-z0-9_]+$/i', $slug_column) || !preg_match('/^[a-z0-9_]+$/i', $id_column)) {
        $slug = sanitize_title($slug_candidate);
        return $slug !== '' ? $slug : $empty_fallback;
    }

    $predicate = static function (string $c) use ($table, $ignore_row_id, $slug_column, $id_column): bool {
        return pokehub_slug_row_exists_in_table($table, $c, $ignore_row_id, $slug_column, $id_column);
    };

    return pokehub_unique_slug_with_predicate($predicate, $slug_candidate, $empty_fallback, $first_suffix);
}

/**
 * Slug unique en testant plusieurs tables / colonnes (ex. special_events + posts distants).
 *
 * @param list<array{table: string, slug_column?: string, id_column?: string, ignore_row_id?: int}> $specs
 */
function pokehub_unique_slug_across_table_specs(
    string $slug_candidate,
    array $specs,
    string $empty_fallback = 'item',
    int $first_suffix = 1
): string {
    $specs = array_values(
        array_filter(
            $specs,
            static function ($s): bool {
                return is_array($s) && !empty($s['table']);
            }
        )
    );

    if ($specs === []) {
        $slug = sanitize_title($slug_candidate);
        if ($slug === '') {
            $slug = sanitize_title($empty_fallback);
        }
        return $slug !== '' ? $slug : $empty_fallback;
    }

    $predicate = static function (string $c) use ($specs): bool {
        foreach ($specs as $spec) {
            $tbl = (string) $spec['table'];
            $sc  = isset($spec['slug_column']) ? (string) $spec['slug_column'] : 'slug';
            $ic  = isset($spec['id_column']) ? (string) $spec['id_column'] : 'id';
            $ign = isset($spec['ignore_row_id']) ? (int) $spec['ignore_row_id'] : 0;
            if (pokehub_slug_row_exists_in_table($tbl, $c, $ign, $sc, $ic)) {
                return true;
            }
        }
        return false;
    };

    return pokehub_unique_slug_with_predicate($predicate, $slug_candidate, $empty_fallback, $first_suffix);
}
