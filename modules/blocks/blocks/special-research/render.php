<?php
/**
 * Rendu du bloc "Études Spéciales"
 *
 * @var array    $attributes Les attributs du bloc.
 * @var string   $content    Le contenu HTML du bloc.
 * @var WP_Block $block      L'instance du bloc.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Récupération robuste du post_id
$post_id = 0;
if (isset($block) && is_object($block) && !empty($block->context['postId'])) {
    $post_id = (int) $block->context['postId'];
}
if (!$post_id) {
    $post_id = (int) get_the_ID();
}
if (!$post_id) {
    $post_id = (int) get_queried_object_id();
}
if (!$post_id && !empty($GLOBALS['post']->ID)) {
    $post_id = (int) $GLOBALS['post']->ID;
}

if (!$post_id) {
    return '';
}

$auto_detect = $attributes['autoDetect'] ?? true;

// Récupérer les études depuis les meta
$research_data = ['steps' => [], 'research_type' => 'special'];
if ($auto_detect && function_exists('pokehub_content_get_special_research')) {
    $research_data = pokehub_content_get_special_research('post', (int) $post_id);
}
$research = isset($research_data['steps']) && is_array($research_data['steps']) ? $research_data['steps'] : [];

if (empty($research)) {
    return '';
}

// Vérifier que les fonctions sont disponibles
if (!function_exists('pokehub_get_pokemon_data_by_id') || !function_exists('poke_hub_pokemon_get_image_url')) {
    return '';
}

$research_type = !empty($research_data['research_type']) ? $research_data['research_type'] : 'special';

$type_labels = [
    'timed' => __('Timed Research', 'poke-hub'),
    'special' => __('Special Research', 'poke-hub'),
    'masterwork' => __('Masterwork Research', 'poke-hub'),
];

$type_label = $type_labels[$research_type] ?? $type_labels['special'];

/**
 * Traite une étape d'étude spéciale
 */
if (!function_exists('pokehub_process_research_step')) {
    function pokehub_process_research_step(array $step): array {
        $quests = !empty($step['quests']) && is_array($step['quests']) ? $step['quests'] : [];
        $step_rewards = !empty($step['rewards']) && is_array($step['rewards']) ? $step['rewards'] : [];
        
        $processed_quests = [];
        foreach ($quests as $quest) {
            $quest_rewards = !empty($quest['rewards']) && is_array($quest['rewards']) ? $quest['rewards'] : [];
            $processed_quests[] = [
                'task' => !empty($quest['task']) ? sanitize_text_field($quest['task']) : '',
                'rewards' => $quest_rewards,
            ];
        }
        
        return [
            'type' => 'quest',
            'quests' => $processed_quests,
            'rewards' => $step_rewards,
        ];
    }
}

// Traiter chaque étude
$processed_research = [];
foreach ($research as $research_item) {
    if (empty($research_item['name'])) {
        continue;
    }
    
    $processed_item = [
        'name' => sanitize_text_field($research_item['name']),
        'common_initial_steps' => [],
        'paths' => [],
        'common_final_steps' => [],
    ];
    
    // Traiter les étapes communes initiales
    if (!empty($research_item['common_initial_steps']) && is_array($research_item['common_initial_steps'])) {
        foreach ($research_item['common_initial_steps'] as $step) {
            $processed_item['common_initial_steps'][] = pokehub_process_research_step($step);
        }
    }
    
    // Traiter les chemins
    if (!empty($research_item['paths']) && is_array($research_item['paths'])) {
        foreach ($research_item['paths'] as $path) {
            $processed_path = [
                'name' => !empty($path['name']) ? sanitize_text_field($path['name']) : '',
                'image_url' => !empty($path['image_url']) ? esc_url($path['image_url']) : '',
                'color' => !empty($path['color']) ? sanitize_hex_color($path['color']) : '#ff6b6b',
                'steps' => [],
            ];
            
            if (!empty($path['steps']) && is_array($path['steps'])) {
                foreach ($path['steps'] as $step) {
                    $processed_path['steps'][] = pokehub_process_research_step($step);
                }
            }
            
            $processed_item['paths'][] = $processed_path;
        }
    }
    
    // Traiter les étapes communes finales
    if (!empty($research_item['common_final_steps']) && is_array($research_item['common_final_steps'])) {
        foreach ($research_item['common_final_steps'] as $step) {
            $processed_item['common_final_steps'][] = pokehub_process_research_step($step);
        }
    }
    
    $processed_research[] = $processed_item;
}

