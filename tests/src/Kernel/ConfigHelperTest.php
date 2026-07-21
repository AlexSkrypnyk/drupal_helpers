<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\drupal_helpers\Helpers\Config;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Kernel tests for the Config helper service.
 */
#[CoversClass(Config::class)]
#[RunTestsInSeparateProcesses]
class ConfigHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore DrupalPractice.Objects.StrictSchemaDisabled.StrictConfigSchema
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system'];

  /**
   * The config helper service.
   */
  protected Config $configHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);

    $this->configHelper = $this->container->get('drupal_helpers.config');
  }

  /**
   * Tests setting a config value.
   */
  public function testSet(): void {
    $this->configHelper->set('system.site', 'name', 'Test Site');

    $value = $this->config('system.site')->get('name');
    $this->assertEquals('Test Site', $value);
  }

  /**
   * Tests getting a config value.
   */
  public function testGet(): void {
    $this->configHelper->set('system.site', 'name', 'Get Test');

    $value = $this->configHelper->get('system.site', 'name');
    $this->assertEquals('Get Test', $value);
  }

  /**
   * Tests deleting a config object.
   */
  public function testDelete(): void {
    $this->configHelper->set('drupal_helpers.test_delete', 'key', 'value');

    $this->configHelper->delete('drupal_helpers.test_delete');

    $config = $this->config('drupal_helpers.test_delete');
    $this->assertTrue($config->isNew());
  }

  /**
   * Tests deleting a non-existent config produces a warning.
   */
  public function testDeleteNonExistent(): void {
    $this->configHelper->delete('drupal_helpers.nonexistent');

    $messages = $this->container->get('messenger')->messagesByType('warning');
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('drupal_helpers.nonexistent', (string) reset($messages));
  }

  /**
   * Tests importing a config from a module's config/install directory.
   */
  public function testImport(): void {
    // Delete existing config, then re-import from system module.
    $this->configHelper->delete('system.logging');

    $this->configHelper->import('system', 'system.logging');

    $config = $this->config('system.logging');
    $this->assertFalse($config->isNew());
    $this->assertEquals('hide', $config->get('error_level'));
  }

  /**
   * Tests importing a missing config file produces an error message.
   */
  public function testImportMissingFile(): void {
    $this->configHelper->import('drupal_helpers', 'drupal_helpers.does_not_exist');

    $messages = $this->container->get('messenger')->messagesByType('error');
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('not found', (string) reset($messages));
  }

  /**
   * Tests importing multiple configs at once.
   */
  public function testImportMultiple(): void {
    // Delete existing configs, then re-import from system module.
    $this->configHelper->delete('system.logging');
    $this->configHelper->delete('system.cron');

    $this->configHelper->importMultiple('system', [
      'system.logging',
      'system.cron',
    ]);

    $logging = $this->config('system.logging');
    $this->assertFalse($logging->isNew());
    $this->assertEquals('hide', $logging->get('error_level'));

    $cron = $this->config('system.cron');
    $this->assertFalse($cron->isNew());
    $this->assertTrue($cron->get('logging'));
  }

  /**
   * Tests setting the front page path.
   */
  public function testSetFrontPage(): void {
    $this->configHelper->setFrontPage('/node/1');

    $value = $this->config('system.site')->get('page.front');
    $this->assertEquals('/node/1', $value);
  }

  /**
   * Tests that a front page path without a leading slash gets normalized.
   */
  public function testSetFrontPageWithoutLeadingSlash(): void {
    $this->configHelper->setFrontPage('node/2');

    $value = $this->config('system.site')->get('page.front');
    $this->assertEquals('/node/2', $value);
  }

  /**
   * Tests that a guarded set applies when the live value matches.
   */
  public function testSetIfExpectedMatch(): void {
    $this->configHelper->set('system.site', 'name', 'Original');

    $result = $this->configHelper->setIfExpected('system.site', 'name', 'Original', 'Updated');

    $this->assertEquals('Updated', $this->config('system.site')->get('name'));
    $this->assertStringContainsString('Set', $result);
    $this->assertStringContainsString('system.site', $result);
  }

  /**
   * Tests that a guarded set is skipped and reported when the value differs.
   */
  public function testSetIfExpectedMismatch(): void {
    $this->configHelper->set('system.site', 'name', 'Actual');

    $result = $this->configHelper->setIfExpected('system.site', 'name', 'Expected', 'Updated');

    // The change is not applied.
    $this->assertEquals('Actual', $this->config('system.site')->get('name'));

    // A warning is surfaced that reports the difference.
    $warnings = $this->container->get('messenger')->messagesByType('warning');
    $this->assertNotEmpty($warnings);
    $warning = (string) reset($warnings);
    $this->assertStringContainsString('Actual', $warning);
    $this->assertStringContainsString('Expected', $warning);

    $this->assertStringContainsString('Actual', $result);
    $this->assertStringContainsString('Expected', $result);
  }

  /**
   * Tests that re-running once the value is set is a no-op reporting success.
   */
  public function testSetIfExpectedAlreadyApplied(): void {
    $this->configHelper->set('system.site', 'name', 'Target');

    $result = $this->configHelper->setIfExpected('system.site', 'name', 'Anything', 'Target');

    $this->assertEquals('Target', $this->config('system.site')->get('name'));
    $this->assertStringContainsString('already set', $result);

    // An idempotent no-op is a success, not a warning.
    $warnings = $this->container->get('messenger')->messagesByType('warning');
    $this->assertEmpty($warnings);
  }

  /**
   * Tests that the guard applies and skips for nested config structures.
   */
  public function testSetIfExpectedNestedStructure(): void {
    $this->configHelper->set('drupal_helpers.test_guard', 'data', ['a' => 1, 'b' => 2]);

    // A match on the whole nested structure applies the change.
    $this->configHelper->setIfExpected('drupal_helpers.test_guard', 'data', ['a' => 1, 'b' => 2], ['a' => 1, 'b' => 3]);
    $this->assertEquals(['a' => 1, 'b' => 3], $this->config('drupal_helpers.test_guard')->get('data'));

    // A mismatch on a nested structure leaves the value untouched and reports
    // both values in full as inline YAML.
    $result = $this->configHelper->setIfExpected('drupal_helpers.test_guard', 'data', ['a' => 9], ['a' => 1, 'b' => 99]);
    $this->assertEquals(['a' => 1, 'b' => 3], $this->config('drupal_helpers.test_guard')->get('data'));
    $this->assertStringContainsString('{ a: 9 }', $result);
    $this->assertStringContainsString('{ a: 1, b: 3 }', $result);
  }

  /**
   * Tests that guarding an unset key reports the missing current value.
   */
  public function testSetIfExpectedMissingKey(): void {
    $result = $this->configHelper->setIfExpected('drupal_helpers.test_guard', 'absent', 'Expected', 'Updated');

    $this->assertNull($this->config('drupal_helpers.test_guard')->get('absent'));
    $this->assertStringContainsString('NULL', $result);
  }

}
