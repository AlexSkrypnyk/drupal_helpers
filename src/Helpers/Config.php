<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drupal_helpers\Report\Reporter;
use Symfony\Component\Yaml\Yaml;

/**
 * Configuration helpers for deploy hooks.
 */
class Config {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected StorageInterface $configStorage,
    protected ModuleExtensionList $moduleExtensionList,
    protected Reporter $reporter,
  ) {}

  /**
   * Set a value in a configuration object.
   *
   * @code
   * Helper::config()->set('system.site', 'name', 'My Site');
   * @endcode
   *
   * @param string $config_name
   *   Config name (e.g., 'system.site').
   * @param string $key
   *   Dot-separated key path (e.g., 'page.front').
   * @param mixed $value
   *   Value to set.
   */
  public function set(string $config_name, string $key, mixed $value): void {
    $this->configFactory->getEditable($config_name)->set($key, $value)->save();

    $this->reporter->updated($this->t('Set "@key" in "@config".', [
      '@key' => $key,
      '@config' => $config_name,
    ]));
  }

  /**
   * Get a value from a configuration object.
   *
   * @code
   * $site_name = Helper::config()->get('system.site', 'name');
   * @endcode
   *
   * @param string $config_name
   *   Config name.
   * @param string $key
   *   Dot-separated key path.
   *
   * @return mixed
   *   Config value.
   */
  public function get(string $config_name, string $key): mixed {
    return $this->configFactory->get($config_name)->get($key);
  }

  /**
   * Delete a configuration object.
   *
   * @code
   * Helper::config()->delete('my_module.settings');
   * @endcode
   *
   * @param string $config_name
   *   Config name to delete.
   */
  public function delete(string $config_name): void {
    $config = $this->configFactory->getEditable($config_name);

    if ($config->isNew()) {
      $this->reporter->skipped($this->t('Config "@config" does not exist — skipped.', [
        '@config' => $config_name,
      ]), severity: Reporter::SEVERITY_WARNING);

      return;
    }

    $config->delete();

    $this->reporter->deleted($this->t('Deleted config "@config".', [
      '@config' => $config_name,
    ]));
  }

  /**
   * Import a config from a module's config/install directory.
   *
   * Creates the config if it does not exist, or updates it if it does.
   *
   * @code
   * Helper::config()->import('my_module', 'views.view.my_view');
   * Helper::config()->import('my_module', 'node.type.page', 'optional');
   * @endcode
   *
   * @param string $module_name
   *   Module machine name.
   * @param string $config_name
   *   Config name (e.g., 'views.view.my_view').
   * @param string $subdirectory
   *   Config subdirectory. Defaults to 'install'.
   */
  public function import(string $module_name, string $config_name, string $subdirectory = 'install'): void {
    $module_path = $this->moduleExtensionList->getPath($module_name);
    $file_path = $module_path . '/config/' . $subdirectory . '/' . $config_name . '.yml';

    if (!file_exists($file_path)) {
      $this->reporter->failed($this->t('Config file "@file" not found.', [
        '@file' => $file_path,
      ]), severity: Reporter::SEVERITY_ERROR);

      return;
    }

    $data = Yaml::parseFile($file_path);
    $config = $this->configFactory->getEditable($config_name);
    $existed = !$config->isNew();
    $config->setData($data)->save();

    $message = $this->t('Imported config "@config" from @module.', [
      '@config' => $config_name,
      '@module' => $module_name,
    ]);

    if ($existed) {
      $this->reporter->updated($message);

      return;
    }

    $this->reporter->created($message);
  }

  /**
   * Import multiple configs from a module.
   *
   * @code
   * Helper::config()->importMultiple('my_module', [
   *   'views.view.my_view',
   *   'field.storage.node.field_custom',
   * ]);
   * @endcode
   *
   * @param string $module_name
   *   Module machine name.
   * @param array $config_names
   *   Array of config names.
   * @param string $subdirectory
   *   Config subdirectory. Defaults to 'install'.
   */
  public function importMultiple(string $module_name, array $config_names, string $subdirectory = 'install'): void {
    foreach ($config_names as $config_name) {
      $this->import($module_name, $config_name, $subdirectory);
    }
  }

  /**
   * Set the site front page.
   *
   * @code
   * Helper::config()->setFrontPage('/node/1');
   * @endcode
   *
   * @param string $path
   *   Path to use as front page (e.g., '/node/1').
   */
  public function setFrontPage(string $path): void {
    if (!str_starts_with($path, '/')) {
      $path = '/' . $path;
    }

    $this->set('system.site', 'page.front', $path);

    $this->reporter->message($this->t('Set front page to "@path".', [
      '@path' => $path,
    ]));
  }

}
