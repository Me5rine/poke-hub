<?php
// File: modules/pokemon/tools/pokemon-import-game-master.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$active_modules = get_option( 'poke_hub_active_modules', [] );
if ( ! is_array( $active_modules ) ) {
    $active_modules = [];
}

if ( ! in_array( 'pokemon', $active_modules, true ) ) {
    echo '<div class="notice notice-error"><p>'
        . esc_html__( 'Game Master tools are only available when the Pokémon module is active.', 'poke-hub' )
        . '</p></div>';
    return;
}

/**
 * Importe / met à jour UN Pokémon à partir de pokemonSettings.
 *
 * + création automatique des formes Méga / Primo comme Pokémon à part entière
 *   (slug = mega-charizard-x, primal-kyogre, etc.) avec leurs stats dédiées.
 *
 * @param string $template_id
 * @param array  $settings
 * @param array  $tables
 * @param array  $stats
 * @param array  $seen_pokemon_slugs
 * @param array  $gender_index
 * @param array  $pokemon_index
 * @param array  $form_costume_index [pokemonIdProto][formProtoUpper] => true (formSettings.forms[].isCostume)
 * @return array
 */
function poke_hub_pokemon_import_from_pokemon_settings(
    $template_id,
    array $settings,
    array $tables,
    array $stats,
    array &$seen_pokemon_slugs,
    array $gender_index = [],
    array &$pokemon_index = [],
    array $gmax_species = [],
    array $species_with_explicit_normal_form = [],
    array $form_costume_index = []
) {
    global $wpdb;

    $pokemon_table = $tables['pokemon'];

    $pokemon_id_proto = $settings['pokemonId'] ?? '';
    if ( $pokemon_id_proto === '' ) {
        return $stats;
    }
    $has_gmax_form = ! empty( $gmax_species[ (string) $pokemon_id_proto ] );
    $has_explicit_normal_family = ! empty( $species_with_explicit_normal_form[ (string) $pokemon_id_proto ] );

    // Dex & génération
    $dex_number        = poke_hub_pokemon_gm_template_to_dex( $template_id );
    $generation_number = poke_hub_pokemon_guess_generation_by_dex( $dex_number );

    // Forme brute Niantic
    $form_proto   = $settings['form'] ?? '';

    // Si l'espèce possède une forme NORMAL explicite (ex: Palkia/Dialga),
    // l'entrée sans forme représente la "famille" (placeholder de regroupement).
    $is_family_placeholder = ( $has_explicit_normal_family && $form_proto === '' );
    // Form slug : normalisation depuis le proto (plus de table form_mappings)
    // Certaines espèces (ex: Dialga/Palkia) ont une forme NORMAL explicite
    // sans exposer formChange dans pokemonSettings.
    $keep_normal_form = $has_explicit_normal_family;
    $form_slug = poke_hub_pokemon_normalize_form_proto(
        $pokemon_id_proto,
        $form_proto,
        array_merge(
            $settings,
            [
                '__keep_normal_form' => $keep_normal_form ? 1 : 0,
            ]
        )
    );

    // Nom humain de base
    $base_name = poke_hub_pokemon_gm_id_to_label( $pokemon_id_proto );

    // Suffix (label_suffix) depuis la forme GM si spécial
    $label_suffix = '';

    // Fallback suffix depuis la forme GM si spécial
    if ( $label_suffix === '' && $form_slug !== '' && $form_proto !== '' && $form_proto !== 'UNSET' && $form_proto !== 'FORM_UNSET' ) {
        $raw_label = poke_hub_pokemon_gm_id_to_label( $form_proto );
        if ( $raw_label === $form_proto || $raw_label === '' ) {
            $raw_label = ucwords( str_replace( [ '-', '_' ], ' ', $form_slug ) );
        }
        $label_suffix = $raw_label;
    }

    // Normalisation du suffix
    $label_suffix = poke_hub_pokemon_normalize_form_label_suffix( $base_name, $label_suffix );
    if ( $keep_normal_form && $form_slug === 'normal' ) {
        // La forme NORMAL explicite doit s'afficher comme le nom canonique (sans "Normal").
        $label_suffix = '';
    }

    // Nom complet
    if ( $label_suffix !== '' ) {
        $name_label = $base_name . ' ' . $label_suffix;
    } else {
        $name_label = $base_name;
        if ( substr( $name_label, -7 ) === ' Normal' ) {
            $name_label = substr( $name_label, 0, -7 );
        }
    }

    // Sync de la forme globale dans pokemon_form_variants
    $variant_id       = 0;
    $variant_category = '';
    $variant_group    = '';

    if ( $form_slug !== '' ) {
        $variant_label = $label_suffix !== '' ? $label_suffix : ucwords( str_replace( '-', ' ', $form_slug ) );

        if ( function_exists( 'poke_hub_pokemon_get_form_variant_by_slug' ) ) {
            $existing_variant = poke_hub_pokemon_get_form_variant_by_slug( $form_slug );
            if ( is_array( $existing_variant ) && ! empty( $existing_variant['id'] ) ) {
                $variant_id = (int) $existing_variant['id'];
            }
        }

        if ( $variant_id <= 0 && function_exists( 'poke_hub_pokemon_upsert_form_variant' ) ) {
            $guess_settings = $settings;
            $fproto_u = $form_proto !== '' ? strtoupper( (string) $form_proto ) : '';
            if ( $fproto_u !== '' && ! empty( $form_costume_index[ (string) $pokemon_id_proto ][ $fproto_u ] ) ) {
                $guess_settings['__isCostume'] = true;
            }
            if ( is_string( $template_id ) && stripos( $template_id, '_COPY' ) !== false ) {
                $guess_settings['__isClone'] = true;
            }
            $auto_form_type = function_exists( 'poke_hub_pokemon_guess_form_type_from_gm' )
                ? poke_hub_pokemon_guess_form_type_from_gm( $pokemon_id_proto, $form_proto, $form_slug, $guess_settings )
                : 'default';
            $variant_id = poke_hub_pokemon_upsert_form_variant(
                $form_slug,
                $variant_label,
                $auto_form_type, // category = type de forme
                '', // group
                [
                    'pokemon_id_proto' => $pokemon_id_proto,
                    'form_proto'       => $form_proto,
                    'template_id'      => $template_id,
                    'form_type'        => $auto_form_type,
                ]
            );
        }

        $variant_row = poke_hub_pokemon_get_form_variant_by_slug( $form_slug );
        if ( is_array( $variant_row ) ) {
            $variant_category = $variant_row['category'] ?? '';
            $variant_group    = $variant_row['group'] ?? '';
        }
    }

    // Slugs
    $slug_base = poke_hub_pokemon_gm_id_to_slug( $pokemon_id_proto );
    $slug      = $slug_base;
    if ( $is_family_placeholder ) {
        $slug = $slug_base . '-family';
    } elseif ( $keep_normal_form && $form_slug === 'normal' ) {
        $slug = $slug_base;
    } elseif ( $form_slug !== '' ) {
        $slug .= '-' . $form_slug;
    }

    // Par défaut :
    // - espèces avec famille explicite : la ligne "*-family" est la forme par défaut
    // - autres espèces : forme vide = défaut
    if ( $is_family_placeholder ) {
        $is_default = 1;
    } elseif ( $keep_normal_form && $form_slug === 'normal' ) {
        $is_default = 0;
    } else {
        $is_default = ( $form_slug === '' ) ? 1 : 0;
    }

    // Slug vu dans ce Game Master (stat)
    $seen_pokemon_slugs[$slug] = true;

    // Gender ratio
    $gender_male   = 0.0;
    $gender_female = 0.0;

    if ( ! empty( $gender_index[ $pokemon_id_proto ] ) ) {
        $gender_male   = (float) ( $gender_index[ $pokemon_id_proto ]['male']   ?? 0.0 );
        $gender_female = (float) ( $gender_index[ $pokemon_id_proto ]['female'] ?? 0.0 );
    }

    // Stats
    $stats_proto = $settings['stats'] ?? [];
    $base_atk    = isset( $stats_proto['baseAttack'] ) ? (int) $stats_proto['baseAttack'] : 0;
    $base_def    = isset( $stats_proto['baseDefense'] ) ? (int) $stats_proto['baseDefense'] : 0;
    $base_sta    = isset( $stats_proto['baseStamina'] ) ? (int) $stats_proto['baseStamina'] : 0;

    // CP sets base
    $cp_sets = [
        'max_cp'        => [],
        'min_cp_10'     => [],
        'min_cp_shadow' => [],
    ];

    if ( function_exists( 'poke_hub_pokemon_build_cp_sets_for_pokemon' )
        && $base_atk > 0 && $base_def > 0 && $base_sta > 0
    ) {
        $cp_sets = poke_hub_pokemon_build_cp_sets_for_pokemon(
            $base_atk,
            $base_def,
            $base_sta
        );
    }

    $type1_proto = $settings['type'] ?? '';
    $type2_proto = $settings['type2'] ?? '';

    $type1_slug = poke_hub_pokemon_gm_type_proto_to_slug( $type1_proto );
    $type2_slug = poke_hub_pokemon_gm_type_proto_to_slug( $type2_proto );

    // Données GM supplémentaires
    $height_m  = isset( $settings['pokedexHeightM'] ) ? (float) $settings['pokedexHeightM'] : 0.0;
    $weight_kg = isset( $settings['pokedexWeightKg'] ) ? (float) $settings['pokedexWeightKg'] : 0.0;

    $km_buddy_distance = isset( $settings['kmBuddyDistance'] ) ? (float) $settings['kmBuddyDistance'] : 0.0;

    $encounter = ( isset( $settings['encounter'] ) && is_array( $settings['encounter'] ) )
        ? $settings['encounter']
        : [];

    $base_capture_rate = isset( $encounter['baseCaptureRate'] ) ? (float) $encounter['baseCaptureRate'] : null;
    $base_flee_rate    = isset( $encounter['baseFleeRate'] ) ? (float) $encounter['baseFleeRate'] : null;

    // Second charged move cost (Game Master thirdMove ou thirdAttack pour rétrocompatibilité)
    $third_attack_raw = ( isset( $settings['thirdMove'] ) && is_array( $settings['thirdMove'] ) )
        ? $settings['thirdMove']
        : ( ( isset( $settings['thirdAttack'] ) && is_array( $settings['thirdAttack'] ) )
            ? $settings['thirdAttack']
            : [] );

    $second_attack_cost = [
        'stardust' => isset( $third_attack_raw['stardustToUnlock'] ) ? (int) $third_attack_raw['stardustToUnlock'] : 0,
        'candy'    => isset( $third_attack_raw['candyToUnlock'] ) ? (int) $third_attack_raw['candyToUnlock'] : 0,
    ];

    // Flags GM
    if ( function_exists( 'poke_hub_pokemon_extract_flags_from_settings' ) ) {
        $flags = poke_hub_pokemon_extract_flags_from_settings( $settings );
    } else {
        $flags = [
            'is_tradable'               => ! empty( $settings['isTradable'] ) ? 1 : 0,
            'is_transferable'           => ! empty( $settings['isTransferable'] ) ? 1 : 0,
            'has_shadow'                => 0,
            'has_purified'              => 0,
            'shadow_stardust'           => 0,
            'shadow_candy'              => 0,
            'shadow_move'               => '',
            'purified_move'             => '',
            'buddy_mega_energy_award'   => 0,
            'attack_probability'        => 0.0,
            'dodge_probability'         => 0.0,
        ];
    }

    // Noms multi-langues
    $names = poke_hub_pokemon_get_i18n_names( 'pokemon', $slug, $name_label );

    // Génération
    $generation_id = 0;
    if ( ! empty( $tables['generations'] ) && $generation_number > 0 ) {
        $generation_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$tables['generations']} WHERE generation_number = %d LIMIT 1",
                $generation_number
            )
        );
    }

    // Ligne existante ?
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$pokemon_table} WHERE slug = %s LIMIT 1",
            $slug
        )
    );

    $game_key = 'pokemon_go';

    $game_go = [
        'pokedex' => [
            'height_m'  => $height_m,
            'weight_kg' => $weight_kg,
        ],
        'buddy'   => [
            'km_buddy_distance'        => $km_buddy_distance,
            'buddy_mega_energy_award'  => $flags['buddy_mega_energy_award'] ?? 0,
        ],
        'encounter' => [
            'base_capture_rate'  => $base_capture_rate,
            'base_flee_rate'     => $base_flee_rate,
            'attack_probability' => $flags['attack_probability'] ?? 0.0,
            'dodge_probability'  => $flags['dodge_probability'] ?? 0.0,
        ],
        'encounter_raw' => $encounter,
        'second_move'   => [
            'cost' => $second_attack_cost,
            'raw'  => $third_attack_raw,
        ],
        'shadow' => [
            'has_shadow'      => (bool) ( $flags['has_shadow'] ?? 0 ),
            'has_purified'    => (bool) ( $flags['has_purified'] ?? 0 ),
            'stardust'        => $flags['shadow_stardust'] ?? 0,
            'candy'           => $flags['shadow_candy'] ?? 0,
            'shadow_move'     => $flags['shadow_move'] ?? '',
            'purified_move'   => $flags['purified_move'] ?? '',
        ],
        'trade' => [
            'is_tradable'     => (bool) ( $flags['is_tradable'] ?? 0 ),
            'is_transferable' => (bool) ( $flags['is_transferable'] ?? 0 ),
        ],
        'cp_sets' => [
            'max_cp'        => $cp_sets['max_cp'],
            'min_cp_10'     => $cp_sets['min_cp_10'],
            'min_cp_shadow' => $cp_sets['min_cp_shadow'],
        ],
    ];

    // Meta extra pour la forme de base
    $extra = [
        'pokemon_id_proto'   => $pokemon_id_proto,
        'template_id'        => $template_id,
        'form_proto'         => $form_proto,
        'form_slug'          => $form_slug,
        'type1_proto'        => $type1_proto,
        'type2_proto'        => $type2_proto,

        'quickMoves'         => $settings['quickMoves']         ?? [],
        'cinematicMoves'     => $settings['cinematicMoves']     ?? [],
        'eliteQuickAttack'     => $settings['eliteQuickAttack']     ?? [],
        'eliteCinematicMove' => $settings['eliteCinematicMove'] ?? [],

        'names'              => $names,
        'generation_number'  => $generation_number,
        'game_key'           => $game_key,

        'variant_form_slug'  => $form_slug,
        'variant_id'         => $variant_id,
        'variant_category'   => $variant_category,
        'variant_group'      => $variant_group,
        'form_label_suffix'  => $label_suffix,
        'has_gmax_form'      => $has_gmax_form,

        'gender'             => [
            'male'   => $gender_male,
            'female' => $gender_female,
        ],

        'pokedex'            => [
            'height_m'  => $height_m,
            'weight_kg' => $weight_kg,
        ],

        'buddy'              => [
            'km_buddy_distance'      => $km_buddy_distance,
            'buddy_mega_energy_award'=> $flags['buddy_mega_energy_award'] ?? 0,
        ],

        'encounter'          => [
            'base_capture_rate'  => $base_capture_rate,
            'base_flee_rate'     => $base_flee_rate,
            'attack_probability' => $flags['attack_probability'] ?? 0.0,
            'dodge_probability'  => $flags['dodge_probability'] ?? 0.0,
        ],
        'encounter_raw'      => $encounter,

        'second_move'        => [
            'cost' => $second_attack_cost,
            'raw'  => $third_attack_raw,
        ],

        'shadow'             => [
            'has_shadow'      => (bool) ( $flags['has_shadow'] ?? 0 ),
            'has_purified'    => (bool) ( $flags['has_purified'] ?? 0 ),
            'stardust'        => $flags['shadow_stardust'] ?? 0,
            'candy'           => $flags['shadow_candy'] ?? 0,
            'shadow_move'     => $flags['shadow_move'] ?? '',
            'purified_move'   => $flags['purified_move'] ?? '',
        ],

        'trade'              => [
            'is_tradable'     => (bool) ( $flags['is_tradable'] ?? 0 ),
            'is_transferable' => (bool) ( $flags['is_transferable'] ?? 0 ),
        ],

        'games'              => [
            $game_key => $game_go,
        ],
    ];

    // === TEMP EVO START : on stocke aussi les tempEvoOverrides dans la forme de base ===
    if ( ! empty( $settings['tempEvoOverrides'] ) && is_array( $settings['tempEvoOverrides'] ) ) {
        $extra['temp_evo_overrides'] = $settings['tempEvoOverrides'];
    }
    if ( function_exists( 'poke_hub_pokemon_extract_form_change_rules' ) ) {
        $form_change_rules = poke_hub_pokemon_extract_form_change_rules( $settings );
        if ( ! empty( $form_change_rules ) ) {
            $extra['form_change_rules'] = $form_change_rules;
        }
    }
    // === TEMP EVO END ===

    // Extra existant (mise à jour) : base commune pour release, names, regional, puis fusion profonde
    $gm_existing_extra = [];
    $gm_existing_extra_valid = true;
    $existing_extra_raw = '';
    $existing_release  = [];
    if ( $row ) {
        $existing_extra_raw = (string) ( $row->extra ?? '' );
        if ( function_exists( 'poke_hub_pokemon_decode_extra_json' ) ) {
            $gm_existing_extra = poke_hub_pokemon_decode_extra_json( $existing_extra_raw, $gm_existing_extra_valid );
        } else {
            $dec = json_decode( $existing_extra_raw !== '' ? $existing_extra_raw : '{}', true );
            $gm_existing_extra = is_array( $dec ) ? $dec : [];
            $gm_existing_extra_valid = ( $existing_extra_raw === '' ) || is_array( $dec );
        }
        if ( isset( $gm_existing_extra['release'] ) && is_array( $gm_existing_extra['release'] ) ) {
            $existing_release = $gm_existing_extra['release'];
            if ( ! isset( $extra['release'] ) || ! is_array( $extra['release'] ) ) {
                $extra['release'] = [];
            }
            foreach ( $existing_release as $key => $value ) {
                if ( ! empty( $value ) && ( ! isset( $extra['release'][ $key ] ) || empty( $extra['release'][ $key ] ) ) ) {
                    $extra['release'][ $key ] = $value;
                }
            }
        }
        if ( isset( $extra['names'] ) && is_array( $extra['names'] ) && function_exists( 'poke_hub_pokemon_gm_merge_extra_names_with_existing' ) ) {
            $extra['names'] = poke_hub_pokemon_gm_merge_extra_names_with_existing( $extra['names'], $gm_existing_extra );
        }
    }

    // Mettre à jour automatiquement les flags en fonction des dates de sortie
    $release = $extra['release'] ?? [];
    
    // Si une date de sortie shadow existe, cela signifie que shadow ET purified existent
    if ( ! empty( $release['shadow'] ) ) {
        $flags['has_shadow']   = 1;
        $flags['has_purified'] = 1;
    }

    // Auto-détection des données régionales lors de l'import Game Master
    // Vérifier si ce Pokémon/form devrait être marqué comme régional
    // IMPORTANT: Wrapper dans try/catch pour éviter de bloquer l'import en cas d'erreur
    try {
        if ( function_exists( 'poke_hub_pokemon_should_be_regional_on_import' ) ) {
            $should_be_regional = poke_hub_pokemon_should_be_regional_on_import( $template_id, $form_slug, $pokemon_id_proto );
            
            if ( $should_be_regional ) {
                // Récupérer les pays associés depuis la config auto (fallback si mapping n'existe pas)
                $regional_countries = [];
                if ( function_exists( 'poke_hub_pokemon_get_regional_countries_for_import' ) ) {
                    $regional_countries = poke_hub_pokemon_get_regional_countries_for_import( $template_id, $form_slug, $pokemon_id_proto );
                }
                
                // Source de vérité: pokemon_regional_mappings (countries + region_slugs)
                // Si un mapping existe déjà, on le met à jour avec les données de la config auto si nécessaire
                // Sinon, on crée un nouveau mapping avec les données de la config auto
                if ( function_exists( 'poke_hub_pokemon_get_regional_mapping_by_pattern' ) && !empty( $slug ) ) {
                    $existing_mapping = poke_hub_pokemon_get_regional_mapping_by_pattern( $slug );
                    $existing_mapping_id = !empty( $existing_mapping ) && !empty( $existing_mapping['id'] ) 
                        ? (int) $existing_mapping['id'] 
                        : null;
                    
                    // Si le mapping existe déjà, on garde ses données (priorité sur config auto)
                    // Si le mapping n'existe pas, on crée un nouveau mapping avec les données de la config auto
                    if ( empty( $existing_mapping ) && !empty( $regional_countries ) ) {
                        // Créer un nouveau mapping avec les données de la config auto
                        $mapping_data = [
                            'pattern_slug' => $slug,
                            'countries' => $regional_countries,
                            'region_slugs' => [], // Laissé vide, sera rempli manuellement depuis l'admin
                            'description' => '', // Laissé vide, sera rempli manuellement depuis l'admin
                        ];
                        
                        if ( function_exists( 'poke_hub_pokemon_save_regional_mapping' ) ) {
                            poke_hub_pokemon_save_regional_mapping( $mapping_data, null );
                        }
                    }
                }
                
                // Préserver les données régionales existantes si elles existent déjà
                $existing_regional = [];
                if ( $row && isset( $gm_existing_extra['regional'] ) && is_array( $gm_existing_extra['regional'] ) ) {
                    $existing_regional = $gm_existing_extra['regional'];
                }
                
                // Construire les données régionales
                // Dans extra['regional'], on garde uniquement: is_regional, description, map_image_id.
                $extra['regional'] = [
                    'is_regional'  => true,
                    'description'  => $existing_regional['description'] ?? '',
                    'map_image_id' => isset( $existing_regional['map_image_id'] ) ? (int) $existing_regional['map_image_id'] : 0,
                ];
            }
        }
    } catch ( Exception $e ) {
        // Ne pas définir $extra['regional'] si erreur, on laisse les données existantes intactes
    }

    if ( $row && function_exists( 'poke_hub_pokemon_gm_deep_merge_extra' ) ) {
        $extra = poke_hub_pokemon_gm_deep_merge_extra( $gm_existing_extra, $extra );
    }

    if ( $row && ! $gm_existing_extra_valid ) {
        // JSON existant invalide: on n'écrase jamais le champ extra.
        $extra_json = $existing_extra_raw;
    } elseif ( function_exists( 'poke_hub_pokemon_encode_extra_json' ) ) {
        $extra_json = poke_hub_pokemon_encode_extra_json( $extra, $existing_extra_raw );
    } else {
        $extra_json = wp_json_encode( $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    // Préparer les données pour l'insertion/mise à jour
    $data = [
        'dex_number'      => $dex_number,
        'name_en'         => $names['en'],
        'slug'            => $slug,
        'form_variant_id' => $variant_id,
        'is_default'      => $is_default,

        'generation_id'   => $generation_id,

        'base_atk'        => $base_atk,
        'base_def'        => $base_def,
        'base_sta'        => $base_sta,

        'is_tradable'                       => (int) ( $flags['is_tradable'] ?? 0 ),
        'is_transferable'                   => (int) ( $flags['is_transferable'] ?? 0 ),
        'has_shadow'                        => (int) ( $flags['has_shadow'] ?? 0 ),
        'has_purified'                      => (int) ( $flags['has_purified'] ?? 0 ),
        'shadow_purification_stardust'      => (int) ( $flags['shadow_stardust'] ?? 0 ),
        'shadow_purification_candy'         => (int) ( $flags['shadow_candy'] ?? 0 ),
        'buddy_walked_mega_energy_award'    => (int) ( $flags['buddy_mega_energy_award'] ?? 0 ),
        'dodge_probability'                 => (float) ( $flags['dodge_probability'] ?? 0.0 ),
        'attack_probability'                => (float) ( $flags['attack_probability'] ?? 0.0 ),

        'extra'           => $extra_json,
    ];

    // Ne mettre name_fr que si on a une traduction (pour ne pas écraser avec vide lors de la mise à jour)
    // Pour les nouveaux enregistrements, on peut mettre vide si pas de traduction
    if (!empty($names['fr'])) {
        // Insérer name_fr après name_en dans le bon ordre
        $data_with_fr = [];
        foreach ($data as $key => $value) {
            $data_with_fr[$key] = $value;
            if ($key === 'name_en') {
                $data_with_fr['name_fr'] = $names['fr'];
            }
        }
        $data = $data_with_fr;
    } elseif (!$row) {
        // Nouvel enregistrement : on peut mettre vide
        $data_with_fr = [];
        foreach ($data as $key => $value) {
            $data_with_fr[$key] = $value;
            if ($key === 'name_en') {
                $data_with_fr['name_fr'] = '';
            }
        }
        $data = $data_with_fr;
    }
    // Si $row existe et name_fr est vide, on ne met pas name_fr dans $data pour ne pas écraser
    if ( $row && function_exists( 'poke_hub_pokemon_gm_preserve_manual_pokemon_fields' ) ) {
        $data = poke_hub_pokemon_gm_preserve_manual_pokemon_fields( $data, $row );
    }

    if ( $row && function_exists( 'poke_hub_pokemon_gm_wpdb_data_only_changed_columns' ) ) {
        $data   = poke_hub_pokemon_gm_wpdb_data_only_changed_columns( $data, $row );
        $format = poke_hub_pokemon_gm_wpdb_format_for_pokemon_row( $data );
    } else {
        $format = poke_hub_pokemon_gm_wpdb_format_for_pokemon_row( $data );
    }

    if ( $row ) {
        $pokemon_id = (int) $row->id;
        if ( ! empty( $data ) ) {
            $wpdb->update(
                $pokemon_table,
                $data,
                [ 'id' => $pokemon_id ],
                $format,
                [ '%d' ]
            );
            $stats['pokemon_updated_count'] = ($stats['pokemon_updated_count'] ?? 0) + 1;
            if (count($stats['pokemon_updated_sample'] ?? []) < 50) {
                $stats['pokemon_updated_sample'][] = $names['en'];
            }
        }
    } else {
        $wpdb->insert( $pokemon_table, $data, $format );
        $pokemon_id                  = (int) $wpdb->insert_id;
        $stats['pokemon_inserted_count'] = ($stats['pokemon_inserted_count'] ?? 0) + 1;
        if (count($stats['pokemon_inserted_sample'] ?? []) < 50) {
            $stats['pokemon_inserted_sample'][] = $names['en'];
        }
    }

    // Récupérer automatiquement les traductions Bulbapedia après insertion/mise à jour
    // si des traductions manquent (surtout pour les autres langues que fr)
    // MAIS seulement pour les formes de base, pas pour les formes spéciales (Mega-, Copy, etc.)
    // ET seulement si on n'est PAS en train d'importer le Game Master (pour éviter les ralentissements)
    // DÉSACTIVÉ pendant l'import : les traductions seront récupérées après via l'onglet Translation
    if ( false && $pokemon_id > 0 && $dex_number > 0 && !defined('POKE_HUB_GM_IMPORT_IN_PROGRESS') && function_exists('poke_hub_pokemon_auto_fetch_translations')) {
        // Vérifier si c'est une forme spéciale
        $is_special_form = false;
        $special_form_patterns = [
            '/\bMega\s*-?\s*/i',
            '/\bCopy\b/i',
            '/\bFall\s+\d{4}\b/i',
            '/\bSpring\s+\d{4}\b/i',
            '/\bSummer\s+\d{4}\b/i',
            '/\bWinter\s+\d{4}\b/i',
            '/\bX\b/i',
            '/\bY\b/i',
            '/\bAlola\b/i',
            '/\bGalar\b/i',
            '/\bHisui\b/i',
            '/\bPaldea\b/i',
        ];
        
        foreach ($special_form_patterns as $pattern) {
            if (preg_match($pattern, $names['en'])) {
                $is_special_form = true;
                break;
            }
        }
        
        // Ne pas récupérer depuis Bulbapedia pour les formes spéciales
        if (!$is_special_form) {
            // Vérifier si des traductions manquent
            $needs_fetch = false;
            $allowed_langs = ['fr', 'de', 'it', 'es', 'ja', 'ko'];
            foreach ($allowed_langs as $lang) {
                if (empty($names[$lang]) || $names[$lang] === $names['en']) {
                    $needs_fetch = true;
                    break;
                }
            }

            if ($needs_fetch) {
                // Extraire le nom de base (sans les suffixes de forme spéciale) pour Bulbapedia
                $base_name = $names['en'];
                foreach ($special_form_patterns as $pattern) {
                    $base_name = preg_replace($pattern, '', $base_name);
                }
                $base_name = trim($base_name);
                
                // Récupérer les noms officiels depuis Bulbapedia avec le nom de base
                $official_names = false;
                if (!empty($base_name) && function_exists('poke_hub_pokemon_fetch_official_names_from_bulbapedia')) {
                    $official_names = poke_hub_pokemon_fetch_official_names_from_bulbapedia($dex_number, $base_name);
                }

                if ($official_names !== false && is_array($official_names)) {
                    // Mettre à jour extra['names'] avec les nouvelles traductions
                    $extra_updated = $extra;
                    $update_extra = false;

                    foreach ($official_names as $lang => $name) {
                        if ($lang === 'en') {
                            continue; // Ne pas écraser l'anglais
                        }

                        // Mettre à jour si la traduction est vide ou identique à l'anglais
                        if (empty($names[$lang]) || $names[$lang] === $names['en']) {
                            if (!isset($extra_updated['names']) || !is_array($extra_updated['names'])) {
                                $extra_updated['names'] = [];
                            }
                            $extra_updated['names'][$lang] = $name;
                            $update_extra = true;
                        }
                    }

                    // Mettre à jour name_fr si c'est le français et qu'il était vide
                    $update_name_fr = false;
                    if (isset($official_names['fr']) && !empty($official_names['fr']) && (empty($names['fr']) || $names['fr'] === $names['en'])) {
                        $update_name_fr = true;
                    }

                    // Mettre à jour la base de données si nécessaire
                    if ($update_extra || $update_name_fr) {
                        $update_data = [];
                        $update_format = [];

                        if ($update_extra) {
                            $update_data['extra'] = wp_json_encode($extra_updated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $update_format[] = '%s';
                        }

                        if ($update_name_fr) {
                            $update_data['name_fr'] = $official_names['fr'];
                            $update_format[] = '%s';
                        }

                        if (!empty($update_data)) {
                            $wpdb->update(
                                $pokemon_table,
                                $update_data,
                                ['id' => $pokemon_id],
                                $update_format,
                                ['%d']
                            );
                        }
                    }
                }
            }
        }
    }

    // Index proto → (id, forme) pour évolutions
    $form_key = $form_proto !== '' ? (string) $form_proto : '';
    if ( $pokemon_id > 0 ) {
        if ( ! isset( $pokemon_index[ $pokemon_id_proto ] ) ) {
            $pokemon_index[ $pokemon_id_proto ] = [];
        }
        $pokemon_index[ $pokemon_id_proto ][ $form_key ] = [
            'pokemon_id'      => $pokemon_id,
            'form_variant_id' => $variant_id,
        ];
    }

    // Lier les types
    $type_ids = [];
    if ( $type1_slug !== '' ) {
        $type_ids[] = poke_hub_pokemon_find_or_create_type( $type1_slug, ucfirst( $type1_slug ) );
    }
    if ( $type2_slug !== '' && $type2_slug !== $type1_slug ) {
        $type_ids[] = poke_hub_pokemon_find_or_create_type( $type2_slug, ucfirst( $type2_slug ) );
    }

    if ( $pokemon_id > 0 && ! empty( $type_ids ) ) {
        poke_hub_pokemon_sync_pokemon_types_links( $pokemon_id, $type_ids, false );
        $stats['pokemon_type_links'] += count( $type_ids );
    }

    // Liens Pokémon ↔ Attaques
    $links = [];
    if ( $pokemon_id > 0 && ! empty( $tables['pokemon_attack_links'] ) && ! empty( $tables['attacks'] ) ) {
        // Support des deux formats : ancien (quickAttacks) et nouveau (quickMoves)
        $quick_moves = [];
        if ( isset($settings['quickMoves']) && is_array($settings['quickMoves']) ) {
            $quick_moves = $settings['quickMoves'];
        } elseif ( isset($settings['quickAttacks']) && is_array($settings['quickAttacks']) ) {
            $quick_moves = $settings['quickAttacks'];
        }
        
        $cinematic_moves = [];
        if ( isset($settings['cinematicMoves']) && is_array($settings['cinematicMoves']) ) {
            $cinematic_moves = $settings['cinematicMoves'];
        } elseif ( isset($settings['cinematicAttacks']) && is_array($settings['cinematicAttacks']) ) {
            $cinematic_moves = $settings['cinematicAttacks'];
        }
        
        $elite_quick = [];
        if ( isset($settings['eliteQuickAttack']) && is_array($settings['eliteQuickAttack']) ) {
            $elite_quick = $settings['eliteQuickAttack'];
        } elseif ( isset($settings['eliteQuickMove']) && is_array($settings['eliteQuickMove']) ) {
            $elite_quick = $settings['eliteQuickMove'];
        } elseif ( isset($settings['eliteQuickMoves']) && is_array($settings['eliteQuickMoves']) ) {
            $elite_quick = $settings['eliteQuickMoves'];
        }
        
        $elite_cinematic = [];
        if ( isset($settings['eliteCinematicMove']) && is_array($settings['eliteCinematicMove']) ) {
            $elite_cinematic = $settings['eliteCinematicMove'];
        } elseif ( isset($settings['eliteCinematicAttack']) && is_array($settings['eliteCinematicAttack']) ) {
            $elite_cinematic = $settings['eliteCinematicAttack'];
        } elseif ( isset($settings['eliteCinematicMoves']) && is_array($settings['eliteCinematicMoves']) ) {
            $elite_cinematic = $settings['eliteCinematicMoves'];
        }        

        $attack_links_map = [];

        $add_link = static function( $attack_id, $role, $is_legacy = 0, $is_event = 0, $is_elite_tm = 0, $extra_data = null ) use ( &$attack_links_map ) {
            $attack_id = (int) $attack_id;
            if ( $attack_id <= 0 || $role === '' ) {
                return;
            }
            $key = $attack_id . '|' . $role;
            if ( ! isset( $attack_links_map[ $key ] ) ) {
                $attack_links_map[ $key ] = [
                    'attack_id'   => $attack_id,
                    'role'        => $role,
                    'is_legacy'   => 0,
                    'is_event'    => 0,
                    'is_elite_tm' => 0,
                    'extra'       => null,
                ];
            }
            if ( $is_legacy ) {
                $attack_links_map[ $key ]['is_legacy'] = 1;
            }
            if ( $is_event ) {
                $attack_links_map[ $key ]['is_event'] = 1;
            }
            if ( $is_elite_tm ) {
                $attack_links_map[ $key ]['is_elite_tm'] = 1;
            }
            if ( $extra_data !== null ) {
                $attack_links_map[ $key ]['extra'] = is_array( $extra_data ) ? wp_json_encode( $extra_data ) : $extra_data;
            }
        };

        foreach ( $quick_moves as $attack_id ) {
            $slug_move = poke_hub_pokemon_gm_id_to_slug( $attack_id );
            $attack_id = poke_hub_pokemon_get_attack_id_by_slug( $slug_move, $tables );
            if ( $attack_id > 0 ) {
                $add_link( $attack_id, 'fast', 0, 0, 0 );
            }
        }

        foreach ( $elite_quick as $attack_id ) {
            $slug_move = poke_hub_pokemon_gm_id_to_slug( $attack_id );
            $attack_id = poke_hub_pokemon_get_attack_id_by_slug( $slug_move, $tables );
            if ( $attack_id > 0 ) {
                $add_link( $attack_id, 'fast', 1, 1, 1 );
            }
        }

        foreach ( $cinematic_moves as $attack_id ) {
            $slug_move = poke_hub_pokemon_gm_id_to_slug( $attack_id );
            $attack_id = poke_hub_pokemon_get_attack_id_by_slug( $slug_move, $tables );
            if ( $attack_id > 0 ) {
                $add_link( $attack_id, 'charged', 0, 0, 0 );
            }
        }

        foreach ( $elite_cinematic as $attack_id ) {
            $slug_move = poke_hub_pokemon_gm_id_to_slug( $attack_id );
            $attack_id = poke_hub_pokemon_get_attack_id_by_slug( $slug_move, $tables );
            if ( $attack_id > 0 ) {
                $add_link( $attack_id, 'charged', 1, 1, 1 );
            }
        }

        // Attaques spéciales shadow (FRUSTRATION) - uniquement obtenable via capture shadow
        if ( ! empty( $flags['shadow_move'] ) ) {
            $slug_move = poke_hub_pokemon_gm_id_to_slug( $flags['shadow_move'] );
            $attack_id = poke_hub_pokemon_get_attack_id_by_slug( $slug_move, $tables );
            if ( $attack_id > 0 ) {
                // Marquer dans extra comme attaque shadow (ni legacy, ni event)
                $add_link( $attack_id, 'charged', 0, 0, 0, [ 'is_shadow' => true, 'source' => 'shadow_capture' ] );
            }
        }

        // Attaques spéciales purified (RETURN) - uniquement obtenable via purification
        if ( ! empty( $flags['purified_move'] ) ) {
            $slug_move = poke_hub_pokemon_gm_id_to_slug( $flags['purified_move'] );
            $attack_id = poke_hub_pokemon_get_attack_id_by_slug( $slug_move, $tables );
            if ( $attack_id > 0 ) {
                // Marquer dans extra comme attaque purified (ni legacy, ni event)
                $add_link( $attack_id, 'charged', 0, 0, 0, [ 'is_purified' => true, 'source' => 'purification' ] );
            }
        }

        $links = array_values( $attack_links_map );
        if ( ! empty( $links ) ) {
            poke_hub_pokemon_sync_pokemon_attack_links( $pokemon_id, $links, $tables, false );
            $stats['pokemon_attack_links'] = ( $stats['pokemon_attack_links'] ?? 0 ) + count( $links );
        }
    }

    /**
     * === TEMP EVO START ===
     * Création des Pokémon Méga / Primo à partir de tempEvoOverrides.
     *
     * Objectif:
     * - Ne créer les Méga/Primo QUE depuis la forme de base (évite les doublons quand plusieurs pokemonSettings
     *   portent tempEvoOverrides: costumes, variantes, etc.)
     * - Anti-doublon intra-run (si jamais on repasse quand même plusieurs fois)
     *
     * - Slugs : mega-charizard-x, primal-kyogre, etc.
     * - Stats dédiées (overrides), CP sets recalculés.
     * - Types override (typeOverride1/2).
     * - Liaison types + attaques.
     * - Index dans $pokemon_index pour être utilisable par PASS 3 (évolutions).
     */
    if (
        $pokemon_id > 0
        && (int) $is_default === 1
        && empty( $form_slug )
        && ! empty( $settings['tempEvoOverrides'] )
        && is_array( $settings['tempEvoOverrides'] )
    ) {
        // Anti-doublon intra-run (clé = mega_slug)
        static $temp_evo_created = [];

        foreach ( $settings['tempEvoOverrides'] as $temp_evo ) {
            if ( ! is_array( $temp_evo ) ) {
                continue;
            }

            $temp_evo_id = (string) ( $temp_evo['tempEvoId'] ?? '' );
            if ( $temp_evo_id === '' ) {
                continue;
            }

            $temp_form_slug  = poke_hub_pokemon_temp_evo_id_to_form_slug( $temp_evo_id );
            $temp_form_label = poke_hub_pokemon_temp_evo_id_to_label( $temp_evo_id );

            // Construction du slug global : mega-charizard-x, primal-kyogre, etc.
            $parts  = explode( '-', $temp_form_slug );
            $prefix = $parts[0] ?? '';
            $suffix = $parts[1] ?? '';

            if ( $prefix === '' ) {
                continue;
            }

            $mega_slug = ( $suffix !== '' )
                ? $prefix . '-' . $slug_base . '-' . $suffix
                : $prefix . '-' . $slug_base;

            // DEDUPE intra-run
            if ( isset( $temp_evo_created[ $mega_slug ] ) ) {
                continue;
            }
            $temp_evo_created[ $mega_slug ] = true;

            // Catégorie de variante
            $mega_variant_category = 'special';
            if ( poke_hub_pokemon_is_temp_evo_mega( $temp_evo_id ) ) {
                $mega_variant_category = 'mega';
            }

            // Upsert variant (form_slug = mega, mega-x, primal, ...)
            $mega_variant_id = 0;
            if ( function_exists( 'poke_hub_pokemon_get_form_variant_by_slug' ) ) {
                $existing_mega_variant = poke_hub_pokemon_get_form_variant_by_slug( $temp_form_slug );
                if ( is_array( $existing_mega_variant ) && ! empty( $existing_mega_variant['id'] ) ) {
                    $mega_variant_id = (int) $existing_mega_variant['id'];
                }
            }
            if ( $mega_variant_id <= 0 && function_exists( 'poke_hub_pokemon_upsert_form_variant' ) ) {
                $mega_variant_id = poke_hub_pokemon_upsert_form_variant(
                    $temp_form_slug,
                    $temp_form_label,
                    $mega_variant_category,
                    '',
                    [
                        'pokemon_id_proto' => $pokemon_id_proto,
                        'temp_evo_id'      => $temp_evo_id,
                        'template_id'      => $template_id,
                    ]
                );
            }

            $mega_variant_row = poke_hub_pokemon_get_form_variant_by_slug( $temp_form_slug );
            if ( is_array( $mega_variant_row ) ) {
                $mega_variant_category = $mega_variant_row['category'] ?? $mega_variant_category;
                $mega_variant_group    = $mega_variant_row['group'] ?? '';
            } else {
                $mega_variant_group    = '';
            }

            // Override des stats
            $mega_stats = $temp_evo['stats'] ?? [];
            $mega_atk   = isset( $mega_stats['baseAttack'] )  ? (int) $mega_stats['baseAttack']  : $base_atk;
            $mega_def   = isset( $mega_stats['baseDefense'] ) ? (int) $mega_stats['baseDefense'] : $base_def;
            $mega_sta   = isset( $mega_stats['baseStamina'] ) ? (int) $mega_stats['baseStamina'] : $base_sta;

            // Types override
            $mega_type1_proto = $temp_evo['typeOverride1'] ?? $type1_proto;
            $mega_type2_proto = $temp_evo['typeOverride2'] ?? $type2_proto;

            $mega_type1_slug = poke_hub_pokemon_gm_type_proto_to_slug( $mega_type1_proto );
            $mega_type2_slug = poke_hub_pokemon_gm_type_proto_to_slug( $mega_type2_proto );

            // Pokedex overrides
            $mega_height_m  = isset( $temp_evo['averageHeightM'] ) ? (float) $temp_evo['averageHeightM'] : $height_m;
            $mega_weight_kg = isset( $temp_evo['averageWeightKg'] ) ? (float) $temp_evo['averageWeightKg'] : $weight_kg;

            // CP sets Méga
            $mega_cp_sets = [
                'max_cp'        => [],
                'min_cp_10'     => [],
                'min_cp_shadow' => [],
            ];
            if (
                function_exists( 'poke_hub_pokemon_build_cp_sets_for_pokemon' )
                && $mega_atk > 0 && $mega_def > 0 && $mega_sta > 0
            ) {
                $mega_cp_sets = poke_hub_pokemon_build_cp_sets_for_pokemon(
                    $mega_atk,
                    $mega_def,
                    $mega_sta
                );
            }

            // Nom / i18n
            $mega_label = $temp_form_label . ' ' . $base_name;
            $mega_names = poke_hub_pokemon_get_i18n_names( 'pokemon', $mega_slug, $mega_label );

            // Game data pour la Méga / Primo
            $mega_game_go = $game_go;
            $mega_game_go['pokedex']['height_m']  = $mega_height_m;
            $mega_game_go['pokedex']['weight_kg'] = $mega_weight_kg;
            $mega_game_go['cp_sets']              = $mega_cp_sets;

            $mega_extra = [
                'pokemon_id_proto'   => $pokemon_id_proto,
                'template_id'        => $template_id,
                'form_proto'         => $temp_evo_id,
                'form_slug'          => $temp_form_slug,
                'type1_proto'        => $mega_type1_proto,
                'type2_proto'        => $mega_type2_proto,

                'quickMoves'         => $settings['quickMoves']     ?? [],
                'cinematicMoves'     => $settings['cinematicMoves'] ?? [],
                'eliteQuickMoves'    => $elite_quick,
                'eliteCinematicMoves'=> $elite_cinematic,

                'names'              => $mega_names,
                'generation_number'  => $generation_number,
                'game_key'           => $game_key,

                'variant_form_slug'  => $temp_form_slug,
                'variant_id'         => $mega_variant_id,
                'variant_category'   => $mega_variant_category,
                'variant_group'      => $mega_variant_group,
                'form_label_suffix'  => $temp_form_label,
                'has_gmax_form'      => $has_gmax_form,

                'gender'             => [
                    'male'   => $gender_male,
                    'female' => $gender_female,
                ],

                'pokedex'            => [
                    'height_m'  => $mega_height_m,
                    'weight_kg' => $mega_weight_kg,
                ],

                'buddy'              => [
                    'km_buddy_distance'       => $km_buddy_distance,
                    'buddy_mega_energy_award' => $flags['buddy_mega_energy_award'] ?? 0,
                ],

                'encounter'          => $extra['encounter'],
                'encounter_raw'      => $encounter,

                'second_move'        => $extra['second_move'],
                'shadow'             => $extra['shadow'],
                'trade'              => $extra['trade'],

                'games'              => [
                    $game_key => $mega_game_go,
                ],

                'temp_evolution'     => [
                    'temp_evo_id'            => $temp_evo_id,
                    'energy_cost'            => (int) ( $temp_evo['temporaryEvolutionEnergyCost'] ?? 0 ),
                    'energy_cost_subsequent' => (int) ( $temp_evo['temporaryEvolutionEnergyCostSubsequent'] ?? 0 ),
                ],
            ];

            // Ligne Méga / Primo existante ? D’abord par slug attendu ; sinon par dex + variante
            // (lignes corrompues slug=0 après ancien bug de formats $wpdb ne matchent pas le 1er critère).
            $mega_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$pokemon_table} WHERE slug = %s LIMIT 1",
                    $mega_slug
                )
            );
            if ( ! $mega_row && $mega_variant_id > 0 ) {
                $mega_row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$pokemon_table} WHERE dex_number = %d AND form_variant_id = %d AND is_default = 0 LIMIT 1",
                        $dex_number,
                        $mega_variant_id
                    )
                );
            }

            $mega_existing_decoded = [];
            $mega_existing_extra_valid = true;
            $mega_existing_extra_raw = '';
            if ( $mega_row ) {
                $mega_existing_extra_raw = (string) ( $mega_row->extra ?? '' );
                if ( function_exists( 'poke_hub_pokemon_decode_extra_json' ) ) {
                    $mega_existing_decoded = poke_hub_pokemon_decode_extra_json( $mega_existing_extra_raw, $mega_existing_extra_valid );
                } else {
                    $md = json_decode( $mega_existing_extra_raw !== '' ? $mega_existing_extra_raw : '{}', true );
                    $mega_existing_decoded = is_array( $md ) ? $md : [];
                    $mega_existing_extra_valid = ( $mega_existing_extra_raw === '' ) || is_array( $md );
                }
            }
            if ( $mega_row && function_exists( 'poke_hub_pokemon_gm_merge_extra_names_with_existing' )
                && isset( $mega_extra['names'] ) && is_array( $mega_extra['names'] ) ) {
                $mega_extra['names'] = poke_hub_pokemon_gm_merge_extra_names_with_existing( $mega_extra['names'], $mega_existing_decoded );
            }
            if ( $mega_row && function_exists( 'poke_hub_pokemon_gm_deep_merge_extra' ) ) {
                $mega_extra = poke_hub_pokemon_gm_deep_merge_extra( $mega_existing_decoded, $mega_extra );
            }

            $mega_extra_json = null;
            if ( $mega_row && ! $mega_existing_extra_valid ) {
                $mega_extra_json = $mega_existing_extra_raw;
            } elseif ( function_exists( 'poke_hub_pokemon_encode_extra_json' ) ) {
                $mega_extra_json = poke_hub_pokemon_encode_extra_json( $mega_extra, $mega_existing_extra_raw );
            } else {
                $mega_extra_json = wp_json_encode( $mega_extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            }

            $mega_data = [
                'dex_number'      => $dex_number,
                'name_en'         => $mega_names['en'],
                'name_fr'         => $mega_names['fr'],
                'slug'            => $mega_slug,
                'form_variant_id' => $mega_variant_id,
                'is_default'      => 0,

                'generation_id'   => $generation_id,

                'base_atk'        => $mega_atk,
                'base_def'        => $mega_def,
                'base_sta'        => $mega_sta,

                'is_tradable'                    => (int) ( $flags['is_tradable'] ?? 0 ),
                'is_transferable'                => (int) ( $flags['is_transferable'] ?? 0 ),
                'has_shadow'                     => (int) ( $flags['has_shadow'] ?? 0 ),
                'has_purified'                   => (int) ( $flags['has_purified'] ?? 0 ),
                'shadow_purification_stardust'   => (int) ( $flags['shadow_stardust'] ?? 0 ),
                'shadow_purification_candy'      => (int) ( $flags['shadow_candy'] ?? 0 ),
                'buddy_walked_mega_energy_award' => (int) ( $flags['buddy_mega_energy_award'] ?? 0 ),
                'dodge_probability'              => (float) ( $flags['dodge_probability'] ?? 0.0 ),
                'attack_probability'             => (float) ( $flags['attack_probability'] ?? 0.0 ),

                'extra'          => $mega_extra_json,
            ];
            if ( $mega_row && empty( $mega_names['fr'] ) && ! empty( $mega_row->name_fr ) ) {
                // Préserver un FR manuel existant si l'import n'en fournit pas.
                unset( $mega_data['name_fr'] );
            }
            if ( $mega_row && function_exists( 'poke_hub_pokemon_gm_preserve_manual_pokemon_fields' ) ) {
                $mega_data = poke_hub_pokemon_gm_preserve_manual_pokemon_fields( $mega_data, $mega_row );
            }

            // Ne pas réutiliser $format de la forme de base : l’ordre des colonnes diffère
            // (ex. sans name_fr sur update) → slug recevait %d et devenait 0 en base.
            if ( $mega_row && function_exists( 'poke_hub_pokemon_gm_wpdb_data_only_changed_columns' ) ) {
                $mega_data_to_write = poke_hub_pokemon_gm_wpdb_data_only_changed_columns( $mega_data, $mega_row );
                $mega_format        = poke_hub_pokemon_gm_wpdb_format_for_pokemon_row( $mega_data_to_write );
            } else {
                $mega_data_to_write = $mega_data;
                $mega_format        = poke_hub_pokemon_gm_wpdb_format_for_pokemon_row( $mega_data );
            }

            if ( $mega_row ) {
                $mega_pokemon_id = (int) $mega_row->id;
                if ( ! empty( $mega_data_to_write ) ) {
                    $wpdb->update(
                        $pokemon_table,
                        $mega_data_to_write,
                        [ 'id' => $mega_pokemon_id ],
                        $mega_format,
                        [ '%d' ]
                    );
                    $stats['pokemon_updated_count'] = ( $stats['pokemon_updated_count'] ?? 0 ) + 1;
                    if ( count( $stats['pokemon_updated_sample'] ?? [] ) < 50 ) {
                        $stats['pokemon_updated_sample'][] = $mega_names['en'];
                    }
                }
            } else {
                $wpdb->insert( $pokemon_table, $mega_data, poke_hub_pokemon_gm_wpdb_format_for_pokemon_row( $mega_data ) );
                $mega_pokemon_id                 = (int) $wpdb->insert_id;
                $stats['pokemon_inserted_count'] = ( $stats['pokemon_inserted_count'] ?? 0 ) + 1;
                if ( count( $stats['pokemon_inserted_sample'] ?? [] ) < 50 ) {
                    $stats['pokemon_inserted_sample'][] = $mega_names['en'];
                }
            }

            // Index proto+tempEvoId → (pokemon_id, variant)
            if ( $mega_pokemon_id > 0 ) {
                if ( ! isset( $pokemon_index[ $pokemon_id_proto ] ) ) {
                    $pokemon_index[ $pokemon_id_proto ] = [];
                }
                $pokemon_index[ $pokemon_id_proto ][ $temp_evo_id ] = [
                    'pokemon_id'      => $mega_pokemon_id,
                    'form_variant_id' => $mega_variant_id,
                ];
            }

            // Types Méga / Primo
            $mega_type_ids = [];
            if ( $mega_type1_slug !== '' ) {
                $mega_type_ids[] = poke_hub_pokemon_find_or_create_type( $mega_type1_slug, ucfirst( $mega_type1_slug ) );
            }
            if ( $mega_type2_slug !== '' && $mega_type2_slug !== $mega_type1_slug ) {
                $mega_type_ids[] = poke_hub_pokemon_find_or_create_type( $mega_type2_slug, ucfirst( $mega_type2_slug ) );
            }

            if ( $mega_pokemon_id > 0 && ! empty( $mega_type_ids ) ) {
                poke_hub_pokemon_sync_pokemon_types_links( $mega_pokemon_id, $mega_type_ids, false );
                $stats['pokemon_type_links'] += count( $mega_type_ids );
            }

            // Liens attaques → mêmes moves que la forme de base
            if (
                $mega_pokemon_id > 0
                && ! empty( $tables['pokemon_attack_links'] )
                && ! empty( $tables['attacks'] )
                && ! empty( $links )
            ) {
                poke_hub_pokemon_sync_pokemon_attack_links( $mega_pokemon_id, $links, $tables, false );
                $stats['pokemon_attack_links'] = ( $stats['pokemon_attack_links'] ?? 0 ) + count( $links );
            }
        }
    }
    /**
    * === TEMP EVO END ===
    */

    return $stats;
}

