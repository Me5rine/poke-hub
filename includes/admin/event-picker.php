<?php
// includes/admin/event-picker.php – Sélecteur d'événements réutilisable (fonds, variants, costumes)

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retourne tous les événements pour un picker (sources : local_event, remote_event, special_event).
 * Réutilisable pour les fonds, variants de Pokémon, costumes, etc.
 *
 * @return array<object> Liste d'objets avec au minimum id, title, slug, source (event_type)
 */
function poke_hub_get_events_for_picker(): array {
    if (!function_exists('poke_hub_events_get_all_sources_by_status')) {
        return [];
    }
    $events = poke_hub_events_get_all_sources_by_status('all', []);
    return is_array($events) ? $events : [];
}

/**
 * Libellé court pour un type d'événement (source).
 *
 * @param string $source local_event | remote_event | special_event (anciennes valeurs acceptées)
 * @return string
 */
function poke_hub_get_event_source_label(string $source): string {
    if (function_exists('poke_hub_events_normalize_event_source')) {
        $source = poke_hub_events_normalize_event_source($source);
    }
    $labels = [
        'local_event'    => __('Local event', 'poke-hub'),
        'remote_event'   => __('Remote event', 'poke-hub'),
        'special_event'  => __('Special event', 'poke-hub'),
    ];
    return $labels[$source] ?? ucfirst(str_replace('_', ' ', $source));
}

/**
 * Affiche une ligne du sélecteur d'événements : un champ caché (event_type) + un select (event_id) avec tous les événements.
 * Un seul select par ligne : recherche par nom, tous types confondus. Le type est enregistré automatiquement via le champ caché.
 *
 * @param int|string $index         Index de la ligne (ou '__INDEX__' pour le template)
 * @param int        $event_id      ID de l'événement sélectionné (0 = aucun)
 * @param string     $event_type    Type de l'événement (source)
 * @param array      $all_events    Liste retournée par poke_hub_get_events_for_picker()
 * @param string     $name_prefix     Préfixe des names (ex: 'event_links')
 * @param string     $row_class       Classe CSS de la ligne (pour le conteneur)
 * @param string     $remove_label    Texte du bouton "Remove"
 * @param string     $remove_btn_class Classe du bouton Remove (pour compatibilité JS existant)
 */
function poke_hub_render_event_picker_row($index, $event_id, $event_type, array $all_events, $name_prefix = 'event_links', $row_class = 'pokehub-event-picker-row', $remove_label = null, $remove_btn_class = 'pokehub-event-picker-remove') {
    if ($remove_label === null) {
        $remove_label = __('Remove', 'poke-hub');
    }
    $event_id = (int) $event_id;
    $event_type = (string) $event_type;
    ?>
    <div class="pokehub-event-picker-row <?php echo esc_attr($row_class); ?>" style="display:flex;align-items:flex-end;gap:10px;margin-bottom:10px;">
        <input type="hidden" class="pokehub-event-picker-type" name="<?php echo esc_attr($name_prefix . '[' . $index . '][event_type]'); ?>" value="<?php echo esc_attr($event_type); ?>" />
        <div class="admin-lab-form-group" style="flex:1;min-width:0;min-width:200px;">
            <label><?php esc_html_e('Event', 'poke-hub'); ?></label>
            <select class="pokehub-event-picker-select" name="<?php echo esc_attr($name_prefix . '[' . $index . '][event_id]'); ?>" style="width:100%;">
                <option value="0"><?php esc_html_e('None', 'poke-hub'); ?></option>
                <?php if (!empty($all_events)) : ?>
                    <?php foreach ($all_events as $event) : ?>
                        <?php
                        $e_id = isset($event->id) ? (int) $event->id : 0;
                        $e_source = isset($event->source) ? (string) $event->source : '';
                        $e_title = isset($event->title) ? (string) $event->title : '';
                        $e_slug = isset($event->slug) ? (string) $event->slug : '';
                        $e_label = $e_title ?: $e_slug;
                        $e_label .= ' (' . esc_html(poke_hub_get_event_source_label($e_source)) . ')';
                        ?>
                        <option value="<?php echo $e_id; ?>" data-source="<?php echo esc_attr($e_source); ?>" <?php selected($event_id, $e_id); ?>><?php echo esc_html($e_label); ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <button type="button" class="button <?php echo esc_attr($remove_btn_class); ?>"><?php echo esc_html($remove_label); ?></button>
    </div>
    <?php
}
