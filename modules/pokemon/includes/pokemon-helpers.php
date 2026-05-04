<?php
// modules/pokemon/functions/pokemon-helpers.php

if (!defined('ABSPATH')) { exit; }

/**
 * Décode un JSON `extra` de façon sûre.
 *
 * @param mixed $raw
 * @param bool|null $is_valid
 * @return array
 */
function poke_hub_pokemon_decode_extra_json($raw, &$is_valid = null): array {
    $raw = (string) $raw;
    if ($raw === '') {
        $is_valid = true;
        return [];
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $is_valid = true;
        return $decoded;
    }

    $is_valid = false;
    return [];
}

/**
 * Encode un tableau `extra` en JSON.
 * En cas d'échec d'encodage, conserve le JSON brut existant si fourni.
 *
 * @param array $extra
 * @param mixed $fallback_raw
 * @return string|null
 */
function poke_hub_pokemon_encode_extra_json(array $extra, $fallback_raw = ''): ?string {
    $encoded = wp_json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($encoded)) {
        return $encoded;
    }

    $fallback_raw = (string) $fallback_raw;
    if ($fallback_raw !== '') {
        return $fallback_raw;
    }

    return null;
}

/**
 * Liste simple de tous les Pokémon pour le select admin.
 */
function pokehub_get_all_pokemon_for_select(): array {
    global $wpdb;

    $pokemon_table = pokehub_get_table('pokemon');
    $form_variants_table = pokehub_get_table('pokemon_form_variants');

    $fam_sql = function_exists('pokehub_pokemon_sql_exclude_family_placeholder_slug_expr')
        ? pokehub_pokemon_sql_exclude_family_placeholder_slug_expr('p.slug')
        : "( LENGTH(TRIM(COALESCE(p.slug, ''))) < 7 OR RIGHT(LOWER(TRIM(COALESCE(p.slug, ''))), 7) <> '-family' )";

    // Récupérer le label de la forme depuis pokemon_form_variants si form_variant_id > 0
    $rows = $wpdb->get_results(
        "SELECT p.id, 
                p.dex_number, 
                p.name_fr,
                p.name_en,
                p.form_variant_id,
                p.extra,
                COALESCE(fv.label, fv.form_slug, '') AS form,
                COALESCE(fv.category, 'normal') AS form_category
         FROM {$pokemon_table} p
         LEFT JOIN {$form_variants_table} fv ON p.form_variant_id = fv.id
         WHERE {$fam_sql}
         ORDER BY p.dex_number ASC, p.name_fr ASC, p.name_en ASC",
        ARRAY_A
    );
    if (!is_array($rows)) {
        return [];
    }

    if (!empty($rows) && function_exists('pokehub_sort_pokemon_select_rows')) {
        pokehub_sort_pokemon_select_rows($rows);
    }

    if ($rows !== []) {
        foreach ($rows as &$row) {
            $extra = [];
            if (!empty($row['extra'])) {
                $decoded = json_decode((string) $row['extra'], true);
                if (is_array($decoded)) {
                    $extra = $decoded;
                }
            }
            $row['is_regional'] = !empty($extra['regional']['is_regional']) ? 1 : 0;
            unset($row['extra']);
        }
        unset($row);
    }

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
            'gmax'    => [],
        ];
    }

    if (!function_exists('pokehub_get_table')) {
        return [
            'fast'    => [],
            'charged' => [],
            'special' => [],
            'gmax'    => [],
        ];
    }

    global $wpdb;

    $table_links = pokehub_get_table('pokemon_attack_links');
    if (!$table_links) {
        return [
            'fast'    => [],
            'charged' => [],
            'special' => [],
            'gmax'    => [],
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
        'gmax'    => [],
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
function poke_hub_pokemon_sync_pokemon_attacks($pokemon_id, array $fast_moves, array $charged_moves, array $gmax_moves = []) {
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
        $normalize($charged_moves, 'charged'),
        $normalize($gmax_moves, 'gmax')
    );

    if (empty($rows_to_insert)) {
        return;
    }

    // On ne remplace que les rôles gérés par ce formulaire.
    // Les liens "special" (ou autres rôles personnalisés) sont préservés.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$table_links}
             WHERE pokemon_id = %d
               AND role IN ('fast', 'charged', 'gmax')",
            $pokemon_id
        )
    );

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

