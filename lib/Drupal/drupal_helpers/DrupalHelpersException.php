<?php

namespace Drupal\drupal_helpers;

/**
 * Class DrupalHelpersException.
 *
 * Exception class handle all exceptions thrown by drupal_helpers classes.
 *
 * @package Drupal\drupal_helpers
 */
class DrupalHelpersException extends \Exception {

  /**
   * DrupalHelpersException constructor.
   *
   * @param string $message
   *   The exception message with optional placeholders in the format of
   *   Drupal's t() function.
   * @param array $args
   *   Array of token arguments in the format of Drupal's t() function.
   * @param \Throwable|null $previous
   *   (optional) Previously thrown exception.
   */
  public function __construct($message = '', array $args = [], \Throwable $previous = NULL) {
    $message = !empty($message) ? $message : 'Error occurred.';

    $printed = Utility::message($message, $args, 0, 'ERROR: ');

    parent::__construct($printed, 0, $previous);
  }

}
