<?php
/**
 * Pass GO : stockage JSON (content_go_pass) + rendu grille type battle pass.
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Slug du type d'événement distant (taxonomie event_type) pour les Pass GO.
 */
function pokehub_go_pass_event_type_slug(): string {
    return (string) apply_filters('pokehub_go_pass_event_type_slug', 'go-pass');
}

/**
 * @param object|string|null $row_or_slug Objet special_events ou slug brut.
 */
function pokehub_is_go_pass_special_event($row_or_slug): bool {
    $slug = '';
    if (is_object($row_or_slug) && isset($row_or_slug->event_type)) {
        $slug = (string) $row_or_slug->event_type;
    } elseif (is_string($row_or_slug)) {
        $slug = $row_or_slug;
    }
    return sanitize_title($slug) === pokehub_go_pass_event_type_slug();
}

/**
 * URL d’édition admin d’un Pass GO (`poke-hub-events` → edit_go_pass).
 * Si le module **Events** n’est pas actif ici, pointe vers le site qui porte les tables
 * content_source (option `siteurl` du préfixe Pokémon / Réglages > Sources).
 *
 * @param int $event_id ID `special_events`.
 */
function pokehub_go_pass_admin_edit_url(int $event_id): string {
    $event_id = (int) $event_id;
    if ($event_id <= 0) {
        return '';
    }

    $path = 'admin.php?page=poke-hub-events&action=edit_go_pass&event_id=' . $event_id;

    if (function_exists('poke_hub_is_module_active') && poke_hub_is_module_active('events')) {
        return admin_url($path);
    }

    $base = function_exists('pokehub_content_source_get_remote_wp_base_url')
        ? pokehub_content_source_get_remote_wp_base_url()
        : '';
    if ($base !== '') {
        return trailingslashit($base) . 'wp-admin/' . $path;
    }

    if (!function_exists('pokehub_events_get_remote_wp_base_url')) {
        $helpers = defined('POKE_HUB_PATH') ? POKE_HUB_PATH . 'modules/events/functions/events-admin-helpers.php' : '';
        if ($helpers !== '' && is_readable($helpers)) {
            require_once $helpers;
        }
    }
    if (function_exists('pokehub_events_get_remote_wp_base_url')) {
        $base = pokehub_events_get_remote_wp_base_url();
        if ($base !== '') {
            return trailingslashit($base) . 'wp-admin/' . $path;
        }
    }

    $base = (string) apply_filters('pokehub_go_pass_admin_edit_base_url', '', $event_id);
    if ($base !== '') {
        return trailingslashit(untrailingslashit(esc_url_raw($base))) . '/wp-admin/' . $path;
    }

    return admin_url($path);
}

/**
 * @return string[]
 */
function pokehub_go_pass_textarea_to_lines($text): array {
    if (!is_string($text)) {
        return [];
    }
    $parts = preg_split('/\r\n|\r|\n/', $text);
    if (!is_array($parts)) {
        return [];
    }
    $out = [];
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }
        $out[] = sanitize_text_field($part);
    }
    return $out;
}

/**
 * @param string[] $lines
 */
function pokehub_go_pass_lines_to_textarea(array $lines): string {
    return implode("\n", array_map('strval', $lines));
}

