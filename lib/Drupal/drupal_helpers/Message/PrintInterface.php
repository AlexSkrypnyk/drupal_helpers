<?php

namespace Drupal\drupal_helpers\Message;

/**
 * Interface PrintInterface
 *
 * @package Drupal\drupal_helpers\Message
 */
interface PrintInterface {
  /**
   * @param string $prefix
   *
   * @return PrintInterface
   */
  public function setPrefix($prefix);

  /**
   * @return string
   */
  public function getPrefix();

  /**
   * @return string
   */
  public function getSuffix();

  /**
   * @param string $suffix
   *
   * @return PrintInterface
   */
  public function setSuffix($suffix);

  /**
   * @return integer
   */
  public function getIndent();

  /**
   * @param integer $indent
   *
   * @return PrintInterface
   */
  public function setIndent($indent);

  /**
   * @return string
   */
  public function getIndentChar();

  /**
   * @param string $indentChar
   *
   * @return PrintInterface
   */
  public function setIndentChar($indentChar);

  /**
   * Output.
   *
   * @param $message
   *
   * @return $this
   */
  public function printOut($message);
}
