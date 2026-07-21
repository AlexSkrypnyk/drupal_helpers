<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use Drupal\drupal_helpers\Helper;
use Drupal\drupal_helpers\Report\Reporter;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the Reporter and its facade integration.
 */
#[CoversClass(Reporter::class)]
#[CoversClass(Helper::class)]
#[RunTestsInSeparateProcesses]
class ReporterKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system', 'taxonomy', 'user', 'text'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('user');

    $vocab_storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_vocabulary');
    $vocab_storage->create(['vid' => 'tags', 'name' => 'Tags'])->save();
  }

  /**
   * Tests that the facade returns the shared reporter service.
   */
  public function testReporterIsShared(): void {
    $this->assertSame($this->container->get('drupal_helpers.reporter'), Helper::reporter());
  }

  /**
   * Tests that operations across different helpers accumulate into one summary.
   */
  public function testCrossHelperAccumulation(): void {
    Helper::term()->createTree('tags', ['A']);
    Helper::role()->create('editor', 'Editor');

    $this->assertSame('Created 2.', Helper::report());
  }

  /**
   * Tests that created and skipped operations are tallied and rendered.
   */
  public function testCreatedAndSkipped(): void {
    Helper::term()->createTree('tags', ['A']);
    $this->assertSame('Created 1.', Helper::report());

    Helper::term()->createTree('tags', ['A', 'B']);
    $this->assertSame('Created 1, skipped 1.', Helper::report());
  }

  /**
   * Tests that reading the report resets the tally for the next hook.
   */
  public function testReportResets(): void {
    Helper::term()->createTree('tags', ['A', 'B']);
    $this->assertSame('Created 2.', Helper::report());
    $this->assertSame('No changes.', Helper::report());
  }

  /**
   * Tests that a tolerated batch failure is tallied as a failure.
   */
  public function testWarningModeTalliesFailures(): void {
    Helper::term()->createTree('tags', ['Keep', 'Fail']);
    Helper::report();

    $query = $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->getQuery()->accessCheck(FALSE);
    Helper::entity()->batchQuery($query, function ($term): void {
      if ($term->label() === 'Fail') {
        throw new \RuntimeException('nope');
      }
    }, TRUE);

    $this->assertSame('Processed 1, failed 1.', Helper::report());
  }

}
