<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Unit;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\drupal_helpers\Report\Reporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Reporter.
 */
#[CoversClass(Reporter::class)]
class ReporterTest extends TestCase {

  /**
   * The logger channel mock.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $logger;

  /**
   * The messenger mock.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $messenger;

  /**
   * The reporter under test.
   */
  protected Reporter $reporter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->logger = $this->createMock(LoggerChannelInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->reporter = new Reporter($this->logger, $this->messenger);
  }

  /**
   * Tests the status-level wrappers count and surface a status message.
   *
   * @dataProvider dataProviderStatusWrappers
   */
  #[DataProvider('dataProviderStatusWrappers')]
  public function testStatusWrappers(string $method, string $status): void {
    $this->logger->expects($this->once())->method('info');
    $this->logger->expects($this->never())->method('warning');
    $this->messenger->expects($this->once())->method('addStatus');
    $this->messenger->expects($this->never())->method('addWarning');

    $this->reporter->$method('A message.');

    $this->assertSame(1, $this->reporter->count($status));
  }

  /**
   * Tests that failed() counts the failure and surfaces a warning by default.
   */
  public function testFailedWarnsByDefault(): void {
    $this->logger->expects($this->once())->method('warning');
    $this->messenger->expects($this->once())->method('addWarning');
    $this->messenger->expects($this->never())->method('addStatus');

    $this->reporter->failed('It failed.');

    $this->assertSame(1, $this->reporter->count(Reporter::FAILED));
  }

  /**
   * Tests that a skip can surface as a warning while still counting as skipped.
   */
  public function testSkippedWithWarningSeverity(): void {
    $this->logger->expects($this->once())->method('warning');
    $this->messenger->expects($this->once())->method('addWarning');
    $this->messenger->expects($this->never())->method('addStatus');

    $this->reporter->skipped('Target missing.', severity: Reporter::SEVERITY_WARNING);

    $this->assertSame(1, $this->reporter->count(Reporter::SKIPPED));
  }

  /**
   * Tests that a failure can be raised to an error severity.
   */
  public function testFailedWithErrorSeverity(): void {
    $this->logger->expects($this->once())->method('error');
    $this->messenger->expects($this->once())->method('addError');

    $this->reporter->failed('Boom.', severity: Reporter::SEVERITY_ERROR);

    $this->assertSame(1, $this->reporter->count(Reporter::FAILED));
  }

  /**
   * Tests that a record can represent more than one operation.
   */
  public function testRecordWithCount(): void {
    $this->reporter->record(Reporter::CREATED, 'Bulk.', 5);

    $this->assertSame(5, $this->reporter->count(Reporter::CREATED));
  }

  /**
   * Tests that message() surfaces a status line without counting or storing.
   */
  public function testMessageDoesNotCount(): void {
    $this->messenger->expects($this->once())->method('addStatus');

    $this->reporter->message('Progress.');

    $this->assertSame(0, $this->reporter->count(Reporter::PROCESSED));
    $this->assertSame([], $this->reporter->messages());
  }

  /**
   * Tests that recorded messages accumulate in order with their status.
   */
  public function testMessagesAccumulate(): void {
    $this->reporter->created('One.');
    $this->reporter->skipped('Two.');

    $this->assertSame([
      ['status' => Reporter::CREATED, 'message' => 'One.'],
      ['status' => Reporter::SKIPPED, 'message' => 'Two.'],
    ], $this->reporter->messages());
  }

  /**
   * Tests the summary rendering for various count combinations.
   *
   * @dataProvider dataProviderSummary
   */
  #[DataProvider('dataProviderSummary')]
  public function testSummary(array $records, string $expected): void {
    foreach ($records as [$status, $count]) {
      $this->reporter->record($status, 'msg', $count);
    }

    $this->assertSame($expected, $this->reporter->summary());
  }

  /**
   * Tests that reset() clears counts and messages.
   */
  public function testReset(): void {
    $this->reporter->created('x');
    $this->reporter->reset();

    $this->assertSame(0, $this->reporter->count(Reporter::CREATED));
    $this->assertSame([], $this->reporter->messages());
    $this->assertSame('No changes.', $this->reporter->summary());
  }

  /**
   * Data provider for testStatusWrappers().
   */
  public static function dataProviderStatusWrappers(): array {
    return [
      'created' => ['created', Reporter::CREATED],
      'updated' => ['updated', Reporter::UPDATED],
      'deleted' => ['deleted', Reporter::DELETED],
      'skipped' => ['skipped', Reporter::SKIPPED],
    ];
  }

  /**
   * Data provider for testSummary().
   */
  public static function dataProviderSummary(): array {
    return [
      'nothing recorded' => [[], 'No changes.'],
      'single status' => [[[Reporter::CREATED, 12]], 'Created 12.'],
      'two statuses' => [[[Reporter::CREATED, 12], [Reporter::SKIPPED, 3]], 'Created 12, skipped 3.'],
      'display order independent of record order' => [[[Reporter::FAILED, 1], [Reporter::CREATED, 2]], 'Created 2, failed 1.'],
      'custom status appended last' => [[[Reporter::CREATED, 1], ['archived', 4]], 'Created 1, archived 4.'],
      'zero counts are omitted' => [[[Reporter::CREATED, 0], [Reporter::UPDATED, 2]], 'Updated 2.'],
    ];
  }

}
