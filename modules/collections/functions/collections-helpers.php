<?php
// modules/collections/functions/collections-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration : ajoute la colonne share_token si absente et remplit les valeurs.
 */
function poke_hub_collections_maybe_add_share_token_column() {
    if (get_option('poke_hub_collections_share_token_migrated')) {
        return;
    }
    global $wpdb;
    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return;
    }
    $col = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'share_token'",
        DB_NAME,
        $collections_table
    ));
    if (empty($col)) {
        $wpdb->query("ALTER TABLE {$collections_table} ADD COLUMN share_token VARCHAR(20) NULL DEFAULT NULL AFTER slug");
        $wpdb->query("ALTER TABLE {$collections_table} ADD UNIQUE KEY share_token (share_token)");
    }
    $rows = $wpdb->get_results("SELECT id FROM {$collections_table} WHERE share_token IS NULL OR share_token = ''", ARRAY_A);
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $token = poke_hub_collections_generate_share_token();
            $wpdb->update($collections_table, ['share_token' => $token], ['id' => $row['id']], ['%s'], ['%d']);
        }
    }
    update_option('poke_hub_collections_share_token_migrated', 1);
    flush_rewrite_rules(false);
}

/**
 * Migration : ajoute la colonne anonymous_ip si absente (collections créées sans compte).
 */
function poke_hub_collections_maybe_add_anonymous_ip_column() {
    if (get_option('poke_hub_collections_anonymous_ip_migrated')) {
        return;
    }
    global $wpdb;
    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return;
    }
    $col = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'anonymous_ip'",
        DB_NAME,
        $collections_table
    ));
    if (empty($col)) {
        $wpdb->query("ALTER TABLE {$collections_table} ADD COLUMN anonymous_ip VARCHAR(45) NULL DEFAULT NULL AFTER share_token");
        $wpdb->query("ALTER TABLE {$collections_table} ADD KEY anonymous_ip (anonymous_ip)");
    }
    update_option('poke_hub_collections_anonymous_ip_migrated', 1);
}

/**
 * Génère un jeton aléatoire unique pour le partage (alphanumérique, 14 caractères).
 *
 * @return string
 */
function poke_hub_collections_generate_share_token(): string {
    global $wpdb;
    $collections_table = pokehub_get_table('collections');
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $max_attempts = 10;
    for ($i = 0; $i < $max_attempts; $i++) {
        $token = '';
        for ($j = 0; $j < 14; $j++) {
            $token .= $chars[random_int(0, strlen($chars) - 1)];
        }
        if (!$collections_table) {
            return $token;
        }
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$collections_table} WHERE share_token = %s",
            $token
        ));
        if (!$exists) {
            return $token;
        }
    }
    return $chars[random_int(0, strlen($chars) - 1)] . wp_generate_password(13, true, true);
}

/**
 * Catégories de collection disponibles (slug => label)
 *
 * @return array<string, string>
 */
function poke_hub_collections_get_categories(): array {
    return [
        'perfect_4'       => __('Pokémon 4* (perfect)', 'poke-hub'),
        'shiny'           => __('Shiny', 'poke-hub'),
        'costume'         => __('Costumed Pokémon', 'poke-hub'),
        'costume_shiny'   => __('Shiny costumed', 'poke-hub'),
        'background'      => __('Pokémon avec fond d’arrière-plan (tous) — in-game : background', 'poke-hub'),
        'background_special' => __('Fond spécial (special background)', 'poke-hub'),
        'background_places'  => __('Fond de lieu (location background)', 'poke-hub'),
        'background_shiny'=> __('Shiny avec fond d’arrière-plan (tous) — in-game : background', 'poke-hub'),
        'background_shiny_special' => __('Shiny + fond spécial (special background)', 'poke-hub'),
        'background_shiny_places'  => __('Shiny + fond de lieu (location background)', 'poke-hub'),
        'lucky'           => __('Lucky', 'poke-hub'),
        'shadow'          => __('Shadow', 'poke-hub'),
        'purified'        => __('Purified', 'poke-hub'),
        'gigantamax'     => __('Gigantamax', 'poke-hub'),
        'dynamax'        => __('Dynamax', 'poke-hub'),
        'legendary_mythical_ultra' => __('Legendary, Mythical & Ultra Beasts (preset)', 'poke-hub'),
        'custom'         => __('Custom list', 'poke-hub'),
    ];
}

/**
 * Catégories « spécifiques » : la collection affiche uniquement ce type (ex. Gigantamax, Dynamax).
 * Pour ces catégories on ne propose pas les options « afficher Méga / Gigantamax / Dynamax / costumes ».
 *
 * @return string[]
 */
function poke_hub_collections_get_specific_categories(): array {
    return [
        'gigantamax',
        'dynamax',
        'costume',
        'costume_shiny',
        'shadow',
        'purified',
        'background',
        'background_shiny',
        'background_special',
        'background_shiny_special',
        'background_places',
        'background_shiny_places',
    ];
}

/**
 * Indique si la catégorie est « spécifique » (liste = uniquement ce type, pas d’options d’ajout Méga/Giga/Dynamax/costumes).
 *
 * @param string $category Slug de catégorie
 * @return bool
 */
function poke_hub_collections_category_is_specific(string $category): bool {
    return in_array($category, poke_hub_collections_get_specific_categories(), true);
}

/**
 * Clés d’options / contrôles de formulaire à ne pas afficher : la catégorie de liste
 * n’y donne pas place (ex. bébés + liste L/M/UC, costumés + liste « costumés » seulement).
 * Les clés correspondent à l’attribut data-collections-control (voir shortcode) et
 * partagent la logique de sauvegarde (include_*, include_baby_pokemon, etc.).
 *
 * @return list<string>
 */
function poke_hub_collections_settings_hidden_control_keys(string $category): array {
    $cat = sanitize_key($category);
    if ($cat === '') {
        return [];
    }
    $h = [];
    if ($cat === 'legendary_mythical_ultra') {
        $h[] = 'include_baby_pokemon';
        $h[] = 'pool_option_baby';
        $h[] = 'pool_option_special_all';
    } elseif (in_array($cat, [ 'gigantamax', 'dynamax' ], true)) {
        $h[] = 'include_baby_pokemon';
        $h[] = 'pool_option_baby';
    } elseif (in_array($cat, [ 'costume', 'costume_shiny' ], true)) {
        $h[] = 'include_costumes';
    } elseif (in_array($cat, [
        'background',
        'background_shiny',
        'background_special',
        'background_shiny_special',
        'background_places',
        'background_shiny_places',
    ], true)) {
        $h[] = 'include_backgrounds';
    }
    if ($cat === 'gigantamax') {
        $h[] = 'include_gigantamax';
    }
    if ($cat === 'dynamax') {
        $h[] = 'include_dynamax';
    }

    return array_values(array_unique(apply_filters('poke_hub_collections_settings_hidden_control_keys', $h, $cat)));
}

/**
 * @return array<string, list<string>> slugs de catégorie => clés masquées
 */
function poke_hub_collections_settings_hidden_control_keys_map_for_ui(): array {
    $out = [];
    foreach (array_keys(poke_hub_collections_get_categories()) as $slug) {
        $out[$slug] = poke_hub_collections_settings_hidden_control_keys((string) $slug);
    }

    return $out;
}

/**
 * N° de Pokédex (national) des espèces à motifs multiples (même logique que l’import GM : UNOWN, SPINDA, VIVILLON, FURFROU).
 * La ligne au slug seul (ex. « unown ») sert de regroupement : à masquer en liste « toutes les formes »
 * dès qu’il existe une variante « unown-… ».
 *
 * @return int[]
 */
function poke_hub_collections_dex_for_visual_variants_base_slug_only(): array {
    $dex = [201, 327, 666, 676];
    $out = (array) apply_filters('poke_hub_collections_visual_variant_placeholder_dex_numbers', $dex);
    $out = array_map('intval', $out);

    return array_values(array_filter(array_unique($out), static function (int $d): bool {
        return $d > 0;
    }));
}

/**
 * Lien table fonds : contexte d’affichage pour une ligne de pool (classique, obscur, dynamax, gigamax).
 */
function poke_hub_collections_pool_row_go_background_link_kind( array $row, string $category ): string {
    $cat = sanitize_key( $category );
    if ( ! empty( $row['synthetic_dynamax'] ) || poke_hub_collections_dynamax_row_is_real_form( $row ) ) {
        return defined( 'POKE_HUB_BG_LINK_DYNAMAX' ) ? POKE_HUB_BG_LINK_DYNAMAX : 'dynamax';
    }
    if ( ! empty( $row['synthetic_gigantamax'] ) || poke_hub_collections_gigantamax_row_is_real_form( $row ) ) {
        return defined( 'POKE_HUB_BG_LINK_GIGANTAMAX' ) ? POKE_HUB_BG_LINK_GIGANTAMAX : 'gigantamax';
    }
    if ( $cat === 'shadow' ) {
        return defined( 'POKE_HUB_BG_LINK_SHADOW' ) ? POKE_HUB_BG_LINK_SHADOW : 'shadow';
    }

    return defined( 'POKE_HUB_BG_LINK_BASE' ) ? POKE_HUB_BG_LINK_BASE : 'base';
}

/**
 * IDs Pokémon pour joindre les lignes de pokemon_background_pokemon_links (même fiche, plusieurs id possibles p. ex. G-Max).
 *
 * @return int[]
 */
function poke_hub_collections_pool_row_go_background_link_pokemon_ids( array $row, string $link_kind ): array {
    $link_kind = sanitize_key( $link_kind );
    if ( $link_kind === ( defined( 'POKE_HUB_BG_LINK_DYNAMAX' ) ? POKE_HUB_BG_LINK_DYNAMAX : 'dynamax' ) ) {
        if ( ! empty( $row['synthetic_dynamax'] ) && ! empty( $row['dynamax_base_pokemon_id'] ) ) {
            $b = (int) $row['dynamax_base_pokemon_id'];
            return $b > 0 ? [ $b ] : [];
        }
        $rid = (int) ( $row['id'] ?? 0 );
        if ( $rid > 0 && ! poke_hub_collections_dynamax_is_synthetic_pokemon_id( $rid ) ) {
            $out = [ $rid ];
            if ( ! empty( $row['dynamax_base_pokemon_id'] ) ) {
                $b = (int) $row['dynamax_base_pokemon_id'];
                if ( $b > 0 && $b !== $rid ) {
                    $out[] = $b;
                }
            }

            return array_values( array_unique( array_filter( $out, static function ( int $x ): bool {
                return $x > 0;
            } ) ) );
        }

        $b = (int) ( $row['dynamax_base_pokemon_id'] ?? 0 );

        return $b > 0 ? [ $b ] : [];
    }
    if ( $link_kind === ( defined( 'POKE_HUB_BG_LINK_GIGANTAMAX' ) ? POKE_HUB_BG_LINK_GIGANTAMAX : 'gigantamax' ) ) {
        $out = [];
        if ( ! empty( $row['gigantamax_base_pokemon_id'] ) ) {
            $out[] = (int) $row['gigantamax_base_pokemon_id'];
        }
        $rid = (int) ( $row['id'] ?? 0 );
        if ( $rid > 0 && ! poke_hub_collections_gigantamax_is_synthetic_pokemon_id( $rid ) && ! poke_hub_collections_dynamax_is_synthetic_pokemon_id( $rid ) ) {
            $out[] = $rid;
        }
        if ( $out === [] && $rid > 0 && poke_hub_collections_gigantamax_is_synthetic_pokemon_id( $rid ) && ! empty( $row['gigantamax_base_pokemon_id'] ) ) {
            $out[] = (int) $row['gigantamax_base_pokemon_id'];
        }

        return array_values( array_unique( array_filter( $out, static function ( int $x ): bool {
            return $x > 0;
        } ) ) );
    }

    $pid = poke_hub_collections_pool_row_pokemon_id_for_go_background_link( $row );
    if ( $pid > 0 ) {
        return [ $pid ];
    }

    return [];
}

/**
 * Tous les fonds GO pour une ligne de collection (fond + type de forme gérés en base).
 *
 * @return list<array{background_id: int, image_url: string, background_title: string}>
 */
function poke_hub_collections_get_all_go_backgrounds_for_pool_row( array $row, string $category, bool $only_shiny_active = false ): array {
    if ( $row === [] ) {
        return [];
    }
    $kind  = poke_hub_collections_pool_row_go_background_link_kind( $row, $category );
    $p_ids = poke_hub_collections_pool_row_go_background_link_pokemon_ids( $row, $kind );

    return poke_hub_collections_get_all_go_backgrounds_for_pokemon_ids( $p_ids, $kind, $only_shiny_active );
}

/**
 * Tous les fonds GO liés à des fiches Pokémon et un type de lien (base, shadow, dynamax, gigantamax).
 *
 * @param int[]  $pokemon_ids
 * @param string $link_kind   POKE_HUB_BG_LINK_*
 * @return list<array{background_id: int, image_url: string, background_title: string}>
 */
function poke_hub_collections_get_all_go_backgrounds_for_pokemon_ids( array $pokemon_ids, string $link_kind, bool $only_shiny_active = false ): array {
    global $wpdb;
    $links_table       = pokehub_get_table( 'pokemon_background_pokemon_links' );
    $backgrounds_table = pokehub_get_table( 'pokemon_backgrounds' );
    if ( ! $links_table || ! $backgrounds_table ) {
        return [];
    }
    $ids = array_values( array_filter( array_unique( array_map( 'intval', $pokemon_ids ) ), static function ( int $i ): bool {
        return $i > 0;
    } ) );
    if ( $ids === [] ) {
        return [];
    }
    $allowed = [ 'base', 'shadow', 'dynamax', 'gigantamax' ];
    if ( ! in_array( $link_kind, $allowed, true ) ) {
        $link_kind = 'base';
    }
    $lock_sql  = $only_shiny_active ? ' AND l.is_shiny_locked = 0' : '';
    $placehold = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
    $sql       = "SELECT DISTINCT b.id AS background_id, b.image_url,
        COALESCE(
            NULLIF(TRIM(b.name_fr), ''),
            NULLIF(TRIM(b.name_en), ''),
            NULLIF(TRIM(b.title), ''),
            NULLIF(TRIM(b.slug), ''),
            CONCAT('Fond #', b.id)
        ) AS background_title
        FROM {$links_table} l
        INNER JOIN {$backgrounds_table} b ON l.background_id = b.id
        WHERE l.pokemon_id IN ({$placehold})
        AND l.link_kind = %s{$lock_sql}
        AND TRIM(COALESCE(b.image_url, '')) != ''
        ORDER BY b.id ASC";
    $args      = array_merge( $ids, [ $link_kind ] );
    $prepared  = $wpdb->prepare( $sql, $args );
    if ( ! is_string( $prepared ) || $prepared === '' ) {
        return [];
    }
    $rows = $wpdb->get_results( $prepared, ARRAY_A );
    if ( ! is_array( $rows ) || $rows === [] ) {
        return [];
    }
    $out = [];
    foreach ( $rows as $r ) {
        $bid = isset( $r['background_id'] ) ? (int) $r['background_id'] : 0;
        $url = isset( $r['image_url'] ) && is_string( $r['image_url'] ) ? trim( $r['image_url'] ) : '';
        if ( $bid > 0 && $url !== '' ) {
            $title = isset( $r['background_title'] ) && is_string( $r['background_title'] ) ? trim( $r['background_title'] ) : '';
            $out[] = [
                'background_id'    => $bid,
                'image_url'        => $url,
                'background_title' => $title,
            ];
        }
    }

    return $out;
}

