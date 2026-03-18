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

$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-day-pokemon-hours-block-wrapper']);
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

ob_start();
?>
<div <?php echo $wrapper_attributes; ?>>
    <h2 class="pokehub-block-title"><?php echo esc_html($heading); ?></h2>

    <?php if ($header_time_text !== '') : ?>
        <div class="pokehub-day-pokemon-hours-global-time">
            <small><?php echo esc_html($header_time_text); ?></small>
        </div>
    <?php endif; ?>

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
</div>
<?php
return ob_get_clean();

