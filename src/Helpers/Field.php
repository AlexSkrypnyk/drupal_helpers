<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\DeletedFieldsRepositoryInterface;
use Drupal\Core\Field\FieldPurger;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Field helpers for deploy hooks.
 */
class Field extends HelperBase {

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
    protected DeletedFieldsRepositoryInterface $deletedFieldsRepository,
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
    $this->purge();

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
    $this->purge();

    $this->messenger->addStatus($this->t('Deleted field instance "@field" from @entity_type.@bundle.', [
      '@field' => $field_name,
      '@entity_type' => $entity_type,
      '@bundle' => $bundle,
    ]));
  }

  /**
   * Purge all field data left behind by a delete.
   *
   * Deleting a field storage or instance only marks its data for removal, so a
   * single batch would leave records behind once a field holds more than one
   * batch. Purging in a loop until the deleted-fields repository is empty keeps
   * the "purged data" status message truthful regardless of record count.
   */
  protected function purge(): void {
    do {
      DeprecationHelper::backwardsCompatibleCall(
        \Drupal::VERSION,
        '11.4.0',
        fn() => $this->purgeViaService($this->batchSize),
        fn() => field_purge_batch($this->batchSize),
      );
    } while ($this->hasPendingPurge());
  }

  /**
   * Purge a batch of deleted field data via the FieldPurger service.
   *
   * @param int $batch_size
   *   Maximum number of field data records to purge in this batch.
   *
   * @throws \RuntimeException
   *   When the FieldPurger service is unavailable, which would otherwise let
   *   the purge silently no-op and leave orphaned data behind.
   */
  protected function purgeViaService(int $batch_size): void {
    if (!$this->fieldPurger instanceof FieldPurger) {
      throw new \RuntimeException('The "Drupal\Core\Field\FieldPurger" service is required to purge deleted field data on Drupal 11.4.0 and later.');
    }

    $this->fieldPurger->purgeBatch($batch_size);
  }

  /**
   * Check whether any deleted field data is still awaiting purge.
   *
   * @return bool
   *   TRUE when deleted field or field storage definitions remain.
   */
  protected function hasPendingPurge(): bool {
    return $this->deletedFieldsRepository->getFieldDefinitions() !== []
      || $this->deletedFieldsRepository->getFieldStorageDefinitions() !== [];
  }

}