/**
 * Affiche les récompenses d'une étude spéciale
 */
if (!function_exists('pokehub_render_research_rewards')) {
    function pokehub_render_research_rewards(array $rewards) {
    foreach ($rewards as $reward) : 
        $type = $reward['type'] ?? '';
        $quantity = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
    ?>
        <?php if ($type === 'stardust') : ?>
            <?php
            $stardust_icon_html = function_exists('pokehub_render_reward_object_icon_img')
                ? pokehub_render_reward_object_icon_img(
                    ['type' => 'stardust'],
                    ['alt' => __('Stardust', 'poke-hub')]
                )
                : '';
            ?>
            <div class="pokehub-research-reward pokehub-research-reward-stardust">
                <?php if ($stardust_icon_html !== '') : ?>
                    <div class="pokehub-research-reward-image">
                        <?php echo $stardust_icon_html; ?>
                    </div>
                <?php endif; ?>
                <div class="pokehub-research-reward-info">
                    <div class="pokehub-research-reward-name">
                        <?php echo esc_html(number_format($quantity, 0, ',', ' ')); ?>x <?php esc_html_e('Stardust', 'poke-hub'); ?>
                    </div>
                </div>
            </div>
        <?php elseif ($type === 'xp') : ?>
            <?php
            $xp_icon_html = function_exists('pokehub_render_reward_object_icon_img')
                ? pokehub_render_reward_object_icon_img(
                    ['type' => 'xp'],
                    ['alt' => __('XP', 'poke-hub')]
                )
                : '';
            ?>
            <div class="pokehub-research-reward pokehub-research-reward-xp">
                <?php if ($xp_icon_html !== '') : ?>
                    <div class="pokehub-research-reward-image">
                        <?php echo $xp_icon_html; ?>
                    </div>
                <?php endif; ?>
                <div class="pokehub-research-reward-info">
                    <div class="pokehub-research-reward-name">
                        <?php echo esc_html(number_format($quantity, 0, ',', ' ')); ?>x <?php esc_html_e('XP', 'poke-hub'); ?>
                    </div>
                </div>
            </div>
        <?php elseif ($type === 'pokemon' && !empty($reward['pokemon_ids']) && is_array($reward['pokemon_ids'])) : ?>
            <?php foreach ($reward['pokemon_ids'] as $pokemon_id) : 
                $pokemon_data = pokehub_get_pokemon_data_by_id((int) $pokemon_id);
                if ($pokemon_data) :
                    $pokemon_name = $pokemon_data['name'] ?? ($pokemon_data['name_fr'] ?? $pokemon_data['name_en'] ?? '');
                    $pokemon_obj = (object) $pokemon_data;
                    $pokemon_image_url = poke_hub_pokemon_get_image_url($pokemon_obj, ['shiny' => false]);
                    $cp_data = function_exists('pokehub_get_pokemon_cp_for_level') 
                        ? pokehub_get_pokemon_cp_for_level((int) $pokemon_id, 15) 
                        : null;
                    $max_cp = $cp_data['max_cp'] ?? null;
            ?>
                <div class="pokehub-research-reward pokehub-research-reward-pokemon">
                    <?php if ($pokemon_image_url) : ?>
                        <img src="<?php echo esc_url($pokemon_image_url); ?>" alt="<?php echo esc_attr($pokemon_name); ?>" class="pokehub-research-reward-pokemon-image" />
                    <?php endif; ?>
                    <div class="pokehub-research-reward-pokemon-info">
                        <div class="pokehub-research-reward-pokemon-name"><?php echo esc_html($pokemon_name); ?></div>
                        <?php if ($max_cp !== null) : ?>
                            <div class="pokehub-research-reward-pokemon-cp">
                                <span class="pokehub-research-cp-label"><?php esc_html_e('Max CP', 'poke-hub'); ?></span>
                                <span class="pokehub-research-cp-value"><?php echo esc_html($max_cp); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; endforeach; ?>
        <?php elseif (in_array($type, ['candy', 'xl_candy', 'mega_energy'], true) && !empty($reward['pokemon_id'])) : ?>
            <?php
            $pokemon_data = function_exists('pokehub_get_pokemon_data_by_id')
                ? pokehub_get_pokemon_data_by_id((int) $reward['pokemon_id'])
                : null;
            if ($pokemon_data) :
                $pokemon_name = isset($pokemon_data['name_fr']) && $pokemon_data['name_fr'] !== ''
                    ? (string) $pokemon_data['name_fr']
                    : (string) ($pokemon_data['name_en'] ?? '');
                $kind = function_exists('pokehub_candy_resource_kind_from_reward_type')
                    ? pokehub_candy_resource_kind_from_reward_type($type)
                    : 'candy';
                $resource_html = function_exists('pokehub_render_pokemon_candy_reward_html')
                    ? pokehub_render_pokemon_candy_reward_html(
                        (int) $reward['pokemon_id'],
                        $quantity,
                        $kind
                    )
                    : '';
                $has_img = function_exists('pokehub_pokemon_candy_reward_markup_has_image')
                    && pokehub_pokemon_candy_reward_markup_has_image($resource_html);
                $type_label = function_exists('pokehub_candy_resource_label_for_reward_type')
                    ? pokehub_candy_resource_label_for_reward_type($type)
                    : __('Candy', 'poke-hub');
                ?>
            <div class="pokehub-research-reward pokehub-research-reward-resource">
                <?php if ($resource_html !== '') : ?>
                    <div class="pokehub-research-reward-image pokehub-research-reward-image--resource">
                        <?php echo $resource_html; ?>
                    </div>
                <?php endif; ?>
                <?php if ($has_img && $pokemon_name !== '') : ?>
                    <div class="pokehub-research-reward-info">
                        <div class="pokehub-research-reward-name">
                            <?php echo esc_html($pokemon_name . ' — ' . $type_label); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
                <?php
            endif;
            ?>
        <?php elseif ($type === 'item' && (!empty($reward['item_name']) || !empty($reward['item_id']))) : ?>
            <?php
            $item_name = isset($reward['item_name']) ? (string) $reward['item_name'] : '';
            if ($item_name === '' && !empty($reward['item_id']) && function_exists('pokehub_get_item_data_by_id')) {
                $item_data = pokehub_get_item_data_by_id((int) $reward['item_id']);
                if (is_array($item_data)) {
                    $item_name = (string) ($item_data['name_fr'] ?? $item_data['name_en'] ?? '');
                }
            }
            $item_icon_html = function_exists('pokehub_render_reward_object_icon_img')
                ? pokehub_render_reward_object_icon_img(
                    $reward,
                    ['alt' => $item_name]
                )
                : '';
            ?>
            <div class="pokehub-research-reward pokehub-research-reward-item">
                <?php if ($item_icon_html !== '') : ?>
                    <div class="pokehub-research-reward-image">
                        <?php echo $item_icon_html; ?>
                    </div>
                <?php endif; ?>
                <div class="pokehub-research-reward-info">
                    <div class="pokehub-research-reward-name">
                        <?php echo esc_html($quantity); ?>x <?php echo esc_html($item_name); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach;
    }
    }

