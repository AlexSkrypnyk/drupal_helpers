<?php

namespace Drupal\drupal_helpers\Message;


class PrintDrush extends PrintString implements PrintInterface {
  /**
   * PrintDrush constructor.
   *
   * @param callable $printCall
   *  Callable class method or function name used to printOut. Use
   *  format accepted by call_user_func().
   */
  public function __construct($printCall = NULL) {
    if (!isset($printCall)) {
      $printCall = 'drush_print';
    }
    parent::__construct($printCall);
    $this->setIndent(2)->setPrefix('-- ')->setSuffix(PHP_EOL);
  }
}
