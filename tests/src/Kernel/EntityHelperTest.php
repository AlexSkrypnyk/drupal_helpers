<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use Drupal\drupal_helpers\Helpers\Entity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Kernel tests for the Entity helper service.
 */
#[CoversClass(Entity::class)]
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

}
