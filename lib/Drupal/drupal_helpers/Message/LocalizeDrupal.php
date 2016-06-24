<?php

namespace Drupal\drupal_helpers\Message;

/**
 * Class LocalizeDrupal
 *
 * LocalizeString a message using appropriate available Drupal calls.
 *
 * @package Drupal\drupal_helpers\Message
 */
class LocalizeDrupal extends LocalizeString implements LocalizeInterface {
  /**
   * LocalizeDrupal constructor.
   *
   * @param callable $localizeCall
   *  Callable class method or function name used to getLocalized. Use
   *  format accepted by call_user_func().
   */
  public function __construct($localizeCall = NULL) {
    if (!isset($localizeCall)) {
      $localizeCall = 't';
    }
    parent::__construct($localizeCall);
  }

}