/**
 * URL du premier fond adapté à la ligne de collection (même règles que les tuiles « fonds » supplémentaires).
 *
 * @return string
 */
function poke_hub_collections_get_background_image_url_for_pool_row( array $row, string $category, bool $only_shiny_active = false ): string {
    $all = poke_hub_collections_get_all_go_backgrounds_for_pool_row( $row, $category, $only_shiny_active );
    if ( $all === [] ) {
        return '';
    }
    $u = trim( (string) ( $all[0]['image_url'] ?? '' ) );
    if ( is_string( $u ) && $u !== '' ) {
        return $u;
    }

    return '';
}

/**
 * Retourne l'URL de l'image du premier fond lié à un Pokémon (pour affichage fond + sprite).
 *
 * @param int  $pokemon_id          ID du Pokémon
 * @param bool $only_shiny_active  Si vrai, ne prend que les fonds où le Pokémon n'est PAS shiny lock
 * @return string URL du fond ou chaîne vide
 */
function poke_hub_collections_get_background_image_url_for_pokemon( int $pokemon_id, bool $only_shiny_active = false, string $link_kind = 'base' ): string {
    $bgs = poke_hub_collections_get_all_go_backgrounds_for_pokemon_ids( [ $pokemon_id ], $link_kind, $only_shiny_active );
    if ( $bgs === [] ) {
        return '';
    }
    $u = trim( (string) ( $bgs[0]['image_url'] ?? '' ) );
    if ( is_string( $u ) && $u !== '' ) {
        return $u;
    }

    return '';
}

/**
 * Tous les fonds GO liés à un Pokémon (pour une tuile par fond, en plus de la fiche de base).
 *
 * @return list<array{background_id: int, image_url: string, background_title: string}>
 */
function poke_hub_collections_get_all_go_backgrounds_for_pokemon( int $pokemon_id, bool $only_shiny_active = false, string $link_kind = 'base' ): array {
    if ( $pokemon_id <= 0 ) {
        return [];
    }

    return poke_hub_collections_get_all_go_backgrounds_for_pokemon_ids( [ $pokemon_id ], $link_kind, $only_shiny_active );
}

/**
 * Options par défaut d'une collection
 *
 * @return array
 */
function poke_hub_collections_default_options(): array {
    return [
        'include_national_dex'    => true,
        'include_gender'         => true,
        'include_regional_forms'  => true,
        'include_costumes'         => true,
        'include_mega'           => true,
        'include_gigantamax'     => true,
        'include_dynamax'        => true,
        'include_backgrounds'    => true,
        'include_special_attacks'=> false,
        'one_per_species'        => false,
        'group_by_generation'   => true,
        'generations_collapsed'  => false,
        'display_mode'          => 'tiles',
        'public'                => false,
        'card_background_image_url' => '',
        /* Filtre pool : n’inclure que les espèces qui n’ont plus d’évolution en jeu */
        'only_final_evolution'  => false,
        /* Filtre pool : bébés (slug listé) — applicable si on n’est pas en « seulement finales ». */
        'include_baby_pokemon'  => true,
        /* Filtre pool : légendaires / fabuleux (mythical) / ultra-chimères (extra.species_special_group). */
        'include_legendary_pokemon'   => true,
        'include_mythical_pokemon'    => true,
        'include_ultra_beast_pokemon'  => true,
        /* true = pool limité aux espèces Lég./Fab./UC (les 3 cases ci-dessus affinent encore). */
        'only_special_species_pool' => false,
        /* Restriction unique du pool : '' | final | baby | special_all | legendary | mythical | ultra_beast | special_attacks */
        'pool_show_only' => '',
        /* Mâle + femelle (deux cases) par espèce dont les déclarés GO ont male + female (Bulbizarre, etc.). */
        'include_both_sexes_collector' => false,
        /* Liste personnalisée (custom) : ne garder que les Pokémon pouvant être shiny en GO. */
        'only_shiny' => false,
    ];
}

/**
 * Valeurs autorisées pour {@see poke_hub_collections_normalize_pool_show_only()}.
 *
 * @return string[]
 */
function poke_hub_collections_pool_show_only_allowed(): array {
    return ['', 'final', 'baby', 'special_all', 'legendary', 'mythical', 'ultra_beast', 'special_attacks'];
}

/**
 * Restriction « Show only » du pool (nouvelle clé pool_show_only + rétrocompatibilité only_final / only_special_species_pool).
 *
 * @param array<string, mixed> $opts Options collection fusionnées avec les défauts.
 */
function poke_hub_collections_normalize_pool_show_only(array $opts): string {
    $raw = isset($opts['pool_show_only']) ? (string) $opts['pool_show_only'] : '';
    $raw = sanitize_key($raw);
    if (in_array($raw, ['final', 'baby', 'special_all', 'legendary', 'mythical', 'ultra_beast', 'special_attacks'], true)) {
        return $raw;
    }
    if (!empty($opts['only_final_evolution'])) {
        return 'final';
    }
    if (!empty($opts['only_special_species_pool'])) {
        return 'special_all';
    }

    return '';
}

/**
 * ♂/♀ : option dérivée (plus de case dédiée) — actif si « différences de genre » ou « mâle et femelle ».
 *
 * @param array<string, mixed> $options
 * @return array<string, mixed>
 */
function poke_hub_collections_derive_gender_symbol_option(array $options): array {
    $options['show_gender_symbols'] = !empty($options['include_gender']) || !empty($options['include_both_sexes_collector']);

    return $options;
}

/**
 * Rétrocompat : l’ancienne clé `include_forms` est remplacée par `one_per_species` (sens inversé pour la requête SQL).
 * - include_forms true  → one_per_species false (lister toutes les formes / lignes distinctes, comme avant).
 * - include_forms false → one_per_species true (repli *-family* / fiche par défaut + filtre is_default si besoin).
 *
 * @param array<string, mixed> $options Options déjà fusionnées avec les défauts, avec `include_forms` si présent en BDD.
 * @return array<string, mixed>
 */
function poke_hub_collections_merge_legacy_include_forms_option(array $options): array {
    if (!array_key_exists('include_forms', $options)) {
        return $options;
    }
    $old_inc = !empty($options['include_forms']);
    unset($options['include_forms']);
    if ($old_inc) {
        $options['one_per_species'] = false;
    } else {
        $options['one_per_species'] = true;
    }

    return $options;
}

/**
 * URL d'image de fond pour la carte d'une collection (liste des collections).
 * Priorité : 1) image personnalisée (options.card_background_image_url), 2) filtre par catégorie.
 *
 * @param array $collection Ligne collection avec au minimum 'category' et optionnellement 'options' (array)
 * @return string URL à utiliser pour le background de la carte, ou chaîne vide
 */
function poke_hub_collections_get_card_background_image_url(array $collection): string {
    $options = isset($collection['options']) && is_array($collection['options']) ? $collection['options'] : [];
    $custom  = isset($options['card_background_image_url']) ? trim((string) $options['card_background_image_url']) : '';
    if ($custom !== '') {
        return esc_url($custom);
    }
    $category = isset($collection['category']) ? $collection['category'] : 'custom';
    return (string) apply_filters('poke_hub_collections_card_background_image_url', '', $category);
}

/**
 * Filtre « sorti dans GO » pour le pool collections, basé sur la fiche courante.
 *
 * Important : on lit uniquement `extra.release` de la ligne en cours (pas de propagation
 * via la chaîne d’évolution), afin de respecter les sorties échelonnées par événement.
 *
 * @param array<string, mixed> $row  Ligne Pokémon (doit contenir `extra`)
 * @param array<string, mixed> $opts Options collection (include_mega, include_gigantamax, include_dynamax, …)
 */
function poke_hub_collections_row_passes_pool_release_filter(array $row, string $release_context, array $opts): bool {
    $extra_raw = isset($row['extra']) ? (string) $row['extra'] : '';
    if ($extra_raw === '') {
        return false;
    }

    $extra = json_decode($extra_raw, true);
    if (!is_array($extra)) {
        return false;
    }

    $release_map = isset($extra['release']) && is_array($extra['release']) ? $extra['release'] : [];
    $has_release = static function (array $map, string $key): bool {
        return trim((string) ($map[$key] ?? '')) !== '';
    };

    if ($release_context !== 'normal') {
        return $has_release($release_map, $release_context);
    }

    if ($has_release($release_map, 'normal')) {
        return true;
    }
    if (!empty($opts['include_mega']) && $has_release($release_map, 'mega')) {
        return true;
    }
    if (!empty($opts['include_dynamax']) && $has_release($release_map, 'dynamax')) {
        return true;
    }
    if (!empty($opts['include_gigantamax']) && $has_release($release_map, 'gigantamax')) {
        return true;
    }

    return false;
}

/**
 * Derniers segments de slug reconnus comme variante (dernier token après « - »).
 *
 * @return list<string>
 */
function poke_hub_collections_pogo_regional_slug_suffixes(): array {
    return [
        'alola', 'alolan', 'galar', 'galarian', 'paldea', 'paldean', 'hisui', 'hisuian',
        'mega', 'megax', 'primal', 'gigantamax', 'gigamax', 'dynamax',
    ];
}

/**
 * Slugs de variante (table pokemon_form_variants.form_slug) correspondant à des formes régionales GO.
 * Aligné sur {@see poke_hub_pokemon_guess_form_type_from_gm()} (ALOLA, GALAR, HISUI, PALDEA) et noms d’import courants.
 *
 * @return list<string> slugs en minuscules
 */
function poke_hub_collections_regional_form_variant_slug_tokens(): array {
    return [
        'alola', 'alolan', 'galar', 'galarian', 'hisui', 'hisuian', 'paldea', 'paldean',
    ];
}

/**
 * Jeton de recherche GO (lettres/chiffres, sans tiret) à partir du slug espèce+forme en base.
 * Ex. rattata-alola → rattata, mr-mime-galar → mrmime.
 */
function poke_hub_collections_pogo_token_from_slug(string $slug): string {
    $slug = strtolower(trim($slug));
    if ($slug === '') {
        return '';
    }
    $parts     = explode('-', $slug);
    $suffix_ok = array_flip(poke_hub_collections_pogo_regional_slug_suffixes());
    if (count($parts) >= 2) {
        $last = $parts[ count($parts) - 1 ];
        if (isset($suffix_ok[ $last ])) {
            array_pop($parts);
            $slug = implode('-', $parts);
        }
    }

    return preg_replace('/[^a-z0-9]/', '', str_replace('-', '', $slug));
}

/**
 * Nom d’affichage → chaîne collée (accents retirés, non-alphanum supprimés).
 */
function poke_hub_collections_pogo_collapse_display_name(string $name): string {
    $name = trim((string) $name);
    if ($name === '') {
        return '';
    }
    if (function_exists('remove_accents')) {
        $name = remove_accents($name);
    }
    $name = strtolower($name);

    return preg_replace('/[^a-z0-9]+/u', '', $name);
}

/**
 * Retire marqueurs régionaux sur une chaîne déjà « collée » (ordre : suffixes d + de / from / of, puis préfixes).
 * Ne pas utiliser (from|of)?(alola)$ seul : sur « rattatadalola » cela ne laissait que « rattatad ».
 */
function poke_hub_collections_pogo_strip_regional_collapsed(string $s): string {
    if ($s === '') {
        return $s;
    }
    $pairs = [
        ['alola', 'alolan', 'alola'],
        ['galar', 'galarian', 'galar'],
        ['paldea', 'paldean', 'paldea'],
        ['hisui', 'hisuian', 'hisui', 'hisuian'],
    ];
    foreach ($pairs as $p) {
        $a = $p[0];
        $b = $p[1];
        $c = $p[2] ?? $a;
        $s = preg_replace('/(d|de)(' . $b . '|' . $c . ')$/u', '', $s);
        $s = preg_replace('/(from|of)(' . $b . '|' . $c . '|' . $a . ')$/u', '', $s);
        $s = preg_replace('/(^|[^d])(' . $b . '|' . $c . '|' . $a . ')$/u', '$1', $s);
        $s = preg_replace('/^(' . $b . '|' . $c . '|' . $a . ')/u', '', $s);
        $s = preg_replace('/(' . $b . '|' . $c . ')form$/u', '', $s);
        $s = preg_replace('/form(' . $b . '|' . $c . '|' . $a . ')$/u', '', $s);
    }

    return $s;
}

/**
 * Clé de groupe de recherche GO pour les formes régionales (ligne « alola& », « galar& », etc.).
 * Basé sur le slug (pas sur la catégorie seule) pour ne pas mélanger avec des noms d’espèce
 * (ex. pas de « mega » dans le nom d’une espèce reclassée à tort en Méga côté JS).
 *
 * @return string '' | 'alola' | 'galar' | 'paldea' | 'hisui' | 'other'
 */
function poke_hub_collections_pogo_regional_key_from_row( array $row ): string {
    $cat  = strtolower( trim( (string) ( $row['form_category'] ?? '' ) ) );
    $slug = strtolower( trim( (string) ( $row['form_slug'] ?? '' ) . ' ' . (string) ( $row['slug'] ?? '' ) ) );
    if ( $slug === '' ) {
        return '';
    }
    if ( false !== strpos( $slug, 'alola' ) || false !== strpos( $slug, 'alolan' ) ) {
        return 'alola';
    }
    if ( false !== strpos( $slug, 'galar' ) || false !== strpos( $slug, 'galarian' ) ) {
        return 'galar';
    }
    if ( false !== strpos( $slug, 'paldea' ) || false !== strpos( $slug, 'paldean' ) ) {
        return 'paldea';
    }
    if ( false !== strpos( $slug, 'hisui' ) || false !== strpos( $slug, 'hisuian' ) ) {
        return 'hisui';
    }
    if ( $cat === 'regional' ) {
        return 'other';
    }

    return '';
}

/**
 * Noms espèce de base (forme par défaut) indexés par dex_number.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array{fr:string,en:string}>
 */
function poke_hub_collections_pogo_base_names_by_dex(array $rows): array {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }
    global $wpdb;
    $pokemon_table = pokehub_get_table('pokemon');
    if (!$pokemon_table || !is_array($rows) || $rows === []) {
        return [];
    }
    $dexes = [];
    foreach ($rows as $r) {
        if (!is_array($r)) {
            continue;
        }
        $d = (int) ($r['dex_number'] ?? 0);
        if ($d > 0) {
            $dexes[$d] = true;
        }
    }
    if ($dexes === []) {
        return [];
    }
    $ids = array_map('intval', array_keys($dexes));
    $in  = implode(',', $ids);
    if ($in === '') {
        return [];
    }

    $sql = "SELECT dex_number, name_fr, name_en, is_default, form_variant_id
            FROM {$pokemon_table}
            WHERE dex_number IN ({$in})
            ORDER BY dex_number ASC, is_default DESC, (form_variant_id = 0) DESC, id ASC";
    $list = $wpdb->get_results($sql, ARRAY_A);
    if (!is_array($list) || $list === []) {
        return [];
    }
    $out = [];
    foreach ($list as $row) {
        $d = (int) ($row['dex_number'] ?? 0);
        if ($d <= 0 || isset($out[$d])) {
            continue;
        }
        $out[$d] = [
            'fr' => trim((string) ($row['name_fr'] ?? '')),
            'en' => trim((string) ($row['name_en'] ?? '')),
        ];
    }

    return $out;
}

/**
 * Noms de variantes indexés par form_variant_id (source de vérité DB).
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array{fr:string,en:string,category:string,slug:string}>
 */
