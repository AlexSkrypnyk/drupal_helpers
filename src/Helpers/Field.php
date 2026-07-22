<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\DeletedFieldsRepositoryInterface;
use Drupal\Core\Field\FieldPurger;
use Drupal\drupal_helpers\Report\Reporter;
use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Field helpers for deploy hooks.
 */
class Field extends HelperBase {

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Reporter $reporter,
    protected DeletedFieldsRepositoryInterface $deletedFieldsRepository,
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected ?FieldPurger $fieldPurger = NULL,
  ) {
    parent::__construct($entity_type_manager, $reporter);
  }

  /**
   * Create a field storage and instance on a bundle from a settings array.
   *
   * The default widget and formatter for the field type are applied to the
   * default form and view displays unless a 'widget' or 'formatter' override is
   * given, so a single call yields an immediately usable field. Re-running with
   * an instance that already exists is a safe no-op.
   *
   * @code
   * Helper::field()->create('node', 'article', 'field_subtitle', [
   *   'type' => 'string',
   *   'label' => 'Subtitle',
   * ]);
   * @endcode
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle machine name.
   * @param string $field_name
   *   Machine name of the field.
   * @param array $settings
   *   Field settings. Storage keys: 'type' (required), 'cardinality',
   *   'storage_settings'. Instance keys: 'label' (defaults to a humanized field
   *   name), 'description', 'required', 'settings', 'default_value'. Display
   *   keys: 'widget', 'formatter' (component options; omit for type defaults).
   *
   * @return \Drupal\field\FieldConfigInterface
   *   The created field instance, or the existing one when it already exists.
   *
   * @throws \InvalidArgumentException
   *   When the 'type' setting is missing, or conflicts with an existing field
   *   storage of a different type.
   */
  public function create(string $entity_type, string $bundle, string $field_name, array $settings): FieldConfigInterface {
    if (!isset($settings['type'])) {
      throw new \InvalidArgumentException(sprintf('The "type" setting is required to create field "%s".', $field_name));
    }

    $field_config_storage = $this->entityTypeManager->getStorage('field_config');

    /** @var \Drupal\field\FieldConfigInterface|null $instance */
    $instance = $field_config_storage->load($entity_type . '.' . $bundle . '.' . $field_name);

    if ($instance instanceof FieldConfigInterface) {
      $this->reporter->skipped($this->t('Field "@field" already exists on @entity_type.@bundle - skipped.', [
        '@field' => $field_name,
        '@entity_type' => $entity_type,
        '@bundle' => $bundle,
      ]));

      return $instance;
    }

    $this->ensureStorage($entity_type, $field_name, $settings);
    $instance = $this->createInstance($entity_type, $bundle, $field_name, $settings);
    $this->setDisplayDefaults($entity_type, $bundle, $field_name, $settings);

    $this->reporter->created($this->t('Created field "@field" on @entity_type.@bundle.', [
      '@field' => $field_name,
      '@entity_type' => $entity_type,
      '@bundle' => $bundle,
    ]));

    return $instance;
  }

  /**
   * Attach an existing field storage to one or more additional bundles.
   *
   * Each bundle receives a field instance reusing the shared storage, with the
   * field type's default widget and formatter wired onto its displays. Bundles
   * that already have the instance are skipped.
   *
   * @code
   * Helper::field()->attachToBundles('field_subtitle', 'node', ['page', 'landing']);
   * @endcode
   *
   * @param string $field_name
   *   Machine name of an existing field storage.
   * @param string $entity_type
   *   Entity type ID the storage belongs to.
   * @param string[] $bundles
   *   Bundle machine names to attach the field to.
   *
   * @return \Drupal\field\FieldConfigInterface[]
   *   The field instances keyed by bundle.
   *
   * @throws \InvalidArgumentException
   *   When the field storage does not exist.
   */
  public function attachToBundles(string $field_name, string $entity_type, array $bundles): array {
    /** @var \Drupal\field\FieldStorageConfigInterface|null $field_storage */
    $field_storage = $this->entityTypeManager->getStorage('field_storage_config')->load($entity_type . '.' . $field_name);

    if (!$field_storage instanceof FieldStorageConfigInterface) {
      throw new \InvalidArgumentException(sprintf('Field storage "%s.%s" does not exist; create it before attaching to bundles.', $entity_type, $field_name));
    }

    $instances = [];

    foreach ($bundles as $bundle) {
      $instances[$bundle] = $this->create($entity_type, $bundle, $field_name, ['type' => $field_storage->getType()]);
    }

    return $instances;
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
      // A storage config ID is "entity_type.field_name", so a bare field name
      // misses above; fall back to matching it across entity types.
      $storages = $field_storage_config_storage->loadByProperties(['field_name' => $field_name]);
      if (empty($storages)) {
        $this->reporter->skipped($this->t('Field storage "@field" not found - skipped.', [
          '@field' => $field_name,
        ]), severity: Reporter::SEVERITY_WARNING);

        return;
      }
      $field_storage = reset($storages);
    }

    // Deleting the storage automatically cascades to all field instances
    // via the config dependency system (ConfigEntityBase::preDelete).
    $field_storage->delete();
    $this->purge();

    $this->reporter->deleted($this->t('Deleted field storage "@field" and purged data.', [
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
      $this->reporter->skipped($this->t('Field instance "@field" not found on @entity_type.@bundle - skipped.', [
        '@field' => $field_name,
        '@entity_type' => $entity_type,
        '@bundle' => $bundle,
      ]), severity: Reporter::SEVERITY_WARNING);

      return;
    }

    $field_config->delete();
    $this->purge();

    $this->reporter->deleted($this->t('Deleted field instance "@field" from @entity_type.@bundle.', [
      '@field' => $field_name,
      '@entity_type' => $entity_type,
      '@bundle' => $bundle,
    ]));
  }

  /**
   * Load the field storage, creating it from the settings when absent.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $field_name
   *   Machine name of the field.
   * @param array $settings
   *   Field settings; 'type' is required, 'cardinality' and 'storage_settings'
   *   are optional storage-level keys.
   *
   * @return \Drupal\field\FieldStorageConfigInterface
   *   The existing or newly created field storage.
   *
   * @throws \InvalidArgumentException
   *   When an existing storage has a field type different from the
   *   requested one.
   */
  protected function ensureStorage(string $entity_type, string $field_name, array $settings): FieldStorageConfigInterface {
    $field_storage_config_storage = $this->entityTypeManager->getStorage('field_storage_config');

    /** @var \Drupal\field\FieldStorageConfigInterface|null $field_storage */
    $field_storage = $field_storage_config_storage->load($entity_type . '.' . $field_name);

    if ($field_storage instanceof FieldStorageConfigInterface) {
      // A storage's type is immutable and shared across every bundle, so a
      // requested type that differs from the existing one cannot be honored and
      // would otherwise be silently ignored.
      if ($field_storage->getType() !== $settings['type']) {
        throw new \InvalidArgumentException(sprintf('Field storage "%s.%s" already exists with type "%s" and cannot be created as "%s".', $entity_type, $field_name, $field_storage->getType(), $settings['type']));
      }

      return $field_storage;
    }

    $values = [
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => $settings['type'],
    ];

    if (isset($settings['cardinality'])) {
      $values['cardinality'] = $settings['cardinality'];
    }

    if (isset($settings['storage_settings'])) {
      $values['settings'] = $settings['storage_settings'];
    }

    /** @var \Drupal\field\FieldStorageConfigInterface $field_storage */
    $field_storage = $field_storage_config_storage->create($values);
    $field_storage->save();

    return $field_storage;
  }

  /**
   * Create and save a field instance on a bundle from the settings.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle machine name.
   * @param string $field_name
   *   Machine name of the field.
   * @param array $settings
   *   Field settings; the instance-level keys 'label', 'description',
   *   'required', 'settings' and 'default_value' are used when present.
   *
   * @return \Drupal\field\FieldConfigInterface
   *   The saved field instance.
   */
  protected function createInstance(string $entity_type, string $bundle, string $field_name, array $settings): FieldConfigInterface {
    $values = [
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => $settings['label'] ?? $this->humanize($field_name),
    ];

    foreach (['description', 'required', 'settings', 'default_value'] as $key) {
      if (isset($settings[$key])) {
        $values[$key] = $settings[$key];
      }
    }

    /** @var \Drupal\field\FieldConfigInterface $instance */
    $instance = $this->entityTypeManager->getStorage('field_config')->create($values);
    $instance->save();

    return $instance;
  }

  /**
   * Wire the field onto the default form and view displays.
   *
   * An empty component options array lets core fill in the field type's default
   * widget and formatter; a 'widget' or 'formatter' setting overrides them.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle machine name.
   * @param string $field_name
   *   Machine name of the field.
   * @param array $settings
   *   Field settings; the optional 'widget' and 'formatter' keys are passed as
   *   component options to the form and view displays respectively.
   */
  protected function setDisplayDefaults(string $entity_type, string $bundle, string $field_name, array $settings): void {
    $this->entityDisplayRepository->getFormDisplay($entity_type, $bundle)->setComponent($field_name, $settings['widget'] ?? [])->save();
    $this->entityDisplayRepository->getViewDisplay($entity_type, $bundle)->setComponent($field_name, $settings['formatter'] ?? [])->save();
  }

  /**
   * Build a human-readable label from a field machine name.
   *
   * @param string $field_name
   *   Machine name of the field, e.g. 'field_subtitle'.
   *
   * @return string
   *   A humanized label, e.g. 'Subtitle'.
   */
  protected function humanize(string $field_name): string {
    $label = preg_replace('/^field_/', '', $field_name);

    return ucfirst(str_replace('_', ' ', (string) $label));
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
