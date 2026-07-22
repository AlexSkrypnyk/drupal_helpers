<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\drupal_helpers\Report\Reporter;
use Drupal\drupal_helpers\Traits\TreeExportTrait;
use Drupal\drupal_helpers\Traits\TreeSyncTrait;
use Drupal\taxonomy\TermInterface;

/**
 * Taxonomy term helpers for deploy hooks.
 */
class Term extends HelperBase {

  use TreeExportTrait;
  use TreeSyncTrait;

  /**
   * Create terms from a nested tree structure.
   *
   * @code
   * // Flat list:
   * Helper::term()->createTree('tags', ['News', 'Events', 'Blog']);
   *
   * // Nested hierarchy:
   * Helper::term()->createTree('topics', [
   *   'Finance' => [
   *     'Budgets',
   *     'Grants',
   *   ],
   *   'Governance' => [
   *     'Policy' => [
   *       'Internal',
   *       'External',
   *     ],
   *     'Compliance',
   *   ],
   *   'Operations',
   * ]);
   *
   * // Reconcile: re-apply the tree to existing terms and delete any not listed.
   * $tree = ['Finance' => ['Budgets', 'Grants'], 'Operations'];
   * Helper::term()->createTree('topics', $tree, mode: Term::MODE_SYNC);
   * @endcode
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   * @param array $tree
   *   Nested array where keys with array values are parent terms and scalar
   *   values are leaf terms.
   * @param string $mode
   *   Reconciliation mode, matching terms by name within their parent:
   *   self::MODE_SAFE (default) creates missing terms and leaves existing ones
   *   untouched; self::MODE_UPDATE also re-applies the tree order to existing
   *   terms; self::MODE_SYNC additionally deletes terms absent from the tree.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Array of created, updated or preserved terms keyed by term ID.
   */
  public function createTree(string $vocabulary, array $tree, string $mode = self::MODE_SAFE): array {
    $this->assertMode($mode);

    $existing = $this->indexTermsByParent($vocabulary);
    $kept = [];
    $terms = $this->reconcileTermTree($vocabulary, $tree, 0, $mode, $existing, $kept);

    if ($mode === self::MODE_SYNC) {
      $this->syncDeleteTerms($vocabulary, $tree, $kept);
    }

    return $terms;
  }

  /**
   * Reconcile one level of a term tree, recursing into children.
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   * @param array $tree
   *   The nested tree level to reconcile.
   * @param int $parent_tid
   *   Parent term ID for this level (0 for the root level).
   * @param string $mode
   *   Reconciliation mode.
   * @param array $existing
   *   Existing terms indexed as [parent term ID][name] => term.
   * @param array<int|string, bool> $kept
   *   Accumulates the IDs of reconciled terms by reference, as a set of
   *   [term ID => TRUE].
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Reconciled terms for this level and its descendants, keyed by term ID.
   */
  protected function reconcileTermTree(string $vocabulary, array $tree, int $parent_tid, string $mode, array $existing, array &$kept): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = [];
    $weight = 0;

    foreach ($tree as $key => $subtree) {
      $name = is_array($subtree) ? $key : $subtree;
      $children = is_array($subtree) ? $subtree : [];

      $term = $existing[$parent_tid][$name] ?? NULL;

      if ($term instanceof TermInterface) {
        $this->reconcileTerm($term, $weight, $mode, $vocabulary);
      }
      else {
        /** @var \Drupal\taxonomy\TermInterface $term */
        $term = $storage->create([
          'vid' => $vocabulary,
          'name' => $name,
          'weight' => $weight,
          'parent' => $parent_tid,
        ]);
        $term->save();

        $this->reporter->created($this->t('Created term "@name" (tid: @tid) in "@vocabulary".', [
          '@name' => $name,
          '@tid' => $term->id(),
          '@vocabulary' => $vocabulary,
        ]));

        // A name repeated under the same parent within the input reuses the
        // term just created rather than duplicating it.
        $existing[$parent_tid][$name] = $term;
      }

      $terms[$term->id()] = $term;
      $kept[$term->id()] = TRUE;

      if ($children !== []) {
        $terms += $this->reconcileTermTree($vocabulary, $children, (int) $term->id(), $mode, $existing, $kept);
      }

      $weight++;
    }

