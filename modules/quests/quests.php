<?php
// modules/quests/quests.php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('POKE_HUB_QUESTS_PATH')) {
    define('POKE_HUB_QUESTS_PATH', __DIR__);
}

if (poke_hub_is_module_active('quests')) {
    require_once POKE_HUB_QUESTS_PATH . '/functions/quests-active-research.php';
    require_once POKE_HUB_QUESTS_PATH . '/admin/quests-admin.php';
}
