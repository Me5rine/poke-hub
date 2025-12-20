<?php
// File: modules/pokemon/functions/pokemon-import-game-master-helpers.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Charge le JSON du Game Master depuis une URL ou un chemin local.
 *
 * @param string $source URL http(s) ou chemin de fichier.
 * @return string|\WP_Error
 */
function poke_hub_pokemon_load_gamemaster_json( $source ) {
    $source = trim( (string) $source );
    if ( $source === '' ) {
        return new \WP_Error( 'invalid_source', 'Empty Game Master source.' );
    }

    // URL ?
    if ( filter_var( $source, FILTER_VALIDATE_URL ) ) {
        $response = wp_remote_get(
            $source,
            [
                'timeout' => 60,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return new \WP_Error( 'http_error', 'Bad response code: ' . $code );
        }

        $body = wp_remote_retrieve_body( $response );
        if ( ! is_string( $body ) || $body === '' ) {
            return new \WP_Error( 'empty_body', 'Empty Game Master body.' );
        }

        return $body;
    }

    // Sinon : fichier local
    if ( ! file_exists( $source ) || ! is_readable( $source ) ) {
        return new \WP_Error( 'file_not_readable', 'Game Master file not readable: ' . $source );
    }

    $json = file_get_contents( $source );
    if ( false === $json || '' === $json ) {
        return new \WP_Error( 'file_empty', 'Game Master file is empty: ' . $source );
    }

    return $json;
}

/**
 * Convertit un proto type du style "POKEMON_TYPE_FIRE" en slug "fire".
 *
 * @param string $proto
 * @return string
 */
function poke_hub_pokemon_gm_type_proto_to_slug( $proto ) {
    $proto = (string) $proto;
    if ( $proto === '' ) {
        return '';
    }

    // ex: POKEMON_TYPE_FIRE → fire
    $proto = str_replace( 'POKEMON_TYPE_', '', $proto );
    $proto = strtolower( $proto );

    return $proto;
}

/**
 * Convertit un identifiant proto en slug lisible.
 * - "VENUSAUR" → "venusaur"
 * - "FURY_CUTTER_FAST" → "fury-cutter-fast"
 *
 * @param string $id
 * @return string
 */
function poke_hub_pokemon_gm_id_to_slug( $id ) {
    $id = (string) $id;
    $id = strtolower( $id );
    $id = str_replace( '_fast', '', $id ); // optionnel
    $id = str_replace( '_', '-', $id );
    return $id;
}

/**
 * Convertit un identifiant proto en label humain approximatif.
 * - "FURY_CUTTER_FAST" → "Fury Cutter Fast"
 * - "VENUSAUR" → "Venusaur"
 *
 * @param string $id
 * @return string
 */
function poke_hub_pokemon_gm_id_to_label( $id ) {
    $id = (string) $id;
    $id = strtolower( $id );
    $id = str_replace( '_fast', '', $id ); // optionnel
    $parts = explode( '_', $id );
    $parts = array_map( 'ucfirst', $parts );
    return implode( ' ', $parts );
}

/**
 * Extrait le numéro de Pokédex à partir d’un templateId type "V0001_POKEMON_BULBASAUR".
 *
 * @param string $template_id
 * @return int
 */
function poke_hub_pokemon_gm_template_to_dex( $template_id ) {
    if ( ! preg_match( '/^V(\d{4})_POKEMON/i', (string) $template_id, $m ) ) {
        return 0;
    }
    return (int) $m[1];
}

/**
 * Devine la génération depuis le numéro de Pokédex.
 *
 * @param int $dex_number
 * @return int 1..9 ou 0 si inconnu
 */
function poke_hub_pokemon_guess_generation_by_dex( $dex_number ) {
    $dex = (int) $dex_number;

    if ( $dex >= 1 && $dex <= 151 ) {
        return 1;
    }
    if ( $dex >= 152 && $dex <= 251 ) {
        return 2;
    }
    if ( $dex >= 252 && $dex <= 386 ) {
        return 3;
    }
    if ( $dex >= 387 && $dex <= 493 ) {
        return 4;
    }
    if ( $dex >= 494 && $dex <= 649 ) {
        return 5;
    }
    if ( $dex >= 650 && $dex <= 721 ) {
        return 6;
    }
    if ( $dex >= 722 && $dex <= 809 ) {
        return 7;
    }
    if ( $dex >= 810 && $dex <= 905 ) {
        return 8;
    }
    if ( $dex >= 906 && $dex <= 1010 ) {
        return 9;
    }

    return 0;
}

/**
 * Helper i18n pour obtenir EN/FR (et éventuellement d’autres langues) à partir d’un slug.
 *
 * Filtre "poke_hub_pokemon_import_i18n" attendu (optionnel) :
 *
 * [
 *   'pokemon' => [...],
 *   'moves'   => [...],
 *   'types'   => [...],
 * ]
 *
 * @param string $category 'pokemon' | 'moves' | 'types'
 * @param string $slug
 * @param string $default_en
 * @return array ['en'=>..., 'fr'=>..., 'de'=>..., 'it'=>..., 'es'=>..., 'ja'=>...]
 */
function poke_hub_pokemon_get_i18n_names( $category, $slug, $default_en ) {
    static $cache = null;

    if ( null === $cache ) {
        $cache = apply_filters(
            'poke_hub_pokemon_import_i18n',
            [
                'pokemon' => [],
                'moves'   => [],
                'types'   => [],
            ]
        );

        if ( ! is_array( $cache ) ) {
            $cache = [
                'pokemon' => [],
                'moves'   => [],
                'types'   => [],
            ];
        }
    }

    $allowed_langs = [ 'en', 'fr', 'de', 'it', 'es', 'ja','ko' ];

    // Initialisation : seulement EN avec la valeur par défaut, les autres langues vides
    $names = [];
    $names['en'] = $default_en;
    foreach ( $allowed_langs as $lang ) {
        if ( $lang !== 'en' ) {
            $names[ $lang ] = '';
        }
    }

    $category_map = $cache[ $category ] ?? [];
    $entry        = $category_map[ $slug ] ?? null;

    if ( is_array( $entry ) ) {
        foreach ( $allowed_langs as $lang ) {
            if ( ! empty( $entry[ $lang ] ) ) {
                $names[ $lang ] = (string) $entry[ $lang ];
            }
        }
    } elseif ( is_string( $entry ) && $entry !== '' ) {
        // Cas simple : on considère que c'est du FR
        $names['fr'] = $entry;
    }

    // Appliquer le filtre pour les noms officiels Bulbapedia si nécessaire
    $names = apply_filters('poke_hub_pokemon_i18n_names', $names, $category, $slug, $default_en);

    return $names;
}

/**
 * Récupère un mapping Game Master → forme à partir de pokemon_form_mappings.
 *
 * @param string $pokemon_id_proto ex: 'MEWTWO'
 * @param string $form_proto       ex: 'MEWTWO_A'
 * @return array|null
 */
function poke_hub_pokemon_get_form_mapping( $pokemon_id_proto, $form_proto ) {
    if ( ! function_exists( 'pokehub_get_table' ) ) {
        return null;
    }

    global $wpdb;

    $pokemon_id_proto = (string) $pokemon_id_proto;
    $form_proto       = (string) $form_proto;

    if ( $pokemon_id_proto === '' || $form_proto === '' ) {
        return null;
    }

    $table = pokehub_get_table( 'pokemon_form_mappings' );
    if ( ! $table ) {
        return null;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE pokemon_id_proto = %s AND form_proto = %s LIMIT 1",
            $pokemon_id_proto,
            $form_proto
        ),
        ARRAY_A
    );

    return $row ?: null;
}

