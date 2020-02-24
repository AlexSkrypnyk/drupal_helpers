<?php

namespace Drupal\drupal_helpers;

/**
 * Class General.
 *
 * @package Drupal\drupal_helpers
 *
 * @deprecated in drupal_helpers:7.x-1.5 and is removed from drupal_helpers:7.x-1.6.
 * There has been too many 'generic' classes. All functionality from these
 * classes was moved to \Drupal\drupal_helpers\Utility class.
 * @see https://www.drupal.org/project/drupal_helpers/issues/3112227
 * @see \Drupal\drupal_helpers\Utility
 */
class General {

  /**
   * Print message.
   *
   * Prints to stdout if using drush, or drupal_set_message() if the web UI.
   *
   * @param string $message
   *   String containing message.
   * @param string $prefix
   *   Prefix to be used for messages when called through CLI.
   *   Defaults to '-- '.
   * @param int $indent
   *   Indent for messages. Defaults to 2.
   *
   * @deprecated in drupal_helpers:7.x-1.5 and is removed from drupal_helpers:7.x-1.6. Use
   * @see https://www.drupal.org/project/drupal_helpers/issues/3112227
   * @see \Drupal\drupal_helpers\Utility::message()
   */
  public static function messageSet($message, $prefix = '-- ', $indent = 2) {
    Utility::message($message, [], $indent, $prefix);
  }

}