function poke_hub_collections_pogo_variant_names_by_id(array $rows): array {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }
    global $wpdb;
    $table = pokehub_get_table('pokemon_form_variants');
    if (!$table || !is_array($rows) || $rows === []) {
        return [];
    }
    $ids = [];
    foreach ($rows as $r) {
        if (!is_array($r)) {
            continue;
        }
        $vid = (int) ($r['form_variant_id'] ?? 0);
        if ($vid > 0) {
            $ids[$vid] = true;
        }
    }
    if ($ids === []) {
        return [];
    }
    $in = implode(',', array_map('intval', array_keys($ids)));
    if ($in === '') {
        return [];
    }
    $sql = "SELECT id, form_slug, category, label, extra
            FROM {$table}
            WHERE id IN ({$in})";
    $list = $wpdb->get_results($sql, ARRAY_A);
    if (!is_array($list) || $list === []) {
        return [];
    }
    $out = [];
    foreach ($list as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $label = trim((string) ($row['label'] ?? ''));
        $fr    = '';
        $en    = '';
        $extra = $row['extra'] ?? null;
        if (is_string($extra) && $extra !== '') {
            $decoded = json_decode($extra, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
            if (is_array($decoded) && !empty($decoded['names']) && is_array($decoded['names'])) {
                $fr = trim((string) ($decoded['names']['fr'] ?? ''));
                $en = trim((string) ($decoded['names']['en'] ?? ''));
            }
        }
        if ($fr === '') {
            $fr = $label;
        }
        if ($en === '') {
            $en = $label;
        }
        $out[$id] = [
            'fr'       => $fr,
            'en'       => $en,
            'category' => strtolower(trim((string) ($row['category'] ?? ''))),
            'slug'     => strtolower(trim((string) ($row['form_slug'] ?? ''))),
        ];
    }

    return $out;
}

/**
 * Nom de variante DB -> jeton de préfixe POGO.
 */
function poke_hub_collections_pogo_variant_prefix_token(string $name): string {
    return poke_hub_collections_pogo_collapse_display_name($name);
}

/**
 * Ajoute pogo_token_fr / pogo_token_en (recherche Pokémon GO) à chaque ligne du pool.
 *
 * Jetons d’affichage pour la recherche in-game (Pokémon GO) : **nom d’espèce** (fiche « base »
 * par numéro National Dex), pas le libellé d’une forme spécifique (Necrozma, Météno, etc.).
 * Repli : nom de la ligne ou slug/espèce si aucune fiche de base n’est trouvée pour le dex.
 *
 * @param array<int, array<string, mixed>> $rows
 */
function poke_hub_collections_pool_rows_add_pogo_tokens(array &$rows): void {
    $baseNames    = poke_hub_collections_pogo_base_names_by_dex($rows);
    $variantNames = poke_hub_collections_pogo_variant_names_by_id($rows);
    foreach ($rows as &$row) {
        if (! is_array($row)) {
            continue;
        }
        $slug   = isset($row['slug']) ? (string) $row['slug'] : '';
        $nameFr = isset($row['name_fr']) ? trim((string) $row['name_fr']) : '';
        $nameEn = isset($row['name_en']) ? trim((string) $row['name_en']) : '';

        $dex = (int) ($row['dex_number'] ?? 0);
        $variantId = (int) ($row['form_variant_id'] ?? 0);
        $isVariant = $variantId > 0;
        $baseFr = ($dex > 0 && !empty($baseNames[$dex]['fr'])) ? (string) $baseNames[$dex]['fr'] : '';
        $baseEn = ($dex > 0 && !empty($baseNames[$dex]['en'])) ? (string) $baseNames[$dex]['en'] : '';

        $frSource = ( $dex > 0 && $baseFr !== '' ) ? $baseFr : $nameFr;
        $enSource = ( $dex > 0 && $baseEn !== '' ) ? $baseEn : $nameEn;

        $tokFr = $frSource !== ''
            ? poke_hub_collections_pogo_strip_regional_collapsed(
                poke_hub_collections_pogo_collapse_display_name($frSource)
            )
            : '';
        $tokEn = $enSource !== ''
            ? poke_hub_collections_pogo_strip_regional_collapsed(
                poke_hub_collections_pogo_collapse_display_name($enSource)
            )
            : '';

        $row['pogo_token_fr'] = $tokFr !== '' ? $tokFr : $tokEn;
        $row['pogo_token_en'] = $tokEn !== '' ? $tokEn : $tokFr;

        if ($row['pogo_token_fr'] === '' && $row['pogo_token_en'] === '') {
            $fallback = poke_hub_collections_pogo_token_from_slug($slug);
            $row['pogo_token_fr'] = $fallback;
            $row['pogo_token_en'] = $fallback;
        } elseif ($row['pogo_token_fr'] === '') {
            $row['pogo_token_fr'] = $row['pogo_token_en'];
        } elseif ($row['pogo_token_en'] === '') {
            $row['pogo_token_en'] = $row['pogo_token_fr'];
        }

        $row['pogo_group_prefix_fr'] = '';
        $row['pogo_group_prefix_en'] = '';
        if ($isVariant && $variantId > 0 && !empty($variantNames[$variantId])) {
            $v = $variantNames[$variantId];
            $vfr = poke_hub_collections_pogo_variant_prefix_token((string) ($v['fr'] ?? ''));
            $ven = poke_hub_collections_pogo_variant_prefix_token((string) ($v['en'] ?? ''));
            $row['pogo_group_prefix_fr'] = $vfr !== '' ? $vfr : $ven;
            $row['pogo_group_prefix_en'] = $ven !== '' ? $ven : $vfr;
        }
        $row['pogo_regional_key'] = poke_hub_collections_pogo_regional_key_from_row( $row );
        // Forme régionale : le jeton d’attente POGO est toujours alola& / galar& / paldea& / hisuian&, pas le libellé
        // (ex. Tauros Paldea Aqua → éviter « paldeaaqua& » au lieu de « paldea& »).
        $reg = $row['pogo_regional_key'];
        if ( $reg === 'alola' || $reg === 'galar' || $reg === 'paldea' || $reg === 'hisui' ) {
            $canon = ( $reg === 'hisui' ) ? 'hisuian' : $reg;
            $row['pogo_group_prefix_fr'] = $canon;
            $row['pogo_group_prefix_en'] = $canon;
        }
    }
    unset($row);
}

/**
 * Sous-titres de forme inutiles en liste (variante de base, « Normal ») : masqués côté pool.
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function poke_hub_collections_pool_row_strip_redundant_form_label( array $row ): array {
    $slug = isset( $row['form_slug'] ) ? strtolower( trim( (string) $row['form_slug'] ) ) : '';
    if ( $slug !== '' && in_array( $slug, [ 'normal', 'form-normal', 'form_normal' ], true ) ) {
        $row['form_label'] = '';
    }
    $fl = trim( (string) ( $row['form_label'] ?? '' ) );
    if ( $fl !== '' && in_array( strtolower( $fl ), [ 'normal', 'normale' ], true ) ) {
        $row['form_label'] = '';
    }

    return $row;
}

/**
 * Récupère le pool de Pokémon (IDs) pour une collection selon sa catégorie et options.
 *
 * @param string $category Slug de catégorie
 * @param array  $options  Options de composition (include_*, display_mode, etc.)
 * @return array Liste de tableaux avec au minimum 'id', 'dex_number', 'name_fr', 'name_en', 'form_variant_id', 'slug'
 */