/** @var string Nom d’option : form_slug dont l’import ne doit pas recréer la ligne après suppression admin. */
const POKE_HUB_GM_SUPPRESSED_FORM_SLUG_OPTION = 'poke_hub_gm_suppressed_form_slugs';

/** @var string Texte brut des alias slug variante GM → registre canonique (une ou plusieurs lignes, voir parsing). */
const POKE_HUB_GM_VARIANT_REGISTRY_SLUG_ALIASES_LINES_OPTION = 'poke_hub_gm_variant_registry_slug_aliases_lines';

/**
 * Slugs suppression stockés en base sans filtre (évite boucle lors des filtres apply_filters).
 *
 * @return string[]
 */
function poke_hub_get_stored_gm_suppressed_form_slugs(): array {
    if (!function_exists('get_option')) {
        return [];
    }
    $raw = get_option(POKE_HUB_GM_SUPPRESSED_FORM_SLUG_OPTION, []);
    $set = [];
    foreach ((array) $raw as $s) {
        $s = sanitize_title(trim((string) $s));
        if ($s !== '') {
            $set[$s] = true;
        }
    }

    return array_keys($set);
}

/**
 * Liste effective (filtre poke_hub_gm_suppressed_form_slugs) pour contrôler INSERT import.
 *
 * @return string[]
 */
function poke_hub_get_gm_suppressed_form_slugs(): array {
    return array_values(
        array_unique(
            apply_filters(
                'poke_hub_gm_suppressed_form_slugs',
                poke_hub_get_stored_gm_suppressed_form_slugs()
            )
        )
    );
}

/**
 * Marque un form_slug pour ne pas être INSERT par l’import après suppressions manuelles.
 */
function poke_hub_suppress_form_slug_from_gm_auto_create(string $form_slug): void {
    $slug = sanitize_title(trim($form_slug));
    if ($slug === '' || !function_exists('get_option')) {
        return;
    }
    $list   = poke_hub_get_stored_gm_suppressed_form_slugs();
    $list[] = $slug;
    update_option(
        POKE_HUB_GM_SUPPRESSED_FORM_SLUG_OPTION,
        array_values(array_unique($list)),
        false
    );
}

/**
 * Autorise à nouveau la création auto après ajout / édition manuel(le) avec ce slug.
 */
function poke_hub_unsuppress_form_slug_for_gm_auto_create(string $form_slug): void {
    $slug = sanitize_title(trim($form_slug));
    if ($slug === '' || !function_exists('get_option')) {
        return;
    }
    $fresh = [];
    foreach (poke_hub_get_stored_gm_suppressed_form_slugs() as $s) {
        if ($s !== $slug) {
            $fresh[] = $s;
        }
    }
    update_option(POKE_HUB_GM_SUPPRESSED_FORM_SLUG_OPTION, array_values(array_unique($fresh)), false);
}

/**
 * Parse le texte d’alias (Réglages Game Master) vers un tableau sanitizé granular => canonique.
 *
 * Lignes reconnues (une paire par ligne ; ignorer les lignes vides ou commençant par #):
 * - `slug-granulaire=>slug-canonical`
 * - `slug-granulaire slug-canonical` (deux premiers jetons whitespace)
 *
 * @return array<string,string>
 */
