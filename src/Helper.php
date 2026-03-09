<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers;

use Drupal\drupal_helpers\Helpers\Config;
use Drupal\drupal_helpers\Helpers\Entity;
use Drupal\drupal_helpers\Helpers\Field;
use Drupal\drupal_helpers\Helpers\HelperBase;
use Drupal\drupal_helpers\Helpers\Menu;
use Drupal\drupal_helpers\Helpers\Redirect;
use Drupal\drupal_helpers\Helpers\Term;
use Drupal\drupal_helpers\Helpers\User;

/**
 * Static facade for drupal_helpers services.
 *
 * Provides convenient access to helper services from deploy hooks without
 * needing to know service names.
 *
 * @code
 * // Simple — no sandbox:
 * Helper::term()->createTree('topics', ['Term 1', 'Term 2']);
 *
 * // Batched — with sandbox:
 * function my_module_deploy_001(array &$sandbox): ?string {
 *   return Helper::entity($sandbox)->batch('node', 'article', function ($node) {
 *     $node->set('field_migrated', TRUE);
 *     $node->save();
 *   });
 * }
 * @endcode
 */
class Helper {

  /**
   * Cached cloned service instances keyed by sandbox ID and service name.
   *
   * @var array
   */
  protected static array $instances = [];

  /**
   * Counter for generating unique sandbox IDs.
   *
   * @var int
   */
  protected static int $instanceId = 0;

  /**
   * Get the term helper service.
   */
  public static function term(?array &$sandbox = NULL, int $batch_size = 50): Term {
    /** @var \Drupal\drupal_helpers\Helpers\Term */
    return static::service('term', $sandbox, $batch_size);
  }

  /**
   * Get the menu helper service.
   */
  public static function menu(?array &$sandbox = NULL, int $batch_size = 50): Menu {
    /** @var \Drupal\drupal_helpers\Helpers\Menu */
    return static::service('menu', $sandbox, $batch_size);
  }

  /**
   * Get the field helper service.
   */
  public static function field(?array &$sandbox = NULL, int $batch_size = 50): Field {
    /** @var \Drupal\drupal_helpers\Helpers\Field */
    return static::service('field', $sandbox, $batch_size);
  }

  /**
   * Get the entity helper service.
   */
  public static function entity(?array &$sandbox = NULL, int $batch_size = 50): Entity {
    /** @var \Drupal\drupal_helpers\Helpers\Entity */
    return static::service('entity', $sandbox, $batch_size);
  }

  /**
   * Get the config helper service.
   */
  public static function config(): Config {
    /** @var \Drupal\drupal_helpers\Helpers\Config */
    return \Drupal::service('drupal_helpers.config');
  }

  /**
   * Get the user helper service.
   */
  public static function user(?array &$sandbox = NULL, int $batch_size = 50): User {
    /** @var \Drupal\drupal_helpers\Helpers\User */
    return static::service('user', $sandbox, $batch_size);
  }

  /**
   * Get the redirect helper service.
   */
  public static function redirect(?array &$sandbox = NULL, int $batch_size = 50): Redirect {
    /** @var \Drupal\drupal_helpers\Helpers\Redirect */
    return static::service('redirect', $sandbox, $batch_size);
  }

  /**
   * Get or create a sandbox-aware service instance.
   *
   * @param string $name
   *   Service name suffix (e.g., 'term', 'entity').
   * @param array|null $sandbox
   *   The deploy hook sandbox array, or NULL for non-batched operations.
   * @param int $batch_size
   *   Number of items to process per batch.
   *
   * @return \Drupal\drupal_helpers\Helpers\HelperBase
   *   The service instance.
   */
  protected static function service(string $name, ?array &$sandbox = NULL, int $batch_size = 50): HelperBase {
    /** @var \Drupal\drupal_helpers\Helpers\HelperBase $instance */
    $instance = \Drupal::service('drupal_helpers.' . $name);

    static::checkRequirements($instance);

    if ($sandbox === NULL) {
      return $instance;
    }

    if (!isset($sandbox['_dh_id'])) {
      $sandbox['_dh_id'] = ++static::$instanceId;
    }

    $key = $sandbox['_dh_id'] . ':' . $name;

    if (!isset(static::$instances[$key])) {
      $clone = clone $instance;
      $clone->setSandbox($sandbox);
      $clone->setBatchSize($batch_size);
      static::$instances[$key] = $clone;
    }

    return static::$instances[$key];
  }

  /**
   * Check that a helper's required modules are installed.
   *
   * @param \Drupal\drupal_helpers\Helpers\HelperBase $helper
   *   The helper instance to check.
   *
   * @throws \RuntimeException
   *   If any required modules are missing.
   */
  protected static function checkRequirements(HelperBase $helper): void {
    $required = $helper->requiredModules();

    if (empty($required)) {
      return;
    }

    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $module_handler */
    $module_handler = \Drupal::service('module_handler');
    $missing = [];

    foreach ($required as $module) {
      if (!$module_handler->moduleExists($module)) {
        $missing[] = $module;
      }
    }

    if ($missing) {
      throw new \RuntimeException(sprintf('Required modules not installed: %s.', implode(', ', $missing)));
    }
  }

}
