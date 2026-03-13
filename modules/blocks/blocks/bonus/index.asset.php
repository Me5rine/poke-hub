<?php
if (!defined('ABSPATH')) {
    exit;
}

$version = defined('POKE_HUB_VERSION') ? POKE_HUB_VERSION : '1.0.0';

return array(
  'dependencies' => array(
    'wp-blocks',
    'wp-element',
    'wp-i18n',
    'wp-block-editor',
  ),
  'version' => $version,
);

