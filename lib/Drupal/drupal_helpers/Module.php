<?php
/**
 * @file
 * Module helpers.
 */

namespace Drupal\drupal_helpers;

/**
 * Class Module.
 *
 * @package Drupal\drupal_helpers
 */
class Module extends \Drupal\drupal_helpers\System {
  /**
   * Enables a module and performs some error checking.
   *
   * @param string $module
   *   Module name to enable.
   * @param bool $enable_dependencies
   *   Flag to enable module's dependencies. Defaults to TRUE.
   *
   * @return bool
   *   Returns TRUE if module was enabled successfully, \DrupalUpdateException
   *   is thrown otherwise.
   *
   * @throws \DrupalUpdateException
   *   Throws exception if module was not enabled.
   */
  public static function enable($module, $enable_dependencies = TRUE) {
    if (self::isEnabled($module)) {
      \Drupal\drupal_helpers\General::messageSet(format_string('Module "@module" already exists - Aborting!', array(
        '@module' => $module,
      )));

      return TRUE;
    }
    $ret = module_enable(array($module), $enable_dependencies);
    if ($ret) {
      // Double check that the installed.
      if (self::isEnabled($module)) {
        \Drupal\drupal_helpers\General::messageSet(format_string('Module "@module" was successfully enabled.', array(
          '@module' => $module,
        )));

        return TRUE;
      }
    }

    throw new \DrupalUpdateException(format_string('Module "@module" could not enabled.', array(
      '@module' => $module,
    )));
  }

  /**
   * Disables a module and performs some error checking.
   *
   * @param string $module
   *   Module name to disable.
   * @param bool $disable_dependencies
   *   Flag to disable module's dependencies. Defaults to TRUE.
   *
   * @return bool
   *   Returns TRUE if module was disabled successfully, \DrupalUpdateException
   *   is thrown otherwise.
   *
   * @throws \DrupalUpdateException
   *   Throws exception if module was not disabled.
   */
  public static function disable($module, $disable_dependencies = TRUE) {
    if (self::isDisabled($module)) {
      \Drupal\drupal_helpers\General::messageSet(format_string('Module "@module" is already disabled - Aborting!', array(
        '@module' => $module,
      )));

      return TRUE;
    }

    module_disable(array($module), $disable_dependencies);

    if (self::isDisabled($module)) {
      \Drupal\drupal_helpers\General::messageSet(format_string('Module "@module" was successfully disabled.', array(
        '@module' => $module,
      )));

      return TRUE;
    }

    throw new \DrupalUpdateException(format_string('Module "@module" could not disabled.', array(
      '@module' => $module,
    )));
  }

  /**
   * Uninstalls a module.
   *
   * @param string $module
   *   Module name to uninstall.
   * @param bool $disable_dependencies
   *   Flag to disable module's dependencies. Defaults to TRUE.
   *
   * @return bool
   *   Returns TRUE if module was uninstalled successfully, \DrupalUpdateException
   *   is thrown otherwise.
   *
   * @throws \DrupalUpdateException
   *   Throws exception if module was not uninstalled.
   */
  public static function uninstall($module, $disable_dependencies = TRUE) {
    self::disable($module, $disable_dependencies);
    drupal_uninstall_modules(array($module), TRUE);

    if (self::isUninstalled($module)) {
      \Drupal\drupal_helpers\General::messageSet(format_string('Module "@module" was successfully uninstalled.', array(
        '@module' => $module,
      )));

      return TRUE;
    }

    throw new \DrupalUpdateException(format_string('Module "@module" could not uninstalled.', array(
      '@module' => $module,
    )));
  }

}