function pokehub_go_pass_parse_datetime_local(string $raw): int {
    $raw = trim($raw);
    if ($raw === '') {
        return 0;
    }
    try {
        $tz = wp_timezone();
        $dt = new DateTime($raw, $tz);
        return $dt->getTimestamp();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Types de récompense Pass GO (alignés sur les quêtes Field Research).
 *
 * @return string[]
 */
function pokehub_go_pass_reward_type_slugs(): array {
    return ['pokemon', 'stardust', 'xp', 'candy', 'xl_candy', 'mega_energy', 'item', 'bonus'];
}

/**
 * Normalise une entrée récompense (JSON ou POST déjà typé).
 *
 * @param array<string, mixed> $r
 * @return array<string, mixed>|null null si entrée vide / invalide
 */
function pokehub_go_pass_normalize_reward_array(array $r): ?array {
    $type = isset($r['type']) ? sanitize_key((string) $r['type']) : '';
    if (!in_array($type, pokehub_go_pass_reward_type_slugs(), true)) {
        return null;
    }

    $qty = isset($r['quantity']) ? max(1, (int) $r['quantity']) : 1;

    $attach_featured = static function (array $out, array $src): array {
        if (!empty($src['featured'])) {
            $out['featured'] = true;
        }

        return $out;
    };

    if ($type === 'bonus') {
        $bid = isset($r['bonus_id']) ? (int) $r['bonus_id'] : 0;
        if ($bid <= 0) {
            return null;
        }
        $out = [
            'type'     => 'bonus',
            'bonus_id' => $bid,
        ];
        $desc = isset($r['description']) ? sanitize_textarea_field((string) $r['description']) : '';
        if ($desc !== '') {
            $out['description'] = $desc;
        }

        return $attach_featured($out, $r);
    }

    if ($type === 'pokemon') {
        $raw_pid = isset($r['pokemon_id']) ? (string) $r['pokemon_id'] : '';
        $pid = 0;
        $gender_from_token = null;
        if ($raw_pid !== '' && preg_match('/^(\d+)\|(male|female)$/i', $raw_pid, $m)) {
            $pid = (int) $m[1];
            $gender_from_token = strtolower((string) $m[2]);
        } else {
            $pid = (int) $raw_pid;
        }
        if ($pid <= 0) {
            return null;
        }

        $out = [
            'type'       => 'pokemon',
            'pokemon_id' => $pid,
            'quantity'   => 1,
        ];
        $pokemon_bool_flags = ['force_shiny', 'force_shadow', 'force_dynamax', 'force_gigamax'];
        foreach ($pokemon_bool_flags as $flag) {
            if (!empty($r[$flag])) {
                $out[$flag] = true;
            }
        }
        $gender = isset($r['gender']) ? sanitize_key((string) $r['gender']) : '';
        if (!in_array($gender, ['male', 'female'], true) && $gender_from_token !== null && in_array($gender_from_token, ['male', 'female'], true)) {
            $gender = $gender_from_token;
        }
        if (in_array($gender, ['male', 'female'], true)) {
            $out['gender'] = $gender;
        }

        return $attach_featured($out, $r);
    }

    if ($type === 'stardust' || $type === 'xp') {
        return $attach_featured(
            [
                'type'     => $type,
                'quantity' => $qty,
            ],
            $r
        );
    }

    if ($type === 'item') {
        $iid = isset($r['item_id']) ? (int) $r['item_id'] : 0;
        if ($iid <= 0) {
            return null;
        }

        return $attach_featured(
            [
                'type'     => 'item',
                'item_id'  => $iid,
                'quantity' => $qty,
            ],
            $r
        );
    }

    $raw_res = isset($r['pokemon_id']) ? (string) $r['pokemon_id'] : '';
    if (function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')) {
        $parsed_res = pokehub_parse_post_pokemon_multiselect_tokens_with_genders(
            $raw_res !== '' ? [$raw_res] : [],
            null
        );
        $pid = (int) ($parsed_res['pokemon_ids'][0] ?? 0);
    } else {
        $pid = isset($r['pokemon_id']) ? (int) $r['pokemon_id'] : 0;
    }
    if ($pid <= 0) {
        return null;
    }

    return $attach_featured(
        [
            'type'       => $type,
            'pokemon_id' => $pid,
            'quantity'   => $qty,
        ],
        $r
    );
}

/**
 * @param array<int, mixed>|null $list
 * @return array<int, array<string, mixed>>
 */
function pokehub_go_pass_normalize_rewards_list(?array $list): array {
    if (!is_array($list) || $list === []) {
        return [];
    }
    $out = [];
    foreach ($list as $r) {
        if (!is_array($r)) {
            continue;
        }
        $n = pokehub_go_pass_normalize_reward_array($r);
        if ($n !== null) {
            $out[] = $n;
        }
    }

    return $out;
}

/**
 * @param array<int, mixed>|null $raw
 * @return array<int, array<string, mixed>>
 */
function pokehub_go_pass_parse_rewards_list_from_post(?array $raw): array {
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $n = pokehub_go_pass_normalize_reward_array($row);
        if ($n !== null) {
            $out[] = $n;
        }
    }

    return $out;
}

/**
 * Indique si les récompenses structurées d’une ligne éditeur contiennent au moins un type « bonus ».
 *
 * @param array<string, mixed> $row Ligne éditeur (tier / milestone) avec free_rewards / premium_rewards.
 */
function pokehub_go_pass_reward_arrays_contain_bonus_type(array $row): bool {
    foreach (['free_rewards', 'premium_rewards'] as $key) {
        if (empty($row[$key]) || !is_array($row[$key])) {
            continue;
        }
        foreach ($row[$key] as $r) {
            if (!is_array($r)) {
                continue;
            }
            $t = isset($r['type']) ? sanitize_key((string) $r['type']) : '';
            if ($t === 'bonus') {
                return true;
            }
        }
    }

    return false;
}

/**
 * Ligne POST gp_tiers[] : palier bonus si une récompense saisie est de type bonus.
 *
 * @param array<string, mixed> $row
 */
function pokehub_go_pass_post_tier_row_has_bonus_reward(array $row): bool {
    foreach (['free_rewards', 'premium_rewards'] as $key) {
        if (empty($row[$key]) || !is_array($row[$key])) {
            continue;
        }
        foreach ($row[$key] as $sub) {
            if (!is_array($sub)) {
                continue;
            }
            $t = isset($sub['type']) ? sanitize_key((string) $sub['type']) : '';
            if ($t === 'bonus') {
                return true;
            }
        }
    }

    return false;
}

/**
 * Texte d’affichage d’une récompense (liste HTML).
 *
 * @param array<string, mixed> $reward
 */
function pokehub_go_pass_format_reward_display(array $reward): string {
    $type = isset($reward['type']) ? sanitize_key((string) $reward['type']) : '';
    if ($type === 'bonus') {
        $bid = (int) ($reward['bonus_id'] ?? 0);
        if ($bid <= 0) {
            return '';
        }
        $title = '';
        if (function_exists('pokehub_get_bonus_data')) {
            $bd = pokehub_get_bonus_data($bid);
            if ($bd) {
                $title = (string) ($bd['title'] ?? '');
            }
        }
        if ($title === '') {
            $title = sprintf(/* translators: %d: bonus catalogue id */ __('Bonus #%d', 'poke-hub'), $bid);
        }
        $desc = isset($reward['description']) ? trim((string) $reward['description']) : '';
        if ($desc !== '') {
            return $title . ' — ' . $desc;
        }

        return $title;
    }

    if ($type === 'pokemon') {
        $pid = (int) ($reward['pokemon_id'] ?? 0);
        if ($pid <= 0) {
            return '';
        }
        $base = '';
        if (function_exists('pokehub_get_pokemon_data_by_id')) {
            $p = pokehub_get_pokemon_data_by_id($pid);
            if ($p) {
                $name = (string) ($p['name_fr'] ?? $p['name_en'] ?? $p['name'] ?? '');
                if ($name !== '') {
                    $base = $name;
                }
            }
        }
        if ($base === '') {
            $base = (string) sprintf(/* translators: %d: Pokémon ID */ __('Pokémon #%d', 'poke-hub'), $pid);
        }

        $tags = [];
        if (!empty($reward['force_shiny'])) {
            $tags[] = __('Shiny', 'poke-hub');
        }
        if (!empty($reward['force_shadow'])) {
            $tags[] = __('Shadow', 'poke-hub');
        }
        if (!empty($reward['force_dynamax'])) {
            $tags[] = __('Dynamax', 'poke-hub');
        }
        if (!empty($reward['force_gigamax'])) {
            $tags[] = __('Gigamax', 'poke-hub');
        }

        if ($tags !== []) {
            return $base . ' (' . implode(', ', $tags) . ')';
        }

        return $base;
    }

    if (function_exists('pokehub_field_research_format_other_reward_line')) {
        return pokehub_field_research_format_other_reward_line($reward);
    }

    return __('Reward', 'poke-hub');
}

/**
 * @param array<string, mixed> $tier_or_milestone
 * @param string               $side free|premium
 * @return string HTML <li>…</li> fragments
 */
function pokehub_go_pass_render_reward_lis_html(array $tier_or_milestone, string $side): string {
    $side = ($side === 'premium') ? 'premium' : 'free';
    $key  = $side . '_rewards';
    $list = isset($tier_or_milestone[$key]) && is_array($tier_or_milestone[$key])
        ? pokehub_go_pass_normalize_rewards_list($tier_or_milestone[$key])
        : [];

    $html = '';
    foreach ($list as $rw) {
        $t = esc_html(pokehub_go_pass_format_reward_display($rw));
        if ($t !== '') {
            $li_class = !empty($rw['featured']) ? ' class="pokehub-go-pass-reward--featured"' : '';
            $html     .= '<li' . $li_class . '>' . $t . '</li>';
        }
    }

    $legacy_key = $side;
    if ($html === '' && !empty($tier_or_milestone[$legacy_key]) && is_array($tier_or_milestone[$legacy_key])) {
        foreach (pokehub_go_pass_normalize_cell_lines($tier_or_milestone[$legacy_key]) as $line) {
            $html .= '<li>' . esc_html($line) . '</li>';
        }
    }

    return $html;
}

/**
 * Données structurées pour une carte récompense (rendu battle pass).
 *
 * @param array<string, mixed> $reward
 * @return array{type_class:string,title:string,subtitle:string,qty:string,img:string,featured:bool}|null
 */
function pokehub_go_pass_reward_card_data(array $reward): ?array {
    $n = pokehub_go_pass_normalize_reward_array($reward);
    if ($n === null) {
        return null;
    }

    $type         = (string) ($n['type'] ?? '');
    $qty          = max(1, (int) ($n['quantity'] ?? 1));
    $qty_label    = '×' . (function_exists('number_format_i18n') ? number_format_i18n($qty) : (string) $qty);
    $featured     = !empty($n['featured']);
    $img          = '';
    $title        = '';
    $subtitle     = '';
    $type_class   = 'pokehub-go-pass-card--' . preg_replace('/[^a-z0-9_-]/', '', $type);

    switch ($type) {
        case 'xp':
            $title = __('XP', 'poke-hub');
            break;
        case 'stardust':
            $title = __('Stardust', 'poke-hub');
            break;
        case 'item':
            $iid = (int) ($n['item_id'] ?? 0);
            $title = __('Item', 'poke-hub');
            if ($iid > 0 && function_exists('pokehub_get_item_data_by_id')) {
                $it = pokehub_get_item_data_by_id($iid);
                if ($it) {
                    $title = (string) ($it['name'] ?? $it['name_fr'] ?? $it['name_en'] ?? $title);
                }
            }
            break;
        case 'pokemon':
            $pid = (int) ($n['pokemon_id'] ?? 0);
            $title = __('Pokémon appears!', 'poke-hub');
            $name  = '';
            if ($pid > 0 && function_exists('pokehub_get_pokemon_data_by_id')) {
                $p = pokehub_get_pokemon_data_by_id($pid);
                if (is_array($p)) {
                    $name = (string) ($p['name_fr'] ?? $p['name_en'] ?? $p['name'] ?? '');
                    if (function_exists('poke_hub_pokemon_get_image_url')) {
                        $imgArgs = [
                            'shiny' => !empty($n['force_shiny']),
                        ];
                        if (!empty($n['gender']) && in_array($n['gender'], ['male', 'female'], true)) {
                            $imgArgs['gender'] = (string) $n['gender'];
                        }
                        $u = poke_hub_pokemon_get_image_url($p, $imgArgs);
                        $img = is_string($u) ? $u : '';
                    }
                }
            }
            if ($name === '') {
                $name = sprintf(/* translators: %d: Pokémon ID */ __('Pokémon #%d', 'poke-hub'), $pid);
            }
            $tags = [];
            if (!empty($n['force_shiny'])) {
                $tags[] = __('Shiny', 'poke-hub');
            }
            if (!empty($n['force_shadow'])) {
                $tags[] = __('Shadow', 'poke-hub');
            }
            if (!empty($n['force_dynamax'])) {
                $tags[] = __('Dynamax', 'poke-hub');
            }
            if (!empty($n['force_gigamax'])) {
                $tags[] = __('Gigamax', 'poke-hub');
            }
            if (!empty($n['gender']) && $n['gender'] === 'female') {
                $tags[] = __('Female', 'poke-hub');
            } elseif (!empty($n['gender']) && $n['gender'] === 'male') {
                $tags[] = __('Male', 'poke-hub');
            }
            $subtitle = $name . ($tags !== [] ? ' · ' . implode(' · ', $tags) : '');
            break;
        case 'candy':
        case 'xl_candy':
        case 'mega_energy':
            $pid = (int) ($n['pokemon_id'] ?? 0);
            $slug = '';
            $pname = '';
            if ($pid > 0 && function_exists('pokehub_get_pokemon_data_by_id')) {
                $p = pokehub_get_pokemon_data_by_id($pid);
                if (is_array($p)) {
                    $slug  = isset($p['slug']) ? sanitize_title((string) $p['slug']) : '';
                    $pname = (string) ($p['name_fr'] ?? $p['name_en'] ?? $p['name'] ?? '');
                }
            }
            if ($type === 'candy') {
                $title = $pname !== '' ? sprintf(/* translators: %s: Pokémon name */ __('%s Candy', 'poke-hub'), $pname) : __('Candy', 'poke-hub');
                if ($slug !== '' && function_exists('poke_hub_get_pokemon_candy_image_url')) {
                    $img = poke_hub_get_pokemon_candy_image_url($slug);
                }
            } elseif ($type === 'xl_candy') {
                $title = $pname !== '' ? sprintf(/* translators: %s: Pokémon name */ __('%s XL Candy', 'poke-hub'), $pname) : __('XL Candy', 'poke-hub');
                if ($slug !== '' && function_exists('poke_hub_get_pokemon_xl_candy_image_url')) {
                    $img = poke_hub_get_pokemon_xl_candy_image_url($slug);
                }
            } else {
                $title = $pname !== '' ? sprintf(/* translators: %s: Pokémon name */ __('%s Mega Energy', 'poke-hub'), $pname) : __('Mega Energy', 'poke-hub');
                if ($slug !== '' && function_exists('poke_hub_get_pokemon_mega_energy_image_url')) {
                    $img = poke_hub_get_pokemon_mega_energy_image_url($slug);
                }
            }
            break;
        case 'bonus':
            $bid = (int) ($n['bonus_id'] ?? 0);
            $title = sprintf(/* translators: %d: bonus id */ __('Bonus #%d', 'poke-hub'), $bid);
            if (function_exists('pokehub_get_bonus_data')) {
                $bd = pokehub_get_bonus_data($bid);
                if (is_array($bd) && !empty($bd['title'])) {
                    $title = (string) $bd['title'];
                }
                if (is_array($bd) && !empty($bd['image_url'])) {
                    $img = (string) $bd['image_url'];
                }
            }
            $desc = isset($n['description']) ? trim((string) $n['description']) : '';
            if ($desc !== '') {
                $subtitle = $desc;
            }
            $qty_label = '';
            break;
        default:
            $title = function_exists('pokehub_field_research_format_other_reward_line')
                ? pokehub_field_research_format_other_reward_line($n)
                : pokehub_go_pass_format_reward_display($n);
            $qty_label = '';
            $subtitle  = '';
            break;
    }

    $out = [
        'type_class' => $type_class,
        'title'      => $title,
        'subtitle'   => $subtitle,
        'qty'        => $qty_label,
        'img'        => $img,
        'featured'   => $featured,
    ];

    $filtered = apply_filters('pokehub_go_pass_reward_card_data', $out, $n);

    return is_array($filtered) ? array_merge($out, array_intersect_key($filtered, $out)) : $out;
}

/**
 * Icône cadenas Deluxe (décoratif).
 */
function pokehub_go_pass_deluxe_lock_svg(): string {
    return '<svg class="pokehub-go-pass-card__lock-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>';
}

/**
 * @param array{type_class:string,title:string,subtitle:string,qty:string,img:string,featured:bool} $data
 * @param string                                                                                    $side free|premium
 */
function pokehub_go_pass_render_reward_card_html(array $data, string $side): string {
    $side       = ($side === 'premium') ? 'premium' : 'free';
    $side_theme = ($side === 'premium') ? 'deluxe' : 'basic';
    $feat_class = !empty($data['featured']) ? ' pokehub-go-pass-card--featured' : '';
    $img_html   = '';
    if (!empty($data['img'])) {
        $img_html = '<div class="pokehub-go-pass-card__visual"><img src="' . esc_url((string) $data['img']) . '" alt="" loading="lazy" decoding="async" width="80" height="80"></div>';
    } else {
        $img_html = '<div class="pokehub-go-pass-card__visual pokehub-go-pass-card__visual--placeholder" aria-hidden="true"><span class="pokehub-go-pass-card__glyph"></span></div>';
    }
    $subtitle = isset($data['subtitle']) ? trim((string) $data['subtitle']) : '';
    $sub_html = $subtitle !== '' ? '<p class="pokehub-go-pass-card__subtitle">' . esc_html($subtitle) . '</p>' : '';
    $qty      = isset($data['qty']) ? trim((string) $data['qty']) : '';
    $qty_html = $qty !== '' ? '<p class="pokehub-go-pass-card__qty">' . esc_html($qty) . '</p>' : '';

    $lock = ($side === 'premium') ? '<span class="pokehub-go-pass-card__lock">' . pokehub_go_pass_deluxe_lock_svg() . '</span>' : '';

    return '<article class="pokehub-go-pass-card ' . esc_attr((string) $data['type_class']) . $feat_class . ' pokehub-go-pass-card--' . esc_attr($side_theme) . '">'
        . '<div class="pokehub-go-pass-card__inner">'
        . '<div class="pokehub-go-pass-card__text">'
        . '<h4 class="pokehub-go-pass-card__title">' . esc_html((string) $data['title']) . '</h4>'
        . $sub_html
        . $qty_html
        . '</div>'
        . $img_html
        . '</div>'
        . $lock
        . '</article>';
}

/**
 * Colonne Basic ou Deluxe : pile de cartes + lignes legacy texte.
 *
 * @param array<string, mixed> $tier_or_milestone
 * @param string               $side              free|premium
 */
function pokehub_go_pass_render_reward_cards_column_html(array $tier_or_milestone, string $side): string {
    $side = ($side === 'premium') ? 'premium' : 'free';
    $key  = $side . '_rewards';
    $list = isset($tier_or_milestone[$key]) && is_array($tier_or_milestone[$key])
        ? pokehub_go_pass_normalize_rewards_list($tier_or_milestone[$key])
        : [];

    $cell_mod = ($side === 'premium') ? 'deluxe' : 'basic';
    $html     = '<div class="pokehub-go-pass-track__cell pokehub-go-pass-track__cell--' . esc_attr($cell_mod) . '">';
    $html    .= '<div class="pokehub-go-pass-track__stack">';

    $any = false;
    foreach ($list as $rw) {
        if (!is_array($rw)) {
            continue;
        }
        $data = pokehub_go_pass_reward_card_data($rw);
        if ($data !== null) {
            $html .= pokehub_go_pass_render_reward_card_html($data, $side);
            $any    = true;
        }
    }

    $legacy_key = $side;
    if (isset($tier_or_milestone[$legacy_key]) && is_array($tier_or_milestone[$legacy_key])) {
        foreach (pokehub_go_pass_normalize_cell_lines($tier_or_milestone[$legacy_key]) as $line) {
            if ($line === '') {
                continue;
            }
            $data = [
                'type_class' => 'pokehub-go-pass-card--legacy',
                'title'      => $line,
                'subtitle'   => '',
                'qty'        => '',
                'img'        => '',
                'featured'   => false,
            ];
            $html .= pokehub_go_pass_render_reward_card_html($data, $side);
            $any   = true;
        }
    }

    if (!$any) {
        $empty_theme = ($side === 'premium') ? 'deluxe' : 'basic';
        $html .= '<div class="pokehub-go-pass-card pokehub-go-pass-card--empty pokehub-go-pass-card--' . esc_attr($empty_theme) . '"><span class="pokehub-go-pass-card__empty-dash">—</span></div>';
    }

    $html .= '</div></div>';

    return $html;
}

/**
 * Récompenses marquées « en avant » pour le résumé (ordre : paliers standards puis paliers bonus).
 *
 * @param array<string, mixed> $data Payload normalisé.
 * @return array<int, array{side:string, text:string}>
 */
function pokehub_go_pass_collect_featured_reward_lines(array $data): array {
    $data = pokehub_go_pass_normalize_payload($data);
    $out  = [];

    $push_side = static function (array $cell, string $side) use (&$out): void {
        $side = ($side === 'premium') ? 'premium' : 'free';
        $key  = $side . '_rewards';
        if (empty($cell[$key]) || !is_array($cell[$key])) {
            return;
        }
        foreach (pokehub_go_pass_normalize_rewards_list($cell[$key]) as $rw) {
            if (empty($rw['featured'])) {
                continue;
            }
            $text = pokehub_go_pass_format_reward_display($rw);
            if ($text === '') {
                continue;
            }
            $out[] = [
                'side' => $side,
                'text' => $text,
            ];
        }
    };

    foreach ($data['tiers'] as $tier) {
        if (is_array($tier)) {
            $push_side($tier, 'free');
            $push_side($tier, 'premium');
        }
    }
    foreach ($data['milestones'] as $m) {
        if (is_array($m)) {
            $push_side($m, 'free');
            $push_side($m, 'premium');
        }
    }

    return $out;
}

/**
 * Construit le payload Pass depuis $_POST (formulaire admin dédié).
 *
 * @return array<string, mixed>
 */
function pokehub_go_pass_build_payload_from_post(): array {
    $p = pokehub_go_pass_default_payload();

    $event_mode = (isset($_POST['event']['mode']) && (string) wp_unslash($_POST['event']['mode']) === 'fixed')
        ? 'fixed'
        : 'local';

    $p['points_per_rank'] = isset($_POST['gp_points_per_rank'])
        ? max(1, (int) $_POST['gp_points_per_rank'])
        : 100;

    $p['rewards_claim_end_ts'] = 0;
    if (!empty($_POST['gp_rewards_claim_end_date']) && function_exists('poke_hub_special_event_parse_date_time_for_save')) {
        $p['rewards_claim_end_ts'] = poke_hub_special_event_parse_date_time_for_save(
            trim((string) wp_unslash($_POST['gp_rewards_claim_end_date'])),
            isset($_POST['gp_rewards_claim_end_time']) ? trim((string) wp_unslash($_POST['gp_rewards_claim_end_time'])) : '',
            $event_mode
        );
    } elseif (!empty($_POST['gp_rewards_claim_end'])) {
        $raw = trim((string) wp_unslash($_POST['gp_rewards_claim_end']));
        $p['rewards_claim_end_ts'] = function_exists('poke_hub_special_event_parse_datetime')
            ? poke_hub_special_event_parse_datetime($raw, $event_mode)
            : pokehub_go_pass_parse_datetime_local($raw);
    }

    $p['unlimited_start_ts'] = 0;
    if (!empty($_POST['gp_unlimited_start_date']) && function_exists('poke_hub_special_event_parse_date_time_for_save')) {
        $p['unlimited_start_ts'] = poke_hub_special_event_parse_date_time_for_save(
            trim((string) wp_unslash($_POST['gp_unlimited_start_date'])),
            isset($_POST['gp_unlimited_start_time']) ? trim((string) wp_unslash($_POST['gp_unlimited_start_time'])) : '',
            $event_mode
        );
    } elseif (!empty($_POST['gp_unlimited_start'])) {
        $raw = trim((string) wp_unslash($_POST['gp_unlimited_start']));
        $p['unlimited_start_ts'] = function_exists('poke_hub_special_event_parse_datetime')
            ? poke_hub_special_event_parse_datetime($raw, $event_mode)
            : pokehub_go_pass_parse_datetime_local($raw);
    }

    $p['unlimited_end_ts'] = 0;
    if (!empty($_POST['gp_unlimited_end_date']) && function_exists('poke_hub_special_event_parse_date_time_for_save')) {
        $p['unlimited_end_ts'] = poke_hub_special_event_parse_date_time_for_save(
            trim((string) wp_unslash($_POST['gp_unlimited_end_date'])),
            isset($_POST['gp_unlimited_end_time']) ? trim((string) wp_unslash($_POST['gp_unlimited_end_time'])) : '',
            $event_mode
        );
    } elseif (!empty($_POST['gp_unlimited_end'])) {
        $raw = trim((string) wp_unslash($_POST['gp_unlimited_end']));
        $p['unlimited_end_ts'] = function_exists('poke_hub_special_event_parse_datetime')
            ? poke_hub_special_event_parse_datetime($raw, $event_mode)
            : pokehub_go_pass_parse_datetime_local($raw);
    }

    $p['weekly_tasks'] = [];
    $weekly = isset($_POST['gp_weekly']) && is_array($_POST['gp_weekly']) ? wp_unslash($_POST['gp_weekly']) : [];
    ksort($weekly, SORT_NUMERIC);
    foreach ($weekly as $w) {
        if (!is_array($w)) {
            continue;
        }
        $label = isset($w['label']) ? sanitize_text_field((string) $w['label']) : '';
        $pts   = isset($w['points']) ? (int) $w['points'] : 0;
        if ($label === '' && $pts === 0) {
            continue;
        }
        $p['weekly_tasks'][] = [
            'label'  => $label,
            'points' => $pts,
        ];
    }

    $core_in = isset($_POST['gp_daily_core']) && is_array($_POST['gp_daily_core']) ? wp_unslash($_POST['gp_daily_core']) : [];
    $p['daily_core_points'] = [
        'raid'  => isset($core_in['raid']) ? max(0, (int) $core_in['raid']) : 0,
        'egg'   => isset($core_in['egg']) ? max(0, (int) $core_in['egg']) : 0,
        'catch' => isset($core_in['catch']) ? max(0, (int) $core_in['catch']) : 0,
    ];
    $p['daily_points_cap'] = isset($_POST['gp_daily_points_cap']) ? max(0, (int) $_POST['gp_daily_points_cap']) : 0;

    $p['daily_tasks'] = [];
    $daily_in = isset($_POST['gp_daily']) && is_array($_POST['gp_daily']) ? wp_unslash($_POST['gp_daily']) : [];
    ksort($daily_in, SORT_NUMERIC);
    foreach ($daily_in as $w) {
        if (!is_array($w)) {
            continue;
        }
        $label = isset($w['label']) ? sanitize_text_field((string) $w['label']) : '';
        $pts   = isset($w['points']) ? (int) $w['points'] : 0;
        if ($label === '' && $pts === 0) {
            continue;
        }
        $p['daily_tasks'][] = [
            'label'  => $label,
            'points' => $pts,
        ];
    }

    $p['bonus_tasks'] = [];

    $p['daily_task'] = null;

    $p['extra_daily_note'] = isset($_POST['gp_extra_daily_note'])
        ? sanitize_text_field((string) wp_unslash($_POST['gp_extra_daily_note']))
        : '';

    $tiers      = [];
    $milestones = [];
    $raw_tiers  = isset($_POST['gp_tiers']) && is_array($_POST['gp_tiers']) ? wp_unslash($_POST['gp_tiers']) : [];
    ksort($raw_tiers, SORT_NUMERIC);

    foreach ($raw_tiers as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rank = isset($row['rank']) ? (int) $row['rank'] : 0;
        if ($rank < 1) {
            continue;
        }
        $rank_to = isset($row['rank_to']) ? (int) $row['rank_to'] : 0;
        if ($rank_to < $rank) {
            $rank_to = $rank;
        }
        if ($rank_to === 0) {
            $rank_to = $rank;
        }

        $free_r = isset($row['free_rewards']) && is_array($row['free_rewards'])
            ? pokehub_go_pass_parse_rewards_list_from_post($row['free_rewards'])
            : [];
        $prem_r = isset($row['premium_rewards']) && is_array($row['premium_rewards'])
            ? pokehub_go_pass_parse_rewards_list_from_post($row['premium_rewards'])
            : [];

        $free_legacy = pokehub_go_pass_textarea_to_lines(isset($row['free']) ? (string) $row['free'] : '');
        $prem_legacy = pokehub_go_pass_textarea_to_lines(isset($row['premium']) ? (string) $row['premium'] : '');

        // Palier bonus = au moins une récompense type « bonus » ; row_kind (ancien formulaire) conservé pour compatibilité cache / imports.
        $legacy_milestone = isset($row['row_kind']) && sanitize_key((string) $row['row_kind']) === 'milestone';
        $is_m             = pokehub_go_pass_post_tier_row_has_bonus_reward($row) || $legacy_milestone;
        if ($is_m) {
            $milestones[] = [
                'at_rank'         => $rank,
                'free_rewards'    => $free_r,
                'premium_rewards' => $prem_r,
                'free'            => $free_legacy,
                'premium'         => $prem_legacy,
            ];
        } else {
            $tier_row = [
                'rank'            => $rank,
                'free_rewards'    => $free_r,
                'premium_rewards' => $prem_r,
                'free'            => $free_legacy,
                'premium'         => $prem_legacy,
            ];
            if ($rank_to > $rank) {
                $tier_row['rank_to'] = $rank_to;
            }
            $tiers[] = $tier_row;
        }
    }

    $p['tiers']      = $tiers;
    $p['milestones'] = $milestones;

    return pokehub_go_pass_normalize_payload($p);
}

/**
 * Rang inclusif de fin d’une ligne de palier (avec ou sans plage).
 *
 * @param array<string, mixed> $tier
 */
function pokehub_go_pass_tier_rank_end(array $tier): int {
    $start = (int) ($tier['rank'] ?? 0);
    $end   = isset($tier['rank_to']) ? (int) $tier['rank_to'] : $start;
    if ($end < $start) {
        return $start;
    }

    return $end;
}

/**
 * Tiers triés par rang de début (ordre stable pour résoudre les recouvrements).
 *
 * @param array<int, mixed> $tiers
 * @return array<int, array<string, mixed>>
 */
function pokehub_go_pass_tiers_sorted_for_display(array $tiers): array {
    $list = [];
    foreach ($tiers as $t) {
        if (is_array($t)) {
            $list[] = $t;
        }
    }
    usort(
        $list,
        static function (array $a, array $b): int {
            return (int) ($a['rank'] ?? 0) <=> (int) ($b['rank'] ?? 0);
        }
    );

    return $list;
}

/**
 * Premier palier standard dont la plage contient ce rang (le plus petit « début » gagne).
 *
 * @param array<int, array<string, mixed>> $sorted_tiers
 */
function pokehub_go_pass_tier_covering_rank(array $sorted_tiers, int $r): ?array {
    $best = null;
    $best_start = PHP_INT_MAX;
    foreach ($sorted_tiers as $t) {
        $a = (int) ($t['rank'] ?? 0);
        $b = pokehub_go_pass_tier_rank_end($t);
        if ($a < 1 || $r < $a || $r > $b) {
            continue;
        }
        if ($a < $best_start) {
            $best_start = $a;
            $best       = $t;
        }
    }

    return $best;
}

/**
 * Tous les rangs à afficher dans le tableau (plages déployées + paliers bonus).
 *
 * @param array<string, mixed> $data Payload normalisé.
 * @return int[]
 */
function pokehub_go_pass_collect_display_ranks(array $data): array {
    $set = [];
    foreach ($data['tiers'] as $t) {
        if (!is_array($t)) {
            continue;
        }
        $a = (int) ($t['rank'] ?? 0);
        $b = pokehub_go_pass_tier_rank_end($t);
        if ($a < 1) {
            continue;
        }
        for ($r = $a; $r <= $b; $r++) {
            $set[$r] = true;
        }
    }
    foreach ($data['milestones'] as $m) {
        if (!is_array($m)) {
            continue;
        }
        $r = (int) ($m['at_rank'] ?? 0);
        if ($r > 0) {
            $set[$r] = true;
        }
    }
    $ranks = array_keys($set);
    sort($ranks, SORT_NUMERIC);

    return $ranks;
}

/**
 * Lignes du constructeur admin : rang + récompenses structurées + palier bonus.
 *
 * @param array<string, mixed> $payload Payload normalisé.
 * @return array<int, array<string, mixed>>
 */
function pokehub_go_pass_payload_to_editor_rows(array $payload): array {
    $payload = pokehub_go_pass_normalize_payload($payload);
    $rows     = [];
    foreach ($payload['tiers'] as $t) {
        $r = (int) ($t['rank'] ?? 0);
        if ($r < 1) {
            continue;
        }
        $r_to = pokehub_go_pass_tier_rank_end($t);
        $row  = [
            'rank'            => $r,
            'rank_to'         => ($r_to > $r) ? $r_to : null,
            'free'            => $t['free'] ?? [],
            'premium'         => $t['premium'] ?? [],
            'free_rewards'    => isset($t['free_rewards']) && is_array($t['free_rewards']) ? $t['free_rewards'] : [],
            'premium_rewards' => isset($t['premium_rewards']) && is_array($t['premium_rewards']) ? $t['premium_rewards'] : [],
            'milestone'       => false,
        ];
        $rows[] = $row;
    }
    foreach ($payload['milestones'] as $m) {
        $r = (int) ($m['at_rank'] ?? 0);
        if ($r < 1) {
            continue;
        }
        $rows[] = [
            'rank'            => $r,
            'rank_to'         => null,
            'free'            => $m['free'] ?? [],
            'premium'         => $m['premium'] ?? [],
            'free_rewards'    => isset($m['free_rewards']) && is_array($m['free_rewards']) ? $m['free_rewards'] : [],
            'premium_rewards' => isset($m['premium_rewards']) && is_array($m['premium_rewards']) ? $m['premium_rewards'] : [],
            'milestone'       => true,
        ];
    }
    usort(
        $rows,
        static function (array $a, array $b): int {
            $cmp = (int) ($a['rank'] ?? 0) <=> (int) ($b['rank'] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }
            return (!empty($a['milestone']) ? 1 : 0) <=> (!empty($b['milestone']) ? 1 : 0);
        }
    );
    if ($rows === []) {
        $rows[] = [
            'rank'            => 1,
            'rank_to'         => null,
            'free'            => [],
            'premium'         => [],
            'free_rewards'    => [],
            'premium_rewards' => [],
            'milestone'       => false,
        ];
    }

    return $rows;
}

/**
 * @return array<string, mixed>
 */
function pokehub_go_pass_default_payload(): array {
    return [
        'points_per_rank'       => 100,
        'rewards_claim_end_ts'  => 0,
        'unlimited_start_ts'    => 0,
        'unlimited_end_ts'      => 0,
        'daily_core_points'     => [
            'raid'  => 0,
            'egg'   => 0,
            'catch' => 0,
        ],
        'daily_points_cap'      => 0,
        'weekly_tasks'          => [],
        'daily_tasks'           => [],
        'bonus_tasks'           => [],
        'daily_task'            => null,
        'extra_daily_note'      => '',
        'tiers'                 => [],
        'milestones'            => [],
    ];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function pokehub_go_pass_normalize_payload(array $payload): array {
    $defaults = pokehub_go_pass_default_payload();
    $out       = array_merge($defaults, $payload);

    $out['points_per_rank']      = max(1, (int) $out['points_per_rank']);
    $out['rewards_claim_end_ts'] = (int) $out['rewards_claim_end_ts'];
    $out['unlimited_start_ts']   = (int) $out['unlimited_start_ts'];
    $out['unlimited_end_ts']     = (int) $out['unlimited_end_ts'];

    if (!is_array($out['weekly_tasks'])) {
        $out['weekly_tasks'] = [];
    }
    if (!is_array($out['daily_tasks'])) {
        $out['daily_tasks'] = [];
    }
    if (!is_array($out['daily_core_points'])) {
        $out['daily_core_points'] = [];
    }
    $dc = $out['daily_core_points'];
    $out['daily_core_points'] = [
        'raid'  => max(0, (int) ($dc['raid'] ?? 0)),
        'egg'   => max(0, (int) ($dc['egg'] ?? 0)),
        'catch' => max(0, (int) ($dc['catch'] ?? 0)),
    ];
    $out['daily_points_cap'] = max(0, (int) ($out['daily_points_cap'] ?? 0));
    $out['bonus_tasks'] = [];
    if (!is_array($out['tiers'])) {
        $out['tiers'] = [];
    }
    if (!is_array($out['milestones'])) {
        $out['milestones'] = [];
    }
    $out['extra_daily_note'] = is_string($out['extra_daily_note'] ?? null)
        ? sanitize_text_field($out['extra_daily_note'])
        : '';

    if (isset($out['daily_task']) && is_array($out['daily_task'])) {
        $out['daily_task'] = [
            'label'  => isset($out['daily_task']['label']) ? sanitize_text_field((string) $out['daily_task']['label']) : '',
            'points' => isset($out['daily_task']['points']) ? (int) $out['daily_task']['points'] : 0,
        ];
    } else {
        $out['daily_task'] = null;
    }

    if ($out['daily_tasks'] === [] && $out['daily_task'] !== null && ($out['daily_task']['label'] !== '' || (int) $out['daily_task']['points'] !== 0)) {
        $out['daily_tasks'] = [$out['daily_task']];
    }

    $normalize_task_rows = static function ($rows): array {
        $acc = [];
        if (!is_array($rows)) {
            return $acc;
        }
        foreach ($rows as $t) {
            if (!is_array($t)) {
                continue;
            }
            $acc[] = [
                'label'  => isset($t['label']) ? sanitize_text_field((string) $t['label']) : '',
                'points' => isset($t['points']) ? (int) $t['points'] : 0,
            ];
        }

        return $acc;
    };

    $out['weekly_tasks'] = $normalize_task_rows($out['weekly_tasks']);
    $out['daily_tasks']  = $normalize_task_rows($out['daily_tasks']);

    $tiers = [];
    foreach ($out['tiers'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rank = isset($row['rank']) ? (int) $row['rank'] : 0;
        if ($rank < 1) {
            continue;
        }
        $rank_to = isset($row['rank_to']) ? (int) $row['rank_to'] : $rank;
        if ($rank_to < $rank) {
            $rank_to = $rank;
        }
        $tier_norm = [
            'rank'            => $rank,
            'free_rewards'    => pokehub_go_pass_normalize_rewards_list(isset($row['free_rewards']) && is_array($row['free_rewards']) ? $row['free_rewards'] : []),
            'premium_rewards' => pokehub_go_pass_normalize_rewards_list(isset($row['premium_rewards']) && is_array($row['premium_rewards']) ? $row['premium_rewards'] : []),
            'free'            => pokehub_go_pass_normalize_cell_lines($row['free'] ?? []),
            'premium'         => pokehub_go_pass_normalize_cell_lines($row['premium'] ?? []),
        ];
        if ($rank_to > $rank) {
            $tier_norm['rank_to'] = $rank_to;
        }
        $tiers[] = $tier_norm;
    }
    usort($tiers, static function ($a, $b) {
        return (int) $a['rank'] <=> (int) $b['rank'];
    });
    $out['tiers'] = $tiers;

    $milestones = [];
    foreach ($out['milestones'] as $m) {
        if (!is_array($m)) {
            continue;
        }
        $at = isset($m['at_rank']) ? (int) $m['at_rank'] : 0;
        if ($at < 1) {
            continue;
        }
        $milestones[] = [
            'at_rank'         => $at,
            'free_rewards'    => pokehub_go_pass_normalize_rewards_list(isset($m['free_rewards']) && is_array($m['free_rewards']) ? $m['free_rewards'] : []),
            'premium_rewards' => pokehub_go_pass_normalize_rewards_list(isset($m['premium_rewards']) && is_array($m['premium_rewards']) ? $m['premium_rewards'] : []),
            'free'            => pokehub_go_pass_normalize_cell_lines($m['free'] ?? []),
            'premium'         => pokehub_go_pass_normalize_cell_lines($m['premium'] ?? []),
        ];
    }
    usort($milestones, static function ($a, $b) {
        return (int) $a['at_rank'] <=> (int) $b['at_rank'];
    });
    $out['milestones'] = $milestones;

    return $out;
}

/**
 * @param mixed $lines
 * @return string[]
 */
function pokehub_go_pass_normalize_cell_lines($lines): array {
    if (!is_array($lines)) {
        if (is_string($lines) && $lines !== '') {
            return [sanitize_text_field($lines)];
        }
        return [];
    }
    $out = [];
    foreach ($lines as $line) {
        $line = is_string($line) ? trim($line) : (is_scalar($line) ? trim((string) $line) : '');
        if ($line === '') {
            continue;
        }
        $out[] = sanitize_text_field($line);
    }
    return $out;
}

/**
 * @return object|null Row DB
 */
function pokehub_content_get_go_pass_row(string $source_type, int $source_id) {
    global $wpdb;
    $table = function_exists('pokehub_get_table') ? pokehub_get_table('content_go_pass') : '';
    if ($table === '' || $source_id <= 0) {
        return null;
    }
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE source_type = %s AND source_id = %d LIMIT 1",
            $source_type,
            $source_id
        )
    );
}

/**
 * @return array<string, mixed>|null Payload normalisé ou null si absent
 */
function pokehub_content_get_go_pass(string $source_type, int $source_id): ?array {
    $row = pokehub_content_get_go_pass_row($source_type, $source_id);
    if (!$row || empty($row->payload)) {
        return null;
    }
    $decoded = json_decode((string) $row->payload, true);
    if (!is_array($decoded)) {
        return null;
    }
    return pokehub_go_pass_normalize_payload($decoded);
}

function pokehub_content_get_go_pass_json_string(string $source_type, int $source_id): string {
    $row = pokehub_content_get_go_pass_row($source_type, $source_id);
    if (!$row || empty($row->payload)) {
        return '';
    }
    $decoded = json_decode((string) $row->payload, true);
    if (!is_array($decoded)) {
        return (string) $row->payload;
    }
    $norm = pokehub_go_pass_normalize_payload($decoded);
    return wp_json_encode($norm, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * @param array<string, mixed> $payload
 */
function pokehub_content_save_go_pass(string $source_type, int $source_id, array $payload): void {
    global $wpdb;

    $source_type = (string) $source_type;
    $source_id   = (int) $source_id;
    if ($source_id <= 0) {
        return;
    }

    $table = function_exists('pokehub_get_table') ? pokehub_get_table('content_go_pass') : '';
    if ($table === '') {
        return;
    }

    $payload   = pokehub_go_pass_normalize_payload($payload);
    $json      = wp_json_encode($payload, JSON_UNESCAPED_UNICODE);
    $dates     = function_exists('pokehub_content_get_dates_for_source')
        ? pokehub_content_get_dates_for_source($source_type, $source_id)
        : ['start_ts' => 0, 'end_ts' => 0];
    $start_ts  = (int) ($dates['start_ts'] ?? 0);
    $end_ts    = (int) ($dates['end_ts'] ?? 0);

    $existing_id = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$table} WHERE source_type = %s AND source_id = %d LIMIT 1",
            $source_type,
            $source_id
        )
    );

    $data = [
        'source_type' => $source_type,
        'source_id'   => $source_id,
        'start_ts'    => $start_ts,
        'end_ts'      => $end_ts,
        'payload'     => $json,
    ];

    if ($existing_id > 0) {
        $wpdb->update(
            $table,
            $data,
            ['id' => $existing_id],
            ['%s', '%d', '%d', '%d', '%s'],
            ['%d']
        );
    } else {
        $wpdb->insert(
            $table,
            $data,
            ['%s', '%d', '%d', '%d', '%s']
        );
    }
}

