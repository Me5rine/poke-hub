<?php
/**
 * Rendu du bloc "Habitats"
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

// Récupérer les habitats depuis les post meta
$habitats = [];
if ($auto_detect) {
    $habitats_meta = function_exists('pokehub_content_get_habitats') ? pokehub_content_get_habitats('post', (int) $post_id) : [];
    if (is_array($habitats_meta) && !empty($habitats_meta)) {
        $habitats = $habitats_meta;
    }
}

// Fallback : récupérer depuis l'événement spécial si disponible
if (empty($habitats) && $auto_detect && function_exists('poke_hub_special_event_get_habitats')) {
    $habitats = poke_hub_special_event_get_habitats($post_id);
}

if (empty($habitats)) {
    return '';
}

// Fonction helper pour formater l'horaire d'un habitat
if (!function_exists('pokehub_format_habitat_schedule')) {
    function pokehub_format_habitat_schedule($schedule) {
        if (empty($schedule) || !is_array($schedule)) {
            return '';
        }
        
        $parts = [];
        foreach ($schedule as $day) {
            if (empty($day['date'])) {
                continue;
            }
            
            $date_str = $day['date'];
            $start_time = $day['start_time'] ?? '';
            $end_time = $day['end_time'] ?? '';
            $all_habitats = !empty($day['all_habitats']);
            
            // Compatibilité avec l'ancienne structure (time_slots)
            if (empty($start_time) && empty($end_time) && !empty($day['time_slots']) && is_array($day['time_slots']) && !empty($day['time_slots'])) {
                $first_slot = reset($day['time_slots']);
                $start_time = $first_slot['start'] ?? '';
                $end_time = $first_slot['end'] ?? '';
            }
            
            $day_label = date_i18n('l j F', strtotime($date_str));
            $time_str = '';
            
            if (!empty($start_time) && !empty($end_time)) {
                $time_str = $start_time . ' – ' . $end_time;
            } elseif (!empty($start_time)) {
                $time_str = __('From', 'poke-hub') . ' ' . $start_time;
            } elseif (!empty($end_time)) {
                $time_str = __('Until', 'poke-hub') . ' ' . $end_time;
            }
            
            if (!empty($time_str)) {
                $parts[] = $day_label . ' : ' . $time_str;
            } else {
                $parts[] = $day_label;
            }
            
            if ($all_habitats) {
                $parts[] = __('All Pokémon from all habitats are available during this time slot.', 'poke-hub');
            }
        }
        
        return implode(' | ', $parts);
    }
}

// Fonction helper pour récupérer l'URL de l'icône d'un habitat
if (!function_exists('pokehub_get_habitat_icon_url')) {
    function pokehub_get_habitat_icon_url($slug) {
        if (empty($slug)) {
            return '';
        }
        
        // Utiliser le helper centralisé des assets
        if (function_exists('poke_hub_get_habitat_icon_url')) {
            return poke_hub_get_habitat_icon_url($slug);
        }
        
        // Fallback : construction manuelle
        $base_url = get_option('poke_hub_assets_bucket_base_url', 'https://pokemon.me5rine-lab.com/');
        $habitats_path = get_option('poke_hub_assets_path_habitats', '/pokemon-go/habitats/');
        $base_url = rtrim($base_url, '/');
        $habitats_path = '/' . ltrim($habitats_path, '/');
        return $base_url . $habitats_path . sanitize_file_name($slug) . '.png';
    }
}

// Wrapper avec les attributs du bloc
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-habitats-block-wrapper']);

ob_start();
?>
<div <?php echo $wrapper_attributes; ?>>
    <?php echo function_exists('pokehub_render_block_title')
        ? pokehub_render_block_title(__('Habitats', 'poke-hub'), 'habitats')
        : '<h2 class="pokehub-block-title">' . esc_html__('Habitats', 'poke-hub') . '</h2>'; ?>
    
    <?php foreach ($habitats as $habitat) : 
        $habitat_name = $habitat['name'] ?? '';
        $habitat_slug = $habitat['slug'] ?? '';
        $pokemon_ids = $habitat['pokemon_ids'] ?? [];
        $forced_shiny_ids = $habitat['forced_shiny_ids'] ?? [];
        $pokemon_genders = $habitat['pokemon_genders'] ?? [];
        $schedule = $habitat['schedule'] ?? [];
        
        if (empty($habitat_name) || empty($habitat_slug)) {
            continue;
        }
        
        $habitat_icon_html = (function_exists('poke_hub_render_bucket_raster_img'))
            ? poke_hub_render_bucket_raster_img(
                'habitats',
                $habitat_slug,
                [
                    'alt'   => $habitat_name,
                    'class' => 'pokehub-habitat-icon',
                ]
            )
            : '';
        $schedule_text = pokehub_format_habitat_schedule($schedule);
    ?>
        <div class="pokehub-habitat-item">
            <div class="pokehub-habitat-header">
                <?php if ($habitat_icon_html !== '') : ?>
                    <?php echo $habitat_icon_html; ?>
                <?php endif; ?>
                <h3 class="pokehub-habitat-title"><?php echo esc_html($habitat_name); ?></h3>
            </div>
            
            <?php 
            // Vérifier s'il y a un horaire "tous les habitats"
            $has_all_habitats = false;
            $all_habitats_schedule = [];
            if (!empty($schedule)) {
                foreach ($schedule as $day) {
                    if (!empty($day['all_habitats'])) {
                        $has_all_habitats = true;
                        $all_habitats_schedule[] = $day;
                    }
                }
            }
            
            // Afficher l'encadré avec date+heure si présent
            if (!empty($schedule) && !$has_all_habitats) : ?>
                <div class="pokehub-habitat-schedule-box">
                    <?php echo esc_html($schedule_text); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($has_all_habitats && !empty($all_habitats_schedule)) : ?>
                <div class="pokehub-habitat-all-pokemon-box">
                    <div class="pokehub-habitat-all-pokemon-info">
                        <?php 
                        $all_parts = [];
                        foreach ($all_habitats_schedule as $day) {
                            if (empty($day['date'])) continue;
                            
                            $date_str = $day['date'];
                            $start_time = $day['start_time'] ?? '';
                            $end_time = $day['end_time'] ?? '';
                            
                            if (empty($start_time) && empty($end_time) && !empty($day['time_slots']) && is_array($day['time_slots']) && !empty($day['time_slots'])) {
                                $first_slot = reset($day['time_slots']);
                                $start_time = $first_slot['start'] ?? '';
                                $end_time = $first_slot['end'] ?? '';
                            }
                            
                            $day_label = date_i18n('l j F', strtotime($date_str));
                            $time_str = '';
                            
                            if (!empty($start_time) && !empty($end_time)) {
                                $time_str = $start_time . ' – ' . $end_time;
                            } elseif (!empty($start_time)) {
                                $time_str = __('From', 'poke-hub') . ' ' . $start_time;
                            } elseif (!empty($end_time)) {
                                $time_str = __('Until', 'poke-hub') . ' ' . $end_time;
                            }
                            
                            if (!empty($time_str)) {
                                $all_parts[] = $day_label . ' : ' . $time_str;
                            } else {
                                $all_parts[] = $day_label;
                            }
                        }
                        echo esc_html(implode(' | ', $all_parts));
                        ?>
                    </div>
                    <div class="pokehub-habitat-all-pokemon-info">
                        <?php esc_html_e('All Pokémon from all habitats are available during this time slot.', 'poke-hub'); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($pokemon_ids) || !empty($forced_shiny_ids)) : 
                // Vérifier que les fonctions nécessaires sont disponibles
                if (!function_exists('pokehub_get_pokemon_data_by_id') || !function_exists('poke_hub_pokemon_get_shiny_info')) {
                    return '';
                }
                
                // Récupérer les données des Pokémon
                $pokemon_list = [];
                $combined_ids = array_unique(array_merge($pokemon_ids, $forced_shiny_ids));
                
                foreach ($combined_ids as $pokemon_id) {
                    $pokemon_id = (int) $pokemon_id;
                    if ($pokemon_id <= 0) {
                        continue;
                    }
                    
                    $pokemon_data = pokehub_get_pokemon_data_by_id($pokemon_id);
                    if (!$pokemon_data) {
                        continue;
                    }
                    
                    // Récupérer le genre pour ce pokémon dans cet habitat
                    $gender = $pokemon_genders[$pokemon_id] ?? null;
                    
                    // Utiliser la fonction helper pour récupérer les infos shiny
                    $shiny_info = poke_hub_pokemon_get_shiny_info($pokemon_id, [
                        'forced_shiny_ids' => $forced_shiny_ids,
                        'gender' => $gender,
                    ]);
                    
                    // Récupérer le nom avec fallback approprié
                    $name_fr = $pokemon_data['name_fr'] ?? '';
                    $name_en = $pokemon_data['name_en'] ?? '';
                    $name = $pokemon_data['name'] ?? '';
                    
                    // Construire le nom d'affichage avec fallback
                    $display_name = '';
                    if (!empty($name_fr)) {
                        $display_name = $name_fr;
                    } elseif (!empty($name_en)) {
                        $display_name = $name_en;
                    } elseif (!empty($name)) {
                        $display_name = $name;
                    }
                    
                    $pokemon_list[] = [
                        'id' => $pokemon_id,
                        'name' => $name,
                        'name_fr' => $name_fr,
                        'name_en' => $name_en,
                        'display_name' => $display_name,
                        'image_url' => $shiny_info['image_url'],
                        'is_shiny_available' => $shiny_info['is_shiny_available'],
                        'is_shiny_forced' => $shiny_info['is_shiny_forced'],
                        'should_show_shiny' => $shiny_info['should_show_shiny'],
                        'dex_number' => isset($pokemon_data['dex_number']) ? (int) $pokemon_data['dex_number'] : 0,
                    ];
                }
                
                // Trier par numéro de Pokédex
                usort($pokemon_list, function($a, $b) {
                    return ($a['dex_number'] ?? 0) <=> ($b['dex_number'] ?? 0);
                });
            ?>
                <div class="pokehub-wild-pokemon-grid">
                    <?php foreach ($pokemon_list as $pokemon) : ?>
                        <div class="pokehub-wild-pokemon-card">
                            <?php if (!empty($pokemon['should_show_shiny'])) : ?>
                                <span class="pokehub-wild-pokemon-shiny-icon" title="<?php echo !empty($pokemon['is_shiny_forced']) ? esc_attr__('Shiny forcé', 'poke-hub') : esc_attr__('Shiny disponible', 'poke-hub'); ?>">✨</span>
                            <?php endif; ?>
                            <div class="pokehub-wild-pokemon-card-inner">
                                <?php if (!empty($pokemon['image_url'])) : ?>
                                    <div class="pokehub-wild-pokemon-image-wrapper">
                                        <img src="<?php echo esc_url($pokemon['image_url']); ?>" 
                                             alt="<?php echo esc_attr($pokemon['name']); ?>"
                                             class="pokehub-wild-pokemon-image"
                                             loading="lazy"
                                             onerror="this.style.display='none';">
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
        </div>
    <?php endforeach; ?>
</div>
<?php
return ob_get_clean();