function poke_hub_collections_get_pool(string $category, array $options = []): array {
    global $wpdb;

    $pokemon_table       = pokehub_get_table('pokemon');
    $form_variants_table = pokehub_get_table('pokemon_form_variants');
    $generations_table   = pokehub_get_table('generations');

    if (empty($pokemon_table) || empty($form_variants_table)) {
        return [];
    }

    $opts = array_merge(poke_hub_collections_default_options(), $options);
    if (array_key_exists('include_forms', $options)) {
        $opts = poke_hub_collections_merge_legacy_include_forms_option($opts);
    }
    $opts = poke_hub_collections_derive_gender_symbol_option($opts);
    $pool_show_only = poke_hub_collections_normalize_pool_show_only($opts);
    $opts['only_final_evolution']      = ($pool_show_only === 'final');
    $opts['only_special_species_pool'] = ($pool_show_only === 'special_all');
    if (array_key_exists('exclude_mega', $options) && !array_key_exists('include_mega', $options)) {
        $opts['include_mega'] = empty($options['exclude_mega']);
    }
    $where = ['1 = 1'];

    // Filtre par catégorie
    switch ($category) {
        case 'costume':
        case 'costume_shiny':
            $where[] = "(LOWER(TRIM(COALESCE(fv.category, ''))) = 'costume' OR (p.extra IS NOT NULL AND (p.extra LIKE '%\"is_event_costumed\":true%' OR p.extra LIKE '%\"is_event_costumed\": true%')))";
            break;
        case 'shadow':
            $where[] = "(
                p.extra IS NOT NULL AND p.extra != ''
                AND TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(p.extra, '$.release.shadow')), '')) != ''
            )";
            break;
        case 'purified':
            $where[] = 'p.has_purified = 1';
            break;
        case 'gigantamax':
            // Fiches forme Gigamax OU fiche (souvent forme par défaut) dont la date est dans extra → release.gigantamax
            // (certaines bases n’ont pas de ligne « variante gigantamax » distincte, seulement le champ extra).
            $where[] = "(
                fv.category = 'gigantamax'
                OR fv.form_slug LIKE '%gigantamax%'
                OR (
                    p.extra IS NOT NULL
                    AND p.extra != ''
                    AND TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(p.extra, '$.release.gigantamax')), '')) != ''
                )
            )";
            break;
        case 'dynamax':
            $where[] = "(fv.category = 'dynamax' OR fv.form_slug LIKE '%dynamax%' OR fv.form_slug = 'normal')";
            break;
        case 'background':
            $links_table = pokehub_get_table('pokemon_background_pokemon_links');
            if ($links_table) {
                $where[] = "EXISTS (SELECT 1 FROM {$links_table} l WHERE l.pokemon_id = p.id)";
            }
            break;
        case 'background_shiny':
            $links_table = pokehub_get_table('pokemon_background_pokemon_links');
            if ($links_table) {
                $where[] = "EXISTS (SELECT 1 FROM {$links_table} l WHERE l.pokemon_id = p.id AND (l.is_shiny_locked = 0 OR l.is_shiny_locked IS NULL))";
            }
            break;
        case 'background_special':
            $links_table   = pokehub_get_table('pokemon_background_pokemon_links');
            $bg_table     = pokehub_get_table('pokemon_backgrounds');
            if ($links_table && $bg_table) {
                $where[] = "EXISTS (SELECT 1 FROM {$links_table} l INNER JOIN {$bg_table} b ON l.background_id = b.id WHERE l.pokemon_id = p.id AND LOWER(TRIM(COALESCE(b.background_type, ''))) = 'special')";
            }
            break;
        case 'background_shiny_special': {
            $links_table   = pokehub_get_table('pokemon_background_pokemon_links');
            $bg_table     = pokehub_get_table('pokemon_backgrounds');
            if ($links_table && $bg_table) {
                $where[] = "EXISTS (SELECT 1 FROM {$links_table} l INNER JOIN {$bg_table} b ON l.background_id = b.id WHERE l.pokemon_id = p.id AND l.is_shiny_locked = 0 AND LOWER(TRIM(COALESCE(b.background_type, ''))) = 'special')";
            }
            break;
        }
        case 'background_places':
            $links_table   = pokehub_get_table('pokemon_background_pokemon_links');
            $bg_table     = pokehub_get_table('pokemon_backgrounds');
            if ($links_table && $bg_table) {
                $where[] = "EXISTS (SELECT 1 FROM {$links_table} l INNER JOIN {$bg_table} b ON l.background_id = b.id WHERE l.pokemon_id = p.id AND LOWER(TRIM(COALESCE(b.background_type, ''))) IN ('location', 'lieu', 'place'))";
            }
            break;
        case 'background_shiny_places': {
            $links_table   = pokehub_get_table('pokemon_background_pokemon_links');
            $bg_table     = pokehub_get_table('pokemon_backgrounds');
            if ($links_table && $bg_table) {
                $where[] = "EXISTS (SELECT 1 FROM {$links_table} l INNER JOIN {$bg_table} b ON l.background_id = b.id WHERE l.pokemon_id = p.id AND l.is_shiny_locked = 0 AND LOWER(TRIM(COALESCE(b.background_type, ''))) IN ('location', 'lieu', 'place'))";
            }
            break;
        }
        case 'perfect_4':
        case 'shiny':
        case 'lucky':
        case 'legendary_mythical_ultra':
        case 'custom':
        default:
            break;
    }

    // switch_battle : jamais en liste (on garde la fiche de base, pas la forme en combat).
    $where[] = "(fv.id IS NULL OR LOWER(TRIM(COALESCE(fv.category, ''))) != 'switch_battle')";

    $tok_reg   = poke_hub_collections_regional_form_variant_slug_tokens();
    $in_ph_reg = implode(',', array_fill(0, count($tok_reg), '%s'));
    $re_reg1   = '^(alola|alolan|galar|galarian|hisui|hisuian|paldea|paldean)(-[0-9]+)?$';
    $re_reg2   = '[-_](alola|alolan|galar|galarian|hisui|hisuian|paldea|paldean)([._-].*)?$';
    $re_p_slug = '[-_](alola|alolan|galar|galarian|paldea|paldean|hisui|hisuian)(_[0-9]+)?$';
    $regional_row_match = $wpdb->prepare(
        "(
  (
    fv.id IS NOT NULL
    AND (
      LOWER(TRIM(COALESCE(fv.category, ''))) = 'regional'
      OR (fv.form_slug IS NOT NULL AND TRIM(fv.form_slug) != '' AND LOWER(TRIM(fv.form_slug)) IN ({$in_ph_reg}))
      OR (fv.form_slug IS NOT NULL AND LOWER(fv.form_slug) REGEXP %s)
      OR (fv.form_slug IS NOT NULL AND LOWER(fv.form_slug) REGEXP %s)
      OR (fv.extra IS NOT NULL AND fv.extra != '' AND LOWER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(fv.extra, '$.form_type')), ''))) = 'regional')
    )
  )
  OR (fv.extra IS NOT NULL AND fv.extra LIKE %s)
  OR (
    p.slug IS NOT NULL
    AND TRIM(p.slug) != ''
    AND LOWER(p.slug) REGEXP %s
  )
)",
        array_merge($tok_reg, [ $re_reg1, $re_reg2, '%"form_type":"regional"%', $re_p_slug ] )
    );
    if ( $regional_row_match === false ) {
        $regional_row_match = '(0)';
    }

    // Formes : « One entry per species » décoché (one_per_species = false) = même requête qu’ex-« Include all forms »
    // (lignes distinctes Unown, etc.). Coché = repli *-family* / fiche par défaut + filtre is_default plus bas.
    if (empty($opts['one_per_species'])) {
        $where[] = $wpdb->prepare(
            "(p.slug IS NULL OR p.slug = '' OR LOWER(TRIM(p.slug)) NOT REGEXP %s)",
            '-family$'
        );
        $where[] = $wpdb->prepare(
            "(p.slug IS NULL OR p.slug = '' OR LOWER(TRIM(p.slug)) NOT REGEXP %s)",
            '-normal$'
        );
        $visual_dex = poke_hub_collections_dex_for_visual_variants_base_slug_only();
        if ( $visual_dex !== [] ) {
            $in = implode( ',', $visual_dex );
            $where[] = "NOT (
                p.dex_number IN ({$in})
                AND p.slug IS NOT NULL AND TRIM( p.slug ) != ''
                AND p.slug NOT LIKE '%-%'
                AND EXISTS (
                    SELECT 1 FROM {$pokemon_table} p2
                    WHERE p2.dex_number = p.dex_number
                      AND p2.id <> p.id
                      AND p2.slug IS NOT NULL AND TRIM( p2.slug ) != ''
                      AND LOWER( p2.slug ) LIKE CONCAT( LOWER( TRIM( p.slug ) ), '-%' )
                )
            )";
        }
    } else {
        $pt = $pokemon_table;
        $one_per_slug = $wpdb->prepare(
            "(
  (p.slug IS NOT NULL AND LOWER(TRIM(p.slug)) REGEXP %s)
  OR (
    NOT EXISTS (
      SELECT 1 FROM {$pt} AS pf
      WHERE pf.dex_number = p.dex_number
        AND pf.slug IS NOT NULL
        AND LOWER(TRIM(pf.slug)) REGEXP %s
    )
    AND p.is_default = 1
    AND (p.slug IS NULL OR p.slug = '' OR LOWER(TRIM(p.slug)) NOT REGEXP %s)
  )
)",
            '-family$',
            '-family$',
            '-normal$'
        );
        if ( $one_per_slug === false ) {
            $one_per_slug = '(0)';
        }
        // Toutes les disjonctions doivent être ici : une 2e clause AND sur is_default|méga ne suffisait pas
        // (les méga ne passent pas one_per_slug, elles n’arrivaient jamais à la 2e partie).
        $one_per_disjuncts = [ "({$one_per_slug})" ];
        if ( !empty($opts['include_regional_forms']) && is_string( $regional_row_match ) && $regional_row_match !== '' && $regional_row_match !== '(0)' ) {
            $one_per_disjuncts[] = "({$regional_row_match})";
        }
        if ( !empty($opts['include_mega']) ) {
            $one_per_disjuncts[] = "(LOWER(TRIM(COALESCE(fv.category, ''))) IN ('mega', 'megax', 'primal'))";
        }
        if ( !empty($opts['include_gigantamax']) ) {
            $one_per_disjuncts[] = "(
    LOWER(TRIM(COALESCE(fv.category, ''))) = 'gigantamax'
    OR (fv.form_slug IS NOT NULL AND LOWER(fv.form_slug) LIKE '%gigantamax%')
  )";
        }
        if ( !empty($opts['include_dynamax']) ) {
            $one_per_disjuncts[] = "(
    LOWER(TRIM(COALESCE(fv.category, ''))) = 'dynamax'
    OR (fv.form_slug IS NOT NULL AND LOWER(fv.form_slug) LIKE '%dynamax%')
  )";
        }
        if ( !empty($opts['include_costumes']) ) {
            $one_per_disjuncts[] = "(
    LOWER(TRIM(COALESCE(fv.category, ''))) = 'costume'
    OR (p.extra IS NOT NULL AND (p.extra LIKE '%\"is_event_costumed\":true%' OR p.extra LIKE '%\"is_event_costumed\": true%'))
  )";
        }
        $one_per_disjuncts[] = "(LOWER(TRIM(COALESCE(fv.category, ''))) IN ('clone', 'fusion', 'special'))";

        $where[] = '(' . implode( ' OR ', $one_per_disjuncts ) . ')';
    }

    // Options « en plus » (Méga, Gigantamax, Dynamax, costumes, formes régionales) : uniquement pour les catégories non spécifiques
    $is_specific = poke_hub_collections_category_is_specific($category);
    if (!$is_specific) {
        if (empty($opts['include_regional_forms'])) {
            $where[] = "NOT ({$regional_row_match})";
        }
        if (!empty($opts['include_costumes'])) {
            // rien
        } elseif (empty($opts['include_costumes'])) {
            // Exclure uniquement costumés (variante + flag extra) — pas toutes les formes non « normal ».
            // Mêmes motifs LIKE que le filtre de catégorie « costume » (lignes case costume / costume_shiny).
            $where[] = "(
                LOWER(TRIM(COALESCE(fv.category, ''))) != 'costume'
                AND (p.extra IS NULL OR p.extra = '' OR (
                    p.extra NOT LIKE '%\"is_event_costumed\":true%'
                    AND p.extra NOT LIKE '%\"is_event_costumed\": true%'
                ))
            )";
        }
        if (empty($opts['include_mega'])) {
            $where[] = "(fv.category IS NULL OR fv.category = '' OR fv.category NOT IN ('mega', 'megax', 'primal'))";
        }
        if (empty($opts['include_gigantamax'])) {
            $where[] = "(fv.category IS NULL OR fv.category = '' OR (fv.category != 'gigantamax' AND fv.form_slug NOT LIKE '%gigantamax%'))";
        }
        if (empty($opts['include_dynamax'])) {
            $where[] = "(fv.category IS NULL OR fv.category = '' OR (fv.category != 'dynamax' AND fv.form_slug NOT LIKE '%dynamax%'))";
        }
        // Le dimorphisme collection (include_gender) = lignes mâle/femelle dérivées en PHP, pas des variantes fv.category = gender.
        $where[] = "COALESCE(fv.category, '') != 'gender'";
    }

    $evolution_table = pokehub_get_table('pokemon_evolutions');
    if ($evolution_table && !empty($opts['only_final_evolution'])) {
        $where[] = "p.id NOT IN (SELECT DISTINCT e.base_pokemon_id FROM {$evolution_table} e WHERE e.base_pokemon_id > 0)";
    }

    $where_sql = implode(' AND ', $where);

    // Contexte de date de sortie selon la catégorie (uniquement les Pokémon sortis dans GO)
    $release_context = 'normal';
    switch ($category) {
        case 'shiny':
        case 'costume_shiny':
        case 'background_shiny':
        case 'background_shiny_special':
        case 'background_shiny_places':
            $release_context = 'shiny';
            break;
        case 'shadow':
        case 'purified':
            $release_context = 'shadow';
            break;
        case 'gigantamax':
            $release_context = 'gigantamax';
            break;
        case 'dynamax':
            $release_context = 'dynamax';
            break;
        case 'perfect_4':
        case 'costume':
        case 'background':
        case 'background_special':
        case 'background_places':
        case 'lucky':
        case 'legendary_mythical_ultra':
        case 'custom':
        default:
            $release_context = 'normal';
            break;
    }

    $gen_select = $generations_table
        ? "p.generation_id, COALESCE(g.name_fr, g.name_en, g.slug, '') AS generation_name, COALESCE(g.generation_number, 0) AS generation_number"
        : "p.generation_id, '' AS generation_name, 0 AS generation_number";
    $gen_join   = $generations_table
        ? "LEFT JOIN {$generations_table} g ON p.generation_id = g.id"
        : '';
    $order_gen  = $generations_table
        ? "COALESCE(g.generation_number, 999) ASC,"
        : "p.generation_id ASC,";

    $sql = "SELECT p.id, p.dex_number, p.name_fr, p.name_en, p.slug, p.form_variant_id, p.extra,
                   COALESCE(fv.label, fv.form_slug, '') AS form_label,
                   COALESCE(fv.form_slug, '') AS form_slug,
                   COALESCE(fv.category, 'normal') AS form_category,
                   {$gen_select}
            FROM {$pokemon_table} p
            LEFT JOIN {$form_variants_table} fv ON p.form_variant_id = fv.id
            {$gen_join}
            WHERE {$where_sql}
            ORDER BY {$order_gen} p.dex_number ASC, p.slug ASC";

    $results = $wpdb->get_results($sql, ARRAY_A);
    if (!is_array($results)) {
        return [];
    }

    // Ne garder que les Pokémon ayant une sortie GO pour ce contexte,
    // en se basant sur la date de la fiche courante (pas de propagation évolution).
    $filtered = [];
    foreach ($results as $row) {
        if (!poke_hub_collections_row_passes_pool_release_filter($row, $release_context, $opts)) {
            continue;
        }
        $row = poke_hub_collections_maybe_mark_gigantamax_synthetic_base_row($row, $category, $opts);
        $row = poke_hub_collections_maybe_mark_dynamax_synthetic_base_row($row, $category, $opts);
        if (function_exists('poke_hub_pokemon_is_baby_from_row')) {
            $row['is_baby'] = poke_hub_pokemon_is_baby_from_row($row);
        } else {
            $row['is_baby'] = false;
        }
        if (function_exists('poke_hub_pokemon_species_special_group_from_row')) {
            $row['species_special_group'] = poke_hub_pokemon_species_special_group_from_row($row);
        } else {
            $row['species_special_group'] = '';
        }
        unset($row['extra']);
        $filtered[] = $row;
    }
    $results = $filtered;

    if (!poke_hub_collections_category_is_specific($category)
        && !empty($opts['include_gigantamax'])
        && $category !== 'gigantamax'
        && $results !== []) {
        $results = poke_hub_collections_apply_marked_synthetic_gigantamax($results);
    }

    if (!poke_hub_collections_category_is_specific($category)
        && !empty($opts['include_dynamax'])
        && $category !== 'dynamax'
        && $results !== []) {
        $results = poke_hub_collections_apply_marked_synthetic_dynamax($results);
    }

    if ($category === 'gigantamax' && $results !== []) {
        $results = poke_hub_collections_merge_gigantamax_synthetic_pool($results);
    }

    if ($category === 'dynamax' && $results !== []) {
        $results = poke_hub_collections_merge_dynamax_synthetic_pool($results);
    }

    if (empty($opts['include_baby_pokemon']) && $pool_show_only !== 'baby' && $results !== []) {
        $results = array_values(
            array_filter(
                $results,
                static function (array $row) {
                    return !poke_hub_collections_row_is_baby_pokemon($row);
                }
            )
        );
    }

    if ($results !== [] && $pool_show_only !== '' && $pool_show_only !== 'final' && $pool_show_only !== 'special_attacks') {
        if ($pool_show_only === 'baby') {
            $results = array_values(
                array_filter(
                    $results,
                    static function (array $row) {
                        return poke_hub_collections_row_is_baby_pokemon($row);
                    }
                )
            );
        } elseif ($pool_show_only === 'special_all') {
            $results = array_values(
                array_filter(
                    $results,
                    static function (array $row) {
                        $g = (string) ($row['species_special_group'] ?? '');

                        return in_array($g, ['legendary', 'mythical', 'ultra_beast'], true);
                    }
                )
            );
        } elseif ($pool_show_only === 'legendary') {
            $results = array_values(
                array_filter(
                    $results,
                    static function (array $row) {
                        return (string) ($row['species_special_group'] ?? '') === 'legendary';
                    }
                )
            );
        } elseif ($pool_show_only === 'mythical') {
            $results = array_values(
                array_filter(
                    $results,
                    static function (array $row) {
                        return (string) ($row['species_special_group'] ?? '') === 'mythical';
                    }
                )
            );
        } elseif ($pool_show_only === 'ultra_beast') {
            $results = array_values(
                array_filter(
                    $results,
                    static function (array $row) {
                        return (string) ($row['species_special_group'] ?? '') === 'ultra_beast';
                    }
                )
            );
        }
        // special_attacks : pas encore de signal fiable sur les lignes pool — aucun filtre additionnel.
    }

    if ($results !== []
        && (
            empty($opts['include_legendary_pokemon'])
            || empty($opts['include_mythical_pokemon'])
            || empty($opts['include_ultra_beast_pokemon'])
        )
    ) {
        $incl_leg  = !empty($opts['include_legendary_pokemon']);
        $incl_myth = !empty($opts['include_mythical_pokemon']);
        $incl_ub   = !empty($opts['include_ultra_beast_pokemon']);
        $results   = array_values(
            array_filter(
                $results,
                static function (array $row) use ($incl_leg, $incl_myth, $incl_ub) {
                    $g = (string) ($row['species_special_group'] ?? '');
                    if ($g === 'legendary' && ! $incl_leg) {
                        return false;
                    }
                    if ($g === 'mythical' && ! $incl_myth) {
                        return false;
                    }
                    if ($g === 'ultra_beast' && ! $incl_ub) {
                        return false;
                    }

                    return true;
                }
            )
        );
    }

    if ($category === 'legendary_mythical_ultra' && $results !== []) {
        $results = array_values(
            array_filter(
                $results,
                static function (array $row) {
                    $g = (string) ($row['species_special_group'] ?? '');

                    return in_array($g, ['legendary', 'mythical', 'ultra_beast'], true);
                }
            )
        );
    }

    if ($results !== []) {
        foreach ($results as &$row) {
            if (is_array($row)) {
                $row['gender_display'] = poke_hub_collections_gender_display_for_row($row);
            }
        }
        unset($row);
    }

    if ((!empty($opts['include_both_sexes_collector']) || !empty($opts['include_gender'])) && $results !== []) {
        $results = poke_hub_collections_apply_sex_collector_pool($results, $opts);
    }

    if (!empty($opts['include_backgrounds']) && $results !== []) {
        $results = poke_hub_collections_apply_synthetic_go_background_pool($results, $category, $opts);
    }

    if ($category === 'custom' && !empty($opts['only_shiny']) && function_exists('pokehub_pokemon_can_be_shiny') && $results !== []) {
        $results = array_values(
            array_filter(
                $results,
                static function (array $row) {
                    $pid = (int) ($row['id'] ?? 0);
                    if (!empty($row['synthetic_go_background_link_pokemon_id'])) {
                        $pid = (int) $row['synthetic_go_background_link_pokemon_id'];
                    } elseif (!empty($row['synthetic_sex_base_id'])) {
                        $pid = (int) $row['synthetic_sex_base_id'];
                    } elseif (!empty($row['gigantamax_base_pokemon_id'])) {
                        $pid = (int) $row['gigantamax_base_pokemon_id'];
                    } elseif ( ! empty( $row['dynamax_base_pokemon_id'] ) ) {
                        $pid = (int) $row['dynamax_base_pokemon_id'];
                    }
                    if ($pid <= 0) {
                        return false;
                    }

                    return pokehub_pokemon_can_be_shiny($pid);
                }
            )
        );
    }

    if ( $results !== [] ) {
        foreach ( $results as &$r ) {
            if ( is_array( $r ) ) {
                $r = poke_hub_collections_pool_row_strip_redundant_form_label( $r );
            }
        }
        unset( $r );
    }

    if ( $results !== [] ) {
        poke_hub_collections_pool_rows_add_pogo_tokens( $results );
    }

    return poke_hub_collections_sort_pool_display( $results );
}

/**
 * true si extra contient une date / valeur pour release.gigantamax (même règle que le WHERE SQL de la catégorie dédiée).
 */
function poke_hub_collections_row_has_gigantamax_release_in_extra(array $row): bool {
    $extra = $row['extra'] ?? null;
    if (!is_string($extra) || $extra === '') {
        return false;
    }
    $data = json_decode($extra, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_array($data)) {
        return false;
    }
    $g = $data['release']['gigantamax'] ?? null;
    if (is_string($g)) {
        return trim($g) !== '';
    }
    if (is_array($g)) {
        return $g !== [];
    }

    return (bool) $g;
}

/**
 * Avant supprimer le JSON extra : marque les fiches de base (sans variante G-Max en table) éligibles,
 * pour {@see poke_hub_collections_apply_marked_synthetic_gigantamax()} (collections custom / shiny + « Afficher Gigantamax »).
 */
