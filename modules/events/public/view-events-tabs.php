<?php
// File: modules/events/public/view-events-tabs.php

if (!defined('ABSPATH')) {
    exit;
}

$current_status = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'current';
$valid_statuses = ['current', 'upcoming', 'past', 'all'];
if (!in_array($current_status, $valid_statuses, true)) {
    $current_status = 'current';
}

// Base URL sans paramètre status (mais on garde event_type, category, etc.)
$base_url = remove_query_arg(['status', 'pg', 'paged']);

?>
<div class="pokehub-events-tabs">
    <a href="<?php echo esc_url(add_query_arg('status', 'current', $base_url)); ?>"
       class="tab me5rine-lab-form-button <?php echo $current_status === 'current' ? 'active' : ''; ?>">
        <?php esc_html_e('Ongoing', 'poke-hub'); ?>
    </a>
    <a href="<?php echo esc_url(add_query_arg('status', 'upcoming', $base_url)); ?>"
       class="tab me5rine-lab-form-button <?php echo $current_status === 'upcoming' ? 'active' : ''; ?>">
        <?php esc_html_e('Upcoming', 'poke-hub'); ?>
    </a>
    <a href="<?php echo esc_url(add_query_arg('status', 'past', $base_url)); ?>"
       class="tab me5rine-lab-form-button <?php echo $current_status === 'past' ? 'active' : ''; ?>">
        <?php esc_html_e('Past', 'poke-hub'); ?>
    </a>
    <a href="<?php echo esc_url(add_query_arg('status', 'all', $base_url)); ?>"
       class="tab me5rine-lab-form-button <?php echo $current_status === 'all' ? 'active' : ''; ?>">
        <?php esc_html_e('All', 'poke-hub'); ?>
    </a>
</div>
