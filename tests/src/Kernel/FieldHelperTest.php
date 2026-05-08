<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

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
    EntityTestHelper::createBundle('bundle2');

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
