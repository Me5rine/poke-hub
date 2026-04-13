<?php
/**
 * Liaison contenu → Pass GO (special_events) : table locale pokehub_go_pass_host_links.
 * Hôtes : local_post (wp_posts), remote_post (table remote_posts / JV Actu), special_event (ligne special_events).
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return list<string>
 */
function pokehub_go_pass_host_kinds(): array {
    return ['local_post', 'remote_post', 'special_event'];
}

function pokehub_go_pass_host_normalize_kind(string $kind): string {
    $k = sanitize_key($kind);
    return in_array($k, pokehub_go_pass_host_kinds(), true) ? $k : 'local_post';
}

/**
 * @return array{special_event_id: int, display_mode: string}|null
 */
function pokehub_go_pass_host_link_get(string $host_kind, int $host_id): ?array {
    if ($host_id <= 0 || !function_exists('pokehub_get_table')) {
        return null;
    }

    $host_kind = pokehub_go_pass_host_normalize_kind($host_kind);

    global $wpdb;
    $table = pokehub_get_table('go_pass_host_links');
    if ($table !== '' && function_exists('pokehub_table_exists') && pokehub_table_exists($table)) {
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT pass_source_id, display_mode FROM `{$table}` WHERE host_kind = %s AND host_id = %d LIMIT 1",
                $host_kind,
                $host_id
            ),
            ARRAY_A
        );
        if (is_array($row)) {
            $eid = (int) ($row['pass_source_id'] ?? 0);
            $mode = isset($row['display_mode']) && (string) $row['display_mode'] === 'full' ? 'full' : 'summary';
            if ($eid > 0) {
                return ['special_event_id' => $eid, 'display_mode' => $mode];
            }
        }
    }

    if ($host_kind === 'local_post') {
        $eid = (int) get_post_meta($host_id, '_pokehub_go_pass_special_event_id', true);
        $meta_mode = get_post_meta($host_id, '_pokehub_go_pass_display_mode', true);
        if ($eid <= 0) {
            return null;
        }
        $mode = ($meta_mode === 'full') ? 'full' : 'summary';

        return ['special_event_id' => $eid, 'display_mode' => $mode];
    }

    return null;
}

/**
 * Pass GO affiché pour un article en cours d’édition / affiché : selon la métabox
 * (_pokehub_go_pass_host_target + _pokehub_go_pass_host_entity_id).
 *
 * @return array{special_event_id: int, display_mode: string}|null
 */
function pokehub_go_pass_host_link_get_for_post(int $post_id): ?array {
    if ($post_id <= 0) {
        return null;
    }

    $target = get_post_meta($post_id, '_pokehub_go_pass_host_target', true);
    $target = is_string($target) ? sanitize_key($target) : '';

    if ($target === 'remote') {
        $hid = (int) get_post_meta($post_id, '_pokehub_go_pass_host_entity_id', true);
        if ($hid > 0) {
            $link = pokehub_go_pass_host_link_get('remote_post', $hid);
            if ($link) {
                return $link;
            }
        }
        return pokehub_go_pass_host_link_get_post_fallback_pass_meta($post_id);
    }

    if ($target === 'special_event') {
        $hid = (int) get_post_meta($post_id, '_pokehub_go_pass_host_entity_id', true);
        if ($hid > 0) {
            $link = pokehub_go_pass_host_link_get('special_event', $hid);
            if ($link) {
                return $link;
            }
        }
        return pokehub_go_pass_host_link_get_post_fallback_pass_meta($post_id);
    }

    return pokehub_go_pass_host_link_get('local_post', $post_id);
}

/**
 * Repli si la table distante n’est pas dispo : métas pass sur le post courant.
 *
 * @return array{special_event_id: int, display_mode: string}|null
 */
function pokehub_go_pass_host_link_get_post_fallback_pass_meta(int $post_id): ?array {
    $eid = (int) get_post_meta($post_id, '_pokehub_go_pass_special_event_id', true);
    if ($eid <= 0) {
        return null;
    }
    $meta_mode = get_post_meta($post_id, '_pokehub_go_pass_display_mode', true);
    $mode      = ($meta_mode === 'full') ? 'full' : 'summary';

    return ['special_event_id' => $eid, 'display_mode' => $mode];
}

/**
 * @param string $host_kind   local_post | remote_post | special_event
 * @param int    $host_id     ID wp_posts, ID remote_posts, ou id special_events
 * @param int    $eid         id special_events (type go-pass)
 * @param string $mode        summary|full
 * @param string $local_post_type post_type si host_kind = local_post, sinon ''
 */