function poke_hub_collections_maybe_mark_gigantamax_synthetic_base_row(array $row, string $category, array $opts): array {
    if (poke_hub_collections_category_is_specific($category)
        || empty($opts['include_gigantamax'])
        || $category === 'gigantamax') {
        return $row;
    }
    if (poke_hub_collections_gigantamax_row_is_real_form($row)) {
        return $row;
    }
    if (!poke_hub_collections_row_has_gigantamax_release_in_extra($row)) {
        return $row;
    }
    $pokemon_id = (int) ($row['id'] ?? 0);
    if ($pokemon_id <= 0) {
        return $row;
    }
    if (function_exists('poke_hub_pokemon_is_released_in_go') && !poke_hub_pokemon_is_released_in_go($pokemon_id, 'gigantamax')) {
        return $row;
    }
    $row['__pokehub_c_gigantamax_src'] = 1;

    return $row;
}

/**
 * true si extra contient une date / valeur pour release.dynamax (fiche de base, pas de variante en table).
 */
function poke_hub_collections_row_has_dynamax_release_in_extra( array $row ): bool {
    $extra = $row['extra'] ?? null;
    if ( ! is_string( $extra ) || $extra === '' ) {
        return false;
    }
    $data = json_decode( $extra, true, 512, JSON_INVALID_UTF8_SUBSTITUTE );
    if ( ! is_array( $data ) ) {
        return false;
    }
    $d = $data['release']['dynamax'] ?? null;
    if ( is_string( $d ) ) {
        return trim( $d ) !== '';
    }
    if ( is_array( $d ) ) {
        return $d !== [];
    }

    return (bool) $d;
}

/**
 * Avant de supprimer extra : fiche de base éligible pour une tuile Dynamax « virtuelle »
 * (release.dynamax en extra, variante dynamax absente côté table), si option + sortie GO.
 */
function poke_hub_collections_maybe_mark_dynamax_synthetic_base_row( array $row, string $category, array $opts ): array {
    if ( poke_hub_collections_category_is_specific( $category )
        || empty( $opts['include_dynamax'] )
        || $category === 'dynamax' ) {
        return $row;
    }
    if ( poke_hub_collections_dynamax_row_is_real_form( $row ) ) {
        return $row;
    }
    if ( ! poke_hub_collections_row_has_dynamax_release_in_extra( $row ) ) {
        return $row;
    }
    $pokemon_id = (int) ( $row['id'] ?? 0 );
    if ( $pokemon_id <= 0 ) {
        return $row;
    }
    if ( function_exists( 'poke_hub_pokemon_is_released_in_go' ) && ! poke_hub_pokemon_is_released_in_go( $pokemon_id, 'dynamax' ) ) {
        return $row;
    }
    $row['__pokehub_c_dynamax_src'] = 1;

    return $row;
}

/**
 * Pour les fiches de base marquées (Gigamax uniquement dans extra, pas de variante en table) :
 * conserve la **forme de base** dans le pool et **ajoute** une entrée G-Max synthétique (id 2100…),
 * sauf si une vraie forme G-Max existe déjà pour le même n° de dex (évite doublon G-Max).
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function poke_hub_collections_apply_marked_synthetic_gigantamax(array $rows): array {
    if ($rows === []) {
        return [];
    }
    $real_dex = [];
    foreach ($rows as $r) {
        if (!poke_hub_collections_gigantamax_row_is_real_form($r)) {
            continue;
        }
        $dex = isset($r['dex_number']) ? (int) $r['dex_number'] : 0;
        if ($dex > 0) {
            $real_dex[$dex] = true;
        }
    }
    $out       = [];
    $seen_base = [];
    foreach ($rows as $row) {
        $mark = !empty($row['__pokehub_c_gigantamax_src']);
        unset($row['__pokehub_c_gigantamax_src']);
        if (poke_hub_collections_gigantamax_row_is_real_form($row)) {
            $out[] = $row;
            continue;
        }
        if (!$mark) {
            $out[] = $row;
            continue;
        }
        $base_id = (int) ($row['id'] ?? 0);
        if ($base_id > 0 && isset($seen_base[$base_id])) {
            continue;
        }
        if ($base_id > 0) {
            $seen_base[$base_id] = true;
        }
        $dex = isset($row['dex_number']) ? (int) $row['dex_number'] : 0;
        $out[] = $row;
        if ($dex > 0 && !empty($real_dex[$dex])) {
            continue;
        }
        $out[] = poke_hub_collections_gigantamax_build_synthetic_from_base_row($row);
    }

    return $out;
}

/**
 * ID d’enregistrement en collection (collection_items) pour un Gigamax « virtuel » dérivé d’une fiche de base
 * (pas de variante gigantamax en table pokemon).
 */
function poke_hub_collections_gigantamax_synthetic_pokemon_id(int $base_pokemon_id): int {
    $offset = 2100000000; // Réservé : hors plage des id auto-incrémentés habituels
    return $offset + $base_pokemon_id;
}

/**
 * @param int $pokemon_id ID possibly returned by {@see poke_hub_collections_gigantamax_synthetic_pokemon_id()}
 */
function poke_hub_collections_gigantamax_is_synthetic_pokemon_id(int $pokemon_id): bool {
    return $pokemon_id >= 2100000000 && $pokemon_id < 2200000000;
}

/**
 * Ligne de pool = vraie forme Gigantamax en base (variante) ?
 * Les entrées {@see poke_hub_collections_gigantamax_build_synthetic_from_base_row} ne comptent pas.
 */
function poke_hub_collections_gigantamax_row_is_real_form(array $row): bool {
    if (!empty($row['synthetic_gigantamax'])) {
        return false;
    }
    $cat = strtolower(trim((string) ($row['form_category'] ?? '')));
    if ($cat === 'gigantamax') {
        return true;
    }
    $slug = strtolower((string) ($row['slug'] ?? ''));
    if ($slug !== '' && strpos($slug, 'gigantamax') !== false) {
        return true;
    }
    $label = strtolower((string) ($row['form_label'] ?? ''));
    return $label !== '' && strpos($label, 'gigantamax') !== false;
}

/**
 * Ligne de pool = vraie forme Dynamax en base (variante) ?
 * Les entrées {@see poke_hub_collections_dynamax_build_synthetic_from_base_row} ne comptent pas.
 */
function poke_hub_collections_dynamax_row_is_real_form( array $row ): bool {
    if ( ! empty( $row['synthetic_dynamax'] ) ) {
        return false;
    }
    $cat = strtolower( trim( (string) ( $row['form_category'] ?? '' ) ) );
    if ( $cat === 'dynamax' ) {
        return true;
    }
    $slug = strtolower( (string) ( $row['slug'] ?? '' ) );
    if ( $slug !== '' && strpos( $slug, 'dynamax' ) !== false ) {
        return true;
    }
    $label = strtolower( (string) ( $row['form_label'] ?? '' ) );

    return $label !== '' && strpos( $label, 'dynamax' ) !== false;
}

/**
 * Plage 1e9+ id : disjointe de Gigamax (2,1e9+), 32 bits OK (max 1,9e9 + id réel < 2^31-1).
 */
function poke_hub_collections_dynamax_synthetic_pokemon_id( int $base_pokemon_id ): int {
    return 1000000000 + (int) $base_pokemon_id;
}

/**
 * @param int $pokemon_id ID issue de {@see poke_hub_collections_dynamax_synthetic_pokemon_id()}
 */
function poke_hub_collections_dynamax_is_synthetic_pokemon_id( int $pokemon_id ): bool {
    return $pokemon_id >= 1000000000 && $pokemon_id < 2000000000;
}

/**
 * Construit une entrée de pool Dynamax quand seule la fiche de base a release.dynamax dans extra, sans variante en table.
 */
function poke_hub_collections_dynamax_build_synthetic_from_base_row( array $base ): array {
    $base_id = (int) ( $base['id'] ?? 0 );
    $slug    = trim( (string) ( $base['slug'] ?? '' ), " \t\n\r\0\x0B" );
    if ( $slug === '' || $slug === '0' ) {
        $dex = isset( $base['dex_number'] ) ? (int) $base['dex_number'] : 0;
        if ( $dex > 0 ) {
            $slug = sprintf( '%03d', $dex );
        } else {
            $slug = 'pokemon';
        }
    }
    if ( strpos( $slug, 'dynamax-' ) === 0 ) {
        $dx_slug = $slug;
    } else {
        $dx_slug = 'dynamax-' . $slug;
    }
    $name_fr_base = trim( (string) ( $base['name_fr'] ?? '' ) );
    $name_en_base = trim( (string) ( $base['name_en'] ?? '' ) );
    if ( $name_fr_base !== '' ) {
        $name_fr = $name_fr_base . ' Dynamax';
    } elseif ( $name_en_base !== '' ) {
        $name_fr = $name_en_base . ' Dynamax';
    } else {
        $name_fr = 'Dynamax';
    }
    if ( $name_en_base !== '' ) {
        $name_en = 'Dynamax ' . $name_en_base;
    } else {
        $name_en = $name_fr;
    }
    $out = $base;
    unset( $out['extra'] );
    $out['id']                        = poke_hub_collections_dynamax_synthetic_pokemon_id( $base_id );
    $out['form_variant_id']         = 0;
    $out['slug']                    = $dx_slug;
    $out['name_fr']                 = $name_fr;
    $out['name_en']                 = $name_en;
    $out['form_category']           = 'dynamax';
    $out['form_label']              = 'Dynamax';
    $out['synthetic_dynamax']       = true;
    $out['dynamax_base_pokemon_id'] = $base_id;

    return (array) apply_filters( 'poke_hub_collections_synthetic_dynamax_row', $out, $base );
}

/**
 * Comme apply_marked_synthetic_gigantamax, pour des dates release.dynamax sans fiche variante.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function poke_hub_collections_apply_marked_synthetic_dynamax( array $rows ): array {
    if ( $rows === [] ) {
        return [];
    }
    $real_dex = [];
    foreach ( $rows as $r ) {
        if ( ! poke_hub_collections_dynamax_row_is_real_form( $r ) ) {
            continue;
        }
        $dex = isset( $r['dex_number'] ) ? (int) $r['dex_number'] : 0;
        if ( $dex > 0 ) {
            $real_dex[ $dex ] = true;
        }
    }
    $out        = [];
    $seen_base  = [];
    foreach ( $rows as $row ) {
        $mark = ! empty( $row['__pokehub_c_dynamax_src'] );
        unset( $row['__pokehub_c_dynamax_src'] );
        if ( poke_hub_collections_dynamax_row_is_real_form( $row ) ) {
            $out[] = $row;
            continue;
        }
        if ( ! $mark ) {
            $out[] = $row;
            continue;
        }
        $base_id = (int) ( $row['id'] ?? 0 );
        if ( $base_id > 0 && isset( $seen_base[ $base_id ] ) ) {
            continue;
        }
        if ( $base_id > 0 ) {
            $seen_base[ $base_id ] = true;
        }
        $dex = isset( $row['dex_number'] ) ? (int) $row['dex_number'] : 0;
        $out[] = $row;
        if ( $dex > 0 && ! empty( $real_dex[ $dex ] ) ) {
            continue;
        }
        $out[] = poke_hub_collections_dynamax_build_synthetic_from_base_row( $row );
    }

    return $out;
}

/**
 * Catégorie collection « dynamax » : une ligne Dynamax (réelle ou synthétique) par espèce.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function poke_hub_collections_merge_dynamax_synthetic_pool( array $rows ): array {
    if ( $rows === [] ) {
        return [];
    }
    $real_dex = [];
    foreach ( $rows as $r ) {
        if ( ! poke_hub_collections_dynamax_row_is_real_form( $r ) ) {
            continue;
        }
        $dex = isset( $r['dex_number'] ) ? (int) $r['dex_number'] : 0;
        if ( $dex > 0 ) {
            $real_dex[ $dex ] = true;
        }
    }
    $out       = [];
    $seen_base = [];
    foreach ( $rows as $row ) {
        if ( poke_hub_collections_dynamax_row_is_real_form( $row ) ) {
            $out[] = $row;
            continue;
        }
        $base_id = (int) ( $row['id'] ?? 0 );
        if ( $base_id <= 0 ) {
            continue;
        }
        if ( isset( $seen_base[ $base_id ] ) ) {
            continue;
        }
        $seen_base[ $base_id ] = true;
        $dex = isset( $row['dex_number'] ) ? (int) $row['dex_number'] : 0;
        if ( $dex > 0 && ! empty( $real_dex[ $dex ] ) ) {
            continue;
        }
        $out[] = poke_hub_collections_dynamax_build_synthetic_from_base_row( $row );
    }

    return $out;
}

/**
 * Construit une entrée de pool côté Gigamax quand seule la fiche d’évolution (ex. Florizarre) existe avec release.gigantamax dans extra.
 */
function poke_hub_collections_gigantamax_build_synthetic_from_base_row(array $base): array {
    $base_id = (int) ($base['id'] ?? 0);
    $slug    = trim((string) ($base['slug'] ?? ''), " \t\n\r\0\x0B");
    if ($slug === '' || $slug === '0') {
        $dex = isset($base['dex_number']) ? (int) $base['dex_number'] : 0;
        if ($dex > 0) {
            $slug = sprintf('%03d', $dex);
        } else {
            $slug = 'pokemon';
        }
    }
    if (strpos($slug, 'gigantamax-') === 0) {
        $gmax_slug = $slug;
    } else {
        $gmax_slug = 'gigantamax-' . $slug;
    }
    $name_fr_base = trim((string) ($base['name_fr'] ?? ''));
    $name_en_base = trim((string) ($base['name_en'] ?? ''));
    if ($name_fr_base !== '') {
        $name_fr = $name_fr_base . ' Gigamax';
    } elseif ($name_en_base !== '') {
        $name_fr = $name_en_base . ' Gigamax';
    } else {
        $name_fr = 'Gigamax';
    }
    if ($name_en_base !== '') {
        $name_en = 'Gigantamax ' . $name_en_base;
    } else {
        $name_en = $name_fr;
    }
    $out = $base;
    unset($out['extra']);
    $out['id']                 = poke_hub_collections_gigantamax_synthetic_pokemon_id($base_id);
    $out['form_variant_id']   = 0;
    $out['slug']              = $gmax_slug;
    $out['name_fr']            = $name_fr;
    $out['name_en']            = $name_en;
    $out['form_category']      = 'gigantamax';
    $out['form_label']         = 'Gigantamax';
    $out['synthetic_gigantamax'] = true;
    $out['gigantamax_base_pokemon_id'] = $base_id;

    return (array) apply_filters('poke_hub_collections_synthetic_gigantamax_row', $out, $base);
}

/**
 * Collections **catégorie gigantamax** : uniquement des entrées « Gigamax » (vraies variantes ou synthétiques).
 * Les fiches base ne restent pas dans le pool — seule la ligne G-Max (ou synthétique depuis la date).
 *
 * @param array $rows Résultat filtré pour la catégorie gigantamax
 * @return array
 */
function poke_hub_collections_merge_gigantamax_synthetic_pool(array $rows): array {
    if ($rows === []) {
        return [];
    }
    $real_gigantamax_dex = [];
    foreach ($rows as $r) {
        if (!poke_hub_collections_gigantamax_row_is_real_form($r)) {
            continue;
        }
        $dex = isset($r['dex_number']) ? (int) $r['dex_number'] : 0;
        if ($dex > 0) {
            $real_gigantamax_dex[$dex] = true;
        }
    }
    $out       = [];
    $seen_base = [];
    foreach ($rows as $row) {
        if (poke_hub_collections_gigantamax_row_is_real_form($row)) {
            $out[] = $row;
            continue;
        }
        $base_id = (int) ($row['id'] ?? 0);
        if ($base_id <= 0) {
            continue;
        }
        if (isset($seen_base[$base_id])) {
            continue;
        }
        $seen_base[$base_id] = true;
        $dex = isset($row['dex_number']) ? (int) $row['dex_number'] : 0;
        if ($dex > 0 && !empty($real_gigantamax_dex[$dex])) {
            continue;
        }
        $out[] = poke_hub_collections_gigantamax_build_synthetic_from_base_row($row);
    }

    return $out;
}

