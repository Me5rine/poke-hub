<?php
// File: /modules/pokemon/includes/pokemon-regional-seed.php
// Contains all regional data seeding logic for Pokémon module

if (!defined('ABSPATH')) {
    exit;
}

// Load helpers if not already loaded
if (!function_exists('poke_hub_pokemon_save_regional_region')) {
    require_once __DIR__ . '/pokemon-regional-db-helpers.php';
}

// Load SINGLE SOURCE OF TRUTH for all regional data (must be loaded first)
require_once __DIR__ . '/pokemon-regional-data.php';

/**
 * Seed regional data (geographic regions and Vivillon pattern mappings)
 * 
 * @param bool $force Force update even if data already exists
 * @return bool True on success, false on failure
 */
function poke_hub_seed_regional_data($force = false) {
    global $wpdb;
    
    // Check if tables exist and are empty
    $regions_table = pokehub_get_table('pokemon_regional_regions');
    $mappings_table = pokehub_get_table('pokemon_regional_mappings');
    
    if (empty($regions_table) || empty($mappings_table)) {
        return false;
    }
    
    // Check if tables exist in database
    $regions_table_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$regions_table}'") === $regions_table);
    $mappings_table_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$mappings_table}'") === $mappings_table);
    
    if (!$regions_table_exists || !$mappings_table_exists) {
        return false;
    }
    
    // Check if tables are empty
    $regions_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$regions_table}");
    $mappings_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$mappings_table}");
    
    // Only seed if both tables are empty (or if forced)
    // But we allow seeding regions even if mappings exist, and vice versa
    if (!$force) {
        // If both tables have data, skip seeding
        if ($regions_count > 0 && $mappings_count > 0) {
            return false;
        }
        // If only one table has data, we still seed the empty one
    }
    
    // Seed geographic regions (Europe, Asia, Africa, etc.)
    if (function_exists('poke_hub_seed_regional_regions')) {
        poke_hub_seed_regional_regions($force);
    }
    
    // Seed regional Pokémon mappings (including all regional Pokémon from DB)
    if (function_exists('poke_hub_seed_regional_pokemon_mappings')) {
        poke_hub_seed_regional_pokemon_mappings($force);
    }
    
    // Seed Vivillon patterns (3x18 = 54 entries: one for each Pokémon x each pattern)
    if (function_exists('poke_hub_seed_vivillon_patterns')) {
        poke_hub_seed_vivillon_patterns($force);
    }
    
    // Seed Flabébé evolution line mappings (Flabébé → Floette → Florges with color forms)
    if (function_exists('poke_hub_seed_flabebe_forms')) {
        poke_hub_seed_flabebe_forms($force);
    }
    
    // Seed Shellos evolution line mappings (Shellos → Gastrodon with sea forms)
    if (function_exists('poke_hub_seed_shellos_forms')) {
        poke_hub_seed_shellos_forms($force);
    }
    
    // Seed form-based regional mappings (Tauros Paldea, Basculin, Oricorio)
    if (function_exists('poke_hub_seed_form_based_regional_mappings')) {
        poke_hub_seed_form_based_regional_mappings($force);
    }
    
    // Note: No need to sync mappings to Pokémon anymore - Pokémon read directly from pokemon_regional_mappings (single source of truth)
    
    return true;
}

/**
 * Seed geographic regions
 * 
 * @param bool $force Force update even if data already exists
 */
function poke_hub_seed_regional_regions($force = false) {
    if (!function_exists('poke_hub_pokemon_save_regional_region') || !function_exists('poke_hub_get_regional_regions_data')) {
        return;
    }
    
    $regions_data = poke_hub_get_regional_regions_data();
    
    foreach ($regions_data as $region_data) {
        // Check if region already exists
        if (!$force && function_exists('poke_hub_pokemon_get_regional_regions_from_db')) {
            $existing_regions = poke_hub_pokemon_get_regional_regions_from_db();
            $exists = false;
            foreach ($existing_regions as $existing) {
                if (isset($existing['slug']) && $existing['slug'] === $region_data['slug']) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                continue; // Skip if already exists and not forcing
            }
        }
        
        // Save region (description is always empty by default)
        $region_data['description'] = ''; // Ensure description is empty by default
        poke_hub_pokemon_save_regional_region($region_data, null);
    }
}

