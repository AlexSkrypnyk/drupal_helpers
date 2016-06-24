<?php

namespace Drupal\drupal_helpers;

use Drupal\drupal_helpers\Message;
use Drupal\drupal_helpers\Message\MessageInterface;

/**
 * Class General.
 *
 * @package Drupal\drupal_helpers
 */
class General {

  /**
   * @var MessageInterface
   */
  private static $messageInstance;

  /**
   * Get a Message class.
   *
   * Singleton Factory for getting a common instance of the Message Interface.
   *
   * @return MessageInterface
   */
  private static function getMessageInstance() {
    if (!self::$messageInstance instanceof MessageInterface) {
      self::$messageInstance = new Message;
    }

    return self::$messageInstance;
  }

  /**
   * Helper to print messages.
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
   */
  public static function messageSet($message, $prefix = '-- ', $indent = 2) {
    self::getMessageInstance()
      ->setText($message)
      ->setPrefix($prefix)
      ->setIndent($indent)
      ->printOut();
  }

}
