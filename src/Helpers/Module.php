<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Update\UpdateHookRegistry;
use Drupal\Core\Update\UpdateRegistry;
use Drupal\drupal_helpers\Report\Reporter;

/**
 * Module install and uninstall helpers for deploy hooks.
 */
class Module {

  use StringTranslationTrait;

  public function __construct(
    protected ModuleInstallerInterface $moduleInstaller,
    protected ModuleExtensionList $moduleExtensionList,
    protected ConfigFactoryInterface $configFactory,
    protected ConfigManagerInterface $configManager,
    protected StorageInterface $configStorage,
    protected UpdateHookRegistry $updateHookRegistry,
    protected UpdateRegistry $postUpdateRegistry,
    protected RouteBuilderInterface $routeBuilder,
    protected Reporter $reporter,
  ) {}

  /**
   * Install a module and its dependencies.
   *
   * Enabling an already-enabled module is a safe no-op.
   *
   * @code
   * Helper::module()->install('pathauto');
   * @endcode
   *
   * @param string $module
   *   Module machine name.
   *
   * @return string
   *   Human-readable status message describing what happened.
   *
   * @throws \RuntimeException
   *   When the module or one of its dependencies cannot be resolved.
   */
  public function install(string $module): string {
    if ($this->isInstalled($module)) {
      $message = $this->t("Module '@module' is already enabled. Skipped.", ['@module' => $module]);
      $this->reporter->skipped($message);

      return (string) $message;
    }

    if (!$this->moduleHasCode($module)) {
      throw new \RuntimeException(sprintf("Module '%s' cannot be installed because its code could not be found.", $module));
    }

    $before = array_keys($this->installedModules());

    try {
      $this->moduleInstaller->install([$module]);
    }
    catch (MissingDependencyException $exception) {
      throw new \RuntimeException(sprintf("Module '%s' or one of its dependencies could not be resolved: %s", $module, $exception->getMessage()), 0, $exception);
    }

    $dependencies = array_diff(array_keys($this->installedModules()), $before, [$module]);
    sort($dependencies);

    if ($dependencies !== []) {
      $message = $this->t("Installed module '@module' and its dependencies: @dependencies.", [
        '@module' => $module,
        '@dependencies' => implode(', ', $dependencies),
      ]);
    }
    else {
      $message = $this->t("Installed module '@module'.", ['@module' => $module]);
    }

    $this->reporter->created($message);

    return (string) $message;
  }

  /**
   * Uninstall a module.
   *
   * Uninstalling an already-absent module is a safe no-op. When the module's
   * code has been removed but it is still recorded in the database, the module
   * is force-removed and the optional callback runs in place of the module's
   * unavailable hook_uninstall() implementation.
   *
   * @code
   * Helper::module()->uninstall('legacy_feature');
   *
   * // Orphaned module (code removed, still in the database):
   * Helper::module()->uninstall('ghost_module', function (string $module): void {
   *   \Drupal::database()->schema()->dropTable('ghost_module_data');
   * });
   * @endcode
   *
   * @param string $module
   *   Module machine name.
   * @param callable|null $callback
   *   Optional callback invoked with the module machine name only when the
   *   module is force-removed because its code is missing. Use it to leave the
   *   traces the module's own hook_uninstall() would have left behind.
   *
   * @return string
   *   Human-readable status message describing what happened.
   *
   * @throws \RuntimeException
   *   When the module cannot be uninstalled because a required dependent module
   *   is missing.
   */
  public function uninstall(string $module, ?callable $callback = NULL): string {
    if (!$this->isInstalled($module)) {
      $message = $this->t("Module '@module' is already uninstalled. Skipped.", ['@module' => $module]);
      $this->reporter->skipped($message);

      return (string) $message;
    }

    if (!$this->moduleHasCode($module)) {
      return $this->forceUninstall($module, $callback);
    }

    $before = array_keys($this->installedModules());

    if ($this->moduleInstaller->uninstall([$module]) === FALSE) {
      throw new \RuntimeException(sprintf("Module '%s' could not be uninstalled because a required dependent module is missing.", $module));
    }

    $dependents = array_diff($before, array_keys($this->installedModules()), [$module]);
    sort($dependents);

    if ($dependents !== []) {
      $message = $this->t("Uninstalled module '@module' and its dependents: @dependents.", [
        '@module' => $module,
        '@dependents' => implode(', ', $dependents),
      ]);
    }
    else {
      $message = $this->t("Uninstalled module '@module'.", ['@module' => $module]);
    }

    $this->reporter->deleted($message);

    return (string) $message;
  }

