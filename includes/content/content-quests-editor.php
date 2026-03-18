<?php
// includes/content/content-quests-editor.php
// Partagé entre le module Events (metabox quêtes sur les posts/events) et le module Quêtes (admin global).

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rendu d'un item de quête dans l'éditeur (metabox events ou page Quêtes).
 *
 * @param int|string $index Index de la quête
 * @param array      $quest  Données de la quête
 * @param string     $prefix Préfixe pour les noms de champs ('event' ou 'season')
 */
function pokehub_render_quest_editor_item($index, $quest, $prefix = 'event') {
    $name_prefix = $prefix === 'season' ? 'pokehub_season_quests' : 'pokehub_quests';
    ?>
    <div class="pokehub-quest-item-editor" data-quest-index="<?php echo esc_attr($index); ?>" data-quest-prefix="<?php echo esc_attr($name_prefix); ?>">
        <h4>
            <?php _e('Quest', 'poke-hub'); ?> #<?php echo is_numeric($index) ? ($index + 1) : $index; ?>
            <button type="button" class="button-link pokehub-remove-quest" style="float:right;">
                <?php _e('Remove', 'poke-hub'); ?>
            </button>
        </h4>

        <label>
            <strong><?php _e('Task', 'poke-hub'); ?>:</strong><br>
            <input
                type="text"
                name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][task]"
                value="<?php echo esc_attr($quest['task'] ?? ''); ?>"
                class="widefat"
                placeholder="<?php esc_attr_e('e.g., Catch 5 Pokémon', 'poke-hub'); ?>"
            />
        </label>
        <?php
        $quest_group_id = isset($quest['quest_group_id']) ? (int) $quest['quest_group_id'] : 0;
        $groups = function_exists('pokehub_get_quest_groups') ? pokehub_get_quest_groups() : [];
        ?>
        <label style="display: block; margin-top: 10px;">
            <strong><?php _e('Category', 'poke-hub'); ?>:</strong><br>
            <select name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][quest_group_id]" class="pokehub-quest-group-select">
                <option value="0"><?php esc_html_e('— None —', 'poke-hub'); ?></option>
                <?php foreach ($groups as $g) : ?>
                    <option value="<?php echo (int) $g->id; ?>" <?php selected($quest_group_id, (int) $g->id); ?>>
                        <?php echo esc_html(!empty($g->title_fr) ? $g->title_fr : $g->title_en); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="pokehub-quest-rewards-editor" style="margin-top: 15px;">
            <strong><?php _e('Rewards', 'poke-hub'); ?>:</strong>

            <?php if (!empty($quest['rewards'])) : ?>
                <?php foreach ($quest['rewards'] as $reward_index => $reward) : ?>
                    <div class="pokehub-quest-reward-editor">
                        <label><?php _e('Reward Type', 'poke-hub'); ?>:
                            <select name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][type]" class="pokehub-reward-type">
                                <option value="pokemon" <?php selected($reward['type'] ?? 'pokemon', 'pokemon'); ?>><?php _e('Pokémon', 'poke-hub'); ?></option>
                                <option value="stardust" <?php selected($reward['type'] ?? '', 'stardust'); ?>><?php _e('Stardust', 'poke-hub'); ?></option>
                                <option value="xp" <?php selected($reward['type'] ?? '', 'xp'); ?>><?php _e('XP', 'poke-hub'); ?></option>
                                <option value="candy" <?php selected($reward['type'] ?? '', 'candy'); ?>><?php _e('Candy', 'poke-hub'); ?></option>
                                <option value="mega_energy" <?php selected($reward['type'] ?? '', 'mega_energy'); ?>><?php _e('Mega Energy', 'poke-hub'); ?></option>
                                <option value="item" <?php selected($reward['type'] ?? '', 'item'); ?>><?php _e('Item', 'poke-hub'); ?></option>
                            </select>
                        </label>

                        <?php
                        $reward_type = $reward['type'] ?? 'pokemon';
                        $is_pokemon = $reward_type === 'pokemon';
                        $is_candy = $reward_type === 'candy';
                        $is_mega_energy = $reward_type === 'mega_energy';
                        $selected_pokemon_ids = isset($reward['pokemon_ids']) && is_array($reward['pokemon_ids'])
                            ? array_map('intval', $reward['pokemon_ids'])
                            : (isset($reward['pokemon_id']) ? [(int) $reward['pokemon_id']] : []);
                        ?>
                        <div class="pokehub-reward-pokemon-fields" style="display:<?php echo $is_pokemon ? 'block' : 'none'; ?>;">
                            <label><?php _e('Pokémon', 'poke-hub'); ?>:
                                <select
                                    name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][pokemon_ids][]"
                                    class="pokehub-select-pokemon pokehub-quest-pokemon-select"
                                    style="width: 100%; min-width: 250px;"
                                    multiple
                                    data-quest-index="<?php echo esc_attr($index); ?>"
                                    data-reward-index="<?php echo esc_attr($reward_index); ?>"
                                >
                                    <?php
                                    // Même logique que les selects "nature" : précharger la liste complète pour afficher les options
                                    // directement (Select2 local, sans dépendre d'un chargement AJAX).
                                    if (function_exists('pokehub_get_pokemon_for_select')) {
                                        $pokemon_list = pokehub_get_pokemon_for_select();
                                        foreach ($pokemon_list as $pokemon_option) {
                                            $is_selected = in_array((int) $pokemon_option['id'], $selected_pokemon_ids, true);
                                            echo '<option value="' . esc_attr($pokemon_option['id']) . '" ' . selected($is_selected, true, false) . '>' . esc_html($pokemon_option['text']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </label>
                            <label title="<?php esc_attr_e('Force shiny only if the Pokémon is shiny-lock. Otherwise, shiny status will be retrieved from the database.', 'poke-hub'); ?>">
                                <input type="checkbox" name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][force_shiny]" <?php checked(!empty($reward['force_shiny'])); ?> />
                                <?php _e('Force Shiny (if shiny-lock)', 'poke-hub'); ?>
                                <small style="display: block; color: #666; margin-top: 3px;">
                                    <?php _e('Only for shiny-lock Pokémon. Otherwise, status is retrieved from the database.', 'poke-hub'); ?>
                                </small>
                            </label>

                            <div class="pokehub-quest-pokemon-genders" data-quest-index="<?php echo esc_attr($index); ?>" data-reward-index="<?php echo esc_attr($reward_index); ?>" style="margin-top: 10px; display: none;">
                                <strong><?php _e('Genders (optional)', 'poke-hub'); ?></strong>
                                <p class="description" style="margin: 5px 0; font-size: 12px;">
                                    <?php _e('For Pokémon with gender dimorphism, you can force a specific gender. By default, the male image will be used.', 'poke-hub'); ?>
                                </p>
                                <div class="pokehub-quest-pokemon-genders-list" data-quest-index="<?php echo esc_attr($index); ?>" data-reward-index="<?php echo esc_attr($reward_index); ?>"></div>
                            </div>
                        </div>

                        <div class="pokehub-reward-pokemon-resource-fields" style="display:<?php echo ($is_candy || $is_mega_energy) ? 'block' : 'none'; ?>;">
                            <label><?php _e('Pokémon', 'poke-hub'); ?>:
                                <select
                                    name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][pokemon_id]"
                                    class="pokehub-select-pokemon-resource"
                                    style="width: 100%; min-width: 250px;"
                                    data-reward-index="<?php echo esc_attr($reward_index); ?>"
                                >
                                    <option value=""><?php _e('Select a Pokémon', 'poke-hub'); ?></option>
                                    <?php
                                    $selected_pokemon_id = isset($reward['pokemon_id']) ? (int) $reward['pokemon_id'] : 0;
                                    if ($selected_pokemon_id > 0 && function_exists('pokehub_get_pokemon_data_by_id')) {
                                        $pokemon_data = pokehub_get_pokemon_data_by_id($selected_pokemon_id);
                                        if ($pokemon_data) {
                                            $dex_number = isset($pokemon_data['dex_number']) ? (int) $pokemon_data['dex_number'] : 0;
                                            $name = $pokemon_data['name'] ?? ($pokemon_data['name_fr'] ?? $pokemon_data['name_en'] ?? '');
                                            $form = !empty($pokemon_data['form']) ? ' (' . $pokemon_data['form'] . ')' : '';
                                            $text = $name;
                                            if ($dex_number > 0) {
                                                $text .= ' #' . str_pad((string) $dex_number, 3, '0', STR_PAD_LEFT);
                                            }
                                            $text .= $form;
                                            echo '<option value="' . esc_attr($selected_pokemon_id) . '" selected>' . esc_html($text) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </label>
                            <label class="pokehub-reward-quantity-field" style="display:<?php echo ($is_candy || $is_mega_energy) ? 'block' : 'none'; ?>;">
                                <?php _e('Quantity', 'poke-hub'); ?>:
                                <input type="number" name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][quantity]" value="<?php echo esc_attr($reward['quantity'] ?? 1); ?>" min="1" />
                            </label>
                        </div>

                        <div class="pokehub-reward-other-fields" style="display:<?php echo ($is_pokemon || $is_candy || $is_mega_energy) ? 'none' : 'block'; ?>;">
                            <?php
                            $reward_type = $reward['type'] ?? '';
                            $is_stardust = $reward_type === 'stardust';
                            $is_xp = $reward_type === 'xp';
                            $is_item = $reward_type === 'item';
                            $is_candy_reward = $reward_type === 'candy';
                            $is_mega_energy_reward = $reward_type === 'mega_energy';
                            ?>
                            <label class="pokehub-reward-quantity-field" style="display:<?php echo ($is_stardust || $is_xp || $is_item || $is_candy_reward || $is_mega_energy_reward) ? 'block' : 'none'; ?>;">
                                <?php _e('Quantity', 'poke-hub'); ?>:
                                <input type="number" name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][quantity]" value="<?php echo esc_attr($reward['quantity'] ?? 1); ?>" min="1" />
                            </label>
                            <label class="pokehub-reward-item-name-field" style="display:<?php echo $is_item ? 'block' : 'none'; ?>;">
                                <?php _e('Item', 'poke-hub'); ?>:
                                <select
                                    name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][item_id]"
                                    class="pokehub-select-item"
                                    style="width: 100%; min-width: 250px;"
                                >
                                    <option value=""><?php _e('Select an item', 'poke-hub'); ?></option>
                                    <?php
                                    $selected_item_id = isset($reward['item_id']) ? (int) $reward['item_id'] : 0;
                                    if ($selected_item_id > 0 && function_exists('pokehub_get_item_data_by_id')) {
                                        $item_data = pokehub_get_item_data_by_id($selected_item_id);
                                        if ($item_data) {
                                            $name_fr = $item_data['name_fr'] ?? '';
                                            $name_en = $item_data['name_en'] ?? '';
                                            $text = $name_fr;
                                            if ($name_en && $name_fr !== $name_en) {
                                                $text .= ' (' . $name_en . ')';
                                            } elseif (!$name_fr && $name_en) {
                                                $text = $name_en;
                                            }
                                            echo '<option value="' . esc_attr($selected_item_id) . '" selected>' . esc_html($text) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <input type="hidden" name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][rewards][<?php echo esc_attr($reward_index); ?>][item_name]" class="pokehub-item-name-field" value="<?php echo esc_attr($reward['item_name'] ?? ''); ?>" />
                            </label>
                        </div>

                        <button type="button" class="button-link pokehub-remove-reward"><?php _e('Remove', 'poke-hub'); ?></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <button type="button" class="button button-small pokehub-add-reward">
                <?php _e('Add Reward', 'poke-hub'); ?>
            </button>
        </div>
    </div>
    <?php
}
