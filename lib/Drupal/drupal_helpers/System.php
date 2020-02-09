<?php

namespace Drupal\drupal_helpers;

/**
 * Class System.
 *
 * @package Drupal\drupal_helpers
 *
 * @deprecated in drupal_helpers:7.x-1.5 and is removed from
 * drupal_helpers:7.x-1.6. This class was used as a parent to other classes.
 * It is now deprecated because architecturally per-component functionality must
 * be self-contained in a relevant class.
 * @see https://www.drupal.org/project/drupal_helpers/issues/3112227
 * @see \Drupal\drupal_helpers\Module
 * @see \Drupal\drupal_helpers\Theme
 */
class System {

  /**
   * Retrieves the weight of a module or theme from the system table.
   *
   * @param string $name
   *   Machine name of module or theme.
   * @param string $type
   *   Item type as it appears in 'type' column in system table. Can be one of
   *   'module' or 'theme'. Defaults to 'module'.
   *
   * @return int
   *   Weight of the specified item.
   *
   * @deprecated in drupal_helpers:7.x-1.5 and is removed from drupal_helpers:7.x-1.6. Use
   * @see https://www.drupal.org/project/drupal_helpers/issues/3112227
   * @see \Drupal\drupal_helpers\Module::weightGet()
   * @see \Drupal\drupal_helpers\Theme::weightGet()
   */
  public static function weightGet($name, $type = 'module') {
    if ($type == 'theme') {
      return Theme::weightGet($name);
    }

    return Module::weightGet($name);
  }

  /**
   * Updates the weight of a module or theme in the system table.
   *
   * @param string $name
   *   Machine name of module or theme.
   * @param int $weight
   *   Weight value to set.
   * @param string $type
   *   Item type as it appears in 'type' column in system table. Can be one of
   *   'module' or 'theme'. Defaults to 'module'.
   *
   * @deprecated in drupal_helpers:7.x-1.5 and is removed from drupal_helpers:7.x-1.6. Use
   * @see https://www.drupal.org/project/drupal_helpers/issues/3112227
   * @see \Drupal\drupal_helpers\Module::weightSet()
   * @see \Drupal\drupal_helpers\Theme::weightSet()
   */
  public static function weightSet($name, $weight, $type = 'module') {
    if ($type == 'theme') {
      Theme::weightSet($name, $weight);
    }

    Module::weightSet($name, $weight);
  }

  /**
   * Checks the status of a module or theme.
   *
   * @param string $name
   *   Machine name of module or theme.
   * @param string $type
   *   Item type as it appears in 'type' column in system table. Can be one of
   *   'module' or 'theme'. Defaults to 'module'.
   *
   * @return bool
   *   TRUE if the item is enabled, FALSE otherwise.
   *
   * @deprecated in drupal_helpers:7.x-1.5 and is removed from drupal_helpers:7.x-1.6. Use
   * @see https://www.drupal.org/project/drupal_helpers/issues/3112227
   * @see \Drupal\drupal_helpers\Module::isEnabled()
   * @see \Drupal\drupal_helpers\Theme::isEnabled()
   */
  public static function isEnabled($name, $type = 'module') {
    if ($type == 'theme') {
      return Theme::isEnabled($name);
    }

    return Module::isEnabled($name);
  }

  /**
   * Checks the status of a module or theme in the system table.
   *
   * @param string $name
   *   Machine name of module or theme.
   * @param string $type
   *   (optional) Item type as it appears in 'type' column in system table. Can
   *   be one of 'module' or 'theme'. Defaults to 'module'.
   *
   * @return bool
   *   TRUE if the item is disabled, FALSE otherwise.
   *
   * @deprecated in drupal_helpers:7.x-1.5 and is removed from drupal_helpers:7.x-1.6. Use
   * @see https://www.drupal.org/project/drupal_helpers/issues/3112227
   * @see \Drupal\drupal_helpers\Module::isDisabled()
   * @see \Drupal\drupal_helpers\Theme::isDisabled()
   */
  public static function isDisabled($name, $type = 'module') {
    return !self::isEnabled($name, $type);
  }

  /**
   * Checks whether a module or theme is uninstalled.
   *
   * @param string $name
   *   Machine name of module or theme.
   * @param string $type
   *   (optional) Item type as it appears in 'type' column in system table.
   *   Can be one of 'module' or 'theme'. Defaults to 'module'.
   *
   * @return bool
   *   TRUE if the item is uninstalled, FALSE otherwise.
   *
   * @deprecated in drupal_helpers:7.x-1.5 and is removed from drupal_helpers:7.x-1.6. Use
   * @see https://www.drupal.org/project/drupal_helpers/issues/3112227
   * @see \Drupal\drupal_helpers\Module::isUninstalled()
   * @see \Drupal\drupal_helpers\Theme::isUninstalled()
   */
  public static function isUninstalled($name, $type = 'module') {
    if ($type == 'theme') {
      return Theme::isUninstalled($name);
    }

    return Module::isUninstalled($name);
  }

}
