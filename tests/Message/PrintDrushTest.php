<?php
namespace Drupal\drupal_helpers\Message;

class PrintDrushTest extends \PHPUnit_Framework_TestCase {
  /**
   * @var PrintDrush
   */
  protected $object;

  /**
   * @var callable
   */
  protected $mockPrintCall;

  /**
   * This method is called before a test is executed.
   */
  protected function setUp() {
    // Mock print callback dependency.
    $method = 'strPrint';
    $mock = $this->getMockBuilder(\stdClass::class)
      ->setMethods([$method])
      ->getMock();

    $this->mockPrintCall = [$mock, $method];
    $this->object = new PrintDrush($this->mockPrintCall);
  }

  /**
   * This method is called after a test is executed.
   */
  protected function tearDown() {
  }

  /**
   * @covers Drupal\drupal_helpers\Message\PrintDrush::printOut
   */
  public function testPrintOut() {
    $message = 'Hello World.';
    $indent = 2;
    $character = ' ';
    $prefix = '-- ';
    $suffix = '';
    $expected = '  -- Hello World.';

    /** @var \PHPUnit_Framework_MockObject_MockObject $watcher */
    list($watcher, $method) = $this->mockPrintCall;

    // Set expected behaviour.
    $watcher->expects($this->once())
      ->method($method)
      ->with($this->equalTo($expected));

    $this->object->setIndent($indent)
      ->setIndentChar($character)
      ->setPrefix($prefix)
      ->setSuffix($suffix)
      ->printOut($message);
  }
}
