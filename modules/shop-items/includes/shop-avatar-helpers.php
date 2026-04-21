<?php
/**
 * Catalogue « boutique avatar » (tables sous le préfixe content_source).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * URL de base bucket + chemin dossier vignettes avatar shop.
 */
function poke_hub_shop_avatar_get_assets_base_url(): string {
    $bucket = trim((string) get_option('poke_hub_assets_bucket_base_url', ''));
    if ($bucket === '') {
        return '';
    }
    $path = (string) get_option('poke_hub_assets_path_avatar_shop', '/pokemon-go/avatar-shop/');
    $bucket = rtrim($bucket, '/');
    $path     = '/' . ltrim($path, '/');
    return $bucket . rtrim($path, '/');
}

/**
 * @param object|array<string,mixed> $item
 * @return array{webp: string, png: string}|null
 */
function poke_hub_shop_avatar_build_bucket_urls_for_slug($item): ?array {
    $slug = '';
    if (is_object($item)) {
        $slug = (string) ($item->slug ?? '');
    } elseif (is_array($item)) {
        $slug = (string) ($item['slug'] ?? '');
    }
    $slug = sanitize_title($slug);
    if ($slug === '') {
        return null;
    }
    $base = poke_hub_shop_avatar_get_assets_base_url();
    if ($base === '') {
        return null;
    }
    return [
        'webp' => $base . '/' . $slug . '.webp',
        'png'  => $base . '/' . $slug . '.png',
    ];
}

/**
 * @param object|array<string,mixed> $item
 * @return array{primary: string, fallback: string}
 */
function poke_hub_shop_avatar_get_item_image_urls($item): array {
    $pair = poke_hub_shop_avatar_build_bucket_urls_for_slug($item);
    if ($pair === null) {
        return ['primary' => '', 'fallback' => ''];
    }
    return ['primary' => $pair['webp'], 'fallback' => $pair['png']];
}

/**
 * Libellé d’affichage pour une ligne `special_events` (admin + AJAX).
 */
function poke_hub_shop_avatar_special_event_label(object $row): string {
    $t = trim((string) ($row->title_fr ?? '')) !== '' ? (string) $row->title_fr : (string) ($row->title_en ?? '');
    if ($t === '') {
        $t = (string) ($row->title ?? '');
    }
    if ($t === '') {
        $t = '#' . (int) ($row->id ?? 0);
    }
    return $t;
}

function poke_hub_shop_avatar_db_tables_ready(): bool {
    if (!function_exists('pokehub_get_table') || !function_exists('pokehub_table_exists')) {
        return false;
    }
    $t = pokehub_get_table('shop_avatar_items');
    return $t !== '' && pokehub_table_exists($t);
}

/**
 * @return object|null
 */
function poke_hub_shop_avatar_get_item_by_id(int $id) {
    if ($id <= 0 || !poke_hub_shop_avatar_db_tables_ready()) {
        return null;
    }
    global $wpdb;
    $table = pokehub_get_table('shop_avatar_items');
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
}

/**
 * @param int[] $ids
 * @return array<int, object> id => row
 */
function poke_hub_shop_avatar_get_items_by_ids(array $ids): array {
    $ids = array_values(array_filter(array_map('intval', $ids), static function ($id) {
        return $id > 0;
    }));
    if (empty($ids) || !poke_hub_shop_avatar_db_tables_ready()) {
        return [];
    }
    global $wpdb;
    $table = pokehub_get_table('shop_avatar_items');
    $in    = implode(',', array_fill(0, count($ids), '%d'));
    $sql   = $wpdb->prepare("SELECT * FROM {$table} WHERE id IN ({$in})", ...$ids);
    $rows  = $wpdb->get_results($sql);
    $out   = [];
    foreach ((array) $rows as $r) {
        $out[(int) $r->id] = $r;
    }
    return $out;
}

/**
 * @return list<array{id: int, text: string}>
 */
