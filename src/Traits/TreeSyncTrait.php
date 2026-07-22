<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Traits;

/**
 * Reconciliation modes shared by the nested tree builders.
 */
trait TreeSyncTrait {

  /**
   * Create missing items only, leaving existing items unchanged (default).
   */
  public const MODE_SAFE = 'safe';

  /**
   * Create missing items and re-apply properties to existing items.
   */
  public const MODE_UPDATE = 'update';

  /**
   * Update existing items and delete items absent from the supplied tree.
   */
  public const MODE_SYNC = 'sync';

  /**
   * Assert that a reconciliation mode is supported.
   *
   * @param string $mode
   *   One of self::MODE_SAFE, self::MODE_UPDATE or self::MODE_SYNC.
   *
   * @throws \InvalidArgumentException
   *   When the mode is not one of the supported reconciliation modes.
   */
  protected function assertMode(string $mode): void {
    $modes = [self::MODE_SAFE, self::MODE_UPDATE, self::MODE_SYNC];

    if (!in_array($mode, $modes, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Unsupported tree sync mode "%s"; expected one of "%s".', $mode, implode('", "', $modes)));
    }
  }

}