/**
 * Upsert non destructif d'une ligne attack_stats pour un context donné.
 * Préserve l'extra existant si l'import n'en fournit pas.
 */
function poke_hub_pokemon_gm_upsert_attack_stat_context( string $stats_table, int $attack_id, string $context, array $payload ): void {
    global $wpdb;

    if ( $attack_id <= 0 || $stats_table === '' ) {
        return;
    }

    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, extra FROM {$stats_table} WHERE attack_id = %d AND game_key = %s AND context = %s LIMIT 1",
            $attack_id,
            'pokemon_go',
            $context
        )
    );

    if ( $existing ) {
        if ( array_key_exists( 'extra', $payload ) ) {
            if ( $payload['extra'] === null && ! empty( $existing->extra ) ) {
                $payload['extra'] = $existing->extra;
            } elseif ( is_string( $payload['extra'] ) && $payload['extra'] !== '' && ! empty( $existing->extra ) ) {
                $old_extra = json_decode( (string) $existing->extra, true );
                $new_extra = json_decode( (string) $payload['extra'], true );
                if ( is_array( $old_extra ) && is_array( $new_extra ) ) {
                    $payload['extra'] = wp_json_encode(
                        array_replace_recursive( $old_extra, $new_extra ),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                }
            }
        }

        $wpdb->update(
            $stats_table,
            $payload,
            [ 'id' => (int) $existing->id ],
            [ '%d', '%f', '%f', '%d', '%d', '%d', '%d', '%s' ],
            [ '%d' ]
        );
        return;
    }

    $wpdb->insert(
        $stats_table,
        array_merge(
            [
                'attack_id' => $attack_id,
                'game_key'  => 'pokemon_go',
                'context'   => $context,
            ],
            $payload
        ),
        [
            '%d', '%s', '%s',
            '%d', '%f', '%f',
            '%d', '%d', '%d',
            '%d', '%s',
        ]
    );
}