/**
 * Get Vivillon patterns data for seeding
 * Returns the static mapping of patterns to countries/regions
 * Now reads from pokemon-regional-data.php (single source of truth)
 * 
 * @return array Array with 'patterns' (array of pattern slugs) and 'mappings' (array of pattern => countries/regions)
 */
function poke_hub_get_vivillon_patterns_data() {
    if (!function_exists('poke_hub_get_all_regional_data')) {
        return [
            'patterns' => [],
            'mappings' => [],
            'pokemon' => [],
        ];
    }
    
    $all_data = poke_hub_get_all_regional_data();
    $vivillon_data = $all_data['vivillon'] ?? [];
    
    return [
        'patterns' => $vivillon_data['patterns'] ?? [],
        'mappings' => $vivillon_data['mappings'] ?? [],
        'pokemon' => $vivillon_data['pokemon'] ?? [],
    ];
}

/**
 * Seed Vivillon patterns (creates 3x18 = 54 entries: one for each Pokémon x each pattern)
 * 
 * @param bool $force Force update even if data already exists
 */
function poke_hub_seed_vivillon_patterns($force = false) {
    $data = poke_hub_get_vivillon_patterns_data();
    $vivillon_patterns = $data['patterns'];
    $static_mapping = $data['mappings'];
    $vivillon_pokemon = $data['pokemon'];
    
    error_log('[POKE-HUB] seed_vivillon_patterns: Starting insertion of 3x18 = 54 Vivillon pattern mappings');
    
    $total_inserted = 0;
    $total_skipped = 0;
    
    // For each pattern
    foreach ($vivillon_patterns as $pattern_slug) {
        if (!isset($static_mapping[$pattern_slug])) {
            continue;
        }
        
        // Separate countries and regions from the mixed array
        $mixed_data = $static_mapping[$pattern_slug];
        $separated = poke_hub_separate_countries_and_regions($mixed_data);
        $countries = $separated['countries'];
        $region_slugs = $separated['region_slugs'];
        
        $pattern_label = ucwords(str_replace(['-', '_'], ' ', $pattern_slug));
        
        // For each Pokémon (Scatterbug, Spewpa, Vivillon)
        foreach ($vivillon_pokemon as $pokemon_data) {
            $dex_number = $pokemon_data['dex_number'];
            $name_en = $pokemon_data['name_en'];
            $name_fr = $pokemon_data['name_fr'];
            $base_slug = $pokemon_data['slug'];
            
            // Pattern slug for mapping: e.g., 'scatterbug-continental', 'spewpa-continental', 'vivillon-continental'
            $mapping_pattern_slug = $base_slug . '-' . $pattern_slug;
            
            // Check if mapping already exists
            $existing_mapping = null;
            $existing_mapping_id = null;
            if (function_exists('poke_hub_pokemon_get_regional_mapping_by_pattern')) {
                $existing_mapping = poke_hub_pokemon_get_regional_mapping_by_pattern($mapping_pattern_slug);
                if (!empty($existing_mapping) && !empty($existing_mapping['id'])) {
                    $existing_mapping_id = (int) $existing_mapping['id'];
                }
            }
            
            // Insert/update logic
            $should_insert = false;
            if ($force) {
                $should_insert = true;
            } elseif (empty($existing_mapping)) {
                $should_insert = true;
            }
            
            if ($should_insert) {
                // IMPORTANT: countries array contains ONLY country names (not regions)
                // region_slugs array contains ONLY region slugs (not country names)
                // Description is NOT set during import - only manually via admin
                $mapping_data = [
                    'pattern_slug' => $mapping_pattern_slug,
                    'countries' => $countries, // Only countries, no regions
                    'region_slugs' => $region_slugs, // Only region slugs, no countries
                ];
                
                // Save or update mapping
                if (function_exists('poke_hub_pokemon_save_regional_mapping')) {
                    $result = poke_hub_pokemon_save_regional_mapping($mapping_data, $existing_mapping_id);
                    if ($result !== false) {
                        $total_inserted++;
                        error_log('[POKE-HUB] seed_vivillon_patterns: ✓ Inserted/updated mapping: ' . $mapping_pattern_slug);
                    } else {
                        error_log('[POKE-HUB] seed_vivillon_patterns: ✗ FAILED to insert mapping: ' . $mapping_pattern_slug);
                    }
                }
            } else {
                $total_skipped++;
                error_log('[POKE-HUB] seed_vivillon_patterns: ⊘ Skipped existing mapping: ' . $mapping_pattern_slug);
            }
        }
    }
    
    error_log('[POKE-HUB] seed_vivillon_patterns: Complete - Inserted: ' . $total_inserted . ', Skipped: ' . $total_skipped . ', Total: ' . ($total_inserted + $total_skipped));
}

