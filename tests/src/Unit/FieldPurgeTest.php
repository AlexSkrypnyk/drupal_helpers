<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\DeletedFieldsRepositoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\drupal_helpers\Helpers\Field;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Field helper purge guard.
 */
#[CoversClass(Field::class)]
class FieldPurgeTest extends TestCase {

  /**
   * Tests that purging without the FieldPurger service throws.
   *
   * On Drupal 11.4.0 and later the procedural fallback is gone, so a missing
   * FieldPurger service must fail loudly instead of silently leaving data.
   */
  public function testPurgeViaServiceRequiresPurger(): void {
    $field = new Field(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(MessengerInterface::class),
      $this->createMock(DeletedFieldsRepositoryInterface::class),
    );

    $method = new \ReflectionMethod($field, 'purgeViaService');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('FieldPurger');

    $method->invoke($field, 50);
  }

}
