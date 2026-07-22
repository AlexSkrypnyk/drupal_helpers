<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Traits;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\drupal_helpers\Report\Reporter;

/**
 * Provides sandbox batching support for helper services.
 *
 * Classes using this trait must also use StringTranslationTrait.
 */
trait BatchTrait {

  /**
   * The sandbox array, or NULL for non-batched operations.
   */
  protected ?array $sandbox = NULL;

  /**
   * The batch size for batched operations.
   */
  protected int $batchSize = 50;

  /**
   * Number of failed items listed in the completion warning before truncation.
   */
  protected int $errorSummaryLimit = 10;

  /**
   * Get the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager service.
   */
  abstract protected function getEntityTypeManager(): EntityTypeManagerInterface;

  /**
   * Get the reporter.
   *
   * @return \Drupal\drupal_helpers\Report\Reporter
   *   The reporter service.
   */
  abstract protected function getReporter(): Reporter;

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
   * With $continue_on_error, a callback that throws does not abort the run:
   * the failure is recorded and the remaining items are still processed. The
   * completion message reports the number of failures and the full list is
   * available in the sandbox 'errors' key.
   *
   * @param array $items
   *   Array of items to process.
   * @param callable $callback
   *   Callback receiving each item and a context array.
   * @param string $label
   *   Human-readable label for status messages (e.g., 'redirects').
   * @param bool $continue_on_error
   *   TRUE to record per-item failures and keep processing; FALSE (default) to
   *   let a thrown error abort the run.
   * @param string|null $status
   *   Reporter status the processed items are counted under (defaults to
   *   'processed'). Pass NULL when the callback already reports each item, to
   *   avoid double counting.
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress.
   */
  public function batch(array $items, callable $callback, string $label = 'items', bool $continue_on_error = FALSE, ?string $status = Reporter::PROCESSED): ?string {
    // Non-sandbox mode: process all items at once.
    if ($this->sandbox === NULL) {
      if (empty($items)) {
        $message = $this->t('No @label to process.', ['@label' => $label]);
        $this->getReporter()->message($message);

        return (string) $message;
      }

      $total = count($items);
      $results = [];
      $errors = [];

      foreach (array_values($items) as $index => $item) {
        $this->batchInvoke($callback, $item, ['index' => $index, 'total' => $total, 'results' => &$results], $continue_on_error, $errors);
      }

      return $this->batchFinish($label, $total, $errors, $status);
    }

    // Sandbox mode: process items in batches.
    if (!isset($this->sandbox['items'])) {
      $this->sandbox['items'] = array_values($items);
      $this->sandbox['total'] = count($this->sandbox['items']);
      $this->sandbox['current'] = 0;
      $this->sandbox['results'] = [];
      $this->sandbox['errors'] = [];

      if ($this->sandbox['total'] === 0) {
        $this->sandbox['#finished'] = 1;
        $message = $this->t('No @label to process.', ['@label' => $label]);
        $this->getReporter()->message($message);

        return (string) $message;
      }
    }

    $batch_items = array_slice($this->sandbox['items'], $this->sandbox['current'], $this->batchSize);

    foreach ($batch_items as $item) {
      $this->batchInvoke($callback, $item, [
        'index' => $this->sandbox['current'],
        'total' => $this->sandbox['total'],
        'results' => &$this->sandbox['results'],
      ], $continue_on_error, $this->sandbox['errors']);
      $this->sandbox['current']++;
    }

    $this->sandbox['#finished'] = $this->sandbox['total'] > 0 ? $this->sandbox['current'] / $this->sandbox['total'] : 1;

    if ($this->sandbox['#finished'] >= 1) {
      return $this->batchFinish($label, $this->sandbox['total'], $this->sandbox['errors'], $status);
    }

    return NULL;
  }

  /**
   * Process entities with optional sandbox batching.
   *
   * Builds an entity query from the type, bundle and conditions, then
   * delegates to batchEntityQuery() to load and process each match.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string|null $bundle
   *   Bundle machine name, or NULL to process all bundles.
   * @param callable $callback
   *   Callback receiving each entity and a context array.
   * @param array $conditions
   *   Additional query conditions as ['field' => 'value'] pairs.
   * @param bool $continue_on_error
   *   TRUE to record per-item failures and keep processing; FALSE (default) to
   *   let a thrown error abort the run.
   * @param string|null $status
   *   Reporter status the processed entities are counted under (defaults to
   *   'processed'). Pass NULL when the callback already reports each entity.
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress.
   */
  public function batchEntity(string $entity_type, ?string $bundle, callable $callback, array $conditions = [], bool $continue_on_error = FALSE, ?string $status = Reporter::PROCESSED): ?string {
    $query = $this->buildEntityQuery($entity_type, $bundle, $conditions);

    return $this->batchEntityQuery($query, $callback, $continue_on_error, $status);
  }