/**
 * Seed regional Pokémon mappings from static data
 * Uses pokemon-regional-data.php as SINGLE SOURCE OF TRUTH
 * Seeds ALL regional Pokémon using their EXACT slugs (as stored in database)
 * 
 * NOTE: This function is now DEPRECATED - all mappings are handled by poke_hub_seed_form_based_regional_mappings()
 * This is kept for backward compatibility and migration
 * 
 * @param bool $force Force update even if data already exists
 */
function poke_hub_seed_regional_pokemon_mappings($force = false) {
    // This function is deprecated - all mappings are now done via form_based_mappings with exact slugs
    // The seeding is now handled by poke_hub_seed_form_based_regional_mappings()
    // We keep this function empty for backward compatibility
    return;
}

/**
 * Seed Flabébé evolution line mappings (Flabébé → Floette → Florges with color forms)
 * Reads data from pokemon-regional-data.php
 * Creates mappings for all evolutions x all forms (3 Pokémon x 3 forms = 9 mappings)
 *
 * @param bool $force Force update even if data already exists
 */
function poke_hub_seed_flabebe_forms($force = false) {
    if (!function_exists('poke_hub_get_all_regional_data')) {
        return;
    }

    $all_data = poke_hub_get_all_regional_data();
    $flabebe_data = $all_data['flabebe'] ?? [];
    $flabebe_forms = $flabebe_data['forms'] ?? [];
    $flabebe_pokemon = $flabebe_data['pokemon'] ?? [];

    if (empty($flabebe_forms) || empty($flabebe_pokemon)) {
        return;
    }

    error_log('[POKE-HUB] seed_flabebe_forms: Starting insertion of 3x3 = 9 Flabébé evolution line mappings');

    $total_inserted = 0;
    $total_skipped = 0;

    // For each form (red, blue, yellow)
    foreach ($flabebe_forms as $form_slug => $countries_regions) {
        // Skip empty entries (white/orange are worldwide)
        if (empty($countries_regions) || !is_array($countries_regions)) {
            continue;
        }

        // Separate countries and regions from the mixed array
        $separated = poke_hub_separate_countries_and_regions($countries_regions);
        $countries = $separated['countries'];
        $region_slugs = $separated['region_slugs'];

        // For each Pokémon (Flabébé, Floette, Florges)
        foreach ($flabebe_pokemon as $pokemon_data) {
            $base_slug = $pokemon_data['slug'];

            // Pattern slug for mapping: e.g., 'flabebe-red', 'floette-red', 'florges-red'
            $mapping_pattern_slug = $base_slug . '-' . $form_slug;

            // Check if mapping already exists
            $existing_mapping = null;
            $existing_mapping_id = null;
            if (function_exists('poke_hub_pokemon_get_regional_mapping_by_pattern')) {
                $existing_mapping = poke_hub_pokemon_get_regional_mapping_by_pattern($mapping_pattern_slug);
                if (!empty($existing_mapping) && !empty($existing_mapping['id'])) {
                    $existing_mapping_id = (int) $existing_mapping['id'];
                }
            }

            // Insert/update logic
            $should_insert = false;
            if ($force) {
                $should_insert = true;
            } elseif (empty($existing_mapping)) {
                $should_insert = true;
            }

            if ($should_insert) {
                // IMPORTANT: countries array contains ONLY country names (not regions)
                // region_slugs array contains ONLY region slugs (not country names)
                // Description is NOT set during import - only manually via admin
                $mapping_data = [
                    'pattern_slug' => $mapping_pattern_slug,
                    'countries' => $countries, // Only countries, no regions
                    'region_slugs' => $region_slugs, // Only region slugs, no countries
                ];

                // Save or update mapping
                if (function_exists('poke_hub_pokemon_save_regional_mapping')) {
                    $result = poke_hub_pokemon_save_regional_mapping($mapping_data, $existing_mapping_id);
                    if ($result !== false) {
                        $total_inserted++;
                        error_log('[POKE-HUB] seed_flabebe_forms: ✓ Inserted/updated mapping: ' . $mapping_pattern_slug);
                    } else {
                        error_log('[POKE-HUB] seed_flabebe_forms: ✗ FAILED to insert mapping: ' . $mapping_pattern_slug);
                    }
                }
            } else {
                $total_skipped++;
                error_log('[POKE-HUB] seed_flabebe_forms: ⊘ Skipped existing mapping: ' . $mapping_pattern_slug);
            }
        }
    }

    error_log('[POKE-HUB] seed_flabebe_forms: Complete - Inserted: ' . $total_inserted . ', Skipped: ' . $total_skipped . ', Total: ' . ($total_inserted + $total_skipped));
}