function pokehub_content_delete_go_pass(string $source_type, int $source_id): void {
    global $wpdb;
    $table = function_exists('pokehub_get_table') ? pokehub_get_table('content_go_pass') : '';
    if ($table === '' || $source_id <= 0) {
        return;
    }
    $wpdb->delete($table, ['source_type' => $source_type, 'source_id' => $source_id], ['%s', '%d']);
}

/**
 * Une ligne de la frise « tâches » Pass GO (carte + point sur la ligne).
 *
 * @param string $variant weekly|daily_core|daily_extra
 */
function pokehub_go_pass_render_task_track_row(string $variant, string $label, int $points, bool $always_show_points = false): void {
    if ($label === '') {
        return;
    }
    $show_badge = $always_show_points || $points > 0;
    ?>
    <li class="pokehub-go-pass-task-card pokehub-go-pass-task-card--<?php echo esc_attr($variant); ?>">
        <div class="pokehub-go-pass-task-card__rail" aria-hidden="true"><span class="pokehub-go-pass-task-card__marker"></span></div>
        <div class="pokehub-go-pass-task-card__panel">
            <span class="pokehub-go-pass-task-card__label"><?php echo esc_html($label); ?></span>
            <?php if ($show_badge) : ?>
                <span class="pokehub-go-pass-task-card__badge">
                    <?php echo esc_html((string) (int) $points); ?>
                    <abbr class="pokehub-go-pass-task-card__badge-abbr" title="<?php echo esc_attr__('points', 'poke-hub'); ?>"><?php esc_html_e('pts', 'poke-hub'); ?></abbr>
                </span>
            <?php endif; ?>
        </div>
    </li>
    <?php
}

