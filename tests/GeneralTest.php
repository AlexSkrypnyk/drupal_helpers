<?php
namespace Drupal\drupal_helpers;

class GeneralTest extends \PHPUnit_Framework_TestCase {
  /**
   * @var General
   */
  protected $object;

  /**
   * This method is called before a test is executed.
   */
  protected function setUp() {
    $this->object = new General;
  }

  /**
   * This method is called after a test is executed.
   */
  protected function tearDown() {
  }

  /**
   * @covers Drupal\drupal_helpers\General::messageSet
   */
  public function testMessageSet() {
    $message = 'Hello World.';
    $prefix = '> ';
    $indent = 3;
    $expected = '   > Hello World.';
    ob_start();
    General::messageSet($message, $prefix, $indent);
    $actual = ob_get_contents();
    ob_end_clean();
    $this->assertEquals($expected, $actual);
  }
}
