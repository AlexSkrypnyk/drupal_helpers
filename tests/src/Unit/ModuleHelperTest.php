<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Update\UpdateHookRegistry;
use Drupal\Core\Update\UpdateRegistry;
use Drupal\drupal_helpers\Helpers\Module;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Module helper service.
 */
#[CoversClass(Module::class)]
class ModuleHelperTest extends TestCase {

  /**
   * The module installer mock.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $moduleInstaller;

  /**
   * The module extension list mock.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $moduleExtensionList;

  /**
   * The config factory mock.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $configFactory;

  /**
   * The config manager mock.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $configManager;

  /**
   * The active config storage mock.
   *
   * @var \Drupal\Core\Config\StorageInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $configStorage;

  /**
   * The update hook registry mock.
   *
   * @var \Drupal\Core\Update\UpdateHookRegistry&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $updateHookRegistry;

  /**
   * The post-update registry mock.
   *
   * @var \Drupal\Core\Update\UpdateRegistry&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $postUpdateRegistry;

  /**
   * The route builder mock.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $routeBuilder;

  /**
   * The messenger mock.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $messenger;

  /**
   * The immutable core.extension config mock.
   *
   * @var \Drupal\Core\Config\ImmutableConfig&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $immutableConfig;

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

    $this->moduleInstaller = $this->createMock(ModuleInstallerInterface::class);
    $this->moduleExtensionList = $this->createMock(ModuleExtensionList::class);
    $this->moduleExtensionList->method('reset')->willReturnSelf();
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configManager = $this->createMock(ConfigManagerInterface::class);
    $this->configStorage = $this->createMock(StorageInterface::class);
    $this->updateHookRegistry = $this->createMock(UpdateHookRegistry::class);
    $this->postUpdateRegistry = $this->createMock(UpdateRegistry::class);
    $this->routeBuilder = $this->createMock(RouteBuilderInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);

    $this->immutableConfig = $this->createMock(ImmutableConfig::class);
    $this->configFactory->method('get')->willReturn($this->immutableConfig);

    $this->translation = $this->createMock(TranslationInterface::class);
    $this->translation->method('translateString')->willReturnCallback(fn($input): string => (string) $input->getUntranslatedString());
  }

  /**
   * Create a Module helper with the mocked services.
   */
  protected function createModule(): Module {
    $module = new Module(
      $this->moduleInstaller,
      $this->moduleExtensionList,
      $this->configFactory,
      $this->configManager,
      $this->configStorage,
      $this->updateHookRegistry,
      $this->postUpdateRegistry,
      $this->routeBuilder,
      $this->messenger,
    );
    $module->setStringTranslation($this->translation);

    return $module;
  }

  /**
   * Tests that installing an already-enabled module short-circuits.
   */
  public function testInstallAlreadyEnabled(): void {
    $this->immutableConfig->method('get')->willReturn(['foo' => 0]);
    $this->moduleInstaller->expects($this->never())->method('install');

    $result = $this->createModule()->install('foo');

    $this->assertStringContainsString('already enabled', $result);
  }