/**
 * ID Pokémon (lien table fonds) pour savoir si la fiche a un arrière-plan GO, selon la ligne de pool
 * (fiche directe, mâle/femelle collectionneur, forme synthétique Gigamax).
 */
function poke_hub_collections_pool_row_pokemon_id_for_go_background_link(array $row): int {
    if (!empty($row['synthetic_sex_base_id'])) {
        return (int) $row['synthetic_sex_base_id'];
    }
    if (!empty($row['gigantamax_base_pokemon_id'])) {
        return (int) $row['gigantamax_base_pokemon_id'];
    }
    if ( ! empty( $row['dynamax_base_pokemon_id'] ) ) {
        return (int) $row['dynamax_base_pokemon_id'];
    }

    return (int) ($row['id'] ?? 0);
}

/**
 * ID d’enregistrement (collection_items) pour une tuile « avec fond GO » (stable, une clé par ligne source + id fond).
 */
function poke_hub_collections_go_background_synthetic_pokemon_id_from_source_and_background(int $source_pool_row_id, int $background_id): int {
    if ($source_pool_row_id <= 0 || $background_id <= 0) {
        return 0;
    }
    $h = (int) (sprintf(
        '%u',
        (int) crc32('pokehub_c_gobg:' . (string) $source_pool_row_id . ':' . (string) $background_id)
    ) % 20000000);

    return 2080000000 + $h;
}

/**
 * @param array<string, mixed> $source   Ligne de pool d’origine
 * @param string                 $bg_title Libellé du fond (pokemon_backgrounds.title), affiché sous le nom
 * @return array<string, mixed>|null
 */
function poke_hub_collections_go_background_synthetic_row_from_base( array $source, int $link_pokemon_id, int $background_id, string $bg_url, string $bg_title = '' ): ?array {
    $bg_url = trim($bg_url);
    if ($bg_url === '' || $link_pokemon_id <= 0 || $background_id <= 0) {
        return null;
    }
    $source_id   = (int) ($source['id'] ?? 0);
    $syntheticId = poke_hub_collections_go_background_synthetic_pokemon_id_from_source_and_background($source_id, $background_id);
    if ($syntheticId <= 0) {
        return null;
    }
    $t = $source;
    unset($t['__pokehub_c_gigantamax_src'], $t['__pokehub_c_dynamax_src']);
    $t['id']                                       = $syntheticId;
    $t['synthetic_go_background']                 = true;
    $t['synthetic_go_background_link_pokemon_id'] = $link_pokemon_id;
    $t['synthetic_go_background_background_id']   = $background_id;
    $t['synthetic_go_background_from_pool_row_id'] = $source_id;
    $t['background_image_url']                    = $bg_url;
    $t['form_label']                              = $bg_title !== '' ? $bg_title : ( trim( (string) ( $t['form_label'] ?? '' ) ) );

    return (array) apply_filters( 'poke_hub_collections_synthetic_go_background_row', $t, $source, $link_pokemon_id, $background_id, $bg_url, $bg_title );
}

/**
 * Collections non dédiées « fonds » : une ligne de base + si option, une tuile par fond GO avec l’image en arrière-plan.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function poke_hub_collections_apply_synthetic_go_background_pool(array $rows, string $category, array $opts): array {
    if ($rows === [] || !function_exists('poke_hub_collections_get_all_go_backgrounds_for_pool_row')) {
        return $rows;
    }
    if (poke_hub_collections_category_is_specific($category)) {
        return $rows;
    }
    $only_shiny = in_array($category, ['shiny', 'costume_shiny'], true) || ($category === 'custom' && !empty($opts['only_shiny']));
    $out        = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            $out[] = $row;
            continue;
        }
        $out[] = $row;
        if (!empty($row['synthetic_go_background'])) {
            continue;
        }
        $backgrounds = poke_hub_collections_get_all_go_backgrounds_for_pool_row( $row, $category, $only_shiny );
        if ( $backgrounds === [] ) {
            continue;
        }
        $link_pid = poke_hub_collections_pool_row_pokemon_id_for_go_background_link( $row );
        if ( $link_pid <= 0 ) {
            $link_pid = (int) ( $row['id'] ?? 0 );
        }
        if ( $link_pid <= 0 ) {
            continue;
        }
        foreach ($backgrounds as $bg) {
            $bg_id   = (int) ($bg['background_id'] ?? 0);
            $bg_url  = isset($bg['image_url']) ? trim((string) $bg['image_url']) : '';
            $bg_title = isset($bg['background_title']) ? trim((string) $bg['background_title']) : '';
            if ($bg_id <= 0 || $bg_url === '') {
                continue;
            }
            $extra = poke_hub_collections_go_background_synthetic_row_from_base( $row, $link_pid, $bg_id, $bg_url, $bg_title );
            if ($extra !== null) {
                $out[] = $extra;
            }
        }
    }

    return $out;
}

/**
 * Tri d’affichage harmonisé : génération -> dex -> nom -> base/costume/méga -> forme.
 *
 * @param array $rows
 * @return array
 */
function poke_hub_collections_sort_pool_display(array $rows): array {
    usort(
        $rows,
        static function (array $a, array $b): int {
            $genA = isset($a['generation_number']) ? (int) $a['generation_number'] : 0;
            $genB = isset($b['generation_number']) ? (int) $b['generation_number'] : 0;
            $effGenA = $genA > 0 ? $genA : 999;
            $effGenB = $genB > 0 ? $genB : 999;
            if ($effGenA !== $effGenB) {
                return $effGenA <=> $effGenB;
            }

            $dexA = isset($a['dex_number']) ? (int) $a['dex_number'] : 0;
            $dexB = isset($b['dex_number']) ? (int) $b['dex_number'] : 0;
            if ($dexA !== $dexB) {
                return $dexA <=> $dexB;
            }

            $nameA = trim((string) (($a['name_fr'] ?? '') !== '' ? $a['name_fr'] : ($a['name_en'] ?? '')));
            $nameB = trim((string) (($b['name_fr'] ?? '') !== '' ? $b['name_fr'] : ($b['name_en'] ?? '')));
            $nameAN = function_exists('mb_strtolower') ? mb_strtolower($nameA, 'UTF-8') : strtolower($nameA);
            $nameBN = function_exists('mb_strtolower') ? mb_strtolower($nameB, 'UTF-8') : strtolower($nameB);
            if ($nameAN !== $nameBN) {
                return $nameAN <=> $nameBN;
            }

            $rankA = function_exists('pokehub_pokemon_select_category_rank')
                ? pokehub_pokemon_select_category_rank((string) ($a['form_category'] ?? ''))
                : 3;
            $rankB = function_exists('pokehub_pokemon_select_category_rank')
                ? pokehub_pokemon_select_category_rank((string) ($b['form_category'] ?? ''))
                : 3;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            $formA = trim((string) ($a['form_label'] ?? ''));
            $formB = trim((string) ($b['form_label'] ?? ''));
            $formAN = function_exists('mb_strtolower') ? mb_strtolower($formA, 'UTF-8') : strtolower($formA);
            $formBN = function_exists('mb_strtolower') ? mb_strtolower($formB, 'UTF-8') : strtolower($formB);
            if ($formAN !== $formBN) {
                return $formAN <=> $formBN;
            }

            $idA = isset($a['id']) ? (int) $a['id'] : 0;
            $idB = isset($b['id']) ? (int) $b['id'] : 0;
            return $idA <=> $idB;
        }
    );

    return $rows;
}

/**
 * Groupe le pool de Pokémon par génération pour l'affichage (clé = libellé génération).
 *
 * @param array $pool Liste retournée par poke_hub_collections_get_pool (avec generation_id, generation_name, generation_number)
 * @return array [ 'Génération 1' => [ ... ], 'Génération 2' => [ ... ], '' => [ ... ] ] ('' = sans génération, en dernier)
 */
function poke_hub_collections_group_pool_by_generation(array $pool): array {
    $by_gen = [];
    foreach ($pool as $p) {
        $gen_num = isset($p['generation_number']) ? (int) $p['generation_number'] : 0;
        $gen_name = isset($p['generation_name']) && (string) $p['generation_name'] !== ''
            ? $p['generation_name']
            : ($gen_num > 0 ? sprintf(__('Generation %d', 'poke-hub'), $gen_num) : '');
        $key = $gen_name !== '' ? $gen_name : "\xFF"; // sans nom en dernier après tri
        if (!isset($by_gen[$key])) {
            $by_gen[$key] = ['order' => $gen_num > 0 ? $gen_num : 999, 'items' => []];
        }
        $by_gen[$key]['items'][] = $p;
    }
    uasort($by_gen, function ($a, $b) {
        return $a['order'] <=> $b['order'];
    });
    $out = [];
    foreach ($by_gen as $key => $data) {
        $label = $key === "\xFF" ? '' : $key;
        $out[$label] = $data['items'];
    }
    return $out;
}

/**
 * Nombre d’entrées possédées par région / génération (mêmes clés que poke_hub_collections_group_pool_by_generation()).
 *
 * @param array $pool  Pool complet (poke_hub_collections_get_pool)
 * @param array $items  pokemon_id => status
 * @return array [ libellé_générations => [ 'owned' => int, 'total' => int ], ... ]
 */
function poke_hub_collections_get_generation_progress(array $pool, array $items): array {
    $by_gen = poke_hub_collections_group_pool_by_generation($pool);
    $out    = [];
    foreach ($by_gen as $label => $gen_pool) {
        $total = count($gen_pool);
        $owned = 0;
        foreach ($gen_pool as $p) {
            if (poke_hub_collections_resolved_status_for_row($p, $items) === 'owned') {
                $owned++;
            }
        }
        $out[$label] = [
            'owned' => $owned,
            'total' => $total,
        ];
    }
    return $out;
}

/**
 * Remet à zéro la progression d’une collection (tous les statuts = absents côté base : lignes supprimées).
 *
 * @param int         $collection_id
 * @param int         $user_id
 * @param string|null $ip
 * @return array{ success: bool, message: string }
 */
function poke_hub_collections_reset_items(int $collection_id, int $user_id = 0, ?string $ip = null): array {
    if ($user_id > 0) {
        if (!poke_hub_collections_can_edit($collection_id, $user_id)) {
            return ['success' => false, 'message' => __('You cannot modify this collection.', 'poke-hub')];
        }
    } else {
        if ($ip === null || !poke_hub_collections_can_edit_anonymous($collection_id, $ip)) {
            return ['success' => false, 'message' => __('You cannot modify this collection.', 'poke-hub')];
        }
    }

    global $wpdb;

    $items_table = pokehub_get_table('collection_items');
    if (!$items_table) {
        return ['success' => false, 'message' => __('Technical error.', 'poke-hub')];
    }

    $r = $wpdb->delete($items_table, ['collection_id' => $collection_id], ['%d']);
    if ($r === false) {
        return ['success' => false, 'message' => __('Could not reset the collection.', 'poke-hub')];
    }

    return [
        'success' => true,
        'message' => __('Collection progress cleared.', 'poke-hub'),
    ];
}

/**
 * Retourne l’IP du client (prise en compte des proxies).
 *
 * @return string
 */
function poke_hub_collections_get_client_ip(): string {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $val = wp_unslash($_SERVER[$key]);
            if (strpos($val, ',') !== false) {
                $val = trim(explode(',', $val)[0]);
            }
            $val = preg_replace('/[^0-9a-f.:]/', '', $val);
            if ($val !== '') {
                return $val;
            }
        }
    }
    return '';
}

/**
 * Sanitize les options de collection (URLs, etc.).
 *
 * @param array $options Options brutes
 * @return array Options sanitized
 */
function poke_hub_collections_sanitize_options(array $options): array {
    if (isset($options['card_background_image_url']) && is_string($options['card_background_image_url'])) {
        $options['card_background_image_url'] = esc_url_raw(trim($options['card_background_image_url']));
    }
    if (array_key_exists('include_forms', $options)) {
        $options = poke_hub_collections_merge_legacy_include_forms_option($options);
    }
    $bool_keys = [
        'include_national_dex', 'include_gender', 'include_regional_forms', 'include_costumes', 'include_mega',
        'include_gigantamax', 'include_dynamax', 'include_backgrounds', 'include_special_attacks',
        'one_per_species', 'group_by_generation', 'generations_collapsed', 'public',
        'only_final_evolution', 'include_baby_pokemon',
        'include_legendary_pokemon', 'include_mythical_pokemon', 'include_ultra_beast_pokemon',
        'only_special_species_pool',
        'include_both_sexes_collector',
        'only_shiny',
    ];
    foreach ($bool_keys as $k) {
        if (array_key_exists($k, $options)) {
            $options[$k] = (bool) $options[$k];
        }
    }
    if (array_key_exists('pool_show_only', $options)) {
        $v = is_string($options['pool_show_only']) ? sanitize_key($options['pool_show_only']) : '';
        $options['pool_show_only'] = in_array($v, poke_hub_collections_pool_show_only_allowed(), true) ? $v : '';
    }
    $ps = isset($options['pool_show_only']) ? (string) $options['pool_show_only'] : '';
    if ($ps === '') {
        $ps = poke_hub_collections_normalize_pool_show_only($options);
    }
    $options['pool_show_only'] = $ps;
    $options['only_final_evolution']      = ($ps === 'final');
    $options['only_special_species_pool'] = ($ps === 'special_all');
    if (array_key_exists('display_mode', $options) && is_string($options['display_mode'])) {
        $options['display_mode'] = in_array($options['display_mode'], ['tiles', 'select', 'tiles_select'], true)
            ? $options['display_mode']
            : 'tiles';
    }

    return poke_hub_collections_derive_gender_symbol_option($options);
}

/**
 * Ajuste les options selon la catégorie stockée (ex. only_shiny réservé aux listes personnalisées).
 *
 * @param array<string, mixed> $options
 * @return array<string, mixed>
 */
function poke_hub_collections_options_align_with_category(array $options, string $category): array {
    $category = sanitize_key($category);
    if ($category !== 'custom') {
        $options['only_shiny'] = false;
    }
    if ($category === 'legendary_mythical_ultra') {
        $options['pool_show_only']            = '';
        $options['only_final_evolution']      = false;
        $options['only_special_species_pool'] = false;
    }

    return poke_hub_collections_derive_gender_symbol_option($options);
}

/**
 * Slugs de repli (ancienne logique) si `extra.is_baby` n’est pas renseigné. Filtre : poke_hub_collections_baby_pokemon_slugs
 *
 * @return string[]
 */
function poke_hub_collections_baby_pokemon_slugs(): array {
    $slugs = [
        'pichu', 'cleffa', 'igglybuff', 'togepi', 'tyrogue', 'smoochum', 'elekid', 'magby',
        'azurill', 'wynaut', 'budew', 'chingling', 'bonsly', 'mime-jr', 'happiny', 'mantyke', 'toxel',
    ];
    return array_unique(apply_filters('poke_hub_collections_baby_pokemon_slugs', $slugs));
}

/**
 * Ligne pool : champ `is_baby` alimenté dans {@see poke_hub_collections_get_pool()} avant retrait d’`extra`.
 * Sinon, repli sur {@see poke_hub_pokemon_is_baby_from_row()} si le JSON est encore présent.
 *
 * @param array $row Ligne pool (slug, is_baby, extra optionnel, …)
 */
