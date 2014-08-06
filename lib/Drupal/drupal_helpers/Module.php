<?php

namespace Drupal\drupal_helpers;

class Module extends \Drupal\drupal_helpers\System {
  /**
   * Enables a module and performs some error checking.
   *
   * @param string $module
   * @param bool $enable_dependencies
   *
   * @return bool
   *  - TRUE: Module was enabled successfully.
   *
   * @throws \DrupalUpdateException
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
   * @param bool $disable_dependents
   *
   * @return bool
   *  - TRUE: Module was disabled successfully.
   *
   * @throws \DrupalUpdateException
   */
  public static function disable($module, $disable_dependents = TRUE) {
    if (self::isDisabled($module)) {
      \Drupal\drupal_helpers\General::messageSet(format_string('Module "@module" is already disabled - Aborting!', array(
        '@module' => $module,
      )));

      return TRUE;
    }

    module_disable(array($module), $disable_dependents);

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
   * @param bool $disable_dependents
   *
   * @return bool
   *  - TRUE: Module was uninstalled successfully.
   */
  public static function uninstall($module, $disable_dependents = TRUE) {
    self::disable($module, $disable_dependents);
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