/**
 * Récupère un variant global (pokemon_form_variants) par form_slug.
 *
 * @param string $form_slug
 * @return array|null
 */
function poke_hub_pokemon_get_form_variant_by_slug( $form_slug ) {
    if ( ! function_exists( 'pokehub_get_table' ) ) {
        return null;
    }

    global $wpdb;

    $form_slug = sanitize_title( (string) $form_slug );
    if ( $form_slug === '' ) {
        return null;
    }

    $table = pokehub_get_table( 'pokemon_form_variants' );
    if ( ! $table ) {
        return null;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE form_slug = %s LIMIT 1",
            $form_slug
        ),
        ARRAY_A
    );

    return $row ?: null;
}

/**
 * Normalise un proto de forme en slug de forme, **sans** mapping en dur.
 *
 * - Forme vide / UNSET / FORM_UNSET → '' (forme de base)
 * - Sinon :
 *   * on enlève l'éventuel préfixe "POKEMONID_"
 *   * on passe en minuscule
 *   * on remplace "_" par "-"
 *
 * @param string $pokemon_id_proto
 * @param string $form_proto
 * @return string Slug de forme ('' = forme de base)
 */
function poke_hub_pokemon_normalize_form_proto( $pokemon_id_proto, $form_proto ) {
    $pokemon_id_proto = (string) $pokemon_id_proto;
    $form_proto       = (string) $form_proto;

    if ( $form_proto === '' || $form_proto === 'UNSET' || $form_proto === 'FORM_UNSET' ) {
        return '';
    }

    $suffix = $form_proto;

    // 1) Prefix exact : "MEWTWO_" dans "MEWTWO_A"
    if ( $pokemon_id_proto !== '' && strpos( $form_proto, $pokemon_id_proto . '_' ) === 0 ) {
        $suffix = substr( $form_proto, strlen( $pokemon_id_proto ) + 1 );
    } else {
        // 2) Fix Nidoran & co : si pokemonId a un suffixe de genre, Niantic peut utiliser la racine
        // Ex: pokemonId = NIDORAN_MALE, form = NIDORAN_NORMAL  => on retire "NIDORAN_"
        $root = preg_replace( '/_(MALE|FEMALE)$/', '', $pokemon_id_proto );
        if ( $root && $root !== $pokemon_id_proto && strpos( $form_proto, $root . '_' ) === 0 ) {
            $suffix = substr( $form_proto, strlen( $root ) + 1 );
        }
    }

    $suffix = strtolower( $suffix );
    $suffix = str_replace( '__', '_', $suffix );

    // "normal"/"standard" = forme de base
    if ( in_array( $suffix, [ 'normal', 'standard', 'form_normal', 'form_standard' ], true ) ) {
        return '';
    }

    // Cas tordu où la forme reste "..._normal" (ex: nidoran_normal si pas matché)
    if ( preg_match( '/(^|_)normal$/', $suffix ) || preg_match( '/(^|_)standard$/', $suffix ) ) {
        return '';
    }

    $suffix = str_replace( '_', '-', $suffix );
    return $suffix;
}

