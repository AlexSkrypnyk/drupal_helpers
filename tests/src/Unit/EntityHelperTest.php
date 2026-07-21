<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Unit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\drupal_helpers\Helpers\Entity;
use Drupal\drupal_helpers\Report\Reporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Entity helper service.
 */
#[CoversClass(Entity::class)]
class EntityHelperTest extends TestCase {

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
   * The entity helper under test.
   */
  protected Entity $entityHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->reporter = new Reporter($this->createMock(LoggerChannelInterface::class), $this->messenger);

    $this->entityHelper = new Entity($this->entityTypeManager, $this->reporter);

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(fn($input): string => (string) $input->getUntranslatedString());
    $this->entityHelper->setStringTranslation($translation);
  }

  /**
   * Tests batchQuery disables access checks and processes matched entities.
   */
  public function testBatchQuery(): void {
    $entity1 = $this->createMock(EntityInterface::class);
    $entity2 = $this->createMock(EntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnMap([[1, $entity1], [2, $entity2]]);

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('getEntityTypeId')->willReturn('node');
    $query->method('execute')->willReturn([1, 2]);

    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $processed = [];
    $result = $this->entityHelper->batchQuery($query, function ($entity) use (&$processed): void {
      $processed[] = $entity;
    });

    $this->assertStringContainsString('Processed 2 node entities', $result);
    $this->assertSame([$entity1, $entity2], $processed);
  }

  /**
   * Tests batchQuery collects per-item failures when tolerating errors.
   */
  public function testBatchQueryContinueOnError(): void {
    $entity1 = $this->createMock(EntityInterface::class);
    $entity2 = $this->createMock(EntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnMap([[1, $entity1], [2, $entity2]]);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('getEntityTypeId')->willReturn('node');
    $query->method('execute')->willReturn([1, 2]);

    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $this->messenger->expects($this->once())->method('addWarning');

    $result = $this->entityHelper->batchQuery($query, function ($entity) use ($entity1): void {
      if ($entity === $entity1) {
        throw new \RuntimeException('bad entity');
      }
    }, TRUE);

    $this->assertStringContainsString('Processed 2 node entities, 1 failed', $result);
  }

  /**
   * Tests batchSetField assigns the value and saves each matched entity.
   */
  public function testBatchSetField(): void {
    $entity1 = $this->createMock(FieldableEntityInterface::class);
    $entity1->expects($this->once())->method('set')->with('field_status', 'archived');
    $entity1->expects($this->once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($entity1);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('getEntityTypeId')->willReturn('node');
    $query->method('execute')->willReturn([1]);

    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $result = $this->entityHelper->batchSetField($query, 'field_status', 'archived');

    $this->assertStringContainsString('Processed 1 node entities', $result);
  }

}
