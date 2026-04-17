<?php
/**
 * Rendu du bloc "Pokémon Sauvages"
 *
 * @var array    $attributes Les attributs du bloc.
 * @var string   $content    Le contenu HTML du bloc.
 * @var WP_Block $block      L'instance du bloc.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Récupération robuste du post_id (compatible Elementor et autres contextes)
$post_id = 0;

// 1) Contexte Gutenberg (fiable même hors loop)
if (isset($block) && is_object($block) && !empty($block->context['postId'])) {
    $post_id = (int) $block->context['postId'];
}

// 2) Fallback loop
if (!$post_id) {
    $post_id = (int) get_the_ID();
}

// 3) Fallback requête courante
if (!$post_id) {
    $post_id = (int) get_queried_object_id();
}

// 4) Fallback global
if (!$post_id && !empty($GLOBALS['post']->ID)) {
    $post_id = (int) $GLOBALS['post']->ID;
}

if (!$post_id) {
    return '';
}

$auto_detect = $attributes['autoDetect'] ?? true;
$pokemon_ids = $attributes['pokemonIds'] ?? [];
$rare_pokemon_ids = $attributes['rarePokemonIds'] ?? [];
$forced_shiny_ids = $attributes['forcedShinyIds'] ?? [];
$show_rare_section = $attributes['showRareSection'] ?? true;

// Vérifier que les fonctions sont disponibles
if (!function_exists('pokehub_get_pokemon_data_by_id') || !function_exists('poke_hub_pokemon_get_shiny_info') || !function_exists('poke_hub_pokemon_get_regional_info') || !function_exists('pokehub_get_pokemon_type_color')) {
    return '';
}

// Auto-détection : privilégier les tables de contenu (indépendant du module events)
$wild_list = [];
if ($auto_detect) {
    if (function_exists('pokehub_content_get_wild_pokemon')) {
        $wild_list = pokehub_content_get_wild_pokemon('post', (int) $post_id);
    } elseif (function_exists('pokehub_get_wild_pokemon')) {
        // Fallback compat (si le module events est actif)
        $wild_list = pokehub_get_wild_pokemon((int) $post_id);
    }
}

if ($auto_detect && !empty($wild_list) && is_array($wild_list)) {
    $pokemon_ids = [];
    $rare_pokemon_ids = [];
    $forced_shiny_ids = [];
    $pokemon_genders = [];
    foreach ($wild_list as $w) {
        $pid = (int) $w['pokemon_id'];
        if ($pid <= 0) {
            continue;
        }
        $pokemon_ids[] = $pid;
        if (!empty($w['is_rare'])) {
            $rare_pokemon_ids[] = $pid;
        }
        if (!empty($w['force_shiny'])) {
            $forced_shiny_ids[] = $pid;
        }
        if (!empty($w['gender']) && in_array($w['gender'], ['male', 'female'], true)) {
            $pokemon_genders[$pid] = $w['gender'];
        }
    }
    $pokemon_ids = array_values(array_unique($pokemon_ids));
    $rare_pokemon_ids = array_values(array_unique($rare_pokemon_ids));
    $forced_shiny_ids = array_values(array_unique($forced_shiny_ids));
}

// Si toujours vide, essayer depuis l'événement spécial (fallback)
if (empty($pokemon_ids) && empty($rare_pokemon_ids) && empty($forced_shiny_ids) && $auto_detect && function_exists('poke_hub_special_event_get_pokemon')) {
    $event_pokemon_ids = poke_hub_special_event_get_pokemon($post_id);
    if (!empty($event_pokemon_ids)) {
        $pokemon_ids = $event_pokemon_ids;
    }
}

// Vérifier qu'on a au moins des Pokémon à afficher
if (empty($pokemon_ids) && empty($rare_pokemon_ids) && empty($forced_shiny_ids)) {
    return '';
}

// Récupérer les données des Pokémon
$pokemon_list = [];
$rare_pokemon_list = [];

// Fonction helper pour traiter un Pokémon (utilise les fonctions globales)
if (!function_exists('pokehub_process_wild_pokemon_item')) {
    function pokehub_process_wild_pokemon_item($pokemon_id, $forced_shiny_ids, $gender = null) {
        $pokemon_id = (int) $pokemon_id;
        if ($pokemon_id <= 0) {
            return null;
        }

        $pokemon_data = pokehub_get_pokemon_data_by_id($pokemon_id);
        if (!$pokemon_data) {
            return null;
        }

        // Utiliser les fonctions globales pour récupérer les infos shiny et régional
        $shiny_info = poke_hub_pokemon_get_shiny_info($pokemon_id, [
            'forced_shiny_ids' => $forced_shiny_ids,
            'gender' => $gender,
        ]);
        
        $regional_info = poke_hub_pokemon_get_regional_info($pokemon_id);
        
        // Récupérer le nom avec fallback approprié
        $name_fr = $pokemon_data['name_fr'] ?? '';
        $name_en = $pokemon_data['name_en'] ?? '';
        $name = $pokemon_data['name'] ?? '';
        
        // Si name est vide, construire depuis name_fr et name_en
        if (empty($name)) {
            if (!empty($name_fr)) {
                $name = $name_fr;
            } elseif (!empty($name_en)) {
                $name = $name_en;
            }
        }
        
        // Fallback final : utiliser name_fr ou name_en
        $display_name = !empty($name_fr) ? $name_fr : (!empty($name_en) ? $name_en : $name);
        
        return [
            'id' => $pokemon_id,
            'dex_number' => isset($pokemon_data['dex_number']) ? (int) $pokemon_data['dex_number'] : 0,
            'name' => $name,
            'name_fr' => $name_fr,
            'name_en' => $name_en,
            'display_name' => $display_name,
            'image_url' => $shiny_info['image_url'],
            'is_shiny_available' => $shiny_info['is_shiny_available'],
            'is_shiny_forced' => $shiny_info['is_shiny_forced'],
            'should_show_shiny' => $shiny_info['should_show_shiny'],
            'is_regional' => $regional_info['is_regional'],
            'should_show_regional_icon' => $regional_info['should_show_icon'],
            'regions' => $regional_info['regions'],
            'type_color' => pokehub_get_pokemon_type_color($pokemon_id),
        ];
    }
}

// Genres : déjà récupérés depuis les tables de contenu si disponibles
$pokemon_genders = isset($pokemon_genders) && is_array($pokemon_genders) ? $pokemon_genders : [];

// Traiter les Pokémon sauvages classiques et shiny forcés ensemble
// Les shiny forcés doivent apparaître avec les sauvages classiques
$combined_normal_and_shiny_ids = array_unique(array_merge($pokemon_ids, $forced_shiny_ids));
foreach ($combined_normal_and_shiny_ids as $pokemon_id) {
    $gender = $pokemon_genders[$pokemon_id] ?? null;
    $item = pokehub_process_wild_pokemon_item($pokemon_id, $forced_shiny_ids, $gender);
    if ($item) {
        $pokemon_list[] = $item;
    }
}

// Trier par numéro de Pokédex
usort($pokemon_list, function($a, $b) {
    return ($a['dex_number'] ?? 0) <=> ($b['dex_number'] ?? 0);
});

// Traiter les Pokémon rares
foreach ($rare_pokemon_ids as $pokemon_id) {
    $gender = $pokemon_genders[$pokemon_id] ?? null;
    $item = pokehub_process_wild_pokemon_item($pokemon_id, $forced_shiny_ids, $gender);
    if ($item) {
        $rare_pokemon_list[] = $item;
    }
}

// Trier les rares par numéro de Pokédex
usort($rare_pokemon_list, function($a, $b) {
    return ($a['dex_number'] ?? 0) <=> ($b['dex_number'] ?? 0);
});

if (empty($pokemon_list) && empty($rare_pokemon_list)) {
    return '';
}

// Wrapper avec les attributs du bloc
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-wild-pokemon-block-wrapper']);

ob_start();
?>
<div <?php echo $wrapper_attributes; ?>>
    <?php echo function_exists('pokehub_render_block_title')
        ? pokehub_render_block_title(__('Pokémon in the wild', 'poke-hub'), 'wild-pokemon')
        : '<h2 class="pokehub-block-title">' . esc_html__('Pokémon in the wild', 'poke-hub') . '</h2>'; ?>
    <?php if (!empty($pokemon_list)) : ?>
        <div class="pokehub-wild-pokemon-grid">
            <?php foreach ($pokemon_list as $pokemon) : ?>
                <div class="pokehub-wild-pokemon-card" 
                     <?php if (!empty($pokemon['type_color'])) : ?>
                     style="--pokemon-type-color: <?php echo esc_attr($pokemon['type_color']); ?>" 
                     <?php endif; ?>>
                    <?php if (!empty($pokemon['should_show_shiny'])) : ?>
                        <span class="pokehub-wild-pokemon-shiny-icon" title="<?php echo !empty($pokemon['is_shiny_forced']) ? esc_attr__('Forced shiny', 'poke-hub') : esc_attr__('Shiny available', 'poke-hub'); ?>">✨</span>
                    <?php endif; ?>
                    
                    <?php if (!empty($pokemon['should_show_regional_icon'])) : ?>
                        <span class="pokehub-wild-pokemon-regional-icon" title="<?php esc_attr_e('Regional Pokémon', 'poke-hub'); ?>">🌍</span>
                    <?php endif; ?>
                    
                    <div class="pokehub-wild-pokemon-card-inner">
                        <?php if (!empty($pokemon['image_url'])) : ?>
                            <div class="pokehub-wild-pokemon-image-wrapper">
                                <img 
                                    src="<?php echo esc_url($pokemon['image_url']); ?>" 
                                    alt="<?php echo esc_attr($pokemon['name']); ?>"
                                    class="pokehub-wild-pokemon-image"
                                    loading="lazy"
                                    onerror="this.style.display='none';"
                                />
                            </div>
                        <?php endif; ?>
                        
                        <div class="pokehub-wild-pokemon-name">
                            <?php echo esc_html($pokemon['display_name'] ?? $pokemon['name_fr'] ?? $pokemon['name_en'] ?? $pokemon['name'] ?? ''); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($show_rare_section && !empty($rare_pokemon_list)) : ?>
        <div class="pokehub-wild-pokemon-rare-section">
            <h3 class="pokehub-wild-pokemon-rare-title">
                <?php esc_html_e('Pokémon appearing more rarely', 'poke-hub'); ?>
            </h3>
            <div class="pokehub-wild-pokemon-grid pokehub-wild-pokemon-grid--rare">
                <?php foreach ($rare_pokemon_list as $pokemon) : ?>
                    <div class="pokehub-wild-pokemon-card pokehub-wild-pokemon-card--rare"
                         <?php if (!empty($pokemon['type_color'])) : ?>
                         style="--pokemon-type-color: <?php echo esc_attr($pokemon['type_color']); ?>"
                         <?php endif; ?>>
                        <?php if (!empty($pokemon['should_show_shiny'])) : ?>
                            <span class="pokehub-wild-pokemon-shiny-icon" title="<?php echo !empty($pokemon['is_shiny_forced']) ? esc_attr__('Forced shiny', 'poke-hub') : esc_attr__('Shiny available', 'poke-hub'); ?>">✨</span>
                        <?php endif; ?>
                        
                        <?php if (!empty($pokemon['should_show_regional_icon'])) : ?>
                            <span class="pokehub-wild-pokemon-regional-icon" title="<?php esc_attr_e('Regional Pokémon', 'poke-hub'); ?>">🌍</span>
                        <?php endif; ?>
                        
                        <div class="pokehub-wild-pokemon-card-inner">
                            <?php if (!empty($pokemon['image_url'])) : ?>
                                <div class="pokehub-wild-pokemon-image-wrapper">
                                    <img 
                                        src="<?php echo esc_url($pokemon['image_url']); ?>" 
                                        alt="<?php echo esc_attr($pokemon['name']); ?>"
                                        class="pokehub-wild-pokemon-image"
                                        loading="lazy"
                                        onerror="this.style.display='none';"
                                    />
                                </div>
                            <?php endif; ?>
                            
                            <div class="pokehub-wild-pokemon-name">
                                <?php echo esc_html($pokemon['display_name'] ?? $pokemon['name_fr'] ?? $pokemon['name_en'] ?? $pokemon['name'] ?? ''); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
$output = ob_get_clean();
return $output;

