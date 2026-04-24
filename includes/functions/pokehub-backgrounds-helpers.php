<?php
// includes/functions/pokehub-backgrounds-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/** Types de fonds Pokémon GO : lieu vs spécial */
const POKE_HUB_BACKGROUND_TYPE_LOCATION = 'location';
const POKE_HUB_BACKGROUND_TYPE_SPECIAL = 'special';

/** Liaison fond ↔ Pokémon : forme d’affichage (collections / tuiles). */
const POKE_HUB_BG_LINK_BASE        = 'base';
const POKE_HUB_BG_LINK_SHADOW      = 'shadow';
const POKE_HUB_BG_LINK_DYNAMAX     = 'dynamax';
const POKE_HUB_BG_LINK_GIGANTAMAX  = 'gigantamax';

/**
 * Retourne les types de fonds disponibles (fonds de lieux / fonds spéciaux).
 *
 * @return array [ 'slug' => 'Label traduit', ... ]
 */
function poke_hub_get_background_types(): array {
    return [
        POKE_HUB_BACKGROUND_TYPE_LOCATION => __('Location background', 'poke-hub'),
        POKE_HUB_BACKGROUND_TYPE_SPECIAL  => __('Special background', 'poke-hub'),
    ];
}

/**
 * Retourne le libellé d'un type de fond.
 *
 * @param string $type 'location' ou 'special'
 * @return string
 */
function poke_hub_get_background_type_label(string $type): string {
    $types = poke_hub_get_background_types();
    return $types[$type] ?? $type;
}

/**
 * Liste des Pokémon éligibles pour un type de lien « fond » (admin : multiselect filtré).
 *
 * @param string $link_kind POKE_HUB_BG_LINK_* (shadow, dynamax, gigantamax)
 * @return array<int, object{ id: int, dex_number: int, name_fr: string, name_en: string }>
 */
function poke_hub_get_pokemon_list_for_background_link_kind( string $link_kind ): array {
    global $wpdb;
    $link_kind = sanitize_key( $link_kind );
    $pokemon_table = pokehub_get_table( 'pokemon' );
    $fv_table      = pokehub_get_table( 'pokemon_form_variants' );
    if ( ! $pokemon_table || ! $fv_table ) {
        return [];
    }
    $no_switch = '(fv.id IS NULL OR LOWER(TRIM(COALESCE(fv.category, \'\'))) != \'switch_battle\')';
    if ( $link_kind === POKE_HUB_BG_LINK_SHADOW ) {
        // Uniquement les fiches avec une date de sortie « shadow » en extra (évite has_shadow=1 sur tout le Pokédex).
        $sql = "SELECT DISTINCT p.id, p.dex_number, p.name_fr, p.name_en
            FROM {$pokemon_table} p
            LEFT JOIN {$fv_table} fv ON p.form_variant_id = fv.id
            WHERE {$no_switch}
            AND p.extra IS NOT NULL AND p.extra != ''
            AND TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(p.extra, '$.release.shadow')), '')) != ''
            ORDER BY p.dex_number ASC, p.name_fr ASC, p.name_en ASC";
    } elseif ( $link_kind === POKE_HUB_BG_LINK_DYNAMAX ) {
        $sql = "SELECT DISTINCT p.id, p.dex_number, p.name_fr, p.name_en
            FROM {$pokemon_table} p
            LEFT JOIN {$fv_table} fv ON p.form_variant_id = fv.id
            WHERE {$no_switch}
            AND (
                LOWER(TRIM(COALESCE(fv.category, ''))) = 'dynamax'
                OR (fv.form_slug IS NOT NULL AND LOWER(fv.form_slug) LIKE '%dynamax%')
                OR (
                    p.extra IS NOT NULL AND p.extra != ''
                    AND TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(p.extra, '$.release.dynamax')), '')) != ''
                )
            )
            ORDER BY p.dex_number ASC, p.name_fr ASC, p.name_en ASC";
    } elseif ( $link_kind === POKE_HUB_BG_LINK_GIGANTAMAX ) {
        $sql = "SELECT DISTINCT p.id, p.dex_number, p.name_fr, p.name_en
            FROM {$pokemon_table} p
            LEFT JOIN {$fv_table} fv ON p.form_variant_id = fv.id
            WHERE {$no_switch}
            AND (
                LOWER(TRIM(COALESCE(fv.category, ''))) = 'gigantamax'
                OR (fv.form_slug IS NOT NULL AND LOWER(fv.form_slug) LIKE '%gigantamax%')
                OR (
                    p.extra IS NOT NULL AND p.extra != ''
                    AND TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(p.extra, '$.release.gigantamax')), '')) != ''
                )
            )
            ORDER BY p.dex_number ASC, p.name_fr ASC, p.name_en ASC";
    } else {
        return [];
    }
    $rows = $wpdb->get_results( $sql );
    if ( ! is_array( $rows ) || $rows === [] ) {
        return [];
    }

    return $rows;
}

