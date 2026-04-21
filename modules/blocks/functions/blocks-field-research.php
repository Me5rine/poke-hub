<?php
/**
 * Field Research (bloc pokehub/event-quests) — rendu et helpers.
 * Tout est chargé uniquement par le module Blocks (aucune dépendance au module Events).
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * URL de l’image d’un Pokémon pour les quêtes (sprite standard ; shiny géré par badge).
 *
 * @param int         $pokemon_id ID
 * @param bool        $is_shiny   Si true, sprite shiny
 * @param string|null $gender     Genre optionnel (dimorphisme)
 */
if (!function_exists('pokehub_get_quest_pokemon_image')) {
    function pokehub_get_quest_pokemon_image($pokemon_id, $is_shiny = false, $gender = null) {
        if (!function_exists('poke_hub_pokemon_get_image_url') || !function_exists('pokehub_get_pokemon_data_by_id')) {
            return '';
        }
        $pokemon_data = pokehub_get_pokemon_data_by_id((int) $pokemon_id);
        if (!$pokemon_data) {
            return '';
        }
        $pokemon = (object) $pokemon_data;
        $opts    = ['shiny' => !empty($is_shiny)];
        if ($gender !== null && $gender !== '') {
            $opts['gender'] = $gender;
        }
        return (string) poke_hub_pokemon_get_image_url($pokemon, $opts);
    }
}

if (!function_exists('pokehub_field_research_flatten_pokemon_slots')) {
    /**
     * @return list<array{pokemon_id:int, reward:array, gender:mixed}>
     */
    function pokehub_field_research_flatten_pokemon_slots(array $rewards): array {
        $out = [];
        foreach ($rewards as $reward) {
            if (($reward['type'] ?? 'pokemon') !== 'pokemon') {
                continue;
            }
            $pokemon_ids = [];
            $genders = isset($reward['pokemon_genders']) && is_array($reward['pokemon_genders'])
                ? $reward['pokemon_genders']
                : [];
            if (isset($reward['pokemon_ids']) && is_array($reward['pokemon_ids'])) {
                if (function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')) {
                    $parsed = pokehub_parse_post_pokemon_multiselect_tokens_with_genders($reward['pokemon_ids'], $genders);
                    $pokemon_ids = $parsed['pokemon_ids'];
                    $genders = $parsed['pokemon_genders'];
                } else {
                    $pokemon_ids = array_filter(array_map('intval', $reward['pokemon_ids']), static function ($id) {
                        return $id > 0;
                    });
                }
            } elseif (!empty($reward['pokemon_id'])) {
                if (function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')) {
                    $parsed = pokehub_parse_post_pokemon_multiselect_tokens_with_genders([(string) $reward['pokemon_id']], $genders);
                    $pokemon_ids = $parsed['pokemon_ids'];
                    $genders = $parsed['pokemon_genders'];
                } else {
                    $pokemon_ids = [(int) $reward['pokemon_id']];
                }
            }
            foreach ($pokemon_ids as $pid) {
                $pid = (int) $pid;
                $g = $genders[(string) $pid] ?? $genders[$pid] ?? null;
                $out[] = [
                    'pokemon_id' => $pid,
                    'reward'     => $reward,
                    'gender'     => $g,
                ];
            }
        }
        return $out;
    }
}

if (!function_exists('pokehub_field_research_count_other_reward_rows')) {
    function pokehub_field_research_count_other_reward_rows(array $rewards): int {
        $n = 0;
        foreach ($rewards as $reward) {
            if (($reward['type'] ?? 'pokemon') !== 'pokemon') {
                $n++;
            }
        }
        return $n;
    }
}

if (!function_exists('pokehub_field_research_reward_quantity_label')) {
    function pokehub_field_research_reward_quantity_label(array $reward): string {
        $qty = (int) ($reward['quantity'] ?? 1);
        $qty = max(1, $qty);
        $formatted = function_exists('number_format_i18n')
            ? number_format_i18n($qty)
            : (string) $qty;
        return '×' . $formatted;
    }
}

