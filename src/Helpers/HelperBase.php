<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drupal_helpers\Traits\BatchTrait;

/**
 * Base class for helper services.
 */
abstract class HelperBase {

  use BatchTrait;
  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getMessenger(): MessengerInterface {
    return $this->messenger;
  }

  /**
   * Get the list of required modules for this helper.
   *
   * Override in subclasses to declare module dependencies.
   *
   * @return string[]
   *   Array of module machine names.
   */
  public function requiredModules(): array {
    return [];
  }

}
