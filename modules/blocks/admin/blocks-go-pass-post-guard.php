<?php
/**
 * Pass GO : liaison dans pokehub_go_pass_host_links (article → pass) + au plus un bloc dans le contenu.
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param array<int, array<string, mixed>> $blocks
 * @param bool                             $kept_go_pass
 * @param int                              $removed_duplicates
 * @param int                              $normalized_blocks nombre de blocs pokehub/go-pass conservés et normalisés
 * @return array<int, array<string, mixed>>
 */
function pokehub_blocks_go_pass_process_walk(array $blocks, bool &$kept_go_pass, int &$removed_duplicates, int &$normalized_blocks): array {
    $out = [];
    foreach ($blocks as $block) {
        $name = isset($block['blockName']) ? (string) $block['blockName'] : '';
        if ($name === 'pokehub/go-pass') {
            if ($kept_go_pass) {
                $removed_duplicates++;
                continue;
            }
            $kept_go_pass = true;
            $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];
            $sid   = (int) ($attrs['specialEventId'] ?? 0);
            $mode  = isset($attrs['displayMode']) ? sanitize_key((string) $attrs['displayMode']) : 'summary';
            if ($mode !== 'full') {
                $mode = 'summary';
            }
            // La métabox est la source de vérité : on nettoie les attributs obsolètes seulement s’ils divergent.
            if ($sid !== 0 || $mode !== 'summary') {
                $block['attrs'] = array_merge($attrs, [
                    'specialEventId' => 0,
                    'displayMode'    => 'summary',
                ]);
                $normalized_blocks++;
            }
        }
        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            $block['innerBlocks'] = pokehub_blocks_go_pass_process_walk(
                $block['innerBlocks'],
                $kept_go_pass,
                $removed_duplicates,
                $normalized_blocks
            );
        }
        $out[] = $block;
    }

    return $out;
}

/**
 * @param array<string, mixed> $data
 * @param array<string, mixed> $postarr
 * @return array<string, mixed>
 */
function pokehub_blocks_go_pass_guard_post_content(array $data, array $postarr): array {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $data;
    }
    $pt = isset($data['post_type']) ? (string) $data['post_type'] : '';
    if ($pt === 'revision') {
        return $data;
    }
    $screens = apply_filters('pokehub_go_pass_metabox_post_types', ['post', 'pokehub_event']);
    if (!in_array($pt, $screens, true)) {
        return $data;
    }
    $content = isset($data['post_content']) ? (string) $data['post_content'] : '';
    if ($content === '' || strpos($content, 'pokehub/go-pass') === false) {
        return $data;
    }
    if (!function_exists('parse_blocks') || !function_exists('serialize_blocks')) {
        return $data;
    }
    $blocks = parse_blocks($content);
    if ($blocks === []) {
        return $data;
    }
    $kept_go_pass        = false;
    $removed_duplicates  = 0;
    $normalized_blocks   = 0;
    $new_blocks          = pokehub_blocks_go_pass_process_walk($blocks, $kept_go_pass, $removed_duplicates, $normalized_blocks);
    $new_content         = serialize_blocks($new_blocks);
    if ($new_content === $content) {
        return $data;
    }
    $data['post_content'] = $new_content;
    if ($removed_duplicates > 0 && is_user_logged_in()) {
        $uid = get_current_user_id();
        if ($uid > 0) {
            set_transient(
                'pokehub_go_pass_guard_' . $uid,
                [
                    'removed' => $removed_duplicates,
                    'post_id' => isset($postarr['ID']) ? (int) $postarr['ID'] : 0,
                ],
                120
            );
        }
    }

    return $data;
}
add_filter('wp_insert_post_data', 'pokehub_blocks_go_pass_guard_post_content', 20, 2);

function pokehub_blocks_go_pass_guard_admin_notice(): void {
    if (!is_user_logged_in()) {
        return;
    }
    $uid = get_current_user_id();
    $key = 'pokehub_go_pass_guard_' . $uid;
    $payload = get_transient($key);
    if (!is_array($payload)) {
        return;
    }
    delete_transient($key);
    $removed = (int) ($payload['removed'] ?? 0);
    if ($removed <= 0) {
        return;
    }
    echo '<div class="notice notice-warning is-dismissible"><p>';
    echo esc_html(
        sprintf(
            /* translators: %d: number of extra GO Pass blocks removed */
            _n(
                '%d extra “GO Pass” block was removed: only one per article is allowed. The pass is chosen in the “GO Pass (block)” box below the editor.',
                '%d extra “GO Pass” blocks were removed: only one per article is allowed. The pass is chosen in the “GO Pass (block)” box below the editor.',
                $removed,
                'poke-hub'
            ),
            $removed
        )
    );
    echo '</p></div>';
}
add_action('admin_notices', 'pokehub_blocks_go_pass_guard_admin_notice');
