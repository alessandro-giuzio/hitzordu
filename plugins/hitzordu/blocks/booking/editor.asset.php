<?php
/**
 * Dependencies and version for the booking block's editor script.
 *
 * @package Hitzordu
 */

return array(
  'dependencies' => array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'),
  'version' => (string) filemtime(__DIR__ . '/editor.js'),
);
