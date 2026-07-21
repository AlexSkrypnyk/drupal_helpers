<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Core\Language\LanguageInterface;
use Drupal\drupal_helpers\Report\Reporter;
use Drupal\path_alias\PathAliasInterface;

/**
 * URL alias helpers for deploy hooks.
 *
 * Manages core 'path_alias' entities; no contrib module is required.
 */
class Alias extends HelperBase {

  /**
   * Create a URL alias.
   *
   * @code
   * Helper::alias()->create('/node/1', '/about-us');
   * Helper::alias()->create('/node/2', '/a-propos', 'fr');
   * @endcode
   *
   * @param string $path
   *   System path (e.g., '/node/1'). A leading slash is added if missing.
   * @param string $alias
   *   URL alias (e.g., '/about-us'). A leading slash is added if missing.
   * @param string|null $langcode
   *   Language code. Defaults to language-neutral when NULL.
   * @param bool $skip_existing
   *   If TRUE, skip creating when an alias for this system path already exists,
   *   or when the alias string is already in use for the given language.
   *   Defaults to TRUE.
   *
   * @return \Drupal\path_alias\PathAliasInterface|null
   *   Created alias entity, or the existing one when skipped.
   */
  public function create(string $path, string $alias, ?string $langcode = NULL, bool $skip_existing = TRUE): ?PathAliasInterface {
    $path = $this->normalizePath($path);
    $alias = $this->normalizePath($alias);
    $langcode ??= LanguageInterface::LANGCODE_NOT_SPECIFIED;

    if ($skip_existing) {
      $existing = $this->findByPath($path, $langcode);
      if ($existing instanceof PathAliasInterface) {
        $this->reporter->skipped($this->t('Alias for "@path" already exists - skipped.', [
          '@path' => $path,
        ]));

        return $existing;
      }

      $taken = $this->findByAlias($alias, $langcode);
      if ($taken instanceof PathAliasInterface) {
        $this->reporter->skipped($this->t('Alias "@alias" is already in use - skipped.', [
          '@alias' => $alias,
        ]));

        return $taken;
      }
    }

    /** @var \Drupal\path_alias\PathAliasInterface $path_alias */
    $path_alias = $this->entityTypeManager->getStorage('path_alias')->create([
      'path' => $path,
      'alias' => $alias,
      'langcode' => $langcode,
    ]);
    $path_alias->save();

    $this->reporter->created($this->t('Created alias: "@path" -> "@alias".', [
      '@path' => $path,
      '@alias' => $alias,
    ]));

    return $path_alias;
  }

