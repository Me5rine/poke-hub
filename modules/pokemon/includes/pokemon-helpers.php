<?php
// modules/pokemon/functions/pokemon-helpers.php

if (!defined('ABSPATH')) { exit; }

/**
 * Liste simple de tous les Pokémon pour le select admin.
 */
function pokehub_get_all_pokemon_for_select(): array {
    global $wpdb;

    $pokemon_table = pokehub_get_table('pokemon');
    $form_variants_table = pokehub_get_table('pokemon_form_variants');

    // Récupérer le label de la forme depuis pokemon_form_variants si form_variant_id > 0
    $rows = $wpdb->get_results(
        "SELECT p.id, 
                p.dex_number, 
                p.name_fr,
                p.name_en,
                p.form_variant_id,
                COALESCE(fv.label, fv.form_slug, '') AS form
         FROM {$pokemon_table} p
         LEFT JOIN {$form_variants_table} fv ON p.form_variant_id = fv.id
         ORDER BY p.dex_number ASC, p.name_fr ASC, p.name_en ASC",
        ARRAY_A
    );
    
    // Construire le nom au format "nom-fr (nom-anglais)" si les deux sont disponibles
    foreach ($rows as &$row) {
        $name_fr = (string) ($row['name_fr'] ?? '');
        $name_en = (string) ($row['name_en'] ?? '');
        if ($name_fr !== '' && $name_en !== '' && $name_fr !== $name_en) {
            $row['name'] = $name_fr . ' (' . $name_en . ')';
        } elseif ($name_fr !== '') {
            $row['name'] = $name_fr;
        } else {
            $row['name'] = $name_en;
        }
    }
    unset($row);

    return $rows ?: [];
}

/**
 * Attaques spéciales pour un Pokémon donné (événementiel).
 * On s'appuie sur pokemon_attack_links avec is_event=1 ou role='special'.
 */
function pokehub_get_pokemon_special_attacks(int $pokemon_id): array {
    global $wpdb;

    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return [];
    }

    // ✅ Nouveau helper de table
    $links_table   = pokehub_get_table('pokemon_attack_links');
    $attacks_table = pokehub_get_table('attacks');

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT a.id, COALESCE(NULLIF(a.name_fr, ''), a.name_en) AS name
            FROM {$links_table} l
            INNER JOIN {$attacks_table} a
                ON a.id = l.attack_id
            WHERE l.pokemon_id = %d
              AND (l.is_event = 1 OR l.role = %s)
            ORDER BY name ASC
            ",
            $pokemon_id,
            'special'
        ),
        ARRAY_A
    );

    return $rows ?: [];
}

/**
 * Retourne les slugs de météo qui boostent ce Pokémon,
 * calculés à partir de ses types.
 *
 * @param object $pokemon  row pokemon
 * @return string[]  ex: ['sunny', 'rain']
 */
function poke_hub_pokemon_get_weather_boosts_for_pokemon($pokemon) {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }

    global $wpdb;

    $table_rel   = pokehub_get_table('pokemon_type_relations');
    $table_types = pokehub_get_table('pokemon_types');

    if (!$table_rel || !$table_types) {
        return [];
    }

    $pokemon_id = (int) $pokemon->id;
    if ($pokemon_id <= 0) {
        return [];
    }

    // Récupère les types du Pokémon
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.*
             FROM {$table_rel} AS rel
             INNER JOIN {$table_types} AS t ON t.id = rel.type_id
             WHERE rel.pokemon_id = %d",
            $pokemon_id
        )
    );

    $weather_slugs = [];

    foreach ($rows as $type_row) {
        $extra = [];
        if (!empty($type_row->extra)) {
            $decoded = json_decode($type_row->extra, true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }
        $boosts = $extra['go']['weather_boosts'] ?? [];
        if (is_array($boosts)) {
            $weather_slugs = array_merge($weather_slugs, $boosts);
        }
    }

    $weather_slugs = array_values(array_unique($weather_slugs));

    return $weather_slugs;
}

