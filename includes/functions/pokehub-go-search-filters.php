<?php
// includes/functions/pokehub-go-search-filters.php — catalogue filtres recherche Pokémon GO (table locale).

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Valeurs insérées au **premier seed** de la table uniquement (pas utilisées au lecture des phrases côté Collections).
 *
 * @return array<string, string> clés `{code}_fr` / `{code}_en`
 */
function poke_hub_go_search_filters_collection_defaults_flat(): array {
    return [
        'shiny_fr'        => 'chromatique',
        'shiny_en'        => 'shiny',
        'shadow_fr'       => 'obscur',
        'shadow_en'       => 'shadow',
        'purified_fr'     => 'purifié',
        'purified_en'     => 'purified',
        'mega_fr'         => 'méga',
        'mega_en'         => 'mega',
        'gigamax_fr'      => 'gigamax',
        'gigamax_en'      => 'gigantamax',
        'dynamax_fr'      => 'dynamax',
        'dynamax_en'      => 'dynamax',
        'costume_fr'      => 'événement',
        'costume_en'      => 'event',
        'male_fr'         => 'mâle',
        'male_en'         => 'male',
        'female_fr'       => 'femelle',
        'female_en'       => 'female',
        'fond_fr'         => 'fond',
        'fond_en'         => 'background',
        'fond_lieu_fr'    => 'fonddelieu',
        'fond_lieu_en'    => 'locationbackground',
        'fond_special_fr' => 'fonspécial',
        'fond_special_en' => 'specialbackground',
        'fond_dynamax_fr' => 'fond&dynamax',
        'fond_dynamax_en' => 'background&dynamax',
        'fond_gigamax_fr' => 'fond&gigamax',
        'fond_gigamax_en' => 'background&gigantamax',
        'lucky_fr'        => 'chanceux',
        'lucky_en'        => 'lucky',
        'eggsonly_fr'     => 'oeufseulement',
        'eggsonly_en'     => 'eggsonly',
    ];
}

/**
 * Option de révision (invalidation cache PHP / localisation script).
 */
function poke_hub_go_search_filters_bump_cache_revision(): void {
    update_option(
        'poke_hub_go_search_filters_cache_rev',
        (int) get_option('poke_hub_go_search_filters_cache_rev', 0) + 1,
        false
    );
}

/**
 * Révision pour cache query (suffix clé objet cache).
 */
function poke_hub_go_search_filters_cache_revision(): int {
    return (int) get_option('poke_hub_go_search_filters_cache_rev', 0);
}

/**
 * @return bool
 */
function poke_hub_go_search_filters_table_exists(): bool {
    global $wpdb;
    $table = function_exists('pokehub_get_table') ? pokehub_get_table('go_search_filters') : '';
    if ($table === '' || ! isset($wpdb) || ! $wpdb instanceof \wpdb) {
        return false;
    }

    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
}

/**
 * dbDelta Collections / go-search-filters si modules actifs.
 */
function poke_hub_go_search_filters_ensure_table(): void {
    if (! apply_filters('pokehub_allow_auto_create_missing_tables', true)) {
        return;
    }
    if (! function_exists('pokehub_install_tables_for_modules')) {
        return;
    }
    $mods = [];
    if (function_exists('poke_hub_is_module_active')) {
        if (poke_hub_is_module_active('collections')) {
            $mods[] = 'collections';
        }
        if (poke_hub_is_module_active('go-search-filters')) {
            $mods[] = 'go-search-filters';
        }
    }
    if ($mods === []) {
        return;
    }
    pokehub_install_tables_for_modules(array_values(array_unique($mods)));
}

/**
 * @return array<int, array<string, mixed>>
 */
