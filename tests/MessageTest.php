<?php
namespace Drupal\drupal_helpers;

use Drupal\drupal_helpers\Message\LocalizeString;
use Drupal\drupal_helpers\Message\PrintString;

class MessageTest extends \PHPUnit_Framework_TestCase {
  /**
   * @var Message
   */
  protected $object;

  // ------------------------------------------------------------------
  //region Setup Fixtures

  /**
   * This method is called before a test is executed.
   */
  protected function setUp() {
    // Inject dependencies.
    $this->object = new Message();
    $this->object
      ->printWith(new PrintString(['MockPrintProvider','printString']))
      ->localizeWith(new LocalizeString());
  }

  /**
   * This method is called after a test is executed.
   */
  protected function tearDown() {
    // Clean-up globals.
    unset($_SERVER['SERVER_SOFTWARE']);
  }

  //endregion
  // ------------------------------------------------------------------
  //region Data Providers

  public function textProvider() {
    return array(
      ['Hello world'],
      ['Hello @world'],
      ['@greeting !world'],
    );
  }

  public function prefixProvider() {
    return array(
      ['-- '],
      ['* '],
      ["\t"],
    );
  }

  public function varsProvider() {
    return array(
      ['foobar', []],
      [['@world' => 'Earth'], ['@world' => 'Earth']],
      [
        ['@greeting' => "G'day", '@world' => 'Earth'],
        ['@greeting' => "G'day", '@world' => 'Earth'],
      ],
    );
  }

  public function varsTwiceProvider() {
    return array(
      ['foo', 'bar', []],
      ['foo', ['bar' => 'baz'], ['bar' => 'baz']],
      [['foo' => 'bar'], 'baz', ['foo' => 'bar']],
      [['@world' => 'Earth'], [], ['@world' => 'Earth']],
      [
        ['@greeting' => 'Wassup'],
        ['@world' => 'Mars'],
        ['@greeting' => 'Wassup', '@world' => 'Mars'],
      ],
      [
        ['@greeting' => 'Wassup', '@world' => 'Mars'],
        ['@foobar' => 'Baz'],
        ['@greeting' => 'Wassup', '@world' => 'Mars', '@foobar' => 'Baz'],
      ],
    );
  }

  public function localizeProvider() {
    return array(
      ['Hello @world', ['!world' => 'Earth'], 'Hello @world'],
      ['Hello !world', ['!world' => 'Earth'], 'Hello Earth'],
      [
        '@greeting @world',
        ['@greeting' => "G'day", '@world' => 'Earth'],
        "G'day Earth",
      ],
    );
  }

  //endregion
  // ------------------------------------------------------------------

  /**
   * @covers Drupal\drupal_helpers\General\Message::getText
   */
  public function testGetText() {
    $this->assertEmpty($this->object->getText());
    $this->assertInternalType('string', $this->object->getText());
  }

  /**
   * @covers       Drupal\drupal_helpers\General\Message::setText
   * @covers       Drupal\drupal_helpers\General\Message::getText
   * @dataProvider textProvider
   *
   * @param $text
   */
  public function testSetTextGetText($text) {
    $this->assertEquals($text, $this->object->setText($text)->getText());
  }

  /**
   * @covers Drupal\drupal_helpers\General\Message::getVars
   */
  public function testGetVars() {
    $this->assertEmpty($this->object->getVars());
    $this->assertInternalType('array', $this->object->getVars());
  }

  /**
   * @covers       Drupal\drupal_helpers\General\Message::setVars
   * @dataProvider varsProvider
   *
   * @param mixed $vars
   * @param array $expected
   */
  public function testSetVars($vars, $expected) {
    $this->assertEquals($expected, $this->object->setVars($vars)->getVars());
  }

  /**
   * @covers       Drupal\drupal_helpers\General\Message::setVars
   * @dataProvider varsTwiceProvider
   *
   * @param array $varsOne
   * @param array $varsTwo
   * @param array $expected
   *
   * @internal param mixed $vars
   */
  public function testSetVarsTwice($varsOne, $varsTwo, $expected) {
    $this->object->setVars($varsOne);
    $this->object->setVars($varsTwo);
    $this->assertEquals($expected, $this->object->getVars());
  }

  /**
   * @covers Drupal\drupal_helpers\General\Message::resetVars
   */
  public function testResetVars() {
    $this->object->setVars(['foo' => 'bar'])->resetVars();
    $this->assertEmpty($this->object->getVars());
    $this->assertInternalType('array', $this->object->getVars());
  }

