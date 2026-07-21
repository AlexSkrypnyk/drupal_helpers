<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\drupal_helpers\Helpers\Entity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
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