/**
 * Seed Shellos evolution line mappings (Shellos → Gastrodon with sea forms)
 * Reads data from pokemon-regional-data.php
 * Creates mappings for all evolutions x all forms (2 Pokémon x 2 forms = 4 mappings)
 *
 * @param bool $force Force update even if data already exists
 */
function poke_hub_seed_shellos_forms($force = false) {
    if (!function_exists('poke_hub_get_all_regional_data')) {
        return;
    }

    $all_data = poke_hub_get_all_regional_data();
    $shellos_data = $all_data['shellos'] ?? [];
    $shellos_forms = $shellos_data['forms'] ?? [];
    $shellos_pokemon = $shellos_data['pokemon'] ?? [];

    if (empty($shellos_forms) || empty($shellos_pokemon)) {
        return;
    }

    error_log('[POKE-HUB] seed_shellos_forms: Starting insertion of 2x2 = 4 Shellos evolution line mappings');

    $total_inserted = 0;
    $total_skipped = 0;

    // For each form (east-sea, west-sea)
    foreach ($shellos_forms as $form_slug => $countries_regions) {
        if (empty($countries_regions) || !is_array($countries_regions)) {
            continue;
        }

        // Separate countries and regions from the mixed array
        $separated = poke_hub_separate_countries_and_regions($countries_regions);
        $countries = $separated['countries'];
        $region_slugs = $separated['region_slugs'];

        // For each Pokémon (Shellos, Gastrodon)
        foreach ($shellos_pokemon as $pokemon_data) {
            $base_slug = $pokemon_data['slug'];

            // Pattern slug for mapping: e.g., 'shellos-east-sea', 'gastrodon-east-sea'
            $mapping_pattern_slug = $base_slug . '-' . $form_slug;

            // Check if mapping already exists
            $existing_mapping = null;
            $existing_mapping_id = null;
            if (function_exists('poke_hub_pokemon_get_regional_mapping_by_pattern')) {
                $existing_mapping = poke_hub_pokemon_get_regional_mapping_by_pattern($mapping_pattern_slug);
                if (!empty($existing_mapping) && !empty($existing_mapping['id'])) {
                    $existing_mapping_id = (int) $existing_mapping['id'];
                }
            }

            // Insert/update logic
            $should_insert = false;
            if ($force) {
                $should_insert = true;
            } elseif (empty($existing_mapping)) {
                $should_insert = true;
            }

            if ($should_insert) {
                // IMPORTANT: countries array contains ONLY country names (not regions)
                // region_slugs array contains ONLY region slugs (not country names)
                // Description is NOT set during import - only manually via admin
                $mapping_data = [
                    'pattern_slug' => $mapping_pattern_slug,
                    'countries' => $countries, // Only countries, no regions
                    'region_slugs' => $region_slugs, // Only region slugs, no countries
                ];

                // Save or update mapping
                if (function_exists('poke_hub_pokemon_save_regional_mapping')) {
                    $result = poke_hub_pokemon_save_regional_mapping($mapping_data, $existing_mapping_id);
                    if ($result !== false) {
                        $total_inserted++;
                        error_log('[POKE-HUB] seed_shellos_forms: ✓ Inserted/updated mapping: ' . $mapping_pattern_slug);
                    } else {
                        error_log('[POKE-HUB] seed_shellos_forms: ✗ FAILED to insert mapping: ' . $mapping_pattern_slug);
                    }
                }
            } else {
                $total_skipped++;
                error_log('[POKE-HUB] seed_shellos_forms: ⊘ Skipped existing mapping: ' . $mapping_pattern_slug);
            }
        }
    }

    error_log('[POKE-HUB] seed_shellos_forms: Complete - Inserted: ' . $total_inserted . ', Skipped: ' . $total_skipped . ', Total: ' . ($total_inserted + $total_skipped));
}