function poke_hub_go_search_filters_seed_rows_definitions(): array {
    $defs = poke_hub_go_search_filters_collection_defaults_flat();
    $desc = static function (string $fr, string $en): array {
        return [ 'description_fr' => $fr, 'description_en' => $en ];
    };

    $meta = [
        'shiny'          => $desc(
            'Filtre « chromatiques » dans la recherche Pokémon (onglet Combats / collection). Réutilisé dans les phrases générées par le module Collections.',
            'Shows shiny Pokémon in in-game Pokémon search. Used by the Collections phrases builder.'
        ),
        'shadow'         => $desc(
            'Filtre Pokémon Obscurs (Rocket).',
            'Shadow Pokémon filter.'
        ),
        'purified'       => $desc(
            'Filtre Pokémon purifiés.',
            'Purified Pokémon filter.'
        ),
        'mega'           => $desc(
            'Filtre formes Méga (recherche Pokémon).',
            'Mega-evolution Pokémon search token.'
        ),
        'gigamax'        => $desc(
            'Mot-clé recherche Gigamax côté FR (nom du phénomène in-game peut varier).',
            'Gigantamax search token for English Pokémon GO.'
        ),
        'dynamax'        => $desc(
            'Filtre Dynamax (mot souvent commun aux deux langues).',
            'Dynamax search token.'
        ),
        'costume'       => $desc(
            'Filtre Pokémon événement / costumes.',
            'Event / costumed Pokémon filter.'
        ),
        'male'          => $desc(
            'Préfixe mâle accumulé dans les phrases automatiques.',
            'Male gender token for stacked search phrases.'
        ),
        'female'        => $desc(
            'Préfixe femelle accumulé dans les phrases automatiques.',
            'Female gender token.'
        ),
        'fond'          => $desc(
            'Filtre arrière-plan standard (sans mot composé).',
            'Standard background filter.'
        ),
        'fond_lieu'     => $desc(
            'Variante lieu pour les arrière-plans.',
            'Location background.'
        ),
        'fond_special'  => $desc(
            'Variante fond spécial / thématique.',
            'Special background.'
        ),
        'fond_dynamax'  => $desc(
            'Combinaison arrière-plan + Dynamax ; utiliser & entre segments sans & final.',
            'Background + Dynamax stacked token (internal & separators, no trailing &).'
        ),
        'fond_gigamax'  => $desc(
            'Combinaison arrière-plan + Gigamax / Gigantamax.',
            'Background + Gigantamax stacked token.'
        ),
        'lucky'         => $desc(
            'Préfixe « chanceux » pour les Lucky Dex dans Collections.',
            'Lucky Pokémon prefix used for Lucky-category collections.'
        ),
        'eggsonly'      => $desc(
            'Restriction « uniquement depuis les œufs » (filtre bébés / liste limitée).',
            'Egg-only Pokémon filter (baby preset in collections pool).'
        ),
    ];

    $order       = [
        'shiny'       => 10,
        'shadow'       => 20,
        'purified'    => 30,
        'mega'        => 40,
        'gigamax'     => 50,
        'dynamax'     => 60,
        'costume'     => 70,
        'male'        => 80,
        'female'      => 90,
        'fond'        => 100,
        'fond_lieu'   => 110,
        'fond_special'=> 120,
        'fond_dynamax'=> 130,
        'fond_gigamax'=> 140,
        'lucky'       => 150,
        'eggsonly'    => 160,
    ];
    /** @var list<array<string, mixed>> */
    $out = [];
    foreach ($order as $code => $so) {
        $fr_k = $code . '_fr';
        $en_k = $code . '_en';
        $m = $meta[ $code ] ?? $desc('', '');
        $out[] = [
            'code'               => $code,
            'filter_fr'           => isset($defs[ $fr_k ]) ? (string) $defs[ $fr_k ] : '',
            'filter_en'           => isset($defs[ $en_k ]) ? (string) $defs[ $en_k ] : '',
            'description_fr'     => (string) $m['description_fr'],
            'description_en'     => (string) $m['description_en'],
            'scope_pokemon'      => 1,
            'scope_friends'      => 0,
            'use_in_collections' => 1,
            'is_system'          => 1,
            'sort_order'         => $so,
        ];
    }

    return $out;
}

/**
 * Insère les lignes système si la table est vide de tout enregistrement.
 */
