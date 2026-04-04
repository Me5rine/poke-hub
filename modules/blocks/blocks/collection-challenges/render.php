<?php
/**
 * Rendu du bloc "Défis de Collection"
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

// Récupérer les défis depuis les meta
$challenges = [];
if ($auto_detect) {
    $challenges = function_exists('pokehub_content_get_collection_challenges') ? pokehub_content_get_collection_challenges('post', (int) $post_id) : [];
    if (!is_array($challenges)) {
        $challenges = [];
    }
}

if (empty($challenges)) {
    return '';
}

// Vérifier que les fonctions sont disponibles
if (!function_exists('pokehub_get_pokemon_data_by_id') || !function_exists('poke_hub_pokemon_get_image_url')) {
    return '';
}

/**
 * Rendu des récompenses d'un défi de collection
 */
if (!function_exists('pokehub_render_collection_challenge_rewards')) {
    function pokehub_render_collection_challenge_rewards(array $rewards) {
        foreach ($rewards as $reward) {
            $type = $reward['type'] ?? '';
            $quantity = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
            
            if ($type === 'stardust') {
                $stardust_icon_url = '';
                $base_url = get_option('poke_hub_assets_bucket_base_url', 'https://pokemon.me5rine-lab.com/');
                $objects_path = get_option('poke_hub_assets_path_objects', '/pokemon-go/objects/');
                $base_url = rtrim($base_url, '/');
                $objects_path = '/' . ltrim($objects_path, '/');
                $stardust_icon_url = $base_url . $objects_path . 'stardust.png';
                ?>
                <div class="pokehub-collection-challenge-reward-item">
                    <?php if ($stardust_icon_url) : ?>
                        <div class="pokehub-collection-challenge-reward-image">
                            <img src="<?php echo esc_url($stardust_icon_url); ?>" alt="<?php esc_attr_e('Stardust', 'poke-hub'); ?>" />
                        </div>
                    <?php endif; ?>
                    <div class="pokehub-collection-challenge-reward-info">
                        <div class="pokehub-collection-challenge-reward-name">
                            <?php echo esc_html(number_format($quantity, 0, ',', ' ')); ?>x <?php esc_html_e('Stardust', 'poke-hub'); ?>
                        </div>
                    </div>
                </div>
                <?php
            } elseif ($type === 'xp') {
                $xp_icon_url = '';
                $base_url = get_option('poke_hub_assets_bucket_base_url', 'https://pokemon.me5rine-lab.com/');
                $objects_path = get_option('poke_hub_assets_path_objects', '/pokemon-go/objects/');
                $base_url = rtrim($base_url, '/');
                $objects_path = '/' . ltrim($objects_path, '/');
                $xp_icon_url = $base_url . $objects_path . 'xp.png';
                ?>
                <div class="pokehub-collection-challenge-reward-item">
                    <?php if ($xp_icon_url) : ?>
                        <div class="pokehub-collection-challenge-reward-image">
                            <img src="<?php echo esc_url($xp_icon_url); ?>" alt="<?php esc_attr_e('XP', 'poke-hub'); ?>" />
                        </div>
                    <?php endif; ?>
                    <div class="pokehub-collection-challenge-reward-info">
                        <div class="pokehub-collection-challenge-reward-name">
                            <?php echo esc_html(number_format($quantity, 0, ',', ' ')); ?>x <?php esc_html_e('XP', 'poke-hub'); ?>
                        </div>
                    </div>
                </div>
                <?php
            } elseif ($type === 'item' && !empty($reward['item_id'])) {
                $item_data = function_exists('pokehub_get_item_data_by_id') ? pokehub_get_item_data_by_id((int) $reward['item_id']) : null;
                $item_name = !empty($reward['item_name']) ? $reward['item_name'] : '';
                $item_image_url = '';
                
                if ($item_data) {
                    if (empty($item_name)) {
                        $item_name = $item_data['name_fr'] ?? $item_data['name_en'] ?? '';
                    }
                    $extra = [];
                    if (!empty($item_data['extra'])) {
                        $decoded = json_decode($item_data['extra'], true);
                        if (is_array($decoded)) {
                            $extra = $decoded;
                        }
                    }
                    $item_image_url = $extra['image_url'] ?? '';
                }
                ?>
                <div class="pokehub-collection-challenge-reward-item">
                    <?php if ($item_image_url) : ?>
                        <div class="pokehub-collection-challenge-reward-image">
                            <img src="<?php echo esc_url($item_image_url); ?>" alt="<?php echo esc_attr($item_name); ?>" />
                        </div>
                    <?php endif; ?>
                    <div class="pokehub-collection-challenge-reward-info">
                        <div class="pokehub-collection-challenge-reward-name">
                            <?php echo esc_html($quantity); ?>x <?php echo esc_html($item_name); ?>
                        </div>
                    </div>
                </div>
                <?php
            } elseif ($type === 'pokemon' && !empty($reward['pokemon_ids']) && is_array($reward['pokemon_ids'])) {
                foreach ($reward['pokemon_ids'] as $pokemon_id) {
                    $pokemon_data = pokehub_get_pokemon_data_by_id((int) $pokemon_id);
                    if ($pokemon_data) {
                        $name_fr = $pokemon_data['name_fr'] ?? '';
                        $name_en = $pokemon_data['name_en'] ?? '';
                        $pokemon_name = !empty($name_fr) ? $name_fr : (!empty($name_en) ? $name_en : '');
                        
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
                        <?php
                    }
                }
            } elseif (in_array($type, ['candy', 'xl_candy', 'mega_energy'], true) && !empty($reward['pokemon_id'])) {
                $pokemon_data = pokehub_get_pokemon_data_by_id((int) $reward['pokemon_id']);
                if ($pokemon_data) {
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
                    <?php
                }
            }
        }
    }
}