function poke_hub_parse_gm_variant_registry_slug_aliases_lines(string $text): array {
    $out = [];
    foreach (preg_split("/\r\n|\r|\n/", $text) as $raw_line) {
        $line = trim((string) $raw_line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        $gran = '';
        $canon = '';
        if (strpos($line, '=>') !== false) {
            $parts = explode('=>', $line, 2);
            $gran  = sanitize_title(trim((string) ($parts[0] ?? '')));
            $canon = sanitize_title(trim((string) ($parts[1] ?? '')));
        } elseif (preg_match('/^(\S+)\s+(\S+)/', $line, $m)) {
            $gran  = sanitize_title($m[1]);
            $canon = sanitize_title($m[2]);
        }
        if ($gran !== '' && $canon !== '' && $gran !== $canon) {
            $out[$gran] = $canon;
        }
    }

    return $out;
}

/**
 * Carte effective slug GM (granulaire) → slug de la ligne pokemon_form_variants (registre).
 *
 * @return array<string,string>
 */
function poke_hub_get_gm_variant_registry_slug_aliases_map(): array {
    static $revision = '';
    static $cached   = [];

    $text = function_exists('get_option')
        ? (string) get_option(POKE_HUB_GM_VARIANT_REGISTRY_SLUG_ALIASES_LINES_OPTION, '')
        : '';

    $new_rev = md5($text);
    if ($new_rev !== $revision) {
        $parsed = poke_hub_parse_gm_variant_registry_slug_aliases_lines($text);
        /** @var array<string,string> $filtered */
        $filtered   = apply_filters('poke_hub_gm_variant_registry_slug_aliases_map', $parsed);
        $normalized = [];
        foreach ((array) $filtered as $k => $v) {
            $gk = sanitize_title((string) $k);
            $gv = sanitize_title((string) $v);
            if ($gk !== '' && $gv !== '' && $gk !== $gv) {
                $normalized[$gk] = $gv;
            }
        }
        $revision = $new_rev;
        $cached   = $normalized;
    }

    return $cached;
}

/**
 * Resolve le slug registre utilisé pour upsert pokemon_form_variants (fusion optionnelle de formes GM).
 *
 * Le slug Pokémon (extra.form_slug et suffix URL) reste le slug granular ; uniquement la ligne de variante partagée change.
 *
 * @param array<string,mixed> $settings
 */
function poke_hub_resolve_gm_variant_registry_slug(
    string $granular_slug,
    string $pokemon_id_proto = '',
    string $template_id = '',
    string $form_proto = '',
    array $settings = []
): string {
    $granular_slug = sanitize_title($granular_slug);
    if ($granular_slug === '') {
        return '';
    }

    $map       = poke_hub_get_gm_variant_registry_slug_aliases_map();
    $resolved  = isset($map[$granular_slug]) ? $map[$granular_slug] : $granular_slug;
    $resolved  = sanitize_title((string) $resolved);
    if ($resolved === '') {
        return $granular_slug;
    }

    $filtered = apply_filters(
        'poke_hub_gm_variant_registry_slug',
        $resolved,
        $granular_slug,
        $pokemon_id_proto,
        $template_id,
        $form_proto,
        $settings
    );
    $out = sanitize_title((string) $filtered);

    return $out !== '' ? $out : $granular_slug;
}

/**
 * Upsert d'une variante de forme globale dans pokemon_form_variants.
 *
 * @param string $form_slug   Slug de forme (ex: 'fall-2019')
 * @param string $label       Label humain (ex: 'Fall 2019')
 * @param string $category    Catégorie logique ('normal', 'costume', 'mega', etc.) - optionnel
 * @param string $group       Groupe logique (ex: 'halloween-2019') - optionnel
 * @param array  $extra_data  Données additionnelles stockées dans extra (fusionnées avec l’existant)
 * @param int    $variant_id  Si > 0 (édition admin), charge la ligne par id pour permettre de changer form_slug sans créer une nouvelle ligne.
 * @param bool   $enforce_suppressed_slug_guard Si faux (sauvegarde admin), autorise INSERT même si ce slug est dans la liste post-suppression.
 * @param string|null $gm_import_granular_slug_for_suppress Import GM uniquement : slug granular (tel que normalisé depuis le GM)
 *                                                           pour tester la liste des slugs supprimés, si différent du form_slug registre après alias.
 *
 * Si `extra.manual_variant_label` est vrai pour une ligne existante, la colonne label n’est pas remplacée par ce paramètre (import GM).
 *
 * Si le slug est absent de la base et listé comme supprim volontaire en admin (`poke_hub_suppress_form_slug_from_gm_auto_create`), aucun INSERT n’est fait (retour 0).
 *
 * @return int ID de la variante ou 0 (ex. slug déjà pris par une autre ligne, ou création réprimée pour slug supprimé)
 */
function poke_hub_pokemon_upsert_form_variant(
    $form_slug,
    $label = '',
    $category = '',
    $group = '',
    array $extra_data = [],
    $variant_id = 0,
    bool $enforce_suppressed_slug_guard = true,
    ?string $gm_import_granular_slug_for_suppress = null
) {
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

    $variant_id = (int) $variant_id;
    $row        = null;
    if ($variant_id > 0) {
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
                $variant_id
            )
        );
    }
    if (!$row && $variant_id > 0) {
        return 0;
    }
    if (!$row) {
        // Import / ajout : retrouver par slug (comportement historique).
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE form_slug = %s LIMIT 1",
                $form_slug
            )
        );
    }

    $current_extra = [];
    if ($row && !empty($row->extra)) {
        $ev_ok = true;
        if (function_exists('poke_hub_pokemon_decode_extra_json')) {
            $current_extra = poke_hub_pokemon_decode_extra_json((string) $row->extra, $ev_ok);
            if (!$ev_ok) {
                $current_extra = [];
            }
        } else {
            $decoded_ce = json_decode((string) $row->extra, true);
            $current_extra = is_array($decoded_ce) ? $decoded_ce : [];
        }
    }

    $suppress_check_slug = $form_slug;
    if ($gm_import_granular_slug_for_suppress !== null && $gm_import_granular_slug_for_suppress !== '') {
        $suppress_check_slug = sanitize_title(trim($gm_import_granular_slug_for_suppress));
    }
    $suppress_slug = $enforce_suppressed_slug_guard && !$row && in_array($suppress_check_slug, poke_hub_get_gm_suppressed_form_slugs(), true);
    if ($suppress_slug) {
        return 0;
    }

    $preserve_manual_label = $row && !empty($current_extra['manual_variant_label']);

    // Label par défaut
    if ($preserve_manual_label) {
        $label = (string) $row->label;
    } elseif ($label === '' && $row) {
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

    // Merge extra (existant ci-dessus fusionné avec le nouveau ; garde manual_variant_label si non écrasé)
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
        $row_id = (int) $row->id;
        $dup    = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE form_slug = %s AND id <> %d LIMIT 1",
                $form_slug,
                $row_id
            )
        );
        if ($dup) {
            return 0;
        }
        $wpdb->update(
            $table,
            $data,
            ['id' => $row_id],
            $format,
            ['%d']
        );
        return $row_id;
    }

    $wpdb->insert($table, $data, $format);
    return (int) $wpdb->insert_id;
}