/**
 * @param object $event Ligne special_events (avec start_ts, end_ts, event_type…)
 */
function pokehub_render_go_pass_html(object $event): string {
    if (empty($event->id)) {
        return '';
    }
    $data = pokehub_content_get_go_pass('special_event', (int) $event->id);
    // Pas de ligne content_go_pass ou payload vide : le rendu complet n’est pas disponible.
    if ($data === null) {
        return '';
    }

    $tz = wp_timezone();

    $fmt_ts = static function (int $ts) use ($tz): string {
        if ($ts <= 0) {
            return '';
        }
        return esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $ts, $tz));
    };

    $points          = (int) $data['points_per_rank'];
    $featured_lines  = pokehub_go_pass_collect_featured_reward_lines($data);
    ob_start();
    ?>
    <div class="pokehub-go-pass" role="region" aria-label="<?php echo esc_attr__('Pass GO', 'poke-hub'); ?>">
        <div class="pokehub-go-pass-meta">
            <?php if ($points > 0) : ?>
                <p class="pokehub-go-pass-points-per-rank">
                    <?php
                    printf(
                        /* translators: %d: GO points per rank */
                        esc_html__('Points per rank: %d', 'poke-hub'),
                        $points
                    );
                    ?>
                </p>
            <?php endif; ?>
            <?php if (!empty($data['rewards_claim_end_ts'])) : ?>
                <p><strong><?php esc_html_e('Claim rewards until:', 'poke-hub'); ?></strong>
                    <?php echo $fmt_ts((int) $data['rewards_claim_end_ts']); ?></p>
            <?php endif; ?>
            <?php if (!empty($data['unlimited_start_ts']) && !empty($data['unlimited_end_ts'])) : ?>
                <p><strong><?php esc_html_e('Unlimited daily points period:', 'poke-hub'); ?></strong>
                    <?php echo $fmt_ts((int) $data['unlimited_start_ts']); ?> – <?php echo $fmt_ts((int) $data['unlimited_end_ts']); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($featured_lines !== []) : ?>
            <div class="pokehub-go-pass-featured-summary">
                <h3 class="pokehub-go-pass-subtitle"><?php esc_html_e('Highlights', 'poke-hub'); ?></h3>
                <p class="description pokehub-go-pass-featured-intro"><?php esc_html_e('Featured rewards for this GO Pass.', 'poke-hub'); ?></p>
                <ul class="pokehub-go-pass-featured-list">
                    <?php foreach ($featured_lines as $line) : ?>
                        <?php
                        $side_label = ($line['side'] ?? '') === 'premium'
                            ? __('Deluxe', 'poke-hub')
                            : __('Free', 'poke-hub');
                        ?>
                        <li>
                            <span class="pokehub-go-pass-featured-side"><?php echo esc_html($side_label); ?></span>
                            <span class="pokehub-go-pass-featured-sep" aria-hidden="true"> — </span>
                            <span class="pokehub-go-pass-featured-text"><?php echo esc_html((string) ($line['text'] ?? '')); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($data['weekly_tasks'])) : ?>
            <?php
            $weekly_shown = false;
            foreach ($data['weekly_tasks'] as $wt) {
                if (is_array($wt) && !empty($wt['label'])) {
                    $weekly_shown = true;
                    break;
                }
            }
            ?>
            <?php if ($weekly_shown) : ?>
                <div class="pokehub-go-pass-task-section pokehub-go-pass-task-section--weekly">
                    <h3 class="pokehub-go-pass-subtitle"><?php esc_html_e('Weekly tasks', 'poke-hub'); ?></h3>
                    <ul class="pokehub-go-pass-task-track">
                        <?php foreach ($data['weekly_tasks'] as $t) : ?>
                            <?php
                            if (!is_array($t) || empty($t['label'])) {
                                continue;
                            }
                            pokehub_go_pass_render_task_track_row(
                                'weekly',
                                (string) $t['label'],
                                isset($t['points']) ? (int) $t['points'] : 0,
                                false
                            );
                            ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php
        $dc_pts = isset($data['daily_core_points']) && is_array($data['daily_core_points']) ? $data['daily_core_points'] : [];
        $dc_raid  = max(0, (int) ($dc_pts['raid'] ?? 0));
        $dc_egg   = max(0, (int) ($dc_pts['egg'] ?? 0));
        $dc_catch = max(0, (int) ($dc_pts['catch'] ?? 0));
        $dc_cap   = max(0, (int) ($data['daily_points_cap'] ?? 0));
        $show_daily_core = ($dc_raid > 0 || $dc_egg > 0 || $dc_catch > 0 || $dc_cap > 0);
        ?>
        <?php if ($show_daily_core) : ?>
        <div class="pokehub-go-pass-task-section pokehub-go-pass-task-section--daily-core">
            <h3 class="pokehub-go-pass-subtitle"><?php esc_html_e('Daily points', 'poke-hub'); ?></h3>
            <ul class="pokehub-go-pass-task-track">
                <?php
                pokehub_go_pass_render_task_track_row('daily_core', __('Win a raid', 'poke-hub'), $dc_raid, true);
                pokehub_go_pass_render_task_track_row('daily_core', __('Hatch an Egg', 'poke-hub'), $dc_egg, true);
                pokehub_go_pass_render_task_track_row('daily_core', __('Catch a Pokémon', 'poke-hub'), $dc_catch, true);
                ?>
            </ul>
            <?php if ($dc_cap > 0) : ?>
                <p class="pokehub-go-pass-daily-cap">
                    <?php
                    printf(
                        /* translators: %d: maximum points per day */
                        esc_html__('Daily cap: up to %d points per day (all daily point sources combined).', 'poke-hub'),
                        $dc_cap
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($data['daily_tasks']) && is_array($data['daily_tasks'])) : ?>
            <?php
            $daily_extra_shown = false;
            foreach ($data['daily_tasks'] as $dt) {
                if (is_array($dt) && !empty($dt['label'])) {
                    $daily_extra_shown = true;
                    break;
                }
            }
            ?>
            <?php if ($daily_extra_shown) : ?>
                <div class="pokehub-go-pass-task-section pokehub-go-pass-task-section--daily-extra">
                    <h3 class="pokehub-go-pass-subtitle"><?php esc_html_e('Other daily tasks', 'poke-hub'); ?></h3>
                    <ul class="pokehub-go-pass-task-track">
                        <?php foreach ($data['daily_tasks'] as $t) : ?>
                            <?php
                            if (!is_array($t) || empty($t['label'])) {
                                continue;
                            }
                            pokehub_go_pass_render_task_track_row(
                                'daily_extra',
                                (string) $t['label'],
                                isset($t['points']) ? (int) $t['points'] : 0,
                                false
                            );
                            ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php elseif (is_array($data['daily_task'] ?? null) && !empty($data['daily_task']['label'])) : ?>
            <div class="pokehub-go-pass-task-section pokehub-go-pass-task-section--daily-extra">
                <h3 class="pokehub-go-pass-subtitle"><?php esc_html_e('Daily task', 'poke-hub'); ?></h3>
                <ul class="pokehub-go-pass-task-track">
                    <?php
                    pokehub_go_pass_render_task_track_row(
                        'daily_extra',
                        (string) $data['daily_task']['label'],
                        isset($data['daily_task']['points']) ? (int) $data['daily_task']['points'] : 0,
                        false
                    );
                    ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($data['extra_daily_note'])) : ?>
            <p class="pokehub-go-pass-note"><?php echo esc_html((string) $data['extra_daily_note']); ?></p>
        <?php endif; ?>

        <div class="pokehub-go-pass-track-wrap">
            <?php
            $milestone_by_rank = [];
            foreach ($data['milestones'] as $m) {
                if (is_array($m)) {
                    $milestone_by_rank[(int) $m['at_rank']] = $m;
                }
            }
            $sorted_tiers   = pokehub_go_pass_tiers_sorted_for_display($data['tiers']);
            $display_ranks  = pokehub_go_pass_collect_display_ranks($data);
            $min_rank_badge = ($display_ranks !== []) ? (int) min($display_ranks) : 0;
            if ($display_ranks === []) :
                $edit_url = '';
                if (function_exists('current_user_can') && current_user_can('manage_options')) {
                    $edit_url = pokehub_go_pass_admin_edit_url((int) $event->id);
                }
                ?>
                <div class="pokehub-go-pass-track pokehub-go-pass-track--empty">
                    <p class="pokehub-go-pass-empty-grid__text">
                        <?php esc_html_e('No reward ranks are configured for this GO Pass yet. Add ranks and rewards in the admin.', 'poke-hub'); ?>
                    </p>
                    <?php if ($edit_url !== '') : ?>
                        <p class="pokehub-go-pass-empty-grid__action">
                            <a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit GO Pass', 'poke-hub'); ?></a>
                        </p>
                    <?php endif; ?>
                </div>
                <?php
            else :
                ?>
            <div class="pokehub-go-pass-track" role="region" aria-label="<?php echo esc_attr__('Reward track', 'poke-hub'); ?>">
                <div class="pokehub-go-pass-track__header">
                    <span class="pokehub-go-pass-track__header-spacer" aria-hidden="true"></span>
                    <div class="pokehub-go-pass-track__head-basic"><?php esc_html_e('Basic', 'poke-hub'); ?></div>
                    <div class="pokehub-go-pass-track__head-deluxe"><?php esc_html_e('Deluxe', 'poke-hub'); ?></div>
                </div>
                <div class="pokehub-go-pass-track__body">
                    <?php foreach ($display_ranks as $rank) : ?>
                        <?php
                        $rank = (int) $rank;
                        $row_current = ($min_rank_badge > 0 && $rank === $min_rank_badge);
                        ?>
                        <?php if (isset($milestone_by_rank[$rank])) : ?>
                            <?php $m = $milestone_by_rank[$rank]; ?>
                            <div class="pokehub-go-pass-track__milestone" role="group" aria-label="<?php echo esc_attr(sprintf(/* translators: %d: rank number */ __('Bonus tier at rank %d', 'poke-hub'), $rank)); ?>">
                                <div class="pokehub-go-pass-track__milestone-label">
                                    <?php
                                    printf(
                                        /* translators: %d: rank number */
                                        esc_html__('Bonus tier (%d)', 'poke-hub'),
                                        $rank
                                    );
                                    ?>
                                </div>
                                <div class="pokehub-go-pass-track__milestone-grid">
                                    <?php
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construit avec échappements internes
                                    echo pokehub_go_pass_render_reward_cards_column_html($m, 'free');
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    echo pokehub_go_pass_render_reward_cards_column_html($m, 'premium');
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php
                        $tier = pokehub_go_pass_tier_covering_rank($sorted_tiers, $rank);
                        if (!$tier) {
                            continue;
                        }
                        ?>
                        <div class="pokehub-go-pass-track__row<?php echo $row_current ? ' pokehub-go-pass-track__row--current' : ''; ?>">
                            <div class="pokehub-go-pass-track__rail">
                                <span class="pokehub-go-pass-track__rail-line" aria-hidden="true"></span>
                                <span class="pokehub-go-pass-track__badge"><?php echo esc_html((string) $rank); ?></span>
                            </div>
                            <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            echo pokehub_go_pass_render_reward_cards_column_html($tier, 'free');
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            echo pokehub_go_pass_render_reward_cards_column_html($tier, 'premium');
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
                <?php
            endif;
            ?>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * Ligne special_events (type Pass GO) par ID SQL.
 *
 * @return object|null
 */