// Source d'images par défaut (icônes dans le thème ou plugin)

// Traiter chaque défi
$processed_challenges = [];
foreach ($challenges as $challenge) {
    if (empty($challenge['name'])) {
        continue;
    }
    
    // Vérifier les dates
    $use_global_dates = !empty($challenge['use_global_dates']);
    $start_date = !empty($challenge['start_date']) ? $challenge['start_date'] : '';
    $start_time = !empty($challenge['start_time']) ? $challenge['start_time'] : '';
    $end_date = !empty($challenge['end_date']) ? $challenge['end_date'] : '';
    $end_time = !empty($challenge['end_time']) ? $challenge['end_time'] : '';
    
    // Collecter tous les Pokémon de toutes les catégories
    $all_pokemon = [];
    
    // Catégorie: Capture (par défaut)
    if (!empty($challenge['pokemon_catch']) && is_array($challenge['pokemon_catch'])) {
        foreach ($challenge['pokemon_catch'] as $pokemon_id) {
            $all_pokemon[] = [
                'id' => (int) $pokemon_id,
                'category' => 'catch',
                'icon' => 'catch'
            ];
        }
    }
    
    // Catégorie: Obscur (Shadow)
    if (!empty($challenge['pokemon_shadow']) && is_array($challenge['pokemon_shadow'])) {
        foreach ($challenge['pokemon_shadow'] as $pokemon_id) {
            $all_pokemon[] = [
                'id' => (int) $pokemon_id,
                'category' => 'shadow',
                'icon' => 'shadow'
            ];
        }
    }
    
    // Catégorie: Évolution
    if (!empty($challenge['pokemon_evolution']) && is_array($challenge['pokemon_evolution'])) {
        foreach ($challenge['pokemon_evolution'] as $pokemon_id) {
            $all_pokemon[] = [
                'id' => (int) $pokemon_id,
                'category' => 'evolution',
                'icon' => 'evolution'
            ];
        }
    }
    
    // Catégorie: Éclosion
    if (!empty($challenge['pokemon_hatch']) && is_array($challenge['pokemon_hatch'])) {
        foreach ($challenge['pokemon_hatch'] as $pokemon_id) {
            $all_pokemon[] = [
                'id' => (int) $pokemon_id,
                'category' => 'hatch',
                'icon' => 'hatch'
            ];
        }
    }
    
    // Catégorie: Costume
    if (!empty($challenge['pokemon_costume']) && is_array($challenge['pokemon_costume'])) {
        foreach ($challenge['pokemon_costume'] as $pokemon_id) {
            $all_pokemon[] = [
                'id' => (int) $pokemon_id,
                'category' => 'costume',
                'icon' => 'costume'
            ];
        }
    }
    
    // Catégorie: Échange
    if (!empty($challenge['pokemon_trade']) && is_array($challenge['pokemon_trade'])) {
        foreach ($challenge['pokemon_trade'] as $pokemon_id) {
            $all_pokemon[] = [
                'id' => (int) $pokemon_id,
                'category' => 'trade',
                'icon' => 'trade'
            ];
        }
    }
    
    // Récupérer les données des Pokémon et trier par dex_number
    $pokemon_data_list = [];
    foreach ($all_pokemon as $pokemon_entry) {
        $pokemon_data = pokehub_get_pokemon_data_by_id($pokemon_entry['id']);
        if ($pokemon_data) {
            // Récupérer les noms
            $name_fr = $pokemon_data['name_fr'] ?? '';
            $name_en = $pokemon_data['name_en'] ?? '';
            $name = $pokemon_data['name'] ?? '';
            
            // Priorité : name_fr, puis name_en, puis name
            $display_name = !empty($name_fr) ? $name_fr : (!empty($name_en) ? $name_en : $name);
            
            $pokemon_data_list[] = [
                'id' => $pokemon_entry['id'],
                'dex_number' => isset($pokemon_data['dex_number']) ? (int) $pokemon_data['dex_number'] : 0,
                'name' => $display_name,
                'category' => $pokemon_entry['category'],
                'icon' => $pokemon_entry['icon']
            ];
        }
    }
    
    // Trier par numéro de Pokédex
    usort($pokemon_data_list, function($a, $b) {
        return ($a['dex_number'] ?? 0) <=> ($b['dex_number'] ?? 0);
    });
    
    // Récupérer les images des Pokémon
    foreach ($pokemon_data_list as &$pokemon_item) {
        $pokemon_obj = (object) ['id' => $pokemon_item['id']];
        if (function_exists('pokehub_get_pokemon_data_by_id')) {
            $data = pokehub_get_pokemon_data_by_id($pokemon_item['id']);
            if ($data) {
                $pokemon_obj = (object) $data;
            }
        }
        $pokemon_item['image_url'] = poke_hub_pokemon_get_image_url($pokemon_obj, ['shiny' => false]);
    }
    unset($pokemon_item);
    
    // Traiter les récompenses
    $rewards = !empty($challenge['rewards']) && is_array($challenge['rewards']) ? $challenge['rewards'] : [];
    
    $processed_challenges[] = [
        'name' => sanitize_text_field($challenge['name']),
        'color' => !empty($challenge['color']) ? sanitize_hex_color($challenge['color']) : '#333333',
        'pokemon' => $pokemon_data_list,
        'rewards' => $rewards,
        'use_global_dates' => $use_global_dates,
        'start_date' => $start_date,
        'start_time' => $start_time,
        'end_date' => $end_date,
        'end_time' => $end_time,
    ];
}

