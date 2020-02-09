<?php

namespace Drupal\drupal_helpers;

/**
 * Class Utility.
 *
 * @package Drupal\drupal_helpers.
 */
class Utility {

  /**
   * Print a message.
   *
   * @param string $message
   *   String containing a message with optional tokens (similar to t()).
   * @param array $args
   *   (optional) Array of tokens arguments for a message.
   * @param int $indent
   *   (optional) Indent for messages. Defaults to 2.
   * @param string $prefix
   *   (optional) Prefix to be used for messages when called through CLI.
   *   Defaults to '-- '.
   *
   * @return string
   *   Message as a string. Used to capture what was already printed to reuse
   *   in other context.
   */
  public static function message($message, array $args = [], $indent = 2, $prefix = '-- ') {
    $t = get_t();
    $message = $t($message, $args);

    if (function_exists('drush_print')) {
      $message = ((string) $prefix) . html_entity_decode($message);
      drush_print($message, $indent);
    }
    else {
      drupal_set_message($message);
    }

    return $message;
  }

  /**
   * Recursively remove empty elements from array.
   *
   * @param array $haystack
   *   Array to remove elements from.
   *
   * @return array
   *   Array with removed elements.
   */
  public static function arrayRemoveEmpty(array $haystack) {
    foreach ($haystack as $key => $value) {
      if (is_array($haystack[$key])) {
        $haystack[$key] = call_user_func([
          __CLASS__,
          __FUNCTION__,
        ], $haystack[$key]);
      }

      if (empty($haystack[$key])) {
        unset($haystack[$key]);
      }
    }

    return $haystack;
  }

  /**
   * Retrieve array column.
   *
   * Supports scalar, arrays and object as array values. For complex objects
   * value retrieval a getter must be specified.
   *
   * @param mixed $value
   *   Value to extract column from.
   * @param string|int $column
   *   (optional) Array column to retrieve value from.
   * @param string $getter
   *   (optional) Getter for cases when values are complex objects.
   *
   * @return array
   *   Array of values retrieved from column or a scalar value if scalar value
   *   was provided.
   */
  public static function arrayGetColumn($value, $column = NULL, $getter = NULL) {
    $result = $value;

    if (is_array($value)) {
      // Value is an array and $column is set.
      foreach ($value as $k => $v) {
        // Value's value is an array.
        if (is_array($v)) {
          // Column exists.
          if (array_key_exists($column, $v)) {
            // Recursively call current function and retrieve each value.
            $result[$k] = call_user_func([
              __CLASS__,
              __FUNCTION__,
            ], $v[$column], NULL, $getter);
          }
          // Column is set, but does not exist.
          else {
            // Not using custom exception for portability.
            throw new \Exception(sprintf('Column "%s" does not exist', $column));
          }
        }
        // Value is not an array.
        else {
          $result[$k] = call_user_func([
            __CLASS__,
            __FUNCTION__,
          ], $v, $column, $getter);
        }
      }
    }
    // Value is not an array.
    else {
      // Value is an object.
      if (is_object($value)) {
        // Try column as object property.
        if (in_array($column, array_keys(get_object_vars($value)))) {
          $result = $value->{$column};
        }
        // Try using getter.
        elseif (method_exists($value, $getter)) {
          $result = call_user_func([
            $value,
            $getter,
          ]);
        }
        // Value object getter does not exist - return as is.
        else {
          $result = $value;
        }
      }
      // Value is a scalar or an array - return as is.
      else {
        $result = $value;
      }
    }

    return $result;
  }

  /**
   * Check that provided data is JSON string.
   *
   * @param string $data
   *   JSON data to assess.
   *
   * @return bool
   *   TRUE if $data is a valid JSON string, FALSE otherwise.
   */
  public static function isJson($data) {
    if (!is_string($data)) {
      return FALSE;
    }

    json_decode($data);

    return json_last_error() === JSON_ERROR_NONE;
  }

}
