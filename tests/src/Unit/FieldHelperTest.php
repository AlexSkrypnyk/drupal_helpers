<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Unit;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\DeletedFieldsRepositoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\drupal_helpers\Helpers\Field;
use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Field helper creation methods.
 */
#[CoversClass(Field::class)]
class FieldHelperTest extends TestCase {

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
   * The entity display repository mock.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->entityDisplayRepository = $this->createMock(EntityDisplayRepositoryInterface::class);
  }

  /**
   * Tests that create() rejects a settings array without a type.
   */
  public function testCreateRequiresType(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The "type" setting is required to create field "field_subtitle".');

    $this->createField()->create('entity_test', 'entity_test', 'field_subtitle', []);
  }

  /**
   * Tests that create() short-circuits when the instance already exists.
   */
  public function testCreateAlreadyExists(): void {
    $instance = $this->createMock(FieldConfigInterface::class);

    $field_config_storage = $this->createMock(EntityStorageInterface::class);
    $field_config_storage->method('load')->with('entity_test.entity_test.field_subtitle')->willReturn($instance);
    $field_config_storage->expects($this->never())->method('create');

    $this->entityTypeManager->method('getStorage')->with('field_config')->willReturn($field_config_storage);
    $this->entityDisplayRepository->expects($this->never())->method('getFormDisplay');
    $this->messenger->expects($this->once())->method('addStatus');

    $result = $this->createField()->create('entity_test', 'entity_test', 'field_subtitle', ['type' => 'string']);

    $this->assertSame($instance, $result);
  }

  /**
   * Tests that create() builds the storage, instance and display components.
   */
  public function testCreateBuildsStorageInstanceAndDisplays(): void {
    $instance = $this->createMock(FieldConfigInterface::class);
    $instance->expects($this->once())->method('save');

    $storage = $this->createMock(FieldStorageConfigInterface::class);
    $storage->expects($this->once())->method('save');

    $field_config_storage = $this->createMock(EntityStorageInterface::class);
    $field_config_storage->method('load')->willReturn(NULL);
    $field_config_storage->expects($this->once())->method('create')->with([
      'field_name' => 'field_subtitle',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'Subtitle',
    ])->willReturn($instance);

    $field_storage_config_storage = $this->createMock(EntityStorageInterface::class);
    $field_storage_config_storage->method('load')->willReturn(NULL);
    $field_storage_config_storage->expects($this->once())->method('create')->with([
      'field_name' => 'field_subtitle',
      'entity_type' => 'entity_test',
      'type' => 'string',
    ])->willReturn($storage);

    $this->entityTypeManager->method('getStorage')->willReturnMap([
      ['field_config', $field_config_storage],
      ['field_storage_config', $field_storage_config_storage],
    ]);

    $form_display = $this->createMock(EntityFormDisplayInterface::class);
    $form_display->expects($this->once())->method('setComponent')->with('field_subtitle', [])->willReturnSelf();
    $form_display->expects($this->once())->method('save');

    $view_display = $this->createMock(EntityViewDisplayInterface::class);
    $view_display->expects($this->once())->method('setComponent')->with('field_subtitle', [])->willReturnSelf();
    $view_display->expects($this->once())->method('save');

    $this->entityDisplayRepository->method('getFormDisplay')->with('entity_test', 'entity_test')->willReturn($form_display);
    $this->entityDisplayRepository->method('getViewDisplay')->with('entity_test', 'entity_test')->willReturn($view_display);

    $result = $this->createField()->create('entity_test', 'entity_test', 'field_subtitle', ['type' => 'string']);

    $this->assertSame($instance, $result);
  }

  /**
   * Tests that attachToBundles() rejects an unknown field storage.
   */
  public function testAttachToBundlesRequiresStorage(): void {
    $field_storage_config_storage = $this->createMock(EntityStorageInterface::class);
    $field_storage_config_storage->method('load')->willReturn(NULL);

    $this->entityTypeManager->method('getStorage')->with('field_storage_config')->willReturn($field_storage_config_storage);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Field storage "entity_test.field_subtitle" does not exist');

    $this->createField()->attachToBundles('field_subtitle', 'entity_test', ['page']);
  }

  /**
   * Tests that attachToBundles() reuses the storage and keys results by bundle.
   */
  public function testAttachToBundles(): void {
    $storage = $this->createMock(FieldStorageConfigInterface::class);
    $storage->method('getType')->willReturn('string');
    $storage->expects($this->never())->method('save');

    $field_storage_config_storage = $this->createMock(EntityStorageInterface::class);
    $field_storage_config_storage->method('load')->willReturn($storage);

    $instance_page = $this->createMock(FieldConfigInterface::class);
    $instance_landing = $this->createMock(FieldConfigInterface::class);

    $field_config_storage = $this->createMock(EntityStorageInterface::class);
    $field_config_storage->method('load')->willReturn(NULL);
    $field_config_storage->method('create')->willReturnOnConsecutiveCalls($instance_page, $instance_landing);

    $this->entityTypeManager->method('getStorage')->willReturnMap([
      ['field_config', $field_config_storage],
      ['field_storage_config', $field_storage_config_storage],
    ]);

    $form_display = $this->createMock(EntityFormDisplayInterface::class);
    $form_display->method('setComponent')->willReturnSelf();
    $view_display = $this->createMock(EntityViewDisplayInterface::class);
    $view_display->method('setComponent')->willReturnSelf();

    $this->entityDisplayRepository->method('getFormDisplay')->willReturn($form_display);
    $this->entityDisplayRepository->method('getViewDisplay')->willReturn($view_display);

    $result = $this->createField()->attachToBundles('field_subtitle', 'entity_test', ['page', 'landing']);

    $this->assertSame(['page' => $instance_page, 'landing' => $instance_landing], $result);
  }

  /**
   * Builds a Field helper with mocked dependencies and a passthrough translator.
   */
  protected function createField(): Field {
    $field = new Field(
      $this->entityTypeManager,
      $this->messenger,
      $this->createMock(DeletedFieldsRepositoryInterface::class),
      $this->entityDisplayRepository,
    );

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(fn($input): string => (string) $input->getUntranslatedString());
    $field->setStringTranslation($translation);

    return $field;
  }

}
