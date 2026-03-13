<?php
// modules/eggs/eggs.php

if (!defined('ABSPATH')) {
    exit;
}

if (!poke_hub_is_module_active('eggs')) {
    return;
}

define('POKE_HUB_EGGS_PATH', __DIR__);
define('POKE_HUB_EGGS_URL', POKE_HUB_URL . 'modules/eggs/');

require_once __DIR__ . '/functions/eggs-helpers.php';
require_once __DIR__ . '/functions/eggs-pages.php';
require_once __DIR__ . '/admin/eggs-admin.php';
require_once __DIR__ . '/admin/eggs-metabox.php';
require_once __DIR__ . '/public/eggs-shortcode.php';
