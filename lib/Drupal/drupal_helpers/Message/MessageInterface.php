<?php

namespace Drupal\drupal_helpers\Message;

/**
 * Interface MessageInterface.
 *
 * @package Drupal\drupal_helpers\Message
 */
interface MessageInterface {
  /**
   * @return string
   */
  public function getText();

  /**
   * @param string $text
   *
   * @return MessageInterface
   */
  public function setText($text);

  /**
   * @return array
   */
  public function getVars();

  /**
   * @param array $vars
   *
   * @return MessageInterface
   */
  public function setVars($vars);

  /**
   * @return MessageInterface
   */
  public function resetVars();

  /**
   * @param string $cliPrefix
   *
   * @return MessageInterface
   */
  public function setPrefix($cliPrefix);

  /**
   * @return string
   */
  public function getPrefix();

  /**
   * @param string $suffix
   *
   * @return MessageInterface
   */
  public function setSuffix($suffix);

  /**
   * @return string
   */
  public function getSuffix();

  /**
   * @return integer
   */
  public function getIndent();

  /**
   * @param integer $cliIndent
   *
   * @return MessageInterface
   */
  public function setIndent($cliIndent);

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
   * Detects a command-line interface environment.
   *
   * @return bool
   */
  public static function isCli();

  /**
   * Set the provider used to Print the message text.
   *
   * @param PrintInterface $printProvider
   *
   * @return MessageInterface
   */
  public function printWith(PrintInterface $printProvider);

  /**
   * Set the provider used to LocalizeString the message text.
   *
   * @param LocalizeInterface $localizeProvider
   *
   * @return MessageInterface
   */
  public function localizeWith(LocalizeInterface $localizeProvider);

  /**
   * Print localized message text.
   *
   * @return $this
   */
  public function printOut();

  /**
   * Get translated message text.
   *
   * Substitutes variables and localizes message text with translations if any.
   *
   * @return string
   */
  public function getLocalized();
}