/**
 * Ajoute au résultat du filtre les fiches déjà enregistrées pour ce fond mais absentes du SQL (sinon l’ID n’apparaît pas dans le select, n’est pas posté, et le lien est effacé).
 *
 * @param array<int, object> $filter_rows Résultat de {@see poke_hub_get_pokemon_list_for_background_link_kind()}
 * @param int[]              $saved_ids   IDs déjà liés (ex. current_dynamax_ids)
 * @return array<int, object>
 */
function poke_hub_merge_pokemon_filter_rows_with_saved_ids( array $filter_rows, array $saved_ids ): array {
    global $wpdb;
    $pokemon_table = pokehub_get_table( 'pokemon' );
    if ( ! $pokemon_table || $saved_ids === [] ) {
        return $filter_rows;
    }
    $have = [];
    foreach ( $filter_rows as $row ) {
        if ( isset( $row->id ) ) {
            $have[ (int) $row->id ] = true;
        }
    }
    $missing = [];
    foreach ( $saved_ids as $id ) {
        $id = (int) $id;
        if ( $id > 0 && empty( $have[ $id ] ) ) {
            $missing[] = $id;
        }
    }
    $missing = array_values( array_unique( $missing ) );
    if ( $missing === [] ) {
        return $filter_rows;
    }
    $placeholders = implode( ',', array_fill( 0, count( $missing ), '%d' ) );
    $sql          = "SELECT id, dex_number, name_fr, name_en FROM {$pokemon_table} WHERE id IN ({$placeholders})";
    $extra        = $wpdb->get_results( $wpdb->prepare( $sql, $missing ) );
    if ( ! is_array( $extra ) || $extra === [] ) {
        return $filter_rows;
    }
    $map = [];
    foreach ( $filter_rows as $row ) {
        if ( isset( $row->id ) ) {
            $map[ (int) $row->id ] = $row;
        }
    }
    foreach ( $extra as $row ) {
        $i = (int) $row->id;
        if ( $i > 0 && ! isset( $map[ $i ] ) ) {
            $map[ $i ] = $row;
        }
    }
    $out = array_values( $map );
    usort(
        $out,
        static function ( $a, $b ): int {
            $da = isset( $a->dex_number ) ? (int) $a->dex_number : 0;
            $db = isset( $b->dex_number ) ? (int) $b->dex_number : 0;
            if ( $da !== $db ) {
                return $da <=> $db;
            }
            $na = (string) ( $a->name_fr ?? $a->name_en ?? '' );
            $nb = (string) ( $b->name_fr ?? $b->name_en ?? '' );

            return strcmp( $na, $nb );
        }
    );

    return $out;
}

/**
 * S’assure que la contrainte d’unicité permet (même background_id, même pokemon_id) pour plusieurs link_kind
 * (base, shadow, dynamax, gigantamax). Remplace l’ancien index UNIQUE(background_id, pokemon_id) si besoin.
 */
function poke_hub_ensure_background_pokemon_link_unique_index(): void {
    global $wpdb;
    $table = pokehub_get_table( 'pokemon_background_pokemon_links' );
    if ( ! $table || ! $wpdb->dbname ) {
        return;
    }
    if ( function_exists( 'pokehub_table_exists' ) && ! pokehub_table_exists( $table ) ) {
        return;
    }
    $link_col = $wpdb->get_var( $wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'link_kind' LIMIT 1",
        $wpdb->dbname,
        $table
    ) );
    if ( empty( $link_col ) && function_exists( 'pokehub_install_tables_for_modules' ) ) {
        pokehub_install_tables_for_modules( [ 'pokemon' ], [ 'skip_allow_filter' => true, 'try_require_db_class' => true ] );
        $link_col = $wpdb->get_var( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'link_kind' LIMIT 1",
            $wpdb->dbname,
            $table
        ) );
    }
    if ( empty( $link_col ) ) {
        return;
    }
    // Tout index UNIQUE ne portant que sur (background_id, pokemon_id) — quel que soit le nom — empêche les lignes par mode (shadow, dynamax…). On le supprime.
    $index_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols, MAX(NON_UNIQUE) AS max_non
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME != 'PRIMARY'
             GROUP BY INDEX_NAME",
            $wpdb->dbname,
            $table
        )
    );
    foreach ( (array) $index_rows as $r ) {
        $max_n = (int) ( is_object( $r ) ? ( $r->max_non ?? 0 ) : 0 );
        if ( $max_n !== 0 ) {
            continue;
        }
        $cols = (string) ( is_object( $r ) && isset( $r->cols ) ? $r->cols : '' );
        if ( 'background_id,pokemon_id' !== $cols && 'pokemon_id,background_id' !== $cols ) {
            continue;
        }
        $iname = is_object( $r )
            ? (string) ( $r->index_name ?? $r->INDEX_NAME ?? '' )
            : '';
        if ( $iname === '' || ! preg_match( '/^[A-Za-z0-9_]+$/', $iname ) ) {
            continue;
        }
        $wpdb->query( "ALTER TABLE `{$table}` DROP INDEX `{$iname}`" );
    }
    $n = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
            $wpdb->dbname,
            $table,
            'background_pokemon_kind'
        )
    );
    if ( $n > 0 ) {
        return;
    }
    $wpdb->query( "ALTER TABLE `{$table}` ADD UNIQUE KEY `background_pokemon_kind` (`background_id`, `pokemon_id`, `link_kind`)" );
}

