<?php
/**
 * Formulaire admin dédié : événement spécial type Pass GO.
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/events-admin-special-event-save-helpers.php';
require_once __DIR__ . '/events-admin-go-pass-reward-fieldset.php';

/**
 * @param string               $mode      'add' ou 'edit'
 * @param object|null          $event     Objet champs communs (dates, titres…)
 * @param array<string,mixed>|null $go_payload Données Pass (null = défauts)
 */
function pokehub_render_go_pass_event_form(string $mode = 'add', ?object $event = null, ?array $go_payload = null): void {
    $is_edit = ($mode === 'edit' && $event && !empty($event->id));
    $has     = ($event && is_object($event));

    $page_title = $is_edit
        ? __('Edit GO Pass', 'poke-hub')
        : __('Add GO Pass', 'poke-hub');

    $title_en = $has ? ($event->title_en ?? $event->title ?? '') : '';
    $title_fr = $has ? ($event->title_fr ?? $event->title ?? '') : '';
    $slug     = $is_edit ? ($event->slug ?? '') : '';
    $desc     = $has ? ($event->description ?? '') : '';

    $event_mode = $has && !empty($event->mode) ? (string) $event->mode : 'local';

    if ($has && !empty($event->start_ts) && function_exists('poke_hub_special_event_format_date_for_input')) {
        $start_date_value = poke_hub_special_event_format_date_for_input((int) $event->start_ts, $event_mode);
        $start_time_value = poke_hub_special_event_format_time_for_input((int) $event->start_ts, $event_mode);
    } else {
        $start_date_value = '';
        $start_time_value = '';
    }
    if ($has && !empty($event->end_ts) && function_exists('poke_hub_special_event_format_date_for_input')) {
        $end_date_value = poke_hub_special_event_format_date_for_input((int) $event->end_ts, $event_mode);
        $end_time_value = poke_hub_special_event_format_time_for_input((int) $event->end_ts, $event_mode);
    } else {
        $end_date_value = '';
        $end_time_value = '';
    }

    $image_id  = $has && !empty($event->image_id) ? (int) $event->image_id : 0;
    $image_url = $has && !empty($event->image_url) ? (string) $event->image_url : '';

    $current_image_url = '';
    if ($image_id) {
        $img = wp_get_attachment_image_src($image_id, 'medium');
        if (!empty($img[0])) {
            $current_image_url = esc_url($img[0]);
        }
    } elseif ($image_url) {
        $current_image_url = esc_url($image_url);
    }

    $p = $go_payload !== null ? pokehub_go_pass_normalize_payload($go_payload) : pokehub_go_pass_default_payload();

    $claim_d = !empty($p['rewards_claim_end_ts']) && function_exists('poke_hub_special_event_format_date_for_input')
        ? poke_hub_special_event_format_date_for_input((int) $p['rewards_claim_end_ts'], $event_mode)
        : '';
    $claim_t = !empty($p['rewards_claim_end_ts']) && function_exists('poke_hub_special_event_format_time_for_input')
        ? poke_hub_special_event_format_time_for_input((int) $p['rewards_claim_end_ts'], $event_mode)
        : '';
    $ul_sd = !empty($p['unlimited_start_ts']) && function_exists('poke_hub_special_event_format_date_for_input')
        ? poke_hub_special_event_format_date_for_input((int) $p['unlimited_start_ts'], $event_mode)
        : '';
    $ul_st = !empty($p['unlimited_start_ts']) && function_exists('poke_hub_special_event_format_time_for_input')
        ? poke_hub_special_event_format_time_for_input((int) $p['unlimited_start_ts'], $event_mode)
        : '';
    $ul_ed = !empty($p['unlimited_end_ts']) && function_exists('poke_hub_special_event_format_date_for_input')
        ? poke_hub_special_event_format_date_for_input((int) $p['unlimited_end_ts'], $event_mode)
        : '';
    $ul_et = !empty($p['unlimited_end_ts']) && function_exists('poke_hub_special_event_format_time_for_input')
        ? poke_hub_special_event_format_time_for_input((int) $p['unlimited_end_ts'], $event_mode)
        : '';

    $weekly      = !empty($p['weekly_tasks']) ? $p['weekly_tasks'] : [['label' => '', 'points' => '']];
    $daily_core  = isset($p['daily_core_points']) && is_array($p['daily_core_points']) ? $p['daily_core_points'] : [];
    $daily_pts_cap = (int) ($p['daily_points_cap'] ?? 0);
    $daily_tasks = !empty($p['daily_tasks']) ? $p['daily_tasks'] : [['label' => '', 'points' => '']];
    $editor_rows = pokehub_go_pass_payload_to_editor_rows($p);

    ?>
    <div class="wrap">
        <?php poke_hub_admin_back_to_list_bar(pokehub_events_admin_list_url()); ?>
        <h1><?php echo esc_html($page_title); ?></h1>
        <p class="description"><?php esc_html_e('Event type is fixed to the remote GO Pass slug. Fill in ranks: one row per rank (or bonus milestone).', 'poke-hub'); ?></p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="pokehub-go-pass-form">
            <?php wp_nonce_field('pokehub_save_go_pass_event', 'pokehub_go_pass_event_nonce'); ?>
            <input type="hidden" name="action" value="pokehub_save_go_pass_event">
            <?php if ($is_edit) : ?>
                <input type="hidden" name="event_id" value="<?php echo esc_attr((string) (int) $event->id); ?>">
            <?php endif; ?>

            <?php
            $preserve_params = ['event_status', 'event_source', 'event_type', 's', 'paged', 'orderby', 'order'];
            foreach ($preserve_params as $param) {
                if (isset($_GET[$param]) && $_GET[$param] !== '') {
                    echo '<input type="hidden" name="' . esc_attr($param) . '" value="' . esc_attr((string) $_GET[$param]) . '">' . "\n";
                }
            }
            ?>

            <h2><?php esc_html_e('Main information', 'poke-hub'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="gp_title_en"><?php esc_html_e('Title (EN)', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" id="gp_title_en" name="event[title_en]"
                               value="<?php echo esc_attr($title_en); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="gp_title_fr"><?php esc_html_e('Title (FR)', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" id="gp_title_fr" name="event[title_fr]"
                               value="<?php echo esc_attr($title_fr); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="gp_slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" id="gp_slug" name="event[slug]"
                               value="<?php echo esc_attr($slug); ?>"
                               placeholder="<?php esc_attr_e('Leave empty to auto-generate from title', 'poke-hub'); ?>">
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Time mode', 'poke-hub'); ?></th>
                    <td>
                        <label><input type="radio" name="event[mode]" value="local" <?php checked($event_mode, 'local'); ?>>
                            <?php esc_html_e('Local time', 'poke-hub'); ?></label><br>
                        <label><input type="radio" name="event[mode]" value="fixed" <?php checked($event_mode, 'fixed'); ?>>
                            <?php esc_html_e('Fixed UTC time', 'poke-hub'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Start', 'poke-hub'); ?></th>
                    <td>
                        <span class="pokehub-datetime-split" style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <span>
                                <label for="gp_start_date" class="screen-reader-text"><?php esc_html_e('Start date', 'poke-hub'); ?></label>
                                <input type="date" id="gp_start_date" name="event[start_date]" value="<?php echo esc_attr($start_date_value); ?>" required>
                            </span>
                            <span>
                                <label for="gp_start_time" class="screen-reader-text"><?php esc_html_e('Start time', 'poke-hub'); ?></label>
                                <input type="time" id="gp_start_time" name="event[start_time]" value="<?php echo esc_attr($start_time_value); ?>" step="60">
                            </span>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('End', 'poke-hub'); ?></th>
                    <td>
                        <span class="pokehub-datetime-split" style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <span>
                                <label for="gp_end_date" class="screen-reader-text"><?php esc_html_e('End date', 'poke-hub'); ?></label>
                                <input type="date" id="gp_end_date" name="event[end_date]" value="<?php echo esc_attr($end_date_value); ?>" required>
                            </span>
                            <span>
                                <label for="gp_end_time" class="screen-reader-text"><?php esc_html_e('End time', 'poke-hub'); ?></label>
                                <input type="time" id="gp_end_time" name="event[end_time]" value="<?php echo esc_attr($end_time_value); ?>" step="60">
                            </span>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th><label for="gp_image"><?php esc_html_e('Event image', 'poke-hub'); ?></label></th>
                    <td>
                        <div class="pokehub-special-event-image-field" id="pokehub-go-pass-image-field">
                            <div class="image-preview">
                                <?php if ($current_image_url) : ?>
                                    <img src="<?php echo esc_url($current_image_url); ?>"
                                         class="pokehub-event-image-preview"
                                         alt="">
                                <?php else : ?>
                                    <p class="description pokehub-event-image-placeholder" style="margin:0;">
                                        <?php esc_html_e('No image selected yet.', 'poke-hub'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <p>
                                <button type="button" class="button pokehub-select-event-image"><?php esc_html_e('Choose from Media Library', 'poke-hub'); ?></button>
                                <button type="button" class="button button-link-delete pokehub-remove-event-image" <?php echo $current_image_url ? '' : ' style="display:none;"'; ?>><?php esc_html_e('Remove image', 'poke-hub'); ?></button>
                            </p>
                            <input type="hidden" id="gp_image_id" name="event[image_id]" value="<?php echo esc_attr((string) $image_id); ?>">
                            <input type="hidden" id="gp_image_url" name="event[image_url]" value="<?php echo esc_attr($image_url); ?>">
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="gp_desc"><?php esc_html_e('Description', 'poke-hub'); ?></label></th>
                    <td><textarea id="gp_desc" name="event[description]" class="large-text" rows="4"><?php echo esc_textarea($desc); ?></textarea></td>
                </tr>
            </table>

            <h2><?php esc_html_e('Pass GO data', 'poke-hub'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="gp_points_per_rank"><?php esc_html_e('Points per rank', 'poke-hub'); ?></label></th>
                    <td><input type="number" id="gp_points_per_rank" name="gp_points_per_rank" min="1" value="<?php echo esc_attr((string) (int) $p['points_per_rank']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="gp_rewards_claim_end_date"><?php esc_html_e('Reward claim deadline', 'poke-hub'); ?></label></th>
                    <td>
                        <span class="pokehub-datetime-split" style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <span>
                                <label for="gp_rewards_claim_end_date" class="screen-reader-text"><?php esc_html_e('Reward claim deadline — date', 'poke-hub'); ?></label>
                                <input type="date" id="gp_rewards_claim_end_date" name="gp_rewards_claim_end_date" value="<?php echo esc_attr($claim_d); ?>">
                            </span>
                            <span>
                                <label for="gp_rewards_claim_end_time" class="screen-reader-text"><?php esc_html_e('Reward claim deadline — time', 'poke-hub'); ?></label>
                                <input type="time" id="gp_rewards_claim_end_time" name="gp_rewards_claim_end_time" value="<?php echo esc_attr($claim_t); ?>" step="60">
                            </span>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Unlimited points window', 'poke-hub'); ?></th>
                    <td>
                        <span class="pokehub-datetime-split" style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <span>
                                <label for="gp_unlimited_start_date" class="screen-reader-text"><?php esc_html_e('Unlimited window — start date', 'poke-hub'); ?></label>
                                <input type="date" id="gp_unlimited_start_date" name="gp_unlimited_start_date" value="<?php echo esc_attr($ul_sd); ?>">
                            </span>
                            <span>
                                <label for="gp_unlimited_start_time" class="screen-reader-text"><?php esc_html_e('Unlimited window — start time', 'poke-hub'); ?></label>
                                <input type="time" id="gp_unlimited_start_time" name="gp_unlimited_start_time" value="<?php echo esc_attr($ul_st); ?>" step="60">
                            </span>
                        </span>
                        <span aria-hidden="true"> — </span>
                        <span class="pokehub-datetime-split" style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <span>
                                <label for="gp_unlimited_end_date" class="screen-reader-text"><?php esc_html_e('Unlimited window — end date', 'poke-hub'); ?></label>
                                <input type="date" id="gp_unlimited_end_date" name="gp_unlimited_end_date" value="<?php echo esc_attr($ul_ed); ?>">
                            </span>
                            <span>
                                <label for="gp_unlimited_end_time" class="screen-reader-text"><?php esc_html_e('Unlimited window — end time', 'poke-hub'); ?></label>
                                <input type="time" id="gp_unlimited_end_time" name="gp_unlimited_end_time" value="<?php echo esc_attr($ul_et); ?>" step="60">
                            </span>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th><label for="gp_extra_daily"><?php esc_html_e('Extra note (optional)', 'poke-hub'); ?></label></th>
                    <td><textarea id="gp_extra_daily" name="gp_extra_daily_note" class="large-text" rows="2"><?php echo esc_textarea((string) $p['extra_daily_note']); ?></textarea></td>
                </tr>
            </table>

            <h3><?php esc_html_e('Daily points (standard actions)', 'poke-hub'); ?></h3>
            <p class="description"><?php esc_html_e('These three actions are always displayed first on the Pass; set points for each one.', 'poke-hub'); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Action', 'poke-hub'); ?></th>
                        <th style="width:120px;"><?php esc_html_e('Points', 'poke-hub'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Win a raid', 'poke-hub'); ?></td>
                        <td><input type="number" name="gp_daily_core[raid]" min="0" value="<?php echo esc_attr((string) (int) ($daily_core['raid'] ?? 0)); ?>"></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Hatch an egg', 'poke-hub'); ?></td>
                        <td><input type="number" name="gp_daily_core[egg]" min="0" value="<?php echo esc_attr((string) (int) ($daily_core['egg'] ?? 0)); ?>"></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Catch a Pokémon', 'poke-hub'); ?></td>
                        <td><input type="number" name="gp_daily_core[catch]" min="0" value="<?php echo esc_attr((string) (int) ($daily_core['catch'] ?? 0)); ?>"></td>
                    </tr>
                </tbody>
            </table>
            <p>
                <label for="gp_daily_points_cap"><strong><?php esc_html_e('Maximum points cap per day', 'poke-hub'); ?></strong></label><br>
                <input type="number" id="gp_daily_points_cap" class="small-text" name="gp_daily_points_cap" min="0" value="<?php echo esc_attr((string) $daily_pts_cap); ?>">
                <span class="description"><?php esc_html_e('0 = no limit. Otherwise, maximum total points that can be earned in a single day (all sources combined, based on your event rules).', 'poke-hub'); ?></span>
            </p>

            <h3><?php esc_html_e('Additional daily tasks', 'poke-hub'); ?></h3>
            <p class="description"><?php esc_html_e('One row per additional task; at least one row (label and/or points).', 'poke-hub'); ?></p>
            <table class="widefat striped" id="pokehub-gp-daily-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Task', 'poke-hub'); ?></th>
                        <th style="width:120px;"><?php esc_html_e('Points', 'poke-hub'); ?></th>
                        <th style="width:50px;"></th>
                    </tr>
                </thead>
                <tbody id="pokehub-gp-daily-body">
                    <?php foreach ($daily_tasks as $di => $d) : ?>
                        <tr class="pokehub-gp-daily-row">
                            <td><input type="text" class="large-text" name="gp_daily[<?php echo (int) $di; ?>][label]" value="<?php echo esc_attr((string) ($d['label'] ?? '')); ?>"></td>
                            <td><input type="number" name="gp_daily[<?php echo (int) $di; ?>][points]" min="0" value="<?php echo esc_attr((string) (int) ($d['points'] ?? 0)); ?>"></td>
                            <td><button type="button" class="button pokehub-gp-remove-daily">&times;</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="pokehub-gp-add-daily"><?php esc_html_e('Add a daily task', 'poke-hub'); ?></button></p>

            <h3><?php esc_html_e('Weekly tasks', 'poke-hub'); ?></h3>
            <p class="description"><?php esc_html_e('One row per task; at least one row.', 'poke-hub'); ?></p>
            <table class="widefat striped" id="pokehub-gp-weekly-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Task', 'poke-hub'); ?></th>
                        <th style="width:120px;"><?php esc_html_e('Points', 'poke-hub'); ?></th>
                        <th style="width:50px;"></th>
                    </tr>
                </thead>
                <tbody id="pokehub-gp-weekly-body">
                    <?php foreach ($weekly as $wi => $w) : ?>
                        <tr class="pokehub-gp-weekly-row">
                            <td><input type="text" class="large-text" name="gp_weekly[<?php echo (int) $wi; ?>][label]" value="<?php echo esc_attr((string) ($w['label'] ?? '')); ?>"></td>
                            <td><input type="number" name="gp_weekly[<?php echo (int) $wi; ?>][points]" min="0" value="<?php echo esc_attr((string) (int) ($w['points'] ?? 0)); ?>"></td>
                            <td><button type="button" class="button pokehub-gp-remove-weekly">&times;</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="pokehub-gp-add-weekly"><?php esc_html_e('Add a weekly task', 'poke-hub'); ?></button></p>

            <h3><?php esc_html_e('Ranks and rewards', 'poke-hub'); ?></h3>
            <p class="description"><?php esc_html_e('Types: XP, stardust, items, Pokémon (single instance; options Shiny / Shadow / Dynamax / Gigantamax), candies, XL candies, mega energy, bonus access (catalog; optional text on the Pass).', 'poke-hub'); ?></p>
            <p class="description"><?php esc_html_e('The "Bonus access" reward type (free or Deluxe) automatically creates a bonus milestone at that rank: a single rank, with no "end rank". Other types are rank rewards (optional "end rank" range). Reorder rows with the handle; saved order follows row order.', 'poke-hub'); ?></p>
            <table class="widefat striped" id="pokehub-gp-tier-table">
                <thead>
                    <tr>
                        <th class="pokehub-gp-tier-drag-col" style="width:36px;" aria-hidden="true"></th>
                        <th style="width:88px;"><?php esc_html_e('Start rank', 'poke-hub'); ?></th>
                        <th style="width:88px;"><?php esc_html_e('End rank', 'poke-hub'); ?></th>
                        <th><?php esc_html_e('Free', 'poke-hub'); ?></th>
                        <th><?php esc_html_e('Deluxe', 'poke-hub'); ?></th>
                        <th style="width:50px;"></th>
                    </tr>
                </thead>
                <tbody id="pokehub-gp-tier-body">
                    <?php foreach ($editor_rows as $ti => $row) : ?>
                        <?php
                        $rk = (!empty($row['milestone']) || pokehub_go_pass_reward_arrays_contain_bonus_type($row))
                            ? 'milestone'
                            : 'reward';
                        $r_from = (int) ($row['rank'] ?? 1);
                        $r_to   = isset($row['rank_to']) && (int) $row['rank_to'] > $r_from ? (int) $row['rank_to'] : 0;
                        ?>
                        <tr class="pokehub-gp-tier-row">
                            <td class="pokehub-gp-tier-drag-cell">
                                <span class="pokehub-gp-tier-drag-handle dashicons dashicons-menu" title="<?php echo esc_attr__('Drag to reorder rows', 'poke-hub'); ?>" aria-hidden="true"></span>
                            </td>
                            <td>
                                <input type="number" class="pokehub-gp-tier-rank-start" name="gp_tiers[<?php echo (int) $ti; ?>][rank]" min="1" value="<?php echo esc_attr((string) $r_from); ?>" required>
                            </td>
                            <td>
                                <span class="description pokehub-gp-tier-rank-to-dash"<?php echo $rk === 'reward' ? ' style="display:none;"' : ''; ?>>—</span>
                                <input type="number"
                                       class="pokehub-gp-tier-rank-to"
                                       name="gp_tiers[<?php echo (int) $ti; ?>][rank_to]"
                                       min="1"
                                       value="<?php echo $rk === 'reward' && $r_to > 0 ? esc_attr((string) $r_to) : ''; ?>"
                                       placeholder="—"
                                       aria-label="<?php esc_attr_e('End rank (optional)', 'poke-hub'); ?>"
                                    <?php echo $rk === 'milestone' ? ' disabled style="display:none;"' : ''; ?>
                                >
                            </td>
                            <td><?php pokehub_go_pass_render_rewards_column((int) $ti, $row, 'free'); ?></td>
                            <td><?php pokehub_go_pass_render_rewards_column((int) $ti, $row, 'premium'); ?></td>
                            <td><button type="button" class="button pokehub-gp-remove-tier">&times;</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button button-primary" id="pokehub-gp-add-tier"><?php esc_html_e('Add row', 'poke-hub'); ?></button></p>

            <?php submit_button($is_edit ? __('Update GO Pass', 'poke-hub') : __('Create GO Pass', 'poke-hub')); ?>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=poke-hub-events')); ?>"><?php esc_html_e('Back to events list', 'poke-hub'); ?></a>
            </p>
        </form>
    </div>
    <?php
}

add_action('admin_post_pokehub_save_go_pass_event', function (): void {
    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to do this.', 'poke-hub'));
    }
    if (empty($_POST['pokehub_go_pass_event_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pokehub_go_pass_event_nonce'])), 'pokehub_save_go_pass_event')) {
        wp_die(__('Security check failed.', 'poke-hub'));
    }

    $incoming_event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
    $was_new            = $incoming_event_id <= 0;

    $save_result = pokehub_special_events_save_row_from_post(pokehub_go_pass_event_type_slug());
    if (is_string($save_result)) {
        wp_die(esc_html($save_result));
    }
    $event_id = (int) $save_result;

    global $wpdb;
    $event_pokemon_table       = pokehub_get_table('special_event_pokemon');
    $event_pokemon_attacks_tbl = pokehub_get_table('special_event_pokemon_attacks');
    $event_bonus_table         = pokehub_get_table('special_event_bonus');
    $wpdb->delete($event_pokemon_attacks_tbl, ['event_id' => $event_id], ['%d']);
    $wpdb->delete($event_pokemon_table, ['event_id' => $event_id], ['%d']);
    $wpdb->delete($event_bonus_table, ['event_id' => $event_id], ['%d']);

    $payload = pokehub_go_pass_build_payload_from_post();
    pokehub_content_save_go_pass('special_event', $event_id, $payload);

    // Aligner les colonnes start_ts/end_ts des tables de contenu (dont content_go_pass) avec l’événement :
    // le formulaire Pass GO utilise event[start_date]/event[end_date], pas event[start]/event[end].
    $event_for_ts   = isset($_POST['event']) && is_array($_POST['event']) ? wp_unslash($_POST['event']) : [];
    $mode_for_dates = (isset($event_for_ts['mode']) && (string) $event_for_ts['mode'] === 'fixed') ? 'fixed' : 'local';
    $start_ts       = 0;
    $end_ts         = 0;
    if (function_exists('poke_hub_special_event_parse_date_time_for_save')) {
        $sd = isset($event_for_ts['start_date']) ? trim((string) $event_for_ts['start_date']) : '';
        $st = isset($event_for_ts['start_time']) ? trim((string) $event_for_ts['start_time']) : '';
        if ($sd !== '') {
            $start_ts = poke_hub_special_event_parse_date_time_for_save($sd, $st, $mode_for_dates);
        }
        $ed = isset($event_for_ts['end_date']) ? trim((string) $event_for_ts['end_date']) : '';
        $et = isset($event_for_ts['end_time']) ? trim((string) $event_for_ts['end_time']) : '';
        if ($ed !== '') {
            $end_ts = poke_hub_special_event_parse_date_time_for_save($ed, $et, $mode_for_dates);
        }
    }
    if ($start_ts > 0 && $end_ts > 0 && function_exists('pokehub_content_sync_dates_for_source')) {
        pokehub_content_sync_dates_for_source('special_event', $event_id, $start_ts, $end_ts);
    }

    pokehub_special_events_redirect_after_save($event_id, $was_new);
});
