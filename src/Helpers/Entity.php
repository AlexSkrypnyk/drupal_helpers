<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Entity helpers for deploy hooks.
 */
class Entity extends HelperBase {

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
    });
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
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress.
   */
  public function batchQuery(QueryInterface $query, callable $callback, bool $continue_on_error = FALSE): ?string {
    $query->accessCheck(FALSE);

    return $this->batchEntityQuery($query, $callback, $continue_on_error);
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
    }, $continue_on_error);
  }

}
