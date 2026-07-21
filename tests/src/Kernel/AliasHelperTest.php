<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Language\LanguageInterface;
use Drupal\drupal_helpers\Helpers\Alias;
use Drupal\KernelTests\KernelTestBase;
use Drupal\path_alias\PathAliasInterface;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Kernel tests for the Alias helper service.
 */
#[CoversClass(Alias::class)]
#[RunTestsInSeparateProcesses]
class AliasHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system', 'path_alias', 'user'];

  /**
   * The alias helper service.
   */
  protected Alias $aliasHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    $this->aliasHelper = $this->container->get('drupal_helpers.alias');
  }

  /**
   * Tests that the helper declares no required modules.
   */
  public function testRequiredModules(): void {
    $this->assertEquals([], $this->aliasHelper->requiredModules());
  }

  /**
   * Tests creating an alias.
   */
  public function testCreate(): void {
    $alias = $this->aliasHelper->create('/node/1', '/about-us');

    $this->assertInstanceOf(PathAliasInterface::class, $alias);
    $this->assertEquals('/node/1', $alias->getPath());
    $this->assertEquals('/about-us', $alias->getAlias());
    $this->assertEquals(LanguageInterface::LANGCODE_NOT_SPECIFIED, $alias->get('langcode')->value);
  }

  /**
   * Tests creating an alias with an explicit langcode.
   */
  public function testCreateWithLangcode(): void {
    $alias = $this->aliasHelper->create('/node/2', '/a-propos', 'fr');

    $this->assertInstanceOf(PathAliasInterface::class, $alias);
    $this->assertEquals('fr', $alias->get('langcode')->value);
  }

  /**
   * Tests that paths and aliases are normalized with a leading slash.
   */
  public function testCreateNormalizesPaths(): void {
    $alias = $this->aliasHelper->create('node/3', 'about');

    $this->assertInstanceOf(PathAliasInterface::class, $alias);
    $this->assertEquals('/node/3', $alias->getPath());
    $this->assertEquals('/about', $alias->getAlias());
  }

  /**
   * Tests that creating the same alias twice skips the duplicate.
   */
  public function testCreateSkipExisting(): void {
    $first = $this->aliasHelper->create('/node/1', '/about-us');
    $second = $this->aliasHelper->create('/node/1', '/about-us');

    $this->assertInstanceOf(PathAliasInterface::class, $first);
    $this->assertInstanceOf(PathAliasInterface::class, $second);
    $this->assertEquals($first->id(), $second->id());

    $storage = $this->container->get('entity_type.manager')->getStorage('path_alias');
    $aliases = $storage->loadByProperties(['path' => '/node/1']);
    $this->assertCount(1, $aliases);
  }

  /**
   * Tests that reusing an alias string for another path is skipped.
   */
  public function testCreateSkipExistingAlias(): void {
    $first = $this->aliasHelper->create('/node/1', '/about-us');
    $second = $this->aliasHelper->create('/node/2', '/about-us');

    $this->assertInstanceOf(PathAliasInterface::class, $first);
    $this->assertInstanceOf(PathAliasInterface::class, $second);
    $this->assertEquals($first->id(), $second->id());

    $storage = $this->container->get('entity_type.manager')->getStorage('path_alias');
    $this->assertCount(1, $storage->loadByProperties(['alias' => '/about-us']));
    $this->assertNull($this->aliasHelper->findByPath('/node/2'));
  }

  /**
   * Tests creating multiple aliases.
   */
  public function testCreateMultiple(): void {
    $count = $this->aliasHelper->createMultiple([
      ['path' => '/node/1', 'alias' => '/about-us'],
      ['path' => '/node/2', 'alias' => '/contact'],
      ['path' => '/node/3', 'alias' => '/a-propos', 'langcode' => 'fr'],
    ]);

    $this->assertEquals(3, $count);

    $storage = $this->container->get('entity_type.manager')->getStorage('path_alias');
    $this->assertCount(3, $storage->loadMultiple());
  }

  /**
   * Tests finding an alias by system path.
   */
  public function testFindByPath(): void {
    $this->aliasHelper->create('/node/1', '/about-us');

    $found = $this->aliasHelper->findByPath('/node/1');
    $this->assertInstanceOf(PathAliasInterface::class, $found);
    $this->assertEquals('/about-us', $found->getAlias());

    $this->assertNull($this->aliasHelper->findByPath('/node/999'));
  }

  /**
   * Tests finding an alias by its alias string.
   */
  public function testFindByAlias(): void {
    $this->aliasHelper->create('/node/1', '/about-us');

    $found = $this->aliasHelper->findByAlias('/about-us');
    $this->assertInstanceOf(PathAliasInterface::class, $found);
    $this->assertEquals('/node/1', $found->getPath());

    $this->assertNull($this->aliasHelper->findByAlias('/nonexistent'));
  }

  /**
   * Tests renaming an alias by system path.
   */
  public function testUpdateByPath(): void {
    $this->aliasHelper->create('/node/1', '/about');

    $updated = $this->aliasHelper->updateByPath('/node/1', '/about-us');
    $this->assertInstanceOf(PathAliasInterface::class, $updated);
    $this->assertEquals('/about-us', $updated->getAlias());
    $this->assertEquals('/node/1', $updated->getPath());

    $this->assertNull($this->aliasHelper->updateByPath('/node/999', '/nothing'));
  }

  /**
   * Tests retargeting an alias to a new system path.
   */
  public function testUpdateByAlias(): void {
    $this->aliasHelper->create('/node/1', '/about-us');

    $updated = $this->aliasHelper->updateByAlias('/about-us', '/node/5');
    $this->assertInstanceOf(PathAliasInterface::class, $updated);
    $this->assertEquals('/node/5', $updated->getPath());
    $this->assertEquals('/about-us', $updated->getAlias());

    $this->assertNull($this->aliasHelper->updateByAlias('/nonexistent', '/node/1'));
  }

  /**
   * Tests deleting aliases by system path.
   */
  public function testDeleteByPath(): void {
    $this->aliasHelper->create('/node/1', '/delete-me');
    $this->aliasHelper->create('/node/2', '/keep-me');

    $count = $this->aliasHelper->deleteByPath('/node/1');
    $this->assertEquals(1, $count);

    $this->assertNull($this->aliasHelper->findByPath('/node/1'));
    $this->assertInstanceOf(PathAliasInterface::class, $this->aliasHelper->findByPath('/node/2'));

    $this->assertEquals(0, $this->aliasHelper->deleteByPath('/node/999'));
  }

  /**
   * Tests deleting aliases by their alias string.
   */
  public function testDeleteByAlias(): void {
    $this->aliasHelper->create('/node/1', '/delete-me');
    $this->aliasHelper->create('/node/2', '/keep-me');

    $count = $this->aliasHelper->deleteByAlias('/delete-me');
    $this->assertEquals(1, $count);

    $this->assertNull($this->aliasHelper->findByAlias('/delete-me'));
    $this->assertInstanceOf(PathAliasInterface::class, $this->aliasHelper->findByAlias('/keep-me'));

    $this->assertEquals(0, $this->aliasHelper->deleteByAlias('/nonexistent'));
  }

  /**
   * Tests deleting all aliases.
   */
  public function testDeleteAll(): void {
    $this->aliasHelper->create('/node/1', '/one');
    $this->aliasHelper->create('/node/2', '/two');
    $this->aliasHelper->create('/node/3', '/three');

    $result = $this->aliasHelper->deleteAll();
    $this->assertNotNull($result);

    $storage = $this->container->get('entity_type.manager')->getStorage('path_alias');
    /** @phpstan-ignore method.impossibleType */
    $this->assertEmpty($storage->loadMultiple());
  }

  /**
   * Tests deleting all aliases using sandbox batching.
   */
  public function testDeleteAllSandbox(): void {
    for ($i = 0; $i < 125; $i++) {
      $this->aliasHelper->create('/node/' . $i, '/alias-' . $i);
    }

    $storage = $this->container->get('entity_type.manager')->getStorage('path_alias');
    $this->assertCount(125, $storage->loadMultiple());

    $sandbox = [];
    $helper = clone $this->aliasHelper;
    $helper->setSandbox($sandbox);
    $helper->setBatchSize(50);

    do {
      $result = $helper->deleteAll();
    } while ($result === NULL);

    /** @phpstan-ignore method.alreadyNarrowedType */
    $this->assertNotNull($result);

    $storage->resetCache();
    /** @phpstan-ignore method.impossibleType */
    $this->assertEmpty($storage->loadMultiple());
  }

  /**
   * Tests importing aliases from a CSV file.
   */
  public function testImportFromCsv(): void {
    $csv_path = $this->writeCsv([
      ['/node/1', '/about-us'],
      ['/node/2', '/contact'],
      ['/node/3', '/a-propos', 'fr'],
      ['', '', ''],
    ]);

    $result = $this->aliasHelper->importFromCsv($csv_path);
    $this->assertNotNull($result);

    $storage = $this->container->get('entity_type.manager')->getStorage('path_alias');
    $this->assertCount(3, $storage->loadMultiple());

    $french = $this->aliasHelper->findByAlias('/a-propos', 'fr');
    $this->assertInstanceOf(PathAliasInterface::class, $french);

    unlink($csv_path);
  }

  /**
   * Tests importing aliases from a CSV file using sandbox batching.
   */
  public function testImportFromCsvSandbox(): void {
    $rows = [];
    for ($i = 0; $i < 125; $i++) {
      $rows[] = ['/node/' . $i, '/alias-' . $i];
    }
    $csv_path = $this->writeCsv($rows);

    $sandbox = [];
    $helper = clone $this->aliasHelper;
    $helper->setSandbox($sandbox);
    $helper->setBatchSize(50);

    do {
      $result = $helper->importFromCsv($csv_path);
    } while ($result === NULL);

    /** @phpstan-ignore method.alreadyNarrowedType */
    $this->assertNotNull($result);

    $storage = $this->container->get('entity_type.manager')->getStorage('path_alias');
    $this->assertCount(125, $storage->loadMultiple());

    unlink($csv_path);
  }

  /**
   * Tests importing from a missing CSV file throws an exception.
   */
  public function testImportFromCsvMissingFile(): void {
    $this->expectException(\RuntimeException::class);

    $this->aliasHelper->importFromCsv('/nonexistent/path/to/file.csv');
  }

  /**
   * Tests language-specific create, find, and delete.
   */
  public function testMultilingual(): void {
    $this->aliasHelper->create('/node/1', '/about-us', 'en');
    $this->aliasHelper->create('/node/1', '/a-propos', 'fr');

    $storage = $this->container->get('entity_type.manager')->getStorage('path_alias');
    $this->assertCount(2, $storage->loadByProperties(['path' => '/node/1']));

    $english = $this->aliasHelper->findByPath('/node/1', 'en');
    $this->assertInstanceOf(PathAliasInterface::class, $english);
    $this->assertEquals('/about-us', $english->getAlias());

    $french = $this->aliasHelper->findByPath('/node/1', 'fr');
    $this->assertInstanceOf(PathAliasInterface::class, $french);
    $this->assertEquals('/a-propos', $french->getAlias());

    // A langcode-agnostic lookup still returns a match.
    $this->assertInstanceOf(PathAliasInterface::class, $this->aliasHelper->findByPath('/node/1'));

    // Deleting one language leaves the other intact.
    $count = $this->aliasHelper->deleteByPath('/node/1', 'fr');
    $this->assertEquals(1, $count);
    $this->assertNull($this->aliasHelper->findByPath('/node/1', 'fr'));
    $this->assertInstanceOf(PathAliasInterface::class, $this->aliasHelper->findByPath('/node/1', 'en'));
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
    $directory = $this->container->get('file_system')->getTempDirectory();
    $csv_path = $directory . '/dh_alias_' . uniqid() . '.csv';

    $handle = fopen($csv_path, 'w');
    $this->assertIsResource($handle);

    foreach ($rows as $row) {
      fputcsv($handle, fields: $row, escape: '\\');
    }

    fclose($handle);

    return $csv_path;
  }

}
