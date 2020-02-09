<?php

namespace Drupal\drupal_helpers;

/**
 * Class Theme.
 *
 * @package Drupal\drupal_helpers
 */
class Theme {

  /**
   * Set a theme as the default.
   */
  public static function setDefault($theme) {
    variable_set('theme_default', $theme);
  }

  /**
   * Get default theme.
   */
  public static function getDefault() {
    return variable_get('theme_default');
  }

  /**
   * Set a theme as the admin theme.
   */
  public static function setAdmin($theme) {
    variable_set('admin_theme', $theme);
  }

  /**
   * Get admin theme.
   */
  public static function getAdmin() {
    return variable_get('admin_theme', 'seven');
  }

  /**
   * Enable a theme and performs some error checking.
   *
   * @param string $theme
   *   Theme machine name.
   */
  public static function enable($theme) {
    if (self::isEnabled($theme)) {
      Utility::message('Theme "@theme" already exists - skipping.', [
        '@theme' => $theme,
      ]);

      return;
    }

    theme_enable([$theme]);

    // Double check that the theme was installed.
    if (!self::isEnabled($theme)) {
      throw new DrupalHelpersException('Theme "@theme" could not enabled.', [
        '@theme' => $theme,
      ]);
    }

    Utility::message('Theme "@theme" was successfully enabled.', [
      '@theme' => $theme,
    ]);
  }

  /**
   * Disables a theme and performs some error checking.
   *
   * @param string $theme
   *   Theme machine name.
   */
  public static function disable($theme) {
    if (self::isDisabled($theme)) {
      Utility::message('Theme "@theme" is already disabled - skipping.', [
        '@theme' => $theme,
      ]);

      return;
    }

    theme_disable([$theme]);

    if (!self::isDisabled($theme)) {
      throw new DrupalHelpersException('Theme "@theme" could not disabled.', [
        '@theme' => $theme,
      ]);
    }

    Utility::message('Theme "@theme" was successfully disabled.', [
      '@theme' => $theme,
    ]);
  }

  /**
   * Set theme setting.
   *
   * @param string $name
   *   Setting key.
   * @param mixed $value
   *   Setting value.
   * @param string|null $theme
   *   (optional) Theme name. Defaults to default theme.
   */
  public static function setSetting($name, $value, $theme = NULL) {
    $theme = $theme ? $theme : variable_get('theme_default');
    $variable_name = 'theme_' . $theme . '_settings';
    $theme_settings = variable_get($variable_name, []);
    $theme_settings[$name] = $value;
    variable_set($variable_name, $theme_settings);
  }

  /**
   * Retrieves the weight of a theme.
   *
   * @param string $name
   *   Machine name of theme.
   *
   * @return int
   *   Weight of the specified item.
   */
  public static function weightGet($name) {
    return db_query("SELECT weight FROM {system} WHERE name = :name AND type = :type", [
      ':name' => $name,
      ':type' => 'theme',
    ])->fetchField();
  }

  /**
   * Updates the weight of a theme.
   *
   * @param string $name
   *   Machine name of theme.
   * @param int $weight
   *   Weight value to set.
   */
  public static function weightSet($name, $weight) {
    db_update('system')
      ->fields(['weight' => $weight])
      ->condition('name', $name)
      ->condition('type', 'theme')
      ->execute();
  }

  /**
   * Checks if theme is enabled.
   *
   * @param string $name
   *   Machine name of theme.
   *
   * @return bool
   *   TRUE if the theme is enabled, FALSE otherwise.
   */
  public static function isEnabled($name) {
    $q = db_select('system');
    $q->fields('system', ['name', 'status'])
      ->condition('name', $name, '=')
      ->condition('type', 'theme', '=');
    $rs = $q->execute();

    return (bool) $rs->fetch()->status;
  }

  /**
   * Checks the status of a theme.
   *
   * @param string $name
   *   Machine name of theme.
   *
   * @return bool
   *   TRUE if the theme is disabled, FALSE otherwise.
   */
  public static function isDisabled($name) {
    return !self::isEnabled($name);
  }

  /**
   * Checks whether a theme is uninstalled.
   *
   * @param string $name
   *   Machine name of theme.
   *
   * @return bool
   *   TRUE if the theme is uninstalled, FALSE otherwise.
   */
  public static function isUninstalled($name) {
    $q = db_select('system');
    $q->fields('system', ['name', 'schema_version'])
      ->condition('name', $name, '=')
      ->condition('type', 'theme', '=');
    $rs = $q->execute();

    return (int) $rs->fetch()->schema_version === -1;
  }

}