/**
 * Enregistre les liens fond ↔ Pokémon (forme de base / obscur / dynamax / gigamax).
 *
 * @param int   $background_id
 * @param int[] $pokemon_shiny_active  IDs forme de base, shiny dispo
 * @param int[] $pokemon_shiny_locked  IDs forme de base, shiny verrou
 * @param int[] $pokemon_shadow
 * @param int[] $pokemon_dynamax
 * @param int[] $pokemon_gigantamax
 */
function poke_hub_save_background_pokemon_links( int $background_id, array $pokemon_shiny_active, array $pokemon_shiny_locked, array $pokemon_shadow, array $pokemon_dynamax, array $pokemon_gigantamax ): void {
    poke_hub_ensure_background_pokemon_link_unique_index();
    global $wpdb;
    $links_table = pokehub_get_table( 'pokemon_background_pokemon_links' );
    if ( ! $links_table || $background_id <= 0 ) {
        return;
    }
    $wpdb->delete( $links_table, [ 'background_id' => $background_id ], [ '%d' ] );

    $shiny_active = array_values( array_filter( array_map( 'intval', $pokemon_shiny_active ), static function ( int $x ): bool {
        return $x > 0;
    } ) );
    $shiny_locked = array_values( array_filter( array_map( 'intval', $pokemon_shiny_locked ), static function ( int $x ): bool {
        return $x > 0;
    } ) );
    $base_ids     = array_values( array_unique( array_merge( $shiny_active, $shiny_locked ) ) );
    sort( $base_ids );
    $log_ins_fail = static function ( string $ctx, int $background_id, int $pid, string $kind = '' ) use ( $wpdb ): void {
        if ( $wpdb->last_error && defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
            $kind_s = $kind !== '' ? ( ', kind=' . $kind ) : '';
            error_log( "pokehub bg link insert failed ({$ctx} bg={$background_id} pid={$pid}{$kind_s}): " . $wpdb->last_error );
        }
    };
    foreach ( $base_ids as $pid ) {
        $is_lock = in_array( $pid, $shiny_locked, true ) ? 1 : 0;
        $ok = $wpdb->insert(
            $links_table,
            [
                'background_id'   => $background_id,
                'pokemon_id'      => $pid,
                'is_shiny_locked' => $is_lock,
                'link_kind'       => POKE_HUB_BG_LINK_BASE,
            ],
            [ '%d', '%d', '%d', '%s' ]
        );
        if ( ! $ok ) {
            $log_ins_fail( 'base', $background_id, $pid, 'base' );
        }
    }
    $append = static function ( array $ids, string $kind ) use ( $wpdb, $links_table, $background_id, $log_ins_fail ): void {
        $ids = array_values( array_filter( array_unique( array_map( 'intval', $ids ) ), static function ( int $x ): bool {
            return $x > 0;
        } ) );
        foreach ( $ids as $pid ) {
            $ok = $wpdb->insert(
                $links_table,
                [
                    'background_id'   => $background_id,
                    'pokemon_id'      => $pid,
                    'is_shiny_locked' => 0,
                    'link_kind'       => $kind,
                ],
                [ '%d', '%d', '%d', '%s' ]
            );
            if ( ! $ok ) {
                $log_ins_fail( 'variant', $background_id, $pid, $kind );
            }
        }
    };
    $append( $pokemon_shadow, POKE_HUB_BG_LINK_SHADOW );
    $append( $pokemon_dynamax, POKE_HUB_BG_LINK_DYNAMAX );
    $append( $pokemon_gigantamax, POKE_HUB_BG_LINK_GIGANTAMAX );
}

