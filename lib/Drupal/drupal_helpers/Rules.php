<?php

namespace Drupal\drupal_helpers;

use Exception;

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
   *
   * @throws \Exception
   */
  public static function setActive($rule_name, $value) {
    $t = get_t();

    $action = $value ? 'enable' : 'disable';
    $actioned = $action . 'd';
    $replacements = [
      '!rule' => $rule_name,
      '!action' => $action,
      '!actioned' => $actioned,
    ];

    try {
      $rules_config = rules_config_load($rule_name);
      if (!$rules_config) {
        General::messageSet($t('Skipped: !rule was not found', $replacements));

        return;
      }

      $rules_config->active = (bool) $value;
      $rules_config->save();

      General::messageSet($t('The rule !rule has been !actioned.', $replacements));
    }
    catch (Exception $e) {
      $replacements['@error_message'] = $e->getMessage();
      $message = 'Failed to !action !rule: @error_message';

      throw new Exception($t($message, $replacements), $e->getCode(), $e);
    }
  }

  /**
   * Disable a rules configuration.
   *
   * @param string $rule_name
   *   Machine name of the Rule.
   *
   * @throws \Exception
   */
  public static function disable($rule_name) {
    self::setActive($rule_name, FALSE);
  }

  /**
   * Enable a rules configuration.
   *
   * @param string $rule_name
   *   Machine name of the Rule.
   *
   * @throws \Exception
   */
  public static function enable($rule_name) {
    self::setActive($rule_name, TRUE);
  }

}