/**
 * Trouve ou crée une ligne dans la table `regions` (Kanto, Hisui, Paldea…).
 * Utilisé pour `pokemon.origin_region_id` et filtres par région « jeu ».
 *
 * @param string $slug     ex. hisui, paldea
 * @param string $label_en Libellé EN si création
 * @return int ID région ou 0
 */
function poke_hub_pokemon_find_or_create_game_region_by_slug(string $slug, string $label_en = ''): int {
    if (!function_exists('pokehub_get_table')) {
        return 0;
    }

    global $wpdb;

    $slug = sanitize_title($slug);
    if ($slug === '') {
        return 0;
    }

    $table = pokehub_get_table('regions');
    if (!$table) {
        return 0;
    }

    $id = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
            $slug
        )
    );
    if ($id > 0) {
        return $id;
    }

    if ($label_en === '') {
        $label_en = ucwords(str_replace('-', ' ', $slug));
    }

    $wpdb->insert(
        $table,
        [
            'slug'       => $slug,
            'name_en'    => $label_en,
            'name_fr'    => '',
            'sort_order' => 0,
            'extra'      => '',
        ],
        ['%s', '%s', '%s', '%d', '%s']
    );

    return (int) $wpdb->insert_id;
}

/**
 * Région d’origine « par défaut » pour une génération (liaison N–N ou colonne generations.region_id).
 * Utilisé par l’import Game Master quand le dex n’a pas de cas spécial GO (ex. Hisui 899–905).
 *
 * @return array{id: int, slug: string} slug vide possible si la ligne regions est absente
 */