if (empty($processed_challenges)) {
    return '';
}

// Wrapper avec les attributs du bloc
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-collection-challenges-block-wrapper']);

ob_start();
?>
<div <?php echo $wrapper_attributes; ?>>
    <h2 class="pokehub-block-title"><?php esc_html_e('Collection Challenges', 'poke-hub'); ?></h2>
    
    <div class="pokehub-collection-challenges-list">
        <?php foreach ($processed_challenges as $challenge) : ?>
            <div class="pokehub-collection-challenge-card">
                <div class="pokehub-collection-challenge-header" style="background-color: <?php echo esc_attr($challenge['color']); ?>;">
                    <h3 class="pokehub-collection-challenge-title"><?php echo esc_html($challenge['name']); ?></h3>
                    
                    <?php if (!$challenge['use_global_dates'] && (!empty($challenge['start_date']) || !empty($challenge['end_date']))) : ?>
                        <div class="pokehub-collection-challenge-dates">
                            <?php if (!empty($challenge['start_date'])) : ?>
                                <span class="pokehub-collection-challenge-date">
                                    <?php 
                                    $start_dt = $challenge['start_date'];
                                    if (!empty($challenge['start_time'])) {
                                        $start_dt .= ' ' . $challenge['start_time'];
                                    }
                                    echo esc_html($start_dt);
                                    ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($challenge['start_date']) && !empty($challenge['end_date'])) : ?>
                                <span class="pokehub-collection-challenge-date-separator">→</span>
                            <?php endif; ?>
                            
                            <?php if (!empty($challenge['end_date'])) : ?>
                                <span class="pokehub-collection-challenge-date">
                                    <?php 
                                    $end_dt = $challenge['end_date'];
                                    if (!empty($challenge['end_time'])) {
                                        $end_dt .= ' ' . $challenge['end_time'];
                                    }
                                    echo esc_html($end_dt);
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="pokehub-collection-challenge-content">
                    <div class="pokehub-collection-challenge-main">
                        <?php if (!empty($challenge['pokemon'])) : ?>
                            <div class="pokehub-collection-challenge-pokemon">
                                <?php foreach ($challenge['pokemon'] as $pokemon) : ?>
                                    <div class="pokehub-collection-challenge-pokemon-item">
                                        <?php if ($pokemon['icon'] !== 'catch') : ?>
                                            <span class="pokehub-collection-challenge-pokemon-icon pokehub-collection-challenge-pokemon-icon--<?php echo esc_attr($pokemon['icon']); ?>" title="<?php echo esc_attr(ucfirst($pokemon['category'])); ?>">
                                                <?php 
                                                // Utiliser le bucket configuré dans les paramètres pour les collection challenges
                                                $icon_name = $pokemon['icon']; // shadow, evolution, hatch, costume, trade
                                                $base_url = get_option('poke_hub_assets_bucket_base_url', 'https://pokemon.me5rine-lab.com/');
                                                $collection_challenges_path = get_option('poke_hub_assets_path_collection_challenges', '/pokemon-go/collection-challenges/');
                                                
                                                // Nettoyer les URLs
                                                $base_url = rtrim($base_url, '/');
                                                $collection_challenges_path = '/' . ltrim($collection_challenges_path, '/');
                                                
                                                $icon_url = $base_url . $collection_challenges_path . $icon_name . '.svg';
                                                ?>
                                                <img src="<?php echo esc_url($icon_url); ?>" alt="<?php echo esc_attr($icon_name); ?>" />
                                            </span>
                                        <?php endif; ?>
                                        
                                        <div class="pokehub-collection-challenge-pokemon-card-inner">
                                            <div class="pokehub-collection-challenge-pokemon-image-wrapper">
                                                <?php if (!empty($pokemon['image_url'])) : ?>
                                                    <img 
                                                        src="<?php echo esc_url($pokemon['image_url']); ?>" 
                                                        alt="<?php echo esc_attr($pokemon['name']); ?>"
                                                        class="pokehub-collection-challenge-pokemon-image"
                                                        loading="lazy"
                                                    />
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="pokehub-collection-challenge-pokemon-name">
                                                <?php echo esc_html($pokemon['name']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($challenge['rewards'])) : ?>
                            <div class="pokehub-collection-challenge-rewards pokehub-collection-challenge-rewards-below">
                                <div class="pokehub-collection-challenge-rewards-label"><?php esc_html_e('REWARDS', 'poke-hub'); ?></div>
                                <div class="pokehub-collection-challenge-rewards-list">
                                    <?php pokehub_render_collection_challenge_rewards($challenge['rewards']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($challenge['rewards'])) : ?>
                        <div class="pokehub-collection-challenge-rewards pokehub-collection-challenge-rewards-side">
                            <div class="pokehub-collection-challenge-rewards-label"><?php esc_html_e('REWARDS', 'poke-hub'); ?></div>
                            <div class="pokehub-collection-challenge-rewards-list">
                                <?php pokehub_render_collection_challenge_rewards($challenge['rewards']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
$output = ob_get_clean();
return $output;

