<?php
// modules/go-search-filters/go-search-filters.php — catalogue administrable des filtres recherche Pokémon GO.

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('poke_hub_is_module_active') || ! poke_hub_is_module_active('go-search-filters')) {
    return;
}

if (is_admin()) {
    require_once __DIR__ . '/admin/go-search-filters-admin.php';
}
