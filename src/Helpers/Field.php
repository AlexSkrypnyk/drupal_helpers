<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

/**
 * Field helpers for deploy hooks.
 */
class Field extends HelperBase {

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
    field_purge_batch(100);

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
    field_purge_batch(100);

    $this->messenger->addStatus($this->t('Deleted field instance "@field" from @entity_type.@bundle.', [
      '@field' => $field_name,
      '@entity_type' => $entity_type,
      '@bundle' => $bundle,
    ]));
  }

}
