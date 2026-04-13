<?php
/**
 * Rendu du bloc "Day Pokémon Hours"
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
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

if (!function_exists('pokehub_content_get_day_pokemon_hours_entries') || !function_exists('pokehub_get_pokemon_data_by_id')) {
    return '';
}

$content_type = isset($attributes['contentType']) ? sanitize_key((string) $attributes['contentType']) : 'featured_hours';
if ($content_type === '') {
    $content_type = 'featured_hours';
}

$block_title = isset($attributes['title']) ? trim((string) $attributes['title']) : '';

$entries = [];
if ($content_type === 'featured_hours' && function_exists('pokehub_content_get_featured_hours_classic_events_entries_for_parent')) {
    $entries = pokehub_content_get_featured_hours_classic_events_entries_for_parent($post_id);
}

if (empty($entries)) {
    $entries = pokehub_content_get_day_pokemon_hours_entries('post', $post_id, $content_type);
}
if (empty($entries)) {
    return '';
}

// Helper: format "18:00" => "18 h" (ou "18 h 30")
if (!function_exists('pokehub_format_day_pokemon_hours_time')) {
    function pokehub_format_day_pokemon_hours_time(string $time): string {
        $time = trim($time);
        if ($time === '') return '';
        $parts = explode(':', $time);
        if (count($parts) < 2) {
            return $time;
        }
        $h = (int) $parts[0];
        $m = (int) $parts[1];
        if ($m === 0) {
            return sprintf(__('%d h', 'poke-hub'), $h);
        }
        return sprintf(__('%d h %d', 'poke-hub'), $h, $m);
    }
}

// Vérifier si toutes les entrées partagent la même plage horaire
$start_times = [];
$end_times = [];
$end_dates = [];
foreach ($entries as $e) {
    if (!empty($e['start_time'])) $start_times[(string) $e['start_time']] = true;
    if (!empty($e['end_time'])) $end_times[(string) $e['end_time']] = true;
    $d = trim((string) ($e['end_date'] ?? ''));
    if ($d === '') {
        $d = (string) ($e['date'] ?? '');
    }
    if ($d !== '') $end_dates[$d] = true;
}

$use_global_time = count($start_times) === 1 && count($end_times) === 1 && count($end_dates) === 1;

$global_start = $use_global_time ? array_key_first($start_times) : '';
$global_end   = $use_global_time ? array_key_first($end_times) : '';

$wrapper_classes = 'pokehub-day-pokemon-hours-block-wrapper';
if ($content_type === 'featured_hours') {
    $wrapper_classes .= ' pokehub-day-pokemon-hours--featured pokehub-wild-pokemon-block-wrapper';
}
$wrapper_attributes = get_block_wrapper_attributes(['class' => trim($wrapper_classes)]);
// Titre par défaut basé sur le type choisi (si l'utilisateur ne remplit pas le champ "Block title").
$default_titles = [
    'raids' => __('Raids', 'poke-hub'),
    'eggs' => __('Eggs', 'poke-hub'),
    'incense' => __('Incense', 'poke-hub'),
    'lures' => __('Lures', 'poke-hub'),
    'featured_hours' => __('Featured Hours', 'poke-hub'),
    'quests' => __('Quests', 'poke-hub'),
];
$heading = $block_title !== '' ? $block_title : (($default_titles[$content_type] ?? __('Day Pokémon Hours', 'poke-hub')));

// Bandeau « même horaire partout » : masqué en featured (jour + heure sur chaque tuile).
$suppress_global_time_banner = ($content_type === 'featured_hours' && $use_global_time);

// Calcul: afficher l'heure en header si identique partout
$header_time_text = '';
if ($use_global_time) {
    $fs = pokehub_format_day_pokemon_hours_time((string) $global_start);
    $fe = pokehub_format_day_pokemon_hours_time((string) $global_end);
    if ($fs !== '' && $fe !== '') {
        $header_time_text = sprintf(
            __('From %1$s to %2$s (local time)', 'poke-hub'),
            $fs,
            $fe
        );

        // Si l'heure de fin passe sur un autre jour (end_date != date), on l'affiche dans le header.
        $global_start_date = '';
        foreach ($entries as $e) {
            if (!empty($e['date'])) {
                $global_start_date = (string) $e['date'];
                break;
            }
        }
        $global_end_date = (string) array_key_first($end_dates);
        if ($global_end_date !== '' && $global_start_date !== '' && $global_end_date !== $global_start_date) {
            $global_end_day_label = date_i18n('j F', strtotime($global_end_date));
            $header_time_text .= ' (' . (string) $global_end_day_label . ')';
        }
    }
}

// Quand les horaires varient, on regroupe par plage horaire pour afficher "heures puis jours".
$groups = [];
$group_order = [];
if (!$use_global_time) {
    foreach ($entries as $day_entry) {
        $date_str = (string) ($day_entry['date'] ?? '');
        if ($date_str === '') continue;

        $row_start = (string) ($day_entry['start_time'] ?? '');
        $row_end   = (string) ($day_entry['end_time'] ?? '');
        $row_end_date = trim((string) ($day_entry['end_date'] ?? ''));
        if ($row_end_date === '') {
            $row_end_date = $date_str;
        }

        $key = $row_start . '|' . $row_end . '|' . $row_end_date;
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'start_time' => $row_start,
                'end_time' => $row_end,
                'end_date' => $row_end_date,
                'items' => [],
            ];
            $group_order[] = $key;
        }
        $groups[$key]['items'][] = $day_entry;
    }
}

if (!function_exists('pokehub_day_pokemon_hours_render_spotlight_tile')) {
    /**
     * Tuile « heure vedette » : visuel type Pokémon sauvage + bandeau date / heure.
     *
     * @param array  $pokemon      Ligne pokehub_get_pokemon_data_by_id().
     * @param string $day_display  Libellé date court.
     * @param bool   $show_time    Afficher la ligne horaire sous la date.
     * @param string $time_display Plage déjà formatée (ex. « 18 h – 19 h »).
     */
    function pokehub_day_pokemon_hours_render_spotlight_tile(array $pokemon, string $day_display, bool $show_time, string $time_display): void {
        $pid = (int) ($pokemon['id'] ?? 0);

        $disp = [
            'image_url'                   => '',
            'should_show_shiny'           => false,
            'is_shiny_forced'             => false,
            'should_show_regional_icon'   => false,
        ];
        if ($pid > 0 && function_exists('poke_hub_pokemon_get_display_info')) {
            $disp = array_merge($disp, poke_hub_pokemon_get_display_info($pid, [
                'forced_shiny_ids' => [],
                'gender'           => null,
            ]));
        } elseif ($pid > 0 && function_exists('poke_hub_pokemon_get_image_url')) {
            $disp['image_url'] = (string) poke_hub_pokemon_get_image_url($pokemon, [
                'shiny'  => false,
                'gender' => null,
            ]);
        }

        $image_url = (string) ($disp['image_url'] ?? '');

        $name_fr = (string) ($pokemon['name_fr'] ?? '');
        $name_en = (string) ($pokemon['name_en'] ?? '');
        $name    = (string) ($pokemon['name'] ?? '');
        if ($name === '') {
            $name = $name_fr !== '' ? $name_fr : ($name_en !== '' ? $name_en : '');
        }
        $display = $name_fr !== '' ? $name_fr : ($name_en !== '' ? $name_en : $name);
        if ($display === '') {
            $display = '#' . $pid;
        }

        $type_color = ($pid > 0 && function_exists('pokehub_get_pokemon_type_color'))
            ? (string) pokehub_get_pokemon_type_color($pid)
            : '';
        ?>
        <div class="pokehub-day-pokemon-hours-spotlight-tile">
            <div class="pokehub-wild-pokemon-card pokehub-day-pokemon-hours-spotlight-card"<?php echo $type_color !== '' ? ' style="--pokemon-type-color: ' . esc_attr($type_color) . '"' : ''; ?>>
                <?php if (!empty($disp['should_show_shiny'])) : ?>
                    <span class="pokehub-wild-pokemon-shiny-icon" title="<?php echo !empty($disp['is_shiny_forced']) ? esc_attr__('Shiny forcé', 'poke-hub') : esc_attr__('Shiny disponible', 'poke-hub'); ?>">✨</span>
                <?php endif; ?>
                <?php if (!empty($disp['should_show_regional_icon'])) : ?>
                    <span class="pokehub-wild-pokemon-regional-icon" title="<?php esc_attr_e('Pokémon régional', 'poke-hub'); ?>">🌍</span>
                <?php endif; ?>
                <div class="pokehub-wild-pokemon-card-inner">
                    <?php if ($image_url !== '') : ?>
                        <div class="pokehub-wild-pokemon-image-wrapper">
                            <img
                                src="<?php echo esc_url($image_url); ?>"
                                alt="<?php echo esc_attr($name !== '' ? $name : $display); ?>"
                                class="pokehub-wild-pokemon-image"
                                loading="lazy"
                                decoding="async"
                                onerror="this.style.display='none';"
                            />
                        </div>
                    <?php endif; ?>
                    <div class="pokehub-wild-pokemon-name"><?php echo esc_html($display); ?></div>
                </div>
            </div>
            <div class="pokehub-day-pokemon-hours-spotlight-meta"<?php echo $pid > 0 ? ' data-pokemon-id="' . esc_attr((string) $pid) . '"' : ''; ?>>
                <span class="pokehub-day-pokemon-hours-spotlight-meta-line">
                    <span class="pokehub-day-pokemon-hours-spotlight-icon pokehub-day-pokemon-hours-spotlight-icon--calendar" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    </span>
                    <span class="pokehub-day-pokemon-hours-spotlight-meta-text"><?php echo esc_html($day_display); ?></span>
                </span>
                <?php if ($show_time && $time_display !== '') : ?>
                    <span class="pokehub-day-pokemon-hours-spotlight-meta-line">
                        <span class="pokehub-day-pokemon-hours-spotlight-icon pokehub-day-pokemon-hours-spotlight-icon--clock" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        </span>
                        <span class="pokehub-day-pokemon-hours-spotlight-meta-text"><?php echo esc_html($time_display); ?></span>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

ob_start();
?>
<div <?php echo $wrapper_attributes; ?>>
    <?php echo function_exists('pokehub_render_block_title')
        ? pokehub_render_block_title($heading, 'day-pokemon-hours')
        : '<h2 class="pokehub-block-title">' . esc_html($heading) . '</h2>'; ?>

    <?php if ($header_time_text !== '' && !$suppress_global_time_banner) : ?>
        <div class="pokehub-day-pokemon-hours-global-time">
            <small><?php echo esc_html($header_time_text); ?></small>
        </div>
    <?php endif; ?>

    <?php if ($content_type === 'featured_hours') : ?>
        <div class="pokehub-day-pokemon-hours-featured-root">
            <?php if ($use_global_time) : ?>
                <?php
                $featured_tile_time = '';
                $ft_fs = pokehub_format_day_pokemon_hours_time((string) $global_start);
                $ft_fe = pokehub_format_day_pokemon_hours_time((string) $global_end);
                if ($ft_fs !== '' && $ft_fe !== '') {
                    /* translators: %1$s start time, %2$s end time (localized short form). */
                    $featured_tile_time = sprintf(__('%1$s – %2$s', 'poke-hub'), $ft_fs, $ft_fe);
                }
                ?>
                <div class="pokehub-day-pokemon-hours-featured-track" role="list">
                    <?php
                    foreach ($entries as $day_entry) {
                        $date_str = (string) ($day_entry['date'] ?? '');
                        if ($date_str === '') {
                            continue;
                        }
                        $pokemon_ids = isset($day_entry['pokemon_ids']) && is_array($day_entry['pokemon_ids']) ? $day_entry['pokemon_ids'] : [];
                        if (empty($pokemon_ids)) {
                            continue;
                        }
                        $day_display = date_i18n('D j M', strtotime($date_str));
                        foreach ($pokemon_ids as $pid) {
                            $pid = (int) $pid;
                            if ($pid <= 0) {
                                continue;
                            }
                            $p = pokehub_get_pokemon_data_by_id($pid);
                            if (!$p) {
                                continue;
                            }
                            echo '<div class="pokehub-day-pokemon-hours-featured-track-item" role="listitem">';
                            pokehub_day_pokemon_hours_render_spotlight_tile($p, $day_display, $featured_tile_time !== '', $featured_tile_time);
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            <?php else : ?>
                <?php foreach ($group_order as $gkey) :
                    $group = $groups[$gkey] ?? null;
                    if (!$group || empty($group['items'])) {
                        continue;
                    }

                    $fs = pokehub_format_day_pokemon_hours_time((string) ($group['start_time'] ?? ''));
                    $fe = pokehub_format_day_pokemon_hours_time((string) ($group['end_time'] ?? ''));
                    $slot_time_display = '';
                    if ($fs !== '' && $fe !== '') {
                        /* translators: %1$s start time, %2$s end time (already localized, e.g. "18 h" – "19 h"). */
                        $slot_time_display = sprintf(__('%1$s – %2$s', 'poke-hub'), $fs, $fe);
                    }
                    ?>
                    <div class="pokehub-day-pokemon-hours-featured-group">
                        <div class="pokehub-day-pokemon-hours-featured-track" role="list">
                            <?php
                            foreach ($group['items'] as $day_entry) {
                                $date_str = (string) ($day_entry['date'] ?? '');
                                if ($date_str === '') {
                                    continue;
                                }
                                $pokemon_ids = isset($day_entry['pokemon_ids']) && is_array($day_entry['pokemon_ids']) ? $day_entry['pokemon_ids'] : [];
                                if (empty($pokemon_ids)) {
                                    continue;
                                }
                                $day_display = date_i18n('D j M', strtotime($date_str));
                                foreach ($pokemon_ids as $pid) {
                                    $pid = (int) $pid;
                                    if ($pid <= 0) {
                                        continue;
                                    }
                                    $p = pokehub_get_pokemon_data_by_id($pid);
                                    if (!$p) {
                                        continue;
                                    }
                                    echo '<div class="pokehub-day-pokemon-hours-featured-track-item" role="listitem">';
                                    pokehub_day_pokemon_hours_render_spotlight_tile($p, $day_display, $slot_time_display !== '', $slot_time_display);
                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php else : ?>

    <div class="pokehub-day-pokemon-hours-list">
        <?php if ($use_global_time) : ?>
            <?php foreach ($entries as $day_entry) :
                $date_str = (string) ($day_entry['date'] ?? '');
                if ($date_str === '') continue;

                $pokemon_ids = isset($day_entry['pokemon_ids']) && is_array($day_entry['pokemon_ids']) ? $day_entry['pokemon_ids'] : [];
                if (empty($pokemon_ids)) continue;

                $day_label = date_i18n('j F', strtotime($date_str));
                ?>
                <div class="pokehub-day-pokemon-hours-row">
                    <div class="pokehub-day-pokemon-hours-row-top">
                        <strong><?php echo esc_html($day_label); ?></strong>
                    </div>
                    <div class="pokehub-day-pokemon-hours-row-pokemon">
                        <?php
                        $names = [];
                        foreach ($pokemon_ids as $pid) {
                            $pid = (int) $pid;
                            if ($pid <= 0) continue;
                            $p = pokehub_get_pokemon_data_by_id($pid);
                            if (!$p) continue;
                            $name_fr = (string) ($p['name_fr'] ?? '');
                            $name_en = (string) ($p['name_en'] ?? '');
                            $name    = (string) ($p['name'] ?? '');
                            $display_name = $name_fr !== '' ? $name_fr : ($name_en !== '' ? $name_en : $name);
                            if ($display_name === '') $display_name = '#' . $pid;
                            $names[] = $display_name;
                        }
                        echo esc_html(implode(', ', array_values(array_unique($names))));
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <?php foreach ($group_order as $gkey) :
                $group = $groups[$gkey] ?? null;
                if (!$group || empty($group['items'])) continue;

                $fs = pokehub_format_day_pokemon_hours_time((string) ($group['start_time'] ?? ''));
                $fe = pokehub_format_day_pokemon_hours_time((string) ($group['end_time'] ?? ''));
                $header_text = '';
                if ($fs !== '' && $fe !== '') {
                    $header_text = sprintf(
                        __('From %1$s to %2$s (local time)', 'poke-hub'),
                        $fs,
                        $fe
                    );
                    $first_item = $group['items'][0];
                    $start_day = (string) ($first_item['date'] ?? '');
                    $end_date = (string) ($group['end_date'] ?? '');
                    if ($end_date !== '' && $end_date !== $start_day) {
                        $end_day_label = date_i18n('j F', strtotime($end_date));
                        $header_text .= ' (' . (string) $end_day_label . ')';
                    }
                }
                ?>
                <div class="pokehub-day-pokemon-hours-group">
                    <?php if ($header_text !== '') : ?>
                        <div class="pokehub-day-pokemon-hours-group-time">
                            <small><?php echo esc_html($header_text); ?></small>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($group['items'] as $day_entry) :
                        $date_str = (string) ($day_entry['date'] ?? '');
                        if ($date_str === '') continue;

                        $pokemon_ids = isset($day_entry['pokemon_ids']) && is_array($day_entry['pokemon_ids']) ? $day_entry['pokemon_ids'] : [];
                        if (empty($pokemon_ids)) continue;

                        $day_label = date_i18n('j F', strtotime($date_str));
                        ?>
                        <div class="pokehub-day-pokemon-hours-row">
                            <div class="pokehub-day-pokemon-hours-row-top">
                                <strong><?php echo esc_html($day_label); ?></strong>
                            </div>
                            <div class="pokehub-day-pokemon-hours-row-pokemon">
                                <?php
                                $names = [];
                                foreach ($pokemon_ids as $pid) {
                                    $pid = (int) $pid;
                                    if ($pid <= 0) continue;
                                    $p = pokehub_get_pokemon_data_by_id($pid);
                                    if (!$p) continue;
                                    $name_fr = (string) ($p['name_fr'] ?? '');
                                    $name_en = (string) ($p['name_en'] ?? '');
                                    $name    = (string) ($p['name'] ?? '');
                                    $display_name = $name_fr !== '' ? $name_fr : ($name_en !== '' ? $name_en : $name);
                                    if ($display_name === '') $display_name = '#' . $pid;
                                    $names[] = $display_name;
                                }
                                echo esc_html(implode(', ', array_values(array_unique($names))));
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php
return ob_get_clean();

