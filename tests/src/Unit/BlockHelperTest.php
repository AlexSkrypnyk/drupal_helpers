<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Unit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\drupal_helpers\Helpers\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Block helper service.
 */
#[CoversClass(Block::class)]
class BlockHelperTest extends TestCase {

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $entityTypeManager;

  /**
   * The messenger mock.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $messenger;

  /**
   * The block helper under test.
   */
  protected Block $blockHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);

    $this->blockHelper = new Block($this->entityTypeManager, $this->messenger);

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(fn($input): string => (string) $input->getUntranslatedString());
    $this->blockHelper->setStringTranslation($translation);
  }

  /**
   * Tests the declared required modules.
   */
  public function testRequiredModules(): void {
    $this->assertEquals(['block'], $this->blockHelper->requiredModules());
  }

  /**
   * Tests placing a block derives the id and creates the entity.
   */
  public function testPlace(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())->method('save');

    $captured = NULL;
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $storage->method('create')->willReturnCallback(function (array $values) use (&$captured, $entity): EntityInterface {
      $captured = $values;
      return $entity;
    });

    $this->entityTypeManager->method('getStorage')->with('block')->willReturn($storage);

    $result = $this->blockHelper->place('system_powered_by_block', 'olivero', 'footer_top');

    $this->assertSame($entity, $result);
    $this->assertEquals('olivero_system_powered_by_block', $captured['id']);
    $this->assertEquals('system_powered_by_block', $captured['plugin']);
    $this->assertEquals('olivero', $captured['theme']);
    $this->assertEquals('footer_top', $captured['region']);
    $this->assertEquals(0, $captured['weight']);
    $this->assertEquals([], $captured['settings']);
    $this->assertEquals([], $captured['visibility']);
  }

  /**
   * Tests that a derivative plugin id is sanitised into the machine name.
   */
  public function testPlaceDerivesIdFromDerivativePlugin(): void {
    $entity = $this->createMock(EntityInterface::class);

    $captured = NULL;
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $storage->method('create')->willReturnCallback(function (array $values) use (&$captured, $entity): EntityInterface {
      $captured = $values;
      return $entity;
    });

    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $this->blockHelper->place('system_menu_block:main', 'olivero', 'primary_menu');

    $this->assertEquals('olivero_system_menu_block_main', $captured['id']);
  }

  /**
   * Tests placing with explicit id, weight, settings and visibility.
   */
  public function testPlaceWithOptions(): void {
    $entity = $this->createMock(EntityInterface::class);

    $captured = NULL;
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $storage->method('create')->willReturnCallback(function (array $values) use (&$captured, $entity): EntityInterface {
      $captured = $values;
      return $entity;
    });

    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $visibility = ['request_path' => ['id' => 'request_path', 'pages' => '/admin/*', 'negate' => TRUE]];
    $this->blockHelper->place('system_powered_by_block', 'olivero', 'header', [
      'id' => 'custom_id',
      'weight' => 7,
      'settings' => ['label' => 'Custom'],
      'visibility' => $visibility,
    ]);

    $this->assertEquals('custom_id', $captured['id']);
    $this->assertEquals(7, $captured['weight']);
    $this->assertEquals(['label' => 'Custom'], $captured['settings']);
    $this->assertEquals($visibility, $captured['visibility']);
  }

  /**
   * Tests that placing an existing block returns it without creating.
   */
  public function testPlaceSkipExisting(): void {
    $existing = $this->createMock(EntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($existing);
    $storage->expects($this->never())->method('create');

    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $result = $this->blockHelper->place('system_powered_by_block', 'olivero', 'footer_top');

    $this->assertSame($existing, $result);
  }

  /**
   * Tests placing multiple blocks.
   */
  public function testPlaceMultiple(): void {
    $entity = $this->createMock(EntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $storage->method('create')->willReturn($entity);

    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $count = $this->blockHelper->placeMultiple([
      ['plugin' => 'system_powered_by_block', 'theme' => 'olivero', 'region' => 'content'],
      ['plugin' => 'system_branding_block', 'theme' => 'olivero', 'region' => 'header', 'options' => ['weight' => -10]],
    ]);

    $this->assertEquals(2, $count);
  }

  /**
   * Tests removing an existing block.
   */
  public function testRemove(): void {
    $block = $this->createMock(EntityInterface::class);
    $block->expects($this->once())->method('delete');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($block);

    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $this->assertTrue($this->blockHelper->remove('olivero_system_powered_by_block'));
  }

  /**
   * Tests removing a missing block returns FALSE.
   */
  public function testRemoveNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);

    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $this->assertFalse($this->blockHelper->remove('missing'));
  }

  /**
   * Tests creating block content.
   */
  public function testCreateContent(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())->method('save');

    $captured = NULL;
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $storage->method('create')->willReturnCallback(function (array $values) use (&$captured, $entity): EntityInterface {
      $captured = $values;
      return $entity;
    });

    $this->entityTypeManager->method('getDefinition')->with('block_content', FALSE)->willReturn($this->createMock(EntityTypeInterface::class));
    $this->entityTypeManager->method('getStorage')->with('block_content')->willReturn($storage);

    $result = $this->blockHelper->createContent('basic', ['info' => 'Footer contact', 'body' => 'Call us on 1234']);

    $this->assertSame($entity, $result);
    $this->assertEquals('basic', $captured['type']);
    $this->assertEquals('Footer contact', $captured['info']);
    $this->assertEquals('Call us on 1234', $captured['body']);
  }

  /**
   * Tests that creating existing block content returns it without creating.
   */
  public function testCreateContentSkipExisting(): void {
    $existing = $this->createMock(EntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([$existing]);
    $storage->expects($this->never())->method('create');

    $this->entityTypeManager->method('getDefinition')->willReturn($this->createMock(EntityTypeInterface::class));
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $result = $this->blockHelper->createContent('basic', ['info' => 'Footer contact']);

    $this->assertSame($existing, $result);
  }

  /**
   * Tests that block content without an info label skips the dedupe lookup.
   */
  public function testCreateContentWithoutInfo(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->never())->method('loadByProperties');
    $storage->method('create')->willReturn($entity);

    $this->entityTypeManager->method('getDefinition')->willReturn($this->createMock(EntityTypeInterface::class));
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $result = $this->blockHelper->createContent('basic', ['body' => 'No info here']);

    $this->assertSame($entity, $result);
  }

  /**
   * Tests that createContent throws when block_content is unavailable.
   */
  public function testCreateContentMissingModule(): void {
    $this->entityTypeManager->method('getDefinition')->with('block_content', FALSE)->willReturn(NULL);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The "block_content" entity type is unavailable');

    $this->blockHelper->createContent('basic', ['info' => 'Footer contact']);
  }

  /**
   * Tests creating multiple block content entities.
   */
  public function testCreateContentMultiple(): void {
    $entity = $this->createMock(EntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $storage->method('create')->willReturn($entity);

    $this->entityTypeManager->method('getDefinition')->willReturn($this->createMock(EntityTypeInterface::class));
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $count = $this->blockHelper->createContentMultiple([
      ['type' => 'basic', 'info' => 'One', 'body' => 'First'],
      ['type' => 'basic', 'info' => 'Two', 'body' => 'Second'],
    ]);

    $this->assertEquals(2, $count);
  }

  /**
   * Tests deleting block content by info label.
   */
  public function testDeleteContent(): void {
    $block1 = $this->createMock(EntityInterface::class);
    $block2 = $this->createMock(EntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([$block1, $block2]);
    $storage->expects($this->once())->method('delete')->with([$block1, $block2]);

    $this->entityTypeManager->method('getDefinition')->willReturn($this->createMock(EntityTypeInterface::class));
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $this->assertEquals(2, $this->blockHelper->deleteContent('Footer contact', 'basic'));
  }

  /**
   * Tests deleting block content when nothing matches.
   */
  public function testDeleteContentNone(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $storage->expects($this->never())->method('delete');

    $this->entityTypeManager->method('getDefinition')->willReturn($this->createMock(EntityTypeInterface::class));
    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $this->assertEquals(0, $this->blockHelper->deleteContent('Nonexistent'));
  }

  /**
   * Tests that deleteContent throws when block_content is unavailable.
   */
  public function testDeleteContentMissingModule(): void {
    $this->entityTypeManager->method('getDefinition')->with('block_content', FALSE)->willReturn(NULL);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The "block_content" entity type is unavailable');

    $this->blockHelper->deleteContent('Footer contact');
  }

}
