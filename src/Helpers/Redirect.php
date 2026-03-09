<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

/**
 * Redirect helpers for deploy hooks.
 *
 * Requires the 'redirect' contrib module.
 */
class Redirect extends HelperBase {

  /**
   * {@inheritdoc}
   */
  public function requiredModules(): array {
    return ['redirect'];
  }

  /**
   * Create a redirect.
   *
   * @code
   * Helper::redirect()->create('old-page', '/new-page');
   * Helper::redirect()->create('legacy', 'https://example.com', 302);
   * @endcode
   *
   * @param string $source_path
   *   Source path (without leading slash, e.g., 'old-page').
   * @param string $redirect_path
   *   Redirect target path (e.g., '/new-page' or 'https://example.com').
   * @param int $status_code
   *   HTTP status code. Defaults to 301.
   * @param bool $skip_existing
   *   If TRUE, skip creating if a redirect for this source already exists.
   *   Defaults to TRUE.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Created redirect entity or NULL if skipped.
   */
  public function create(string $source_path, string $redirect_path, int $status_code = 301, bool $skip_existing = TRUE): mixed {
    $source_path = ltrim($source_path, '/');

    $storage = $this->entityTypeManager->getStorage('redirect');

    if ($skip_existing) {
      $existing = $storage->loadByProperties(['redirect_source__path' => $source_path]);
      if ($existing) {
        $this->messenger->addStatus($this->t('Redirect from "@source" already exists — skipped.', [
          '@source' => $source_path,
        ]));

        return reset($existing);
      }
    }

    $uri = $this->pathToUri($redirect_path);

    $redirect = $storage->create([
      'redirect_source' => ['path' => $source_path],
      'redirect_redirect' => ['uri' => $uri],
      'status_code' => $status_code,
    ]);
    $redirect->save();

    $this->messenger->addStatus($this->t('Created @code redirect: "@source" -> "@target".', [
      '@code' => $status_code,
      '@source' => $source_path,
      '@target' => $redirect_path,
    ]));

    return $redirect;
  }

  /**
   * Create multiple redirects.
   *
   * @code
   * Helper::redirect()->createMultiple([
   *   ['source' => 'old-page', 'target' => '/new-page'],
   *   ['source' => 'legacy', 'target' => 'https://example.com', 'status_code' => 302],
   * ]);
   * @endcode
   *
   * @param array $redirects
   *   Array of redirects, each being an array with keys:
   *   - 'source': Source path.
   *   - 'target': Target path.
   *   - 'status_code': (optional) HTTP status code. Defaults to 301.
   *
   * @return int
   *   Number of created redirects.
   */
  public function createMultiple(array $redirects): int {
    $count = 0;

    foreach ($redirects as $redirect) {
      $result = $this->create(
        $redirect['source'],
        $redirect['target'],
        $redirect['status_code'] ?? 301,
      );

      if ($result !== NULL) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * Delete redirects by source path.
   *
   * @code
   * Helper::redirect()->deleteBySource('old-page');
   * @endcode
   *
   * @param string $source_path
   *   Source path.
   *
   * @return int
   *   Number of deleted redirects.
   */
  public function deleteBySource(string $source_path): int {
    $source_path = ltrim($source_path, '/');
    $storage = $this->entityTypeManager->getStorage('redirect');
    $redirects = $storage->loadByProperties(['redirect_source__path' => $source_path]);

    if (empty($redirects)) {
      return 0;
    }

    $storage->delete($redirects);

    $count = count($redirects);
    $this->messenger->addStatus($this->t('Deleted @count redirects for "@source".', [
      '@count' => $count,
      '@source' => $source_path,
    ]));

    return $count;
  }

  /**
   * Delete all redirect entities.
   *
   * @code
   * Helper::redirect()->deleteAll();
   * @endcode
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress.
   */
  public function deleteAll(): ?string {
    return $this->batchEntity('redirect', NULL, function ($redirect): void {
      $redirect->delete();
    });
  }

  /**
   * Import redirects from a CSV file.
   *
   * CSV format (no header row):
   * - Column 1: Source path (e.g., 'old-page')
   * - Column 2: Target path (e.g., '/new-page' or 'https://example.com')
   * - Column 3: (optional) HTTP status code (default: 301)
   *
   * @code
   * Helper::redirect()->importFromCsv('/path/to/redirects.csv');
   *
   * // With sandbox for large files:
   * function my_module_deploy_001(array &$sandbox): ?string {
   *   return Helper::redirect($sandbox)->importFromCsv('/path/to/redirects.csv');
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
        if (empty($row) || (count($row) === 1 && trim($row[0]) === '')) {
          continue;
        }

        $rows[] = [
          'source' => $row[0],
          'target' => $row[1] ?? '',
          'status_code' => (int) ($row[2] ?? 301),
        ];
      }

      fclose($handle);
    }
    else {
      $rows = [];
    }

    return $this->batch($rows, function (array $row): void {
      $this->create($row['source'], $row['target'], $row['status_code']);
    }, 'redirects');
  }

  /**
   * Convert a path to a URI.
   *
   * @param string $path
   *   Path string.
   *
   * @return string
   *   URI string.
   */
  protected function pathToUri(string $path): string {
    if (str_starts_with($path, 'internal:') || str_starts_with($path, 'entity:') || str_starts_with($path, 'route:')) {
      return $path;
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
      return $path;
    }

    if (!str_starts_with($path, '/')) {
      $path = '/' . $path;
    }

    return 'internal:' . $path;
  }

}
