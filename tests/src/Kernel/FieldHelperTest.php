<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
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