/**
 * Trouve (ou crée) un type Pokémon dans pokehub_pokemon_types à partir d’un slug.
 *
 * @param string $slug      ex: "fire", "water"
 * @param string $label_en  Label humain EN (optionnel)
 * @return int ID du type ou 0
 */
function poke_hub_pokemon_find_or_create_type( $slug, $label_en = '' ) {
    if ( ! function_exists( 'pokehub_get_table' ) ) {
        return 0;
    }

    global $wpdb;
    $slug = sanitize_title( $slug );
    if ( $slug === '' ) {
        return 0;
    }

    $types_table = pokehub_get_table( 'pokemon_types' );
    if ( ! $types_table ) {
        return 0;
    }

    // Cherche déjà
    $type_id = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT id FROM {$types_table} WHERE slug = %s LIMIT 1", $slug )
    );
    if ( $type_id > 0 ) {
        return $type_id;
    }

    if ( $label_en === '' ) {
        $label_en = poke_hub_pokemon_gm_id_to_label( $slug );
    }

    $names = poke_hub_pokemon_get_i18n_names( 'types', $slug, $label_en );
    $extra = [
        'names' => $names,
    ];

    $wpdb->insert(
        $types_table,
        [
            'slug'       => $slug,
            'name_en'    => $names['en'],
            'name_fr'    => $names['fr'],
            'color'      => '',
            'icon'       => '',
            'sort_order' => 0,
            'extra'      => wp_json_encode( $extra ),
        ],
        [ '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
    );

    return (int) $wpdb->insert_id;
}