/**
 * Affiche les récompenses finales d'une étape (style défis de collection)
 */
if (!function_exists('pokehub_render_research_final_rewards')) {
    function pokehub_render_research_final_rewards(array $rewards) {
        foreach ($rewards as $reward) : 
            $type = $reward['type'] ?? '';
            $quantity = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
        ?>
            <?php if ($type === 'stardust') : ?>
                <?php
                $stardust_icon_html = function_exists('pokehub_render_reward_object_icon_img')
                    ? pokehub_render_reward_object_icon_img(
                        ['type' => 'stardust'],
                        ['alt' => __('Stardust', 'poke-hub')]
                    )
                    : '';
                ?>
                <div class="pokehub-collection-challenge-reward-item">
                    <?php if ($stardust_icon_html !== '') : ?>
                        <div class="pokehub-collection-challenge-reward-image">
                            <?php echo $stardust_icon_html; ?>
                        </div>
                    <?php endif; ?>
                    <div class="pokehub-collection-challenge-reward-info">
                        <div class="pokehub-collection-challenge-reward-name">
                            <?php echo esc_html(number_format($quantity, 0, ',', ' ')); ?>x <?php esc_html_e('Stardust', 'poke-hub'); ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($type === 'xp') : ?>
                <?php
                $xp_icon_html = function_exists('pokehub_render_reward_object_icon_img')
                    ? pokehub_render_reward_object_icon_img(
                        ['type' => 'xp'],
                        ['alt' => __('XP', 'poke-hub')]
                    )
                    : '';
                ?>
                <div class="pokehub-collection-challenge-reward-item">
                    <?php if ($xp_icon_html !== '') : ?>
                        <div class="pokehub-collection-challenge-reward-image">
                            <?php echo $xp_icon_html; ?>
                        </div>
                    <?php endif; ?>
                    <div class="pokehub-collection-challenge-reward-info">
                        <div class="pokehub-collection-challenge-reward-name">
                            <?php echo esc_html(number_format($quantity, 0, ',', ' ')); ?>x <?php esc_html_e('XP', 'poke-hub'); ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($type === 'pokemon' && !empty($reward['pokemon_ids']) && is_array($reward['pokemon_ids'])) : ?>
                <?php foreach ($reward['pokemon_ids'] as $pokemon_id) : 
                    $pokemon_data = pokehub_get_pokemon_data_by_id((int) $pokemon_id);
                    if ($pokemon_data) :
                        $pokemon_name = $pokemon_data['name'] ?? ($pokemon_data['name_fr'] ?? $pokemon_data['name_en'] ?? '');
                        $pokemon_obj = (object) $pokemon_data;
                        $pokemon_image_url = poke_hub_pokemon_get_image_url($pokemon_obj, ['shiny' => false]);
                        $cp_data = function_exists('pokehub_get_pokemon_cp_for_level') 
                            ? pokehub_get_pokemon_cp_for_level((int) $pokemon_id, 15) 
                            : null;
                        $max_cp = $cp_data['max_cp'] ?? null;
                ?>
                    <div class="pokehub-collection-challenge-reward-item pokehub-collection-challenge-reward-pokemon">
                        <?php if ($pokemon_image_url) : ?>
                            <div class="pokehub-collection-challenge-reward-image">
                                <img src="<?php echo esc_url($pokemon_image_url); ?>" alt="<?php echo esc_attr($pokemon_name); ?>" />
                            </div>
                        <?php endif; ?>
                        <div class="pokehub-collection-challenge-reward-info">
                            <div class="pokehub-collection-challenge-reward-name"><?php echo esc_html($pokemon_name); ?></div>
                            <?php if ($max_cp !== null) : ?>
                                <div class="pokehub-collection-challenge-reward-cp">
                                    <div class="pokehub-collection-challenge-cp-box">
                                        <div class="pokehub-collection-challenge-cp-label"><?php esc_html_e('Max CP', 'poke-hub'); ?></div>
                                        <div class="pokehub-collection-challenge-cp-value"><?php echo esc_html($max_cp); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; endforeach; ?>
            <?php elseif (in_array($type, ['candy', 'xl_candy', 'mega_energy'], true) && !empty($reward['pokemon_id'])) : ?>
                <?php
                $pokemon_data = pokehub_get_pokemon_data_by_id((int) $reward['pokemon_id']);
                if ($pokemon_data) :
                    $name_fr = $pokemon_data['name_fr'] ?? '';
                    $name_en = $pokemon_data['name_en'] ?? '';
                    $pokemon_name = !empty($name_fr) ? $name_fr : (!empty($name_en) ? $name_en : '');
                    $kind = function_exists('pokehub_candy_resource_kind_from_reward_type')
                        ? pokehub_candy_resource_kind_from_reward_type($type)
                        : 'candy';
                    $resource_html = function_exists('pokehub_render_pokemon_candy_reward_html')
                        ? pokehub_render_pokemon_candy_reward_html(
                            (int) $reward['pokemon_id'],
                            $quantity,
                            $kind
                        )
                        : '';
                    $has_img = function_exists('pokehub_pokemon_candy_reward_markup_has_image')
                        && pokehub_pokemon_candy_reward_markup_has_image($resource_html);
                    $type_label = function_exists('pokehub_candy_resource_label_for_reward_type')
                        ? pokehub_candy_resource_label_for_reward_type($type)
                        : __('Candy', 'poke-hub');
                    ?>
                    <div class="pokehub-collection-challenge-reward-item pokehub-collection-challenge-reward-item--resource">
                        <?php if ($resource_html !== '') : ?>
                            <div class="pokehub-collection-challenge-reward-image pokehub-collection-challenge-reward-image--resource">
                                <?php echo $resource_html; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($has_img && $pokemon_name !== '') : ?>
                            <div class="pokehub-collection-challenge-reward-info">
                                <div class="pokehub-collection-challenge-reward-name">
                                    <?php echo esc_html($pokemon_name . ' — ' . $type_label); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php elseif ($type === 'item' && (!empty($reward['item_name']) || !empty($reward['item_id']))) : ?>
                <?php
                $item_name = isset($reward['item_name']) ? (string) $reward['item_name'] : '';
                if ($item_name === '' && !empty($reward['item_id']) && function_exists('pokehub_get_item_data_by_id')) {
                    $item_data = pokehub_get_item_data_by_id((int) $reward['item_id']);
                    if (is_array($item_data)) {
                        $item_name = (string) ($item_data['name_fr'] ?? $item_data['name_en'] ?? '');
                    }
                }
                $item_icon_html = function_exists('pokehub_render_reward_object_icon_img')
                    ? pokehub_render_reward_object_icon_img(
                        $reward,
                        ['alt' => $item_name]
                    )
                    : '';
                ?>
                <div class="pokehub-collection-challenge-reward-item">
                    <?php if ($item_icon_html !== '') : ?>
                        <div class="pokehub-collection-challenge-reward-image">
                            <?php echo $item_icon_html; ?>
                        </div>
                    <?php endif; ?>
                    <div class="pokehub-collection-challenge-reward-info">
                        <div class="pokehub-collection-challenge-reward-name">
                            <?php echo esc_html($quantity); ?>x <?php echo esc_html($item_name); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach;
    }
    }