  /**
   * Process the results of an entity query with optional sandbox batching.
   *
   * The query is executed once - its IDs are stashed in the sandbox - and each
   * matched entity is loaded and passed to the callback.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The entity query selecting the entities to process.
   * @param callable $callback
   *   Callback receiving each entity and a context array.
   * @param bool $continue_on_error
   *   TRUE to record per-item failures and keep processing; FALSE (default) to
   *   let a thrown error abort the run.
   * @param string|null $status
   *   Reporter status the processed entities are counted under (defaults to
   *   'processed'). Pass NULL when the callback already reports each entity.
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress.
   */
  protected function batchEntityQuery(QueryInterface $query, callable $callback, bool $continue_on_error = FALSE, ?string $status = Reporter::PROCESSED): ?string {
    $entity_type = $query->getEntityTypeId();
    $ids = [];

    if (!$this->sandbox || !isset($this->sandbox['items'])) {
      // Query::execute() returns an int only when the query has the "count"
      // flag set, which batching never does; guard it anyway for PHPStan.
      $result = $query->execute();
      $ids = is_array($result) ? array_values($result) : [];
    }

    $storage = $this->getEntityTypeManager()->getStorage($entity_type);

    return $this->batch($ids, function ($id, array $context) use ($storage, $callback): void {
      $entity = $storage->load($id);

      if ($entity) {
        $callback($entity, $context);
      }
    }, $entity_type . ' entities', $continue_on_error, $status);
  }

  /**
   * Build an entity query for a type, optional bundle and conditions.
   *
   * The returned query is configured but not executed.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string|null $bundle
   *   Bundle machine name, or NULL to query all bundles.
   * @param array $conditions
   *   Additional query conditions as ['field' => 'value'] pairs.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The configured entity query.
   */
  protected function buildEntityQuery(string $entity_type, ?string $bundle, array $conditions): QueryInterface {
    $entity_type_manager = $this->getEntityTypeManager();
    $query = $entity_type_manager->getStorage($entity_type)->getQuery()->accessCheck(FALSE);

    if ($bundle !== NULL) {
      $bundle_key = $entity_type_manager->getDefinition($entity_type)->getKey('bundle');
      if ($bundle_key) {
        $query->condition($bundle_key, $bundle);
      }
    }

    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }

    return $query;
  }

  /**
   * Invoke the batch callback for a single item, optionally tolerating errors.
   *
   * @param callable $callback
   *   The per-item callback.
   * @param mixed $item
   *   The item to process.
   * @param array $context
   *   The callback context ('index', 'total', 'results').
   * @param bool $continue_on_error
   *   TRUE to catch a thrown error and record it; FALSE to let it propagate.
   * @param array $errors
   *   Accumulator receiving a record for each caught failure (by reference).
   */
  protected function batchInvoke(callable $callback, mixed $item, array $context, bool $continue_on_error, array &$errors): void {
    if (!$continue_on_error) {
      $callback($item, $context);

      return;
    }

    try {
      $callback($item, $context);
    }
    catch (\Throwable $exception) {
      $errors[] = ['index' => $context['index'], 'item' => $item, 'message' => $exception->getMessage()];
    }
  }

  /**
   * Build the completion message, report it and record any failures.
   *
   * @param string $label
   *   Human-readable label for status messages.
   * @param int $total
   *   Total number of items processed.
   * @param array $errors
   *   Records of caught per-item failures.
   * @param string|null $status
   *   Reporter status the successful items are counted under, or NULL to report
   *   the completion message without counting.
   *
   * @return string
   *   The completion status message.
   */
  protected function batchFinish(string $label, int $total, array $errors, ?string $status): string {
    $failed = count($errors);
    $success = $total - $failed;

    if ($failed > 0) {
      $message = (string) $this->t('Processed @total @label, @failed failed.', [
        '@total' => $total,
        '@label' => $label,
        '@failed' => $failed,
      ]);
    }
    else {
      $message = (string) $this->t('Processed @total @label.', ['@total' => $total, '@label' => $label]);
    }

    if ($status !== NULL && $success > 0) {
      $this->getReporter()->record($status, $message, $success);
    }
    else {
      $this->getReporter()->message($message);
    }

    if ($failed > 0) {
      $this->getReporter()->failed($this->batchErrorSummary($errors), $failed);
    }

    return $message;
  }

  /**
   * Build a consolidated warning listing failed items.
   *
   * @param array $errors
   *   Records of caught per-item failures.
   *
   * @return string
   *   A warning listing the failures, truncated to the summary limit.
   */
  protected function batchErrorSummary(array $errors): string {
    $lines = [];
    foreach (array_slice($errors, 0, $this->errorSummaryLimit) as $error) {
      $lines[] = sprintf('%s: %s', $this->batchItemLabel($error['item']), $error['message']);
    }

    $suffix = count($errors) > $this->errorSummaryLimit ? sprintf(' (showing first %d of %d)', $this->errorSummaryLimit, count($errors)) : '';

    return (string) $this->t('@count failed - @items@suffix', [
      '@count' => count($errors),
      '@items' => implode('; ', $lines),
      '@suffix' => $suffix,
    ]);
  }

  /**
   * Render a batch item as a string for a failure message.
   *
   * @param mixed $item
   *   The item that failed.
   *
   * @return string
   *   A scalar item rendered as-is, otherwise its type name.
   */
  protected function batchItemLabel(mixed $item): string {
    return is_scalar($item) ? (string) $item : get_debug_type($item);
  }

}
