<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drupal_helpers\Report\Reporter;
use Drupal\drupal_helpers\Traits\BatchTrait;

/**
 * Base class for helper services.
 */
abstract class HelperBase {

  use BatchTrait;
  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Reporter $reporter,
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
  protected function getReporter(): Reporter {
    return $this->reporter;
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

  /**
   * Assert that an entity type is available.
   *
   * Lets a single method declare a dependency narrower than the whole service
   * (see requiredModules()): an entity type is defined only while its providing
   * module is installed.
   *
   * @param string $entity_type_id
   *   Entity type ID to require (e.g., 'block_content').
   *
   * @throws \RuntimeException
   *   If the entity type is not defined.
   */
  protected function requireEntityType(string $entity_type_id): void {
    if ($this->entityTypeManager->getDefinition($entity_type_id, FALSE) === NULL) {
      throw new \RuntimeException(sprintf('The "%s" entity type is unavailable; install its providing module.', $entity_type_id));
    }
  }

}
