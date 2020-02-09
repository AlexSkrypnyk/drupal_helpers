<?php

namespace Drupal\drupal_helpers;

/**
 * Class Rules.
 *
 * @package Drupal\drupal_helpers
 */
class Rules {

  /**
   * Set Active.
   *
   * Set the active property for a rules configuration.
   *
   * @param string $rule_name
   *   Machine name of the Rule.
   * @param bool $value
   *   Set rule active property to value.
   */
  public static function setActive($rule_name, $value) {
    $action = $value ? 'enable' : 'disable';

    $replacements = [
      '@rule_name' => $rule_name,
      '@action' => $action,
      '@actioned' => $action . 'd',
    ];

    try {
      $rules_config = rules_config_load($rule_name);

      if (!$rules_config) {
        throw new DrupalHelpersException('Failed to load rules "@rule_name" configuration', [
          '@rule_name' => $rule_name,
        ]);
      }

      $rules_config->active = (bool) $value;
      $rules_config->save();
      Utility::message('The rules @rule_name has been @actioned.', $replacements);
    }
    catch (\Exception $e) {
      throw new DrupalHelpersException('Failed to @action rules "@rule_name".', $replacements, $e);
    }
  }

  /**
   * Disable a rules configuration.
   *
   * @param string $rule_name
   *   Machine name of the Rule.
   */
  public static function disable($rule_name) {
    self::setActive($rule_name, FALSE);
  }

  /**
   * Enable a rules configuration.
   *
   * @param string $rule_name
   *   Machine name of the Rule.
   */
  public static function enable($rule_name) {
    self::setActive($rule_name, TRUE);
  }

  /**
   * Delete a rules configuration.
   *
   * @param string $rule_name
   *   Machine name of the Rule.
   */
  public static function delete($rule_name) {
    try {
      $rules_config = rules_config_load($rule_name);

      if (!$rules_config) {
        throw new DrupalHelpersException('Failed to load rules "@rule_name" configuration', [
          '@rule_name' => $rule_name,
        ]);
      }

      $rules_config->delete();

      Utility::message('The rules "@rule_name" has been deleted.', [
        '@rule_name' => $rule_name,
      ]);
    }
    catch (\Exception $e) {
      throw new DrupalHelpersException('Failed to delete rules "@rule_name".', [
        '@rule_name' => $rule_name,
      ], $e);
    }
  }

}
