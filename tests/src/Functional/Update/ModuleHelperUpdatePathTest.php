<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests force-removal of an orphaned module through the update path.
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
      __DIR__ . '/../../../fixtures/update/drupal_helpers_orphan.php',
    ];
  }

  /**
   * Tests that post-update hooks drive the Module helper.
   */
  public function testModuleOperationsViaPostUpdate(): void {
    $modules = \Drupal::config('core.extension')->get('module');
    $this->assertIsArray($modules);
    $this->assertArrayHasKey('drupal_helpers_test', $modules);

    $this->runUpdates();

    $this->assertSession()->pageTextContains("Uninstalled module 'drupal_helpers_test'");
    $this->assertSession()->pageTextContains("Force-removed orphaned module 'drupal_helpers_orphan'");

    \Drupal::configFactory()->reset();
    $modules = \Drupal::config('core.extension')->get('module');
    $this->assertIsArray($modules);
    $this->assertArrayNotHasKey('drupal_helpers_test', $modules);
    $this->assertArrayNotHasKey('drupal_helpers_orphan', $modules);
    $this->assertSame('drupal_helpers_orphan', \Drupal::state()->get('drupal_helpers_update_test_removed'));
  }

}