/**
 * Seed form-based regional Pokémon mappings (single Pokémon forms, not evolution lines)
 * Reads data from pokemon-regional-data.php
 * Seeds mappings for Pokémon with region-specific forms (Tauros Paldea, Basculin, Oricorio)
 *
 * @param bool $force Force update even if data already exists
 */
function poke_hub_seed_form_based_regional_mappings($force = false) {
    if (!function_exists('poke_hub_get_all_regional_data')) {
        return;
    }

    $all_data = poke_hub_get_all_regional_data();
    $form_based_mappings = $all_data['form_based_mappings'] ?? [];

    if (empty($form_based_mappings) || !is_array($form_based_mappings)) {
        return;
    }

    error_log('[POKE-HUB] seed_form_based_regional_mappings: Starting insertion of form-based regional Pokémon mappings');

    $total_inserted = 0;
    $total_skipped = 0;

    foreach ($form_based_mappings as $pattern_slug => $countries_regions) {
        // Skip empty entries (worldwide forms like Flabébé White/Orange)
        if (empty($countries_regions) || !is_array($countries_regions)) {
            continue;
        }

        // Separate countries and regions from the mixed array
        $separated = poke_hub_separate_countries_and_regions($countries_regions);
        $countries = $separated['countries'];
        $region_slugs = $separated['region_slugs'];

        // Check if mapping already exists for this pattern_slug
        $existing_mapping = null;
        $existing_mapping_id = null;
        if (function_exists('poke_hub_pokemon_get_regional_mapping_by_pattern')) {
            $existing_mapping = poke_hub_pokemon_get_regional_mapping_by_pattern($pattern_slug);
            if (!empty($existing_mapping) && !empty($existing_mapping['id'])) {
                $existing_mapping_id = (int) $existing_mapping['id'];
            }
        }

        // Insert/update logic
        $should_insert = false;
        if ($force) {
            $should_insert = true;
        } elseif (empty($existing_mapping)) {
            $should_insert = true;
        }

        if ($should_insert) {
            // IMPORTANT: countries array contains ONLY country names (not regions)
            // region_slugs array contains ONLY region slugs (not country names)
            // Description is NOT set during import - only manually via admin
            $mapping_data = [
                'pattern_slug' => $pattern_slug, // Complete slug: e.g., 'tauros-paldea-combat', 'basculin-red-striped'
                'countries' => $countries, // Only countries, no regions
                'region_slugs' => $region_slugs, // Only region slugs, no countries
            ];

            // Save or update mapping
            if (function_exists('poke_hub_pokemon_save_regional_mapping')) {
                $result = poke_hub_pokemon_save_regional_mapping($mapping_data, $existing_mapping_id);
                if ($result !== false) {
                    $total_inserted++;
                    error_log('[POKE-HUB] seed_form_based_regional_mappings: ✓ Inserted/updated mapping: ' . $pattern_slug);
                } else {
                    error_log('[POKE-HUB] seed_form_based_regional_mappings: ✗ FAILED to insert mapping: ' . $pattern_slug);
                }
            }
        } else {
            $total_skipped++;
            error_log('[POKE-HUB] seed_form_based_regional_mappings: ⊘ Skipped existing mapping: ' . $pattern_slug);
        }
    }

    error_log('[POKE-HUB] seed_form_based_regional_mappings: Complete - Inserted: ' . $total_inserted . ', Skipped: ' . $total_skipped . ', Total: ' . ($total_inserted + $total_skipped));
}