/**
 * Met à jour la table pokemon_type_links pour un Pokémon donné.
 *
 * @param int   $pokemon_id
 * @param int[] $type_ids
 */
function poke_hub_pokemon_sync_pokemon_types_links( $pokemon_id, array $type_ids ) {
    if ( ! function_exists( 'pokehub_get_table' ) ) {
        return;
    }

    global $wpdb;

    $pokemon_id = (int) $pokemon_id;
    if ( $pokemon_id <= 0 ) {
        return;
    }

    $link_table = pokehub_get_table( 'pokemon_type_links' );
    if ( ! $link_table ) {
        return;
    }

    // Nettoyage des liens POUR CE POKÉMON UNIQUEMENT
    $type_ids = array_map( 'intval', $type_ids );
    $type_ids = array_filter(
        $type_ids,
        static function ( $v ) {
            return $v > 0;
        }
    );
    $type_ids = array_values( array_unique( $type_ids ) );

    $wpdb->delete( $link_table, [ 'pokemon_id' => $pokemon_id ], [ '%d' ] );

    if ( empty( $type_ids ) ) {
        return;
    }

    // slot 1 / 2...
    $values       = [];
    $placeholders = [];
    $slot         = 1;

    foreach ( $type_ids as $tid ) {
        $values[]       = $pokemon_id;
        $values[]       = $tid;
        $values[]       = $slot;
        $placeholders[] = '(%d, %d, %d)';
        $slot++;
    }

    $sql = "INSERT INTO {$link_table} (pokemon_id, type_id, slot) VALUES " . implode( ',', $placeholders );
    $wpdb->query( $wpdb->prepare( $sql, $values ) );
}

/**
 * Synchronise les liens Pokémon ↔ Attaques.
 *
 * @param int   $pokemon_id
 * @param array $links [
 *   [
 *     'attack_id'   => int,
 *     'role'        => 'fast'|'charged'|'special',
 *     'is_legacy'   => 0|1,
 *     'is_event'    => 0|1,
 *     'is_elite_tm' => 0|1,
 *   ], ...
 * ]
 * @param array $tables
 */
function poke_hub_pokemon_sync_pokemon_attack_links( $pokemon_id, array $links, array $tables ) {
    if ( ! function_exists( 'pokehub_get_table' ) ) {
        return;
    }

    global $wpdb;

    $pokemon_id = (int) $pokemon_id;
    if ( $pokemon_id <= 0 ) {
        return;
    }

    $link_table = ! empty( $tables['pokemon_attack_links'] )
        ? $tables['pokemon_attack_links']
        : pokehub_get_table( 'pokemon_attack_links' );

    if ( ! $link_table ) {
        return;
    }

    // Nettoyage des liens POUR CE POKÉMON UNIQUEMENT
    $wpdb->delete( $link_table, [ 'pokemon_id' => $pokemon_id ], [ '%d' ] );

    if ( empty( $links ) ) {
        return;
    }

    $values       = [];
    $placeholders = [];

    foreach ( $links as $link ) {
        $attack_id   = isset( $link['attack_id'] ) ? (int) $link['attack_id'] : 0;
        $role        = isset( $link['role'] ) ? (string) $link['role'] : '';
        $is_legacy   = ! empty( $link['is_legacy'] ) ? 1 : 0;
        $is_event    = ! empty( $link['is_event'] ) ? 1 : 0;
        $is_elite_tm = ! empty( $link['is_elite_tm'] ) ? 1 : 0;
        $extra       = isset( $link['extra'] ) ? $link['extra'] : null;

        if ( $attack_id <= 0 || $role === '' ) {
            continue;
        }

        // Si extra est un tableau, l'encoder en JSON
        if ( is_array( $extra ) ) {
            $extra = wp_json_encode( $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        }

        $values[]       = $pokemon_id;
        $values[]       = $attack_id;
        $values[]       = $role;
        $values[]       = $is_legacy;
        $values[]       = $is_event;
        $values[]       = $is_elite_tm;
        $values[]       = $extra;

        $placeholders[] = '(%d, %d, %s, %d, %d, %d, %s)';
    }

    if ( empty( $placeholders ) ) {
        return;
    }

    $sql = "INSERT INTO {$link_table}
        (pokemon_id, attack_id, role, is_legacy, is_event, is_elite_tm, extra)
        VALUES " . implode( ',', $placeholders );

    $wpdb->query( $wpdb->prepare( $sql, $values ) );
}

/**
 * Helper : récupère l’ID d’une attaque à partir de son slug.
 *
 * @param string $slug
 * @param array  $tables
 * @return int
 */
function poke_hub_pokemon_get_attack_id_by_slug( $slug, array $tables ) {
    global $wpdb;

    $slug = (string) $slug;
    if ( $slug === '' || empty( $tables['attacks'] ) ) {
        return 0;
    }

    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$tables['attacks']} WHERE slug = %s LIMIT 1",
            $slug
        )
    );
}

