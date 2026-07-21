<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Traits;

use Drupal\Component\Serialization\Yaml;

/**
 * Render exported tree structures as ready-to-paste PHP or YAML.
 */
trait TreeExportTrait {

  /**
   * Return the tree as a nested array.
   */
  public const FORMAT_ARRAY = 'array';

  /**
   * Render the tree as a PHP array literal.
   */
  public const FORMAT_PHP = 'php';

  /**
   * Render the tree as YAML.
   */
  public const FORMAT_YAML = 'yaml';

  /**
   * Render a nested tree array in the requested format.
   *
   * @param array $tree
   *   Nested tree array.
   * @param string $format
   *   One of self::FORMAT_PHP or self::FORMAT_YAML.
   *
   * @return string
   *   The rendered tree.
   *
   * @throws \InvalidArgumentException
   *   When the format is not supported.
   */
  protected function renderTree(array $tree, string $format): string {
    return match ($format) {
      self::FORMAT_PHP => $this->renderPhp($tree),
      self::FORMAT_YAML => Yaml::encode($tree),
      default => throw new \InvalidArgumentException(sprintf('Unsupported export format "%s".', $format)),
    };
  }

  /**
   * Render a nested tree array as a PHP array literal using short syntax.
   *
   * @param array $tree
   *   Nested tree array.
   * @param int $depth
   *   Internal parameter for recursion. Current nesting depth.
   *
   * @return string
   *   PHP array literal with two-space indentation and trailing commas.
   */
  protected function renderPhp(array $tree, int $depth = 0): string {
    if ($tree === []) {
      return '[]';
    }

    $indent = str_repeat('  ', $depth + 1);
    $lines = [];

    foreach ($tree as $key => $value) {
      $rendered_key = is_string($key) ? var_export($key, TRUE) . ' => ' : '';
      $rendered_value = is_array($value) ? $this->renderPhp($value, $depth + 1) : var_export($value, TRUE);
      $lines[] = $indent . $rendered_key . $rendered_value . ',';
    }

    return '[' . PHP_EOL . implode(PHP_EOL, $lines) . PHP_EOL . str_repeat('  ', $depth) . ']';
  }

  /**
   * Compare two nodes by weight, falling back to a secondary string key.
   *
   * @param int $weight_a
   *   Weight of the first node.
   * @param string $key_a
   *   Secondary sort key of the first node.
   * @param int $weight_b
   *   Weight of the second node.
   * @param string $key_b
   *   Secondary sort key of the second node.
   *
   * @return int
   *   Negative, zero or positive per the usort() contract.
   */
  protected function compareByWeight(int $weight_a, string $key_a, int $weight_b, string $key_b): int {
    return ($weight_a <=> $weight_b) ?: strcmp($key_a, $key_b);
  }

}
