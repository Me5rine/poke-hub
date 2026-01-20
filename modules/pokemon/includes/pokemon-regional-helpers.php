<?php
// modules/pokemon/includes/pokemon-regional-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get countries list for regional Pokémon
 * 
 * This function is independent of the user-profiles module.
 * It tries to use Ultimate Member countries if available, otherwise returns a basic list.
 * 
 * @return array Countries list (label => label) for use in select options
 */
function poke_hub_pokemon_get_countries() {
    // First, try to get from Ultimate Member if available
    if (function_exists('UM') && is_object(UM())) {
        $um_countries = UM()->builtin()->get('countries');
        if (is_array($um_countries) && !empty($um_countries)) {
            // UM returns code => label, we return label => label for consistency
            $countries = [];
            foreach ($um_countries as $code => $label) {
                $countries[$label] = $label;
            }
            return $countries;
        }
    }
    
    // Fallback: Try to get from user-profiles module helper if available
    if (function_exists('poke_hub_get_countries')) {
        $up_countries = poke_hub_get_countries();
        if (is_array($up_countries) && !empty($up_countries)) {
            // Convert code => label to label => label
            $countries = [];
            foreach ($up_countries as $code => $label) {
                $countries[$label] = $label;
            }
            return $countries;
        }
    }
    
    // Final fallback: Basic list of common countries (in French)
    return [
        'Afghanistan' => 'Afghanistan',
        'Afrique du Sud' => 'Afrique du Sud',
        'Albanie' => 'Albanie',
        'Algérie' => 'Algérie',
        'Allemagne' => 'Allemagne',
        'Andorre' => 'Andorre',
        'Angola' => 'Angola',
        'Arabie saoudite' => 'Arabie saoudite',
        'Argentine' => 'Argentine',
        'Australie' => 'Australie',
        'Autriche' => 'Autriche',
        'Bahreïn' => 'Bahreïn',
        'Bangladesh' => 'Bangladesh',
        'Belgique' => 'Belgique',
        'Belize' => 'Belize',
        'Bhoutan' => 'Bhoutan',
        'Biélorussie' => 'Biélorussie',
        'Bolivie' => 'Bolivie',
        'Bosnie-Herzégovine' => 'Bosnie-Herzégovine',
        'Brésil' => 'Brésil',
        'Bulgarie' => 'Bulgarie',
        'Canada' => 'Canada',
        'Chili' => 'Chili',
        'Chine' => 'Chine',
        'Chypre' => 'Chypre',
        'Colombie' => 'Colombie',
        'Costa Rica' => 'Costa Rica',
        'Croatie' => 'Croatie',
        'Cuba' => 'Cuba',
        'Danemark' => 'Danemark',
        'Égypte' => 'Égypte',
        'Émirats arabes unis' => 'Émirats arabes unis',
        'Équateur' => 'Équateur',
        'Espagne' => 'Espagne',
        'États-Unis' => 'États-Unis',
        'Estonie' => 'Estonie',
        'Finlande' => 'Finlande',
        'France' => 'France',
        'Grèce' => 'Grèce',
        'Groenland' => 'Groenland',
        'Guatemala' => 'Guatemala',
        'Guernesey' => 'Guernesey',
        'Guyane' => 'Guyane',
        'Guyane française' => 'Guyane française',
        'Haïti' => 'Haïti',
        'Honduras' => 'Honduras',
        'Hongrie' => 'Hongrie',
        'Inde' => 'Inde',
        'Irak' => 'Irak',
        'Iran' => 'Iran',
        'Irlande' => 'Irlande',
        'Islande' => 'Islande',
        'Israël' => 'Israël',
        'Italie' => 'Italie',
        'Jamaïque' => 'Jamaïque',
        'Japon' => 'Japon',
        'Jersey' => 'Jersey',
        'Jordanie' => 'Jordanie',
        'Koweït' => 'Koweït',
        'Liban' => 'Liban',
        'Lettonie' => 'Lettonie',
        'Libye' => 'Libye',
        'Lituanie' => 'Lituanie',
        'Luxembourg' => 'Luxembourg',
        'Macédoine du Nord' => 'Macédoine du Nord',
        'Malte' => 'Malte',
        'Maroc' => 'Maroc',
        'Mexique' => 'Mexique',
        'Moldavie' => 'Moldavie',
        'Monaco' => 'Monaco',
        'Monténégro' => 'Monténégro',
        'Népal' => 'Népal',
        'Nicaragua' => 'Nicaragua',
        'Norvège' => 'Norvège',
        'Oman' => 'Oman',
        'Palestine' => 'Palestine',
        'Panama' => 'Panama',
        'Paraguay' => 'Paraguay',
        'Pays-Bas' => 'Pays-Bas',
        'Pérou' => 'Pérou',
        'Pologne' => 'Pologne',
        'Portugal' => 'Portugal',
        'Qatar' => 'Qatar',
        'République dominicaine' => 'République dominicaine',
        'République tchèque' => 'République tchèque',
        'Roumanie' => 'Roumanie',
        'Royaume-Uni' => 'Royaume-Uni',
        'Saint-Kitts-et-Nevis' => 'Saint-Kitts-et-Nevis',
        'Saint-Marin' => 'Saint-Marin',
        'Sainte-Lucie' => 'Sainte-Lucie',
        'Saint-Vincent-et-les-Grenadines' => 'Saint-Vincent-et-les-Grenadines',
        'Serbie' => 'Serbie',
        'Slovaquie' => 'Slovaquie',
        'Slovénie' => 'Slovénie',
        'Sri Lanka' => 'Sri Lanka',
        'Soudan' => 'Soudan',
        'Suède' => 'Suède',
        'Suisse' => 'Suisse',
        'Suriname' => 'Suriname',
        'Syrie' => 'Syrie',
        'Tunisie' => 'Tunisie',
        'Turquie' => 'Turquie',
        'Ukraine' => 'Ukraine',
        'Uruguay' => 'Uruguay',
        'Vatican' => 'Vatican',
        'Venezuela' => 'Venezuela',
        'Yémen' => 'Yémen',
        'Île de Man' => 'Île de Man',
    ];
}

