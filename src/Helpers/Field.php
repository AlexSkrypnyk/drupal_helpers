<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldPurger;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Field helpers for deploy hooks.
 */
class Field extends HelperBase {

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
    protected ?FieldPurger $fieldPurger = NULL,
  ) {
    parent::__construct($entity_type_manager, $messenger);
  }

  /**
   * Delete a field from all entity bundles and purge its data.
   *
   * @code
   * Helper::field()->delete('field_subtitle');
   * @endcode
   *
   * @param string $field_name
   *   Machine name of the field.
   */
  public function delete(string $field_name): void {
    $field_storage_config_storage = $this->entityTypeManager->getStorage('field_storage_config');

    /** @var \Drupal\field\FieldStorageConfigInterface|null $field_storage */
    $field_storage = $field_storage_config_storage->load($field_name);

    if ($field_storage === NULL) {
      // Try loading by entity_type.field_name pattern.
      $storages = $field_storage_config_storage->loadByProperties(['field_name' => $field_name]);
      if (empty($storages)) {
        $this->messenger->addWarning($this->t('Field storage "@field" not found — skipped.', [
          '@field' => $field_name,
        ]));

        return;
      }
      $field_storage = reset($storages);
    }

    // Deleting the storage automatically cascades to all field instances
    // via the config dependency system (ConfigEntityBase::preDelete).
    $field_storage->delete();
    $this->purgeBatch(100);

    $this->messenger->addStatus($this->t('Deleted field storage "@field" and purged data.', [
      '@field' => $field_name,
    ]));
  }

  /**
   * Delete a field instance from a specific entity bundle.
   *
   * @code
   * Helper::field()->deleteInstance('field_subtitle', 'node', 'article');
   * @endcode
   *
   * @param string $field_name
   *   Machine name of the field.
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle machine name.
   */
  public function deleteInstance(string $field_name, string $entity_type, string $bundle): void {
    $field_config_storage = $this->entityTypeManager->getStorage('field_config');
    $id = $entity_type . '.' . $bundle . '.' . $field_name;

    /** @var \Drupal\field\FieldConfigInterface|null $field_config */
    $field_config = $field_config_storage->load($id);

    if ($field_config === NULL) {
      $this->messenger->addWarning($this->t('Field instance "@field" not found on @entity_type.@bundle — skipped.', [
        '@field' => $field_name,
        '@entity_type' => $entity_type,
        '@bundle' => $bundle,
      ]));

      return;
    }

    $field_config->delete();
    $this->purgeBatch(100);

    $this->messenger->addStatus($this->t('Deleted field instance "@field" from @entity_type.@bundle.', [
      '@field' => $field_name,
      '@entity_type' => $entity_type,
      '@bundle' => $bundle,
    ]));
  }

  /**
   * Purge a batch of deleted field data.
   *
   * @param int $batch_size
   *   Maximum number of field data records to purge in this batch.
   */
  protected function purgeBatch(int $batch_size): void {
    // The FieldPurger service replaced the procedural field_purge_batch()
    // function on Drupal 11.4.0; the injected service is NULL on earlier
    // supported cores, where the function is still the correct entry point.
    DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      '11.4.0',
      fn() => $this->fieldPurger?->purgeBatch($batch_size),
      fn() => field_purge_batch($batch_size),
    );
  }

}
