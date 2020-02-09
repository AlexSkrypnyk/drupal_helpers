<?php

namespace Drupal\drupal_helpers;

/**
 * Class Module.
 *
 * @package Drupal\drupal_helpers
 */
class Module {

  /**
   * Enables a module and performs some error checking.
   *
   * @param string $module
   *   Module name to enable.
   * @param bool $enable_dependencies
   *   Flag to enable module's dependencies. Defaults to TRUE.
   *
   * @throws \DrupalUpdateException
   *   Throws exception if module was not enabled.
   */
  public static function enable($module, $enable_dependencies = TRUE) {
    if (self::isEnabled($module)) {
      Utility::message('Module "@module" already enabled - skipping enabling!', [
        '@module' => $module,
      ]);

      return;
    }

    $is_enabled = module_enable([$module], $enable_dependencies);

    // Double check that the module was installed.
    if (!$is_enabled || !self::isEnabled($module)) {
      throw new DrupalHelpersException('Module "@module" could not be enabled.', [
        '@module' => $module,
      ]);
    }

    Utility::message('Module "@module" was successfully enabled.', [
      '@module' => $module,
    ]);
  }

  /**
   * Disables a module and performs some error checking.
   *
   * @param string $module
   *   Module name to disable.
   * @param bool $disable_dependents
   *   If TRUE, dependent modules will automatically be added and disabled in
   *   the correct order.
   *
   * @throws DrupalHelpersException
   *   Throws exception if module was not disabled.
   */
  public static function disable($module, $disable_dependents = TRUE) {
    if (self::isDisabled($module)) {
      Utility::message('Module "@module" is already disabled - skipping disabling!', [
        '@module' => $module,
      ]);

      return;
    }

    module_disable([$module], $disable_dependents);

    if (!self::isDisabled($module)) {
      throw new DrupalHelpersException('Module "@module" could not be disabled.', [
        '@module' => $module,
      ]);
    }

    Utility::message('Module "@module" was successfully disabled.', [
      '@module' => $module,
    ]);
  }

  /**
   * Uninstalls a module.
   *
   * @param string $module
   *   Module name to uninstall.
   * @param bool $uninstall_dependents
   *   If TRUE, dependent modules will automatically be disabled and uninstalled
   *   in the correct order.
   *
   * @throws DrupalHelpersException
   *   Throws exception if module was not uninstalled.
   */
  public static function uninstall($module, $uninstall_dependents = TRUE) {
    self::disable($module, $uninstall_dependents);
    drupal_uninstall_modules([$module], $uninstall_dependents);

    if (!self::isUninstalled($module)) {
      throw new DrupalHelpersException('Module "@module" could not uninstalled.', [
        '@module' => $module,
      ]);
    }

    Utility::message('Module "@module" was successfully uninstalled.', ['@module' => $module]);
  }

  /**
   * Removes already uninstalled module.
   *
   * @param string $module
   *   Module name to remove.
   */
  public static function remove($module) {
    db_update('system')
      ->fields(['status' => '0'])
      ->condition('name', $module)
      ->execute();

    db_delete('cache_bootstrap')
      ->condition('cid', 'system_list')
      ->execute();

    db_delete('system')
      ->condition('name', $module)
      ->execute();

    Utility::message('Removed traces of module "@module".', ['@module' => $module]);
  }

  /**
   * Retrieves the weight of a module.
   *
   * @param string $name
   *   Machine name of module.
   *
   * @return int
   *   Weight of the specified item.
   */
  public static function weightGet($name) {
    return db_query("SELECT weight FROM {system} WHERE name = :name AND type = :type", [
      ':name' => $name,
      ':type' => 'module',
    ])->fetchField();
  }

  /**
   * Updates the weight of a module.
   *
   * @param string $name
   *   Machine name of module.
   * @param int $weight
   *   Weight value to set.
   */
  public static function weightSet($name, $weight) {
    db_update('system')
      ->fields(['weight' => $weight])
      ->condition('name', $name)
      ->condition('type', 'module')
      ->execute();
  }

  /**
   * Checks if module is enabled.
   *
   * @param string $name
   *   Machine name of module.
   *
   * @return bool
   *   TRUE if the module is enabled, FALSE otherwise.
   */
  public static function isEnabled($name) {
    $q = db_select('system');
    $q->fields('system', ['name', 'status'])
      ->condition('name', $name, '=')
      ->condition('type', 'module', '=');
    $rs = $q->execute();

    return (bool) $rs->fetch()->status;
  }

  /**
   * Checks the status of a module.
   *
   * @param string $name
   *   Machine name of module.
   *
   * @return bool
   *   TRUE if the module is disabled, FALSE otherwise.
   */
  public static function isDisabled($name) {
    return !self::isEnabled($name);
  }

  /**
   * Checks whether a module is uninstalled.
   *
   * @param string $name
   *   Machine name of module.
   *
   * @return bool
   *   TRUE if the module is uninstalled, FALSE otherwise.
   */
  public static function isUninstalled($name) {
    $q = db_select('system');
    $q->fields('system', ['name', 'schema_version'])
      ->condition('name', $name, '=')
      ->condition('type', 'module', '=');
    $rs = $q->execute();

    return (int) $rs->fetch()->schema_version === -1;
  }

}
