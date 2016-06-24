<?php
namespace Drupal\drupal_helpers\Message;

class PrintStringTest extends \PHPUnit_Framework_TestCase {
  /**
   * @var PrintString
   */
  protected $object;

  /**
   * Sets up the fixture, for example, opens a network connection.
   * This method is called before a test is executed.
   */
  protected function setUp() {
    $this->object = new PrintString;
  }

  /**
   * Tears down the fixture, for example, closes a network connection.
   * This method is called after a test is executed.
   */
  protected function tearDown() {
  }

  /**
   * @covers Drupal\drupal_helpers\Message\PrintString::printOut
   * @todo   Implement testOutput().
   */
  public function testPrintOut() {
    $message = 'Hello World.';
    $indent = 3;
    $character = '.';
    $prefix = '>>';
    $suffix = '[end]';
    $expected = '...>>Hello World.[end]';
    ob_start();
    $this->object->setIndent($indent)
      ->setIndentChar($character)
      ->setPrefix($prefix)
      ->setSuffix($suffix)
      ->printOut($message);
    $actual = ob_get_contents();
    ob_end_clean();
    $this->assertEquals($expected, $actual);
  }
}
