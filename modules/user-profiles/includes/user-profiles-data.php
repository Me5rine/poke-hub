<?php
// modules/user-profiles/includes/user-profiles-data.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralized data definitions for User Profiles module
 * 
 * This file contains all lists and options that can be customized.
 * Use WordPress filters to modify these lists without editing this file.
 */

/**
 * Get default teams list.
 * Filter: poke_hub_user_profiles_teams
 *
 * @return array Teams list (slug => label)
 */
function poke_hub_get_default_teams() {
    return [
        'instinct' => __('Instinct (Yellow)', 'poke-hub'),
        'mystic'   => __('Mystic (Blue)', 'poke-hub'),
        'valor'    => __('Valor (Red)', 'poke-hub'),
    ];
}

/**
 * Get default reasons list.
 * Filter: poke_hub_user_profiles_reasons
 *
 * @return array Reasons list (slug => label)
 */
function poke_hub_get_default_reasons() {
    return [
        'xp'           => __('Earn XP', 'poke-hub'),
        'send_gifts'   => __('Send gifts', 'poke-hub'),
        'open_gifts'   => __('Open gifts', 'poke-hub'),
        'join_raids'   => __('Join raids', 'poke-hub'),
        'invite_raids' => __('Invite to raids', 'poke-hub'),
        'trade'        => __('Trade', 'poke-hub'),
    ];
}






