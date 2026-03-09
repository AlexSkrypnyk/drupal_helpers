<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\taxonomy\TermInterface;

/**
 * Taxonomy term helpers for deploy hooks.
 */
class Term extends HelperBase {

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
          $this->messenger->addStatus($this->t('Term "@name" already exists in "@vocabulary" — skipped.', [
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

      $this->messenger->addStatus($this->t('Created term "@name" (tid: @tid) in "@vocabulary".', [
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
    }, ['vid' => $vocabulary]);
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
