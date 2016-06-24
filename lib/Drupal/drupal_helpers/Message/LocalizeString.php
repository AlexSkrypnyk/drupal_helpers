<?php

namespace Drupal\drupal_helpers\Message;

/**
 * Class LocalizeString.
 *
 * @package Drupal\drupal_helpers\Message
 */
class LocalizeString implements LocalizeInterface {

  /**
   * @var string
   */
  private $text = '';

  /**
   * @var array
   */
  private $vars = [];

  /**
   * @var callable
   */
  private $localizeCall = 'strtr';

  // ------------------------------------------------------------------
  //region Magic Methods

  /**
   * LocalizeString constructor.
   *
   * @param callable $localizeCall
   *  Callable class method or function name used to getLocalized. Use 
   *  format accepted by call_user_func().
   */
  public function __construct($localizeCall = NULL) {
    $this->setLocalizeCall($localizeCall);
  }

  //endregion
  // ------------------------------------------------------------------
  //region Getters and Setters

  /**
   * Get Variables.
   *
   * @return array
   *  Key, Value pairs for variable name, variable value.
   */
  public function getVars() {
    return $this->vars;
  }

  /**
   * Set Variables.
   *
   * Variables are added without removing existing variables. New variable
   * values overwrite old values.
   *
   * @param array $vars
   *  Key, Value pairs for variable name, variable value.
   *
   * @return LocalizeInterface
   */
  public function setVars($vars) {
    if (is_array($vars)) {
      $this->vars = $vars + $this->vars;
    }
    return $this;
  }

  /**
   * Reset Variables.
   *
   * Clears all set variables.
   *
   * @return LocalizeInterface
   */
  public function resetVars() {
    $this->vars = [];
    return $this;
  }

  /**
   * @return string
   */
  public function getText() {
    return $this->text;
  }

  /**
   * @param string $text
   *
   * @return LocalizeInterface
   */
  public function setText($text) {
    $this->text = $text;
    return $this;
  }

  /**
   * @return callable
   */
  private function getLocalizeCall() {
    return $this->localizeCall;
  }

  /**
   * @param callable $localizeCall
   *
   * @return LocalizeInterface
   */
  private function setLocalizeCall($localizeCall) {
    if (is_callable($localizeCall)) {
      $this->localizeCall = $localizeCall;
    }
    return $this;
  }

  //endregion
  // ------------------------------------------------------------------

  /**
   * Get translated message text.
   *
   * Substitutes variables and localizes text using available translations.
   *
   * @return string
   */
  public function getLocalized() {
    $t = $this->getLocalizeCall();
    $string = $this->getText();
    $vars = $this->getVars();
    return call_user_func($t, $string, $vars);
  }
}