function poke_hub_collections_row_is_baby_pokemon(array $row): bool {
    if (array_key_exists('is_baby', $row)) {
        return (bool) $row['is_baby'];
    }
    if (function_exists('poke_hub_pokemon_is_baby_from_row')) {
        return poke_hub_pokemon_is_baby_from_row($row);
    }
    $slug = strtolower((string) ($row['slug'] ?? ''));
    if ($slug === '') {
        return false;
    }
    if (strpos($slug, '-') !== false) {
        $slug = substr($slug, 0, (int) strpos($slug, '-'));
    }
    $babies = poke_hub_collections_baby_pokemon_slugs();

    return in_array($slug, $babies, true);
}

/**
 * Symbole ♂/♀ pour une ligne de variante « genre » en base (fv.category = gender), si elle apparaît encore au pool, ou ''.
 *
 * Le dimorphisme collection (`include_gender`) s’affiche via les lignes synthétiques mâle/femelle, pas via ces variantes.
 * Détails : le slug / libellé peuvent être en anglais (nidoran-male) ou en français (Mâle, Femelle) ;
 * « mâle » ne contient pas la chaîne « male » ; éviter uniquement {@see strtolower} sur de l’UTF-8.
 */
function poke_hub_collections_gender_display_for_row(array $row): string {
    if (strtolower((string) ($row['form_category'] ?? '')) !== 'gender') {
        return '';
    }
    $slug  = (string) ($row['form_slug'] ?? '');
    $label = (string) ($row['form_label'] ?? '');
    $raw   = $slug . ' ' . $label;
    $fs    = (function_exists('mb_strtolower') && function_exists('mb_strpos')) ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);

    $pos = function ($needle) use ($fs) {
        return function_exists('mb_strpos') ? mb_strpos($fs, $needle, 0, 'UTF-8') : strpos($fs, $needle);
    };

    if (false !== $pos('♀')) {
        return '♀';
    }
    if (false !== $pos('♂')) {
        return '♂';
    }
    if (false !== $pos('female') || false !== $pos('femelle') || false !== $pos('féminin') || false !== $pos('feminin') || false !== $pos('féminine') || false !== $pos('feminine')) {
        return '♀';
    }
    if (false !== $pos('mâle') || false !== $pos('masculin')) {
        return '♂';
    }
    if (false !== $pos('male') && false === $pos('female')) {
        return '♂';
    }

    return '';
}

/**
 * Plage d’ID réservée : lignes mâle/femelle « collectionneur » (dérivées, pas en BDD).
 * Évite le chevauchement Gigamax (2 100 M…) et les vrais id auto-inc.
 */
const POKE_HUB_COLLECTIONS_SEX_SYNTH_BASE = 2200000000;

/**
 * @return int ID d’enregistrement (collection_items) pour une ligne mâle/femelle synthétique.
 */
function poke_hub_collections_sex_synthetic_pokemon_id(int $base_pokemon_id, string $sex): int {
    $base_pokemon_id = (int) $base_pokemon_id;
    if ($base_pokemon_id <= 0) {
        return 0;
    }
    $sex = strtolower($sex) === 'female' ? 1 : 0;

    return POKE_HUB_COLLECTIONS_SEX_SYNTH_BASE + $base_pokemon_id * 2 + $sex;
}

/**
 * @return array{base: int, sex: 'male'|'female'}|null
 */
function poke_hub_collections_synthetic_sex_decode(int $pokemon_id): ?array {
    $pokemon_id = (int) $pokemon_id;
    $d          = (int) $pokemon_id - POKE_HUB_COLLECTIONS_SEX_SYNTH_BASE;
    if ($d < 0) {
        return null;
    }
    if (0 === ($d % 2)) {
        $base = (int) ($d / 2);
        $sex  = 'male';
    } else {
        $base = (int) (($d - 1) / 2);
        $sex  = 'female';
    }
    if ($base <= 0) {
        return null;
    }
    if (poke_hub_collections_sex_synthetic_pokemon_id($base, $sex) !== $pokemon_id) {
        return null;
    }

    return ['base' => $base, 'sex' => $sex];
}

function poke_hub_collections_pokemon_id_is_synthetic_sex(int $pokemon_id): bool {
    return null !== poke_hub_collections_synthetic_sex_decode($pokemon_id);
}

/**
 * Remplace chaque fiche de base par deux lignes mâle + femelle (id synthétique + ♂/♀) si :
 * - « mâle et femelle (GO) » : les deux genres sont disponibles dans le profil ; ou
 * - « dimorphisme » : has_gender_dimorphism est coché en fiche Pokédex et mâle + femelle sont disponibles.
 *
 * Ne touche pas aux fiches variante fv.category = gender (hors pool), ni aux Gigamax synthétiques.
 */
function poke_hub_collections_apply_sex_collector_pool(array $rows, array $opts): array {
    if ($rows === [] || (empty($opts['include_both_sexes_collector']) && empty($opts['include_gender']))) {
        return $rows;
    }
    if (!function_exists('poke_hub_pokemon_get_gender_profile')) {
        return $rows;
    }
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            $out[] = $row;
            continue;
        }
        if (poke_hub_collections_gigantamax_is_synthetic_pokemon_id((int) ($row['id'] ?? 0))) {
            $out[] = $row;
            continue;
        }
        if ( function_exists( 'poke_hub_collections_dynamax_is_synthetic_pokemon_id' )
            && poke_hub_collections_dynamax_is_synthetic_pokemon_id( (int) ( $row['id'] ?? 0 ) ) ) {
            $out[] = $row;
            continue;
        }
        if (!empty($row['synthetic_go_background'])) {
            $out[] = $row;
            continue;
        }
        if (strtolower((string) ($row['form_category'] ?? '')) === 'gender') {
            $out[] = $row;
            continue;
        }
        if (!empty($row['synthetic_gigantamax'])) {
            $out[] = $row;
            continue;
        }
        if ( ! empty( $row['synthetic_dynamax'] ) ) {
            $out[] = $row;
            continue;
        }
        $base_id = (int) ($row['id'] ?? 0);
        if ($base_id <= 0) {
            $out[] = $row;
            continue;
        }
        $profile = poke_hub_pokemon_get_gender_profile($base_id);
        $genders = is_array($profile['available_genders'] ?? null) ? $profile['available_genders'] : [];
        if (!in_array('male', $genders, true) || !in_array('female', $genders, true)) {
            $out[] = $row;
            continue;
        }
        $split_both = !empty($opts['include_both_sexes_collector']);
        // Même critère que {@see pokehub_pokemon_is_dimorphic_for_select()} (évite un 2ᵉ appel : profil déjà chargé).
        $split_dimorphism = !empty($opts['include_gender']) && !empty($profile['has_gender_dimorphism']);
        if (!$split_both && !$split_dimorphism) {
            $out[] = $row;
            continue;
        }
        $m = poke_hub_collections_sex_synthetic_row_from_base($row, 'male', $base_id);
        $f = poke_hub_collections_sex_synthetic_row_from_base($row, 'female', $base_id);
        if ($m !== null) {
            $out[] = $m;
        }
        if ($f !== null) {
            $out[] = $f;
        }
    }

    return $out;
}

/**
 * @return array<string, mixed>|null
 */
function poke_hub_collections_sex_synthetic_row_from_base(array $row, string $sex, int $base_id): ?array {
    $base_id = (int) $base_id;
    if ($base_id <= 0) {
        return null;
    }
    $id = poke_hub_collections_sex_synthetic_pokemon_id($base_id, $sex);
    if ($id <= 0) {
        return null;
    }
    $t              = (array) $row;
    $t['id']        = $id;
    $t['synthetic_sex'] = (strtolower($sex) === 'female') ? 'female' : 'male';
    $t['synthetic_sex_base_id'] = $base_id;
    $t['synthetic_sex_collector'] = true;
    $t['form_category']   = (string) ($t['form_category'] ?? 'normal');
    $t['gender_display']  = (strtolower($sex) === 'female') ? '♀' : '♂';
    if (function_exists('pokehub_pokemon_is_baby_from_row')) {
        $t['is_baby'] = pokehub_pokemon_is_baby_from_row($row);
    } else {
        $t['is_baby'] = false;
    }
    if (array_key_exists('species_special_group', $row)) {
        $t['species_special_group'] = (string) $row['species_special_group'];
    } elseif (function_exists('poke_hub_pokemon_species_special_group_from_row')) {
        $t['species_special_group'] = poke_hub_pokemon_species_special_group_from_row($row);
    } else {
        $t['species_special_group'] = '';
    }
    if (function_exists('apply_filters')) {
        $t = (array) apply_filters('poke_hub_collections_synthetic_sex_row', $t, $row, $sex);
    }

    return $t;
}

/**
 * Statut d’affichage pour une ligne de pool (repli : entrée mâle/fem. → statut de la fiche de base).
 *
 * @param array<string, mixed> $p
 * @param array<int, string>   $items pokemon_id (DB ou synth) => statut
 */
function poke_hub_collections_resolved_status_for_row(array $p, array $items): string {
    $id = (int) ($p['id'] ?? 0);
    if (isset($items[$id])) {
        $st = (string) $items[$id];
    } else {
        $bid = (int) ($p['synthetic_sex_base_id'] ?? 0);
        if (!empty($p['synthetic_sex_collector']) && $bid > 0 && isset($items[$bid])) {
            $st = (string) $items[$bid];
        } else {
            $st = 'missing';
        }
    }
    if (in_array($st, ['owned', 'for_trade', 'missing'], true)) {
        return $st;
    }

    return 'missing';
}

/**
 * @param array<int, string> $items
 * @param list<array<string, mixed>> $pool
 * @return array<int, string> id (pool) => statut
 */
function poke_hub_collections_resolved_items_map(array $items, array $pool): array {
    $out = [];
    foreach ($pool as $p) {
        if (!is_array($p)) {
            continue;
        }
        $id = (int) ($p['id'] ?? 0);
        if ($id > 0) {
            $out[$id] = poke_hub_collections_resolved_status_for_row($p, $items);
        }
    }

    return $out;
}

/**
 * Cible (objet Pokémon + args) pour les helpers d’URL/sprite, selon la ligne de pool.
 *
 * @return array{0: object, 1: array<string, mixed>}
 */
function poke_hub_collections_pool_row_to_pokemon_for_image_target(array $p, bool $is_shiny): array {
    $row = (array) $p;
    $arg = [ 'shiny' => $is_shiny ];
    if (!empty($p['synthetic_go_background']) && !empty($p['synthetic_go_background_link_pokemon_id'])) {
        $row['id'] = (int) $p['synthetic_go_background_link_pokemon_id'];
    } elseif (!empty($p['synthetic_sex_collector']) && !empty($p['synthetic_sex_base_id'])) {
        $row['id']     = (int) $p['synthetic_sex_base_id'];
        $arg['gender'] = (strtolower((string) ($p['synthetic_sex'] ?? '')) === 'female') ? 'female' : 'male';
    } elseif (function_exists('poke_hub_collections_gigantamax_is_synthetic_pokemon_id')
        && !empty($p['id'])
        && poke_hub_collections_gigantamax_is_synthetic_pokemon_id((int) $p['id'])
        && !empty($p['gigantamax_base_pokemon_id'])) {
        $row['id'] = (int) $p['gigantamax_base_pokemon_id'];
    } elseif ( function_exists( 'poke_hub_collections_dynamax_is_synthetic_pokemon_id' )
        && ! empty( $p['id'] )
        && poke_hub_collections_dynamax_is_synthetic_pokemon_id( (int) $p['id'] )
        && ! empty( $p['dynamax_base_pokemon_id'] ) ) {
        $row['id'] = (int) $p['dynamax_base_pokemon_id'];
    }

    return [ (object) $row, $arg ];
}

/**
 * URLs primaire + repli (mêmes règles que {@see poke_hub_collections_get_image_url_for_pool_row}).
 *
 * @return array{primary: string, fallback: string}
 */
function poke_hub_collections_get_image_sources_for_pool_row(array $p, bool $is_shiny): array {
    if (!function_exists('poke_hub_pokemon_get_image_sources')) {
        return [ 'primary' => '', 'fallback' => '' ];
    }
    $target        = poke_hub_collections_pool_row_to_pokemon_for_image_target($p, $is_shiny);
    $sources       = poke_hub_pokemon_get_image_sources($target[0], $target[1]);
    $sources['primary']  = trim((string) ($sources['primary'] ?? ''));
    $sources['fallback'] = trim((string) ($sources['fallback'] ?? ''));

    return $sources;
}

/**
 * URL d’icône pour une ligne de pool (id synth. sexe → id de base + genre).
 */
function poke_hub_collections_get_image_url_for_pool_row(array $p, bool $is_shiny): string {
    if (!function_exists('poke_hub_pokemon_get_image_url')) {
        return '';
    }
    $target = poke_hub_collections_pool_row_to_pokemon_for_image_target($p, $is_shiny);

    return (string) poke_hub_pokemon_get_image_url($target[0], $target[1]);
}

/**
 * Crée une collection pour un utilisateur connecté.
 *
 * @param int   $user_id User ID
 * @param array $data    name, category, options, is_public
 * @return array { success, message, collection_id?, slug?, share_token? }
 */
function poke_hub_collections_create(int $user_id, array $data): array {
    global $wpdb;

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table || $user_id <= 0) {
        return ['success' => false, 'message' => __('Invalid request.', 'poke-hub')];
    }

    $name     = isset($data['name']) ? sanitize_text_field($data['name']) : '';
    $category = isset($data['category']) ? sanitize_key($data['category']) : 'custom';
    $slug     = sanitize_title($name ?: 'collection-' . $user_id . '-' . time());
    $options  = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];
    $is_public = !empty($data['is_public']);

    $categories = array_keys(poke_hub_collections_get_categories());
    if (!in_array($category, $categories, true)) {
        $category = 'custom';
    }

    $options = array_merge(poke_hub_collections_default_options(), $options);
    $options = poke_hub_collections_sanitize_options($options);
    $options = poke_hub_collections_options_align_with_category($options, $category);
    $options_json = wp_json_encode($options);

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$collections_table} WHERE user_id = %d AND slug = %s",
        $user_id,
        $slug
    ));

    if ($existing) {
        $slug = $slug . '-' . time();
    }

    $share_token = poke_hub_collections_generate_share_token();

    $r = $wpdb->insert(
        $collections_table,
        [
            'user_id'     => $user_id,
            'name'        => $name ?: __('My collection', 'poke-hub'),
            'slug'        => $slug,
            'share_token' => $share_token,
            'category'    => $category,
            'options'     => $options_json,
            'is_public'   => $is_public ? 1 : 0,
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s', '%d']
    );

    if ($r === false) {
        return ['success' => false, 'message' => __('Could not create collection.', 'poke-hub')];
    }

    $collection_id = (int) $wpdb->insert_id;
    return [
        'success'        => true,
        'message'       => __('Collection created.', 'poke-hub'),
        'collection_id'  => $collection_id,
        'slug'           => $slug,
        'share_token'    => $share_token,
    ];
}

/**
 * Crée une collection anonyme (sans compte), liée à l’IP pour rattachement ultérieur.
 *
 * @param array  $data name, category, options
 * @param string $ip   IP du client
 * @return array { success, message, collection_id?, share_token? }
 */
