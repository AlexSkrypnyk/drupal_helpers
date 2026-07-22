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

  /**
   * Sentinel marking an omitted expected value.
   *
   * Lets a guarded write be told apart from an unconditional one even when the
   * caller's expected value is NULL, since NULL is itself a valid config value.
   */
  protected const NO_EXPECTED = '__drupal_helpers_no_expected__';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected StorageInterface $configStorage,
    protected ModuleExtensionList $moduleExtensionList,
    protected Reporter $reporter,
  ) {}

  /**
   * Set a value in a configuration object, optionally guarded.
   *
   * With no $expected argument the value is written unconditionally. Pass
   * $expected to guard the write against clobbering a value that has drifted
   * from what the caller assumed: the change applies only when the live value
   * equals $expected. On a mismatch the write is skipped and reported as a
   * warning describing the difference; when the target value is already in
   * place the call is an idempotent no-op that still reports success. Any
   * $expected value enables the guard, including NULL.
   *
   * @code
   * // Unconditional write:
   * Helper::config()->set('system.site', 'name', 'My Site');
   * // Guarded - applies only while the live value is still 'Old Name':
   * return Helper::config()->set('system.site', 'name', 'New Name', 'Old Name');
   * @endcode
   *
   * @param string $config_name
   *   Config name (e.g., 'system.site').
   * @param string $key
   *   Dot-separated key path (e.g., 'page.front').
   * @param mixed $value
   *   Value to set.
   * @param mixed $expected
   *   When provided, the value the live config must currently hold for the
   *   write to apply. Omit to write unconditionally.
   *
   * @return string
   *   A human-readable description of the outcome, suitable for returning
   *   directly as a deploy hook's output.
   */
  public function set(string $config_name, string $key, mixed $value, mixed $expected = self::NO_EXPECTED): string {
    $config = $this->configFactory->getEditable($config_name);

    if ($expected !== self::NO_EXPECTED) {
      $current = $config->get($key);

      if ($current === $value) {
        $message = $this->t('Value "@key" in "@config" already set - skipped.', [
          '@key' => $key,
          '@config' => $config_name,
        ]);
        $this->reporter->skipped($message);

        return (string) $message;
      }

      if ($current !== $expected) {
        $message = $this->t('Value "@key" in "@config" is "@current" but expected "@expected" - skipped.', [
          '@key' => $key,
          '@config' => $config_name,
          '@current' => $this->formatValue($current),
          '@expected' => $this->formatValue($expected),
        ]);
        $this->reporter->skipped($message, severity: Reporter::SEVERITY_WARNING);

        return (string) $message;
      }
    }

    $config->set($key, $value)->save();

    $message = $this->t('Set "@key" in "@config".', [
      '@key' => $key,
      '@config' => $config_name,
    ]);
    $this->reporter->updated($message);

    return (string) $message;
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
      $this->reporter->skipped($this->t('Config "@config" does not exist - skipped.', [
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
   * @param string $module
   *   Module machine name.
   * @param string $config_name
   *   Config name (e.g., 'views.view.my_view').
   * @param string $subdirectory
   *   Config subdirectory. Defaults to 'install'.
   */
  public function import(string $module, string $config_name, string $subdirectory = 'install'): void {
    $module_path = $this->moduleExtensionList->getPath($module);
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
      '@module' => $module,
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
   * @param string $module
   *   Module machine name.
   * @param array $config_names
   *   Array of config names.
   * @param string $subdirectory
   *   Config subdirectory. Defaults to 'install'.
   */
  public function importMultiple(string $module, array $config_names, string $subdirectory = 'install'): void {
    foreach ($config_names as $config_name) {
      $this->import($module, $config_name, $subdirectory);
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

  /**
   * Format a config value for display in an operation message.
   *
   * @param mixed $value
   *   The value to format.
   *
   * @return string
   *   A compact, single-line representation: booleans as TRUE/FALSE, NULL as
   *   NULL, arrays as inline YAML, and scalars as their string form with line
   *   breaks escaped.
   */
  protected function formatValue(mixed $value): string {
    if (is_bool($value)) {
      return $value ? 'TRUE' : 'FALSE';
    }

    if (is_array($value)) {
      return Yaml::dump($value, 0);
    }

    if (is_scalar($value)) {
      // Escape line breaks so the reporter's single-line summary stays intact.
      return str_replace(["\r\n", "\r", "\n"], '\n', (string) $value);
    }

    return 'NULL';
  }

}