function poke_hub_pokemon_get_default_origin_region_for_generation(int $generation_id): array {
    global $wpdb;

    if ($generation_id <= 0 || !function_exists('pokehub_get_table')) {
        return ['id' => 0, 'slug' => ''];
    }

    $regions_table = pokehub_get_table('regions');
    $gens_table     = pokehub_get_table('generations');
    $gr_table       = pokehub_get_table('generation_regions');
    if ($regions_table === '' || $gens_table === '') {
        return ['id' => 0, 'slug' => ''];
    }

    $rid = 0;
    if (
        $gr_table !== ''
        && function_exists('pokehub_table_exists')
        && pokehub_table_exists($gr_table)
        && function_exists('poke_hub_get_generation_region_ids_ordered')
    ) {
        $ordered = poke_hub_get_generation_region_ids_ordered($generation_id);
        if (!empty($ordered)) {
            $rid = (int) $ordered[0];
        }
    }

    if ($rid <= 0) {
        $rid = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT region_id FROM {$gens_table} WHERE id = %d LIMIT 1",
                $generation_id
            )
        );
    }

    if ($rid <= 0) {
        return ['id' => 0, 'slug' => ''];
    }

    $slug = (string) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT slug FROM {$regions_table} WHERE id = %d LIMIT 1",
            $rid
        )
    );
    $slug = $slug !== '' ? sanitize_title($slug) : '';

    return ['id' => $rid, 'slug' => $slug];
}

/**
 * IDs des régions « jeu » liées à une génération (table generation_regions), ordre admin.
 *
 * @return int[]
 */
function poke_hub_get_generation_region_ids_ordered(int $generation_id): array {
    global $wpdb;

    if ($generation_id <= 0 || !function_exists('pokehub_get_table')) {
        return [];
    }

    $gr = pokehub_get_table('generation_regions');
    if (!$gr) {
        return [];
    }

    $sql = "SELECT region_id FROM {$gr} WHERE generation_id = %d ORDER BY sort_order ASC, region_id ASC";

    $cols = $wpdb->get_col($wpdb->prepare($sql, $generation_id));

    if (!is_array($cols)) {
        return [];
    }

    $out = [];
    foreach ($cols as $v) {
        $rid = (int) $v;
        if ($rid > 0) {
            $out[] = $rid;
        }
    }

    return $out;
}

/**
 * Remplace les liaisons génération ↔ régions. Met à jour aussi generations.region_id avec la 1re région (rétrocompat).
 */
function poke_hub_sync_generation_region_links(int $generation_id, array $region_ids): void {
    global $wpdb;

    if ($generation_id <= 0 || !function_exists('pokehub_get_table')) {
        return;
    }

    $gr   = pokehub_get_table('generation_regions');
    $gens = pokehub_get_table('generations');
    if (!$gr || !$gens) {
        return;
    }

    $region_ids = array_values(array_unique(array_filter(array_map('intval', $region_ids))));

    $wpdb->delete($gr, ['generation_id' => $generation_id], ['%d']);

    $sort = 0;
    foreach ($region_ids as $rid) {
        if ($rid <= 0) {
            continue;
        }
        $wpdb->insert(
            $gr,
            [
                'generation_id' => $generation_id,
                'region_id'     => $rid,
                'sort_order'    => $sort,
            ],
            ['%d', '%d', '%d']
        );
        ++$sort;
    }

    $first = $region_ids[0] ?? 0;

    $wpdb->update(
        $gens,
        ['region_id' => $first > 0 ? $first : 0],
        ['id' => $generation_id],
        ['%d'],
        ['%d']
    );
}