function poke_hub_go_search_filters_seed_if_empty(): void {
    global $wpdb;
    if (! poke_hub_go_search_filters_table_exists()) {
        return;
    }
    $table = pokehub_get_table('go_search_filters');
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from helper.
    $n = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
    if ($n > 0) {
        return;
    }
    $rows = poke_hub_go_search_filters_seed_rows_definitions();
    foreach ($rows as $row) {
        $wpdb->insert(
            $table,
            [
                'code'                => (string) $row['code'],
                'filter_fr'            => (string) $row['filter_fr'],
                'filter_en'            => (string) $row['filter_en'],
                'description_fr'       => (string) $row['description_fr'],
                'description_en'       => (string) $row['description_en'],
                'scope_pokemon'        => (int) $row['scope_pokemon'] ? 1 : 0,
                'scope_friends'        => (int) $row['scope_friends'] ? 1 : 0,
                'use_in_collections'   => (int) $row['use_in_collections'] ? 1 : 0,
                'is_system'            => (int) $row['is_system'] ? 1 : 0,
                'sort_order'           => (int) $row['sort_order'],
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d' ]
        );
    }
}

/**
 * Codes utilisés comme référence (seed, tooling) — couples `{code}_fr` dans les défauts plats.
 *
 * @return list<string>
 */
function poke_hub_go_search_filters_collection_codes(): array {
    $defs  = poke_hub_go_search_filters_collection_defaults_flat();
    $codes = [];
    foreach (array_keys($defs) as $k) {
        if (preg_match('/^(.+)_fr$/', (string) $k, $m)) {
            $codes[] = $m[1];
        }
    }

    return array_values(array_unique($codes));
}

/**
 * Lecture pour wp_localize : paires `{code}_fr` / `{code}_en` depuis la BDD.
 *
 * @return array<string, string>
 */
function poke_hub_go_search_filters_get_flat_for_collections(): array {
    if (! function_exists('pokehub_get_table')) {
        return [];
    }
    poke_hub_go_search_filters_ensure_table();
    if (! poke_hub_go_search_filters_table_exists()) {
        return [];
    }
    poke_hub_go_search_filters_seed_if_empty();

    global $wpdb;
    $table = pokehub_get_table('go_search_filters');
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results(
        "SELECT code, filter_fr, filter_en FROM `{$table}` WHERE use_in_collections = 1 ORDER BY sort_order ASC, code ASC",
        ARRAY_A
    );
    if (! is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        if (! is_array($r)) {
            continue;
        }
        $code = isset($r['code']) ? strtolower(trim((string) $r['code'])) : '';
        if ($code === '' || preg_match('/[^a-z0-9_-]/', $code)) {
            continue;
        }
        $out[ $code . '_fr' ] = (string) ($r['filter_fr'] ?? '');
        $out[ $code . '_en' ] = (string) ($r['filter_en'] ?? '');
    }

    return $out;
}

/**
 * Liste complète catalogue (admin).
 *
 * @return array<int, array<string, mixed>>
 */
function poke_hub_go_search_filters_get_all(): array {
    global $wpdb;
    if (! poke_hub_go_search_filters_table_exists()) {
        return [];
    }
    $table = pokehub_get_table('go_search_filters');

    return $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        "SELECT * FROM `{$table}` ORDER BY sort_order ASC, code ASC",
        ARRAY_A
    ) ?: [];
}

/**
 * Une ligne par id.
 *
 * @return array<string, mixed>|null
 */
function poke_hub_go_search_filters_get_row(int $id): ?array {
    global $wpdb;
    if ($id <= 0 || ! poke_hub_go_search_filters_table_exists()) {
        return null;
    }
    $table = pokehub_get_table('go_search_filters');
    $row   = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1",
            $id
        ),
        ARRAY_A
    );

    return is_array($row) ? $row : null;
}

/**
 * @param array<string, mixed> $data
 */
function poke_hub_go_search_filters_validate_row(array $data, ?int $ignore_id = null): ?string {
    $code = isset($data['code']) ? strtolower(trim((string) $data['code'])) : '';
    $code = preg_replace('/[^a-z0-9_-]+/', '', $code);
    if ($code === '') {
        return __('A unique code is required (lowercase, numbers, hyphen).', 'poke-hub');
    }

    global $wpdb;
    $table = pokehub_get_table('go_search_filters');
    if ($ignore_id !== null && $ignore_id > 0) {
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$table}` WHERE code = %s AND id != %d LIMIT 1",
                $code,
                $ignore_id
            )
        );
    } else {
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$table}` WHERE code = %s LIMIT 1",
                $code
            )
        );
    }
    if ($exists) {
        return __('This code is already used.', 'poke-hub');
    }

    return null;
}

/**
 * Normalise données formulaire avant insert/update.
 *
 * @param array<string, mixed> $post
 * @return array<string, scalar>
 */
function poke_hub_go_search_filters_sanitize_post(array $post): array {
    $filter_fr            = sanitize_text_field((string) ($post['filter_fr'] ?? ''));
    $filter_en            = sanitize_text_field((string) ($post['filter_en'] ?? ''));
    $description_fr       = isset($post['description_fr']) ? sanitize_textarea_field((string) $post['description_fr']) : '';
    $description_en       = isset($post['description_en']) ? sanitize_textarea_field((string) $post['description_en']) : '';

    return [
        'code'               => strtolower(preg_replace('/[^a-z0-9_-]+/', '', (string) ($post['code'] ?? ''))),
        'filter_fr'           => trim($filter_fr),
        'filter_en'           => trim($filter_en),
        'description_fr'       => trim($description_fr),
        'description_en'       => trim($description_en),
        'scope_pokemon'        => ! empty($post['scope_pokemon']) ? 1 : 0,
        'scope_friends'        => ! empty($post['scope_friends']) ? 1 : 0,
        'use_in_collections'   => ! empty($post['use_in_collections']) ? 1 : 0,
        'sort_order'           => isset($post['sort_order']) ? (int) $post['sort_order'] : 0,
    ];
}