/**
 * Affiche une étape d'étude spéciale
 */
if (!function_exists('pokehub_render_research_step')) {
    function pokehub_render_research_step(array $step, int $step_number, int $total_steps, string $research_name = '', string $header_color = '') {
        $header_style = '';
        if (!empty($header_color)) {
            $header_style = 'background: linear-gradient(135deg, ' . esc_attr($header_color) . ', ' . esc_attr($header_color) . '80); color: #fff;';
        }
    ?>
    <div class="pokehub-special-research-step" data-stage-number="<?php echo esc_attr($step_number); ?>" data-is-path-step="<?php echo !empty($header_color) ? 'true' : 'false'; ?>">
        <div class="pokehub-special-research-step-header" style="<?php echo $header_style; ?>">
            <div class="pokehub-special-research-step-badge">
                <span class="pokehub-special-research-step-badge-text"><?php esc_html_e('STEP', 'poke-hub'); ?></span>
                <span class="pokehub-special-research-step-badge-number"><?php echo esc_html($step_number); ?>/<?php echo esc_html($total_steps); ?></span>
            </div>
            <h4 class="pokehub-special-research-step-title">
                <?php 
                if (!empty($research_name)) {
                    echo esc_html($research_name);
                } else {
                    esc_html_e('Research', 'poke-hub');
                }
                ?>
            </h4>
        </div>
        
        <div class="pokehub-special-research-step-content">
            <div class="pokehub-special-research-step-main">
                <?php if (!empty($step['quests'])) : ?>
                    <div class="pokehub-special-research-quests">
                        <?php foreach ($step['quests'] as $quest) : ?>
                            <div class="pokehub-special-research-quest-item">
                                <div class="pokehub-special-research-quest-task">
                                    <?php echo esc_html($quest['task']); ?>
                                </div>
                                <?php if (!empty($quest['rewards'])) : ?>
                                    <div class="pokehub-special-research-quest-reward">
                                        <?php pokehub_render_research_rewards($quest['rewards']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($step['rewards'])) : ?>
                    <div class="pokehub-special-research-step-rewards pokehub-special-research-step-rewards-below">
                        <div class="pokehub-special-research-step-rewards-label"><?php esc_html_e('REWARDS', 'poke-hub'); ?></div>
                        <div class="pokehub-special-research-step-rewards-list">
                            <?php pokehub_render_research_final_rewards($step['rewards']); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($step['rewards'])) : ?>
                <div class="pokehub-special-research-step-rewards pokehub-special-research-step-rewards-side">
                    <div class="pokehub-special-research-step-rewards-label"><?php esc_html_e('REWARDS', 'poke-hub'); ?></div>
                    <div class="pokehub-special-research-step-rewards-list">
                        <?php pokehub_render_research_final_rewards($step['rewards']); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    }
}