function pokehub_go_pass_get_special_event_row_by_id(int $id) {
    global $wpdb;
    if ($id <= 0) {
        return null;
    }
    $table = pokehub_get_table('special_events');
    if ($table === '') {
        return null;
    }
    $slug = pokehub_go_pass_event_type_slug();
    $row  = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND event_type = %s LIMIT 1",
            $id,
            $slug
        )
    );
    return $row instanceof stdClass ? $row : null;
}

/**
 * Timestamps début / fin d’événement pour un article (Me5rine LAB + metas Poké HUB).
 *
 * @return array{start_ts: int, end_ts: int} Zéros si introuvable ou incohérent.
 */
function pokehub_go_pass_resolve_date_range_from_host_post(int $post_id): array {
    if ($post_id <= 0) {
        return ['start_ts' => 0, 'end_ts' => 0];
    }

    $start = (int) get_post_meta($post_id, '_admin_lab_event_start', true);
    $end   = (int) get_post_meta($post_id, '_admin_lab_event_end', true);
    if ($start > 0 && $end > 0 && $end >= $start) {
        return ['start_ts' => $start, 'end_ts' => $end];
    }

    if (!function_exists('poke_hub_events_get_post_dates')) {
        $helpers = defined('POKE_HUB_PATH') ? POKE_HUB_PATH . 'modules/events/functions/events-helpers.php' : '';
        if ($helpers !== '' && is_readable($helpers)) {
            require_once $helpers;
        }
    }
    if (function_exists('poke_hub_events_get_post_dates')) {
        $d = poke_hub_events_get_post_dates($post_id);
        $st = isset($d['start_ts']) && $d['start_ts'] !== null ? (int) $d['start_ts'] : 0;
        $en = isset($d['end_ts']) && $d['end_ts'] !== null ? (int) $d['end_ts'] : 0;
        if ($st > 0 && $en > 0 && $en >= $st) {
            return ['start_ts' => $st, 'end_ts' => $en];
        }
    }

    $custom = apply_filters('pokehub_go_pass_host_post_date_range', null, $post_id);
    if (is_array($custom) && isset($custom['start_ts'], $custom['end_ts'])) {
        $st = (int) $custom['start_ts'];
        $en = (int) $custom['end_ts'];
        if ($st > 0 && $en > 0 && $en >= $st) {
            return ['start_ts' => $st, 'end_ts' => $en];
        }
    }

    return ['start_ts' => 0, 'end_ts' => 0];
}