  /**
   * Force-remove a module whose code is missing but is still in the database.
   *
   * The native module installer refuses to uninstall a module it cannot find
   * on disk, leaving orphaned traces behind. This reproduces the parts of a
   * normal uninstall that do not need the module's code: dependent config is
   * repaired, the module's own config is removed, its schema version and
   * post-update bookkeeping are cleared, caches are flushed and routes are
   * rebuilt. The module's own hook_uninstall() and hook_schema() teardown
   * cannot run, so the caller-supplied callback must remove any leftover
   * database tables or data the module owned.
   *
   * @param string $module
   *   Module machine name.
   * @param callable|null $callback
   *   Optional callback invoked with the module machine name.
   *
   * @return string
   *   Human-readable status message.
   */
  protected function forceUninstall(string $module, ?callable $callback): string {
    if ($callback !== NULL) {
      $callback($module);
    }

    $this->removeModuleConfig($module);

    $this->configFactory->getEditable('core.extension')->clear('module.' . $module)->save();

    $this->updateHookRegistry->deleteInstalledVersion($module);
    $this->postUpdateRegistry->filterOutInvokedUpdatesByExtension($module);

    $this->moduleExtensionList->reset();
    $this->flushCaches();
    $this->routeBuilder->rebuild();

    $message = $this->t("Force-removed orphaned module '@module' because its code is missing.", ['@module' => $module]);
    $this->reporter->deleted($message);

    return (string) $message;
  }

  /**
   * Remove and repair configuration owned by or depending on a module.
   *
   * Reproduces the dependency repair and config removal a normal uninstall
   * performs through the config manager, without resolving the module's path:
   * a missing module has no path, and requesting one would emit a "missing
   * from the file system" warning. The config manager's own uninstall() only
   * needs that path for a schema-cache refresh a codeless module never needs.
   *
   * Config entities that depend on the module are fixed or deleted, and the
   * module's own simple configuration is removed from the default collection
   * and from every other collection (such as language overrides).
   *
   * @param string $module
   *   Module machine name.
   */
  protected function removeModuleConfig(string $module): void {
    $entities = $this->configManager->getConfigEntitiesToChangeOnDependencyRemoval('module', [$module], FALSE);

    /** @var \Drupal\Core\Config\Entity\ConfigEntityBase[] $update */
    $update = $entities['update'];
    foreach ($update as $entity) {
      $entity->save();
    }

    /** @var \Drupal\Core\Config\Entity\ConfigEntityBase[] $delete */
    $delete = $entities['delete'];
    foreach ($delete as $entity) {
      $entity->setUninstalling(TRUE);
      $entity->delete();
    }

    $prefix = $module . '.';

    foreach ($this->configFactory->listAll($prefix) as $config_name) {
      $this->configFactory->getEditable($config_name)->delete();
    }

    foreach ($this->configStorage->getAllCollectionNames() as $collection) {
      $this->configStorage->createCollection($collection)->deleteAll($prefix);
    }
  }

  /**
   * Flush all cache bins.
   *
   * Mirrors the final cache flush the module installer performs, since an
   * orphaned module's data may be cached under any bin.
   */
  protected function flushCaches(): void {
    foreach (Cache::getBins() as $cache_backend) {
      $cache_backend->deleteAll();
    }
  }

  /**
   * Get the list of installed modules from configuration.
   *
   * Reads core.extension directly rather than the module handler: the module
   * handler cannot see a module whose code is missing, so it cannot tell an
   * orphaned-in-database module apart from a fully absent one.
   *
   * @return array<string, int>
   *   Installed module machine names keyed to their weight.
   */
  protected function installedModules(): array {
    return $this->configFactory->get('core.extension')->get('module') ?? [];
  }

  /**
   * Check whether a module is recorded as installed in the database.
   *
   * @param string $module
   *   Module machine name.
   *
   * @return bool
   *   TRUE when the module is listed in core.extension.
   */
  protected function isInstalled(string $module): bool {
    return array_key_exists($module, $this->installedModules());
  }

  /**
   * Check whether a module's code is present on disk.
   *
   * Rescans the extension list first so a module added to or removed from the
   * filesystem during the same request is detected, rather than relying on the
   * list discovered when the container was built.
   *
   * @param string $module
   *   Module machine name.
   *
   * @return bool
   *   TRUE when the module's code can be found.
   */
  protected function moduleHasCode(string $module): bool {
    return $this->moduleExtensionList->reset()->exists($module);
  }

}