  /**
   * @covers       Drupal\drupal_helpers\General\Message::setPrefix
   * @covers       Drupal\drupal_helpers\General\Message::getPrefix
   * @dataProvider prefixProvider
   *
   * @param string $prefix
   */
  public function testGetSetCliPrefix($prefix) {
    $this->object->setPrefix($prefix);
    $this->assertEquals($prefix, $this->object->getPrefix());
  }

  /**
   * @covers Drupal\drupal_helpers\General\Message::getIndent
   * @covers Drupal\drupal_helpers\General\Message::setIndent
   * @todo   Implement testGetCliIndent().
   */
  public function testGetSetCliIndent() {
    $this->object->setIndent(5);
    $this->assertInternalType('integer', $this->object->getIndent());
    $this->assertEquals(5, $this->object->getIndent());
  }

  /**
   * @covers Drupal\drupal_helpers\General\Message::__toString
   */
  public function test__toString() {
    $this->object
      ->setText('Hello @world')
      ->setVars(['@world' => 'Mars']);
    $this->assertEquals('Hello Mars', (string) $this->object);
  }

  /**
   * @covers Drupal\drupal_helpers\General\Message::isCli
   */
  public function testIsCli() {
    $this->assertTrue($this->object->isCli());
  }

  /**
   * @covers Drupal\drupal_helpers\General\Message::isWeb
   * @backupGlobals disabled
   */
  public function testIsWeb() {
    // Simulate web server in global state.
    $_SERVER['SERVER_SOFTWARE'] = 'MockWebSAPI';
    $this->assertTrue($this->object->isWeb());
  }

  /**
   * @covers Drupal\drupal_helpers\General\Message::output
   */
  public function testPrint() {
    // Mock print callback dependency.
    $watcher = $this->getMockBuilder(\stdClass::class)
      ->setMethods(['printString'])
      ->getMock();

    // Inject dependency.
    $printProvider = new PrintString([$watcher, 'printString']);

    // Set expected behaviour.
    $watcher->expects($this->once())->method('printString');

    $this->object->printWith($printProvider)->setText('Hello world')->printOut();
  }

  /**
   * @covers       Drupal\drupal_helpers\General\Message::getTranslated
   * @dataProvider localizeProvider
   *
   * @param string $text
   * @param mixed $vars
   * @param string $expected
   */
  public function testGetLocalized($text, $vars, $expected) {
    $this->object->setText($text)->setVars($vars);
    $this->assertEquals($expected, $this->object->getLocalized());
  }

  /**
   * @covers Drupal\drupal_helpers\General\Message::localizeWith
   */
  public function testLocalizeWith() {
    $text = 'Hello !world.';
    $vars = ['!world' => 'Earth'];

    // Mock localize callback dependency.
    $watcher = $this->getMockBuilder(\stdClass::class)
      ->setMethods(['strtr'])
      ->getMock();

    // Inject dependency.
    $provider = new LocalizeString([$watcher, 'strtr']);

    // Set expected behaviour.
    $watcher->expects($this->once())->method('strtr');

    $this->object->localizeWith($provider)
      ->setText($text)
      ->setVars($vars)
      ->getLocalized();
  }

  /**
   * @covers Drupal\drupal_helpers\General\Message::localizeWith
   */
  public function testLocalizeWithChangeThenGetText() {
    $text = 'Hello !world.';
    $vars = ['!world' => 'Earth'];

    // Mock print callback dependency.
    $watcher = $this->getMockBuilder(\stdClass::class)
      ->setMethods(['strtr'])
      ->getMock();

    // Inject dependency.
    $providerOne = new LocalizeString();
    $providerTwo = new LocalizeString();

    $this->object->localizeWith($providerOne)
      ->setText($text)
      ->setVars($vars);

    $this->object->localizeWith($providerTwo);
    $this->assertEquals($text, $this->object->getText());
  }

  /**
   * @covers Drupal\drupal_helpers\General\Message::localizeWith
   */
  public function testLocalizeWithChangeProvider() {
    $text = 'Hello !world.';
    $vars = ['!world' => 'Earth'];

    // Mock localize callback dependency.
    $method = 'strTr';
    $watcher = $this->getMockBuilder(\stdClass::class)
      ->setMethods([$method])
      ->getMock();

    // Inject dependency.
    $providerOne = new LocalizeString();
    $providerTwo = new LocalizeString([$watcher, $method]);

    // Set expected behaviour.
    $watcher->expects($this->once())
      ->method($method)
      ->with($this->equalTo($text), $this->equalTo($vars));

    // Set Provider One.
    $this->object->localizeWith($providerOne)
      ->setVars($vars)
      ->setText($text);

    // Change to Provider Two without losing text or vars.
    $this->object->localizeWith($providerTwo)
      ->getLocalized();
  }

