<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Unit;

use Drupal\drupal_helpers\Traits\TreeSyncTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TreeSyncTrait.
 */
#[CoversClass(TreeSyncTrait::class)]
class TreeSyncTraitTest extends TestCase {

  /**
   * The test helper instance using TreeSyncTrait.
   */
  protected TreeSyncTraitTestHelper $helper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->helper = new TreeSyncTraitTestHelper();
  }

  /**
   * Tests that supported modes pass validation.
   *
   * @dataProvider dataProviderAssertModeValid
   */
  #[DataProvider('dataProviderAssertModeValid')]
  public function testAssertModeValid(string $mode): void {
    $this->helper->doAssertMode($mode);

    // Reaching this point means no exception was thrown.
    $this->addToAssertionCount(1);
  }

  /**
   * Data provider for testAssertModeValid.
   */
  public static function dataProviderAssertModeValid(): array {
    return [
      'safe' => [TreeSyncTraitTestHelper::MODE_SAFE],
      'update' => [TreeSyncTraitTestHelper::MODE_UPDATE],
      'sync' => [TreeSyncTraitTestHelper::MODE_SYNC],
    ];
  }

  /**
   * Tests that an unsupported mode throws.
   *
   * @dataProvider dataProviderAssertModeInvalid
   */
  #[DataProvider('dataProviderAssertModeInvalid')]
  public function testAssertModeInvalid(string $mode): void {
    $this->expectException(\InvalidArgumentException::class);

    $this->helper->doAssertMode($mode);
  }

  /**
   * Data provider for testAssertModeInvalid.
   */
  public static function dataProviderAssertModeInvalid(): array {
    return [
      'unknown' => ['bogus'],
      'empty' => [''],
      'wrong case' => ['SAFE'],
    ];
  }

}

/**
 * Test helper class that uses TreeSyncTrait.
 */
class TreeSyncTraitTestHelper {

  use TreeSyncTrait;

  /**
   * Exposes assertMode() for testing.
   *
   * @param string $mode
   *   Reconciliation mode to validate.
   */
  public function doAssertMode(string $mode): void {
    $this->assertMode($mode);
  }

}