/**
 * Crée un événement spécial Pass GO minimal + ligne content_go_pass (payload vide).
 *
 * @param int $host_post_id Article WordPress lié : si > 0, réutilise début / fin d’événement (metas) quand disponibles.
 * @return int|WP_Error ID inséré ou erreur.
 */
function pokehub_go_pass_create_empty_special_event(string $title_en = '', string $title_fr = '', int $host_post_id = 0) {
    global $wpdb;

    if (!function_exists('pokehub_generate_unique_event_slug')) {
        $admin_helpers = defined('POKE_HUB_PATH') ? POKE_HUB_PATH . 'modules/events/functions/events-admin-helpers.php' : '';
        if ($admin_helpers !== '' && is_readable($admin_helpers)) {
            require_once $admin_helpers;
        }
    }
    if (!function_exists('pokehub_generate_unique_event_slug')) {
        return new WP_Error(
            'missing_slug_helper',
            __('Event slug helper is not available.', 'poke-hub'),
            ['status' => 500]
        );
    }

    $events_table       = pokehub_get_table('special_events');
    $remote_posts_table = pokehub_get_table('remote_posts');
    if ($events_table === '') {
        return new WP_Error('no_table', __('Database table is missing.', 'poke-hub'), ['status' => 500]);
    }
    if ($remote_posts_table === '') {
        $remote_posts_table = $wpdb->posts;
    }

    $title_en = $title_en !== '' ? $title_en : __('New GO Pass', 'poke-hub');
    $title_fr = $title_fr !== '' ? $title_fr : __('New GO Pass', 'poke-hub');
    $title    = $title_en;

    $slug = pokehub_generate_unique_event_slug($title_en, 0);

    $existing_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$events_table} WHERE slug = %s",
            $slug
        )
    );
    if ($existing_id) {
        return new WP_Error('slug_exists', __('This slug is already used by another event.', 'poke-hub'), ['status' => 400]);
    }

    $remote_slug_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ID FROM {$remote_posts_table} WHERE post_name = %s LIMIT 1",
            $slug
        )
    );
    if ($remote_slug_exists) {
        return new WP_Error('slug_remote', __('This slug is already used by a remote event.', 'poke-hub'), ['status' => 400]);
    }

    $now = current_time('timestamp');
    $end = $now + (int) (30 * DAY_IN_SECONDS);

    $mode_row = 'local';
    if ($host_post_id > 0) {
        $range = pokehub_go_pass_resolve_date_range_from_host_post($host_post_id);
        if ($range['start_ts'] > 0 && $range['end_ts'] > 0) {
            $now = $range['start_ts'];
            $end = $range['end_ts'];
        }
        $m = get_post_meta($host_post_id, '_event_mode', true);
        if ($m === 'fixed' || $m === 'local') {
            $mode_row = $m;
        }
    }

    $event_type = sanitize_title(pokehub_go_pass_event_type_slug());

    $data = [
        'slug'                    => $slug,
        'title'                   => $title,
        'title_en'                => $title_en,
        'title_fr'                => $title_fr,
        'description'             => '',
        'event_type'              => $event_type,
        'start_ts'                => $now,
        'end_ts'                  => $end,
        'mode'                    => $mode_row,
        'recurring'               => 0,
        'recurring_freq'          => 'weekly',
        'recurring_interval'      => 1,
        'recurring_window_end_ts' => 0,
        'image_id'                => null,
        'image_url'               => '',
    ];

    $formats = ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%d', '%d', '%s'];

    $wpdb->insert($events_table, $data, $formats);
    $event_id = (int) $wpdb->insert_id;
    if ($event_id <= 0) {
        return new WP_Error('insert_failed', __('Could not save event.', 'poke-hub'), ['status' => 500]);
    }

    pokehub_content_save_go_pass('special_event', $event_id, pokehub_go_pass_default_payload());

    return $event_id;
}

