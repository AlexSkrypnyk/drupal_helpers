<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\drupal_helpers\Report\Reporter;

/**
 * Redirect helpers for deploy hooks.
 *
 * Requires the 'redirect' contrib module.
 */
class Redirect extends HelperBase {

  /**
   * Statuses listed, in order, in the CSV run summary.
   */
  protected const SUMMARY_STATUSES = [
    Reporter::CREATED,
    Reporter::UPDATED,
    Reporter::DELETED,
    Reporter::SKIPPED,
    Reporter::FAILED,
  ];

  /**
   * Per-status counts for a non-sandbox CSV run, keyed by status.
   *
   * Sandbox runs persist the equivalent tally in the sandbox instead, so a
   * resumed batch still reports the whole run rather than the last chunk only.
   *
   * @var array<string, int>
   */
  protected array $csvTally = [];

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
   * Helper::redirect()->create('vieux', '/nouveau', 301, TRUE, 'fr');
   * @endcode
   *
   * @param string $source_path
   *   Source path (without leading slash, e.g., 'old-page').
   * @param string $target_path
   *   Redirect target path (e.g., '/new-page' or 'https://example.com').
   * @param int $status_code
   *   HTTP status code. Defaults to 301.
   * @param bool $skip_existing
   *   If TRUE, skip creating if a redirect for this source already exists.
   *   Defaults to TRUE.
   * @param string|null $langcode
   *   Language code the redirect applies to, or NULL for all languages.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Created redirect entity or NULL if skipped.
   */
  public function create(string $source_path, string $target_path, int $status_code = 301, bool $skip_existing = TRUE, ?string $langcode = NULL): mixed {
    $source_path = ltrim($source_path, '/');
    $langcode ??= LanguageInterface::LANGCODE_NOT_SPECIFIED;

    if ($skip_existing) {
      $existing = $this->loadRedirects($source_path, $langcode);
      if ($existing !== []) {
        $this->reporter->skipped($this->t('Redirect from "@source" already exists - skipped.', [
          '@source' => $source_path,
        ]));

        return reset($existing);
      }
    }

    $redirect = $this->saveRedirect($source_path, $target_path, $status_code, $langcode);

    $this->reporter->created($this->t('Created @code redirect: "@source" -> "@target".', [
      '@code' => $status_code,
      '@source' => $source_path,
      '@target' => $target_path,
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
    $this->reporter->deleted($this->t('Deleted @count redirects for "@source".', [
      '@count' => $count,
      '@source' => $source_path,
    ]), $count);

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
    }, status: Reporter::DELETED);
  }

  /**
   * Export all redirects to a CSV file.
   *
   * Writes a headerless CSV with columns: source path, target path, HTTP status
   * code, and language code. The target is written without the 'internal:'
   * scheme so the file re-imports cleanly through importFromCsv().
   *
   * @code
   * Helper::redirect()->exportToCsv('/path/to/redirects.csv');
   * @endcode
   *
   * @param string $file_path
   *   Absolute path to the CSV file to write.
   *
   * @return string
   *   Status message describing how many redirects were exported.
   *
   * @throws \RuntimeException
   *   If the file cannot be written.
   */
  public function exportToCsv(string $file_path): string {
    $storage = $this->entityTypeManager->getStorage('redirect');

    $ids = array_values($storage->getQuery()->accessCheck(FALSE)->sort('rid')->execute());

    if (!is_writable(dirname($file_path))) {
      throw new \RuntimeException(sprintf('Cannot write CSV file: %s', $file_path));
    }

    $handle = fopen($file_path, 'w');

    // @codeCoverageIgnoreStart
    if ($handle === FALSE) {
      throw new \RuntimeException(sprintf('Cannot open CSV file for writing: %s', $file_path));
    }
    // @codeCoverageIgnoreEnd
    $count = 0;

    foreach (array_chunk($ids, max(1, $this->batchSize)) as $chunk) {
      foreach ($storage->loadMultiple($chunk) as $redirect) {
        if (!$redirect instanceof ContentEntityInterface) {
          continue;
        }

        fputcsv($handle, $this->toCsvRow($redirect), escape: '\\');
        $count++;
      }
    }

    fclose($handle);

    $message = $this->t('Exported @count redirects to @file.', [
      '@count' => $count,
      '@file' => $file_path,
    ]);
    $this->reporter->message($message);

    return (string) $message;
  }

  /**
   * Import redirects from a CSV file.
   *
   * CSV format (no header row):
   * - Column 1: Source path (e.g., 'old-page')
   * - Column 2: Target path (e.g., '/new-page' or 'https://example.com')
   * - Column 3: (optional) HTTP status code (default: 301)
   * - Column 4: (optional) Language code (default: all languages)
   *
   * Each row is created, updated when a redirect for the same source and
   * language already exists with a different target or status code, or skipped
   * when it is unchanged. Malformed rows are reported with their line number
   * and counted as failed while the rest of the import continues. The returned
   * message summarizes the created / updated / skipped / failed tallies.
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
    return $this->batchCsv($file_path, function (array $row): string {
      [$source, $target, $status_code, $langcode] = $this->validateImportRow($row);

      return $this->upsert($source, $target, $status_code, $langcode);
    });
  }

  /**
   * Delete redirects listed in a CSV file.
   *
   * Reads the same CSV format as importFromCsv(), but only the source path and
   * optional language columns are used to locate the redirects to delete. Rows
   * with no matching redirect are skipped; malformed rows are reported with
   * their line number and counted as failed while the rest continues.
   *
   * @code
   * Helper::redirect()->deleteFromCsv('/path/to/remove.csv');
   *
   * // With sandbox for large files:
   * function my_module_deploy_001(array &$sandbox): ?string {
   *   return Helper::redirect($sandbox)->deleteFromCsv('/path/to/remove.csv');
   * }
   * @endcode
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
  public function deleteFromCsv(string $file_path): ?string {
    return $this->batchCsv($file_path, function (array $row): string {
      [$source, $langcode] = $this->validateDeleteRow($row);

      return $this->deleteRow($source, $langcode);
    });
  }

  /**
   * Parse, validate and batch-process a redirect CSV file.
   *
   * Shared skeleton for importFromCsv() and deleteFromCsv(): the file is read
   * once on the first call and each row is handed to the per-row handler. A
   * handler that throws marks that row as failed - reported with its line
   * number - without aborting the run. Per-status counts are accumulated in
   * the sandbox, so a resumed batch still reports the whole run, not only the
   * last chunk.
   *
   * @param string $file_path
   *   Absolute path to the CSV file.
   * @param callable $handler
   *   Per-row callback receiving a parsed row record and returning the outcome
   *   status. It may throw to mark the row as failed.
   *
   * @return string|null
   *   Summary message when finished, or NULL while in progress (sandbox mode).
   */
  protected function batchCsv(string $file_path, callable $handler): ?string {
    if (!isset($this->sandbox['items'])) {
      $rows = $this->parseRows($file_path);

      if ($this->sandbox === NULL) {
        $this->csvTally = [];
      }
    }
    else {
      $rows = [];
    }

    $process = function (array $row) use ($handler): void {
      try {
        $status = (string) $handler($row);
      }
      catch (\Throwable $exception) {
        $this->reporter->failed($this->t('Line @line: @message', [
          '@line' => $row['line'],
          '@message' => $exception->getMessage(),
        ]));
        $status = Reporter::FAILED;
      }

      $this->bumpTally($status);
    };

    $result = $this->batch($rows, $process, 'redirects', status: NULL);

    return $result === NULL ? NULL : $this->summarizeCsvTally();
  }

  /**
   * Read a CSV file into row records tagged with their line number.
   *
   * @param string $file_path
   *   Absolute path to the CSV file.
   *
   * @return array<int, array<string, int|string>>
   *   One record per non-blank line, in file order, each with 'line', 'source',
   *   'target', 'status_code' and 'language' keys.
   *
   * @throws \RuntimeException
   *   If the file cannot be read or opened.
   */
  protected function parseRows(string $file_path): array {
    if (!is_readable($file_path)) {
      throw new \RuntimeException(sprintf('Cannot read CSV file: %s', $file_path));
    }

    $handle = fopen($file_path, 'r');

    if ($handle === FALSE) {
      throw new \RuntimeException(sprintf('Cannot open CSV file: %s', $file_path));
    }

    $rows = [];
    $line = 0;

    while (($columns = fgetcsv($handle, escape: '\\')) !== FALSE) {
      $line++;

      $has_value = (bool) array_filter($columns, static fn($value): bool => trim((string) $value) !== '');
      if (!$has_value) {
        continue;
      }

      $rows[] = [
        'line' => $line,
        'source' => $columns[0] ?? '',
        'target' => $columns[1] ?? '',
        'status_code' => $columns[2] ?? '',
        'language' => $columns[3] ?? '',
      ];
    }

    fclose($handle);

    return $rows;
  }

  /**
   * Validate and normalize a row for import.
   *
   * @param array $row
   *   A row record from parseRows().
   *
   * @return array{0: string, 1: string, 2: int, 3: string}
   *   Tuple of source path, target path, status code and language code.
   *
   * @throws \RuntimeException
   *   If the row is missing a source or target, or has an invalid status code.
   */
  protected function validateImportRow(array $row): array {
    $source = trim((string) $row['source']);
    if ($source === '') {
      throw new \RuntimeException('missing source path');
    }

    $target = trim((string) $row['target']);
    if ($target === '') {
      throw new \RuntimeException('missing target path');
    }

    return [
      $source,
      $target,
      $this->parseStatusCode((string) $row['status_code']),
      $this->parseLanguage((string) $row['language']),
    ];
  }

  /**
   * Validate and normalize a row for deletion.
   *
   * @param array $row
   *   A row record from parseRows().
   *
   * @return array{0: string, 1: string}
   *   Tuple of source path and language code.
   *
   * @throws \RuntimeException
   *   If the row is missing a source path.
   */
  protected function validateDeleteRow(array $row): array {
    $source = trim((string) $row['source']);
    if ($source === '') {
      throw new \RuntimeException('missing source path');
    }

    return [$source, $this->parseLanguage((string) $row['language'])];
  }

  /**
   * Parse a status code column value.
   *
   * @param string $value
   *   The raw column value.
   *
   * @return int
   *   The status code, defaulting to 301 when the column is empty.
   *
   * @throws \RuntimeException
   *   If the value is not a 3xx redirect status code.
   */
  protected function parseStatusCode(string $value): int {
    $value = trim($value);

    if ($value === '') {
      return 301;
    }

    if (!ctype_digit($value)) {
      throw new \RuntimeException(sprintf('invalid status code "%s"', $value));
    }

    $code = (int) $value;

    if ($code < 300 || $code > 399) {
      throw new \RuntimeException(sprintf('status code "%s" is out of the 3xx range', $value));
    }

    return $code;
  }

  /**
   * Parse a language column value.
   *
   * @param string $value
   *   The raw column value.
   *
   * @return string
   *   The language code, defaulting to language-neutral when the column is
   *   empty.
   */
  protected function parseLanguage(string $value): string {
    $value = trim($value);

    return $value === '' ? LanguageInterface::LANGCODE_NOT_SPECIFIED : $value;
  }

  /**
   * Load redirect entities matching a source path and language.
   *
   * @param string $source_path
   *   Source path (without leading slash).
   * @param string $langcode
   *   Language code to match.
   *
   * @return array<int, \Drupal\Core\Entity\EntityInterface>
   *   Matching redirect entities, keyed by entity ID.
   */
  protected function loadRedirects(string $source_path, string $langcode): array {
    return $this->entityTypeManager->getStorage('redirect')->loadByProperties([
      'redirect_source__path' => ltrim($source_path, '/'),
      'language' => $langcode,
    ]);
  }

  /**
   * Build and save a new redirect entity.
   *
   * @param string $source_path
   *   Source path (without leading slash).
   * @param string $target_path
   *   Redirect target path.
   * @param int $status_code
   *   HTTP status code.
   * @param string $langcode
   *   Language code the redirect applies to.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The saved redirect entity.
   */
  protected function saveRedirect(string $source_path, string $target_path, int $status_code, string $langcode): ContentEntityInterface {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $redirect */
    $redirect = $this->entityTypeManager->getStorage('redirect')->create([
      'redirect_source' => ['path' => ltrim($source_path, '/')],
      'redirect_redirect' => ['uri' => $this->pathToUri($target_path)],
      'status_code' => $status_code,
      'language' => $langcode,
    ]);
    $redirect->save();

    return $redirect;
  }

  /**
   * Create, update or skip a single redirect from an import row.
   *
   * @param string $source_path
   *   Source path (without leading slash).
   * @param string $target_path
   *   Redirect target path.
   * @param int $status_code
   *   HTTP status code.
   * @param string $langcode
   *   Language code the redirect applies to.
   *
   * @return string
   *   The outcome status: created, updated or skipped.
   */
  protected function upsert(string $source_path, string $target_path, int $status_code, string $langcode): string {
    $source_path = ltrim($source_path, '/');
    $existing = $this->loadRedirects($source_path, $langcode);

    if ($existing === []) {
      $this->saveRedirect($source_path, $target_path, $status_code, $langcode);
      $this->reporter->created($this->t('Created @code redirect: "@source" -> "@target".', [
        '@code' => $status_code,
        '@source' => $source_path,
        '@target' => $target_path,
      ]));

      return Reporter::CREATED;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $redirect */
    $redirect = reset($existing);
    $uri = $this->pathToUri($target_path);
    $changed = FALSE;

    if ($this->firstValue($redirect, 'redirect_redirect', 'uri') !== $uri) {
      $redirect->set('redirect_redirect', ['uri' => $uri]);
      $changed = TRUE;
    }

    if ((int) $this->firstValue($redirect, 'status_code', 'value') !== $status_code) {
      $redirect->set('status_code', $status_code);
      $changed = TRUE;
    }

    if (!$changed) {
      $this->reporter->skipped($this->t('Redirect from "@source" unchanged - skipped.', [
        '@source' => $source_path,
      ]));

      return Reporter::SKIPPED;
    }

    $redirect->save();

    $this->reporter->updated($this->t('Updated @code redirect: "@source" -> "@target".', [
      '@code' => $status_code,
      '@source' => $source_path,
      '@target' => $target_path,
    ]));

    return Reporter::UPDATED;
  }

  /**
   * Delete the redirects matching a delete row, or skip when none match.
   *
   * @param string $source_path
   *   Source path (without leading slash).
   * @param string $langcode
   *   Language code to match.
   *
   * @return string
   *   The outcome status: deleted or skipped.
   */
  protected function deleteRow(string $source_path, string $langcode): string {
    $source_path = ltrim($source_path, '/');
    $redirects = $this->loadRedirects($source_path, $langcode);

    if ($redirects === []) {
      $this->reporter->skipped($this->t('No redirect found for "@source" - skipped.', [
        '@source' => $source_path,
      ]));

      return Reporter::SKIPPED;
    }

    $this->entityTypeManager->getStorage('redirect')->delete($redirects);

    $count = count($redirects);
    $this->reporter->deleted($this->t('Deleted @count redirect(s) for "@source".', [
      '@count' => $count,
      '@source' => $source_path,
    ]), $count);

    return Reporter::DELETED;
  }

  /**
   * Render a redirect entity as a CSV row.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $redirect
   *   The redirect entity.
   *
   * @return array<int, string>
   *   Columns: source path, target path, status code, language code.
   */
  protected function toCsvRow(ContentEntityInterface $redirect): array {
    return [
      $this->firstValue($redirect, 'redirect_source', 'path'),
      $this->uriToPath($this->firstValue($redirect, 'redirect_redirect', 'uri')),
      $this->firstValue($redirect, 'status_code', 'value'),
      $this->firstValue($redirect, 'language', 'value'),
    ];
  }

  /**
   * Read a single field item property as a string.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to read from.
   * @param string $field
   *   The field name.
   * @param string $property
   *   The field item property to read (e.g., 'value', 'uri', 'path').
   *
   * @return string
   *   The property value cast to a string, or an empty string when absent.
   */
  protected function firstValue(ContentEntityInterface $entity, string $field, string $property): string {
    $values = $entity->get($field)->getValue();
    $first = $values[0] ?? [];

    return is_array($first) ? (string) ($first[$property] ?? '') : '';
  }

  /**
   * Convert a stored redirect URI back to an importable path.
   *
   * The inverse of pathToUri(): strips the 'internal:' scheme so internal
   * targets round-trip, while external, entity and route URIs pass through.
   *
   * @param string $uri
   *   The stored redirect URI.
   *
   * @return string
   *   The importable path.
   */
  protected function uriToPath(string $uri): string {
    if (str_starts_with($uri, 'internal:')) {
      return substr($uri, strlen('internal:'));
    }

    return $uri;
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

  /**
   * Increment the current CSV run tally for a status.
   *
   * The tally lives in the sandbox during a batched run so it survives resumed
   * chunks, and on the helper instance for a non-batched run.
   *
   * @param string $status
   *   The status to increment (one of the SUMMARY_STATUSES).
   */
  protected function bumpTally(string $status): void {
    $tally = $this->readTally();
    $tally[$status] = ($tally[$status] ?? 0) + 1;

    if ($this->sandbox === NULL) {
      $this->csvTally = $tally;

      return;
    }

    $this->sandbox['csv_tally'] = $tally;
  }

  /**
   * Read the current CSV run tally, defaulting every known status to zero.
   *
   * @return array<string, int>
   *   The per-status counts, keyed by status.
   */
  protected function readTally(): array {
    $stored = $this->sandbox === NULL ? $this->csvTally : ($this->sandbox['csv_tally'] ?? []);

    $tally = [];
    foreach (self::SUMMARY_STATUSES as $status) {
      $tally[$status] = is_array($stored) ? (int) ($stored[$status] ?? 0) : 0;
    }

    return $tally;
  }

  /**
   * Build a summary of the current CSV run tally.
   *
   * @return string
   *   A summary such as "Created 3, updated 1, skipped 2, failed 1.", or "No
   *   changes." when nothing was recorded.
   */
  protected function summarizeCsvTally(): string {
    $tally = $this->readTally();

    $segments = [];
    foreach (self::SUMMARY_STATUSES as $status) {
      if ($tally[$status] > 0) {
        $segments[] = $status . ' ' . $tally[$status];
      }
    }

    if ($segments === []) {
      return 'No changes.';
    }

    return ucfirst(implode(', ', $segments)) . '.';
  }

}