/**
 * Get list of region slugs to identify regions vs countries
 * 
 * @return array Array of region slugs
 */
function poke_hub_get_regional_region_slugs() {
    return [
        'europe', 'asia', 'oceania', 'africa', 'middle-east',
        'north-america', 'south-america', 'central-america', 'caribbean',
        'western-hemisphere', 'eastern-hemisphere', 'northern-hemisphere', 'southern-hemisphere',
        'southeast-asia', 'tropical-regions',
    ];
}

/**
 * Check if a value is a region name (by checking against region names in English and French)
 * 
 * @param string $value Value to check
 * @return string|false Region slug if it's a region, false otherwise
 */
function poke_hub_is_region_name($value) {
    // Map of region names (English and French) to slugs
    // Note: Both English and French names can map to the same slug
    $region_names_to_slugs = [
        // English
        'Europe' => 'europe',
        'Asia' => 'asia',
        'Oceania' => 'oceania',
        'Africa' => 'africa',
        'Middle East' => 'middle-east',
        'North America' => 'north-america',
        'South America' => 'south-america',
        'Central America' => 'central-america',
        'Caribbean' => 'caribbean',
        'Western Hemisphere' => 'western-hemisphere',
        'Eastern Hemisphere' => 'eastern-hemisphere',
        'Northern Hemisphere' => 'northern-hemisphere',
        'Southern Hemisphere' => 'southern-hemisphere',
        'Southeast Asia' => 'southeast-asia',
        'Tropical Regions' => 'tropical-regions',
        // French variants
        'Asie' => 'asia',
        'Océanie' => 'oceania',
        'Afrique' => 'africa',
        'Moyen-Orient' => 'middle-east',
        'Amérique du Nord' => 'north-america',
        'Amérique du Sud' => 'south-america',
        'Amérique centrale' => 'central-america',
        'Caraïbes' => 'caribbean',
        'Hémisphère Ouest' => 'western-hemisphere',
        'Hémisphère Est' => 'eastern-hemisphere',
        'Hémisphère Nord' => 'northern-hemisphere',
        'Hémisphère Sud' => 'southern-hemisphere',
        'Asie du Sud-Est' => 'southeast-asia',
        'Régions tropicales' => 'tropical-regions',
    ];
    
    return isset($region_names_to_slugs[$value]) ? $region_names_to_slugs[$value] : false;
}

/**
 * Separate countries and regions from a mixed array
 * 
 * @param array $mixed_array Array containing both country names and region names
 * @return array Array with 'countries' (country names) and 'region_slugs' (region slugs)
 */
function poke_hub_separate_countries_and_regions($mixed_array) {
    $countries = [];
    $region_slugs = [];
    
    foreach ($mixed_array as $value) {
        $region_slug = poke_hub_is_region_name($value);
        if ($region_slug !== false) {
            // It's a region - add to region_slugs
            if (!in_array($region_slug, $region_slugs, true)) {
                $region_slugs[] = $region_slug;
            }
        } else {
            // It's a country - keep as is
            // NOTE: Countries are currently in French (as stored in Ultimate Member)
            // TODO: Convert to English country names for consistency
            if (!in_array($value, $countries, true)) {
                $countries[] = $value;
            }
        }
    }
    
    return [
        'countries' => $countries, // Only countries, no regions
        'region_slugs' => $region_slugs, // Only region slugs, no countries
    ];
}

