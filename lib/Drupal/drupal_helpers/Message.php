<?php

namespace Drupal\drupal_helpers;

use Drupal\drupal_helpers\Message\LocalizeDrupal;
use Drupal\drupal_helpers\Message\LocalizeDrush;
use Drupal\drupal_helpers\Message\LocalizeString;
use Drupal\drupal_helpers\Message\LocalizeInterface;
use Drupal\drupal_helpers\Message\PrintDrupal;
use Drupal\drupal_helpers\Message\PrintDrush;
use Drupal\drupal_helpers\Message\PrintString;
use Drupal\drupal_helpers\Message\PrintInterface;
use Drupal\drupal_helpers\Message\MessageInterface;

/**
 * Class Message.
 *
 * @package Drupal\drupal_helpers\Message
 */
class Message implements MessageInterface {

  /**
   * @var string
   */
  private $text = '';

  /**
   * @var LocalizeInterface
   */
  private $localizeProvider;

  /**
   * @var PrintInterface
   */
  private $printProvider;

  // ------------------------------------------------------------------
  //region Magic Methods

  /**
   * Message constructor.
   */
  public function __construct() {
    if (self::isCli() && function_exists('drush_print')) {
      $printProvider = new PrintDrush();
      $localizeProvider = new LocalizeDrush();
    }
    elseif (function_exists('drupal_set_message') && function_exists('get_t')) {
      $printProvider = new PrintDrupal();
      // Use get_t() in case we're running in an install context.
      $localizeProvider = new LocalizeDrupal(get_t());
    }
    else {
      $printProvider = new PrintString();
      $localizeProvider = new LocalizeString();
    }

    $this->printWith($printProvider)
      ->localizeWith($localizeProvider);
  }

  /**
   * Convert Message to a string.
   *
   * @return string
   */
  function __toString() {
    // Uncaught exceptions in __toString will result in a fatal error.
    try {
      $string = $this->getLocalized();
    }
    catch (\Exception $e) {
      $string = 'Cannot convert Message to string: ' . $e->getMessage();
    }
    return $string;
  }

  //endregion
  // ------------------------------------------------------------------
  //region Getters and Setters

  /**
   * @return string
   */
  public function getText() {
    return $this->text;
  }

  /**
   * @param string $text
   *
   * @return Message
   */
  public function setText($text) {
    $this->text = $text;
    return $this;
  }

  /**
   * @return array
   */
  public function getVars() {
    return $this->localizeProvider->getVars();
  }

  /**
   * @param array $vars
   *
   * @return Message
   */
  public function setVars($vars) {
    $this->localizeProvider->setVars($vars);
    return $this;
  }

  /**
   * @return Message
   */
  public function resetVars() {
    $this->localizeProvider->resetVars();
    return $this;
  }

  /**
   * @param string $prefix
   *
   * @return Message
   */
  public function setPrefix($prefix) {
    $this->printProvider->setPrefix($prefix);
    return $this;
  }

  /**
   * @return string
   */
  public function getPrefix() {
    return $this->printProvider->getPrefix();
  }

  /**
   * @param string $suffix
   *
   * @return Message
   */
  public function setSuffix($suffix) {
    $this->printProvider->setSuffix($suffix);
    return $this;
  }

  /**
   * @return string
   */
  public function getSuffix() {
    return $this->printProvider->getSuffix();
  }

  /**
   * @return integer
   */
  public function getIndent() {
    return $this->printProvider->getIndent();
  }

  /**
   * @param integer $indent
   *
   * @return Message
   */
  public function setIndent($indent) {
    $this->printProvider->setIndent($indent);
    return $this;
  }

  /**
   * @return string
   */
  public function getIndentChar() {
    return $this->printProvider->getIndentChar();
  }

  /**
   * @param string $indentChar
   *
   * @return Message
   */
  public function setIndentChar($indentChar) {
    $this->printProvider->setIndentChar($indentChar);
    return $this;
  }


  //endregion
  // ------------------------------------------------------------------

  /**
   * Detects a command-line interface environment.
   *
   * @return bool
   */
  public static function isCli() {
      return (!isset($_SERVER['SERVER_SOFTWARE']) && (php_sapi_name() == 'cli' || (is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0)));
  }

  /**
   * Detects a web server environment.
   *
   * @return bool
   */
  public static function isWeb() {
    return !self::isCli();
  }

  /**
   * Set the provider used to localize the message text.
   *
   * @param LocalizeInterface $localizeProvider
   *
   * @return Message
   */
  public function localizeWith(LocalizeInterface $localizeProvider) {
    if ($this->localizeProvider instanceof LocalizeInterface) {
      $vars = $this->localizeProvider->getVars();
      $text = $this->localizeProvider->getText();
      $localizeProvider->setVars($vars);
      $localizeProvider->setText($text);
    }
    $this->localizeProvider = $localizeProvider;

    return $this;
  }

  /**
   * Set the provider used to print the message text.
   *
   * @param PrintInterface $printProvider
   *
   * @return Message
   */
  public function printWith(PrintInterface $printProvider) {
    if ($this->printProvider instanceof PrintInterface) {
      $indent = $this->printProvider->getIndent();
      $character = $this->printProvider->getIndentChar();
      $prefix = $this->printProvider->getPrefix();
      $suffix = $this->printProvider->getSuffix();
      $printProvider->setIndent($indent);
      $printProvider->setIndentChar($character);
      $printProvider->setPrefix($prefix);
      $printProvider->setSuffix($suffix);
    }
    $this->printProvider = $printProvider;

    return $this;
  }

  /**
   * Print localized message text.
   *
   * @return $this
   */
  public function printOut() {
    $this->printProvider->printOut($this->getLocalized());

    return $this;
  }

  /**
   * Get translated message text.
   *
   * Substitutes variables and localizes message text with translations if any.
   *
   * @return string
   */
  public function getLocalized() {
    return $this->localizeProvider
      ->setText($this->getText())
      ->getLocalized();
  }
}