/**
 * Importe / met à jour UN move PvE à partir de moveSettings.
 *
 * Remplit :
 * - attacks (slug, name_en, name_fr, category = fast/charged, extra),
 * - attack_stats (stats globales avec context='', stats PvE avec context='pve'),
 * - attack_type_links.
 *
 * Les stats globales (duration, damage_window) sont stockées avec context=''.
 * Les stats PvE (damage, dps, eps, energy) sont stockées avec context='pve'.
 *
 * @param string $template_id
 * @param array  $settings
 * @param array  $tables
 * @param array  $stats
 * @param array  $seen_attack_slugs Tableau de slugs de moves vus (passé par référence)
 * @return array
 */
function poke_hub_pokemon_import_from_attack_settings( $template_id, array $settings, array $tables, array $stats, array &$seen_attack_slugs ) {
    global $wpdb;

    $attacks_table = $tables['attacks'];
    $stats_table   = $tables['attack_stats'];

    $movement_id = $settings['movementId'] ?? '';
    if ( $movement_id === '' ) {
        return $stats;
    }

    // Catégorie globale du move : fast / charged / gmax
    $is_gmax_move = ( strpos( (string) $movement_id, 'VN_BM_' ) === 0 );

    // Pour les attaques GMAX, on utilise vfxName comme source lisible
    // (ex: gmax_chistrike) au lieu du code interne VN_BM_xxx.
    $slug_source  = $movement_id;
    $label_source = $movement_id;
    if ( $is_gmax_move ) {
        $vfx_name = trim( (string) ( $settings['vfxName'] ?? '' ) );
        if ( $vfx_name !== '' ) {
            $slug_source  = $vfx_name;
            $label_source = str_replace( '_', ' ', strtoupper( $vfx_name ) );
            $label_source = str_ireplace( 'GMAX', 'G-Max', $label_source );
            $label_source = ucwords( strtolower( $label_source ) );
        }
    }

    $slug          = poke_hub_pokemon_gm_id_to_slug( $slug_source );
    $default_label = poke_hub_pokemon_gm_id_to_label( $label_source );
    $names         = poke_hub_pokemon_get_i18n_names( 'moves', $slug, $default_label );

    // On mémorise le slug comme "vu" (statistique uniquement)
    $seen_attack_slugs[$slug] = true;

    $type_proto = $settings['pokemonType'] ?? '';
    $type_slug  = poke_hub_pokemon_gm_type_proto_to_slug( $type_proto );

    $power                  = isset( $settings['power'] ) ? (int) $settings['power'] : 0;
    $duration_ms            = isset( $settings['durationMs'] ) ? (int) $settings['durationMs'] : 0;
    $energy_delta           = isset( $settings['energyDelta'] ) ? (int) $settings['energyDelta'] : 0;
    $damage_window_start_ms = isset( $settings['damageWindowStartMs'] ) ? (int) $settings['damageWindowStartMs'] : 0;
    $damage_window_end_ms   = isset( $settings['damageWindowEndMs'] ) ? (int) $settings['damageWindowEndMs'] : 0;

    $duration_s = ( $duration_ms > 0 ) ? ( $duration_ms / 1000.0 ) : 0.0;
    $dps        = ( $duration_s > 0 && $power > 0 ) ? ( $power / $duration_s ) : 0.0;
    $eps        = ( $duration_s > 0 && 0 !== $energy_delta ) ? ( $energy_delta / $duration_s ) : 0.0;

    if ( $is_gmax_move ) {
        $category = 'gmax';
    } else {
        $category = ( substr( $movement_id, -5 ) === '_FAST' ) ? 'fast' : 'charged';
    }

    // Cherche attaque existante
    $row = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$attacks_table} WHERE slug = %s LIMIT 1", $slug )
    );

    $extra = [
        'movement_id' => $movement_id,
        'template_id' => $template_id,
        'game_key'    => 'pokemon_go',
        'names'       => $names,
    ];
    $attack_existing_decoded = [];
    $attack_existing_extra_valid = true;
    $attack_existing_extra_raw = '';
    if ( $row ) {
        $attack_existing_extra_raw = (string) ( $row->extra ?? '' );
        if ( function_exists( 'poke_hub_pokemon_decode_extra_json' ) ) {
            $attack_existing_decoded = poke_hub_pokemon_decode_extra_json( $attack_existing_extra_raw, $attack_existing_extra_valid );
        } else {
            $ad = json_decode( $attack_existing_extra_raw !== '' ? $attack_existing_extra_raw : '{}', true );
            $attack_existing_decoded = is_array( $ad ) ? $ad : [];
            $attack_existing_extra_valid = ( $attack_existing_extra_raw === '' ) || is_array( $ad );
        }
        if ( function_exists( 'poke_hub_pokemon_gm_merge_extra_names_with_existing' ) ) {
            $extra['names'] = poke_hub_pokemon_gm_merge_extra_names_with_existing( $extra['names'], $attack_existing_decoded );
        }
        if ( function_exists( 'poke_hub_pokemon_gm_deep_merge_extra' ) ) {
            $extra = poke_hub_pokemon_gm_deep_merge_extra( $attack_existing_decoded, $extra );
        }
    }
    if ( $row && ! $attack_existing_extra_valid ) {
        $extra_json = $attack_existing_extra_raw;
    } elseif ( function_exists( 'poke_hub_pokemon_encode_extra_json' ) ) {
        $extra_json = poke_hub_pokemon_encode_extra_json( $extra, $attack_existing_extra_raw );
    } else {
        $extra_json = wp_json_encode( $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    $attack_data = [
        'slug'     => $slug,
        'name_en'  => $names['en'],
        'name_fr'  => $names['fr'],
        'category' => $category,
        'extra'    => $extra_json,
    ];
    if ( $row && empty( $names['fr'] ) && ! empty( $row->name_fr ) ) {
        // Préserver un FR manuel existant si l'import n'en fournit pas.
        unset( $attack_data['name_fr'] );
    }
    if ( $row && function_exists( 'poke_hub_pokemon_gm_wpdb_data_only_changed_columns' ) ) {
        $attack_data   = poke_hub_pokemon_gm_wpdb_data_only_changed_columns( $attack_data, $row );
        $attack_format = poke_hub_pokemon_gm_wpdb_format_attack_row( $attack_data );
    } else {
        $attack_format = [ '%s', '%s', '%s', '%s', '%s' ];
    }

    if ( $row ) {
        $attack_id = (int) $row->id;
        if ( ! empty( $attack_data ) ) {
            $wpdb->update(
                $attacks_table,
                $attack_data,
                [ 'id' => $attack_id ],
                $attack_format,
                [ '%d' ]
            );
            $stats['attacks_updated_count'] = ($stats['attacks_updated_count'] ?? 0) + 1;
            if (count($stats['attacks_updated_sample'] ?? []) < 50) {
                $stats['attacks_updated_sample'][] = $names['en'];
            }
        }
    } else {
        $wpdb->insert( $attacks_table, $attack_data, $attack_format );
        $attack_id                 = (int) $wpdb->insert_id;
        $stats['attacks_inserted_count'] = ($stats['attacks_inserted_count'] ?? 0) + 1;
        if (count($stats['attacks_inserted_sample'] ?? []) < 50) {
            $stats['attacks_inserted_sample'][] = $names['en'];
        }
    }

    if ( $attack_id <= 0 || empty( $stats_table ) ) {
        return $stats;
    }

    // Stats globales (duration, damage_window uniquement) - context = ""
    poke_hub_pokemon_gm_upsert_attack_stat_context(
        $stats_table,
        $attack_id,
        '',
        [
            'damage'                 => 0,
            'dps'                    => 0.0,
            'eps'                    => 0.0,
            'duration_ms'            => $duration_ms,
            'damage_window_start_ms' => $damage_window_start_ms,
            'damage_window_end_ms'   => $damage_window_end_ms,
            'energy'                 => 0,
            'extra'                  => null,
        ],
    );

    // Stats PvE (damage, dps, eps, energy) - context = "pve"
    poke_hub_pokemon_gm_upsert_attack_stat_context(
        $stats_table,
        $attack_id,
        'pve',
        [
            'damage'                 => $power,
            'dps'                    => $dps,
            'eps'                    => $eps,
            'duration_ms'            => 0,
            'damage_window_start_ms' => 0,
            'damage_window_end_ms'   => 0,
            'energy'                 => $energy_delta,
            'extra'                  => null,
        ],
    );

    $stats['pve_stats']++;

    // Liaison type ↔ move
    if ( $type_slug !== '' ) {
        $type_id = poke_hub_pokemon_find_or_create_type( $type_slug, ucfirst( $type_slug ) );
        if ( $type_id > 0 ) {
            if ( function_exists( 'poke_hub_pokemon_import_sync_attack_types_links_non_destructive' ) ) {
                poke_hub_pokemon_import_sync_attack_types_links_non_destructive( $attack_id, [ $type_id ] );
            } else {
                poke_hub_pokemon_sync_attack_types_links( $attack_id, [ $type_id ] );
            }
            $stats['attack_type_links']++;
        }
    }

    return $stats;
}

/**
 * Importe / met à jour UN move PvP à partir de combatMove.
 *
 * Remplit uniquement attack_stats (context='pvp' avec damage, dps, eps, energy) et met à jour les noms,
 * sans toucher à category (déjà décidé côté PvE).
 *
 * Les stats globales (duration, damage_window) sont gérées par moveSettings.
 * Si combatMove contient durationTurns, la durée PvP est stockée dans duration_ms des stats PvP.
 * Les buffs/debuffs sont stockés dans le champ extra au format JSON.
 *
 * @param string $template_id
 * @param array  $combat_move
 * @param array  $tables
 * @param array  $stats
 * @param array  $seen_attack_slugs Tableau de slugs de moves vus (passé par référence)
 * @return array
 */
function poke_hub_pokemon_import_from_combat_move( $template_id, array $combat_move, array $tables, array $stats, array &$seen_attack_slugs ) {
    global $wpdb;

    $attacks_table = $tables['attacks'];
    $stats_table   = $tables['attack_stats'];

    $unique_id = $combat_move['uniqueId'] ?? '';
    if ( $unique_id === '' ) {
        return $stats;
    }

    $slug          = poke_hub_pokemon_gm_id_to_slug( $unique_id );
    $default_label = poke_hub_pokemon_gm_id_to_label( $unique_id );
    $names         = poke_hub_pokemon_get_i18n_names( 'moves', $slug, $default_label );

    // On mémorise le slug comme "vu" (statistique uniquement)
    $seen_attack_slugs[$slug] = true;

    $type_proto = $combat_move['type'] ?? '';
    $type_slug  = poke_hub_pokemon_gm_type_proto_to_slug( $type_proto );

    $power        = isset( $combat_move['power'] ) ? (int) $combat_move['power'] : 0;
    $energy_delta = isset( $combat_move['energyDelta'] ) ? (int) $combat_move['energyDelta'] : 0;
    $turns        = isset( $combat_move['durationTurns'] ) ? (int) $combat_move['durationTurns'] : 0;

    // Cherche attaque existante
    $row = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$attacks_table} WHERE slug = %s LIMIT 1", $slug )
    );

    // Calcul de la durée PvP : on vérifie d'abord si durationTurns est présent dans combatMove
    // Si oui, on utilise cette valeur (qui peut différer de la durée globale)
    // Sinon, on utilise la durée globale depuis moveSettings
    $duration_s_pvp = 0.0;
    $duration_ms_pvp = 0;
    $global_duration_ms = 0;

    // Récupération de la durée globale si l'attaque existe déjà
    if ( $row && ! empty( $stats_table ) ) {
        $existing_global_stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT duration_ms FROM {$stats_table} 
                 WHERE attack_id = %d AND game_key = %s AND context = %s LIMIT 1",
                (int) $row->id,
                'pokemon_go',
                ''
            )
        );
        if ( $existing_global_stats && $existing_global_stats->duration_ms > 0 ) {
            $global_duration_ms = (int) $existing_global_stats->duration_ms;
        }
    }

    // Si durationTurns est présent, on calcule la durée PvP spécifique
    // Il faut donc ajouter 1 pour obtenir la durée correcte
    // Source: dataminers ("Turns in the Game Master are one less than what is displayed")
    $real_turns = 0;
    $dpt = 0.0; // Damage per turn
    $ept = 0.0; // Energy per turn
    
    if ( $turns > 0 ) {
        $real_turns = $turns + 1;
        $duration_s_pvp = $real_turns * 0.5;
        $duration_ms_pvp = (int) round( $duration_s_pvp * 1000 );
        
        // Calcul des statistiques par tour (spécifiques au PvP)
        $dpt = ( $real_turns > 0 && $power > 0 ) ? ( $power / $real_turns ) : 0.0;
        $ept = ( $real_turns > 0 && 0 !== $energy_delta ) ? ( $energy_delta / $real_turns ) : 0.0;
    } else {
        // Si pas de durationTurns, on utilise la durée globale pour les calculs
        if ( $global_duration_ms > 0 ) {
            $duration_s_pvp = $global_duration_ms / 1000.0;
            // On calcule le nombre de tours à partir de la durée globale
            $real_turns = (int) round( $duration_s_pvp / 0.5 );
            if ( $real_turns > 0 ) {
                $dpt = ( $power > 0 ) ? ( $power / $real_turns ) : 0.0;
                $ept = ( 0 !== $energy_delta ) ? ( $energy_delta / $real_turns ) : 0.0;
            }
        }
    }

    $dps = ( $duration_s_pvp > 0 && $power > 0 ) ? ( $power / $duration_s_pvp ) : 0.0;
    $eps = ( $duration_s_pvp > 0 && 0 !== $energy_delta ) ? ( $energy_delta / $duration_s_pvp ) : 0.0;

    // Récupération des buffs/debuffs depuis combatMove
    $buffs = [];
    if ( isset( $combat_move['buffs'] ) && is_array( $combat_move['buffs'] ) ) {
        $buffs = [
            'buff_activation_chance'            => isset( $combat_move['buffs']['buffActivationChance'] ) ? (float) $combat_move['buffs']['buffActivationChance'] : 0.0,
            'attacker_attack_stat_stage_change' => isset( $combat_move['buffs']['attackerAttackStatStageChange'] ) ? (int) $combat_move['buffs']['attackerAttackStatStageChange'] : 0,
            'attacker_defense_stat_stage_change' => isset( $combat_move['buffs']['attackerDefenseStatStageChange'] ) ? (int) $combat_move['buffs']['attackerDefenseStatStageChange'] : 0,
            'target_attack_stat_stage_change'   => isset( $combat_move['buffs']['targetAttackStatStageChange'] ) ? (int) $combat_move['buffs']['targetAttackStatStageChange'] : 0,
            'target_defense_stat_stage_change'  => isset( $combat_move['buffs']['targetDefenseStatStageChange'] ) ? (int) $combat_move['buffs']['targetDefenseStatStageChange'] : 0,
        ];
    }

    $extra = [
        'unique_id'   => $unique_id,
        'template_id' => $template_id,
        'game_key'    => 'pokemon_go',
        'names'       => $names,
    ];
    $pvp_existing_decoded = [];
    $pvp_existing_extra_valid = true;
    $pvp_existing_extra_raw = '';
    if ( $row ) {
        $pvp_existing_extra_raw = (string) ( $row->extra ?? '' );
        if ( function_exists( 'poke_hub_pokemon_decode_extra_json' ) ) {
            $pvp_existing_decoded = poke_hub_pokemon_decode_extra_json( $pvp_existing_extra_raw, $pvp_existing_extra_valid );
        } else {
            $pd = json_decode( $pvp_existing_extra_raw !== '' ? $pvp_existing_extra_raw : '{}', true );
            $pvp_existing_decoded = is_array( $pd ) ? $pd : [];
            $pvp_existing_extra_valid = ( $pvp_existing_extra_raw === '' ) || is_array( $pd );
        }
        if ( function_exists( 'poke_hub_pokemon_gm_merge_extra_names_with_existing' ) ) {
            $extra['names'] = poke_hub_pokemon_gm_merge_extra_names_with_existing( $extra['names'], $pvp_existing_decoded );
        }
        if ( function_exists( 'poke_hub_pokemon_gm_deep_merge_extra' ) ) {
            $extra = poke_hub_pokemon_gm_deep_merge_extra( $pvp_existing_decoded, $extra );
        }
    }
    if ( $row && ! $pvp_existing_extra_valid ) {
        $extra_json = $pvp_existing_extra_raw;
    } elseif ( function_exists( 'poke_hub_pokemon_encode_extra_json' ) ) {
        $extra_json = poke_hub_pokemon_encode_extra_json( $extra, $pvp_existing_extra_raw );
    } else {
        $extra_json = wp_json_encode( $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    $attack_data = [
        'slug'    => $slug,
        'name_en' => $names['en'],
        'name_fr' => $names['fr'],
        'extra'   => $extra_json,
    ];
    if ( $row && empty( $names['fr'] ) && ! empty( $row->name_fr ) ) {
        // Préserver un FR manuel existant si l'import n'en fournit pas.
        unset( $attack_data['name_fr'] );
    }
    if ( $row && function_exists( 'poke_hub_pokemon_gm_wpdb_data_only_changed_columns' ) ) {
        $attack_data   = poke_hub_pokemon_gm_wpdb_data_only_changed_columns( $attack_data, $row );
        $attack_format = poke_hub_pokemon_gm_wpdb_format_attack_row( $attack_data );
    } else {
        $attack_format = [ '%s', '%s', '%s', '%s' ];
    }

    if ( $row ) {
        $attack_id = (int) $row->id;
        if ( ! empty( $attack_data ) ) {
            $wpdb->update(
                $attacks_table,
                $attack_data,
                [ 'id' => $attack_id ],
                $attack_format,
                [ '%d' ]
            );
            $stats['attacks_updated_count'] = ($stats['attacks_updated_count'] ?? 0) + 1;
            if (count($stats['attacks_updated_sample'] ?? []) < 50) {
                $stats['attacks_updated_sample'][] = $names['en'];
            }
        }
    } else {
        $wpdb->insert( $attacks_table, $attack_data, $attack_format );
        $attack_id                 = (int) $wpdb->insert_id;
        $stats['attacks_inserted_count'] = ($stats['attacks_inserted_count'] ?? 0) + 1;
        if (count($stats['attacks_inserted_sample'] ?? []) < 50) {
            $stats['attacks_inserted_sample'][] = $names['en'];
        }
    }

    if ( $attack_id <= 0 || empty( $stats_table ) ) {
        return $stats;
    }

    // Stats PvP (damage, dps, eps, energy) - context = "pvp"
    // Si durationTurns est présent et différent de la durée globale, on stocke duration_ms dans les stats PvP
    // Stocker duration_ms uniquement si elle diffère de la durée globale (si durationTurns était présent)
    $duration_ms_for_pvp = 0;
    if ( $turns > 0 && $duration_ms_pvp > 0 ) {
        // Si durationTurns était présent, on stocke la durée PvP
        // même si elle est identique à la globale, pour garder la traçabilité
        $duration_ms_for_pvp = $duration_ms_pvp;
    }

    // Préparation des données extra pour les buffs/debuffs et statistiques par tour
    $pvp_extra_data = [];
    
    if ( ! empty( $buffs ) ) {
        $pvp_extra_data['buffs'] = $buffs;
    }
    
    // Ajout des statistiques par tour (spécifiques au PvP)
    if ( $real_turns > 0 ) {
        $pvp_extra_data['turns'] = $real_turns;
        $pvp_extra_data['dpt'] = round( $dpt, 3 );
        $pvp_extra_data['ept'] = round( $ept, 3 );
    }
    
    $pvp_extra = ! empty( $pvp_extra_data ) ? wp_json_encode( $pvp_extra_data ) : null;

    poke_hub_pokemon_gm_upsert_attack_stat_context(
        $stats_table,
        $attack_id,
        'pvp',
        [
            'damage'                 => $power,
            'dps'                    => $dps,
            'eps'                    => $eps,
            'duration_ms'            => $duration_ms_for_pvp,
            'damage_window_start_ms' => 0,
            'damage_window_end_ms'   => 0,
            'energy'                 => $energy_delta,
            'extra'                  => $pvp_extra,
        ],
    );

    $stats['pvp_stats']++;

    // Liaison type ↔ move
    if ( $type_slug !== '' ) {
        $type_id = poke_hub_pokemon_find_or_create_type( $type_slug, ucfirst( $type_slug ) );
        if ( $type_id > 0 ) {
            if ( function_exists( 'poke_hub_pokemon_import_sync_attack_types_links_non_destructive' ) ) {
                poke_hub_pokemon_import_sync_attack_types_links_non_destructive( $attack_id, [ $type_id ] );
            } else {
                poke_hub_pokemon_sync_attack_types_links( $attack_id, [ $type_id ] );
            }
            $stats['attack_type_links']++;
        }
    }

    return $stats;
}

/**
 * Import global Game Master Pokémon GO → tables Poké HUB.
 *
 * IMPORTANT : ne supprime RIEN.
 * - Ajoute ce qui n'existe pas
 * - Met à jour ce qui existe déjà
 *
 * @param string $source URL ou chemin du JSON Game Master.
 * @return array|\WP_Error
 */
function poke_hub_pokemon_import_game_master( $source, array $options = [] ) {
    $do_types_import = ! empty( $options['import_types_from_bulbapedia'] );
    if ( ! function_exists( 'pokehub_get_table' ) ) {
        return new \WP_Error( 'missing_helper', 'pokehub_get_table() is required.' );
    }

    // pour éviter les timeouts et les erreurs API (trop d'appels simultanés)
    if ( ! defined( 'POKE_HUB_GM_IMPORT_IN_PROGRESS' ) ) {
        define( 'POKE_HUB_GM_IMPORT_IN_PROGRESS', true );
    }

    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 300 );
    }
    if ( function_exists( 'ignore_user_abort' ) ) {
        @ignore_user_abort( true );
    }
    if ( function_exists( 'wp_raise_memory_limit' ) ) {
        wp_raise_memory_limit( 'admin' );
    } elseif ( function_exists( 'ini_set' ) ) {
        @ini_set( 'memory_limit', '512M' );
    }

    $json = poke_hub_pokemon_load_gamemaster_json( $source );
    if ( is_wp_error( $json ) ) {
        return $json;
    }

    $decoded = json_decode( $json, true );
    unset($json); // IMPORTANT: libère la grosse string
    if ( ! is_array( $decoded ) ) {
        return new \WP_Error( 'invalid_json', 'Game Master JSON is invalid.' );
    }

    if ( function_exists( 'poke_hub_gm_progress' ) ) {
        poke_hub_gm_progress( 'loaded', 15, 'Loaded JSON' );
    }

    global $wpdb;

    $tables = [
        'pokemon'              => pokehub_get_table( 'pokemon' ),
        'pokemon_types'        => pokehub_get_table( 'pokemon_types' ),
        'pokemon_type_links'   => pokehub_get_table( 'pokemon_type_links' ),
        'attacks'              => pokehub_get_table( 'attacks' ),
        'attack_stats'         => pokehub_get_table( 'attack_stats' ),
        'attack_type_links'    => pokehub_get_table( 'attack_type_links' ),
        'pokemon_attack_links' => pokehub_get_table( 'pokemon_attack_links' ),
        'generations'          => pokehub_get_table( 'generations' ),
        'pokemon_evolutions'   => pokehub_get_table( 'pokemon_evolutions' ),
    ];

    // Stats détaillées
    // Stats (évite de stocker des milliers de strings en RAM)
    $stats = [
        'pokemon_inserted_count'   => 0,
        'pokemon_updated_count'    => 0,
        'attacks_inserted_count'   => 0,
        'attacks_updated_count'    => 0,

        // échantillons debug (facultatif)
        'pokemon_inserted_sample'  => [],
        'pokemon_updated_sample'   => [],
        'attacks_inserted_sample'  => [],
        'attacks_updated_sample'   => [],

        'pve_stats'                => 0,
        'pvp_stats'                => 0,
        'pokemon_type_links'       => 0,
        'attack_type_links'        => 0,
        'pokemon_attack_links'     => 0,
    ];

    $seen_pokemon_slugs = [];
    $seen_attack_slugs    = [];

    $gender_index = function_exists( 'poke_hub_gm_build_gender_index' )
        ? poke_hub_gm_build_gender_index( $decoded )
        : [];

    // Index global proto → id / forme (servira pour les évolutions)
    $pokemon_index = [];

    // Mapping GMAX: [pokemonId][formKey] => move proto id
    $gmax_move_mappings = [];
    // Lookup move proto -> slug importé (priorité vfxName pour VN_BM_*).
    $gmax_move_slug_by_proto = [];
    // Set espèces ayant une version GMAX
    $gmax_species = [];
    // Set espèces avec forme NORMAL explicite dans formSettings (ex: Dialga/Palkia).
    $species_with_explicit_normal_form = [];
    // Agrégat depuis pokemonSettings : NORMAL explicite + au moins une forme non-NORMAL
    // (formSettings ne liste pas toujours NORMAL, d'où collisions de slug sans ce complément).
    $pokemon_forms_agg = [];
    $form_costume_index = [];

    // Prélecture du mapping GMAX (SOURDOUGH_MOVE_MAPPING_SETTINGS)
    foreach ( $decoded as $entry ) {
        if ( ! is_array( $entry ) ) {
            continue;
        }
        $data = $entry['data'] ?? [];

        if ( ! empty( $data['pokemonSettings'] ) && is_array( $data['pokemonSettings'] ) ) {
            $ps           = $data['pokemonSettings'];
            $poke_proto   = (string) ( $ps['pokemonId'] ?? '' );
            $form_raw     = strtoupper( (string) ( $ps['form'] ?? '' ) );
            if ( $poke_proto !== '' && $form_raw !== '' && $form_raw !== 'UNSET' && $form_raw !== 'FORM_UNSET' ) {
                if ( ! isset( $pokemon_forms_agg[ $poke_proto ] ) ) {
                    $pokemon_forms_agg[ $poke_proto ] = [
                        'explicit_normal' => false,
                        'non_normal'      => false,
                    ];
                }
                if ( preg_match( '/_NORMAL$/', $form_raw ) || $form_raw === 'NORMAL' || $form_raw === 'FORM_NORMAL' ) {
                    $pokemon_forms_agg[ $poke_proto ]['explicit_normal'] = true;
                } else {
                    $pokemon_forms_agg[ $poke_proto ]['non_normal'] = true;
                }
            }
        }

        if ( ! empty( $data['formSettings'] ) && is_array( $data['formSettings'] ) ) {
            $form_settings = $data['formSettings'];
            $pokemon_proto = (string) ( $form_settings['pokemon'] ?? '' );
            $forms_list    = $form_settings['forms'] ?? [];
            if ( $pokemon_proto !== '' && is_array( $forms_list ) ) {
                $has_normal_form = false;
                $has_other_form_change = false;
                foreach ( $forms_list as $form_row ) {
                    if ( ! is_array( $form_row ) ) {
                        continue;
                    }
                    if ( ! empty( $form_row['isCostume'] ) ) {
                        $form_for_costume = strtoupper( (string) ( $form_row['form'] ?? '' ) );
                        if ( $form_for_costume !== '' ) {
                            if ( ! isset( $form_costume_index[ $pokemon_proto ] ) ) {
                                $form_costume_index[ $pokemon_proto ] = [];
                            }
                            $form_costume_index[ $pokemon_proto ][ $form_for_costume ] = true;
                        }
                    }
                    $form_value = strtoupper( (string) ( $form_row['form'] ?? '' ) );
                    if ( $form_value === '' ) {
                        continue;
                    }
                    if ( preg_match( '/_NORMAL$/', $form_value ) || $form_value === 'NORMAL' || $form_value === 'FORM_NORMAL' ) {
                        $has_normal_form = true;
                    } else {
                        // On ne conserve NORMAL que s'il existe au moins une vraie forme de changement.
                        // Cela exclut les formes cosmétiques/régionales qui ne doivent pas créer "xxx-normal".
                        $form_kind = function_exists( 'poke_hub_pokemon_guess_form_type_from_gm' )
                            ? poke_hub_pokemon_guess_form_type_from_gm(
                                $pokemon_proto,
                                $form_value,
                                '',
                                [
                                    '__isCostume' => ! empty( $form_row['isCostume'] ),
                                ]
                            )
                            : '';
                        $has_meaningful_normal_pair = in_array(
                            $form_kind,
                            [ 'switch_form', 'switch_battle', 'special', 'fusion' ],
                            true
                        );
                        if ( ! $has_meaningful_normal_pair ) {
                            // Fallback explicite: certaines espèces (ex: Palkia/Dialga) ont NORMAL + ORIGIN
                            // sans signal complet côté settings, mais ORIGIN reste une vraie forme métier.
                            if ( preg_match( '/_(ORIGIN|INCARNATE|THERIAN|RESOLUTE|BLADE|SHIELD|HERO|CROWNED|DUSK_MANE|DAWN_WINGS|BLACK|WHITE)$/', $form_value ) ) {
                                $has_meaningful_normal_pair = true;
                            }
                        }
                        if ( $has_meaningful_normal_pair ) {
                            $has_other_form_change = true;
                        }
                    }
                }
                // NORMAL n'est une vraie forme que si l'espèce a aussi une forme "métier" (switch/fusion/special).
                if ( $has_normal_form && $has_other_form_change ) {
                    $species_with_explicit_normal_form[ $pokemon_proto ] = true;
                }
            }
        }
        if ( ! empty( $data['moveSettings'] ) && is_array( $data['moveSettings'] ) ) {
            $move_settings = $data['moveSettings'];
            $movement_id   = (string) ( $move_settings['movementId'] ?? '' );
            if ( $movement_id !== '' && strpos( $movement_id, 'VN_BM_' ) === 0 ) {
                $vfx_name = trim( (string) ( $move_settings['vfxName'] ?? '' ) );
                $gmax_move_slug_by_proto[ $movement_id ] = $vfx_name !== ''
                    ? poke_hub_pokemon_gm_id_to_slug( $vfx_name )
                    : poke_hub_pokemon_gm_id_to_slug( $movement_id );
            }
        }
        if ( empty( $data['sourdoughMoveMappingSettings']['mappings'] ) || ! is_array( $data['sourdoughMoveMappingSettings']['mappings'] ) ) {
            continue;
        }
        foreach ( $data['sourdoughMoveMappingSettings']['mappings'] as $map_row ) {
            if ( ! is_array( $map_row ) ) {
                continue;
            }
            $pokemon_id_proto = (string) ( $map_row['pokemonId'] ?? '' );
            $move_proto       = (string) ( $map_row['move'] ?? '' );
            if ( $pokemon_id_proto === '' || $move_proto === '' ) {
                continue;
            }
            $gmax_species[ $pokemon_id_proto ] = true;
            $form_proto = (string) ( $map_row['form'] ?? '' );
            $form_key = $form_proto;
            if ( $form_key !== '' ) {
                $upper_form = strtoupper( $form_key );
                if ( $upper_form === 'NORMAL' || $upper_form === 'FORM_NORMAL' || preg_match( '/_NORMAL$/', $upper_form ) ) {
                    $form_key = '';
                }
            }
            if ( ! isset( $gmax_move_mappings[ $pokemon_id_proto ] ) ) {
                $gmax_move_mappings[ $pokemon_id_proto ] = [];
            }
            $gmax_move_mappings[ $pokemon_id_proto ][ $form_key ] = $move_proto;
        }
    }

    foreach ( $pokemon_forms_agg as $proto => $agg ) {
        if ( ! empty( $agg['explicit_normal'] ) && ! empty( $agg['non_normal'] ) ) {
            $species_with_explicit_normal_form[ $proto ] = true;
        }
    }

    if ( function_exists( 'poke_hub_gm_progress' ) ) {
        poke_hub_gm_progress( 'pass1_moves', 20, 'PASS 1: Moves' );
    }    

    // PASS 1 : Attacks (PvE + PvP)
    foreach ( $decoded as $entry ) {
        if ( ! is_array( $entry ) ) {
            continue;
        }

        $template_id = $entry['templateId'] ?? '';
        $data        = $entry['data'] ?? [];

        if ( isset( $data['moveSettings'] ) && is_array( $data['moveSettings'] ) && ! empty( $tables['attacks'] ) ) {
            $stats = poke_hub_pokemon_import_from_attack_settings(
                $template_id,
                $data['moveSettings'],
                $tables,
                $stats,
                $seen_attack_slugs
            );
            continue;
        }

        // Détection de combatMove (template COMBAT_V0296_MOVE_*)
        if ( isset( $data['combatMove'] ) && is_array( $data['combatMove'] ) && ! empty( $tables['attacks'] ) ) {
            $stats = poke_hub_pokemon_import_from_combat_move(
                $template_id,
                $data['combatMove'],
                $tables,
                $stats,
                $seen_attack_slugs
            );
            continue;
        }

        // Rétrocompatibilité : combatAttack (ancien format)
        if ( isset( $data['combatAttack'] ) && is_array( $data['combatAttack'] ) && ! empty( $tables['attacks'] ) ) {
            $stats = poke_hub_pokemon_import_from_combat_move(
                $template_id,
                $data['combatAttack'],
                $tables,
                $stats,
                $seen_attack_slugs
            );
            continue;
        }
    }

    if ( function_exists( 'poke_hub_gm_progress' ) ) {
        poke_hub_gm_progress( 'pass2_pokemon', 45, 'PASS 2: Pokémon' );
    }

    // PASS 2 : Pokémon (stats, types, attaques, flags GM)
    foreach ( $decoded as $entry ) {
        if ( ! is_array( $entry ) ) {
            continue;
        }

        $template_id = $entry['templateId'] ?? '';
        $data        = $entry['data'] ?? [];

        if ( isset( $data['pokemonSettings'] ) && is_array( $data['pokemonSettings'] ) && ! empty( $tables['pokemon'] ) ) {
            $stats = poke_hub_pokemon_import_from_pokemon_settings(
                $template_id,
                $data['pokemonSettings'],
                $tables,
                $stats,
                $seen_pokemon_slugs,
                $gender_index,
                $pokemon_index,
                $gmax_species,
                $species_with_explicit_normal_form,
                $form_costume_index
            );
            continue;
        }
    }

    // PASS 2C : lier attaques GMAX aux Pokémon importés
    if ( ! empty( $gmax_move_mappings ) ) {
        foreach ( $gmax_move_mappings as $pokemon_id_proto => $moves_by_form ) {
            if ( empty( $pokemon_index[ $pokemon_id_proto ] ) || ! is_array( $moves_by_form ) ) {
                continue;
            }
            foreach ( $moves_by_form as $form_key => $move_proto ) {
                $target_info = $pokemon_index[ $pokemon_id_proto ][ $form_key ] ?? null;
                if ( ! $target_info && isset( $pokemon_index[ $pokemon_id_proto ][''] ) ) {
                    $target_info = $pokemon_index[ $pokemon_id_proto ][''];
                }
                if ( ! is_array( $target_info ) ) {
                    continue;
                }
                $pokemon_id = (int) ( $target_info['pokemon_id'] ?? 0 );
                if ( $pokemon_id <= 0 ) {
                    continue;
                }
                $move_slug  = $gmax_move_slug_by_proto[ (string) $move_proto ] ?? poke_hub_pokemon_gm_id_to_slug( (string) $move_proto );
                $attack_id  = poke_hub_pokemon_get_attack_id_by_slug( $move_slug, $tables );
                if ( $attack_id <= 0 ) {
                    continue;
                }
                poke_hub_pokemon_sync_pokemon_attack_links(
                    $pokemon_id,
                    [
                        [
                            'attack_id' => $attack_id,
                            'role'      => 'gmax',
                            'extra'     => wp_json_encode(
                                [
                                    'source' => 'sourdough_mapping',
                                    'move_proto' => $move_proto,
                                ],
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                            ),
                        ],
                    ],
                    $tables,
                    false
                );
                $stats['pokemon_attack_links'] = ( $stats['pokemon_attack_links'] ?? 0 ) + 1;
            }
        }
    }

    if ( function_exists( 'poke_hub_gm_progress' ) ) {
        poke_hub_gm_progress( 'pass3_evos', 70, 'PASS 3: Evolutions' );
    }

    /**
     * PASS 3 : Evolutions (pokemon_evolutions)
     *
     * On a maintenant :
     * - $pokemon_index[pokemonId_proto][form_proto] => ['pokemon_id','form_variant_id']
     * - les helpers :
     *   * poke_hub_pokemon_normalize_evolution_branches()
     *   * poke_hub_pokemon_sync_pokemon_evolutions()
     */
    if (
        ! empty( $tables['pokemon_evolutions'] )
        && function_exists( 'poke_hub_pokemon_normalize_evolution_branches' )
        && function_exists( 'poke_hub_pokemon_sync_pokemon_evolutions' )
    ) {
        foreach ( $decoded as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }

            $template_id = $entry['templateId'] ?? '';
            
            $data = $entry['data'] ?? [];
            if ( empty( $data['pokemonSettings'] ) || ! is_array( $data['pokemonSettings'] ) ) {
                continue;
            }

            $pokemon_settings = $data['pokemonSettings'];

            $base_proto = $pokemon_settings['pokemonId'] ?? '';
            if ( $base_proto === '' ) {
                continue;
            }

            $base_form_proto = $pokemon_settings['form'] ?? '';
            $base_form_key   = $base_form_proto !== '' ? (string) $base_form_proto : '';

            if ( empty( $pokemon_index[ $base_proto ] ) ) {
                continue;
            }

            // On essaie d'abord forme exacte, sinon forme par défaut
            $base_info = $pokemon_index[ $base_proto ][ $base_form_key ] ?? null;
            if ( ! $base_info && isset( $pokemon_index[ $base_proto ][''] ) ) {
                $base_info = $pokemon_index[ $base_proto ][''];
            }

            if ( ! $base_info ) {
                continue;
            }

            $base_pokemon_id      = (int) ( $base_info['pokemon_id']      ?? 0 );
            $base_form_variant_id = (int) ( $base_info['form_variant_id'] ?? 0 );

            if ( $base_pokemon_id <= 0 ) {
                continue;
            }

            $branches = poke_hub_pokemon_normalize_evolution_branches( $pokemon_settings );
            if ( empty( $branches ) ) {
                continue;
            }

            // Resolver proto → (pokemon_id, form_variant_id)
            $resolver = static function( string $target_id_proto, string $target_form_proto ) use ( $pokemon_index ): array {
                if ( $target_id_proto === '' ) {
                    return [ 0, 0 ];
                }
                if ( empty( $pokemon_index[ $target_id_proto ] ) ) {
                    return [ 0, 0 ];
                }

                $form_key = $target_form_proto !== '' ? $target_form_proto : '';

                $info = $pokemon_index[ $target_id_proto ][ $form_key ] ?? null;
                if ( ! $info && isset( $pokemon_index[ $target_id_proto ][''] ) ) {
                    $info = $pokemon_index[ $target_id_proto ][''];
                }

                if ( ! $info ) {
                    return [ 0, 0 ];
                }

                return [
                    (int) ( $info['pokemon_id']      ?? 0 ),
                    (int) ( $info['form_variant_id'] ?? 0 ),
                ];
            };

            poke_hub_pokemon_sync_pokemon_evolutions(
                $base_pokemon_id,
                $base_form_variant_id,
                $branches,
                [
                    'mode'     => 'import',
                    'tables'   => $tables,
                    'resolver' => $resolver,
                ]
            );            
        }
    }

    if ( function_exists( 'poke_hub_gm_progress' ) ) {
        poke_hub_gm_progress( 'pass4_types', 85, 'PASS 4: Types' );
    }    

    $do_types_import = ! empty( $options['import_types_from_bulbapedia'] );

    if ( $do_types_import ) {
    
        /**
         * PASS 4 : Import des données de types depuis Bulbapedia
         */
        $importer_file = POKE_HUB_POKEMON_PATH . '/includes/pokemon-type-bulbapedia-importer.php';
        if ( file_exists( $importer_file ) ) {
            require_once $importer_file;
        } else {
            $stats['types_import_error'] = 'Fichier importer Bulbapedia introuvable';
        }
    
        if ( function_exists( 'poke_hub_pokemon_import_all_types_for_pokemon_go' ) ) {
    
            $type_import_stats = poke_hub_pokemon_import_all_types_for_pokemon_go();
    
            if ( isset( $type_import_stats['success'] ) ) {
                $stats['types_imported_for_pokemon_go'] = $type_import_stats['success'];
            }
            if ( isset( $type_import_stats['total'] ) ) {
                $stats['types_import_total'] = $type_import_stats['total'];
            }
            if ( ! empty( $type_import_stats['errors'] ) && is_array( $type_import_stats['errors'] ) ) {
                $stats['types_import_errors'] = $type_import_stats['errors'];
            }
    
        } elseif ( empty( $stats['types_import_error'] ) ) {
            // Si on n'a pas déjà une erreur "fichier introuvable"
            $stats['types_import_error'] = 'Fonction poke_hub_pokemon_import_all_types_for_pokemon_go() non trouvée';
        }
    
    } else {
        $stats['types_import_skipped'] = 1;
    }
    
    if ( function_exists( 'poke_hub_gm_progress' ) ) {
        poke_hub_gm_progress( 'done', 95, 'Finalizing' );
    }

    return $stats;
}