/**
 * Normalise un label de forme :
 * - trim
 * - compactage des espaces
 * - suppression du nom de base en préfixe (Farfetchd Farfetchd Galarian → Farfetchd Galarian, puis suffix = Galarian)
 *
 * @param string $base_name
 * @param string $suffix
 * @return string
 */
function poke_hub_pokemon_normalize_form_label_suffix( $base_name, $suffix ) {
    $base_name = trim( (string) $base_name );
    $suffix    = trim( (string) $suffix );

    if ( $suffix === '' ) {
        return '';
    }

    // Compactage des espaces multiples
    $suffix = preg_replace( '/\s+/', ' ', $suffix );

    // Si le suffix commence par le nom de base → on le retire
    if ( $base_name !== '' && stripos( $suffix, $base_name ) === 0 ) {
        $rest = trim( substr( $suffix, strlen( $base_name ) ) );
        if ( $rest !== '' ) {
            $suffix = $rest;
        }
    }

    return $suffix;
}

/**
 * Construit un index [pokemon_id_proto => ['male' => %, 'female' => %]]
 * à partir des blocs SPAWN_* / genderSettings.
 *
 * @param array $gm_entries Liste brute des entrées du Game Master
 * @return array
 */
function poke_hub_gm_build_gender_index( array $gm_entries ): array {
    $index = [];

    foreach ( $gm_entries as $entry ) {
        if ( empty( $entry['data']['genderSettings'] ) ) {
            continue;
        }

        $gs = $entry['data']['genderSettings'];

        if ( empty( $gs['pokemon'] ) || empty( $gs['gender'] ) ) {
            continue;
        }

        $pokemon_id = $gs['pokemon']; // ex: "BULBASAUR"

        $male   = $gs['gender']['malePercent']   ?? null;
        $female = $gs['gender']['femalePercent'] ?? null;

        // On attend des valeurs entre 0 et 1 → on convertit en %
        if ( $male !== null || $female !== null ) {
            $male   = $male   !== null ? round( $male * 100, 1) : 0.0;
            $female = $female !== null ? round( $female * 100, 1) : 0.0;

            $index[ $pokemon_id ] = [
                'male'   => $male,
                'female' => $female,
            ];
        }
    }

    return $index;
}

/**
 * Extrait les flags principaux d'un bloc pokemonSettings :
 * - isTradable / isTransferable
 * - shadow (stardust, candy, moves)
 * - buddyWalkedMegaEnergyAward
 * - encounter.attackProbability / dodgeProbability
 *
 * @param array $pokemon_settings
 * @return array [
 *   'is_tradable'      => 0|1,
 *   'is_transferable'  => 0|1,
 *   'has_shadow'       => 0|1,
 *   'has_purified'     => 0|1,
 *   'shadow_stardust'  => int,
 *   'shadow_candy'     => int,
 *   'shadow_move'      => 'FRUSTRATION'|'' ,
 *   'purified_move'    => 'RETURN'|'' ,
 *   'buddy_mega_energy_award' => int,
 *   'attack_probability'      => float,
 *   'dodge_probability'       => float,
 * ]
 */
