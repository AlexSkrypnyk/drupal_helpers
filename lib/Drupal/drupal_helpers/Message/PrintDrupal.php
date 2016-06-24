<?php

namespace Drupal\drupal_helpers\Message;


class PrintDrupal extends PrintString implements PrintInterface {
  /**
   * PrintDrupal constructor.
   *
   * @param callable $printCall
   *  Callable class method or function name used to printOut. Use
   *  format accepted by call_user_func().
   */
  public function __construct($printCall = NULL) {
    if (!isset($printCall)) {
      $printCall = 'drupal_set_message';
    }
    parent::__construct($printCall);
    $this->setIndent(0)->setPrefix('')->setSuffix('<BR />' . PHP_EOL);
  }
}
