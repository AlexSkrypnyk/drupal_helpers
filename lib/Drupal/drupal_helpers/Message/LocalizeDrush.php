<?php

namespace Drupal\drupal_helpers\Message;

/**
 * Class LocalizeDrush
 *
 * LocalizeString a message using appropriate available Drush calls.
 *
 * @package Drupal\drupal_helpers\Message
 */
class LocalizeDrush extends LocalizeString implements LocalizeInterface {
  /**
   * LocalizeDrush constructor.
   *
   * @param callable $localizeCall
   *  Callable class method or function name used to getLocalized. Use
   *  format accepted by call_user_func().
   */
  public function __construct($localizeCall = NULL) {
    if (!isset($localizeCall)) {
      $localizeCall = 'dt';
    }
    parent::__construct($localizeCall);
  }

}