function poke_hub_pokemon_extract_flags_from_settings( array $pokemon_settings ): array {
    $settings = $pokemon_settings;

    $is_tradable     = ! empty( $settings['isTradable'] ) ? 1 : 0;
    $is_transferable = ! empty( $settings['isTransferable'] ) ? 1 : 0;

    $shadow_stardust = 0;
    $shadow_candy    = 0;
    $shadow_move     = '';
    $purified_move   = '';
    $has_shadow      = 0;
    $has_purified    = 0;

    if ( ! empty( $settings['shadow'] ) && is_array( $settings['shadow'] ) ) {
        $sh = $settings['shadow'];

        // Si le bloc shadow existe, activer automatiquement les flags shadow et purified
        $has_shadow   = 1;
        $has_purified = 1;

        if ( isset( $sh['purificationStardustNeeded'] ) ) {
            $shadow_stardust = (int) $sh['purificationStardustNeeded'];
        }
        if ( isset( $sh['purificationCandyNeeded'] ) ) {
            $shadow_candy = (int) $sh['purificationCandyNeeded'];
        }
        if ( ! empty( $sh['shadowChargeMove'] ) ) {
            $shadow_move = (string) $sh['shadowChargeMove'];
        } elseif ( ! empty( $sh['shadowChargeAttack'] ) ) {
            // Support de l'ancienne clé pour compatibilité
            $shadow_move = (string) $sh['shadowChargeAttack'];
        }
        if ( ! empty( $sh['purifiedChargeMove'] ) ) {
            $purified_move = (string) $sh['purifiedChargeMove'];
        } elseif ( ! empty( $sh['purifiedChargeAttack'] ) ) {
            // Support de l'ancienne clé pour compatibilité
            $purified_move = (string) $sh['purifiedChargeAttack'];
        }
    }

    $buddy_mega_energy_award = 0;
    if ( isset( $settings['buddyWalkedMegaEnergyAward'] ) ) {
        $buddy_mega_energy_award = (int) $settings['buddyWalkedMegaEnergyAward'];
    }

    $attack_probability = 0.0;
    $dodge_probability  = 0.0;

    if ( ! empty( $settings['encounter'] ) && is_array( $settings['encounter'] ) ) {
        $enc = $settings['encounter'];

        if ( isset( $enc['attackProbability'] ) ) {
            $attack_probability = (float) $enc['attackProbability'];
        }
        if ( isset( $enc['dodgeProbability'] ) ) {
            $dodge_probability = (float) $enc['dodgeProbability'];
        }
    }

    return [
        'is_tradable'               => $is_tradable,
        'is_transferable'           => $is_transferable,
        'has_shadow'                => $has_shadow,
        'has_purified'              => $has_purified,
        'shadow_stardust'           => $shadow_stardust,
        'shadow_candy'              => $shadow_candy,
        'shadow_move'               => $shadow_move,
        'purified_move'             => $purified_move,
        'buddy_mega_energy_award'   => $buddy_mega_energy_award,
        'attack_probability'        => $attack_probability,
        'dodge_probability'         => $dodge_probability,
    ];
}