/**
 * Récupère les attaques liées à un Pokémon.
 *
 * Retourne un tableau du type :
 * [
 *   'fast'    => [ [ 'attack_id' => 123, 'is_legacy' => 0, 'is_event' => 0, 'is_elite_tm' => 0 ], ... ],
 *   'charged' => [ ... ],
 *   'special' => [ ... ], // au cas où tu utilises ce rôle plus tard
 * ]
 *
 * @param int $pokemon_id
 * @return array
 */
function poke_hub_pokemon_get_pokemon_attacks($pokemon_id) {
    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return [
            'fast'    => [],
            'charged' => [],
            'special' => [],
        ];
    }

    if (!function_exists('pokehub_get_table')) {
        return [
            'fast'    => [],
            'charged' => [],
            'special' => [],
        ];
    }

    global $wpdb;

    $table_links = pokehub_get_table('pokemon_attack_links');
    if (!$table_links) {
        return [
            'fast'    => [],
            'charged' => [],
            'special' => [],
        ];
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT attack_id, role, is_legacy, is_event, is_elite_tm
             FROM {$table_links}
             WHERE pokemon_id = %d",
            $pokemon_id
        ),
        ARRAY_A
    );

    $result = [
        'fast'    => [],
        'charged' => [],
        'special' => [],
    ];

    if (empty($rows)) {
        return $result;
    }

    foreach ($rows as $row) {
        $role = isset($row['role']) && $row['role'] !== '' ? $row['role'] : 'fast';

        if (!isset($result[$role])) {
            $result[$role] = [];
        }

        $result[$role][] = [
            'attack_id'   => (int) $row['attack_id'],
            'is_legacy'   => !empty($row['is_legacy']) ? 1 : 0,
            'is_event'    => !empty($row['is_event']) ? 1 : 0,
            'is_elite_tm' => !empty($row['is_elite_tm']) ? 1 : 0,
        ];
    }

    return $result;
}

/**
 * Synchronise les liens Pokémon ↔ attaques pour un Pokémon donné.
 *
 * @param int   $pokemon_id
 * @param array $fast_moves    Tableau brut venant de $_POST['fast_moves']
 * @param array $charged_moves Tableau brut venant de $_POST['charged_moves']
 */
function poke_hub_pokemon_sync_pokemon_attacks($pokemon_id, array $fast_moves, array $charged_moves) {
    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return;
    }

    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;

    $table_links = pokehub_get_table('pokemon_attack_links');
    if (!$table_links) {
        return;
    }

    // On supprime toutes les anciennes lignes pour ce Pokémon
    $wpdb->delete($table_links, ['pokemon_id' => $pokemon_id], ['%d']);

    $rows_to_insert = [];

    $normalize = function(array $raw_list, string $role) {
        $normalized = [];

        foreach ($raw_list as $row) {
            if (!is_array($row)) {
                continue;
            }

            $attack_id = isset($row['attack_id']) ? (int) $row['attack_id'] : 0;
            if ($attack_id <= 0) {
                continue;
            }

            $is_legacy   = !empty($row['is_legacy']) ? 1 : 0;
            $is_event    = !empty($row['is_event']) ? 1 : 0;     // pas exposé dans le formulaire pour l’instant
            $is_elite_tm = !empty($row['is_elite_tm']) ? 1 : 0; // idem

            $normalized[] = [
                'attack_id'   => $attack_id,
                'role'        => $role,
                'is_legacy'   => $is_legacy,
                'is_event'    => $is_event,
                'is_elite_tm' => $is_elite_tm,
            ];
        }

        return $normalized;
    };

    $rows_to_insert = array_merge(
        $normalize($fast_moves, 'fast'),
        $normalize($charged_moves, 'charged')
    );

    if (empty($rows_to_insert)) {
        return;
    }

    foreach ($rows_to_insert as $row) {
        $wpdb->insert(
            $table_links,
            [
                'pokemon_id'  => $pokemon_id,
                'attack_id'   => $row['attack_id'],
                'role'        => $row['role'],
                'is_legacy'   => $row['is_legacy'],
                'is_event'    => $row['is_event'],
                'is_elite_tm' => $row['is_elite_tm'],
                'extra'       => null,
            ],
            ['%d', '%d', '%s', '%d', '%d', '%d', '%s']
        );
    }
}

