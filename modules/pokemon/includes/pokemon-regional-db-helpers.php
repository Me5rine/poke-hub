<?php
// File: modules/pokemon/includes/pokemon-regional-db-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the table name for regional regions (uses Pokemon table prefix)
 * 
 * @return string Table name
 */
function poke_hub_pokemon_get_regional_regions_table() {
    // Use remote_pokemon prefix if configured, otherwise local
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);
    
    global $wpdb;
    $use_remote = false;
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote = true;
            }
        }
    }
    
    if ($use_remote) {
        return pokehub_get_table('remote_pokemon_regional_regions');
    }
    
    return pokehub_get_table('pokemon_regional_regions');
}

/**
 * Get the table name for regional mappings (uses Pokemon table prefix)
 * 
 * @return string Table name
 */
function poke_hub_pokemon_get_regional_mappings_table() {
    // Use remote_pokemon prefix if configured, otherwise local
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);
    
    global $wpdb;
    $use_remote = false;
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        if (function_exists('poke_hub_pokemon_get_table_prefix')) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote = true;
            }
        }
    }
    
    if ($use_remote) {
        return pokehub_get_table('remote_pokemon_regional_mappings');
    }
    
    return pokehub_get_table('pokemon_regional_mappings');
}

/**
 * Get all regional regions from database
 * 
 * @return array Array of regions with id, slug, name_fr, name_en, countries
 */
function poke_hub_pokemon_get_regional_regions_from_db() {
    global $wpdb;
    
    $table = poke_hub_pokemon_get_regional_regions_table();
    if (empty($table)) {
        return [];
    }
    
    $results = $wpdb->get_results(
        "SELECT id, slug, name_fr, name_en, countries, description 
         FROM {$table} 
         ORDER BY name_fr ASC, slug ASC",
        ARRAY_A
    );
    
    if (empty($results)) {
        return [];
    }
    
    $regions = [];
    foreach ($results as $row) {
        $countries = [];
        if (!empty($row['countries'])) {
            $decoded = json_decode($row['countries'], true);
            if (is_array($decoded)) {
                $countries = $decoded;
            }
        }
        
        $regions[] = [
            'id' => (int) $row['id'],
            'slug' => $row['slug'],
            'name_fr' => $row['name_fr'],
            'name_en' => $row['name_en'],
            'countries' => $countries,
            'description' => $row['description'] ?? '',
        ];
    }
    
    return $regions;
}

/**
 * Get a regional region by slug
 * 
 * @param string $slug Region slug
 * @return array|null Region data or null if not found
 */
function poke_hub_pokemon_get_regional_region_by_slug($slug) {
    global $wpdb;
    
    $table = poke_hub_pokemon_get_regional_regions_table();
    if (empty($table)) {
        return null;
    }
    
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, slug, name_fr, name_en, countries, description 
             FROM {$table} 
             WHERE slug = %s 
             LIMIT 1",
            $slug
        ),
        ARRAY_A
    );
    
    if (empty($row)) {
        return null;
    }
    
    $countries = [];
    if (!empty($row['countries'])) {
        $decoded = json_decode($row['countries'], true);
        if (is_array($decoded)) {
            $countries = $decoded;
        }
    }
    
    return [
        'id' => (int) $row['id'],
        'slug' => $row['slug'],
        'name_fr' => $row['name_fr'],
        'name_en' => $row['name_en'],
        'countries' => $countries,
        'description' => $row['description'] ?? '',
    ];
}

/**
 * Get all regional mappings from database
 * 
 * @return array Array of mappings with pattern_slug, countries, region_slugs
 */
function poke_hub_pokemon_get_regional_mappings_from_db() {
    global $wpdb;
    
    $table = poke_hub_pokemon_get_regional_mappings_table();
    if (empty($table)) {
        return [];
    }
    
    $results = $wpdb->get_results(
        "SELECT id, pattern_slug, countries, region_slugs, description 
         FROM {$table} 
         ORDER BY pattern_slug ASC",
        ARRAY_A
    );
    
    if (empty($results)) {
        return [];
    }
    
    $mappings = [];
    foreach ($results as $row) {
        $countries = [];
        if (!empty($row['countries'])) {
            $decoded = json_decode($row['countries'], true);
            if (is_array($decoded)) {
                $countries = $decoded;
            }
        }
        
        $region_slugs = [];
        if (!empty($row['region_slugs'])) {
            $decoded = json_decode($row['region_slugs'], true);
            if (is_array($decoded)) {
                $region_slugs = $decoded;
            }
        }
        
        $mappings[] = [
            'id' => (int) $row['id'],
            'pattern_slug' => $row['pattern_slug'],
            'countries' => $countries,
            'region_slugs' => $region_slugs,
            'description' => $row['description'] ?? '',
        ];
    }
    
    return $mappings;
}

