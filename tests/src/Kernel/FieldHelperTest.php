<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\drupal_helpers\Helpers\Field;
use Drupal\entity_test\EntityTestHelper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Kernel tests for the Field helper service.
 */
#[CoversClass(Field::class)]
#[RunTestsInSeparateProcesses]
class FieldHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system', 'user', 'field', 'entity_test'];

  /**
   * The field helper service.
   */
  protected Field $fieldHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');

    $this->fieldHelper = $this->container->get('drupal_helpers.field');
  }

  /**
   * Tests that requiredModules returns an empty array.
   */
  public function testRequiredModules(): void {
    $this->assertEquals([], $this->fieldHelper->requiredModules());
  }

  /**
   * Registers a new bundle for the entity_test entity type.
   *
   * Compatible with both Drupal 10 and Drupal 11.2+.
   *
   * @param string $bundle
   *   The bundle machine name.
   */
  protected function createBundle(string $bundle): void {
    if (class_exists(EntityTestHelper::class)) {
      EntityTestHelper::createBundle($bundle);

      return;
    }

    $bundles = \Drupal::state()->get('entity_test.bundles', ['entity_test' => ['label' => 'Entity Test Bundle']]);
    $bundles += [$bundle => ['label' => $bundle]];
    \Drupal::state()->set('entity_test.bundles', $bundles);
    \Drupal::service('entity_bundle.listener')->onBundleCreate($bundle, 'entity_test');
  }

  /**
   * Creates a field storage and field instance on the given bundle.
   *
   * @param string $field_name
   *   The field machine name.
   * @param string $bundle
   *   The entity_test bundle.
   */
  protected function createField(string $field_name, string $bundle = 'entity_test'): void {
    $field_storage = FieldStorageConfig::loadByName('entity_test', $field_name);

    if ($field_storage === NULL) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'entity_test',
        'type' => 'boolean',
      ])->save();
    }

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'bundle' => $bundle,
      'label' => ucfirst(str_replace('_', ' ', $field_name)),
    ])->save();
  }

  /**
   * Tests creating a field with default widget, formatter and label.
   */
  public function testCreate(): void {
    $instance = $this->fieldHelper->create('entity_test', 'entity_test', 'field_subtitle', ['type' => 'string']);

    $this->assertEquals('field_subtitle', $instance->getName());

    $storage = FieldStorageConfig::loadByName('entity_test', 'field_subtitle');
    $this->assertNotNull($storage);
    $this->assertEquals('string', $storage->getType());

    $config = FieldConfig::loadByName('entity_test', 'entity_test', 'field_subtitle');
    $this->assertNotNull($config);
    // The label defaults to a humanized field name.
    $this->assertEquals('Subtitle', $config->label());

    $form_display = $this->displayRepository()->getFormDisplay('entity_test', 'entity_test');
    $widget = $form_display->getComponent('field_subtitle');
    $this->assertIsArray($widget);
    $this->assertEquals('string_textfield', $widget['type']);

    $view_display = $this->displayRepository()->getViewDisplay('entity_test', 'entity_test');
    $formatter = $view_display->getComponent('field_subtitle');
    $this->assertIsArray($formatter);
    $this->assertEquals('string', $formatter['type']);
  }

  /**
   * Tests creating a field with explicit storage, instance and display config.
   */
  public function testCreateWithExplicitSettings(): void {
    $this->fieldHelper->create('entity_test', 'entity_test', 'field_tagline', [
      'type' => 'string',
      'label' => 'Tagline',
      'cardinality' => 3,
      'required' => TRUE,
      'storage_settings' => ['max_length' => 100],
      'widget' => ['type' => 'string_textfield', 'weight' => 7],
      'formatter' => ['type' => 'string', 'weight' => 4],
    ]);

    $storage = FieldStorageConfig::loadByName('entity_test', 'field_tagline');
    $this->assertEquals(3, $storage->getCardinality());
    $this->assertEquals(100, $storage->getSetting('max_length'));

    $config = FieldConfig::loadByName('entity_test', 'entity_test', 'field_tagline');
    $this->assertEquals('Tagline', $config->label());
    $this->assertTrue($config->isRequired());

    $widget = $this->displayRepository()->getFormDisplay('entity_test', 'entity_test')->getComponent('field_tagline');
    $this->assertEquals('string_textfield', $widget['type']);
    $this->assertEquals(7, $widget['weight']);

    $formatter = $this->displayRepository()->getViewDisplay('entity_test', 'entity_test')->getComponent('field_tagline');
    $this->assertEquals('string', $formatter['type']);
    $this->assertEquals(4, $formatter['weight']);
  }

  /**
   * Tests that recreating an existing field is a safe no-op.
   */
  public function testCreateAlreadyExists(): void {
    $first = $this->fieldHelper->create('entity_test', 'entity_test', 'field_subtitle', ['type' => 'string', 'label' => 'Subtitle']);

    // A second call with conflicting settings must leave the field untouched.
    $second = $this->fieldHelper->create('entity_test', 'entity_test', 'field_subtitle', ['type' => 'integer', 'label' => 'Changed']);

    $this->assertEquals($first->id(), $second->id());

    $storage = FieldStorageConfig::loadByName('entity_test', 'field_subtitle');
    $this->assertEquals('string', $storage->getType());

    $config = FieldConfig::loadByName('entity_test', 'entity_test', 'field_subtitle');
    $this->assertEquals('Subtitle', $config->label());

    $messages = $this->container->get('messenger')->messagesByType('status');
    $this->assertStringContainsString('already exists', (string) end($messages));
  }

  /**
   * Tests that create() rejects a type conflicting with existing storage.
   */
  public function testCreateStorageTypeMismatch(): void {
    $this->createBundle('page');
    $this->fieldHelper->create('entity_test', 'entity_test', 'field_subtitle', ['type' => 'string']);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('already exists with type "string"');

    $this->fieldHelper->create('entity_test', 'page', 'field_subtitle', ['type' => 'integer']);
  }

  /**
   * Tests attaching an existing field storage to multiple bundles.
   */
  public function testAttachToBundles(): void {
    $this->createBundle('page');
    $this->createBundle('landing');

    $this->fieldHelper->create('entity_test', 'entity_test', 'field_subtitle', ['type' => 'string']);

    $instances = $this->fieldHelper->attachToBundles('field_subtitle', 'entity_test', ['page', 'landing']);

    $this->assertArrayHasKey('page', $instances);
    $this->assertArrayHasKey('landing', $instances);

    // A single storage is shared across every bundle.
    $storages = $this->container->get('entity_type.manager')
      ->getStorage('field_storage_config')
      ->loadByProperties(['entity_type' => 'entity_test', 'field_name' => 'field_subtitle']);
    $this->assertCount(1, $storages);

    foreach (['page', 'landing'] as $bundle) {
      $config = FieldConfig::loadByName('entity_test', $bundle, 'field_subtitle');
      $this->assertNotNull($config);

      $widget = $this->displayRepository()->getFormDisplay('entity_test', $bundle)->getComponent('field_subtitle');
      $this->assertEquals('string_textfield', $widget['type']);

      $formatter = $this->displayRepository()->getViewDisplay('entity_test', $bundle)->getComponent('field_subtitle');
      $this->assertEquals('string', $formatter['type']);
    }
  }

  /**
   * Tests that attaching to a missing field storage throws.
   */
  public function testAttachToBundlesMissingStorage(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Field storage "entity_test.field_ghost" does not exist');

    $this->fieldHelper->attachToBundles('field_ghost', 'entity_test', ['entity_test']);
  }

  /**
   * Returns the entity display repository service.
   *
   * @return \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   *   The entity display repository.
   */
  protected function displayRepository(): EntityDisplayRepositoryInterface {
    return $this->container->get('entity_display.repository');
  }

  /**
   * Tests deleting a field storage and all its instances.
   */
  public function testDelete(): void {
    $this->createField('field_subtitle');

    $this->fieldHelper->delete('field_subtitle');

    $field_storage = FieldStorageConfig::loadByName('entity_test', 'field_subtitle');
    $this->assertNull($field_storage);

    $field_config = FieldConfig::loadByName('entity_test', 'entity_test', 'field_subtitle');
    $this->assertNull($field_config);
  }

  /**
   * Tests that deleting a field purges all data across multiple batches.
   */
  public function testDeletePurgesAllFieldData(): void {
    $this->createField('field_flag');

    $storage = $this->container->get('entity_type.manager')->getStorage('entity_test');
    for ($i = 0; $i < 3; $i++) {
      $storage->create(['name' => 'entity_' . $i, 'field_flag' => TRUE])->save();
    }

    // Force a batch size of one so the purge loop runs multiple passes.
    $this->fieldHelper->setBatchSize(1);
    $this->fieldHelper->delete('field_flag');

    $this->assertNull(FieldStorageConfig::loadByName('entity_test', 'field_flag'));

    $repository = $this->container->get('entity_field.deleted_fields_repository');
    $this->assertSame([], $repository->getFieldStorageDefinitions());
    $this->assertSame([], $repository->getFieldDefinitions());
  }

  /**
   * Tests deleting a non-existent field produces a warning.
   */
  public function testDeleteNonExistent(): void {
    $this->fieldHelper->delete('field_nonexistent');

    $messages = $this->container->get('messenger')->messagesByType('warning');
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('field_nonexistent', (string) reset($messages));
  }

  /**
   * Tests deleting a field instance from one bundle leaves other bundles.
   */
  public function testDeleteInstance(): void {
    $this->createBundle('bundle2');

    $this->createField('field_subtitle', 'entity_test');
    $this->createField('field_subtitle', 'bundle2');

    $this->fieldHelper->deleteInstance('field_subtitle', 'entity_test', 'entity_test');

    $deleted = FieldConfig::loadByName('entity_test', 'entity_test', 'field_subtitle');
    $this->assertNull($deleted);

    $remaining = FieldConfig::loadByName('entity_test', 'bundle2', 'field_subtitle');
    $this->assertNotNull($remaining);
    $this->assertEquals('bundle2', $remaining->getTargetBundle());
  }

  /**
   * Tests deleting a non-existent field instance produces a warning.
   */
  public function testDeleteInstanceNonExistent(): void {
    $this->fieldHelper->deleteInstance('field_nonexistent', 'entity_test', 'entity_test');

    $messages = $this->container->get('messenger')->messagesByType('warning');
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('field_nonexistent', (string) reset($messages));
  }

}
