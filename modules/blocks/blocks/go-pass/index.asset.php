<?php
if (!defined('ABSPATH')) {
    exit;
}

$version = defined('POKE_HUB_VERSION') ? POKE_HUB_VERSION : '1.0.0';

return [
    'dependencies' => [
        'wp-api-fetch',
        'wp-block-editor',
        'wp-blocks',
        'wp-components',
        'wp-element',
        'wp-i18n',
    ],
    'version'      => $version,
];
