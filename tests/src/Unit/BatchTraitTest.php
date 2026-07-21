<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\drupal_helpers\Report\Reporter;
use Drupal\drupal_helpers\Traits\BatchTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BatchTrait.
 */
#[CoversClass(BatchTrait::class)]
class BatchTraitTest extends TestCase {

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $entityTypeManager;

  /**
   * The messenger mock.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $messenger;

  /**
   * The reporter forwarding to the messenger mock.
   */
  protected Reporter $reporter;

  /**
   * The test helper instance using BatchTrait.
   */
  protected BatchTraitTestHelper $helper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->reporter = new Reporter($this->createMock(LoggerChannelInterface::class), $this->messenger);

    $this->helper = new BatchTraitTestHelper($this->entityTypeManager, $this->reporter);

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(fn($input): string => (string) $input->getUntranslatedString());
    $this->helper->setStringTranslation($translation);
  }

  /**
   * Tests non-sandbox mode with various item counts.
   *
   * @dataProvider dataProviderBatchNonSandbox
   */
  #[DataProvider('dataProviderBatchNonSandbox')]
  public function testBatchNonSandbox(array $items, int $expected_count): void {
    $processed = [];
    $callback = function ($item, array $context) use (&$processed): void {
      $processed[] = [
        'item' => $item,
        'index' => $context['index'],
        'total' => $context['total'],
      ];
    };

    $this->messenger->expects($this->once())->method('addStatus');

    $result = $this->helper->batch($items, $callback, 'things');

    $this->assertCount($expected_count, $processed);
    $this->assertStringContainsString((string) $expected_count, $result);
    $this->assertStringContainsString('things', $result);

    foreach ($processed as $index => $entry) {
      $this->assertSame($index, $entry['index']);
      $this->assertSame($expected_count, $entry['total']);
      $this->assertSame($items[$index], $entry['item']);
    }
  }

  /**
   * Data provider for testBatchNonSandbox.
   */
  public static function dataProviderBatchNonSandbox(): array {
    return [
      '1 item' => [['a'], 1],
      '3 items' => [['a', 'b', 'c'], 3],
      '5 items' => [['x', 'y', 'z', 'w', 'v'], 5],
    ];
  }

  /**
   * Tests non-sandbox mode with empty items.
   */
  public function testBatchNonSandboxEmpty(): void {
    $this->messenger->expects($this->once())->method('addStatus');

    $result = $this->helper->batch([], function (): void {
    }, 'items');

    $this->assertStringContainsString('No', $result);
    $this->assertStringContainsString('items', $result);
    $this->assertStringContainsString('process', $result);
  }

  /**
   * Tests sandbox mode processes all items across multiple batches.
   */
  public function testBatchSandbox(): void {
    $items = range(1, 120);
    /** @var array<string, mixed> $sandbox */
    $sandbox = [];
    $this->helper->setSandbox($sandbox);
    $this->helper->setBatchSize(50);

    $processed = [];
    $callback = function ($item, array $context) use (&$processed): void {
      $processed[] = $item;
    };

    // First call: items 1-50.
    $result = $this->helper->batch($items, $callback, 'records');
    $this->assertNull($result);
    $this->assertCount(50, $processed);

    // Second call: items 51-100.
    $result = $this->helper->batch($items, $callback, 'records');
    $this->assertNull($result);
    /** @phpstan-ignore method.impossibleType */
    $this->assertCount(100, $processed);

    // Third call: items 101-120 (final).
    $this->messenger->expects($this->once())->method('addStatus');
    $result = $this->helper->batch($items, $callback, 'records');
    $this->assertNotNull($result);
    /** @phpstan-ignore method.impossibleType */
    $this->assertCount(120, $processed);
    $this->assertStringContainsString('120', $result);
    $this->assertStringContainsString('records', $result);

    // Verify all items were processed in order.
    /** @phpstan-ignore method.impossibleType */
    $this->assertSame($items, $processed);
  }

  /**
   * Tests sandbox progress tracking.
   */
  public function testBatchSandboxProgress(): void {
    $items = range(1, 120);
    /** @var array<string, mixed> $sandbox */
    $sandbox = [];
    $this->helper->setSandbox($sandbox);
    $this->helper->setBatchSize(50);

    $callback = function (): void {
    };

    // First batch: 50/120.
    $this->helper->batch($items, $callback, 'items');
    $this->assertEqualsWithDelta(50 / 120, $sandbox['#finished'], 0.001);

    // Second batch: 100/120.
    $this->helper->batch($items, $callback, 'items');
    $this->assertEqualsWithDelta(100 / 120, $sandbox['#finished'], 0.001);

    // Third batch: 120/120 = 1.0.
    $this->helper->batch($items, $callback, 'items');
    $this->assertEqualsWithDelta(1.0, $sandbox['#finished'], 0.001);
  }

  /**
   * Tests sandbox mode with empty items returns immediate completion.
   */
  public function testBatchSandboxEmpty(): void {
    /** @var array<string, mixed> $sandbox */
    $sandbox = [];
    $this->helper->setSandbox($sandbox);

    $this->messenger->expects($this->once())->method('addStatus');

    $result = $this->helper->batch([], function (): void {
    }, 'items');

    $this->assertNotNull($result);
    $this->assertStringContainsString('No', $result);
    $this->assertSame(1, $sandbox['#finished']);
  }

  /**
   * Tests callback context has correct keys and results accumulate.
   */
  public function testBatchCallbackContext(): void {
    $items = ['a', 'b', 'c'];
    $captured_contexts = [];

    $callback = function (string $item, array $context) use (&$captured_contexts): void {
      $this->assertArrayHasKey('index', $context);
      $this->assertArrayHasKey('total', $context);
      $this->assertArrayHasKey('results', $context);

      $context['results'][] = $item . '_done';
      $captured_contexts[] = $context;
    };

    $this->helper->batch($items, $callback, 'items');

    $this->assertCount(3, $captured_contexts);

    // Verify results accumulate by reference.
    $this->assertSame(['a_done', 'b_done', 'c_done'], $captured_contexts[2]['results']);
  }

  /**
   * Tests batchEntity in non-sandbox mode.
   */
  public function testBatchEntityNonSandbox(): void {
    $entity1 = $this->createMock(EntityInterface::class);
    $entity2 = $this->createMock(EntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnMap([
      [1, $entity1],
      [2, $entity2],
    ]);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([1, 2]);

    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager->method('getStorage')->willReturn($storage);
    $this->entityTypeManager->method('getDefinition')->willReturn($this->createMock(EntityTypeInterface::class));

    $processed_entities = [];
    $callback = function ($entity, array $context) use (&$processed_entities): void {
      $processed_entities[] = $entity;
    };

    $result = $this->helper->batchEntity('node', NULL, $callback);

    $this->assertNotNull($result);
    $this->assertCount(2, $processed_entities);
    $this->assertSame($entity1, $processed_entities[0]);
    $this->assertSame($entity2, $processed_entities[1]);
  }

  /**
   * Tests batchEntity in sandbox mode with 120+ IDs.
   */
  public function testBatchEntitySandbox(): void {
    $ids = range(1, 125);

    $entities = [];
    $load_map = [];
    foreach ($ids as $id) {
      $entity = $this->createMock(EntityInterface::class);
      $entities[$id] = $entity;
      $load_map[] = [$id, $entity];
    }

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnMap($load_map);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn($ids);

    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager->method('getStorage')->willReturn($storage);
    $this->entityTypeManager->method('getDefinition')->willReturn($this->createMock(EntityTypeInterface::class));

    /** @var array<string, mixed> $sandbox */
    $sandbox = [];
    $this->helper->setSandbox($sandbox);
    $this->helper->setBatchSize(50);

    $processed_entities = [];
    $callback = function ($entity, array $context) use (&$processed_entities): void {
      $processed_entities[] = $entity;
    };

    // Iterate until sandbox is complete.
    $result = NULL;
    $iterations = 0;
    while ($result === NULL && $iterations < 10) {
      $result = $this->helper->batchEntity('node', NULL, $callback);
      $iterations++;
    }

    $this->assertNotNull($result);
    $this->assertCount(125, $processed_entities);
    $this->assertStringContainsString('125', $result);
    $this->assertSame(3, $iterations);
  }

  /**
   * Tests batchEntity skips entities when storage returns NULL.
   */
  public function testBatchEntitySkipsMissing(): void {
    $entity1 = $this->createMock(EntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnMap([
      [1, $entity1],
      [2, NULL],
      [3, NULL],
    ]);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([1, 2, 3]);

    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager->method('getStorage')->willReturn($storage);
    $this->entityTypeManager->method('getDefinition')->willReturn($this->createMock(EntityTypeInterface::class));

    $processed_entities = [];
    $callback = function ($entity, array $context) use (&$processed_entities): void {
      $processed_entities[] = $entity;
    };

    $result = $this->helper->batchEntity('node', NULL, $callback);

    $this->assertNotNull($result);
    // Only entity1 should be processed; entities 2 and 3 are NULL.
    $this->assertCount(1, $processed_entities);
    $this->assertSame($entity1, $processed_entities[0]);
  }

  /**
   * Tests continue-on-error records failures and keeps processing (no sandbox).
   */
  public function testBatchContinueOnErrorNonSandbox(): void {
    $items = ['ok1', 'bad', 'ok2'];
    $processed = [];

    $callback = function (string $item) use (&$processed): void {
      if ($item === 'bad') {
        throw new \RuntimeException('boom');
      }
      $processed[] = $item;
    };

    $warnings = [];
    $this->messenger->expects($this->once())->method('addStatus');
    $this->messenger->method('addWarning')->willReturnCallback(function ($message) use (&$warnings): MessengerInterface {
      $warnings[] = (string) $message;

      return $this->messenger;
    });

    $result = $this->helper->batch($items, $callback, 'things', TRUE);

    $this->assertSame(['ok1', 'ok2'], $processed);
    $this->assertStringContainsString('Processed 3 things', $result);
    $this->assertStringContainsString('1 failed', $result);
    $this->assertCount(1, $warnings);
    $this->assertStringContainsString('boom', $warnings[0]);
  }

  /**
   * Tests continue-on-error with no failures yields the normal message.
   */
  public function testBatchContinueOnErrorNoFailures(): void {
    $this->messenger->expects($this->once())->method('addStatus');
    $this->messenger->expects($this->never())->method('addWarning');

    $result = $this->helper->batch(['a', 'b'], function (): void {
    }, 'items', TRUE);

    $this->assertStringContainsString('Processed 2 items', $result);
    $this->assertStringNotContainsString('failed', $result);
  }

  /**
   * Tests a thrown error aborts the run when continue-on-error is off.
   */
  public function testBatchAbortsWithoutContinueOnError(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('boom');

    $this->helper->batch(['a'], function (): void {
      throw new \RuntimeException('boom');
    }, 'items');
  }

  /**
   * Tests sandbox continue-on-error accumulates failures across batches.
   */
  public function testBatchContinueOnErrorSandbox(): void {
    $items = ['a', 'bad1', 'c', 'bad2', 'e'];
    /** @var array<string, mixed> $sandbox */
    $sandbox = [];
    $this->helper->setSandbox($sandbox);
    $this->helper->setBatchSize(2);

    $processed = [];
    $callback = function (string $item) use (&$processed): void {
      if (str_starts_with($item, 'bad')) {
        throw new \RuntimeException('fail ' . $item);
      }
      $processed[] = $item;
    };

    $this->messenger->expects($this->once())->method('addStatus');
    $this->messenger->expects($this->once())->method('addWarning');

    $result1 = $this->helper->batch($items, $callback, 'items', TRUE);
    $result2 = $this->helper->batch($items, $callback, 'items', TRUE);
    $result = $this->helper->batch($items, $callback, 'items', TRUE);

    $this->assertNull($result1);
    $this->assertNull($result2);
    $this->assertNotNull($result);
    $this->assertStringContainsString('Processed 5 items, 2 failed', $result);
    $this->assertSame(['a', 'c', 'e'], $processed);
    $this->assertCount(2, $sandbox['errors']);
    $this->assertSame(1, $sandbox['errors'][0]['index']);
    $this->assertSame('bad1', $sandbox['errors'][0]['item']);
    $this->assertStringContainsString('fail bad1', $sandbox['errors'][0]['message']);
    $this->assertSame(3, $sandbox['errors'][1]['index']);
    $this->assertSame('bad2', $sandbox['errors'][1]['item']);
  }

  /**
   * Tests the failure warning truncates the list and labels non-scalar items.
   */
  public function testBatchErrorSummaryTruncatesAndLabelsItems(): void {
    $items = array_fill(0, 12, ['some' => 'data']);

    $warnings = [];
    $this->messenger->method('addWarning')->willReturnCallback(function ($message) use (&$warnings): MessengerInterface {
      $warnings[] = (string) $message;

      return $this->messenger;
    });

    $result = $this->helper->batch($items, function (): void {
      throw new \RuntimeException('nope');
    }, 'rows', TRUE);

    $this->assertStringContainsString('Processed 12 rows, 12 failed', $result);
    $this->assertCount(1, $warnings);
    $this->assertStringContainsString('12 failed', $warnings[0]);
    $this->assertStringContainsString('array: nope', $warnings[0]);
    $this->assertStringContainsString('showing first 10 of 12', $warnings[0]);
  }

  /**
   * Tests batchEntity applies the bundle key and conditions to the query.
   */
  public function testBatchEntityBundleAndConditions(): void {
    $entity1 = $this->createMock(EntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($entity1);

    $captured = [];
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('getEntityTypeId')->willReturn('node');
    $query->method('condition')->willReturnCallback(function (string $field, mixed $value) use (&$captured, $query): QueryInterface {
      $captured[$field] = $value;

      return $query;
    });
    $query->method('execute')->willReturn([1]);
    $storage->method('getQuery')->willReturn($query);

    $definition = $this->createMock(EntityTypeInterface::class);
    $definition->method('getKey')->willReturn('type');

    $this->entityTypeManager->method('getStorage')->willReturn($storage);
    $this->entityTypeManager->method('getDefinition')->willReturn($definition);

    $processed = [];
    $result = $this->helper->batchEntity('node', 'article', function ($entity) use (&$processed): void {
      $processed[] = $entity;
    }, ['status' => 1, 'field_x' => 'y']);

    $this->assertNotNull($result);
    $this->assertCount(1, $processed);
    $this->assertSame(['type' => 'article', 'status' => 1, 'field_x' => 'y'], $captured);
  }

  /**
   * Tests batchEntity records per-item failures when tolerating errors.
   */
  public function testBatchEntityContinueOnError(): void {
    $entity1 = $this->createMock(EntityInterface::class);
    $entity2 = $this->createMock(EntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnMap([[1, $entity1], [2, $entity2]]);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('getEntityTypeId')->willReturn('node');
    $query->method('execute')->willReturn([1, 2]);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager->method('getStorage')->willReturn($storage);
    $this->entityTypeManager->method('getDefinition')->willReturn($this->createMock(EntityTypeInterface::class));

    $this->messenger->expects($this->once())->method('addWarning');

    $result = $this->helper->batchEntity('node', NULL, function ($entity) use ($entity2): void {
      if ($entity === $entity2) {
        throw new \RuntimeException('cannot process');
      }
    }, [], TRUE);

    $this->assertStringContainsString('Processed 2 node entities, 1 failed', $result);
  }

}

/**
 * Test helper class that uses BatchTrait.
 */
class BatchTraitTestHelper {

  use BatchTrait;
  use StringTranslationTrait;

  /**
   * Constructs a BatchTraitTestHelper.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Reporter $reporter,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getReporter(): Reporter {
    return $this->reporter;
  }

}
