<?php
/**
 * Module : articles / bloc — boutique avatar (catégories) + stickers en jeu (sans catégorie), vignettes bucket, liens événements.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('poke_hub_is_module_active') || !poke_hub_is_module_active('shop-items')) {
    return;
}

define('POKE_HUB_SHOP_ITEMS_PATH', __DIR__);
define('POKE_HUB_SHOP_ITEMS_URL', POKE_HUB_URL . 'modules/shop-items/');
/** Slug unique admin : avatar / catégories / stickers via &tab= */
define('POKE_HUB_SHOP_ITEMS_ADMIN_PAGE', 'poke-hub-shop-items');

/**
 * Onglet courant (avatar, categories, stickers).
 */
function poke_hub_shop_items_admin_tab(): string {
    $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';
    if (in_array($tab, ['avatar', 'categories', 'stickers'], true)) {
        return $tab;
    }
    return 'avatar';
}

/**
 * URL admin boutique (onglet + query additionnelle).
 *
 * @param array<string,scalar|null> $args
 */
function poke_hub_shop_items_admin_url(string $tab, array $args = []): string {
    $args = array_merge(['page' => POKE_HUB_SHOP_ITEMS_ADMIN_PAGE, 'tab' => $tab], $args);
    return add_query_arg($args, admin_url('admin.php'));
}

/**
 * Libellé de section (titre principal), aligné sur le module Pokémon.
 */
function poke_hub_shop_items_get_tab_section_label(string $tab): string {
    switch ($tab) {
        case 'avatar':
            return __('Avatar shop', 'poke-hub');
        case 'categories':
            return __('Avatar shop categories', 'poke-hub');
        case 'stickers':
            return __('In-game stickers', 'poke-hub');
        default:
            return __('Shop', 'poke-hub');
    }
}

/**
 * Début du cadre liste : même agencement que `poke_hub_pokemon_admin_ui()` (titre + bouton + onglets + zone contenu).
 *
 * @param array{label: string, url: string}|null $add_button
 */
function poke_hub_shop_items_admin_render_list_frame_start(string $active_tab, ?array $add_button): void {
    $section_label = poke_hub_shop_items_get_tab_section_label($active_tab);

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">' . esc_html($section_label . ' – Poké HUB') . '</h1>';

    if (is_array($add_button) && !empty($add_button['url']) && !empty($add_button['label'])) {
        echo '<a href="' . esc_url((string) $add_button['url']) . '" class="page-title-action">' . esc_html((string) $add_button['label']) . '</a>';
    }

    echo '<hr class="wp-header-end" />';

    $avatar_tabs = [
        'avatar'     => __('Items', 'poke-hub'),
        'categories' => __('Categories', 'poke-hub'),
    ];

    echo '<h2 class="nav-tab-wrapper poke-hub-shop-items-nav">';
    echo '<span class="poke-hub-shop-items-nav__group poke-hub-shop-items-nav__group--avatar" role="presentation">';
    echo '<span class="poke-hub-shop-items-nav__group-title" id="poke-hub-shop-items-nav-avatar-heading">' . esc_html__('Avatar shop', 'poke-hub') . '</span>';
    echo '<span class="poke-hub-shop-items-nav__tabs" role="group" aria-labelledby="poke-hub-shop-items-nav-avatar-heading">';
    foreach ($avatar_tabs as $slug => $label) {
        $url   = poke_hub_shop_items_admin_url($slug);
        $class = 'nav-tab' . ($active_tab === $slug ? ' nav-tab-active' : '');
        printf(
            '<a href="%s" class="%s">%s</a>',
            esc_url($url),
            esc_attr($class),
            esc_html($label)
        );
    }
    echo '</span></span>';

    echo '<span class="poke-hub-shop-items-nav__group poke-hub-shop-items-nav__group--stickers" role="presentation">';
    echo '<span class="poke-hub-shop-items-nav__group-title" id="poke-hub-shop-items-nav-stickers-heading">' . esc_html__('In-game stickers', 'poke-hub') . '</span>';
    echo '<span class="poke-hub-shop-items-nav__tabs" role="group" aria-labelledby="poke-hub-shop-items-nav-stickers-heading">';
    $slug  = 'stickers';
    $label = __('Stickers', 'poke-hub');
    $url   = poke_hub_shop_items_admin_url($slug);
    $class = 'nav-tab' . ($active_tab === $slug ? ' nav-tab-active' : '');
    printf(
        '<a href="%s" class="%s">%s</a>',
        esc_url($url),
        esc_attr($class),
        esc_html($label)
    );
    echo '</span></span>';

    echo '</h2>';

    echo '<div class="poke-hub-shop-items-tab-content" style="margin-top:12px;">';
}

