<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\drupal_helpers\Helpers\Entity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Kernel tests for the Entity helper service.
 */
#[CoversClass(Entity::class)]
#[RunTestsInSeparateProcesses]
class EntityHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system', 'node', 'user', 'field'];

  /**
   * The entity helper service.
   */
  protected Entity $entityHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    $this->entityHelper = $this->container->get('drupal_helpers.entity');
  }

  /**
   * Tests that requiredModules returns an empty array.
   */
  public function testRequiredModules(): void {
    $this->assertEquals([], $this->entityHelper->requiredModules());
  }

  /**
   * Tests creating a single entity.
   */
  public function testCreate(): void {
    $entity = $this->entityHelper->create('node', 'article', ['title' => 'Welcome']);

    $this->assertInstanceOf(NodeInterface::class, $entity);
    $this->assertEquals('article', $entity->bundle());
    $this->assertEquals('Welcome', $entity->label());
    $this->assertNotNull($entity->id());
  }

  /**
   * Tests that an identity key skips creating a duplicate.
   */
  public function testCreateSkipsDuplicateByIdentity(): void {
    $first = $this->entityHelper->create('node', 'article', ['title' => 'Welcome'], 'title');
    $second = $this->entityHelper->create('node', 'article', ['title' => 'Welcome'], 'title');

    $this->assertEquals($first->id(), $second->id());

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $this->assertCount(1, $storage->loadByProperties(['type' => 'article', 'title' => 'Welcome']));
  }

  /**
   * Tests that without an identity key duplicates are created.
   */
  public function testCreateWithoutIdentityAllowsDuplicate(): void {
    $this->entityHelper->create('node', 'article', ['title' => 'Welcome']);
    $this->entityHelper->create('node', 'article', ['title' => 'Welcome']);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $this->assertCount(2, $storage->loadByProperties(['type' => 'article', 'title' => 'Welcome']));
  }

  /**
   * Tests that identity duplicate detection is scoped to the bundle.
   */
  public function testCreateIdentityScopedToBundle(): void {
    $article = $this->entityHelper->create('node', 'article', ['title' => 'Shared'], 'title');
    $page = $this->entityHelper->create('node', 'page', ['title' => 'Shared'], 'title');

    $this->assertNotEquals($article->id(), $page->id());
    $this->assertEquals('article', $article->bundle());
    $this->assertEquals('page', $page->bundle());

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $this->assertCount(2, $storage->loadByProperties(['title' => 'Shared']));
  }

  /**
   * Tests creating multiple entities without sandbox.
   */
  public function testCreateMultiple(): void {
    $result = $this->entityHelper->createMultiple('node', 'article', [
      ['title' => 'One'],
      ['title' => 'Two'],
      ['title' => 'Three'],
    ]);

    $this->assertStringContainsString('Processed 3', $result);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $this->assertCount(3, $storage->loadByProperties(['type' => 'article']));
  }

  /**
   * Tests that bulk creation skips duplicates by identity.
   */
  public function testCreateMultipleSkipsDuplicates(): void {
    $this->entityHelper->createMultiple('node', 'article', [
      ['title' => 'Dup'],
      ['title' => 'Dup'],
      ['title' => 'Unique'],
    ], 'title');

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $this->assertCount(1, $storage->loadByProperties(['type' => 'article', 'title' => 'Dup']));
    $this->assertCount(2, $storage->loadByProperties(['type' => 'article']));
  }

  /**
   * Tests creating multiple entities with sandbox batching.
   */
  public function testCreateMultipleSandbox(): void {
    $rows = [];
    for ($i = 0; $i < 125; $i++) {
      $rows[] = ['title' => 'Node ' . $i];
    }

    $sandbox = [];
    $helper = clone $this->entityHelper;
    $helper->setSandbox($sandbox);
    $helper->setBatchSize(50);

    do {
      $result = $helper->createMultiple('node', 'article', $rows);
    } while ($result === NULL);

    /** @phpstan-ignore method.alreadyNarrowedType */
    $this->assertNotNull($result);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache();
    $this->assertCount(125, $storage->loadByProperties(['type' => 'article']));
  }

  /**
   * Tests updating entities matched by properties.
   */
  public function testUpdate(): void {
    for ($i = 0; $i < 3; $i++) {
      Node::create(['type' => 'article', 'title' => 'A' . $i, 'status' => 1])->save();
    }
    Node::create(['type' => 'page', 'title' => 'P', 'status' => 1])->save();

    $result = $this->entityHelper->update('node', ['type' => 'article'], ['status' => 0]);
    $this->assertStringContainsString('Processed 3', $result);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache();

    foreach ($storage->loadByProperties(['type' => 'article']) as $node) {
      $this->assertFalse($node->isPublished());
    }

    // Entities of another bundle are left untouched.
    $page = $storage->loadByProperties(['type' => 'page']);
    $page = reset($page);
    $this->assertInstanceOf(NodeInterface::class, $page);
    $this->assertTrue($page->isPublished());
  }

  /**
   * Tests that updating with no match returns a status message.
   */
  public function testUpdateNoMatch(): void {
    Node::create(['type' => 'article', 'title' => 'A'])->save();

    $result = $this->entityHelper->update('node', ['title' => 'Nonexistent'], ['status' => 0]);

    $this->assertStringContainsString('No node entities to process', $result);
  }

  /**
   * Tests updating entities with sandbox batching.
   */
  public function testUpdateSandbox(): void {
    for ($i = 0; $i < 125; $i++) {
      Node::create(['type' => 'article', 'title' => 'A' . $i, 'status' => 1])->save();
    }

    $sandbox = [];
    $helper = clone $this->entityHelper;
    $helper->setSandbox($sandbox);
    $helper->setBatchSize(50);

    do {
      $result = $helper->update('node', ['type' => 'article'], ['status' => 0]);
    } while ($result === NULL);

    /** @phpstan-ignore method.alreadyNarrowedType */
    $this->assertNotNull($result);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache();

    $nodes = $storage->loadByProperties(['type' => 'article']);
    $this->assertCount(125, $nodes);

    foreach ($nodes as $node) {
      $this->assertFalse($node->isPublished());
    }
  }

  /**
   * Tests deleting all nodes of a given type without sandbox.
   */
  public function testDeleteAllNonSandbox(): void {
    for ($i = 0; $i < 5; $i++) {
      Node::create(['type' => 'article', 'title' => 'Article ' . $i])->save();
    }

    $result = $this->entityHelper->deleteAll('node', 'article');

    $this->assertStringContainsString('Processed 5', $result);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $remaining = $storage->loadMultiple();
    $this->assertCount(0, $remaining);
  }

  /**
   * Tests deleting nodes of one bundle leaves other bundles intact.
   */
  public function testDeleteAllWithBundle(): void {
    for ($i = 0; $i < 3; $i++) {
      Node::create(['type' => 'article', 'title' => 'Article ' . $i])->save();
    }
    for ($i = 0; $i < 2; $i++) {
      Node::create(['type' => 'page', 'title' => 'Page ' . $i])->save();
    }

    $this->entityHelper->deleteAll('node', 'article');

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $remaining = $storage->loadMultiple();
    $this->assertCount(2, $remaining);

    foreach ($remaining as $node) {
      $this->assertEquals('page', $node->bundle());
    }
  }

  /**
   * Tests deleting entities with sandbox batching.
   */
  public function testDeleteAllSandbox(): void {
    $total = 120;
    for ($i = 0; $i < $total; $i++) {
      Node::create(['type' => 'article', 'title' => 'Article ' . $i])->save();
    }

    $sandbox = [];
    $this->entityHelper->setSandbox($sandbox);
    $this->entityHelper->setBatchSize(50);

    // First batch: processes 50 items.
    $result = $this->entityHelper->deleteAll('node', 'article');
    $this->assertNull($result);
    $this->assertLessThan(1, $sandbox['#finished']);

    // Second batch: processes next 50 items.
    $result = $this->entityHelper->deleteAll('node', 'article');
    $this->assertNull($result);
    $this->assertLessThan(1, $sandbox['#finished']);

    // Third batch: processes remaining 20 items.
    $result = $this->entityHelper->deleteAll('node', 'article');
    $this->assertNotNull($result);
    $this->assertGreaterThanOrEqual(1, $sandbox['#finished']);
    $this->assertStringContainsString('Processed 120', $result);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $remaining = $storage->loadMultiple();
    $this->assertCount(0, $remaining);
  }

  /**
   * Tests deleting when no entities exist produces a status message.
   */
  public function testDeleteAllEmpty(): void {
    $result = $this->entityHelper->deleteAll('node', 'article');

    $this->assertStringContainsString('No node entities to process', $result);
  }

  /**
   * Tests batchQuery processes only the entities selected by the query.
   */
  public function testBatchQueryTargeting(): void {
    $sticky_ids = [];
    for ($i = 0; $i < 5; $i++) {
      $node = Node::create(['type' => 'article', 'title' => 'Article ' . $i, 'sticky' => $i < 2 ? 1 : 0]);
      $node->save();
      if ($i < 2) {
        $sticky_ids[] = $node->id();
      }
    }

    // The query intentionally omits accessCheck() - batchQuery must apply it.
    $query = \Drupal::entityQuery('node')->condition('sticky', 1);

    $processed = [];
    $result = $this->entityHelper->batchQuery($query, function ($node) use (&$processed): void {
      $processed[] = $node->id();
    });

    $this->assertStringContainsString('Processed 2 node entities', $result);
    $this->assertEqualsCanonicalizing($sticky_ids, $processed);
  }

  /**
   * Tests batchQuery collects per-item failures without aborting the run.
   */
  public function testBatchQueryContinueOnError(): void {
    for ($i = 0; $i < 4; $i++) {
      Node::create(['type' => 'article', 'title' => 'Article ' . $i])->save();
    }

    $sandbox = [];
    $this->entityHelper->setSandbox($sandbox);

    $query = \Drupal::entityQuery('node')->condition('type', 'article');

    $processed = [];
    $result = $this->entityHelper->batchQuery($query, function ($node) use (&$processed): void {
      if (in_array($node->getTitle(), ['Article 1', 'Article 3'], TRUE)) {
        throw new \RuntimeException('cannot process ' . $node->getTitle());
      }
      $processed[] = $node->getTitle();
    }, TRUE);

    $this->assertStringContainsString('Processed 4 node entities, 2 failed', $result);
    $this->assertCount(2, $processed);
    $this->assertCount(2, $sandbox['errors']);
  }

  /**
   * Tests batchSetField sets a field value across every matched entity.
   */
  public function testBatchSetField(): void {
    $ids = [];
    for ($i = 0; $i < 3; $i++) {
      $node = Node::create(['type' => 'article', 'title' => 'Article ' . $i, 'status' => 1]);
      $node->save();
      $ids[] = $node->id();
    }

    // A published page the article-only query must leave untouched.
    $page = Node::create(['type' => 'page', 'title' => 'Page', 'status' => 1]);
    $page->save();
    $page_id = $page->id();

    $query = \Drupal::entityQuery('node')->condition('type', 'article');

    $result = $this->entityHelper->batchSetField($query, 'status', 0);

    $this->assertStringContainsString('Processed 3 node entities', $result);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache();

    foreach ($ids as $id) {
      $node = $storage->load($id);
      $this->assertFalse($node->isPublished());
    }

    $this->assertTrue($storage->load($page_id)->isPublished());
  }

}
