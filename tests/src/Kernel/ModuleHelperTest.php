<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use Drupal\Core\Update\UpdateHookRegistry;
use Drupal\drupal_helpers\Helper;
use Drupal\drupal_helpers\Helpers\Module;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the Module helper service.
 */
#[CoversClass(Module::class)]
#[RunTestsInSeparateProcesses]
class ModuleHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Uninstalling modules requires the users_data table to exist.
    $this->installSchema('user', ['users_data']);
  }

  /**
   * Tests installing a module.
   */
  public function testInstall(): void {
    $this->assertFalse($this->moduleHandler()->moduleExists('drupal_helpers_test'));

    $result = Helper::module()->install('drupal_helpers_test');

    $this->assertTrue($this->moduleHandler()->moduleExists('drupal_helpers_test'));
    $this->assertStringContainsString("Installed module 'drupal_helpers_test'", $result);
  }

  /**
   * Tests that installing a module also enables its dependencies.
   */
  public function testInstallResolvesDependencies(): void {
    $result = Helper::module()->install('drupal_helpers_test_dependent');

    $this->assertTrue($this->moduleHandler()->moduleExists('drupal_helpers_test_dependent'));
    $this->assertTrue($this->moduleHandler()->moduleExists('drupal_helpers_test'));
    $this->assertStringContainsString('dependencies: drupal_helpers_test', $result);
  }

  /**
   * Tests that installing an already-enabled module is a no-op.
   */
  public function testInstallAlreadyEnabled(): void {
    Helper::module()->install('drupal_helpers_test');

    $result = Helper::module()->install('drupal_helpers_test');

    $this->assertStringContainsString('already enabled', $result);
  }

  /**
   * Tests that installing a module with no code throws.
   */
  public function testInstallMissingModuleThrows(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("Module 'drupal_helpers_nonexistent' cannot be installed because its code could not be found.");

    Helper::module()->install('drupal_helpers_nonexistent');
  }

  /**
   * Tests that installing a module with an unresolvable dependency throws.
   */
  public function testInstallMissingDependencyThrows(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('could not be resolved');

    Helper::module()->install('drupal_helpers_test_broken');
  }

  /**
   * Tests uninstalling a module.
   */
  public function testUninstall(): void {
    Helper::module()->install('drupal_helpers_test');
    $this->assertTrue($this->moduleHandler()->moduleExists('drupal_helpers_test'));

    $result = Helper::module()->uninstall('drupal_helpers_test');

    $this->assertFalse($this->moduleHandler()->moduleExists('drupal_helpers_test'));
    $this->assertStringContainsString("Uninstalled module 'drupal_helpers_test'", $result);
  }

  /**
   * Tests that uninstalling an already-absent module is a no-op.
   */
  public function testUninstallAlreadyAbsent(): void {
    $result = Helper::module()->uninstall('drupal_helpers_test');

    $this->assertStringContainsString('already uninstalled', $result);
  }

  /**
   * Tests that uninstalling a module also uninstalls its dependents.
   */
  public function testUninstallWithDependents(): void {
    Helper::module()->install('drupal_helpers_test_dependent');
    $this->assertTrue($this->moduleHandler()->moduleExists('drupal_helpers_test'));

    $result = Helper::module()->uninstall('drupal_helpers_test');

    $this->assertFalse($this->moduleHandler()->moduleExists('drupal_helpers_test'));
    $this->assertFalse($this->moduleHandler()->moduleExists('drupal_helpers_test_dependent'));
    $this->assertStringContainsString('drupal_helpers_test_dependent', $result);
  }

  /**
   * Tests force-removing an orphaned module whose code is missing.
   */
  public function testForceUninstallOrphanedModule(): void {
    $this->registerOrphanedModule('ghost_module');

    // Seed the module's own config in the default and a non-default collection.
    // An orphaned module's leftover config has no schema, so write it straight
    // to storage to bypass the kernel test's config schema checker.
    $this->container->get('config.storage')->write('ghost_module.settings', ['foo' => 'bar']);
    $this->container->get('config.storage')->createCollection('language.xx')->write('ghost_module.settings', ['foo' => 'bar']);

    $called = NULL;
    $result = Helper::module()->uninstall('ghost_module', function (string $module) use (&$called): void {
      $called = $module;
    });

    $this->assertSame('ghost_module', $called);
    $this->assertArrayNotHasKey('ghost_module', $this->installedModules());
    $this->assertSame(UpdateHookRegistry::SCHEMA_UNINSTALLED, $this->updateHookRegistry()->getInstalledVersion('ghost_module'));
    $this->assertTrue($this->container->get('config.factory')->get('ghost_module.settings')->isNew());
    $this->assertFalse($this->container->get('config.storage')->createCollection('language.xx')->exists('ghost_module.settings'));
    $this->assertStringContainsString("Force-removed orphaned module 'ghost_module'", $result);
  }

  /**
   * Tests that force-removal works without a callback.
   */
  public function testForceUninstallWithoutCallback(): void {
    $this->registerOrphanedModule('ghost_module');

    $result = Helper::module()->uninstall('ghost_module');

    $this->assertArrayNotHasKey('ghost_module', $this->installedModules());
    $this->assertSame(UpdateHookRegistry::SCHEMA_UNINSTALLED, $this->updateHookRegistry()->getInstalledVersion('ghost_module'));
    $this->assertStringContainsString("Force-removed orphaned module 'ghost_module'", $result);
  }

  /**
   * Register a module in the database without providing its code.
   *
   * @param string $module
   *   Module machine name.
   */
  protected function registerOrphanedModule(string $module): void {
    $config = $this->container->get('config.factory')->getEditable('core.extension');
    $modules = $config->get('module') ?? [];
    $modules[$module] = 0;
    $config->set('module', $modules)->save();

    $this->updateHookRegistry()->setInstalledVersion($module, 8000);
  }

  /**
   * Get the installed modules from configuration.
   *
   * @return array<string, int>
   *   Installed module machine names keyed to their weight.
   */
  protected function installedModules(): array {
    return $this->container->get('config.factory')->get('core.extension')->get('module') ?? [];
  }

  /**
   * Get the current module handler from the active container.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler service.
   */
  protected function moduleHandler(): object {
    return \Drupal::service('module_handler');
  }

  /**
   * Get the update hook registry from the active container.
   *
   * @return \Drupal\Core\Update\UpdateHookRegistry
   *   The update hook registry service.
   */
  protected function updateHookRegistry(): UpdateHookRegistry {
    return \Drupal::service('update.update_hook_registry');
  }

}
