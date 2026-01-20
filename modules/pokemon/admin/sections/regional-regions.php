<?php
// modules/pokemon/admin/sections/regional-regions.php

if (!defined('ABSPATH')) {
    exit;
}

// Load helpers
if (!function_exists('poke_hub_pokemon_get_regional_regions_from_db')) {
    if (file_exists(POKE_HUB_POKEMON_PATH . '/includes/pokemon-regional-db-helpers.php')) {
        require_once POKE_HUB_POKEMON_PATH . '/includes/pokemon-regional-db-helpers.php';
    }
}

// Load list table from Settings (reuse existing)
// Path: Use POKE_HUB_PATH constant if available, otherwise calculate from __DIR__
if (defined('POKE_HUB_PATH')) {
    $plugin_root = POKE_HUB_PATH;
} else {
    // Calculate plugin root: from modules/pokemon/admin/sections/ go up 4 levels to plugin root
    $plugin_root = dirname(dirname(dirname(dirname(__DIR__))));
}

$list_table_path = rtrim($plugin_root, '/\\') . '/includes/settings/class-regional-regions-list-table.php';
$form_path = rtrim($plugin_root, '/\\') . '/includes/settings/forms/regional-region-form.php';

if (file_exists($list_table_path)) {
    require_once $list_table_path;
}

// Load form from Settings (reuse existing)
if (file_exists($form_path)) {
    require_once $form_path;
}

// Ensure regional tables exist
if (class_exists('Pokehub_DB')) {
    Pokehub_DB::getInstance()->ensureRegionalTablesExist();
}

// Handle actions on admin_init (after WordPress is fully loaded)
add_action('admin_init', function() {
    // Only process if we're on the regional_regions section
    if (!isset($_GET['page']) || $_GET['page'] !== 'poke-hub-pokemon') {
        return;
    }
    
    $current_section = isset($_GET['ph_section']) ? sanitize_key($_GET['ph_section']) : '';
    if ($current_section !== 'regional_regions') {
        return;
    }
    
    // Get current action
    $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    
    // Handle delete action (GET)
    if ($action === 'delete' && !empty($_GET['id'])) {
        check_admin_referer('poke_hub_delete_regional_region_' . (int) $_GET['id']);
        
        $id = (int) $_GET['id'];
        if ($id > 0 && function_exists('poke_hub_pokemon_delete_regional_region')) {
            poke_hub_pokemon_delete_regional_region($id);
        }
        
        $redirect = add_query_arg(
            [
                'page'       => 'poke-hub-pokemon',
                'ph_section' => 'regional_regions',
                'ph_msg'     => 'deleted',
            ],
            admin_url('admin.php')
        );
        wp_safe_redirect($redirect);
        exit;
    }
    
    // Handle form submissions (POST)
    if (!empty($_POST['poke_hub_regional_mapping_submit'])) {
        check_admin_referer('poke_hub_regional_mapping_settings', 'poke_hub_regional_mapping_nonce');
        
        $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        
        if ($action === 'save_region') {
            // Save or update a region
            $region_id = isset($_POST['region_id']) ? (int) $_POST['region_id'] : 0;
            $slug = sanitize_key($_POST['region_slug'] ?? '');
            $name_fr = sanitize_text_field($_POST['region_name_fr'] ?? '');
            $name_en = sanitize_text_field($_POST['region_name_en'] ?? '');
            $description = sanitize_textarea_field($_POST['region_description'] ?? '');
            
            // Parse countries JSON
            $countries_json = isset($_POST['region_countries']) ? wp_unslash($_POST['region_countries']) : '[]';
            $countries = json_decode($countries_json, true);
            if (!is_array($countries)) {
                $countries = [];
            }
            
            if (empty($slug) || empty($name_fr) || empty($name_en)) {
                // Error - will be displayed in form
            } else {
                $region_data = [
                    'slug' => $slug,
                    'name_fr' => $name_fr,
                    'name_en' => $name_en,
                    'countries' => $countries,
                    'description' => $description,
                ];
                
                $result = poke_hub_pokemon_save_regional_region($region_data, $region_id > 0 ? $region_id : null);
                if ($result !== false) {
                    $redirect = add_query_arg(
                        [
                            'page'       => 'poke-hub-pokemon',
                            'ph_section' => 'regional_regions',
                            'ph_msg'     => $region_id > 0 ? 'updated' : 'saved',
                        ],
                        admin_url('admin.php')
                    );
                    wp_safe_redirect($redirect);
                    exit;
                }
            }
        }
    }
});

/**
 * Display the regional regions admin screen (list table only)
 * Note: Add/Edit forms are handled in pokemon-admin.php in the first switch case
 */
function poke_hub_pokemon_admin_regional_regions_screen() {
    // Display list table
    $list_table = new Poke_Hub_Regional_Regions_List_Table();
    $list_table->process_bulk_action();
    $list_table->prepare_items();
    
    // Display messages
    if (!empty($_GET['ph_msg'])) {
        $msg = sanitize_key($_GET['ph_msg']);
        if ($msg === 'saved' || $msg === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Saved successfully.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Deleted successfully.', 'poke-hub') . '</p></div>';
        }
    }
    
    echo '<p>' . esc_html__('Manage geographic regions (Europe, Asia, etc.) and their associated countries. These regions are used for regional Pokémon mappings.', 'poke-hub') . '</p>';
    
    ?>
    <form method="get">
        <input type="hidden" name="page" value="poke-hub-pokemon" />
        <input type="hidden" name="ph_section" value="regional_regions" />
        <?php wp_nonce_field('bulk-regional_regions'); ?>
        <?php $list_table->search_box(__('Search', 'poke-hub'), 'regional-regions'); ?>
        <?php $list_table->display(); ?>
    </form>
    <?php
}

