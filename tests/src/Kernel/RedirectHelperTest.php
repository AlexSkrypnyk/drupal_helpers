<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Language\LanguageInterface;
use Drupal\drupal_helpers\Helpers\Redirect;
use Drupal\drupal_helpers\Report\Reporter;
use Drupal\KernelTests\KernelTestBase;
use Drupal\redirect\Entity\Redirect as RedirectEntity;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Kernel tests for the Redirect helper service.
 */
#[CoversClass(Redirect::class)]
#[RunTestsInSeparateProcesses]
class RedirectHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system', 'redirect', 'link', 'path_alias', 'user'];

  /**
   * The redirect helper service.
   */
  protected Redirect $redirectHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('redirect');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    $this->redirectHelper = $this->container->get('drupal_helpers.redirect');
  }

  /**
   * Tests that requiredModules returns the redirect module.
   */
  public function testRequiredModules(): void {
    $this->assertEquals(['redirect'], $this->redirectHelper->requiredModules());
  }

  /**
   * Tests creating a redirect.
   */
  public function testCreate(): void {
    $redirect = $this->redirectHelper->create('old-page', '/new-page');
    $this->assertInstanceOf(RedirectEntity::class, $redirect);
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $this->assertEquals('old-page', $redirect->get('redirect_source')->path);
    $this->assertEquals('internal:/new-page', $redirect->get('redirect_redirect')->uri);
    $this->assertEquals(301, $redirect->get('status_code')->value);
    $this->assertEquals(LanguageInterface::LANGCODE_NOT_SPECIFIED, $redirect->get('language')->value);
  }

  /**
   * Tests creating a redirect with a custom status code.
   */
  public function testCreateWithCustomStatusCode(): void {
    $redirect = $this->redirectHelper->create('old-page', '/new-page', 302);
    $this->assertInstanceOf(RedirectEntity::class, $redirect);
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $this->assertEquals(302, $redirect->get('status_code')->value);
  }

  /**
   * Tests creating a redirect for a specific language.
   */
  public function testCreateWithLanguage(): void {
    $french = $this->redirectHelper->create('old-page', '/nouvelle', 301, TRUE, 'fr');
    $this->assertInstanceOf(RedirectEntity::class, $french);
    /** @var \Drupal\redirect\Entity\Redirect $french */
    $this->assertEquals('fr', $french->get('language')->value);

    // A same-source redirect in a different language is a separate entity.
    $german = $this->redirectHelper->create('old-page', '/neue', 301, TRUE, 'de');
    $this->assertInstanceOf(RedirectEntity::class, $german);
    $this->assertNotEquals($french->id(), $german->id());

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $this->assertCount(2, $storage->loadByProperties(['redirect_source__path' => 'old-page']));

    // Re-creating the same language skips.
    $again = $this->redirectHelper->create('old-page', '/nouvelle', 301, TRUE, 'fr');
    $this->assertInstanceOf(RedirectEntity::class, $again);
    $this->assertEquals($french->id(), $again->id());
    $this->assertCount(2, $storage->loadByProperties(['redirect_source__path' => 'old-page']));
  }

  /**
   * Tests that creating the same redirect twice skips the duplicate.
   */
  public function testCreateSkipExisting(): void {
    $this->redirectHelper->create('old-page', '/new-page');
    $this->redirectHelper->create('old-page', '/new-page');

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $redirects = $storage->loadByProperties(['redirect_source__path' => 'old-page']);

    $this->assertCount(1, $redirects);
  }

  /**
   * Tests creating a redirect to an external URL.
   */
  public function testCreateExternalUrl(): void {
    $redirect = $this->redirectHelper->create('old-page', 'https://example.com');
    $this->assertInstanceOf(RedirectEntity::class, $redirect);
    $this->assertEquals('https://example.com', $redirect->get('redirect_redirect')->uri);
  }

  /**
   * Tests creating multiple redirects.
   */
  public function testCreateMultiple(): void {
    $count = $this->redirectHelper->createMultiple([
      ['source' => 'page-one', 'target' => '/target-one'],
      ['source' => 'page-two', 'target' => '/target-two'],
      ['source' => 'page-three', 'target' => '/target-three'],
    ]);

    $this->assertEquals(3, $count);

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $all = $storage->loadMultiple();
    $this->assertCount(3, $all);
  }

  /**
   * Tests deleting redirects by source path.
   */
  public function testDeleteBySource(): void {
    $this->redirectHelper->create('delete-me', '/target');
    $this->redirectHelper->create('keep-me', '/target');

    $count = $this->redirectHelper->deleteBySource('delete-me');

    $this->assertEquals(1, $count);

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $remaining = $storage->loadByProperties(['redirect_source__path' => 'delete-me']);
    $this->assertEmpty($remaining);

    $kept = $storage->loadByProperties(['redirect_source__path' => 'keep-me']);
    $this->assertCount(1, $kept);
  }

  /**
   * Tests deleting a non-existent source returns zero.
   */
  public function testDeleteBySourceNotFound(): void {
    $count = $this->redirectHelper->deleteBySource('nonexistent-path');

    $this->assertEquals(0, $count);
  }

  /**
   * Tests deleting all redirects.
   */
  public function testDeleteAll(): void {
    $this->redirectHelper->create('page-one', '/target-one');
    $this->redirectHelper->create('page-two', '/target-two');
    $this->redirectHelper->create('page-three', '/target-three');

    $result = $this->redirectHelper->deleteAll();

    $this->assertNotNull($result);

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $all = $storage->loadMultiple();
    /** @phpstan-ignore method.impossibleType */
    $this->assertEmpty($all);
  }

  /**
   * Tests deleting all redirects using sandbox batching.
   */
  public function testDeleteAllSandbox(): void {
    for ($i = 0; $i < 125; $i++) {
      $this->redirectHelper->create('page-' . $i, '/target-' . $i, 301, FALSE);
    }

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $this->assertCount(125, $storage->loadMultiple());

    $sandbox = [];
    $helper = clone $this->redirectHelper;
    $helper->setSandbox($sandbox);
    $helper->setBatchSize(50);

    do {
      $result = $helper->deleteAll();
    } while ($result === NULL);

    /** @phpstan-ignore method.alreadyNarrowedType */
    $this->assertNotNull($result);

    // Reset entity cache and verify all deleted.
    $storage->resetCache();
    $all = $storage->loadMultiple();
    /** @phpstan-ignore method.impossibleType */
    $this->assertEmpty($all);
  }

  /**
   * Tests exporting redirects to a CSV file.
   */
  public function testExportToCsv(): void {
    $this->redirectHelper->create('old-page', '/new-page');
    $this->redirectHelper->create('legacy', 'https://example.com', 302);
    $this->redirectHelper->create('vieux', '/nouveau', 301, TRUE, 'fr');

    $csv_path = $this->tempFile();
    $result = $this->redirectHelper->exportToCsv($csv_path);

    $this->assertStringContainsString('Exported 3 redirects', $result);
    $this->assertEquals([
      ['old-page', '/new-page', '301', 'und'],
      ['legacy', 'https://example.com', '302', 'und'],
      ['vieux', '/nouveau', '301', 'fr'],
    ], $this->readCsv($csv_path));

    unlink($csv_path);
  }

  /**
   * Tests exporting when there are no redirects writes an empty file.
   */
  public function testExportToCsvEmpty(): void {
    $csv_path = $this->tempFile();
    $result = $this->redirectHelper->exportToCsv($csv_path);

    $this->assertStringContainsString('Exported 0 redirects', $result);
    $this->assertEquals('', file_get_contents($csv_path));

    unlink($csv_path);
  }

  /**
   * Tests exporting to an unwritable location throws an exception.
   */
  public function testExportToCsvUnwritable(): void {
    $this->expectException(\RuntimeException::class);

    $this->redirectHelper->exportToCsv('/nonexistent-directory-' . uniqid() . '/redirects.csv');
  }

  /**
   * Tests that export then import reproduces the original redirects.
   */
  public function testExportImportRoundTrip(): void {
    $this->redirectHelper->create('a', '/target-a');
    $this->redirectHelper->create('b', 'https://example.com/b', 302);
    $this->redirectHelper->create('c', '/target-c', 301, TRUE, 'fr');

    $before = $this->snapshotRedirects();

    $csv_path = $this->tempFile();
    $this->redirectHelper->exportToCsv($csv_path);

    $this->redirectHelper->deleteAll();
    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $storage->resetCache();
    /** @phpstan-ignore method.impossibleType */
    $this->assertEmpty($storage->loadMultiple());

    $this->redirectHelper->importFromCsv($csv_path);
    $storage->resetCache();

    $this->assertEquals($before, $this->snapshotRedirects());

    unlink($csv_path);
  }

  /**
   * Tests importing redirects from a CSV file.
   */
  public function testImportFromCsv(): void {
    $csv_path = $this->writeCsv([
      ['csv-page-one', '/csv-target-one', '301'],
      ['csv-page-two', '/csv-target-two', '302'],
      ['csv-page-three', 'https://example.com', '301'],
    ]);

    $result = $this->redirectHelper->importFromCsv($csv_path);

    $this->assertNotNull($result);

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $all = $storage->loadMultiple();
    $this->assertCount(3, $all);

    unlink($csv_path);
  }

  /**
   * Tests importing redirects with a per-row language column.
   */
  public function testImportWithLanguageColumn(): void {
    $csv_path = $this->writeCsv([
      ['fr-page', '/fr-target', '301', 'fr'],
      ['de-page', '/de-target', '302', 'de'],
    ]);

    $result = $this->redirectHelper->importFromCsv($csv_path);
    $this->assertEquals('Created 2.', $result);

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $french = $storage->loadByProperties(['redirect_source__path' => 'fr-page', 'language' => 'fr']);
    $this->assertCount(1, $french);
    $redirect = reset($french);
    $this->assertInstanceOf(RedirectEntity::class, $redirect);
    $this->assertEquals('fr', $redirect->get('language')->value);
    $this->assertEquals(301, $redirect->get('status_code')->value);

    unlink($csv_path);
  }

  /**
   * Tests that importing an existing source updates the redirect.
   */
  public function testImportUpdatesExisting(): void {
    $this->redirectHelper->create('page', '/old-target', 301);

    $csv_path = $this->writeCsv([['page', '/new-target', '302']]);
    $result = $this->redirectHelper->importFromCsv($csv_path);
    $this->assertEquals('Updated 1.', $result);

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $storage->resetCache();
    $redirects = $storage->loadByProperties(['redirect_source__path' => 'page']);
    $this->assertCount(1, $redirects);
    $redirect = reset($redirects);
    $this->assertInstanceOf(RedirectEntity::class, $redirect);
    $this->assertEquals('internal:/new-target', $redirect->get('redirect_redirect')->uri);
    $this->assertEquals(302, $redirect->get('status_code')->value);

    unlink($csv_path);
  }

  /**
   * Tests that importing an unchanged redirect skips it.
   */
  public function testImportSkipsUnchanged(): void {
    $this->redirectHelper->create('page', '/target', 301);

    $csv_path = $this->writeCsv([['page', '/target', '301']]);
    $result = $this->redirectHelper->importFromCsv($csv_path);
    $this->assertEquals('Skipped 1.', $result);

    unlink($csv_path);
  }

  /**
   * Tests that malformed rows are reported with line numbers and skipped.
   */
  public function testImportMalformedRows(): void {
    $csv_path = $this->writeCsv([
      ['good-1', '/target-1'],
      ['', '/target-2'],
      ['good-3', ''],
      ['good-4', '/target-4', 'abc'],
      ['good-5', '/target-5', '404'],
      ['good-6', '/target-6', '302'],
      ['', '', ''],
    ]);

    $result = $this->redirectHelper->importFromCsv($csv_path);
    $this->assertEquals('Created 2, failed 4.', $result);

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $this->assertCount(2, $storage->loadMultiple());

    $messages = $this->reporterMessages();
    $this->assertStringContainsString('Line 2: missing source path', $messages);
    $this->assertStringContainsString('Line 3: missing target path', $messages);
    $this->assertStringContainsString('Line 4', $messages);
    $this->assertStringContainsString('Line 5', $messages);

    unlink($csv_path);
  }

  /**
   * Tests the created / updated / skipped / failed summary in one import.
   */
  public function testImportSummaryTallies(): void {
    $this->redirectHelper->create('existing-1', '/old', 301);
    $this->redirectHelper->create('existing-2', '/same', 301);

    $csv_path = $this->writeCsv([
      ['new-1', '/new-target'],
      ['existing-1', '/updated-target'],
      ['existing-2', '/same'],
      ['', '/x'],
    ]);

    $result = $this->redirectHelper->importFromCsv($csv_path);
    $this->assertEquals('Created 1, updated 1, skipped 1, failed 1.', $result);

    unlink($csv_path);
  }

  /**
   * Tests importing redirects from a CSV file using sandbox batching.
   */
  public function testImportFromCsvSandbox(): void {
    $rows = [];
    for ($i = 0; $i < 125; $i++) {
      $rows[] = ['csv-sandbox-page-' . $i, '/csv-sandbox-target-' . $i, '301'];
    }
    $csv_path = $this->writeCsv($rows);

    $sandbox = [];
    $helper = clone $this->redirectHelper;
    $helper->setSandbox($sandbox);
    $helper->setBatchSize(50);

    do {
      $result = $helper->importFromCsv($csv_path);
    } while ($result === NULL);

    /** @phpstan-ignore method.alreadyNarrowedType */
    $this->assertNotNull($result);

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $all = $storage->loadMultiple();
    $this->assertCount(125, $all);

    unlink($csv_path);
  }

  /**
   * Tests importing from a missing CSV file throws an exception.
   */
  public function testImportFromCsvMissingFile(): void {
    $this->expectException(\RuntimeException::class);

    $this->redirectHelper->importFromCsv('/nonexistent/path/to/file.csv');
  }

  /**
   * Tests deleting redirects listed in a CSV file.
   */
  public function testDeleteFromCsv(): void {
    $this->redirectHelper->create('remove-1', '/target-1');
    $this->redirectHelper->create('remove-2', '/target-2');
    $this->redirectHelper->create('keep', '/target-keep');

    $csv_path = $this->writeCsv([
      ['remove-1'],
      ['remove-2'],
    ]);

    $result = $this->redirectHelper->deleteFromCsv($csv_path);
    $this->assertEquals('Deleted 2.', $result);

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $storage->resetCache();
    $this->assertCount(1, $storage->loadMultiple());
    $this->assertCount(1, $storage->loadByProperties(['redirect_source__path' => 'keep']));

    unlink($csv_path);
  }

  /**
   * Tests that deleting a source with no redirect is skipped.
   */
  public function testDeleteFromCsvNotFound(): void {
    $csv_path = $this->writeCsv([['ghost']]);

    $result = $this->redirectHelper->deleteFromCsv($csv_path);
    $this->assertEquals('Skipped 1.', $result);

    unlink($csv_path);
  }

  /**
   * Tests deleting a single language's redirect via a CSV language column.
   */
  public function testDeleteFromCsvWithLanguage(): void {
    $this->redirectHelper->create('page', '/en-target', 301, TRUE, 'en');
    $this->redirectHelper->create('page', '/fr-target', 301, TRUE, 'fr');

    $csv_path = $this->writeCsv([['page', '', '', 'fr']]);

    $result = $this->redirectHelper->deleteFromCsv($csv_path);
    $this->assertEquals('Deleted 1.', $result);

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $storage->resetCache();
    $remaining = $storage->loadByProperties(['redirect_source__path' => 'page']);
    $this->assertCount(1, $remaining);
    $redirect = reset($remaining);
    $this->assertInstanceOf(RedirectEntity::class, $redirect);
    $this->assertEquals('en', $redirect->get('language')->value);

    unlink($csv_path);
  }

  /**
   * Tests deleting redirects from a CSV file using sandbox batching.
   */
  public function testDeleteFromCsvSandbox(): void {
    $rows = [];
    for ($i = 0; $i < 125; $i++) {
      $this->redirectHelper->create('page-' . $i, '/target-' . $i, 301, FALSE);
      $rows[] = ['page-' . $i];
    }
    $csv_path = $this->writeCsv($rows);

    $sandbox = [];
    $helper = clone $this->redirectHelper;
    $helper->setSandbox($sandbox);
    $helper->setBatchSize(50);

    do {
      $result = $helper->deleteFromCsv($csv_path);
    } while ($result === NULL);

    /** @phpstan-ignore method.alreadyNarrowedType */
    $this->assertNotNull($result);
    $this->assertStringContainsString('Deleted 125', $result);

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $storage->resetCache();
    /** @phpstan-ignore method.impossibleType */
    $this->assertEmpty($storage->loadMultiple());

    unlink($csv_path);
  }

  /**
   * Tests path to URI conversion indirectly via create().
   */
  public function testPathToUri(): void {
    // Internal path without leading slash gets prefixed.
    $redirect = $this->redirectHelper->create('source-one', 'target-page', 301, FALSE);
    $this->assertInstanceOf(RedirectEntity::class, $redirect);
    $this->assertEquals('internal:/target-page', $redirect->get('redirect_redirect')->uri);

    // Internal path with leading slash.
    $redirect = $this->redirectHelper->create('source-two', '/target-page', 301, FALSE);
    $this->assertInstanceOf(RedirectEntity::class, $redirect);
    $this->assertEquals('internal:/target-page', $redirect->get('redirect_redirect')->uri);

    // External URL passes through unchanged.
    $redirect = $this->redirectHelper->create('source-three', 'https://example.com', 301, FALSE);
    $this->assertInstanceOf(RedirectEntity::class, $redirect);
    $this->assertEquals('https://example.com', $redirect->get('redirect_redirect')->uri);

    // Already-prefixed URI passes through unchanged.
    $redirect = $this->redirectHelper->create('source-four', 'internal:/already-prefixed', 301, FALSE);
    $this->assertInstanceOf(RedirectEntity::class, $redirect);
    $this->assertEquals('internal:/already-prefixed', $redirect->get('redirect_redirect')->uri);

    // Entity URI passes through unchanged.
    $redirect = $this->redirectHelper->create('source-five', 'entity:node/1', 301, FALSE);
    $this->assertInstanceOf(RedirectEntity::class, $redirect);
    $this->assertEquals('entity:node/1', $redirect->get('redirect_redirect')->uri);

    // Route URI passes through unchanged.
    $redirect = $this->redirectHelper->create('source-six', 'route:<front>', 301, FALSE);
    $this->assertInstanceOf(RedirectEntity::class, $redirect);
    $this->assertEquals('route:<front>', $redirect->get('redirect_redirect')->uri);
  }

  /**
   * The shared reporter service.
   */
  protected function reporter(): Reporter {
    return $this->container->get('drupal_helpers.reporter');
  }

  /**
   * All reporter message texts joined into a single string.
   */
  protected function reporterMessages(): string {
    $texts = array_map(static fn(array $message): string => $message['message'], $this->reporter()->messages());

    return implode("\n", $texts);
  }

  /**
   * A normalized, source-sorted snapshot of every stored redirect.
   *
   * @return array<int, array<string, int|string>>
   *   One record per redirect with source, uri, status_code and language.
   */
  protected function snapshotRedirects(): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');

    $snapshot = [];
    foreach ($storage->loadMultiple() as $redirect) {
      /** @var \Drupal\redirect\Entity\Redirect $redirect */
      $snapshot[] = [
        'source' => (string) $redirect->get('redirect_source')->path,
        'uri' => (string) $redirect->get('redirect_redirect')->uri,
        'status_code' => (int) $redirect->get('status_code')->value,
        'language' => (string) $redirect->get('language')->value,
      ];
    }

    usort($snapshot, static fn(array $a, array $b): int => strcmp((string) $a['source'], (string) $b['source']));

    return $snapshot;
  }

  /**
   * Write rows to a temporary CSV file and return its path.
   *
   * @param array<int, array<int, string>> $rows
   *   CSV rows.
   *
   * @return string
   *   Path to the written CSV file.
   */
  protected function writeCsv(array $rows): string {
    $csv_path = $this->tempFile();

    $handle = fopen($csv_path, 'w');
    $this->assertIsResource($handle);

    foreach ($rows as $row) {
      fputcsv($handle, fields: $row, escape: '\\');
    }

    fclose($handle);

    return $csv_path;
  }

  /**
   * Read a CSV file into an array of string-column rows.
   *
   * @param string $path
   *   Path to the CSV file.
   *
   * @return array<int, array<int, string>>
   *   The parsed rows.
   */
  protected function readCsv(string $path): array {
    $rows = [];

    $handle = fopen($path, 'r');
    $this->assertIsResource($handle);

    while (($columns = fgetcsv($handle, escape: '\\')) !== FALSE) {
      $rows[] = array_map(static fn($value): string => (string) $value, $columns);
    }

    fclose($handle);

    return $rows;
  }

  /**
   * Build a unique temporary CSV file path.
   */
  protected function tempFile(): string {
    $directory = $this->container->get('file_system')->getTempDirectory();

    return $directory . '/dh_redirect_' . uniqid() . '.csv';
  }

}