/**
 * Résumé court (carte) pour affichage dans un article.
 *
 * @param object               $event   Ligne special_events.
 * @param array<string,mixed>|null $payload Payload normalisé (optionnel).
 */
function pokehub_render_go_pass_summary_html(object $event, ?array $payload = null): string {
    if (empty($event->id) || empty($event->slug)) {
        return '';
    }

    $id = (int) $event->id;
    if ($payload === null) {
        $payload = pokehub_content_get_go_pass('special_event', $id);
    }

    $locale   = get_locale();
    $title_fr = isset($event->title_fr) ? (string) $event->title_fr : '';
    $title_en = isset($event->title_en) ? (string) $event->title_en : '';
    $title    = isset($event->title) ? (string) $event->title : '';
    if (strpos($locale, 'fr') === 0 && $title_fr !== '') {
        $display_title = $title_fr;
    } elseif ($title_en !== '') {
        $display_title = $title_en;
    } else {
        $display_title = $title;
    }

    $tz = wp_timezone();
    $fmt_date = static function (int $ts) use ($tz): string {
        if ($ts <= 0) {
            return '';
        }
        return esc_html(wp_date(get_option('date_format'), $ts, $tz));
    };

    $start_ts = (int) ($event->start_ts ?? 0);
    $end_ts   = (int) ($event->end_ts ?? 0);
    $dates    = '';
    if ($start_ts > 0 && $end_ts > 0) {
        $dates = sprintf(
            /* translators: 1: start date, 2: end date */
            esc_html__('%1$s → %2$s', 'poke-hub'),
            $fmt_date($start_ts),
            $fmt_date($end_ts)
        );
    } elseif ($start_ts > 0) {
        $dates = $fmt_date($start_ts);
    }

    $tier_count = ($payload && !empty($payload['tiers']) && is_array($payload['tiers']))
        ? count($payload['tiers'])
        : 0;

    $public_url = function_exists('poke_hub_special_event_get_url')
        ? poke_hub_special_event_get_url((string) $event->slug)
        : '';

    ob_start();
    ?>
    <div class="pokehub-go-pass-block pokehub-go-pass-summary-card">
        <h3 class="pokehub-go-pass-summary-title"><?php echo esc_html($display_title); ?></h3>
        <?php if ($dates !== '') : ?>
            <p class="pokehub-go-pass-summary-dates"><?php echo $dates; ?></p>
        <?php endif; ?>
        <?php if ($tier_count > 0) : ?>
            <p class="pokehub-go-pass-summary-tiers">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %d: number of GO Pass ranks */
                        _n('%d rank configured', '%d ranks configured', $tier_count, 'poke-hub'),
                        $tier_count
                    )
                );
                ?>
            </p>
        <?php else : ?>
            <p class="pokehub-go-pass-summary-draft"><?php esc_html_e('No reward rows yet — edit the GO Pass to add ranks.', 'poke-hub'); ?></p>
        <?php endif; ?>
        <?php if ($public_url !== '') : ?>
            <p class="pokehub-go-pass-summary-actions">
                <a class="pokehub-go-pass-summary-link" href="<?php echo esc_url($public_url); ?>">
                    <?php esc_html_e('View GO Pass page', 'poke-hub'); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}
