<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\drupal_helpers\Report\Reporter;

/**
 * Entity helpers for deploy hooks.
 */
class Entity extends HelperBase {

  /**
   * Create an entity of a given type and bundle.
   *
   * @code
   * Helper::entity()->create('node', 'article', [
   *   'title' => 'Welcome',
   *   'body' => 'Hello world',
   * ]);
   *
   * // Skip re-creating an entity that already has the same identity value:
   * Helper::entity()->create('node', 'article', [
   *   'title' => 'Welcome',
   * ], identity: 'title');
   * @endcode
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle machine name.
   * @param array $values
   *   Field values keyed by field name.
   * @param string|null $identity
   *   Field or property name used to detect duplicates within the bundle. When
   *   set and present in $values, an existing entity with the same value is
   *   returned instead of creating a new one. Defaults to NULL (always create).
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Created entity, or the existing one when a duplicate is detected.
   */
  public function create(string $entity_type, string $bundle, array $values, ?string $identity = NULL): EntityInterface {
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $bundle_key = $this->entityTypeManager->getDefinition($entity_type)->getKey('bundle');

    if ($identity !== NULL && array_key_exists($identity, $values)) {
      $conditions = [$identity => $values[$identity]];

      if ($bundle_key) {
        $conditions[$bundle_key] = $bundle;
      }

      $existing = $storage->loadByProperties($conditions);

      if ($existing) {
        $entity = reset($existing);
        $this->reporter->skipped($this->t('@type "@value" already exists - skipped.', [
          '@type' => $entity_type,
          '@value' => $values[$identity],
        ]));

        return $entity;
      }
    }

    if ($bundle_key) {
      $values = [$bundle_key => $bundle] + $values;
    }

    $entity = $storage->create($values);
    $entity->save();

    $this->reporter->created($this->t('Created @type (id: @id).', [
      '@type' => $entity_type,
      '@id' => $entity->id(),
    ]));

    return $entity;
  }

  /**
   * Create multiple entities with optional sandbox batching.
   *
   * @code
   * $rows = [
   *   ['title' => 'Page one'],
   *   ['title' => 'Page two'],
   * ];
   * Helper::entity()->createMultiple('node', 'article', $rows, identity: 'title');
   *
   * // With sandbox for large datasets:
   * function my_module_deploy_001(array &$sandbox): ?string {
   *   return Helper::entity($sandbox)->createMultiple('node', 'article', $rows, identity: 'title');
   * }
   * @endcode
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle machine name.
   * @param array $rows
   *   Array of field-value arrays, one per entity to create.
   * @param string|null $identity
   *   Field or property name used to detect duplicates within the bundle.
   *   Defaults to NULL (always create).
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress.
   */
  public function createMultiple(string $entity_type, string $bundle, array $rows, ?string $identity = NULL): ?string {
    // The per-row create() already reports each entity, so the batch itself
    // records no count to avoid double counting.
    return $this->batch($rows, function (array $row) use ($entity_type, $bundle, $identity): void {
      $this->create($entity_type, $bundle, $row, $identity);
    }, $entity_type . ' entities', status: NULL);
  }

  /**
   * Update entities matched by a set of properties.
   *
   * @code
   * Helper::entity()->update('node', ['type' => 'article'], ['status' => 0]);
   *
   * // With sandbox for large datasets:
   * function my_module_deploy_001(array &$sandbox): ?string {
   *   return Helper::entity($sandbox)->update('node', ['type' => 'article'], ['status' => 0]);
   * }
   * @endcode
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param array $properties
   *   Properties to match as ['field' => 'value'] pairs. May include the bundle
   *   key to constrain the update to a single bundle.
   * @param array $values
   *   Field values to set on each matched entity, keyed by field name.
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress.
   */
  public function update(string $entity_type, array $properties, array $values): ?string {
    return $this->batchEntity($entity_type, NULL, function ($entity) use ($values): void {
      foreach ($values as $field => $value) {
        $entity->set($field, $value);
      }
      $entity->save();
    }, $properties, status: Reporter::UPDATED);
  }

  /**
   * Delete all entities of a given type and optional bundle.
   *
   * @code
   * Helper::entity()->deleteAll('node', 'article');
   *
   * // With sandbox for large datasets:
   * function my_module_deploy_001(array &$sandbox): ?string {
   *   return Helper::entity($sandbox)->deleteAll('node', 'article');
   * }
   * @endcode
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string|null $bundle
   *   Bundle machine name, or NULL to delete all.
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress.
   */
  public function deleteAll(string $entity_type, ?string $bundle = NULL): ?string {
    return $this->batchEntity($entity_type, $bundle, function ($entity): void {
      $entity->delete();
    }, status: Reporter::DELETED);
  }

  /**
   * Process entities matching an entity query with optional sandbox batching.
   *
   * The query is executed once and each matched entity is loaded and passed to
   * the callback. Access checking is disabled on the query so it behaves
   * predictably in deploy hooks, where there is no current user.
   *
   * @code
   * // Migrate a value on every legacy article, tolerating per-item failures:
   * function my_module_deploy_001(array &$sandbox): ?string {
   *   $query = \Drupal::entityQuery('node')
   *     ->condition('type', 'article')
   *     ->condition('field_legacy', 1);
   *   return Helper::entity($sandbox)->batchQuery($query, function ($node): void {
   *     $node->set('field_migrated', TRUE);
   *     $node->save();
   *   }, continue_on_error: TRUE);
   * }
   * @endcode
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The entity query selecting the entities to process.
   * @param callable $callback
   *   Callback receiving each entity and a context array.
   * @param bool $continue_on_error
   *   TRUE to collect per-item failures into the summary and keep processing;
   *   FALSE (default) to abort on the first error.
   * @param string|null $status
   *   Reporter status the processed entities are counted under (defaults to
   *   'processed'). Pass NULL when the callback already reports each entity.
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress.
   */
  public function batchQuery(QueryInterface $query, callable $callback, bool $continue_on_error = FALSE, ?string $status = Reporter::PROCESSED): ?string {
    $query->accessCheck(FALSE);

    return $this->batchEntityQuery($query, $callback, $continue_on_error, $status);
  }

  /**
   * Set a field value on every entity matching an entity query.
   *
   * A convenience around batchQuery() that assigns the same value to a field on
   * each matched entity and saves it, without writing a callback.
   *
   * @code
   * // Archive every article:
   * function my_module_deploy_001(array &$sandbox): ?string {
   *   $query = \Drupal::entityQuery('node')->condition('type', 'article');
   *   return Helper::entity($sandbox)->batchSetField($query, 'field_status', 'archived');
   * }
   * @endcode
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The entity query selecting the entities to update.
   * @param string $field_name
   *   The field to set on each matched entity.
   * @param mixed $value
   *   The value to assign to the field.
   * @param bool $continue_on_error
   *   TRUE to collect per-item failures into the summary and keep processing;
   *   FALSE (default) to abort on the first error.
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress.
   */
  public function batchSetField(QueryInterface $query, string $field_name, mixed $value, bool $continue_on_error = FALSE): ?string {
    return $this->batchQuery($query, function ($entity) use ($field_name, $value): void {
      $entity->set($field_name, $value);
      $entity->save();
    }, $continue_on_error, Reporter::UPDATED);
  }

}