/**
 * Retourne la table des variantes de formes.
 *
 * @return string
 */
function poke_hub_pokemon_get_form_variants_table(): string {
    if (!function_exists('pokehub_get_table')) {
        return '';
    }
    return pokehub_get_table('pokemon_form_variants');
}

/**
 * Retourne la variante associée à un form_slug.
 *
 * @param string $form_slug
 * @return array|null
 */
function poke_hub_pokemon_get_form_variant(string $form_slug): ?array {
    $form_slug = trim($form_slug);
    if ($form_slug === '') {
        // convention: forme de base
        return [
            'form_slug' => '',
            'category'  => 'normal',
            'group'     => '',
            'label'     => '',
            'extra'     => null,
        ];
    }

    $table = poke_hub_pokemon_get_form_variants_table();
    if (!$table) {
        return null;
    }

    global $wpdb;

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE form_slug = %s LIMIT 1",
            $form_slug
        ),
        ARRAY_A
    );

    if (!$row) {
        return null;
    }

    return $row;
}

/**
 * Retourne la liste des catégories existantes (distinct category)
 * + éventuellement quelques valeurs par défaut si la table est vide.
 *
 * @return string[] slug => label
 */
function poke_hub_pokemon_get_variant_categories(): array {
    $table = poke_hub_pokemon_get_form_variants_table();
    $base  = [
        'normal' => __('Normal', 'poke-hub'),
    ];

    if (!$table) {
        return $base;
    }

    global $wpdb;

    $cats = $wpdb->get_col("SELECT DISTINCT category FROM {$table} ORDER BY category ASC");
    if (empty($cats)) {
        return $base;
    }

    $out = [];
    foreach ($cats as $cat) {
        $cat = (string) $cat;
        if ($cat === '') {
            continue;
        }
        // Label simple : ucfirst + filtre pour surcharger
        $label = apply_filters(
            'poke_hub_pokemon_variant_category_label',
            ucfirst(str_replace('_', ' ', $cat)),
            $cat
        );
        $out[$cat] = $label;
    }

    // On s'assure que "normal" est toujours présent
    if (!isset($out['normal'])) {
        $out = array_merge(['normal' => $base['normal']], $out);
    }

    return $out;
}

/**
 * Retourne les groupes distincts pour une catégorie donnée.
 *
 * @param string $category
 * @return string[]
 */
function poke_hub_pokemon_get_variant_groups_for_category(string $category): array {
    $table = poke_hub_pokemon_get_form_variants_table();
    if (!$table) {
        return [];
    }

    global $wpdb;

    $category = sanitize_key($category);

    $rows = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT `group` FROM {$table} WHERE category = %s AND `group` <> '' ORDER BY `group` ASC",
            $category
        )
    );

    if (!$rows) {
        return [];
    }

    return array_values(array_unique(array_map('sanitize_title', $rows)));
}

/**
 * Retourne tous les groupes connus (toutes catégories) – utile pour autocomplétion.
 *
 * @return string[]
 */
function poke_hub_pokemon_get_all_variant_groups(): array {
    $table = poke_hub_pokemon_get_form_variants_table();
    if (!$table) {
        return [];
    }

    global $wpdb;

    $rows = $wpdb->get_col("SELECT DISTINCT `group` FROM {$table} WHERE `group` <> '' ORDER BY `group` ASC");
    if (!$rows) {
        return [];
    }

    return array_values(array_unique(array_map('sanitize_title', $rows)));
}

/**
 * Upsert d'une variante de forme globale dans pokemon_form_variants.
 *
 * @param string $form_slug  Slug de forme (ex: 'fall-2019')
 * @param string $label      Label humain (ex: 'Fall 2019')
 * @param string $category   Catégorie logique ('normal', 'costume', 'mega', etc.) - optionnel
 * @param string $group      Groupe logique (ex: 'halloween-2019') - optionnel
 * @param array  $extra_data Données additionnelles stockées dans extra
 *
 * @return int ID de la variante ou 0
 */
