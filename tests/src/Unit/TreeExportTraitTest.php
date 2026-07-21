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

}
