<?php

/**
 * @file
 * Enables the drupal_helpers modules in the pre-update database state.
 *
 * The update path then exercises the Module helper from a post-update hook,
 * uninstalling a code-present module the way a deploy hook would.
 */

declare(strict_types=1);

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Record the helper modules as installed.
foreach (['drupal_helpers', 'drupal_helpers_update_test', 'drupal_helpers_test'] as $installed_module) {
  $connection->merge('key_value')
    ->keys(['collection' => 'system.schema', 'name' => $installed_module])
    ->fields(['value' => serialize(8000)])
    ->execute();
}

// Enable those modules in core.extension.
$data = $connection->select('config', 'c')
  ->fields('c', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();

$extension = is_string($data) ? unserialize($data, ['allowed_classes' => FALSE]) : [];
$extension = is_array($extension) ? $extension : [];
$modules = isset($extension['module']) && is_array($extension['module']) ? $extension['module'] : [];
$modules['drupal_helpers'] = 0;
$modules['drupal_helpers_update_test'] = 0;
$modules['drupal_helpers_test'] = 0;
ksort($modules);
$extension['module'] = $modules;

$connection->update('config')
  ->fields(['data' => serialize($extension)])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();
