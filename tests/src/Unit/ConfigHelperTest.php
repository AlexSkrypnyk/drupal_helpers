<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Unit;

use Drupal\Core\Config\Config as CoreConfig;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\drupal_helpers\Helpers\Config;
use Drupal\drupal_helpers\Report\Reporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Config helper service.
 */
#[CoversClass(Config::class)]
class ConfigHelperTest extends TestCase {

  /**
   * The config factory mock.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $configFactory;

  /**
   * The messenger mock.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $messenger;

  /**
   * The reporter forwarding to the messenger mock.
   */
  protected Reporter $reporter;

  /**
   * The translation stub.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $translation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->reporter = new Reporter($this->createMock(LoggerChannelInterface::class), $this->messenger);

    $this->translation = $this->createMock(TranslationInterface::class);
    $this->translation->method('translateString')->willReturnCallback(fn($input): string => (string) $input->getUntranslatedString());
  }

  /**
   * Tests that the change applies when the live value matches the expected one.
   */
  public function testSetIfExpectedMatchApplies(): void {
    $editable = $this->createMock(CoreConfig::class);
    $editable->method('get')->with('name')->willReturn('Old');
    $editable->expects($this->once())->method('set')->with('name', 'New')->willReturnSelf();
    $editable->expects($this->once())->method('save')->willReturnSelf();
    $this->configFactory->method('getEditable')->with('system.site')->willReturn($editable);

    $this->messenger->expects($this->once())->method('addStatus');

    $result = $this->createConfigHelper()->setIfExpected('system.site', 'name', 'Old', 'New');

    $this->assertStringContainsString('Set', $result);
    $this->assertStringContainsString('system.site', $result);
  }

  /**
   * Tests that a mismatch skips the write and warns with the difference.
   */
  public function testSetIfExpectedMismatchSkips(): void {
    $editable = $this->createMock(CoreConfig::class);
    $editable->method('get')->with('name')->willReturn('Actual');
    $editable->expects($this->never())->method('set');
    $editable->expects($this->never())->method('save');
    $this->configFactory->method('getEditable')->willReturn($editable);

    $this->messenger->expects($this->once())->method('addWarning');
    $this->messenger->expects($this->never())->method('addStatus');

    $result = $this->createConfigHelper()->setIfExpected('system.site', 'name', 'Expected', 'New');

    $this->assertStringContainsString('Actual', $result);
    $this->assertStringContainsString('Expected', $result);
  }

  /**
   * Tests that re-running once the value is set is an idempotent success.
   */
  public function testSetIfExpectedAlreadyApplied(): void {
    $editable = $this->createMock(CoreConfig::class);
    $editable->method('get')->with('name')->willReturn('New');
    $editable->expects($this->never())->method('set');
    $editable->expects($this->never())->method('save');
    $this->configFactory->method('getEditable')->willReturn($editable);

    $this->messenger->expects($this->once())->method('addStatus');
    $this->messenger->expects($this->never())->method('addWarning');

    $result = $this->createConfigHelper()->setIfExpected('system.site', 'name', 'Old', 'New');

    $this->assertStringContainsString('already set', $result);
  }

  /**
   * Tests that mismatch messages format each value type readably.
   */
  #[DataProvider('dataProviderSetIfExpectedFormatsMismatchValue')]
  public function testSetIfExpectedFormatsMismatchValue(mixed $current, string $needle): void {
    $editable = $this->createMock(CoreConfig::class);
    $editable->method('get')->willReturn($current);
    $editable->expects($this->never())->method('set');
    $editable->expects($this->never())->method('save');
    $this->configFactory->method('getEditable')->willReturn($editable);

    $this->messenger->expects($this->once())->method('addWarning');

    $result = $this->createConfigHelper()->setIfExpected('config', 'key', '__expected__', '__value__');

    $this->assertStringContainsString($needle, $result);
    // The summary must stay single-line regardless of the value's content.
    $this->assertStringNotContainsString("\n", $result);
  }

  /**
   * Data provider for testSetIfExpectedFormatsMismatchValue().
   *
   * @return array<string, array{mixed, string}>
   *   Each case is the live value and the token expected in the message.
   */
  public static function dataProviderSetIfExpectedFormatsMismatchValue(): array {
    return [
      'boolean true' => [TRUE, 'TRUE'],
      'boolean false' => [FALSE, 'FALSE'],
      'null' => [NULL, 'NULL'],
      'integer' => [42, '42'],
      'float' => [3.5, '3.5'],
      'string' => ['zzz', 'zzz'],
      'array' => [['x' => 7], 'x: 7'],
      'multiline string' => ["first\nsecond", 'first\nsecond'],
    ];
  }

  /**
   * Build a Config helper with the mocked dependencies.
   */
  protected function createConfigHelper(): Config {
    $config = new Config($this->configFactory, $this->createMock(StorageInterface::class), $this->createMock(ModuleExtensionList::class), $this->reporter);
    $config->setStringTranslation($this->translation);

    return $config;
  }

}
