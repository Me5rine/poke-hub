<?php
/**
 * Ligne d’édition d’une récompense Pass GO (sélects comme les quêtes Field Research).
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string               $name_base Préfixe complet, ex. gp_tiers[0][free_rewards][2].
 * @param array<string, mixed> $reward    Données récompense (type, ids, quantity…).
 */
function pokehub_go_pass_render_reward_editor(string $name_base, array $reward = []): void {
    $type = isset($reward['type']) ? sanitize_key((string) $reward['type']) : 'xp';
    if (!in_array($type, pokehub_go_pass_reward_type_slugs(), true)) {
        $type = 'pokemon';
    }

    $is_pokemon            = ($type === 'pokemon');
    $is_candy              = ($type === 'candy');
    $is_xl_candy           = ($type === 'xl_candy');
    $is_mega_energy        = ($type === 'mega_energy');
    $is_pokemon_resource   = $is_candy || $is_xl_candy || $is_mega_energy;
    $is_stardust           = ($type === 'stardust');
    $is_xp                 = ($type === 'xp');
    $is_item               = ($type === 'item');
    $is_bonus              = ($type === 'bonus');
    $selected_pokemon_id   = isset($reward['pokemon_id']) ? (int) $reward['pokemon_id'] : 0;
    $selected_ids_attr     = $selected_pokemon_id > 0 ? [(int) $selected_pokemon_id] : [];
    $qty                   = isset($reward['quantity']) ? max(1, (int) $reward['quantity']) : 1;
    $bonus_catalog         = function_exists('pokehub_get_all_bonuses_for_select') ? pokehub_get_all_bonuses_for_select() : [];
    $selected_bonus_id     = isset($reward['bonus_id']) ? (int) $reward['bonus_id'] : 0;
    $bonus_desc            = isset($reward['description']) ? (string) $reward['description'] : '';
    ?>
    <div class="pokehub-quest-reward-editor pokehub-go-pass-reward-editor" style="margin-top:8px;padding:8px;background:#fff;border:1px solid #ccd0d4;">
        <label>
            <?php esc_html_e('Type de récompense', 'poke-hub'); ?> :
            <select name="<?php echo esc_attr($name_base); ?>[type]" class="pokehub-reward-type">
                <option value="xp" <?php selected($type, 'xp'); ?>><?php esc_html_e('XP', 'poke-hub'); ?></option>
                <option value="stardust" <?php selected($type, 'stardust'); ?>><?php esc_html_e('Poussière d’étoile', 'poke-hub'); ?></option>
                <option value="item" <?php selected($type, 'item'); ?>><?php esc_html_e('Objet', 'poke-hub'); ?></option>
                <option value="pokemon" <?php selected($type, 'pokemon'); ?>><?php esc_html_e('Pokémon', 'poke-hub'); ?></option>
                <option value="candy" <?php selected($type, 'candy'); ?>><?php esc_html_e('Bonbon', 'poke-hub'); ?></option>
                <option value="xl_candy" <?php selected($type, 'xl_candy'); ?>><?php esc_html_e('Bonbon XL', 'poke-hub'); ?></option>
                <option value="mega_energy" <?php selected($type, 'mega_energy'); ?>><?php esc_html_e('Méga-énergie', 'poke-hub'); ?></option>
                <option value="bonus" <?php selected($type, 'bonus'); ?>><?php esc_html_e('Bonus', 'poke-hub'); ?></option>
            </select>
        </label>

        <p style="margin:8px 0 0;">
            <label>
                <input type="checkbox" name="<?php echo esc_attr($name_base); ?>[featured]" value="1" <?php checked(!empty($reward['featured'])); ?> />
                <?php esc_html_e('Mettre en avant dans le résumé', 'poke-hub'); ?>
            </label>
        </p>

        <div class="pokehub-go-pass-reward-bonus-fields" style="display:<?php echo $is_bonus ? 'block' : 'none'; ?>;">
            <p class="description" style="margin:0 0 6px;">
                <?php esc_html_e('After choosing “Bonus”, pick the catalog entry below (one bonus per reward row).', 'poke-hub'); ?>
            </p>
            <label>
                <?php esc_html_e('Catalog bonus', 'poke-hub'); ?> :
                <select
                    name="<?php echo esc_attr($name_base); ?>[bonus_id]"
                    class="pokehub-go-pass-bonus-reward-select"
                    style="width: 100%; min-width: 240px;"
                    data-placeholder="<?php esc_attr_e('Select a catalog bonus…', 'poke-hub'); ?>"
                    <?php echo $is_bonus ? '' : ' disabled'; ?>
                >
                    <option value="0"><?php esc_html_e('— Select a bonus —', 'poke-hub'); ?></option>
                    <?php foreach ($bonus_catalog as $cat) : ?>
                        <option value="<?php echo esc_attr((string) (int) ($cat['id'] ?? 0)); ?>" <?php selected($selected_bonus_id, (int) ($cat['id'] ?? 0)); ?>>
                            <?php echo esc_html((string) ($cat['label'] ?? '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label style="display:block;margin-top:8px;">
                <?php esc_html_e('Texte complémentaire sur le Pass (optionnel)', 'poke-hub'); ?> :
                <textarea name="<?php echo esc_attr($name_base); ?>[description]" class="large-text" rows="2" style="width:100%;" <?php echo $is_bonus ? '' : ' disabled'; ?>><?php echo esc_textarea($bonus_desc); ?></textarea>
            </label>
        </div>

        <div class="pokehub-reward-pokemon-fields" style="display:<?php echo $is_pokemon ? 'block' : 'none'; ?>;">
            <label>
                <?php esc_html_e('Pokémon', 'poke-hub'); ?> (<?php esc_html_e('un exemplaire', 'poke-hub'); ?>) :
                <select
                    name="<?php echo esc_attr($name_base); ?>[pokemon_id]"
                    class="pokehub-select-pokemon pokehub-quest-pokemon-select"
                    style="width: 100%; min-width: 250px;"
                    data-placeholder="<?php esc_attr_e('Rechercher un Pokémon…', 'poke-hub'); ?>"
                    <?php
                    echo $is_pokemon ? '' : ' disabled';
                    if ($selected_ids_attr !== []) {
                        echo ' data-selected-ids="' . esc_attr(implode(',', $selected_ids_attr)) . '"';
                    }
                    ?>
                >
                    <?php
                    foreach ($selected_ids_attr as $pid) {
                        $label = '#' . $pid;
                        if (function_exists('pokehub_get_pokemon_data_by_id')) {
                            $pokemon_data = pokehub_get_pokemon_data_by_id($pid);
                            if ($pokemon_data) {
                                $dex_number = isset($pokemon_data['dex_number']) ? (int) $pokemon_data['dex_number'] : 0;
                                $name       = $pokemon_data['name'] ?? ($pokemon_data['name_fr'] ?? $pokemon_data['name_en'] ?? '');
                                $form       = !empty($pokemon_data['form']) ? ' (' . $pokemon_data['form'] . ')' : '';
                                $label      = $name;
                                if ($dex_number > 0) {
                                    $label .= ' #' . str_pad((string) $dex_number, 3, '0', STR_PAD_LEFT);
                                }
                                $label .= $form;
                            }
                        }
                        echo '<option value="' . esc_attr((string) $pid) . '" selected>' . esc_html($label) . '</option>';
                    }
                    ?>
                </select>
            </label>
            <div class="pokehub-go-pass-pokemon-flags" style="margin-top:10px;">
                <span class="description" style="display:block;margin-bottom:6px;"><?php esc_html_e('À activer seulement si vous voulez forcer l’affichage / la variante sur le Pass.', 'poke-hub'); ?></span>
                <label style="display:inline-block;margin-right:12px;">
                    <input type="checkbox" name="<?php echo esc_attr($name_base); ?>[force_shiny]" value="1" <?php checked(!empty($reward['force_shiny'])); ?> <?php echo $is_pokemon ? '' : ' disabled'; ?> />
                    <?php esc_html_e('Shiny', 'poke-hub'); ?>
                </label>
                <label style="display:inline-block;margin-right:12px;">
                    <input type="checkbox" name="<?php echo esc_attr($name_base); ?>[force_shadow]" value="1" <?php checked(!empty($reward['force_shadow'])); ?> <?php echo $is_pokemon ? '' : ' disabled'; ?> />
                    <?php esc_html_e('Obscur', 'poke-hub'); ?>
                </label>
                <label style="display:inline-block;margin-right:12px;">
                    <input type="checkbox" name="<?php echo esc_attr($name_base); ?>[force_dynamax]" value="1" <?php checked(!empty($reward['force_dynamax'])); ?> <?php echo $is_pokemon ? '' : ' disabled'; ?> />
                    <?php esc_html_e('Dynamax', 'poke-hub'); ?>
                </label>
                <label style="display:inline-block;margin-right:12px;">
                    <input type="checkbox" name="<?php echo esc_attr($name_base); ?>[force_gigamax]" value="1" <?php checked(!empty($reward['force_gigamax'])); ?> <?php echo $is_pokemon ? '' : ' disabled'; ?> />
                    <?php esc_html_e('Gigamax', 'poke-hub'); ?>
                </label>
            </div>
        </div>

        <div class="pokehub-reward-pokemon-resource-fields" style="display:<?php echo $is_pokemon_resource ? 'block' : 'none'; ?>;">
            <label>
                <?php esc_html_e('Pokémon', 'poke-hub'); ?> :
                <select
                    name="<?php echo esc_attr($name_base); ?>[pokemon_id]"
                    class="pokehub-select-pokemon-resource"
                    style="width: 100%; min-width: 250px;"
                    <?php echo $is_pokemon_resource ? '' : ' disabled'; ?>
                >
                    <option value=""><?php esc_html_e('Choisir un Pokémon', 'poke-hub'); ?></option>
                    <?php
                    if ($selected_pokemon_id > 0 && function_exists('pokehub_get_pokemon_data_by_id')) {
                        $pokemon_data = pokehub_get_pokemon_data_by_id($selected_pokemon_id);
                        if ($pokemon_data) {
                            $dex_number = isset($pokemon_data['dex_number']) ? (int) $pokemon_data['dex_number'] : 0;
                            $name       = $pokemon_data['name'] ?? ($pokemon_data['name_fr'] ?? $pokemon_data['name_en'] ?? '');
                            $form       = !empty($pokemon_data['form']) ? ' (' . $pokemon_data['form'] . ')' : '';
                            $text       = $name;
                            if ($dex_number > 0) {
                                $text .= ' #' . str_pad((string) $dex_number, 3, '0', STR_PAD_LEFT);
                            }
                            $text .= $form;
                            echo '<option value="' . esc_attr((string) $selected_pokemon_id) . '" selected>' . esc_html($text) . '</option>';
                        }
                    }
                    ?>
                </select>
            </label>
            <label class="pokehub-reward-quantity-field" style="display:<?php echo $is_pokemon_resource ? 'block' : 'none'; ?>;">
                <?php esc_html_e('Quantité', 'poke-hub'); ?> :
                <input type="number" name="<?php echo esc_attr($name_base); ?>[quantity]" value="<?php echo esc_attr((string) $qty); ?>" min="1" <?php echo $is_pokemon_resource ? '' : ' disabled'; ?> />
            </label>
        </div>

        <div class="pokehub-reward-other-fields" style="display:<?php echo ($is_pokemon || $is_pokemon_resource || $is_bonus) ? 'none' : 'block'; ?>;">
            <label class="pokehub-reward-quantity-field" style="display:<?php echo ($is_stardust || $is_xp || $is_item) ? 'block' : 'none'; ?>;">
                <?php esc_html_e('Quantité', 'poke-hub'); ?> :
                <input type="number" name="<?php echo esc_attr($name_base); ?>[quantity]" value="<?php echo esc_attr((string) $qty); ?>" min="1" <?php echo ($is_stardust || $is_xp || $is_item) ? '' : ' disabled'; ?> />
            </label>
            <label class="pokehub-reward-item-name-field" style="display:<?php echo $is_item ? 'block' : 'none'; ?>;">
                <?php esc_html_e('Objet', 'poke-hub'); ?> :
                <select
                    name="<?php echo esc_attr($name_base); ?>[item_id]"
                    class="pokehub-select-item"
                    style="width: 100%; min-width: 250px;"
                    <?php echo $is_item ? '' : ' disabled'; ?>
                >
                    <option value=""><?php esc_html_e('Choisir un objet', 'poke-hub'); ?></option>
                    <?php
                    $selected_item_id = isset($reward['item_id']) ? (int) $reward['item_id'] : 0;
                    if ($selected_item_id > 0 && function_exists('pokehub_get_item_data_by_id')) {
                        $item_data = pokehub_get_item_data_by_id($selected_item_id);
                        if ($item_data) {
                            $name_fr = $item_data['name_fr'] ?? '';
                            $name_en = $item_data['name_en'] ?? '';
                            $text    = $name_fr;
                            if ($name_en && $name_fr !== $name_en) {
                                $text .= ' (' . $name_en . ')';
                            } elseif (!$name_fr && $name_en) {
                                $text = $name_en;
                            }
                            echo '<option value="' . esc_attr((string) $selected_item_id) . '" selected>' . esc_html($text) . '</option>';
                        }
                    }
                    ?>
                </select>
            </label>
        </div>

        <p style="margin:8px 0 0;">
            <button type="button" class="button-link pokehub-gp-remove-reward"><?php esc_html_e('Supprimer cette récompense', 'poke-hub'); ?></button>
        </p>
    </div>
    <?php
}

/**
 * @param array<string, mixed> $row  Ligne éditeur (tier ou milestone).
 * @param string               $side free|premium
 * @return array<int, array<string, mixed>>
 */
function pokehub_go_pass_editor_reward_rows_for_side(array $row, string $side): array {
    $side = ($side === 'premium') ? 'premium' : 'free';
    $key  = $side . '_rewards';
    $list = isset($row[$key]) && is_array($row[$key])
        ? pokehub_go_pass_normalize_rewards_list($row[$key])
        : [];

    if ($list === []) {
        return [['type' => 'xp']];
    }

    return $list;
}

/**
 * Colonne « Gratuit » ou « Deluxe » : liste de récompenses + bouton ajouter.
 *
 * @param int                  $tier_index Index de ligne gp_tiers.
 * @param array<string, mixed> $row        Ligne éditeur (tier / milestone).
 * @param string               $side       free|premium
 */
function pokehub_go_pass_render_rewards_column(int $tier_index, array $row, string $side): void {
    $side   = ($side === 'premium') ? 'premium' : 'free';
    $prefix = 'gp_tiers[' . $tier_index . '][' . $side . '_rewards]';
    $rows   = pokehub_go_pass_editor_reward_rows_for_side($row, $side);
    ?>
    <div class="pokehub-go-pass-rewards-col">
        <div class="pokehub-go-pass-rewards-list">
            <?php
            foreach (array_values($rows) as $ri => $rw) {
                pokehub_go_pass_render_reward_editor($prefix . '[' . $ri . ']', is_array($rw) ? $rw : []);
            }
            ?>
        </div>
        <p style="margin:8px 0 0;">
            <button type="button" class="button button-small pokehub-gp-add-reward" data-prefix="<?php echo esc_attr($prefix); ?>">
                <?php esc_html_e('Ajouter une récompense', 'poke-hub'); ?>
            </button>
        </p>
    </div>
    <?php
}