  /**
   * Create multiple URL aliases.
   *
   * @code
   * Helper::alias()->createMultiple([
   *   ['path' => '/node/1', 'alias' => '/about-us'],
   *   ['path' => '/node/2', 'alias' => '/a-propos', 'langcode' => 'fr'],
   * ]);
   * @endcode
   *
   * @param array $aliases
   *   Array of aliases, each being an array with keys:
   *   - 'path': System path.
   *   - 'alias': URL alias.
   *   - 'langcode': (optional) Language code.
   *
   * @return int
   *   Number of processed aliases.
   */
  public function createMultiple(array $aliases): int {
    $count = 0;

    foreach ($aliases as $alias) {
      $result = $this->create($alias['path'], $alias['alias'], $alias['langcode'] ?? NULL);

      if ($result instanceof PathAliasInterface) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * Find an alias by system path.
   *
   * @code
   * $alias = Helper::alias()->findByPath('/node/1');
   * @endcode
   *
   * @param string $path
   *   System path (e.g., '/node/1'). A leading slash is added if missing.
   * @param string|null $langcode
   *   Language code to match, or NULL to match any language.
   *
   * @return \Drupal\path_alias\PathAliasInterface|null
   *   Alias entity or NULL if not found.
   */
  public function findByPath(string $path, ?string $langcode = NULL): ?PathAliasInterface {
    return $this->findByProperty('path', $this->normalizePath($path), $langcode);
  }

  /**
   * Find an alias by its alias string.
   *
   * @code
   * $alias = Helper::alias()->findByAlias('/about-us');
   * @endcode
   *
   * @param string $alias
   *   URL alias (e.g., '/about-us'). A leading slash is added if missing.
   * @param string|null $langcode
   *   Language code to match, or NULL to match any language.
   *
   * @return \Drupal\path_alias\PathAliasInterface|null
   *   Alias entity or NULL if not found.
   */
  public function findByAlias(string $alias, ?string $langcode = NULL): ?PathAliasInterface {
    return $this->findByProperty('alias', $this->normalizePath($alias), $langcode);
  }

  /**
   * Rename the alias for a system path.
   *
   * Locates the alias by its system path and replaces the alias string.
   *
   * @code
   * Helper::alias()->updateByPath('/node/1', '/about-us');
   * @endcode
   *
   * @param string $path
   *   System path to locate (e.g., '/node/1').
   * @param string $alias
   *   New URL alias (e.g., '/about-us').
   * @param string|null $langcode
   *   Language code to match, or NULL to match any language.
   *
   * @return \Drupal\path_alias\PathAliasInterface|null
   *   Updated alias entity, or NULL if no matching alias was found.
   */
  public function updateByPath(string $path, string $alias, ?string $langcode = NULL): ?PathAliasInterface {
    $path = $this->normalizePath($path);
    $alias = $this->normalizePath($alias);

    $path_alias = $this->findByPath($path, $langcode);

    if (!$path_alias instanceof PathAliasInterface) {
      $this->reporter->skipped($this->t('No alias found for path "@path" - nothing to update.', [
        '@path' => $path,
      ]));

      return NULL;
    }

    $path_alias->setAlias($alias);
    $path_alias->save();

    $this->reporter->updated($this->t('Updated alias for path "@path" -> "@alias".', [
      '@path' => $path,
      '@alias' => $alias,
    ]));

    return $path_alias;
  }

  /**
   * Retarget an alias to a new system path.
   *
   * Locates the alias by its alias string and replaces the system path.
   *
   * @code
   * Helper::alias()->updateByAlias('/about-us', '/node/5');
   * @endcode
   *
   * @param string $alias
   *   URL alias to locate (e.g., '/about-us').
   * @param string $path
   *   New system path (e.g., '/node/5').
   * @param string|null $langcode
   *   Language code to match, or NULL to match any language.
   *
   * @return \Drupal\path_alias\PathAliasInterface|null
   *   Updated alias entity, or NULL if no matching alias was found.
   */
  public function updateByAlias(string $alias, string $path, ?string $langcode = NULL): ?PathAliasInterface {
    $alias = $this->normalizePath($alias);
    $path = $this->normalizePath($path);

    $path_alias = $this->findByAlias($alias, $langcode);

    if (!$path_alias instanceof PathAliasInterface) {
      $this->reporter->skipped($this->t('No alias "@alias" found - nothing to update.', [
        '@alias' => $alias,
      ]));

      return NULL;
    }

    $path_alias->setPath($path);
    $path_alias->save();

    $this->reporter->updated($this->t('Retargeted alias "@alias" -> "@path".', [
      '@alias' => $alias,
      '@path' => $path,
    ]));

    return $path_alias;
  }

  /**
   * Delete aliases by system path.
   *
   * @code
   * Helper::alias()->deleteByPath('/node/1');
   * @endcode
   *
   * @param string $path
   *   System path (e.g., '/node/1'). A leading slash is added if missing.
   * @param string|null $langcode
   *   Language code to match, or NULL to match any language.
   *
   * @return int
   *   Number of deleted aliases.
   */
  public function deleteByPath(string $path, ?string $langcode = NULL): int {
    return $this->deleteByProperty('path', $this->normalizePath($path), $langcode);
  }

  /**
   * Delete aliases by their alias string.
   *
   * @code
   * Helper::alias()->deleteByAlias('/about-us');
   * @endcode
   *
   * @param string $alias
   *   URL alias (e.g., '/about-us'). A leading slash is added if missing.
   * @param string|null $langcode
   *   Language code to match, or NULL to match any language.
   *
   * @return int
   *   Number of deleted aliases.
   */
  public function deleteByAlias(string $alias, ?string $langcode = NULL): int {
    return $this->deleteByProperty('alias', $this->normalizePath($alias), $langcode);
  }

  /**
   * Delete all URL aliases.
   *
   * @code
   * Helper::alias()->deleteAll();
   * @endcode
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress.
   */
  public function deleteAll(): ?string {
    return $this->batchEntity('path_alias', NULL, function ($path_alias): void {
      $path_alias->delete();
    }, status: Reporter::DELETED);
  }

  /**
   * Import URL aliases from a CSV file.
   *
   * CSV format (no header row):
   * - Column 1: System path (e.g., '/node/1')
   * - Column 2: URL alias (e.g., '/about-us')
   * - Column 3: (optional) Language code (default: language-neutral)
   *
   * @code
   * Helper::alias()->importFromCsv('/path/to/aliases.csv');
   *
   * // With sandbox for large files:
   * function my_module_deploy_001(array &$sandbox): ?string {
   *   return Helper::alias($sandbox)->importFromCsv('/path/to/aliases.csv');
   * }
   * @endcode
   *
   * Supports sandbox batching for large files when called via the facade
   * with a sandbox reference.
   *
   * @param string $file_path
   *   Absolute path to the CSV file.
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress (sandbox mode).
   *
   * @throws \RuntimeException
   *   If the file cannot be read.
   */
  public function importFromCsv(string $file_path): ?string {
    if (!isset($this->sandbox['items'])) {
      if (!is_readable($file_path)) {
        throw new \RuntimeException(sprintf('Cannot read CSV file: %s', $file_path));
      }

      $rows = [];
      $handle = fopen($file_path, 'r');

      if ($handle === FALSE) {
        throw new \RuntimeException(sprintf('Cannot open CSV file: %s', $file_path));
      }

      while (($row = fgetcsv($handle, escape: '\\')) !== FALSE) {
        $has_value = (bool) array_filter($row, static fn($value): bool => trim((string) $value) !== '');
        if (!$has_value) {
          continue;
        }

        $langcode = isset($row[2]) ? trim($row[2]) : '';

        $rows[] = [
          'path' => $row[0],
          'alias' => $row[1] ?? '',
          'langcode' => $langcode !== '' ? $langcode : NULL,
        ];
      }

      fclose($handle);
    }
    else {
      $rows = [];
    }

    // The per-row create() already reports each alias, so the batch itself
    // records no count to avoid double counting.
    return $this->batch($rows, function (array $row): void {
      $this->create($row['path'], $row['alias'], $row['langcode']);
    }, 'aliases', status: NULL);
  }

  /**
   * Find a single alias entity by a base-field property.
   *
   * @param string $property
   *   Property name ('path' or 'alias').
   * @param string $value
   *   Property value to match.
   * @param string|null $langcode
   *   Language code to match, or NULL to match any language.
   *
   * @return \Drupal\path_alias\PathAliasInterface|null
   *   Alias entity or NULL if not found.
   */
  protected function findByProperty(string $property, string $value, ?string $langcode): ?PathAliasInterface {
    $properties = [$property => $value];

    if ($langcode !== NULL) {
      $properties['langcode'] = $langcode;
    }

    /** @var \Drupal\path_alias\PathAliasInterface[] $matches */
    $matches = $this->entityTypeManager->getStorage('path_alias')->loadByProperties($properties);

    return $matches ? reset($matches) : NULL;
  }

  /**
   * Delete alias entities matching a base-field property.
   *
   * @param string $property
   *   Property name ('path' or 'alias').
   * @param string $value
   *   Property value to match.
   * @param string|null $langcode
   *   Language code to match, or NULL to match any language.
   *
   * @return int
   *   Number of deleted aliases.
   */
  protected function deleteByProperty(string $property, string $value, ?string $langcode): int {
    $storage = $this->entityTypeManager->getStorage('path_alias');
    $properties = [$property => $value];

    if ($langcode !== NULL) {
      $properties['langcode'] = $langcode;
    }

    $path_aliases = $storage->loadByProperties($properties);

    if (empty($path_aliases)) {
      return 0;
    }

    $storage->delete($path_aliases);

    $count = count($path_aliases);
    $this->reporter->deleted($this->t('Deleted @count alias(es) matching @property "@value".', [
      '@count' => $count,
      '@property' => $property,
      '@value' => $value,
    ]), $count);

    return $count;
  }

  /**
   * Ensure a path has a single leading slash.
   *
   * @param string $path
   *   Path or alias string.
   *
   * @return string
   *   Path with a single leading slash.
   */
  protected function normalizePath(string $path): string {
    return '/' . ltrim($path, '/');
  }

}
