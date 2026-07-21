<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Unit;

use Drupal\drupal_helpers\Traits\TreeExportTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Drupal\Component\Serialization\Yaml;

/**
 * Tests for TreeExportTrait.
 */
#[CoversClass(TreeExportTrait::class)]
class TreeExportTraitTest extends TestCase {

  /**
   * The test helper instance using TreeExportTrait.
   */
  protected TreeExportTraitTestHelper $helper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->helper = new TreeExportTraitTestHelper();
  }

  /**
   * Tests rendering trees as PHP array literals.
   *
   * @dataProvider dataProviderRenderPhp
   */
  #[DataProvider('dataProviderRenderPhp')]
  public function testRenderPhp(array $tree, string $expected): void {
    $this->assertSame($expected, $this->helper->doRenderTree($tree, TreeExportTraitTestHelper::FORMAT_PHP));
  }

  /**
   * Data provider for testRenderPhp.
   */
  public static function dataProviderRenderPhp(): array {
    return [
      'empty' => [[], '[]'],
      'flat' => [
        ['News', 'Events'],
        "[\n  'News',\n  'Events',\n]",
      ],
      'nested' => [
        ['Finance' => ['Budgets', 'Grants'], 'Operations'],
        "[\n  'Finance' => [\n    'Budgets',\n    'Grants',\n  ],\n  'Operations',\n]",
      ],
      'escaping' => [
        ["O'Brien"],
        "[\n  'O\\'Brien',\n]",
      ],
    ];
  }

  /**
   * Tests rendering a tree as YAML.
   */
  public function testRenderYaml(): void {
    $tree = [
      'Home' => '/',
      'About' => [
        'path' => '/about',
        'children' => ['Team' => '/about/team'],
      ],
    ];

    $yaml = $this->helper->doRenderTree($tree, TreeExportTraitTestHelper::FORMAT_YAML);

    $this->assertSame($tree, Yaml::decode($yaml));
  }

  /**
   * Tests that an unsupported format throws.
   */
  public function testRenderTreeInvalidFormat(): void {
    $this->expectException(\InvalidArgumentException::class);

    $this->helper->doRenderTree(['News'], 'xml');
  }

  /**
   * Tests comparing nodes by weight, then by secondary key.
   *
   * @dataProvider dataProviderCompareByWeight
   */
  #[DataProvider('dataProviderCompareByWeight')]
  public function testCompareByWeight(int $weight_a, string $key_a, int $weight_b, string $key_b, int $expected_sign): void {
    $result = $this->helper->doCompareByWeight($weight_a, $key_a, $weight_b, $key_b);

    $this->assertSame($expected_sign, $result <=> 0);
  }

  /**
   * Data provider for testCompareByWeight.
   */
  public static function dataProviderCompareByWeight(): array {
    return [
      'lower weight first' => [0, 'b', 5, 'a', -1],
      'higher weight last' => [5, 'a', 0, 'b', 1],
      'equal weight sorts by key ascending' => [0, 'Apple', 0, 'Banana', -1],
      'equal weight sorts by key descending' => [0, 'Banana', 0, 'Apple', 1],
      'equal weight and key' => [3, 'same', 3, 'same', 0],
    ];
  }

}

/**
 * Test helper class that uses TreeExportTrait.
 */
class TreeExportTraitTestHelper {

  use TreeExportTrait;

  /**
   * Exposes renderTree() for testing.
   *
   * @param array $tree
   *   Nested tree array.
   * @param string $format
   *   Output format.
   *
   * @return string
   *   Rendered tree.
   */
  public function doRenderTree(array $tree, string $format): string {
    return $this->renderTree($tree, $format);
  }

  /**
   * Exposes compareByWeight() for testing.
   *
   * @param int $weight_a
   *   Weight of the first node.
   * @param string $key_a
   *   Secondary key of the first node.
   * @param int $weight_b
   *   Weight of the second node.
   * @param string $key_b
   *   Secondary key of the second node.
   *
   * @return int
   *   Comparison result.
   */
  public function doCompareByWeight(int $weight_a, string $key_a, int $weight_b, string $key_b): int {
    return $this->compareByWeight($weight_a, $key_a, $weight_b, $key_b);
  }

}
