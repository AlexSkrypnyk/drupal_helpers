<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\taxonomy\TermInterface;
use Drupal\drupal_helpers\Helpers\Term;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use Drupal\Component\Serialization\Yaml;

/**
 * Kernel tests for the Term helper service.
 */
#[CoversClass(Term::class)]
#[RunTestsInSeparateProcesses]
class TermHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system', 'taxonomy', 'user', 'text'];

  /**
   * The term helper service.
   */
  protected Term $termHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('user');

    // Create 'tags' vocabulary.
    $vocab_storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_vocabulary');
    $vocab_storage->create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();

    $this->termHelper = $this->container->get('drupal_helpers.term');
  }

  /**
   * Tests that requiredModules returns an empty array.
   */
  public function testRequiredModules(): void {
    $this->assertEquals([], $this->termHelper->requiredModules());
  }

  /**
   * Tests creating a flat list of terms.
   */
  public function testCreateTreeFlat(): void {
    $terms = $this->termHelper->createTree('tags', ['News', 'Events', 'Blog']);

    $this->assertCount(3, $terms);

    $names = array_map(fn(TermInterface $term) => $term->getName(), $terms);
    $this->assertContains('News', $names);
    $this->assertContains('Events', $names);
    $this->assertContains('Blog', $names);
  }

  /**
   * Tests creating a nested tree with parent-child relationships.
   */
  public function testCreateTreeNested(): void {
    $tree = [
      'Finance' => [
        'Budgets',
        'Grants',
      ],
      // phpcs:ignore Squiz.Arrays.ArrayDeclaration.NoKeySpecified
      'Operations',
    ];

    $terms = $this->termHelper->createTree('tags', $tree);

    $this->assertCount(4, $terms);

    // Find the parent and children.
    $terms_array = array_values($terms);
    $finance = $terms_array[0];
    $budgets = $terms_array[1];
    $grants = $terms_array[2];

    $this->assertEquals('Finance', $finance->getName());
    $this->assertEquals('Budgets', $budgets->getName());
    $this->assertEquals('Grants', $grants->getName());

    // Verify parent-child relationship.
    $parent_field = $budgets->get('parent')->first();
    $this->assertEquals((int) $finance->id(), (int) $parent_field->get('target_id')->getValue());
  }

  /**
   * Tests that existing terms are preserved by default.
   */
  public function testCreateTreePreserveExisting(): void {
    // Create 'News' first.
    $first_result = $this->termHelper->createTree('tags', ['News']);
    $first_terms = array_values($first_result);
    $original_tid = $first_terms[0]->id();

    // Create tree again with 'News' included.
    $second_result = $this->termHelper->createTree('tags', ['News', 'Events']);

    // Should have 2 terms total (no duplicate).
    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $all_news = $storage->loadByProperties(['vid' => 'tags', 'name' => 'News']);
    $this->assertCount(1, $all_news);

    // The existing term should be returned.
    $second_terms = array_values($second_result);
    $this->assertEquals($original_tid, $second_terms[0]->id());
  }

  /**
   * Tests overwriting existing terms when preserve_existing is FALSE.
   */
  public function testCreateTreeOverwrite(): void {
    $this->termHelper->createTree('tags', ['News']);

    // Create again with preserve_existing = FALSE.
    $this->termHelper->createTree('tags', ['News'], FALSE);

    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $all_news = $storage->loadByProperties(['vid' => 'tags', 'name' => 'News']);
    $this->assertCount(2, $all_news);
  }

  /**
   * Tests adding children beneath an already-existing parent term.
   */
  public function testCreateTreePreserveExistingWithChildren(): void {
    $this->termHelper->createTree('tags', ['Finance' => ['Budgets']]);

    // 'Finance' already exists; a second call adds children beneath it.
    $this->termHelper->createTree('tags', ['Finance' => ['Grants']]);

    $this->assertSame(['Finance' => ['Budgets', 'Grants']], $this->termHelper->exportTree('tags'));
  }

  /**
   * Tests exporting a flat vocabulary to a tree array.
   */
  public function testExportTreeFlat(): void {
    $this->termHelper->createTree('tags', ['News', 'Events', 'Blog']);

    $this->assertSame(['News', 'Events', 'Blog'], $this->termHelper->exportTree('tags'));
  }

  /**
   * Tests exporting a nested vocabulary preserves hierarchy and order.
   */
  public function testExportTreeNested(): void {
    $tree = [
      'Finance' => [
        'Budgets',
        'Grants',
      ],
      'Governance' => [
        'Policy' => [
          'Internal',
          'External',
        ],
        // phpcs:ignore Squiz.Arrays.ArrayDeclaration.NoKeySpecified
        'Compliance',
      ],
      // phpcs:ignore Squiz.Arrays.ArrayDeclaration.NoKeySpecified
      'Operations',
    ];

    $this->termHelper->createTree('tags', $tree);

    $this->assertSame($tree, $this->termHelper->exportTree('tags'));
  }

  /**
   * Tests exporting an empty vocabulary returns an empty array.
   */
  public function testExportTreeEmpty(): void {
    $this->assertSame([], $this->termHelper->exportTree('tags'));
  }

  /**
   * Tests that exporting then re-creating reproduces the structure.
   */
  public function testExportTreeRoundTrip(): void {
    $tree = [
      'Finance' => [
        'Budgets',
        'Grants',
      ],
      // phpcs:ignore Squiz.Arrays.ArrayDeclaration.NoKeySpecified
      'Operations',
    ];
    $this->termHelper->createTree('tags', $tree);
    $exported = $this->termHelper->exportTree('tags');
    $this->assertIsArray($exported);

    // Wipe the vocabulary and rebuild it from the exported structure.
    $this->termHelper->deleteAll('tags');
    $this->termHelper->createTree('tags', $exported);

    $this->assertSame($tree, $this->termHelper->exportTree('tags'));
  }

  /**
   * Tests exporting a vocabulary as a ready-to-paste PHP array literal.
   */
  public function testExportTreePhp(): void {
    $this->termHelper->createTree('tags', [
      'Finance' => [
        'Budgets',
        'Grants',
      ],
      // phpcs:ignore Squiz.Arrays.ArrayDeclaration.NoKeySpecified
      'Operations',
    ]);

    $expected = <<<'PHP'
    [
      'Finance' => [
        'Budgets',
        'Grants',
      ],
      'Operations',
    ]
    PHP;

    $this->assertSame($expected, $this->termHelper->exportTree('tags', Term::FORMAT_PHP));
  }

  /**
   * Tests exporting a vocabulary as YAML.
   */
  public function testExportTreeYaml(): void {
    $this->termHelper->createTree('tags', ['News', 'Events']);

    $yaml = $this->termHelper->exportTree('tags', Term::FORMAT_YAML);
    $this->assertIsString($yaml);

    $this->assertSame(['News', 'Events'], Yaml::decode($yaml));
  }

  /**
   * Tests exporting orders terms by weight, not by creation order.
   */
  public function testExportTreeOrdersByWeight(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $storage->create(['vid' => 'tags', 'name' => 'Third', 'weight' => 10])->save();
    $storage->create(['vid' => 'tags', 'name' => 'First', 'weight' => -5])->save();
    $storage->create(['vid' => 'tags', 'name' => 'Second', 'weight' => 0])->save();

    $this->assertSame(['First', 'Second', 'Third'], $this->termHelper->exportTree('tags'));
  }

  /**
   * Tests exporting warns when sibling parent terms share a name.
   */
  public function testExportTreeDuplicateNameWarns(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $first = $storage->create(['vid' => 'tags', 'name' => 'Dup', 'weight' => 0]);
    $first->save();
    $second = $storage->create(['vid' => 'tags', 'name' => 'Dup', 'weight' => 1]);
    $second->save();
    $storage->create(['vid' => 'tags', 'name' => 'ChildA', 'parent' => $first->id()])->save();
    $storage->create(['vid' => 'tags', 'name' => 'ChildB', 'parent' => $second->id()])->save();

    $this->termHelper->exportTree('tags');

    $messages = $this->container->get('messenger')->messagesByType('warning');
    $this->assertNotEmpty($messages);
  }

  /**
   * Tests deleting all terms from a vocabulary.
   */
  public function testDeleteAll(): void {
    $this->termHelper->createTree('tags', ['News', 'Events', 'Blog']);

    $result = $this->termHelper->deleteAll('tags');

    $this->assertNotNull($result);

    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $remaining = $storage->loadByProperties(['vid' => 'tags']);
    $this->assertEmpty($remaining);
  }

  /**
   * Tests deleting terms in sandbox batch mode.
   */
  public function testDeleteAllSandbox(): void {
    // Create 120+ terms.
    $names = [];
    for ($i = 0; $i < 125; $i++) {
      $names[] = 'Term ' . $i;
    }
    $this->termHelper->createTree('tags', $names);

    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $this->assertCount(125, $storage->loadByProperties(['vid' => 'tags']));

    // Use sandbox batching.
    $sandbox = [];
    $this->termHelper->setSandbox($sandbox);

    do {
      $result = $this->termHelper->deleteAll('tags');
    } while ($result === NULL);

    $storage->resetCache();
    $remaining = $storage->loadByProperties(['vid' => 'tags']);
    /** @phpstan-ignore method.impossibleType */
    $this->assertEmpty($remaining);
  }

  /**
   * Tests finding a term by name and vocabulary.
   */
  public function testFind(): void {
    $this->termHelper->createTree('tags', ['News', 'Events']);

    $term = $this->termHelper->find('News', 'tags');

    $this->assertNotNull($term);
    $this->assertEquals('News', $term->getName());
  }

  /**
   * Tests finding a term without specifying a vocabulary.
   */
  public function testFindAcrossVocabularies(): void {
    $this->termHelper->createTree('tags', ['Shared']);

    $term = $this->termHelper->find('Shared');

    $this->assertNotNull($term);
    $this->assertEquals('Shared', $term->getName());
  }

  /**
   * Tests that finding a non-existent term returns NULL.
   */
  public function testFindNotFound(): void {
    $term = $this->termHelper->find('Ghost', 'tags');

    $this->assertNull($term);
  }

}