if (!function_exists('pokehub_field_research_pokemon_badges')) {
    /**
     * @return array{show_shiny:bool, show_regional:bool, is_forced_shiny:bool}
     */
    function pokehub_field_research_pokemon_badges(int $pokemon_id, array $reward): array {
        $is_forced_shiny = !empty($reward['force_shiny']) || !empty($reward['is_shiny']);
        $shiny_available = false;
        if (!$is_forced_shiny && function_exists('poke_hub_pokemon_get_shiny_info')) {
            $info = poke_hub_pokemon_get_shiny_info($pokemon_id, []);
            $shiny_available = !empty($info['is_shiny_available']);
        }
        $show_shiny = $is_forced_shiny || $shiny_available;

        $show_regional = false;
        if (function_exists('pokehub_get_pokemon_data_by_id')) {
            $data = pokehub_get_pokemon_data_by_id($pokemon_id);
            if ($data && function_exists('poke_hub_pokemon_get_regional_info')) {
                $reg = poke_hub_pokemon_get_regional_info((object) $data);
                $show_regional = !empty($reg['should_show_icon']);
            }
        }

        return [
            'show_shiny'      => $show_shiny,
            'show_regional'   => $show_regional,
            'is_forced_shiny' => $is_forced_shiny,
        ];
    }
}

if (!function_exists('pokehub_field_research_format_other_reward_line')) {
    function pokehub_field_research_format_other_reward_line(array $reward): string {
        $type = $reward['type'] ?? '';
        $qty  = (int) ($reward['quantity'] ?? 1);
        $qty  = max(1, $qty);

        switch ($type) {
            case 'stardust':
                return sprintf(__('Stardust × %s', 'poke-hub'), number_format_i18n($qty));
            case 'xp':
                return sprintf(__('XP × %s', 'poke-hub'), number_format_i18n($qty));
            case 'item':
                $item_id = (int) ($reward['item_id'] ?? 0);
                $name    = '';
                if ($item_id > 0 && function_exists('pokehub_get_item_data_by_id')) {
                    $item = pokehub_get_item_data_by_id($item_id);
                    if ($item) {
                        $name = (string) ($item['name_fr'] ?? $item['name_en'] ?? '');
                    }
                }
                if ($name === '') {
                    $name = (string) ($reward['item_name'] ?? '');
                }
                if ($name === '') {
                    $name = __('Item', 'poke-hub');
                }
                return $qty > 1 ? sprintf('%s × %s', $name, number_format_i18n($qty)) : $name;

            case 'candy':
            case 'xl_candy':
            case 'mega_energy':
                $pid   = (int) ($reward['pokemon_id'] ?? 0);
                $pname = '';
                if ($pid > 0 && function_exists('pokehub_get_pokemon_data_by_id')) {
                    $p = pokehub_get_pokemon_data_by_id($pid);
                    if ($p) {
                        $pname = (string) ($p['name_fr'] ?? $p['name_en'] ?? '');
                    }
                }
                if ($pname === '') {
                    $pname = __('Pokémon', 'poke-hub');
                }
                if ($type === 'candy') {
                    return sprintf(__('%1$s Candy × %2$s', 'poke-hub'), $pname, number_format_i18n($qty));
                }
                if ($type === 'xl_candy') {
                    return sprintf(__('%1$s XL Candy × %2$s', 'poke-hub'), $pname, number_format_i18n($qty));
                }

                return sprintf(__('%1$s Mega Energy × %2$s', 'poke-hub'), $pname, number_format_i18n($qty));

            default:
                return __('Reward', 'poke-hub');
        }
    }
}

