<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use Drupal\block\BlockInterface;
use Drupal\block_content\BlockContentInterface;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\drupal_helpers\Helpers\Block;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the Block helper service.
 */
#[CoversClass(Block::class)]
#[RunTestsInSeparateProcesses]
class BlockHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system', 'user', 'field', 'text', 'block', 'block_content', 'path_alias'];

  /**
   * The block helper service.
   */
  protected Block $blockHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');
    $this->installConfig(['system']);
    $this->container->get('theme_installer')->install(['stark']);

    BlockContentType::create(['id' => 'basic', 'label' => 'Basic block'])->save();

    FieldStorageConfig::create([
      'field_name' => 'body',
      'entity_type' => 'block_content',
      'type' => 'text_with_summary',
    ])->save();
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'block_content',
      'bundle' => 'basic',
      'label' => 'Body',
    ])->save();

    $this->blockHelper = $this->container->get('drupal_helpers.block');
  }

  /**
   * Tests that the helper declares the block module as required.
   */
  public function testRequiredModules(): void {
    $this->assertEquals(['block'], $this->blockHelper->requiredModules());
  }

  /**
   * Tests placing a plugin block into a theme region.
   */
  public function testPlace(): void {
    $block = $this->blockHelper->place('system_powered_by_block', 'stark', 'content');

    $this->assertInstanceOf(BlockInterface::class, $block);
    $this->assertEquals('stark_system_powered_by_block', $block->id());
    $this->assertEquals('system_powered_by_block', $block->get('plugin'));
    $this->assertEquals('stark', $block->get('theme'));
    $this->assertEquals('content', $block->get('region'));
    $this->assertEquals(0, $block->get('weight'));
  }

  /**
   * Tests placing with an explicit id and weight.
   */
  public function testPlaceWithOptions(): void {
    $block = $this->blockHelper->place('system_powered_by_block', 'stark', 'footer', [
      'id' => 'custom_powered',
      'weight' => 10,
    ]);

    $this->assertInstanceOf(BlockInterface::class, $block);
    $this->assertEquals('custom_powered', $block->id());
    $this->assertEquals(10, $block->get('weight'));
  }

  /**
   * Tests placing a block with visibility conditions.
   */
  public function testPlaceVisibility(): void {
    $block = $this->blockHelper->place('system_powered_by_block', 'stark', 'content', [
      'visibility' => [
        'request_path' => [
          'id' => 'request_path',
          'pages' => '/admin/*',
          'negate' => TRUE,
        ],
      ],
    ]);

    $this->assertInstanceOf(BlockInterface::class, $block);
    $visibility = $block->get('visibility');
    $this->assertArrayHasKey('request_path', $visibility);
    $this->assertEquals('/admin/*', $visibility['request_path']['pages']);
    $this->assertTrue($visibility['request_path']['negate']);
  }

  /**
   * Tests that placing the same block twice skips the duplicate.
   */
  public function testPlaceSkipExisting(): void {
    $first = $this->blockHelper->place('system_powered_by_block', 'stark', 'content');
    $second = $this->blockHelper->place('system_powered_by_block', 'stark', 'content');

    $this->assertInstanceOf(BlockInterface::class, $first);
    $this->assertInstanceOf(BlockInterface::class, $second);
    $this->assertEquals($first->id(), $second->id());

    $storage = $this->container->get('entity_type.manager')->getStorage('block');
    $this->assertCount(1, $storage->loadByProperties(['plugin' => 'system_powered_by_block']));
  }

  /**
   * Tests placing multiple blocks.
   */
  public function testPlaceMultiple(): void {
    $count = $this->blockHelper->placeMultiple([
      ['plugin' => 'system_powered_by_block', 'theme' => 'stark', 'region' => 'content'],
      ['plugin' => 'system_powered_by_block', 'theme' => 'stark', 'region' => 'footer', 'options' => ['id' => 'stark_powered_two', 'weight' => 5]],
    ]);

    $this->assertEquals(2, $count);

    $storage = $this->container->get('entity_type.manager')->getStorage('block');
    $this->assertCount(2, $storage->loadMultiple());
  }

  /**
   * Tests removing a placed block.
   */
  public function testRemove(): void {
    $this->blockHelper->place('system_powered_by_block', 'stark', 'content');

    $this->assertTrue($this->blockHelper->remove('stark_system_powered_by_block'));

    $storage = $this->container->get('entity_type.manager')->getStorage('block');
    $this->assertNull($storage->load('stark_system_powered_by_block'));

    $this->assertFalse($this->blockHelper->remove('nonexistent_block'));
  }

  /**
   * Tests creating a block content entity with field values.
   */
  public function testCreateContent(): void {
    $block_content = $this->blockHelper->createContent('basic', [
      'info' => 'Footer contact',
      'body' => 'Call us on 1234',
    ]);

    $this->assertInstanceOf(BlockContentInterface::class, $block_content);
    $this->assertEquals('Footer contact', $block_content->label());
    $this->assertEquals('basic', $block_content->bundle());
    $this->assertEquals('Call us on 1234', $block_content->get('body')->value);
  }

  /**
   * Tests that creating the same block content twice skips the duplicate.
   */
  public function testCreateContentSkipExisting(): void {
    $first = $this->blockHelper->createContent('basic', ['info' => 'Duplicate', 'body' => 'One']);
    $second = $this->blockHelper->createContent('basic', ['info' => 'Duplicate', 'body' => 'Two']);

    $this->assertInstanceOf(BlockContentInterface::class, $first);
    $this->assertInstanceOf(BlockContentInterface::class, $second);
    $this->assertEquals($first->id(), $second->id());

    $storage = $this->container->get('entity_type.manager')->getStorage('block_content');
    $this->assertCount(1, $storage->loadByProperties(['info' => 'Duplicate']));
  }

  /**
   * Tests creating multiple block content entities.
   */
  public function testCreateContentMultiple(): void {
    $count = $this->blockHelper->createContentMultiple([
      ['type' => 'basic', 'info' => 'One', 'body' => 'First'],
      ['type' => 'basic', 'info' => 'Two', 'body' => 'Second'],
    ]);

    $this->assertEquals(2, $count);

    $storage = $this->container->get('entity_type.manager')->getStorage('block_content');
    $this->assertCount(2, $storage->loadMultiple());
  }

  /**
   * Tests deleting block content by info label.
   */
  public function testDeleteContent(): void {
    $this->blockHelper->createContent('basic', ['info' => 'Delete me', 'body' => 'x']);
    $this->blockHelper->createContent('basic', ['info' => 'Keep me', 'body' => 'y']);

    $this->assertEquals(1, $this->blockHelper->deleteContent('Delete me'));

    $storage = $this->container->get('entity_type.manager')->getStorage('block_content');
    $this->assertCount(0, $storage->loadByProperties(['info' => 'Delete me']));
    $this->assertCount(1, $storage->loadByProperties(['info' => 'Keep me']));

    $this->assertEquals(1, $this->blockHelper->deleteContent('Keep me', 'basic'));
    $this->assertEquals(0, $this->blockHelper->deleteContent('Nonexistent'));
  }

}
