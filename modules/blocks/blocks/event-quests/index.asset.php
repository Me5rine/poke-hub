<?php
if (!defined('ABSPATH')) {
    exit;
}

$version = poke_hub_plugin_asset_version('modules/blocks/blocks/event-quests/index.js');

return array(
  'dependencies' => array(
    'wp-blocks',
    'wp-element',
    'wp-i18n',
    'wp-block-editor',
  ),
  'version' => $version,
);