/**
 * IDs des Pokémon ayant cette région d’origine (`origin_region_id`).
 *
 * @return int[]
 */
function poke_hub_pokemon_get_ids_by_origin_region_id(int $region_id): array {
    global $wpdb;

    if ($region_id <= 0 || !function_exists('pokehub_get_table')) {
        return [];
    }

    $t = pokehub_get_table('pokemon');
    if (!$t) {
        return [];
    }

    $rows = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT id FROM {$t} WHERE origin_region_id = %d ORDER BY dex_number ASC, slug ASC",
            $region_id
        )
    );

    if (!is_array($rows)) {
        return [];
    }

    $out = array_values(array_unique(array_map('intval', $rows)));

    return apply_filters('poke_hub_pokemon_ids_by_origin_region_id', $out, $region_id);
}

/**
 * Comme {@see poke_hub_pokemon_get_ids_by_origin_region_id()} mais par slug `regions`.
 *
 * @return int[]
 */
function poke_hub_pokemon_get_ids_by_origin_region_slug(string $slug): array {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }

    global $wpdb;

    $slug = sanitize_title($slug);
    if ($slug === '') {
        return [];
    }

    $regions = pokehub_get_table('regions');
    $pokemon = pokehub_get_table('pokemon');
    if (!$regions || !$pokemon) {
        return [];
    }

    $region_id = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$regions} WHERE slug = %s LIMIT 1",
            $slug
        )
    );

    return poke_hub_pokemon_get_ids_by_origin_region_id($region_id);
}

/**
 * Résout `origin_region_id` pour une ligne objet/array Pokémon (colonne en priorité).
 *
 * @param object|array|null $row Ligne stdClass ou tableau avec origin_region_id / extra
 * @param string              $raw_extra JSON extra si $row ne l’expose pas
 */
function poke_hub_pokemon_resolve_origin_region_id($row, string $raw_extra = ''): int {
    if (is_object($row) && isset($row->origin_region_id)) {
        return max(0, (int) $row->origin_region_id);
    }
    if (is_array($row) && array_key_exists('origin_region_id', $row)) {
        return max(0, (int) $row['origin_region_id']);
    }

    $raw = $raw_extra;
    if ($raw === '' && is_object($row) && isset($row->extra)) {
        $raw = (string) $row->extra;
    }
    if ($raw === '' && is_array($row) && isset($row['extra'])) {
        $raw = (string) $row['extra'];
    }

    if ($raw === '') {
        return 0;
    }

    $valid = true;
    $extra = poke_hub_pokemon_decode_extra_json($raw, $valid);
    if (!$valid || empty($extra['origin_region_slug'])) {
        return 0;
    }

    global $wpdb;
    $slug = sanitize_title((string) $extra['origin_region_slug']);
    if ($slug === '' || !function_exists('pokehub_get_table')) {
        return 0;
    }

    $regions = pokehub_get_table('regions');
    if (!$regions) {
        return 0;
    }

    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$regions} WHERE slug = %s LIMIT 1",
            $slug
        )
    );
}

/**
 * NOTE: La fonction poke_hub_pokemon_get_scatterbug_patterns() a été déplacée
 * dans includes/functions/pokemon-public-helpers.php pour être disponible
 * même si le module Pokémon n'est pas actif.
 * 
 * Cette fonction est utilisée par le module user-profiles et doit être
 * accessible dès l'activation du plugin.
 */