function poke_hub_collections_create_anonymous(array $data, string $ip): array {
    global $wpdb;

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return ['success' => false, 'message' => __('Technical error.', 'poke-hub')];
    }

    $ip = preg_replace('/[^0-9a-f.:]/', '', $ip);
    if ($ip === '') {
        return ['success' => false, 'message' => __('Unable to identify your connection.', 'poke-hub')];
    }

    $name     = isset($data['name']) ? sanitize_text_field($data['name']) : '';
    $category = isset($data['category']) ? sanitize_key($data['category']) : 'custom';
    $options  = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];

    $categories = array_keys(poke_hub_collections_get_categories());
    if (!in_array($category, $categories, true)) {
        $category = 'custom';
    }

    $options    = array_merge(poke_hub_collections_default_options(), $options);
    $options    = poke_hub_collections_sanitize_options($options);
    $options    = poke_hub_collections_options_align_with_category($options, $category);
    $options_json = wp_json_encode($options);

    $share_token = poke_hub_collections_generate_share_token();
    $slug        = 'anon-' . time() . '-' . substr($share_token, 0, 6);

    $r = $wpdb->insert(
        $collections_table,
        [
            'user_id'      => 0,
            'name'         => $name ?: __('My collection', 'poke-hub'),
            'slug'         => $slug,
            'share_token'  => $share_token,
            'anonymous_ip' => $ip,
            'category'     => $category,
            'options'      => $options_json,
            'is_public'    => 0,
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
    );

    if ($r === false) {
        return ['success' => false, 'message' => __('Could not create collection.', 'poke-hub')];
    }

    $collection_id = (int) $wpdb->insert_id;
    return [
        'success'        => true,
        'message'        => __('Collection created.', 'poke-hub'),
        'collection_id'   => $collection_id,
        'share_token'    => $share_token,
    ];
}

/**
 * Met à jour une collection (nom, options, is_public). Propriétaire compte ou collection anonyme (même IP).
 *
 * @param int         $collection_id
 * @param int         $user_id
 * @param array       $data  name, options, is_public (catégorie non modifiée)
 * @param string|null $ip    IP client pour collections anonymes (ignoré si le propriétaire est un user connecté)
 * @return array{ success: bool, message: string, forbidden?: bool }
 */
function poke_hub_collections_update(int $collection_id, int $user_id, array $data, ?string $ip = null): array {
    global $wpdb;

    $ipNorm = $ip !== null && $ip !== '' ? preg_replace('/[^0-9a-f.:]/', '', (string) $ip) : '';
    if (!poke_hub_collections_can_edit($collection_id, $user_id, $ipNorm)) {
        return [
            'success'   => false,
            'forbidden' => true,
            'message'   => __('You cannot edit this collection.', 'poke-hub'),
        ];
    }

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return ['success' => false, 'message' => __('Technical error.', 'poke-hub')];
    }

    $current_category = (string) $wpdb->get_var($wpdb->prepare(
        "SELECT category FROM {$collections_table} WHERE id = %d",
        $collection_id
    ));
    if ($current_category === '') {
        $current_category = 'custom';
    }

    $updates = [];
    $formats = [];

    if (array_key_exists('name', $data)) {
        $name = sanitize_text_field($data['name']);
        if ($name !== '') {
            $updates['name'] = $name;
            $formats[] = '%s';
        }
    }

    if (isset($data['options']) && is_array($data['options'])) {
        $options = array_merge(poke_hub_collections_default_options(), $data['options']);
        $options = poke_hub_collections_sanitize_options($options);
        $options = poke_hub_collections_options_align_with_category($options, $current_category);
        $updates['options'] = wp_json_encode($options);
        $formats[] = '%s';
    }

    if (array_key_exists('is_public', $data)) {
        $updates['is_public'] = !empty($data['is_public']) ? 1 : 0;
        $formats[] = '%d';
    }

    if (empty($updates)) {
        return ['success' => true, 'message' => __('No changes.', 'poke-hub')];
    }

    $r = $wpdb->update(
        $collections_table,
        $updates,
        ['id' => $collection_id],
        $formats,
        ['%d']
    );

    if ($r === false) {
        return ['success' => false, 'message' => __('Could not save.', 'poke-hub')];
    }

    return ['success' => true, 'message' => __('Settings saved.', 'poke-hub')];
}

/**
 * Récupère les collections d'un utilisateur.
 *
 * @param int $user_id
 * @return array
 */
function poke_hub_collections_get_by_user(int $user_id): array {
    global $wpdb;

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table || $user_id <= 0) {
        return [];
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, user_id, name, slug, share_token, anonymous_ip, category, options, is_public, created_at, updated_at
         FROM {$collections_table} WHERE user_id = %d ORDER BY updated_at DESC",
        $user_id
    ), ARRAY_A);

    if (!is_array($rows)) {
        return [];
    }

    foreach ($rows as &$row) {
        if (!empty($row['options'])) {
            $row['options'] = json_decode($row['options'], true) ?: [];
        } else {
            $row['options'] = poke_hub_collections_default_options();
        }
    }
    unset($row);

    return $rows;
}

/**
 * Récupère une collection par ID ou par slug+user_id.
 *
 * @param int         $collection_id ID (prioritaire)
 * @param string|null $slug          Slug (si pas d'ID)
 * @param int|null    $user_id       User (requis si slug)
 * @return array|null
 */
function poke_hub_collections_get_one(int $collection_id = 0, ?string $slug = null, ?int $user_id = null): ?array {
    global $wpdb;

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return null;
    }

    if ($collection_id > 0) {
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, name, slug, share_token, anonymous_ip, category, options, is_public, created_at, updated_at
             FROM {$collections_table} WHERE id = %d",
            $collection_id
        ), ARRAY_A);
    } elseif ($slug !== null && $slug !== '' && $user_id > 0) {
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, name, slug, share_token, anonymous_ip, category, options, is_public, created_at, updated_at
             FROM {$collections_table} WHERE user_id = %d AND slug = %s",
            $user_id,
            $slug
        ), ARRAY_A);
    } else {
        return null;
    }

    if (!is_array($row)) {
        return null;
    }

    $row['options'] = !empty($row['options']) ? (json_decode($row['options'], true) ?: []) : poke_hub_collections_default_options();
    return $row;
}

/**
 * Récupère une collection par son jeton de partage (unique, public ou propriétaire).
 *
 * @param string $token
 * @return array|null
 */
function poke_hub_collections_get_by_share_token(string $token): ?array {
    global $wpdb;

    $token = preg_replace('/[^a-zA-Z0-9]/', '', $token);
    if ($token === '') {
        return null;
    }

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return null;
    }

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, name, slug, share_token, anonymous_ip, category, options, is_public, created_at, updated_at
         FROM {$collections_table} WHERE share_token = %s",
        $token
    ), ARRAY_A);

    if (!is_array($row)) {
        return null;
    }

    $row['options'] = !empty($row['options']) ? (json_decode($row['options'], true) ?: []) : poke_hub_collections_default_options();
    return $row;
}

/**
 * Récupère une collection publique par slug (sans user_id).
 *
 * @param string $slug
 * @return array|null
 */
function poke_hub_collections_get_public_by_slug(string $slug): ?array {
    global $wpdb;

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return null;
    }

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, name, slug, share_token, anonymous_ip, category, options, is_public, created_at, updated_at
         FROM {$collections_table} WHERE slug = %s AND is_public = 1",
        $slug
    ), ARRAY_A);

    if (!is_array($row)) {
        return null;
    }

    $row['options'] = !empty($row['options']) ? (json_decode($row['options'], true) ?: []) : poke_hub_collections_default_options();
    return $row;
}

/**
 * Vérifie que l’utilisateur peut modifier la collection.
 * Compte connecté : propriétaire uniquement. Hors ligne : $user_id = 0 et $ip = IP client alignée sur anonymous_ip.
 *
 * @param int    $collection_id
 * @param int    $user_id       >0 = WP user, 0 = tenter l’accès collection anonyme (IP requise)
 * @param string $ip            IP normalisée (même règle que {@see poke_hub_collections_get_client_ip()})
 */
function poke_hub_collections_can_edit(int $collection_id, int $user_id, string $ip = ''): bool {
    $col = poke_hub_collections_get_one($collection_id);
    if (!$col) {
        return false;
    }
    if ($user_id > 0) {
        return (int) $col['user_id'] === $user_id;
    }
    if ($ip === '') {
        return false;
    }
    $ip = preg_replace('/[^0-9a-f.:]/', '', (string) $ip);

    return $ip !== '' && poke_hub_collections_can_edit_anonymous($collection_id, $ip);
}

/**
 * Vérifie qu’une collection anonyme peut être modifiée depuis cette IP.
 */
function poke_hub_collections_can_edit_anonymous(int $collection_id, string $ip): bool {
    $col = poke_hub_collections_get_one($collection_id);
    return $col && (int) $col['user_id'] === 0 && !empty($col['anonymous_ip']) && $col['anonymous_ip'] === $ip;
}

/**
 * Récupère les collections anonymes créées depuis cette IP (pour proposition de rattachement au compte).
 *
 * @param string $ip
 * @return array
 */
function poke_hub_collections_get_anonymous_by_ip(string $ip): array {
    global $wpdb;

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table || $ip === '') {
        return [];
    }

    $ip = preg_replace('/[^0-9a-f.:]/', '', $ip);
    if ($ip === '') {
        return [];
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, user_id, name, slug, share_token, anonymous_ip, category, options, is_public, created_at, updated_at
         FROM {$collections_table} WHERE user_id = 0 AND anonymous_ip = %s ORDER BY updated_at DESC",
        $ip
    ), ARRAY_A);

    if (!is_array($rows)) {
        return [];
    }

    foreach ($rows as &$row) {
        $row['options'] = !empty($row['options']) ? (json_decode($row['options'], true) ?: []) : poke_hub_collections_default_options();
    }
    unset($row);

    return $rows;
}

/**
 * Rattache une collection anonyme au compte de l’utilisateur (même IP requise).
 *
 * @param int    $collection_id
 * @param int    $user_id
 * @param string $ip IP du client
 * @return array { success, message }
 */
function poke_hub_collections_claim(int $collection_id, int $user_id, string $ip): array {
    global $wpdb;

    $col = poke_hub_collections_get_one($collection_id);
    if (!$col) {
        return ['success' => false, 'message' => __('Collection not found.', 'poke-hub')];
    }
    if ((int) $col['user_id'] !== 0) {
        return ['success' => false, 'message' => __('This collection is already linked to an account.', 'poke-hub')];
    }
    $ip = preg_replace('/[^0-9a-f.:]/', '', $ip);
    if ($ip === '' || empty($col['anonymous_ip']) || $col['anonymous_ip'] !== $ip) {
        return ['success' => false, 'message' => __('This collection was not created from this connection.', 'poke-hub')];
    }

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return ['success' => false, 'message' => __('Technical error.', 'poke-hub')];
    }

    $slug = sanitize_title($col['name'] ?: 'collection-' . $user_id . '-' . time());
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$collections_table} WHERE user_id = %d AND slug = %s",
        $user_id,
        $slug
    ));
    if ($existing) {
        $slug = $slug . '-' . time();
    }

    $r = $wpdb->update(
        $collections_table,
        ['user_id' => $user_id, 'slug' => $slug, 'anonymous_ip' => null],
        ['id' => $collection_id],
        ['%d', '%s', '%s'],
        ['%d']
    );

    if ($r === false) {
        return ['success' => false, 'message' => __('Could not attach.', 'poke-hub')];
    }

    return ['success' => true, 'message' => __('Collection linked to your account.', 'poke-hub')];
}

/**
 * Supprime une collection et ses items. Propriétaire ou collection anonyme (même IP).
 *
 * @param int         $collection_id
 * @param int         $user_id 0 pour anonyme
 * @param string|null $ip      requis si user_id = 0
 * @return array { success, message }
 */
function poke_hub_collections_delete(int $collection_id, int $user_id = 0, ?string $ip = null): array {
    global $wpdb;

    if ($user_id > 0) {
        if (!poke_hub_collections_can_edit($collection_id, $user_id)) {
            return ['success' => false, 'message' => __('You cannot delete this collection.', 'poke-hub')];
        }
    } else {
        if ($ip === null || !poke_hub_collections_can_edit_anonymous($collection_id, $ip)) {
            return ['success' => false, 'message' => __('You cannot delete this collection.', 'poke-hub')];
        }
    }

    $collections_table = pokehub_get_table('collections');
    $items_table       = pokehub_get_table('collection_items');
    if (!$collections_table) {
        return ['success' => false, 'message' => __('Technical error.', 'poke-hub')];
    }

    if ($items_table) {
        $wpdb->delete($items_table, ['collection_id' => $collection_id], ['%d']);
    }
    $r = $wpdb->delete($collections_table, ['id' => $collection_id], ['%d']);

    if ($r === false) {
        return ['success' => false, 'message' => __('Could not delete.', 'poke-hub')];
    }

    return ['success' => true, 'message' => __('Collection deleted.', 'poke-hub')];
}

/**
 * Récupère les items (pokemon_id => status) d'une collection.
 *
 * @param int $collection_id
 * @return array [ pokemon_id => 'owned'|'for_trade'|'missing' ]
 */
function poke_hub_collections_get_items(int $collection_id): array {
    global $wpdb;

    $items_table = pokehub_get_table('collection_items');
    if (!$items_table || $collection_id <= 0) {
        return [];
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT pokemon_id, status FROM {$items_table} WHERE collection_id = %d",
        $collection_id
    ), ARRAY_A);

    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $out[(int) $row['pokemon_id']] = in_array($row['status'], ['owned', 'for_trade', 'missing'], true) ? $row['status'] : 'missing';
    }
    return $out;
}

/**
 * Met à jour le statut d'un Pokémon dans une collection.
 *
 * @param int    $collection_id
 * @param int    $pokemon_id
 * @param string $status   owned|for_trade|missing
 * @param int    $user_id  Vérification propriétaire (0 si collection anonyme)
 * @param string|null $ip  IP du client (requis si user_id = 0 pour collection anonyme)
 * @return bool
 */
function poke_hub_collections_set_item(int $collection_id, int $pokemon_id, string $status, int $user_id = 0, ?string $ip = null): bool {
    global $wpdb;

    if ($user_id > 0) {
        if (!poke_hub_collections_can_edit($collection_id, $user_id)) {
            return false;
        }
    } else {
        if ($ip === null || !poke_hub_collections_can_edit_anonymous($collection_id, $ip)) {
            return false;
        }
    }

    $status = in_array($status, ['owned', 'for_trade', 'missing'], true) ? $status : 'missing';
    $items_table = pokehub_get_table('collection_items');
    if (!$items_table) {
        return false;
    }

    $wpdb->replace(
        $items_table,
        [
            'collection_id' => $collection_id,
            'pokemon_id'     => $pokemon_id,
            'status'         => $status,
        ],
        ['%d', '%d', '%s']
    );

    return true;
}

/**
 * Met à jour plusieurs items en une fois.
 *
 * @param int   $collection_id
 * @param array $items  [ pokemon_id => status, ... ]
 * @param int   $user_id 0 pour anonyme
 * @param string|null $ip requis si user_id = 0
 * @return bool
 */
function poke_hub_collections_set_items(int $collection_id, array $items, int $user_id = 0, ?string $ip = null): bool {
    if ($user_id > 0) {
        if (!poke_hub_collections_can_edit($collection_id, $user_id)) {
            return false;
        }
    } else {
        if ($ip === null || !poke_hub_collections_can_edit_anonymous($collection_id, $ip)) {
            return false;
        }
    }

    foreach ($items as $pokemon_id => $status) {
        poke_hub_collections_set_item($collection_id, (int) $pokemon_id, (string) $status, $user_id, $ip);
    }
    return true;
}