    return $terms;
  }

  /**
   * Apply the reconciliation mode to an existing term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The existing term matched in the tree.
   * @param int $weight
   *   The term's position within its parent.
   * @param string $mode
   *   Reconciliation mode.
   * @param string $vocabulary
   *   Vocabulary machine name, for reporting.
   */
  protected function reconcileTerm(TermInterface $term, int $weight, string $mode, string $vocabulary): void {
    if ($mode === self::MODE_SAFE) {
      $this->reporter->skipped($this->t('Term "@name" already exists in "@vocabulary" - skipped.', [
        '@name' => $term->getName(),
        '@vocabulary' => $vocabulary,
      ]));

      return;
    }

    $term->set('weight', $weight);
    $term->save();

    $this->reporter->updated($this->t('Updated term "@name" (tid: @tid) in "@vocabulary".', [
      '@name' => $term->getName(),
      '@tid' => $term->id(),
      '@vocabulary' => $vocabulary,
    ]));
  }

  /**
   * Index all terms in a vocabulary by parent term ID and name.
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   *
   * @return array<int, array<string, \Drupal\taxonomy\TermInterface>>
   *   Terms indexed as [parent term ID][name] => term. When several terms share
   *   a name under the same parent, the first loaded wins.
   */
  protected function indexTermsByParent(string $vocabulary): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $index = [];

    foreach ($storage->loadByProperties(['vid' => $vocabulary]) as $term) {
      $index[$this->termParentId($term)][$term->getName()] ??= $term;
    }

    return $index;
  }

  /**
   * Delete terms absent from the supplied tree during a sync.
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   * @param array $tree
   *   The tree that was reconciled.
   * @param array<int|string, bool> $kept
   *   Set of kept term IDs.
   */
  protected function syncDeleteTerms(string $vocabulary, array $tree, array $kept): void {
    if ($tree === []) {
      $this->reporter->skipped($this->t('Refused to delete every term in "@vocabulary" from an empty sync tree; use deleteAll() to clear it intentionally.', [
        '@vocabulary' => $vocabulary,
      ]), severity: Reporter::SEVERITY_WARNING);

      return;
    }

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    foreach ($storage->loadByProperties(['vid' => $vocabulary]) as $tid => $term) {
      if (isset($kept[$tid])) {
        continue;
      }

      $this->reporter->deleted($this->t('Deleted term "@name" (tid: @tid) from "@vocabulary".', [
        '@name' => $term->getName(),
        '@tid' => $term->id(),
        '@vocabulary' => $vocabulary,
      ]));
      $term->delete();
    }
  }

  /**
   * Export a vocabulary to the nested tree accepted by createTree().
   *
   * Sibling parent terms that share a name cannot both be represented and are
   * reported with a warning during export.
   *
   * @code
   * // Snapshot structure as data:
   * $tree = Helper::term()->exportTree('topics');
   *
   * // Render as ready-to-paste PHP or YAML:
   * $php = Helper::term()->exportTree('topics', Term::FORMAT_PHP);
   * $yaml = Helper::term()->exportTree('topics', Term::FORMAT_YAML);
   * @endcode
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   * @param string $format
   *   Output format: self::FORMAT_ARRAY (default), self::FORMAT_PHP or
   *   self::FORMAT_YAML.
   *
   * @return array|string
   *   The nested tree array, or a rendered PHP/YAML string.
   */
  public function exportTree(string $vocabulary, string $format = self::FORMAT_ARRAY): array|string {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $children_by_parent = [];
    foreach ($storage->loadByProperties(['vid' => $vocabulary]) as $term) {
      $children_by_parent[$this->termParentId($term)][] = $term;
    }

    foreach ($children_by_parent as &$terms) {
      usort($terms, $this->compareTerms(...));
    }
    unset($terms);

    $tree = $this->buildTermTree($children_by_parent, 0);

    return $format === self::FORMAT_ARRAY ? $tree : $this->renderTree($tree, $format);
  }

  /**
   * Build a nested term tree from terms grouped by parent.
   *
   * @param array $children_by_parent
   *   Term entities keyed by parent term ID, each level ordered by weight then
   *   name.
   * @param int $parent_tid
   *   Parent term ID whose level is being built (0 for the root level).
   *
   * @return array
   *   Nested tree where parent terms are string keys and leaf terms are scalar
   *   values, matching the structure accepted by createTree().
   */
  protected function buildTermTree(array $children_by_parent, int $parent_tid): array {
    $tree = [];

    foreach ($children_by_parent[$parent_tid] ?? [] as $term) {
      $name = $term->getName();
      $children = $this->buildTermTree($children_by_parent, (int) $term->id());

      if ($children === []) {
        $tree[] = $name;

        continue;
      }

      if (isset($tree[$name])) {
        $this->reporter->skipped($this->t('Parent terms share the name "@name" at the same level; the exported tree can only keep one.', [
          '@name' => $name,
        ]), severity: Reporter::SEVERITY_WARNING);
      }

      $tree[$name] = $children;
    }

    return $tree;
  }

  /**
   * Get the parent term ID of a term (0 when it is a root term).
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term.
   *
   * @return int
   *   Parent term ID, or 0 when the term has no parent.
   */
  protected function termParentId(TermInterface $term): int {
    $value = $term->get('parent')->getValue();

    return (int) ($value[0]['target_id'] ?? 0);
  }

  /**
   * Compare two terms by weight, then by name.
   *
   * @param \Drupal\taxonomy\TermInterface $a
   *   First term.
   * @param \Drupal\taxonomy\TermInterface $b
   *   Second term.
   *
   * @return int
   *   Negative, zero or positive per the usort() contract.
   */
  protected function compareTerms(TermInterface $a, TermInterface $b): int {
    return $this->compareByWeight($a->getWeight(), $a->getName(), $b->getWeight(), $b->getName());
  }

  /**
   * Delete all terms from a vocabulary.
   *
   * @code
   * Helper::term()->deleteAll('tags');
   * @endcode
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress.
   */
  public function deleteAll(string $vocabulary): ?string {
    return $this->batchEntity('taxonomy_term', NULL, function ($term): void {
      $term->delete();
    }, ['vid' => $vocabulary], status: Reporter::DELETED);
  }

  /**
   * Find a term by name in a vocabulary.
   *
   * Returns the first matching term.
   *
   * @code
   * $term = Helper::term()->find('News', 'tags');
   * @endcode
   *
   * @param string $name
   *   Term name.
   * @param string|null $vocabulary
   *   Vocabulary machine name. If NULL, searches all vocabularies.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   Term entity or NULL if not found.
   */
  public function find(string $name, ?string $vocabulary = NULL): ?TermInterface {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $properties = ['name' => $name];

    if ($vocabulary !== NULL) {
      $properties['vid'] = $vocabulary;
    }

    $terms = $storage->loadByProperties($properties);

    return $terms ? reset($terms) : NULL;
  }

}