if (empty($processed_research)) {
    return '';
}

// Wrapper avec les attributs du bloc
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-special-research-block-wrapper']);

ob_start();
?>
<div <?php echo $wrapper_attributes; ?>>
    <?php echo function_exists('pokehub_render_block_title')
        ? pokehub_render_block_title($type_label, 'special-research')
        : '<h2 class="pokehub-block-title">' . esc_html($type_label) . '</h2>'; ?>
    
    <div class="pokehub-special-research-list">
        <?php foreach ($processed_research as $research_item) : ?>
            <div class="pokehub-special-research-item">
                <h3 class="pokehub-special-research-name"><?php echo esc_html($research_item['name']); ?></h3>
                
                <div class="pokehub-special-research-steps">
                    <?php 
                    // Calculer le total global d'étapes pour la numérotation séquentielle
                    $total_global_steps = count($research_item['common_initial_steps']);
                    // Pour les chemins, on prend le maximum d'étapes parmi tous les chemins
                    $max_path_steps = 0;
                    if (!empty($research_item['paths'])) {
                        foreach ($research_item['paths'] as $path) {
                            $path_steps_count = count($path['steps']);
                            if ($path_steps_count > $max_path_steps) {
                                $max_path_steps = $path_steps_count;
                            }
                        }
                    }
                    $total_global_steps += $max_path_steps; // On ajoute le max des étapes des chemins
                    $total_global_steps += count($research_item['common_final_steps']);
                    
                    $step_number = 1;
                    
                    // Afficher les étapes communes initiales
                    if (!empty($research_item['common_initial_steps'])) :
                        foreach ($research_item['common_initial_steps'] as $step) :
                            pokehub_render_research_step($step, $step_number, $total_global_steps, $research_item['name']);
                            $step_number++;
                        endforeach;
                    endif;
                    
                    // Afficher la sélection de chemin
                    if (!empty($research_item['paths'])) :
                        $paths_count = count($research_item['paths']);
                    ?>
                        <div class="pokehub-special-research-path-selection" data-paths-count="<?php echo esc_attr($paths_count); ?>">
                            <?php foreach ($research_item['paths'] as $path_index => $path) : 
                                $path_color = !empty($path['color']) ? esc_attr($path['color']) : '#ff6b6b';
                            ?>
                                <div class="pokehub-special-research-path" data-path-index="<?php echo esc_attr($path_index); ?>">
                                    <div class="pokehub-special-research-path-header" style="--path-color: <?php echo $path_color; ?>; <?php echo !empty($path['image_url']) ? '--path-image-url: url(' . esc_url($path['image_url']) . ');' : ''; ?>">
                                        <?php if (!empty($path['image_url'])) : ?>
                                            <img src="<?php echo esc_url($path['image_url']); ?>" alt="<?php echo esc_attr($path['name']); ?>" class="pokehub-special-research-path-image" />
                                        <?php endif; ?>
                                        <?php if (!empty($path['name'])) : ?>
                                            <div class="pokehub-special-research-path-name"><?php echo esc_html($path['name']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($path['steps'])) : ?>
                                        <div class="pokehub-special-research-path-steps">
                                            <?php 
                                            $path_step_number = $step_number; // Commencer à partir du numéro global actuel
                                            foreach ($path['steps'] as $step) : ?>
                                                <?php 
                                                // Numérotation globale séquentielle avec couleur du chemin
                                                pokehub_render_research_step($step, $path_step_number, $total_global_steps, $research_item['name'], $path_color);
                                                $path_step_number++;
                                                ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php
                        // Mettre à jour le numéro d'étape après les chemins
                        if ($max_path_steps > 0) {
                            $step_number += $max_path_steps;
                        }
                    endif;
                    
                    // Afficher les étapes communes finales
                    if (!empty($research_item['common_final_steps'])) :
                        foreach ($research_item['common_final_steps'] as $step) :
                            pokehub_render_research_step($step, $step_number, $total_global_steps, $research_item['name']);
                            $step_number++;
                        endforeach;
                    endif;
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
$output = ob_get_clean();
return $output;