/**
 * Normalise le bloc evolutionBranch[] d'un pokemonSettings
 * dans un format générique utilisable pour la BDD.
 *
 * @param array $pokemon_settings pokemonSettings (data.pokemonSettings)
 * @return array[] Liste de branches :
 *   [
 *     'target_id_proto'        => 'IVYSAUR',
 *     'target_form_proto'      => 'IVYSAUR_NORMAL',
 *     'candy_cost'             => 25,
 *     'candy_cost_purified'    => 22,
 *     'method'                 => 'levelup|item|trade|quest|lure|stats|other',
 *     'item_requirement'       => 'ITEM_KINGS_ROCK',
 *     'item_requirement_cost'  => 0|50|20|...,
 *     'lure_item_requirement'  => 'ITEM_TROY_DISK_RAINY',
 *     'no_candy_cost_via_trade'=> 0|1,
 *     'gender_requirement'     => 'MALE|FEMALE|',
 *     'time_of_day'            => 'day|night|dusk|full_moon|',
 *     'priority'               => 0|10|...,
 *     'quest_template_id'      => 'FLORGES_EVOLUTION_QUEST',
 *     'is_random_evolution'    => 0|1,
 *     'extra'                  => array,
 *   ]
 */
function poke_hub_pokemon_normalize_evolution_branches( array $pokemon_settings ): array {
    if ( empty( $pokemon_settings['evolutionBranch'] ) || ! is_array( $pokemon_settings['evolutionBranch'] ) ) {
        return [];
    }

    $branches = [];

    foreach ( $pokemon_settings['evolutionBranch'] as $branch ) {
        if ( empty( $branch['evolution'] ) ) {
            continue;
        }

        // Récupération éventuelle du template de quête d'évolution
        $quest_template_id = '';
        if ( ! empty( $branch['questDisplay'] ) && is_array( $branch['questDisplay'] ) ) {
            $first_qd = reset( $branch['questDisplay'] );
            if ( ! empty( $first_qd['questRequirementTemplateId'] ) ) {
                $quest_template_id = (string) $first_qd['questRequirementTemplateId'];
            }
        }

        $b = [
            'target_id_proto'         => (string) $branch['evolution'],
            'target_form_proto'       => isset( $branch['form'] ) ? (string) $branch['form'] : '',
            'candy_cost'              => isset( $branch['candyCost'] ) ? (int) $branch['candyCost'] : 0,
            'candy_cost_purified'     => isset( $branch['candyCostPurified'] ) ? (int) $branch['candyCostPurified'] : 0,
            'priority'                => isset( $branch['priority'] ) ? (int) $branch['priority'] : 0,

            'method'                  => 'levelup',
            'item_requirement'        => isset( $branch['evolutionItemRequirement'] ) ? (string) $branch['evolutionItemRequirement'] : '',
            'item_requirement_cost'   => isset( $branch['evolutionItemRequirementCost'] ) ? (int) $branch['evolutionItemRequirementCost'] : 0,
            'lure_item_requirement'   => isset( $branch['lureItemRequirement'] ) ? (string) $branch['lureItemRequirement'] : '',
            'no_candy_cost_via_trade' => ! empty( $branch['noCandyCostViaTrade'] ) ? 1 : 0,
            'gender_requirement'      => isset( $branch['genderRequirement'] ) ? (string) $branch['genderRequirement'] : '',
            'time_of_day'             => '',
            'quest_template_id'       => $quest_template_id,
            'is_random_evolution'     => 0,
            'extra'                   => [],
        ];

        /**
         * Fenêtres temporelles :
         * - onlyDaytime      → day
         * - onlyNighttime    → night
         * - onlyDuskPeriod   → dusk (Rockruff dusk)
         *
         * Pleine lune sera gérée plutôt dans extra, avec éventuellement
         * time_of_day = full_moon si aucun autre time_of_day n'est déjà posé.
         */
        if ( ! empty( $branch['onlyDaytime'] ) ) {
            $b['time_of_day'] = 'day';
        } elseif ( ! empty( $branch['onlyNighttime'] ) ) {
            $b['time_of_day'] = 'night';
        } elseif ( ! empty( $branch['onlyDuskPeriod'] ) ) {
            $b['time_of_day'] = 'dusk';
        }

        // Conditions spéciales : pleine lune, crépuscule, etc.
        // (en plus du time_of_day, pour pouvoir filtrer finement côté front)
        if ( ! empty( $branch['onlyFullMoon'] ) ) {
            $b['extra']['only_full_moon'] = true;
            $b['extra']['moon_phase']     = 'full';

            // Optionnel : si aucun time_of_day défini, on taggue full_moon
            if ( $b['time_of_day'] === '' ) {
                $b['time_of_day'] = 'full_moon';
            }
        }

        if ( ! empty( $branch['onlyDuskPeriod'] ) ) {
            // On garde aussi une trace explicite
            $b['extra']['only_dusk_period'] = true;
        }

        // Méthode principale
        if ( $b['lure_item_requirement'] !== '' ) {
            $b['method'] = 'lure';
        } elseif ( $b['item_requirement'] !== '' ) {
            $b['method'] = 'item';
        } elseif ( $b['no_candy_cost_via_trade'] ) {
            $b['method'] = 'trade';
        } else {
            $b['method'] = 'levelup';
        }

        $branches[] = $b;
    }

    /**
     * Filtre pour affiner les branches, tagger les cas spéciaux :
     * - stats (Hitmon*)
     * - random interne (Silcoon/Cascoon)
     * - pleine lune / météo exotique / etc.
     */
    $branches = apply_filters( 'poke_hub_pokemon_import_evolution_branches', $branches, $pokemon_settings );

    return $branches;
}