function poke_hub_shop_avatar_search_items(string $q, int $limit = 40): array {
    if (!poke_hub_shop_avatar_db_tables_ready()) {
        return [];
    }
    global $wpdb;
    $table = pokehub_get_table('shop_avatar_items');
    $limit = max(1, min(80, $limit));
    $like  = '%' . $wpdb->esc_like($q) . '%';
    $rows  = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, name_fr, name_en, slug FROM {$table}
             WHERE name_fr LIKE %s OR name_en LIKE %s OR slug LIKE %s
             ORDER BY name_en ASC, id ASC
             LIMIT %d",
            $like,
            $like,
            $like,
            $limit
        )
    );
    $out = [];
    foreach ((array) $rows as $r) {
        $label = trim((string) $r->name_en) !== '' ? (string) $r->name_en : (string) $r->name_fr;
        if ($label === '') {
            $label = (string) $r->slug;
        }
        $out[] = ['id' => (int) $r->id, 'text' => $label];
    }
    return $out;
}

/**
 * @param array{name_en?: string, name_fr?: string, category_id?: int} $args
 * @return int|\WP_Error
 */
function poke_hub_shop_avatar_create_item(array $args) {
    if (!poke_hub_shop_avatar_db_tables_ready()) {
        return new WP_Error('no_table', __('Shop avatar tables are not available.', 'poke-hub'));
    }
    global $wpdb;
    $name_en = isset($args['name_en']) ? sanitize_text_field((string) $args['name_en']) : '';
    if ($name_en === '') {
        return new WP_Error('name', __('English name is required.', 'poke-hub'));
    }
    $name_fr = isset($args['name_fr']) ? sanitize_text_field((string) $args['name_fr']) : '';
    $slug    = pokehub_slug_base_from_names($name_en, $name_fr, 'item');
    $slug    = pokehub_unique_slug_for_table(pokehub_get_table('shop_avatar_items'), $slug, 0, 'slug', 'id', 'item');
    $cat  = isset($args['category_id']) ? (int) $args['category_id'] : 0;

    $ok = $wpdb->insert(
        pokehub_get_table('shop_avatar_items'),
        [
            'category_id' => $cat,
            'name_fr'     => $name_fr,
            'name_en'     => $name_en,
            'slug'        => $slug,
            'sort_order'  => 0,
        ],
        ['%d', '%s', '%s', '%s', '%d']
    );
    if (!$ok) {
        return new WP_Error('db', __('Could not create shop item.', 'poke-hub'));
    }
    return (int) $wpdb->insert_id;
}

/**
 * @param int[] $event_ids
 */
function poke_hub_shop_avatar_save_item_events(int $item_id, array $event_ids): void {
    if ($item_id <= 0 || !poke_hub_shop_avatar_db_tables_ready()) {
        return;
    }
    global $wpdb;
    $tbl = pokehub_get_table('shop_avatar_item_events');
    $wpdb->delete($tbl, ['item_id' => $item_id], ['%d']);
    $sort = 0;
    foreach ($event_ids as $eid) {
        $eid = (int) $eid;
        if ($eid <= 0) {
            continue;
        }
        $wpdb->insert(
            $tbl,
            [
                'item_id'          => $item_id,
                'special_event_id' => $eid,
                'sort_order'       => $sort++,
            ],
            ['%d', '%d', '%d']
        );
    }
}

/**
 * @return int[]
 */
function poke_hub_shop_avatar_get_item_event_ids(int $item_id): array {
    if ($item_id <= 0 || !poke_hub_shop_avatar_db_tables_ready()) {
        return [];
    }
    global $wpdb;
    $tbl = pokehub_get_table('shop_avatar_item_events');
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT special_event_id FROM {$tbl} WHERE item_id = %d ORDER BY sort_order ASC, id ASC",
        $item_id
    ));
    return array_values(array_filter(array_map('intval', (array) $ids)));
}

/**
 * @return list<array{id: int, text: string}>
 */
function poke_hub_shop_avatar_get_categories_for_select(): array {
    if (!poke_hub_shop_avatar_db_tables_ready()) {
        return [];
    }
    global $wpdb;
    $tbl = pokehub_get_table('shop_avatar_categories');
    $rows = $wpdb->get_results("SELECT id, name_fr, name_en, slug FROM {$tbl} ORDER BY sort_order ASC, id ASC");
    $out = [['id' => 0, 'text' => '—']];
    foreach ((array) $rows as $r) {
        $label = trim((string) $r->name_fr) !== '' ? (string) $r->name_fr : (string) $r->name_en;
        if ($label === '') {
            $label = (string) $r->slug;
        }
        $out[] = ['id' => (int) $r->id, 'text' => $label];
    }
    return $out;
}
