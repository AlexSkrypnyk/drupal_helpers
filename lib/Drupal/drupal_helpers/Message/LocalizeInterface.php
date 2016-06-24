<?php

namespace Drupal\drupal_helpers\Message;

/**
 * Class LocalizeString.
 *
 * @package Drupal\drupal_helpers\Message
 */
interface LocalizeInterface {
  /**
   * @return array
   */
  public function getVars();

  /**
   * @param array $vars
   *
   * @return LocalizeInterface
   */
  public function setVars($vars);

  /**
   * @return LocalizeInterface
   */
  public function resetVars();

  /**
   * @return string
   */
  public function getText();

  /**
   * @param string $text
   *
   * @return LocalizeInterface
   */
  public function setText($text);

  /**
   * Get translated message text.
   *
   * Substitutes variables and localizes message text with translations if any.
   *
   * @return string
   */
  public function getLocalized();
}