/**
 * Retourne les IDs des Pokémon liés à un fond donné.
 *
 * @param int $background_id ID du fond (pokemon_backgrounds.id)
 * @return int[] Liste d’IDs de Pokémon
 */
function poke_hub_get_pokemon_ids_for_background(int $background_id): array {
    global $wpdb;
    $links_table = pokehub_get_table('pokemon_background_pokemon_links');
    if (!$links_table || $background_id <= 0) {
        return [];
    }
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT pokemon_id FROM {$links_table} WHERE background_id = %d ORDER BY pokemon_id ASC",
        $background_id
    ));
    return array_map('intval', is_array($ids) ? $ids : []);
}

/**
 * Retourne les événements associés à un fond (un fond peut avoir plusieurs événements).
 *
 * @param int $background_id ID du fond (pokemon_backgrounds.id)
 * @return array Liste de [ 'event_type' => string, 'event_id' => int ]
 */
function poke_hub_get_background_events(int $background_id): array {
    global $wpdb;
    $table = pokehub_get_table('pokemon_background_events');
    if (!$table || $background_id <= 0) {
        return [];
    }
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT event_type, event_id FROM {$table} WHERE background_id = %d ORDER BY id ASC",
            $background_id
        ),
        ARRAY_A
    );
    if (!is_array($rows)) {
        return [];
    }
    return array_map(function ($row) {
        return [
            'event_type' => (string) ($row['event_type'] ?? ''),
            'event_id'   => (int) ($row['event_id'] ?? 0),
        ];
    }, $rows);
}

/**
 * Retourne les IDs des Pokémon shiny lock pour un fond (fond sorti avant le shiny).
 *
 * @param int $background_id ID du fond
 * @return int[] Liste d'IDs de Pokémon
 */
function poke_hub_get_background_shiny_locked_pokemon_ids(int $background_id): array {
    global $wpdb;
    $links_table = pokehub_get_table('pokemon_background_pokemon_links');
    if (!$links_table || $background_id <= 0) {
        return [];
    }
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT pokemon_id FROM {$links_table} WHERE background_id = %d AND is_shiny_locked = 1
         AND (link_kind = %s OR link_kind = '' OR link_kind IS NULL) ORDER BY pokemon_id ASC",
        $background_id,
        POKE_HUB_BG_LINK_BASE
    ));
    return array_map('intval', is_array($ids) ? $ids : []);
}

/**
 * Indique si un Pokémon est shiny lock pour un fond donné (fond sorti avant le shiny).
 *
 * @param int $background_id ID du fond
 * @param int $pokemon_id ID du Pokémon
 * @return bool
 */
function poke_hub_is_background_pokemon_shiny_locked(int $background_id, int $pokemon_id): bool {
    global $wpdb;
    $links_table = pokehub_get_table('pokemon_background_pokemon_links');
    if (!$links_table || $background_id <= 0 || $pokemon_id <= 0) {
        return false;
    }
    $val = $wpdb->get_var($wpdb->prepare(
        "SELECT is_shiny_locked FROM {$links_table} WHERE background_id = %d AND pokemon_id = %d
         AND (link_kind = %s OR link_kind = '' OR link_kind IS NULL) LIMIT 1",
        $background_id,
        $pokemon_id,
        POKE_HUB_BG_LINK_BASE
    ));
    return (int) $val === 1;
}

/**
 * Retourne les IDs des Pokémon liés à au moins un des fonds donnés (union).
 *
 * @param int[] $background_ids IDs des fonds
 * @return int[] Liste d’IDs de Pokémon, sans doublon
 */
function poke_hub_get_pokemon_ids_for_backgrounds(array $background_ids): array {
    global $wpdb;
    $links_table = pokehub_get_table('pokemon_background_pokemon_links');
    if (!$links_table) {
        return [];
    }
    $background_ids = array_filter(array_map('intval', $background_ids));
    if (empty($background_ids)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($background_ids), '%d'));
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT pokemon_id FROM {$links_table} WHERE background_id IN ({$placeholders}) ORDER BY pokemon_id ASC",
        ...$background_ids
    ));
    return array_map('intval', is_array($ids) ? $ids : []);
}

/**
 * Retourne les IDs de tous les Pokémon qui ont au moins un fond lié.
 *
 * @return int[] Liste d’IDs de Pokémon
 */
function poke_hub_get_pokemon_ids_with_any_background(): array {
    global $wpdb;
    $links_table = pokehub_get_table('pokemon_background_pokemon_links');
    if (!$links_table) {
        return [];
    }
    $ids = $wpdb->get_col("SELECT DISTINCT pokemon_id FROM {$links_table} ORDER BY pokemon_id ASC");
    return array_map('intval', is_array($ids) ? $ids : []);
}
