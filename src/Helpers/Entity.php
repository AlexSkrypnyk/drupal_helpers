<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

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

}
