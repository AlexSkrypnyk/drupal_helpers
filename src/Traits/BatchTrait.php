<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Traits;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Provides sandbox batching support for helper services.
 *
 * Classes using this trait must also use StringTranslationTrait.
 */
trait BatchTrait {

  /**
   * The sandbox array, or NULL for non-batched operations.
   *
   * @var array|null
   */
  protected ?array $sandbox = NULL;

  /**
   * The batch size for batched operations.
   *
   * @var int
   */
  protected int $batchSize = 50;

  /**
   * Get the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager service.
   */
  abstract protected function getEntityTypeManager(): EntityTypeManagerInterface;

  /**
   * Get the messenger service.
   *
   * @return \Drupal\Core\Messenger\MessengerInterface
   *   The messenger service.
   */
  abstract protected function getMessenger(): MessengerInterface;

  /**
   * Set the sandbox array.
   *
   * @param array &$sandbox
   *   The deploy hook sandbox array (passed by reference).
   */
  public function setSandbox(array &$sandbox): void {
    $this->sandbox = &$sandbox;
  }

  /**
   * Set the batch size.
   *
   * @param int $batch_size
   *   Number of items to process per batch.
   */
  public function setBatchSize(int $batch_size): void {
    $this->batchSize = $batch_size;
  }

  /**
   * Process an array of items with optional sandbox batching.
   *
   * When no sandbox is set, all items are processed at once. When a sandbox
   * is set (via the facade), items are processed in batches.
   *
   * The callback receives each item and a context array:
   * - 'index': zero-based position of the current item.
   * - 'total': total number of items being processed.
   * - 'results': an accumulator array that persists across batches (passed by
   *    reference).
   * @code
   * function (mixed $item, array $context): void {
   * }
   * @endcode
   *
   * @param array $items
   *   Array of items to process.
   * @param callable $callback
   *   Callback receiving each item and a context array.
   * @param string $label
   *   Human-readable label for status messages (e.g., 'redirects').
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress.
   */
  public function batch(array $items, callable $callback, string $label = 'items'): ?string {
    // Non-sandbox mode: process all items at once.
    if ($this->sandbox === NULL) {
      if (empty($items)) {
        $message = $this->t('No @label to process.', ['@label' => $label]);
        $this->getMessenger()->addStatus($message);

        return (string) $message;
      }

      $total = count($items);
      $results = [];

      foreach (array_values($items) as $index => $item) {
        $callback($item, ['index' => $index, 'total' => $total, 'results' => &$results]);
      }

      $message = $this->t('Processed @count @label.', ['@count' => $total, '@label' => $label]);
      $this->getMessenger()->addStatus($message);

      return (string) $message;
    }

    // Sandbox mode: process items in batches.
    if (!isset($this->sandbox['items'])) {
      $this->sandbox['items'] = array_values($items);
      $this->sandbox['total'] = count($this->sandbox['items']);
      $this->sandbox['current'] = 0;
      $this->sandbox['results'] = [];

      if ($this->sandbox['total'] === 0) {
        $this->sandbox['#finished'] = 1;
        $message = $this->t('No @label to process.', ['@label' => $label]);
        $this->getMessenger()->addStatus($message);

        return (string) $message;
      }
    }

    $batch_items = array_slice($this->sandbox['items'], $this->sandbox['current'], $this->batchSize);

    foreach ($batch_items as $item) {
      $callback($item, [
        'index' => $this->sandbox['current'],
        'total' => $this->sandbox['total'],
        'results' => &$this->sandbox['results'],
      ]);
      $this->sandbox['current']++;
    }

    $this->sandbox['#finished'] = $this->sandbox['total'] > 0 ? $this->sandbox['current'] / $this->sandbox['total'] : 1;

    if ($this->sandbox['#finished'] >= 1) {
      $message = $this->t('Processed @count @label.', ['@count' => $this->sandbox['total'], '@label' => $label]);
      $this->getMessenger()->addStatus($message);

      return (string) $message;
    }

    return NULL;
  }

  /**
   * Process entities with optional sandbox batching.
   *
   * Queries entity IDs matching the criteria, then delegates to batch() to
   * load and process each entity.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string|null $bundle
   *   Bundle machine name, or NULL to process all bundles.
   * @param callable $callback
   *   Callback receiving each entity and a context array.
   * @param array $conditions
   *   Additional query conditions as ['field' => 'value'] pairs.
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress.
   */
  public function batchEntity(string $entity_type, ?string $bundle, callable $callback, array $conditions = []): ?string {
    $ids = (!$this->sandbox || !isset($this->sandbox['items'])) ? $this->queryEntityIds($entity_type, $bundle, $conditions) : [];
    $storage = $this->getEntityTypeManager()->getStorage($entity_type);

    return $this->batch($ids, function ($id, array $context) use ($storage, $callback): void {
      $entity = $storage->load($id);
      if ($entity) {
        $callback($entity, $context);
      }
    }, $entity_type . ' entities');
  }

  /**
   * Query entity IDs matching the given criteria.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string|null $bundle
   *   Bundle machine name, or NULL to query all bundles.
   * @param array $conditions
   *   Additional query conditions as ['field' => 'value'] pairs.
   *
   * @return array
   *   Array of entity IDs.
   */
  protected function queryEntityIds(string $entity_type, ?string $bundle, array $conditions): array {
    $etm = $this->getEntityTypeManager();
    $storage = $etm->getStorage($entity_type);
    $query = $storage->getQuery()->accessCheck(FALSE);

    if ($bundle !== NULL) {
      $bundle_key = $etm->getDefinition($entity_type)->getKey('bundle');
      if ($bundle_key) {
        $query->condition($bundle_key, $bundle);
      }
    }

    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }

    return array_values($query->execute());
  }

}
