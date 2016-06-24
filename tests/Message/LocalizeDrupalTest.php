<?php
namespace Drupal\drupal_helpers\Message;

require_once __DIR__ . '/LocalizeStringTest.php';

class LocalizeDrupalTest extends LocalizeStringTest {
  /**
   * @var LocalizeDrupal
   */
  protected $object;

  /**
   * @var callable
   */
  protected $mockLocalizeCall;

  /**
   * This method is called before a test is executed.
   */
  protected function setUp() {
    // Mock localize callback dependency.
    $method = 'strtr';
    $mock = $this->getMockBuilder(\stdClass::class)
      ->setMethods([$method])
      ->getMock();

    $this->mockLocalizeCall = [$mock, $method];

    $this->object = new LocalizeDrush($this->mockLocalizeCall);
  }

  /**
   * Tears down the fixture, for example, closes a network connection.
   * This method is called after a test is executed.
   */
  protected function tearDown() {
  }

  /**
   * @covers       Drupal\drupal_helpers\Message\LocalizeDrupal::getLocalized
   * @dataProvider localizeProvider
   *
   * @param string $text
   * @param mixed $vars
   * @param string $expected
   */
  public function testGetLocalized($text, $vars, $expected) {
    /** @var \PHPUnit_Framework_MockObject_MockObject $watcher */
    list($watcher, $method) = $this->mockLocalizeCall;

    // Set expected behaviour.
    $watcher->expects($this->once())
      ->method($method)
      ->with($this->equalTo($text), $this->equalTo($vars))
      ->willReturn($expected);

    $this->object->setText($text)->setVars($vars)->getLocalized();
  }

}