function pokehub_go_pass_host_link_save(string $host_kind, int $host_id, int $eid, string $mode, string $local_post_type = ''): void {
    if ($host_id <= 0 || !function_exists('pokehub_get_table')) {
        return;
    }
    $host_kind = pokehub_go_pass_host_normalize_kind($host_kind);
    if ($mode !== 'full') {
        $mode = 'summary';
    }

    global $wpdb;
    $table = pokehub_get_table('go_pass_host_links');
    if ($table === '' || !function_exists('pokehub_table_exists') || !pokehub_table_exists($table)) {
        return;
    }

    if ($eid <= 0) {
        $wpdb->delete($table, ['host_kind' => $host_kind, 'host_id' => $host_id], ['%s', '%d']);
        return;
    }

    $host_post_type = '';
    if ($host_kind === 'local_post') {
        $pt = $local_post_type !== '' ? $local_post_type : get_post_type($host_id);
        if ($pt === false || $pt === '') {
            return;
        }
        $host_post_type = (string) $pt;
    }

    $existing = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM `{$table}` WHERE host_kind = %s AND host_id = %d",
            $host_kind,
            $host_id
        )
    );
    $data = [
        'host_post_type'   => $host_post_type,
        'pass_source_type' => 'special_event',
        'pass_source_id'   => $eid,
        'display_mode'     => $mode,
    ];
    if ($existing) {
        $wpdb->update(
            $table,
            $data,
            ['host_kind' => $host_kind, 'host_id' => $host_id],
            ['%s', '%s', '%d', '%s'],
            ['%s', '%d']
        );
    } else {
        $insert = array_merge(
            [
                'host_kind'        => $host_kind,
                'host_id'          => $host_id,
            ],
            $data
        );
        $wpdb->insert(
            $table,
            $insert,
            ['%s', '%d', '%s', '%s', '%d', '%s']
        );
    }
}

function pokehub_go_pass_host_link_delete(string $host_kind, int $host_id): void {
    if ($host_id <= 0 || !function_exists('pokehub_get_table')) {
        return;
    }
    $host_kind = pokehub_go_pass_host_normalize_kind($host_kind);
    global $wpdb;
    $table = pokehub_get_table('go_pass_host_links');
    if ($table === '' || !function_exists('pokehub_table_exists') || !pokehub_table_exists($table)) {
        return;
    }
    $wpdb->delete($table, ['host_kind' => $host_kind, 'host_id' => $host_id], ['%s', '%d']);
}

/**
 * Copie unique des anciennes métas vers la table puis suppression des métas.
 */
function pokehub_go_pass_host_link_maybe_migrate_meta(): void {
    if (get_option('pokehub_go_pass_host_links_meta_migrated_v1', '')) {
        return;
    }
    if (!function_exists('pokehub_get_table') || !function_exists('pokehub_table_exists')) {
        return;
    }
    $table = pokehub_get_table('go_pass_host_links');
    if ($table === '' || !pokehub_table_exists($table)) {
        return;
    }

    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_pokehub_go_pass_special_event_id'",
        ARRAY_A
    );
    if (!is_array($rows)) {
        update_option('pokehub_go_pass_host_links_meta_migrated_v1', '1', false);
        return;
    }

    foreach ($rows as $row) {
        $pid = (int) ($row['post_id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $eid = (int) ($row['meta_value'] ?? 0);
        $mode = get_post_meta($pid, '_pokehub_go_pass_display_mode', true);
        if ($mode !== 'full' && $mode !== 'summary') {
            $mode = 'summary';
        }
        $ptype = get_post_type($pid);
        if ($ptype === false) {
            $ptype = '';
        }
        if ($eid > 0) {
            pokehub_go_pass_host_link_save('local_post', $pid, $eid, (string) $mode, (string) $ptype);
            update_post_meta($pid, '_pokehub_go_pass_host_target', 'local');
            delete_post_meta($pid, '_pokehub_go_pass_host_entity_id');
        } else {
            pokehub_go_pass_host_link_delete('local_post', $pid);
        }
        delete_post_meta($pid, '_pokehub_go_pass_special_event_id');
        delete_post_meta($pid, '_pokehub_go_pass_display_mode');
    }

    update_option('pokehub_go_pass_host_links_meta_migrated_v1', '1', false);
}

add_action('init', 'pokehub_go_pass_host_link_maybe_migrate_meta', 30);

function pokehub_go_pass_host_link_on_delete_post(int $post_id): void {
    $target = get_post_meta($post_id, '_pokehub_go_pass_host_target', true);
    $entity = (int) get_post_meta($post_id, '_pokehub_go_pass_host_entity_id', true);
    if ($target === 'remote' && $entity > 0) {
        pokehub_go_pass_host_link_delete('remote_post', $entity);
    }
    if ($target === 'special_event' && $entity > 0) {
        pokehub_go_pass_host_link_delete('special_event', $entity);
    }
    pokehub_go_pass_host_link_delete('local_post', $post_id);
}
add_action('delete_post', 'pokehub_go_pass_host_link_on_delete_post', 10, 1);
