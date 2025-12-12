<?php
// File: modules/pokemon/includes/pokemon-cp-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Niveaux "standard" pour lesquels on veut des CP.
 *
 * @return int[]
 */
function poke_hub_pokemon_get_default_cp_levels(): array {
    return [15, 20, 25, 30, 35, 40, 50, 51];
}

/**
 * Tableau des CP Multipliers connus.
 *
 * ⚠️ Table partielle : tu peux la compléter/ajuster si besoin.
 * Les niveaux utilisés dans le form sont présents.
 *
 * @return array [ niveau(float) => cpm(float) ]
 */
function poke_hub_pokemon_get_cp_multipliers(): array {
    $cpm = [
        // Niveaux principaux
        15  => 0.51739395,
        20  => 0.5974,
        25  => 0.667934,
        30  => 0.7317,
        35  => 0.76156384,
        40  => 0.7903,

        // XL officiels
        50  => 0.84029999,
        51  => 0.84529999,
    ];

    /**
     * Filtre si tu veux surcharger / compléter le tableau.
     */
    return apply_filters('poke_hub_pokemon_cp_multipliers', $cpm);
}

/**
 * Récupère le CP Multiplier pour un niveau donné.
 *
 * @param float|int $level
 * @return float
 */
function poke_hub_pokemon_get_cp_multiplier($level): float {
    $level      = (float) $level;
    $cpm_table  = poke_hub_pokemon_get_cp_multipliers();
    $cpm_level  = $level;

    // On essaye d’abord la clé exacte
    if (isset($cpm_table[$cpm_level])) {
        return (float) $cpm_table[$cpm_level];
    }

    // Sinon, on tente avec un arrondi .5 / .0 si besoin
    $candidate = number_format($level, 1, '.', '');
    if (isset($cpm_table[$candidate])) {
        return (float) $cpm_table[$candidate];
    }

    // Pas trouvé → 0 = CP invalide (better than planter)
    return 0.0;
}

/**
 * Calcule le CP pour un Pokémon donné (stats de base + IV + niveau).
 *
 * PC = floor(((Atk+IVAtk) * sqrt(Def+IVDef) * sqrt(Sta+IVSta) * (CPM^2)) / 10)
 *
 * @param int   $base_atk
 * @param int   $base_def
 * @param int   $base_sta
 * @param int   $iv_atk
 * @param int   $iv_def
 * @param int   $iv_sta
 * @param float $level
 * @return int
 */
function poke_hub_pokemon_compute_cp(
    int $base_atk,
    int $base_def,
    int $base_sta,
    int $iv_atk,
    int $iv_def,
    int $iv_sta,
    float $level
): int {
    $cpm = poke_hub_pokemon_get_cp_multiplier($level);
    if ($cpm <= 0) {
        return 0;
    }

    $atk = $base_atk + $iv_atk;
    $def = $base_def + $iv_def;
    $sta = $base_sta + $iv_sta;

    if ($atk <= 0 || $def <= 0 || $sta <= 0) {
        return 0;
    }

    $cp = ($atk * sqrt($def) * sqrt($sta) * $cpm * $cpm) / 10.0;

    return (int) floor($cp);
}

/**
 * Génère un set de CP pour un même triplet IV sur plusieurs niveaux.
 *
 * @param int   $base_atk
 * @param int   $base_def
 * @param int   $base_sta
 * @param float[]|int[] $levels
 * @param int[] $ivs [atk, def, sta]
 * @return array [ '15' => CP, '20' => CP, ... ]
 */
function poke_hub_pokemon_compute_cp_set_for_levels(
    int $base_atk,
    int $base_def,
    int $base_sta,
    array $levels,
    array $ivs
): array {
    $result = [];

    $iv_atk = (int) ($ivs[0] ?? 15);
    $iv_def = (int) ($ivs[1] ?? 15);
    $iv_sta = (int) ($ivs[2] ?? 15);

    foreach ($levels as $level) {
        $level = (float) $level;
        $cp    = poke_hub_pokemon_compute_cp(
            $base_atk,
            $base_def,
            $base_sta,
            $iv_atk,
            $iv_def,
            $iv_sta,
            $level
        );

        // On stocke la clé niveau en string, comme "15", "20", ...
        $key = (string) (int) $level;
        $result[$key] = $cp;
    }

    return $result;
}

/**
 * Helper global : construit tous les sets de CP utiles pour un Pokémon :
 * - max_cp       : 15/15/15
 * - min_cp_10    : 10/10/10
 * - min_cp_shadow: 6/6/6 (pour les obscurs)
 *
 * @param int   $base_atk
 * @param int   $base_def
 * @param int   $base_sta
 * @param array $levels  niveaux optionnels, sinon niveaux par défaut
 * @return array {
 *   max_cp        => [ level => CP ],
 *   min_cp_10     => [ level => CP ],
 *   min_cp_shadow => [ level => CP ],
 * }
 */
function poke_hub_pokemon_build_cp_sets_for_pokemon(
    int $base_atk,
    int $base_def,
    int $base_sta,
    array $levels = []
): array {
    if (empty($levels)) {
        $levels = poke_hub_pokemon_get_default_cp_levels();
    }

    if ($base_atk <= 0 || $base_def <= 0 || $base_sta <= 0) {
        return [
            'max_cp'        => [],
            'min_cp_10'     => [],
            'min_cp_shadow' => [],
        ];
    }

    return [
        'max_cp'        => poke_hub_pokemon_compute_cp_set_for_levels(
            $base_atk,
            $base_def,
            $base_sta,
            $levels,
            [15, 15, 15]
        ),
        'min_cp_10'     => poke_hub_pokemon_compute_cp_set_for_levels(
            $base_atk,
            $base_def,
            $base_sta,
            $levels,
            [10, 10, 10]
        ),
        'min_cp_shadow' => poke_hub_pokemon_compute_cp_set_for_levels(
            $base_atk,
            $base_def,
            $base_sta,
            $levels,
            [6, 6, 6]
        ),
    ];
}
