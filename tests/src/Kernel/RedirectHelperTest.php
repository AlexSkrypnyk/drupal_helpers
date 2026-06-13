<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use Drupal\drupal_helpers\Helpers\Redirect;
use Drupal\KernelTests\KernelTestBase;
use Drupal\redirect\Entity\Redirect as RedirectEntity;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Kernel tests for the Redirect helper service.
 */
#[CoversClass(Redirect::class)]
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
   * Tests importing redirects from a CSV file.
   */
  public function testImportFromCsv(): void {
    $csv_path = sys_get_temp_dir() . '/test_redirects_' . uniqid() . '.csv';
    $handle = fopen($csv_path, 'w');
    $this->assertIsResource($handle);
    fputcsv($handle, fields: ['csv-page-one', '/csv-target-one', '301'], escape: '\\');
    fputcsv($handle, fields: ['csv-page-two', '/csv-target-two', '302'], escape: '\\');
    fputcsv($handle, fields: ['csv-page-three', 'https://example.com', '301'], escape: '\\');
    fclose($handle);

    $result = $this->redirectHelper->importFromCsv($csv_path);

    $this->assertNotNull($result);

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $all = $storage->loadMultiple();
    $this->assertCount(3, $all);

    unlink($csv_path);
  }

  /**
   * Tests importing redirects from a CSV file using sandbox batching.
   */
  public function testImportFromCsvSandbox(): void {
    $csv_path = sys_get_temp_dir() . '/test_redirects_sandbox_' . uniqid() . '.csv';
    $handle = fopen($csv_path, 'w');
    $this->assertIsResource($handle);
    for ($i = 0; $i < 125; $i++) {
      fputcsv($handle, fields: ['csv-sandbox-page-' . $i, '/csv-sandbox-target-' . $i, '301'], escape: '\\');
    }
    fclose($handle);

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

}
