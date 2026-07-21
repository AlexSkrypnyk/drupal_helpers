<?php

/**
 * @file
 * Post-update hooks for the drupal_helpers_update_test module.
 */

declare(strict_types=1);

use Drupal\drupal_helpers\Helper;

/**
 * Uninstalls a module whose code is present, as a deploy hook would.
 */
function drupal_helpers_update_test_post_update_1_uninstall_module(): string {
  return Helper::module()->uninstall('drupal_helpers_test');
}

/**
 * Force-removes an orphaned module whose code was deleted from disk.
 */
function drupal_helpers_update_test_post_update_2_force_remove_orphan(): string {
  // update.php refuses to run while a module recorded in core.extension is
  // missing from disk, so the orphaned state is simulated here rather than
  // seeded in the fixture: register a codeless module before removing it.
  \Drupal::configFactory()->getEditable('core.extension')->set('module.drupal_helpers_orphan', 0)->save();
  \Drupal::keyValue('system.schema')->set('drupal_helpers_orphan', 8000);

  return Helper::module()->uninstall('drupal_helpers_orphan', function (string $module): void {
    \Drupal::state()->set('drupal_helpers_update_test_removed', $module);
  });
}
