<?php
if (!defined('ABSPATH')) {
    exit;
}

$version = poke_hub_plugin_asset_version('modules/blocks/blocks/go-pass/index.js');

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
