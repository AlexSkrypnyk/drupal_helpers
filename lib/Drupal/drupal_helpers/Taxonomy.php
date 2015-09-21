<?php
/**
 * @file
 * Taxonomy-related helpers.
 */

namespace Drupal\drupal_helpers;

if (!module_exists('taxonomy')) {
  throw new Exception('Taxonomy module is not present.');
}

/**
 * Class Taxonomy.
 *
 * @package Drupal\drupal_helpers
 */
class Taxonomy {
  /**
   * Create form element options from terms in provided vocabulary.
   *
   * @param string $machine_name
   *   Vocabulary machine name.
   * @param string $depth_prefix
   *   Depth indentation prefix. Defaults to '-'.
   *
   * @return []
   *   Array of options keyed by term id and suitable for use with FAPI elements
   *   that support '#options' property.
   */
  public static function formElementOptions($machine_name, $depth_prefix = '-') {
    $options = [];

    $vocab = taxonomy_vocabulary_machine_name_load($machine_name);
    $terms = taxonomy_get_tree($vocab->vid);

    foreach ($terms as $term) {
      $options[$term->tid] = str_repeat($depth_prefix, $term->depth) . $term->name;
    }

    return $options;
  }

}