  /**
   * Tests that installing a module with no code throws.
   */
  public function testInstallMissingModuleThrows(): void {
    $this->immutableConfig->method('get')->willReturn([]);
    $this->moduleExtensionList->method('exists')->with('foo')->willReturn(FALSE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("Module 'foo' cannot be installed because its code could not be found.");

    $this->createModule()->install('foo');
  }

  /**
   * Tests that a MissingDependencyException is wrapped in a clear exception.
   */
  public function testInstallWrapsMissingDependencyException(): void {
    $this->immutableConfig->method('get')->willReturn([]);
    $this->moduleExtensionList->method('exists')->with('foo')->willReturn(TRUE);
    $this->moduleInstaller->method('install')->willThrowException(new MissingDependencyException('missing bar'));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("Module 'foo' or one of its dependencies could not be resolved: missing bar");

    $this->createModule()->install('foo');
  }

  /**
   * Tests a successful install and its status message.
   *
   * @dataProvider dataProviderInstallSuccess
   */
  #[DataProvider('dataProviderInstallSuccess')]
  public function testInstallSuccess(array $after, string $expected): void {
    $this->immutableConfig->method('get')->willReturnOnConsecutiveCalls([], [], $after);
    $this->moduleExtensionList->method('exists')->with('foo')->willReturn(TRUE);
    $this->moduleInstaller->expects($this->once())->method('install')->with(['foo']);
    $this->messenger->expects($this->once())->method('addStatus');

    $result = $this->createModule()->install('foo');

    $this->assertStringContainsString($expected, $result);
  }

  /**
   * Tests uninstalling an already-absent module short-circuits.
   */
  public function testUninstallAlreadyAbsent(): void {
    $this->immutableConfig->method('get')->willReturn([]);
    $this->moduleInstaller->expects($this->never())->method('uninstall');

    $result = $this->createModule()->uninstall('foo');

    $this->assertStringContainsString('already uninstalled', $result);
  }

  /**
   * Tests a successful uninstall and its status message.
   *
   * @dataProvider dataProviderUninstallSuccess
   */
  #[DataProvider('dataProviderUninstallSuccess')]
  public function testUninstallSuccess(array $before, array $after, string $expected): void {
    $this->immutableConfig->method('get')->willReturnOnConsecutiveCalls($before, $before, $after);
    $this->moduleExtensionList->method('exists')->with('foo')->willReturn(TRUE);
    $this->moduleInstaller->expects($this->once())->method('uninstall')->with(['foo'])->willReturn(TRUE);
    $this->messenger->expects($this->once())->method('addStatus');

    $result = $this->createModule()->uninstall('foo');

    $this->assertStringContainsString($expected, $result);
  }

  /**
   * Tests that a missing dependent during uninstall throws.
   */
  public function testUninstallDependentMissingThrows(): void {
    $this->immutableConfig->method('get')->willReturn(['foo' => 0]);
    $this->moduleExtensionList->method('exists')->with('foo')->willReturn(TRUE);
    $this->moduleInstaller->method('uninstall')->with(['foo'])->willReturn(FALSE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("Module 'foo' could not be uninstalled because a required dependent module is missing.");

    $this->createModule()->uninstall('foo');
  }

  /**
   * Tests that force-removal runs the full cleanup and invokes the callback.
   */
  public function testForceUninstallInvokesCallback(): void {
    $this->immutableConfig->method('get')->willReturn(['ghost' => 0]);
    $this->moduleExtensionList->method('exists')->with('ghost')->willReturn(FALSE);

    $update_entity = $this->createMock(ConfigEntityBase::class);
    $update_entity->expects($this->once())->method('save');
    $delete_entity = $this->createMock(ConfigEntityBase::class);
    $delete_entity->expects($this->once())->method('setUninstalling')->with(TRUE);
    $delete_entity->expects($this->once())->method('delete');
    $this->configManager->expects($this->once())->method('getConfigEntitiesToChangeOnDependencyRemoval')
      ->with('module', ['ghost'], FALSE)
      ->willReturn(['update' => [$update_entity], 'delete' => [$delete_entity], 'unchanged' => []]);

    $own_config = $this->createMock(Config::class);
    $own_config->expects($this->once())->method('delete');
    $this->configFactory->method('listAll')->with('ghost.')->willReturn(['ghost.settings']);

    $core_extension = $this->createMock(Config::class);
    $core_extension->expects($this->once())->method('clear')->with('module.ghost')->willReturnSelf();
    $core_extension->expects($this->once())->method('save')->willReturnSelf();
    $this->configFactory->method('getEditable')->willReturnMap([
      ['ghost.settings', $own_config],
      ['core.extension', $core_extension],
    ]);

    $collection_storage = $this->createMock(StorageInterface::class);
    $collection_storage->expects($this->once())->method('deleteAll')->with('ghost.');
    $this->configStorage->method('getAllCollectionNames')->willReturn(['language.de']);
    $this->configStorage->method('createCollection')->with('language.de')->willReturn($collection_storage);

    $this->updateHookRegistry->expects($this->once())->method('deleteInstalledVersion')->with('ghost');
    $this->postUpdateRegistry->expects($this->once())->method('filterOutInvokedUpdatesByExtension')->with('ghost');
    $this->routeBuilder->expects($this->once())->method('rebuild');

    $module = $this->createForcedModule();
    $module->expects($this->once())->method('flushCaches');

    $called = NULL;
    $result = $module->uninstall('ghost', function (string $name) use (&$called): void {
      $called = $name;
    });

    $this->assertSame('ghost', $called);
    $this->assertStringContainsString("Force-removed orphaned module 'ghost'", $result);
  }

  /**
   * Tests that force-removal works without a callback or dependent config.
   */
  public function testForceUninstallWithoutCallback(): void {
    $this->immutableConfig->method('get')->willReturn(['ghost' => 0]);
    $this->moduleExtensionList->method('exists')->with('ghost')->willReturn(FALSE);

    $this->configManager->method('getConfigEntitiesToChangeOnDependencyRemoval')
      ->willReturn(['update' => [], 'delete' => [], 'unchanged' => []]);
    $this->configFactory->method('listAll')->willReturn([]);
    $this->configStorage->method('getAllCollectionNames')->willReturn([]);

    $core_extension = $this->createMock(Config::class);
    $core_extension->method('clear')->willReturnSelf();
    $core_extension->method('save')->willReturnSelf();
    $this->configFactory->method('getEditable')->willReturn($core_extension);

    $this->updateHookRegistry->expects($this->once())->method('deleteInstalledVersion')->with('ghost');
    $this->routeBuilder->expects($this->once())->method('rebuild');

    $module = $this->createForcedModule();
    $module->expects($this->once())->method('flushCaches');

    $result = $module->uninstall('ghost');

    $this->assertStringContainsString("Force-removed orphaned module 'ghost'", $result);
  }

  /**
   * Create a Module helper with a stubbed flushCaches() seam.
   *
   * @return \Drupal\drupal_helpers\Helpers\Module&\PHPUnit\Framework\MockObject\MockObject
   *   The partially mocked Module helper.
   */
  protected function createForcedModule(): MockObject {
    $module = $this->getMockBuilder(Module::class)
      ->setConstructorArgs([
        $this->moduleInstaller,
        $this->moduleExtensionList,
        $this->configFactory,
        $this->configManager,
        $this->configStorage,
        $this->updateHookRegistry,
        $this->postUpdateRegistry,
        $this->routeBuilder,
        $this->messenger,
      ])
      ->onlyMethods(['flushCaches'])
      ->getMock();
    $module->setStringTranslation($this->translation);

    return $module;
  }

  /**
   * Data provider for testInstallSuccess().
   */
  public static function dataProviderInstallSuccess(): array {
    return [
      'no dependencies' => [['foo' => 0], "Installed module 'foo'."],
      'with dependencies' => [['foo' => 0, 'bar' => 0, 'baz' => 0], 'and its dependencies: bar, baz'],
    ];
  }

  /**
   * Data provider for testUninstallSuccess().
   */
  public static function dataProviderUninstallSuccess(): array {
    return [
      'no dependents' => [['foo' => 0], [], "Uninstalled module 'foo'."],
      'with dependents' => [['foo' => 0, 'dep' => 0], [], 'and its dependents: dep'],
    ];
  }

}