function poke_hub_pokemon_upsert_form_variant($form_slug, $label = '', $category = '', $group = '', array $extra_data = []) {
    if (!function_exists('pokehub_get_table')) {
        return 0;
    }

    global $wpdb;

    $table = pokehub_get_table('pokemon_form_variants');
    if (!$table) {
        return 0;
    }

    $form_slug = sanitize_title((string) $form_slug);
    if ($form_slug === '') {
        return 0;
    }

    // Récupère éventuelle ligne existante
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE form_slug = %s LIMIT 1",
            $form_slug
        )
    );

    // Label par défaut
    if ($label === '' && $row) {
        $label = (string) $row->label;
    } elseif ($label === '') {
        $label = ucwords(str_replace(['-', '_'], ' ', $form_slug));
    }

    // Catégorie par défaut
    if ($category === '') {
        $category = $row ? (string) $row->category : 'special';
    }

    // Group par défaut
    if ($group === '' && $row) {
        $group = (string) $row->group;
    }

    // Merge extra (on fusionne l'existant avec le nouveau)
    $current_extra = [];
    if ($row && !empty($row->extra)) {
        $decoded = json_decode($row->extra, true);
        if (is_array($decoded)) {
            $current_extra = $decoded;
        }
    }

    $extra = array_merge($current_extra, $extra_data);
    $extra_json = !empty($extra) ? wp_json_encode($extra) : null;

    $data = [
        'form_slug' => $form_slug,
        'category'  => $category,
        'group'     => $group,
        'label'     => $label,
        'extra'     => $extra_json,
    ];

    $format = ['%s','%s','%s','%s','%s'];

    if ($row) {
        $wpdb->update(
            $table,
            $data,
            ['id' => (int) $row->id],
            $format,
            ['%d']
        );
        return (int) $row->id;
    }

    $wpdb->insert($table, $data, $format);
    return (int) $wpdb->insert_id;
}

/**
 * Get Scatterbug/Vivillon patterns from database.
 * Only returns patterns marked as regional (extra->regional->is_regional = true).
 * Patterns are stored as form variants for Scatterbug (dex_number 664) and Vivillon (dex_number 666).
 *
 * @return array Associative array form_slug => label (French or English name)
 */
function poke_hub_pokemon_get_scatterbug_patterns(): array {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }

    global $wpdb;

    $pokemon_table = pokehub_get_table('pokemon');
    $form_variants_table = pokehub_get_table('pokemon_form_variants');

    if (!$pokemon_table || !$form_variants_table) {
        return [];
    }

    // Get form variants for Scatterbug (664) and Vivillon (666)
    // Only those marked as regional (extra->regional->is_regional = true)
    $patterns = $wpdb->get_results(
        "SELECT DISTINCT 
            fv.form_slug,
            fv.label,
            COALESCE(p.name_fr, p.name_en, '') AS pokemon_name
        FROM {$pokemon_table} p
        INNER JOIN {$form_variants_table} fv ON p.form_variant_id = fv.id
        WHERE p.dex_number IN (664, 666)
        AND p.form_variant_id > 0
        AND p.extra LIKE '%\"regional\":{\"is_regional\":true%'
        ORDER BY fv.label ASC, fv.form_slug ASC"
    );

    $result = [];
    foreach ($patterns as $pattern) {
        $form_slug = (string) $pattern->form_slug;
        if (empty($form_slug)) {
            continue;
        }

        // Use variant label, otherwise use pokemon name + form_slug
        $label = (string) $pattern->label;
        if (empty($label)) {
            $label = ucwords(str_replace(['-', '_'], ' ', $form_slug));
        }

        $result[$form_slug] = $label;
    }

    // If no patterns found, return empty array
    // (fallback to hardcoded list will be handled in user-profiles if needed)
    return $result;
}