function poke_hub_shop_items_admin_render_list_frame_end(): void {
    echo '</div></div>';
}

/**
 * Titre de l’onglet navigateur (admin), comme le module Pokémon.
 *
 * @param string $admin_title
 * @param string $title
 */
function poke_hub_shop_items_change_admin_title($admin_title, $title = '') {
    unset($title);
    if (!is_admin() || empty($_GET['page']) || sanitize_key((string) wp_unslash($_GET['page'])) !== POKE_HUB_SHOP_ITEMS_ADMIN_PAGE) {
        return $admin_title;
    }
    $tab   = poke_hub_shop_items_admin_tab();
    $label = poke_hub_shop_items_get_tab_section_label($tab);
    $blog  = get_bloginfo('name');

    return $label . ' ‹ ' . $blog . ' — WordPress';
}
add_filter('admin_title', 'poke_hub_shop_items_change_admin_title', 10, 2);

/**
 * Redirection des anciennes URLs (bookmarks / liens externes).
 */
function poke_hub_shop_items_legacy_admin_redirect(): void {
    if (!is_admin() || empty($_GET['page'])) {
        return;
    }
    $page = sanitize_key((string) wp_unslash($_GET['page']));
    $map  = [
        'poke-hub-shop-avatar'             => 'avatar',
        'poke-hub-shop-avatar-categories'  => 'categories',
        'poke-hub-shop-stickers'           => 'stickers',
    ];
    if (!isset($map[$page])) {
        return;
    }
    $tab  = $map[$page];
    $args = [];
    foreach (wp_unslash($_GET) as $k => $v) {
        if ($k === 'page') {
            continue;
        }
        $args[$k] = $v;
    }
    $args['page'] = POKE_HUB_SHOP_ITEMS_ADMIN_PAGE;
    $args['tab']  = $tab;
    wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
    exit;
}
add_action('admin_init', 'poke_hub_shop_items_legacy_admin_redirect', 0);

/**
 * Options d’écran (pagination) selon l’onglet.
 */
function poke_hub_shop_items_admin_dispatch_screen_options(): void {
    if (empty($_GET['page']) || sanitize_key((string) wp_unslash($_GET['page'])) !== POKE_HUB_SHOP_ITEMS_ADMIN_PAGE) {
        return;
    }
    switch (poke_hub_shop_items_admin_tab()) {
        case 'categories':
            poke_hub_shop_avatar_categories_screen_options();
            break;
        case 'stickers':
            poke_hub_shop_sticker_items_screen_options();
            break;
        case 'avatar':
        default:
            poke_hub_shop_avatar_items_screen_options();
            break;
    }
}
add_action('load-poke-hub_page_' . POKE_HUB_SHOP_ITEMS_ADMIN_PAGE, 'poke_hub_shop_items_admin_dispatch_screen_options', 5);

require_once POKE_HUB_SHOP_ITEMS_PATH . '/includes/shop-avatar-helpers.php';
require_once POKE_HUB_SHOP_ITEMS_PATH . '/includes/shop-sticker-helpers.php';

if (is_admin()) {
    require_once POKE_HUB_SHOP_ITEMS_PATH . '/admin/shop-avatar-admin.php';
    require_once POKE_HUB_SHOP_ITEMS_PATH . '/admin/shop-avatar-item-form-ajax.php';
    require_once POKE_HUB_SHOP_ITEMS_PATH . '/admin/shop-sticker-admin.php';
    require_once POKE_HUB_SHOP_ITEMS_PATH . '/admin/shop-sticker-item-form-ajax.php';
}

/**
 * Point d’entrée unique sous Poké HUB.
 */
function poke_hub_shop_items_admin_router(): void {
    if (!current_user_can('manage_options')) {
        return;
    }
    switch (poke_hub_shop_items_admin_tab()) {
        case 'categories':
            poke_hub_shop_avatar_categories_admin_ui();
            return;
        case 'stickers':
            poke_hub_shop_sticker_items_admin_ui();
            return;
        case 'avatar':
        default:
            poke_hub_shop_avatar_items_admin_ui();
            return;
    }
}

/**
 * Sous-menu Poké HUB.
 */
function poke_hub_shop_items_admin_menu(): void {
    if (!current_user_can('manage_options')) {
        return;
    }
    add_submenu_page(
        'poke-hub',
        __('Shop (avatar & stickers)', 'poke-hub'),
        __('Shop', 'poke-hub'),
        'manage_options',
        POKE_HUB_SHOP_ITEMS_ADMIN_PAGE,
        'poke_hub_shop_items_admin_router'
    );
}
add_action('admin_menu', 'poke_hub_shop_items_admin_menu', 17);