  /**
   * @covers Drupal\drupal_helpers\General\Message::printWith
   */
  public function testPrintWithIndent() {
    $indent = 5;
    $char = '.';
    $prefix = '';
    $suffix = '';
    $message = 'Hello World.';

    $expected = '.....Hello World.';

    // Mock print callback dependency.
    $watcher = $this->getMockBuilder(\stdClass::class)
      ->setMethods(['strPrint'])
      ->getMock();

    // Inject dependency.
    $provider = new PrintString([$watcher, 'strPrint']);

    // Set expected behaviour.
    $watcher->expects($this->once())
      ->method('strPrint')
      ->with($this->equalTo($expected));

    $this->object->printWith($provider)
      ->setIndent($indent)
      ->setIndentChar($char)
      ->setPrefix($prefix)
      ->setSuffix($suffix)
      ->resetVars()
      ->setText($message)
      ->printOut();
  }

  /**
   * @covers Drupal\drupal_helpers\General\Message::printWith
   */
  public function testPrintWithPrefixSuffix() {
    $indent = 0;
    $char = '';
    $prefix = '<FOO>';
    $suffix = '</FOO>';
    $message = 'Hello World.';

    $expected = '<FOO>Hello World.</FOO>';

    // Mock print callback dependency.
    $watcher = $this->getMockBuilder(\stdClass::class)
      ->setMethods(['strPrint'])
      ->getMock();

    // Inject dependency.
    $provider = new PrintString([$watcher, 'strPrint']);

    // Set expected behaviour.
    $watcher->expects($this->once())
      ->method('strPrint')
      ->with($this->equalTo($expected));

    $this->object->printWith($provider)
      ->setIndent($indent)
      ->setIndentChar($char)
      ->setPrefix($prefix)
      ->setSuffix($suffix)
      ->resetVars()
      ->setText($message)
      ->printOut();
  }

  /**
   * @covers Drupal\drupal_helpers\General\Message::printWith
   */
  public function testPrintWithIndentBeforePrefix() {
    $indent = 5;
    $char = '-';
    $prefix = '<FOO />';
    $suffix = '';
    $message = 'Hello World.';

    $expected = '-----<FOO />Hello World.';

    // Mock print callback dependency.
    $method = 'strPrint';
    $watcher = $this->getMockBuilder(\stdClass::class)
      ->setMethods([$method])
      ->getMock();

    // Inject dependency.
    $provider = new PrintString([$watcher, $method]);

    // Set expected behaviour.
    $watcher->expects($this->once())
      ->method($method)
      ->with($this->equalTo($expected));

    $this->object->printWith($provider)
      ->setIndent($indent)
      ->setIndentChar($char)
      ->setPrefix($prefix)
      ->setSuffix($suffix)
      ->resetVars()
      ->setText($message)
      ->printOut();
  }

  /**
   * @covers Drupal\drupal_helpers\General\Message::printWith
   */
  public function testPrintWithVars() {
    $indent = 0;
    $char = '';
    $prefix = '';
    $suffix = '';
    $vars = ['@world' => 'Earth'];
    $message = 'Hello @world.';

    $expected = 'Hello Earth.';

    // Mock print callback dependency.
    $watcher = $this->getMockBuilder(\stdClass::class)
      ->setMethods(['strPrint'])
      ->getMock();

    // Inject dependency.
    $provider = new PrintString([$watcher, 'strPrint']);

    // Set expected behaviour.
    $watcher->expects($this->once())
      ->method('strPrint')
      ->with($this->equalTo($expected));

    $this->object->printWith($provider)
      ->setIndent($indent)
      ->setIndentChar($char)
      ->setPrefix($prefix)
      ->setSuffix($suffix)
      ->setVars($vars)
      ->setText($message)
      ->printOut();
  }

  /**
   * @covers Drupal\drupal_helpers\General\Message::printWith
   */
  public function testPrintWithChangeProvider() {
    $indent = 3;
    $character = '.';
    $prefix = '--';
    $suffix = '[end]';
    $message = 'Hello World.';
    
    $expected = '...--Hello World.[end]';

    // Mock print callback dependency.
    $method = 'strPrint';
    $watcher = $this->getMockBuilder(\stdClass::class)
      ->setMethods([$method])
      ->getMock();

    // Inject dependency.
    $providerOne = new PrintString();
    $providerTwo = new PrintString([$watcher, $method]);

    // Set expected behaviour.
    $watcher->expects($this->once())
      ->method($method)
      ->with($this->equalTo($expected));

    $this->object->printWith($providerOne)
      ->setIndent($indent)
      ->setIndentChar($character)
      ->setPrefix($prefix)
      ->setSuffix($suffix)
      ->setText($message);

    $this->object->printWith($providerTwo)->printOut();
  }
}