/**
 * Get a regional mapping by pattern slug
 * 
 * @param string $pattern_slug Pattern slug (e.g., 'continental', 'archipelago')
 * @return array|null Mapping data or null if not found
 */
function poke_hub_pokemon_get_regional_mapping_by_pattern($pattern_slug) {
    global $wpdb;
    
    $table = poke_hub_pokemon_get_regional_mappings_table();
    if (empty($table)) {
        return null;
    }
    
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, pattern_slug, countries, region_slugs, description 
             FROM {$table} 
             WHERE pattern_slug = %s 
             LIMIT 1",
            $pattern_slug
        ),
        ARRAY_A
    );
    
    if (empty($row)) {
        return null;
    }
    
    $countries = [];
    if (!empty($row['countries'])) {
        $decoded = json_decode($row['countries'], true);
        if (is_array($decoded)) {
            $countries = $decoded;
        }
    }
    
    $region_slugs = [];
    if (!empty($row['region_slugs'])) {
        $decoded = json_decode($row['region_slugs'], true);
        if (is_array($decoded)) {
            $region_slugs = $decoded;
        }
    }
    
    return [
        'id' => (int) $row['id'],
        'pattern_slug' => $row['pattern_slug'],
        'countries' => $countries,
        'region_slugs' => $region_slugs,
        'description' => $row['description'] ?? '',
    ];
}

/**
 * Save or update a regional region
 * 
 * @param array $region Region data (slug, name_fr, name_en, countries, description)
 * @param int|null $id Region ID for update, null for insert
 * @return int|false Region ID on success, false on failure
 */
