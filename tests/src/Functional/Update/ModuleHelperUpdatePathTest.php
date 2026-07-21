<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Module helper driven from a post-update hook.
 */
#[Group('drupal_helpers')]
#[CoversNothing]
#[RunTestsInSeparateProcesses]
class ModuleHelperUpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      $this->root . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/drupal_helpers_modules.php',
    ];
  }

  /**
   * Tests that a post-update hook uninstalls a module via the helper.
   */
  public function testModuleUninstalledViaPostUpdate(): void {
    $modules = \Drupal::config('core.extension')->get('module');
    $this->assertIsArray($modules);
    $this->assertArrayHasKey('drupal_helpers_test', $modules);

    $this->runUpdates();

    $this->assertSession()->pageTextContains("Uninstalled module 'drupal_helpers_test'");

    \Drupal::configFactory()->reset();
    $modules = \Drupal::config('core.extension')->get('module');
    $this->assertIsArray($modules);
    $this->assertArrayNotHasKey('drupal_helpers_test', $modules);
  }

}
