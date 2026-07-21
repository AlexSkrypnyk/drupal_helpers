<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\drupal_helpers\Report\Reporter;
use Drupal\drupal_helpers\Traits\TreeExportTrait;
use Drupal\taxonomy\TermInterface;

/**
 * Taxonomy term helpers for deploy hooks.
 */
class Term extends HelperBase {

  use TreeExportTrait;

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
   * @endcode
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   * @param array $tree
   *   Nested array where keys with array values are parent terms and scalar
   *   values are leaf terms.
   * @param bool $preserve_existing
   *   If TRUE, skip creating terms that already exist in the vocabulary.
   *   Defaults to TRUE.
   * @param int $parent_tid
   *   Internal parameter for recursion. Parent term ID.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Array of created or existing terms keyed by term ID.
   */
  public function createTree(string $vocabulary, array $tree, bool $preserve_existing = TRUE, int $parent_tid = 0): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = [];
    $weight = 0;

    foreach ($tree as $parent => $subtree) {
      $name = is_array($subtree) ? $parent : $subtree;

      if ($preserve_existing) {
        $existing = $storage->loadByProperties(['vid' => $vocabulary, 'name' => $name]);
        if ($existing) {
          $term = reset($existing);
          $this->reporter->skipped($this->t('Term "@name" already exists in "@vocabulary" — skipped.', [
            '@name' => $name,
            '@vocabulary' => $vocabulary,
          ]));
          $terms[$term->id()] = $term;

          if (is_array($subtree)) {
            $terms += $this->createTree($vocabulary, $subtree, $preserve_existing, (int) $term->id());
          }

          $weight++;

          continue;
        }
      }

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

      $terms[$term->id()] = $term;

      if (is_array($subtree)) {
        $terms += $this->createTree($vocabulary, $subtree, $preserve_existing, (int) $term->id());
      }

      $weight++;
    }

    return $terms;
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
        $this->messenger->addWarning($this->t('Parent terms share the name "@name" at the same level; the exported tree can only keep one.', [
          '@name' => $name,
        ]));
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
