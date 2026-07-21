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
function drupal_helpers_update_test_post_update_uninstall_module(): string {
  return Helper::module()->uninstall('drupal_helpers_test');
}
