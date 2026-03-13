<?php
// modules/collections/public/collections-routing.php
// Réécriture d’URL pour /collections/TOKEN ou /pokemon-go/collections/TOKEN

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enregistre la query var pour le jeton de partage.
 */
function poke_hub_collections_register_query_var(array $vars): array {
    $vars[] = 'collection_share';
    return $vars;
}
add_filter('query_vars', 'poke_hub_collections_register_query_var');

/**
 * Ajoute la règle de réécriture : page-collections/TOKEN -> même page avec collection_share=TOKEN.
 */
function poke_hub_collections_add_rewrite_rules() {
    $page_id = get_option('poke_hub_page_collections');
    if (!$page_id || !get_post_status($page_id)) {
        return;
    }
    $url = get_permalink($page_id);
    if (!$url) {
        return;
    }
    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
    if ($path === '') {
        return;
    }
    add_rewrite_rule(
        $path . '/([a-zA-Z0-9]+)/?$',
        'index.php?pagename=' . $path . '&collection_share=$matches[1]',
        'top'
    );
}
add_action('init', 'poke_hub_collections_add_rewrite_rules', 5);