/**
 * Normalize a label to avoid mismatches (UM uses typographic apostrophes, special spaces, etc.)
 *
 * @param string $s
 * @return string
 */
function poke_hub_pokemon_normalize_um_label($s) {
    $s = (string) $s;

    // Typographic apostrophes -> ASCII apostrophe
    // ' (U+2019 RIGHT SINGLE QUOTATION MARK) et ' (U+2018 LEFT SINGLE QUOTATION MARK) -> '
    // Replace typographic apostrophes (U+2019 RIGHT SINGLE QUOTATION MARK and U+2018 LEFT SINGLE QUOTATION MARK) with ASCII apostrophe
    $s = str_replace(["\xE2\x80\x99", "\xE2\x80\x98", "´", "`"], "'", $s);

    // Non-breaking spaces -> normal space
    $s = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], " ", $s);

    // Normalize whitespace
    $s = trim(preg_replace('/\s+/', ' ', $s));

    return $s;
}

/**
 * Get list of country labels for a geographic region.
 * Converts generic region names (like "Europe", "Asie") to actual Ultimate Member country labels.
 *
 * @param string $region_name Generic region name (e.g., 'Europe', 'Asie', 'Hémisphère Est')
 * @param array  $visited Internal recursion guard
 * @return array Array of Ultimate Member country labels
 */
function poke_hub_pokemon_get_countries_for_region($region_name, $visited = []) {

    // Prevent infinite recursion
    $region_norm = poke_hub_pokemon_normalize_um_label($region_name);
    if (isset($visited[$region_norm])) {
        return [];
    }
    $visited[$region_norm] = true;

    // 1) D'abord, essayer de récupérer depuis la table pokemon_regional_regions (nouvelle source de vérité)
    if (function_exists('poke_hub_pokemon_get_regional_region_by_slug')) {
        // Essayer avec le slug exact
        $region_data = poke_hub_pokemon_get_regional_region_by_slug($region_name);
        if (empty($region_data)) {
            // Essayer avec le slug normalisé
            $region_slug = sanitize_key($region_name);
            $region_data = poke_hub_pokemon_get_regional_region_by_slug($region_slug);
        }
        
        if (!empty($region_data) && !empty($region_data['countries'])) {
            // Résoudre les sous-régions si nécessaire (récursion)
            $countries = $region_data['countries'];
            $all_resolved = [];
            foreach ($countries as $country_or_region) {
                // Si c'est une référence à une autre région (commence par une majuscule et n'est pas un pays UM connu)
                // On essaie de la résoudre
                $maybe_region = poke_hub_pokemon_get_countries_for_region($country_or_region, $visited);
                if (!empty($maybe_region)) {
                    $all_resolved = array_merge($all_resolved, $maybe_region);
                } else {
                    // C'est probablement un pays direct
                    $all_resolved[] = $country_or_region;
                }
            }
            return array_unique(array_filter($all_resolved));
        }
    }

    // 2) Fallback: Utiliser les données depuis pokemon-regional-data.php
    if (function_exists('poke_hub_get_regional_regions_data')) {
        $regions_data = poke_hub_get_regional_regions_data();
        foreach ($regions_data as $region_data) {
            $region_slug_norm = poke_hub_pokemon_normalize_um_label($region_data['slug'] ?? '');
            $region_name_fr_norm = poke_hub_pokemon_normalize_um_label($region_data['name_fr'] ?? '');
            $region_name_en_norm = poke_hub_pokemon_normalize_um_label($region_data['name_en'] ?? '');
            
            if ($region_slug_norm === $region_norm || $region_name_fr_norm === $region_norm || $region_name_en_norm === $region_norm) {
                if (!empty($region_data['countries']) && is_array($region_data['countries'])) {
                    $countries = $region_data['countries'];
                    $all_resolved = [];
                    foreach ($countries as $country_or_region) {
                        $maybe_region = poke_hub_pokemon_get_countries_for_region($country_or_region, $visited);
                        if (!empty($maybe_region)) {
                            $all_resolved = array_merge($all_resolved, $maybe_region);
                        } else {
                            $all_resolved[] = $country_or_region;
                        }
                    }
                    return array_unique(array_filter($all_resolved));
                }
            }
        }
    }

    // 3) Final fallback: Check if it's a country name directly
    $all_countries = [];
    if (function_exists('poke_hub_get_countries')) {
        $all_countries_map = poke_hub_get_countries();
        $all_countries = is_array($all_countries_map) ? array_values($all_countries_map) : [];
    } elseif (function_exists('UM') && is_object(UM())) {
        $all_countries_map = UM()->builtin()->get('countries');
        $all_countries = is_array($all_countries_map) ? array_values($all_countries_map) : [];
    }

    // Build a normalized lookup => official UM label
    $all_countries_norm_map = [];
    foreach ($all_countries as $label) {
        $all_countries_norm_map[poke_hub_pokemon_normalize_um_label($label)] = $label;
    }

    if (isset($all_countries_norm_map[$region_norm])) {
        return [$all_countries_norm_map[$region_norm]];
    }

    return [];
}