function poke_hub_pokemon_save_regional_region($region, $id = null) {
    global $wpdb;
    
    $table = poke_hub_pokemon_get_regional_regions_table();
    if (empty($table)) {
        return false;
    }
    
    $slug = sanitize_key($region['slug'] ?? '');
    $name_fr = sanitize_text_field($region['name_fr'] ?? '');
    $name_en = sanitize_text_field($region['name_en'] ?? '');
    $countries = isset($region['countries']) && is_array($region['countries']) 
        ? wp_json_encode($region['countries'], JSON_UNESCAPED_UNICODE) 
        : '[]';
    // Description: only set if explicitly provided and non-empty, otherwise force empty
    $description = '';
    if (isset($region['description']) && !empty(trim($region['description']))) {
        $description = sanitize_textarea_field($region['description']);
    }
    
    if (empty($slug)) {
        return false;
    }
    
    $data = [
        'slug' => $slug,
        'name_fr' => $name_fr,
        'name_en' => $name_en,
        'countries' => $countries,
        'description' => $description,
    ];
    
    if ($id !== null && $id > 0) {
        // Update
        $result = $wpdb->update(
            $table,
            $data,
            ['id' => (int) $id],
            ['%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
        return $result !== false ? (int) $id : false;
    } else {
        // Insert
        $result = $wpdb->insert(
            $table,
            $data,
            ['%s', '%s', '%s', '%s', '%s']
        );
        return $result !== false ? (int) $wpdb->insert_id : false;
    }
}

/**
 * Save or update a regional mapping
 * 
 * @param array $mapping Mapping data (pattern_slug, countries, region_slugs, description)
 * @param int|null $id Mapping ID for update, null for insert
 * @return int|false Mapping ID on success, false on failure
 */
function poke_hub_pokemon_save_regional_mapping($mapping, $id = null) {
    global $wpdb;
    
    $table = poke_hub_pokemon_get_regional_mappings_table();
    if (empty($table)) {
        error_log('[POKE-HUB] save_regional_mapping: ERROR - Table name is empty');
        return false;
    }
    
    $pattern_slug = sanitize_key($mapping['pattern_slug'] ?? '');
    $countries = isset($mapping['countries']) && is_array($mapping['countries']) 
        ? wp_json_encode($mapping['countries'], JSON_UNESCAPED_UNICODE) 
        : '[]';
    $region_slugs = isset($mapping['region_slugs']) && is_array($mapping['region_slugs']) 
        ? wp_json_encode($mapping['region_slugs'], JSON_UNESCAPED_UNICODE) 
        : '[]';
    // Description: only set if explicitly provided and non-empty, otherwise force empty
    $description = '';
    if (isset($mapping['description']) && !empty(trim($mapping['description']))) {
        $description = sanitize_textarea_field($mapping['description']);
    }
    
    if (empty($pattern_slug)) {
        error_log('[POKE-HUB] save_regional_mapping: ERROR - pattern_slug is empty');
        return false;
    }
    
    $data = [
        'pattern_slug' => $pattern_slug,
        'countries' => $countries,
        'region_slugs' => $region_slugs,
        'description' => $description,
    ];
    
    if ($id !== null && $id > 0) {
        // Update
        error_log('[POKE-HUB] save_regional_mapping: Updating pattern ' . $pattern_slug . ' (ID: ' . $id . ') in table ' . $table);
        $result = $wpdb->update(
            $table,
            $data,
            ['id' => (int) $id],
            ['%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
        if ($result !== false) {
            error_log('[POKE-HUB] save_regional_mapping: ✓ Successfully updated pattern ' . $pattern_slug . ' (ID: ' . $id . ', rows affected: ' . $result . ')');
            if ($wpdb->last_error) {
                error_log('[POKE-HUB] save_regional_mapping: WARNING - DB error after update: ' . $wpdb->last_error);
            }
            
            // Note: Pokémon now read from pokemon_regional_mappings directly, no need to update them
            // The mapping table is the single source of truth
            
            return (int) $id;
        } else {
            error_log('[POKE-HUB] save_regional_mapping: ✗ FAILED to update pattern ' . $pattern_slug . ' (ID: ' . $id . ') - DB error: ' . ($wpdb->last_error ?: 'unknown'));
            return false;
        }
    } else {
        // Insert
        error_log('[POKE-HUB] save_regional_mapping: Inserting new pattern ' . $pattern_slug . ' in table ' . $table . ' (countries: ' . count($mapping['countries'] ?? []) . ', regions: ' . count($mapping['region_slugs'] ?? []) . ')');
        $result = $wpdb->insert(
            $table,
            $data,
            ['%s', '%s', '%s', '%s']
        );
        if ($result !== false) {
            $insert_id = (int) $wpdb->insert_id;
            error_log('[POKE-HUB] save_regional_mapping: ✓ Successfully inserted pattern ' . $pattern_slug . ' (new ID: ' . $insert_id . ')');
            if ($wpdb->last_error) {
                error_log('[POKE-HUB] save_regional_mapping: WARNING - DB error after insert: ' . $wpdb->last_error);
            }
            
            // Note: Pokémon now read from pokemon_regional_mappings directly, no need to update them
            // The mapping table is the single source of truth
            
            return $insert_id;
        } else {
            error_log('[POKE-HUB] save_regional_mapping: ✗ FAILED to insert pattern ' . $pattern_slug . ' - DB error: ' . ($wpdb->last_error ?: 'unknown'));
            return false;
        }
    }
}

/**
 * Delete a regional region
 * 
 * @param int $id Region ID
 * @return bool True on success, false on failure
 */
function poke_hub_pokemon_delete_regional_region($id) {
    global $wpdb;
    
    $table = poke_hub_pokemon_get_regional_regions_table();
    if (empty($table)) {
        return false;
    }
    
    return $wpdb->delete(
        $table,
        ['id' => (int) $id],
        ['%d']
    ) !== false;
}

/**
 * Delete a regional mapping
 * 
 * @param int $id Mapping ID
 * @return bool True on success, false on failure
 */
function poke_hub_pokemon_delete_regional_mapping($id) {
    global $wpdb;
    
    $table = poke_hub_pokemon_get_regional_mappings_table();
    if (empty($table)) {
        return false;
    }
    
    return $wpdb->delete(
        $table,
        ['id' => (int) $id],
        ['%d']
    ) !== false;
}