/**
 * Transforme un tempEvoId Niantic en form_slug interne.
 *
 * Exemples :
 * - TEMP_EVOLUTION_MEGA     → mega
 * - TEMP_EVOLUTION_MEGA_X   → mega-x
 * - TEMP_EVOLUTION_MEGA_Y   → mega-y
 * - TEMP_EVOLUTION_PRIMAL   → primal
 */
function poke_hub_pokemon_temp_evo_id_to_form_slug( string $temp_evo_id ): string {
    $temp_evo_id = trim( $temp_evo_id );

    switch ( $temp_evo_id ) {
        case 'TEMP_EVOLUTION_MEGA':
            return 'mega';
        case 'TEMP_EVOLUTION_MEGA_X':
            return 'mega-x';
        case 'TEMP_EVOLUTION_MEGA_Y':
            return 'mega-y';
        case 'TEMP_EVOLUTION_PRIMAL':
            return 'primal';
        default:
            // Fallback lisible, au cas où Niantic rajoute d'autres variantes
            $slug = str_replace( 'TEMP_EVOLUTION_', '', $temp_evo_id );
            $slug = strtolower( $slug );
            $slug = str_replace( '__', '_', $slug );
            return sanitize_title( $slug );
    }
}

/**
 * Label humain pour une temp evolution.
 *
 * - TEMP_EVOLUTION_MEGA     → "Mega"
 * - TEMP_EVOLUTION_MEGA_X   → "Mega X"
 * - TEMP_EVOLUTION_MEGA_Y   → "Mega Y"
 * - TEMP_EVOLUTION_PRIMAL   → "Primal"
 */
function poke_hub_pokemon_temp_evo_id_to_label( string $temp_evo_id ): string {
    switch ( $temp_evo_id ) {
        case 'TEMP_EVOLUTION_MEGA':
            return 'Mega';
        case 'TEMP_EVOLUTION_MEGA_X':
            return 'Mega X';
        case 'TEMP_EVOLUTION_MEGA_Y':
            return 'Mega Y';
        case 'TEMP_EVOLUTION_PRIMAL':
            return 'Primal';
        default:
            $label = str_replace( 'TEMP_EVOLUTION_', '', $temp_evo_id );
            $label = str_replace( '_', ' ', strtolower( $label ) );
            return ucwords( $label );
    }
}

/**
 * Helpers booléens : Méga / Primal ?
 */
function poke_hub_pokemon_is_temp_evo_mega( string $temp_evo_id ): bool {
    return ( $temp_evo_id === 'TEMP_EVOLUTION_MEGA'
        || strpos( $temp_evo_id, 'TEMP_EVOLUTION_MEGA_' ) === 0 );
}

function poke_hub_pokemon_is_temp_evo_primal( string $temp_evo_id ): bool {
    return ( $temp_evo_id === 'TEMP_EVOLUTION_PRIMAL' );
}