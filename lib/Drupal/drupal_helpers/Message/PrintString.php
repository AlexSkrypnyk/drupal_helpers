<?php

namespace Drupal\drupal_helpers\Message;

/**
 * Class PrintString.
 *
 * @package Drupal\drupal_helpers\Message
 */
class PrintString implements PrintInterface {

  /**
   * @var callable
   */
  private $printCall = [self::class, 'printCall'];

  /**
   * @var integer
   */
  private $indent = 2;
  
  /**
   * @var string
   */
  private $indentChar = ' ';
  
  /**
   * @var string
   */
  private $prefix = '-- ';

  /**
   * @var string
   */
  private $suffix = '';

  // ------------------------------------------------------------------
  //region Magic Methods

  /**
   * PrintString constructor.
   * 
   * @param callable $printCall
   *  Callable class method or function name used to printOut. Use
   *  format accepted by call_user_func().
   */
  public function __construct($printCall = NULL) {
    $this->setPrintCall($printCall);
  }

  //endregion
  // ------------------------------------------------------------------
  //region Getters and Setters

  /**
   * @return callable
   */
  private function getPrintCall() {
    return $this->printCall;
  }

  /**
   * @param callable $printCall
   *
   * @return PrintInterface
   */
  private function setPrintCall($printCall) {
    if (is_callable($printCall)) {
      $this->printCall = $printCall;
    }
    return $this;
  }

  /**
   * @param string $prefix
   *
   * @return PrintInterface
   */
  public function setPrefix($prefix) {
    $this->prefix = (string) $prefix;
    return $this;
  }

  /**
   * @return string
   */
  public function getPrefix() {
    return $this->prefix;
  }

  /**
   * @return integer
   */
  public function getIndent() {
    return $this->indent;
  }

  /**
   * @return string
   */
  public function getSuffix() {
    return $this->suffix;
  }

  /**
   * @param string $suffix
   *
   * @return PrintString
   */
  public function setSuffix($suffix) {
    $this->suffix = $suffix;
    return $this;
  }

  /**
   * @param integer $indent
   *
   * @return PrintInterface
   */
  public function setIndent($indent) {
    $this->indent = (int) $indent;
    return $this;
  }

  /**
   * @return string
   */
  public function getIndentChar() {
    return $this->indentChar;
  }

  /**
   * @param string $indentChar
   *
   * @return PrintInterface
   */
  public function setIndentChar($indentChar) {
    $this->indentChar = $indentChar;
    return $this;
  }

  //endregion
  // ------------------------------------------------------------------

  /**
   * Print Callable.
   *
   * Bridge to language construct print for use with variable functions. Makes
   * print callable.
   *
   * @param $string
   */
  private static function printCall($string) {
    print $string;
  }

  /**
   * Output.
   *
   * @param $message
   *
   * @return PrintInterface
   */
  public function printOut($message) {
    $print = $this->getPrintCall();
    $indent = str_repeat($this->getIndentChar(), $this->getIndent());
    $prefix = $this->getPrefix();
    $suffix = $this->getSuffix();
    call_user_func($print, "{$indent}{$prefix}{$message}{$suffix}");

    return $this;
  }
}
