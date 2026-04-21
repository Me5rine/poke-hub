<?php
// modules/blocks/functions/blocks-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tables catalogue + contenu article pour le bloc / métabox « avatar shop ».
 */
function pokehub_blocks_shop_avatar_schema_ready(): bool {
    if (!function_exists('pokehub_get_table') || !function_exists('pokehub_table_exists')) {
        return false;
    }
    foreach (['shop_avatar_items', 'content_shop_avatar', 'content_shop_avatar_entries'] as $key) {
        $t = pokehub_get_table($key);
        if ($t === '' || !pokehub_table_exists($t)) {
            return false;
        }
    }
    return true;
}

/**
 * Tables catalogue + contenu article pour le bloc / métabox « stickers ».
 */
function pokehub_blocks_shop_sticker_schema_ready(): bool {
    if (!function_exists('pokehub_get_table') || !function_exists('pokehub_table_exists')) {
        return false;
    }
    foreach (['shop_sticker_items', 'content_shop_sticker', 'content_shop_sticker_entries'] as $key) {
        $t = pokehub_get_table($key);
        if ($t === '' || !pokehub_table_exists($t)) {
            return false;
        }
    }
    return true;
}

/**
 * Helper pour vérifier si un bloc est disponible
 */
function pokehub_block_is_available($block_name) {
    if (!function_exists('WP_Block_Type_Registry')) {
        return false;
    }
    
    $registry = WP_Block_Type_Registry::get_instance();
    return $registry->is_registered($block_name);
}

/**
 * Helper pour obtenir la liste des blocs disponibles
 */
function pokehub_get_available_blocks() {
    if (!function_exists('WP_Block_Type_Registry')) {
        return [];
    }
    
    $registry = WP_Block_Type_Registry::get_instance();
    $all_blocks = $registry->get_all_registered();
    
    // Filtrer uniquement les blocs Poké HUB
    $pokehub_blocks = [];
    foreach ($all_blocks as $name => $block) {
        if (strpos($name, 'pokehub/') === 0) {
            $pokehub_blocks[$name] = $block;
        }
    }
    
    return $pokehub_blocks;
}

/**
 * Resolve the event label for shop highlight block sentences.
 */
function pokehub_shop_highlights_resolve_event_label(int $post_id): string {
    if ($post_id <= 0) {
        return __('this event', 'poke-hub');
    }

    $pt = get_post_type($post_id);
    if ($pt === 'pokehub_event') {
        $t = wp_strip_all_tags(get_the_title($post_id));
        return $t !== '' ? $t : __('this event', 'poke-hub');
    }

    if (function_exists('pokehub_go_pass_host_link_get_for_post')) {
        $link = pokehub_go_pass_host_link_get_for_post($post_id);
        if (is_array($link) && !empty($link['special_event_id'])) {
            $eid = (int) $link['special_event_id'];
            if ($eid > 0 && function_exists('pokehub_get_table')) {
                global $wpdb;
                $table = pokehub_get_table('special_events');
                if ($table !== '') {
                    $row = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT title, title_fr FROM {$table} WHERE id = %d LIMIT 1",
                            $eid
                        )
                    );
                    if ($row) {
                        $tf  = trim((string) ($row->title_fr ?? ''));
                        $tde = trim((string) ($row->title ?? ''));
                        $pick = $tf !== '' ? $tf : $tde;
                        if ($pick !== '') {
                            return wp_strip_all_tags($pick);
                        }
                    }
                }
            }
        }
    }

    $t = wp_strip_all_tags(get_the_title($post_id));
    return $t !== '' ? $t : __('this event', 'poke-hub');
}

/**
 * @param list<object> $rows Rows from shop_avatar_items or shop_sticker_items.
 * @return list<string>
 */
function pokehub_shop_highlights_collect_item_display_names(array $rows): array {
    $out = [];
    foreach ($rows as $row) {
        if (!is_object($row)) {
            continue;
        }
        $display = trim((string) ($row->name_fr ?? '')) !== '' ? trim((string) ($row->name_fr)) : trim((string) ($row->name_en ?? ''));
        if ($display === '') {
            $display = trim((string) ($row->slug ?? ''));
        }
        if ($display !== '') {
            $out[] = $display;
        }
    }
    return $out;
}