if (!function_exists('pokehub_blocks_render_event_quests')) {
    /**
     * Rendu HTML liste de quêtes (Field Research) pour le bloc et réutilisations internes.
     *
     * @param array $quests Liste de quêtes (task + rewards)
     */
    function pokehub_blocks_render_event_quests(array $quests): string {
        if ($quests === []) {
            return '';
        }

        ob_start();
        ?>
        <ul class="pokehub-event-quests-list">
            <?php
            foreach ($quests as $quest_index => $quest) :
                $task    = isset($quest['task']) ? trim((string) $quest['task']) : '';
                $rewards = isset($quest['rewards']) && is_array($quest['rewards']) ? $quest['rewards'] : [];

                if ($rewards === []) {
                    continue;
                }

                $pokemon_slots = pokehub_field_research_flatten_pokemon_slots($rewards);
                $other_rows    = pokehub_field_research_count_other_reward_rows($rewards);
                $other_rewards = array_values(array_filter($rewards, static function ($reward) {
                    return ($reward['type'] ?? 'pokemon') !== 'pokemon';
                }));

                if ($pokemon_slots === [] && $other_rows === 0) {
                    continue;
                }

                $total_lines   = count($pokemon_slots) + $other_rows;
                $rewards_label = $total_lines === 1
                    ? esc_html__('REWARD', 'poke-hub')
                    : esc_html__('POSSIBLE REWARDS', 'poke-hub');
                $quest_id      = 'pokehub-quest-' . $quest_index;

                /** Aperçu enroulé : max 3 vignettes Pokémon, le reste en +N (hauteur de ligne homogène). */
                $preview_max   = 3;
                $preview_slots = array_slice($pokemon_slots, 0, $preview_max);
                $more_pokemon  = max(0, count($pokemon_slots) - count($preview_slots));
                ?>

                <li class="pokehub-quest-item">
                    <div class="pokehub-quest-main">
                        <div class="pokehub-quest-task">
                            <?php
                            if ($task !== '') {
                                echo esc_html($task);
                            } else {
                                echo '<span class="pokehub-quest-task-placeholder">';
                                /* translators: Shown when the quest task field is empty but rewards are listed (e.g. early event announcement). */
                                esc_html_e('Task TBA', 'poke-hub');
                                echo '</span>';
                            }
                            ?>
                        </div>

                        <div class="pokehub-quest-rewards-preview" aria-hidden="false">
                            <?php if ($pokemon_slots !== []) : ?>
                                <?php
                                foreach ($preview_slots as $slot) :
                                    $pid    = (int) $slot['pokemon_id'];
                                    $rew    = $slot['reward'];
                                    $gender = $slot['gender'] ?? null;
                                    if ($pid <= 0) {
                                        continue;
                                    }
                                    $badges = pokehub_field_research_pokemon_badges($pid, $rew);
                                    $img    = pokehub_get_quest_pokemon_image($pid, false, $gender);
                                    ?>
                                    <div class="pokehub-wild-pokemon-card pokehub-field-research-preview-tile">
                                        <?php if (!empty($badges['show_shiny'])) : ?>
                                            <span class="pokehub-wild-pokemon-shiny-icon" title="<?php echo !empty($badges['is_forced_shiny']) ? esc_attr__('Forced shiny', 'poke-hub') : esc_attr__('Shiny available', 'poke-hub'); ?>">✨</span>
                                        <?php endif; ?>
                                        <?php if (!empty($badges['show_regional'])) : ?>
                                            <span class="pokehub-wild-pokemon-regional-icon" title="<?php esc_attr_e('Regional Pokémon', 'poke-hub'); ?>">🌍</span>
                                        <?php endif; ?>
                                        <div class="pokehub-wild-pokemon-card-inner">
                                            <?php if ($img !== '') : ?>
                                                <div class="pokehub-wild-pokemon-image-wrapper">
                                                    <img src="<?php echo esc_url($img); ?>" alt="" class="pokehub-wild-pokemon-image" loading="lazy" onerror="this.style.display='none';">
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <?php if ($more_pokemon > 0) : ?>
                                    <span class="pokehub-quest-preview-meta pokehub-quest-preview-more" title="<?php esc_attr_e('Additional Pokémon rewards', 'poke-hub'); ?>">+<?php echo (int) $more_pokemon; ?></span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($other_rows > 0) : ?>
                                <?php
                                $other_preview_value = '×' . (int) $other_rows;
                                $other_preview_title = __('Number of non-Pokémon reward lines', 'poke-hub');
                                if (count($other_rewards) === 1) {
                                    $other_preview_value = pokehub_field_research_reward_quantity_label($other_rewards[0]);
                                    $other_preview_title = __('Quantity of non-Pokémon reward', 'poke-hub');
                                } else {
                                    $rows_label = function_exists('number_format_i18n')
                                        ? number_format_i18n($other_rows)
                                        : (string) $other_rows;
                                    $other_preview_value = sprintf(
                                        /* translators: %s: number of non-Pokémon reward lines */
                                        __('Other × %s', 'poke-hub'),
                                        $rows_label
                                    );
                                }
                                ?>
                                <span class="pokehub-quest-preview-meta pokehub-quest-preview-other-count" title="<?php echo esc_attr($other_preview_title); ?>"><?php echo esc_html($other_preview_value); ?></span>
                            <?php endif; ?>
                        </div>

                        <span class="pokehub-quest-toggle" aria-expanded="false" data-quest-id="<?php echo esc_attr($quest_id); ?>">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </div>

                    <div class="pokehub-quest-details" id="<?php echo esc_attr($quest_id); ?>">
                        <div class="pokehub-quest-rewards-label"><?php echo $rewards_label; ?></div>

                        <div class="pokehub-quest-rewards-list">
                            <?php foreach ($pokemon_slots as $slot) :
                                $pokemon_id = (int) $slot['pokemon_id'];
                                $reward     = $slot['reward'];
                                $gender     = $slot['gender'] ?? null;
                                if ($pokemon_id <= 0) {
                                    continue;
                                }
                                $pokemon_data = function_exists('pokehub_get_pokemon_data_by_id')
                                    ? pokehub_get_pokemon_data_by_id($pokemon_id)
                                    : null;
                                if (!$pokemon_data) {
                                    continue;
                                }
                                $badges = pokehub_field_research_pokemon_badges($pokemon_id, $reward);

                                $image_url = pokehub_get_quest_pokemon_image($pokemon_id, false, $gender);

                                $cp_data = function_exists('pokehub_get_pokemon_cp_for_level')
                                    ? pokehub_get_pokemon_cp_for_level($pokemon_id, 15)
                                    : null;
                                $max_cp  = $cp_data['max_cp'] ?? null;
                                $min_cp  = $cp_data['min_cp'] ?? null;

                                $pokemon_name = $pokemon_data['name_fr'] ?? $pokemon_data['name_en'] ?? '';
                                ?>
                                <div class="pokehub-quest-reward-item pokehub-quest-reward-item--pokemon">
                                    <div class="pokehub-wild-pokemon-card pokehub-field-research-detail-tile">
                                        <?php if (!empty($badges['show_shiny'])) : ?>
                                            <span class="pokehub-wild-pokemon-shiny-icon" title="<?php echo !empty($badges['is_forced_shiny']) ? esc_attr__('Forced shiny', 'poke-hub') : esc_attr__('Shiny available', 'poke-hub'); ?>">✨</span>
                                        <?php endif; ?>
                                        <?php if (!empty($badges['show_regional'])) : ?>
                                            <span class="pokehub-wild-pokemon-regional-icon" title="<?php esc_attr_e('Regional Pokémon', 'poke-hub'); ?>">🌍</span>
                                        <?php endif; ?>
                                        <div class="pokehub-wild-pokemon-card-inner">
                                            <?php if ($image_url !== '') : ?>
                                                <div class="pokehub-wild-pokemon-image-wrapper">
                                                    <img src="<?php echo esc_url($image_url); ?>"
                                                         alt="<?php echo esc_attr($pokemon_name); ?>"
                                                         class="pokehub-wild-pokemon-image"
                                                         loading="lazy"
                                                         onerror="this.style.display='none';">
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="pokehub-quest-reward-info">
                                        <div class="pokehub-quest-reward-name"><?php echo esc_html($pokemon_name); ?></div>

                                        <?php if ($max_cp !== null || $min_cp !== null) : ?>
                                            <div class="pokehub-quest-reward-cp">
                                                <?php if ($min_cp !== null) : ?>
                                                    <div class="pokehub-quest-cp-box pokehub-quest-cp-box--min">
                                                        <span class="pokehub-quest-cp-label" title="<?php esc_attr_e('Minimum CP at level 15', 'poke-hub'); ?>"><?php echo esc_html_x('CP min', 'Short label for minimum CP (level 15)', 'poke-hub'); ?></span>
                                                        <span class="pokehub-quest-cp-value"><?php echo esc_html((string) $min_cp); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($max_cp !== null) : ?>
                                                    <div class="pokehub-quest-cp-box pokehub-quest-cp-box--max">
                                                        <span class="pokehub-quest-cp-label" title="<?php esc_attr_e('Maximum CP at level 15', 'poke-hub'); ?>"><?php echo esc_html_x('CP max', 'Short label for maximum CP (level 15)', 'poke-hub'); ?></span>
                                                        <span class="pokehub-quest-cp-value"><?php echo esc_html((string) $max_cp); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php foreach ($rewards as $reward) : ?>
                                <?php if (($reward['type'] ?? 'pokemon') === 'pokemon') {
                                    continue;
                                }
                                $o_type = $reward['type'] ?? '';
                                $resource_html = '';
                                if (in_array($o_type, ['candy', 'xl_candy', 'mega_energy'], true) && !empty($reward['pokemon_id'])
                                    && function_exists('pokehub_render_pokemon_candy_reward_html')) {
                                    $kind = function_exists('pokehub_candy_resource_kind_from_reward_type')
                                        ? pokehub_candy_resource_kind_from_reward_type($o_type)
                                        : 'candy';
                                    $resource_html = pokehub_render_pokemon_candy_reward_html(
                                        (int) $reward['pokemon_id'],
                                        (int) ($reward['quantity'] ?? 1),
                                        $kind
                                    );
                                }
                                $resource_has_img = function_exists('pokehub_pokemon_candy_reward_markup_has_image')
                                    && pokehub_pokemon_candy_reward_markup_has_image($resource_html);
                                $object_icon_html = '';
                                if (in_array($o_type, ['stardust', 'xp', 'item'], true) && function_exists('pokehub_render_reward_object_icon_img')) {
                                    $object_icon_html = pokehub_render_reward_object_icon_img($reward, [
                                        'alt' => (string) pokehub_field_research_format_other_reward_line($reward),
                                        'class' => 'pokehub-quest-reward-object-image',
                                    ]);
                                }
                                $has_visual_icon = ($resource_html !== '' || $object_icon_html !== '');
                                $compact_qty = pokehub_field_research_reward_quantity_label($reward);
                                ?>
                                <div class="pokehub-quest-reward-item pokehub-quest-reward-item--other<?php echo $resource_html !== '' ? ' pokehub-quest-reward-item--resource' : ''; ?>">
                                    <?php if ($resource_html !== '') : ?>
                                        <div class="pokehub-quest-reward-resource-visual"><?php echo $resource_html; ?></div>
                                    <?php elseif ($object_icon_html !== '') : ?>
                                        <div class="pokehub-quest-reward-resource-visual"><?php echo $object_icon_html; ?></div>
                                    <?php else : ?>
                                        <span class="pokehub-quest-reward-other-symbol" aria-hidden="true">✦</span>
                                    <?php endif; ?>
                                    <div class="pokehub-quest-reward-info">
                                        <div class="pokehub-quest-reward-name"><?php
                                        if ($resource_has_img) {
                                            $rid = (int) ($reward['pokemon_id'] ?? 0);
                                            $rp  = $rid > 0 && function_exists('pokehub_get_pokemon_data_by_id')
                                                ? pokehub_get_pokemon_data_by_id($rid)
                                                : null;
                                            $rn  = $rp
                                                ? (string) ($rp['name_fr'] ?? $rp['name_en'] ?? '')
                                                : '';
                                            $lab = function_exists('pokehub_candy_resource_label_for_reward_type')
                                                ? pokehub_candy_resource_label_for_reward_type($o_type)
                                                : __('Candy', 'poke-hub');
                                            if ($has_visual_icon) {
                                                echo esc_html($compact_qty);
                                            } else {
                                                echo esc_html($rn !== '' ? $rn . ' — ' . $lab : $lab);
                                            }
                                        } elseif ($has_visual_icon) {
                                            echo esc_html($compact_qty);
                                        } elseif ($resource_html === '') {
                                            echo esc_html(pokehub_field_research_format_other_reward_line($reward));
                                        }
                                        ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php

        return ob_get_clean();
    }
}
