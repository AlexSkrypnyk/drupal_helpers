<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drupal_helpers\Report\Reporter;

/**
 * Entity form and view display helpers for deploy hooks.
 */
class Display {

  use StringTranslationTrait;

  public function __construct(
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected Reporter $reporter,
  ) {}

  /**
   * Set a widget component on an entity form display.
   *
   * @code
   * Helper::display()->setFormComponent('node', 'article', 'default', 'field_subtitle', [
   *   'type' => 'string_textfield',
   *   'weight' => 5,
   * ]);
   * @endcode
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle machine name.
   * @param string $mode
   *   Form mode machine name (e.g., 'default').
   * @param string $field_name
   *   Machine name of the component (field) to configure.
   * @param array $options
   *   Widget options passed to the display: 'type', 'settings', 'weight',
   *   'region', 'third_party_settings'.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   The saved form display.
   */
  public function setFormComponent(string $entity_type, string $bundle, string $mode, string $field_name, array $options = []): EntityDisplayInterface {
    $display = $this->entityDisplayRepository->getFormDisplay($entity_type, $bundle, $mode);

    return $this->set($display, $field_name, $options, 'form');
  }

  /**
   * Set a formatter component on an entity view display.
   *
   * @code
   * Helper::display()->setViewComponent('node', 'article', 'teaser', 'field_subtitle', [
   *   'type' => 'string',
   *   'label' => 'hidden',
   *   'weight' => 5,
   * ]);
   * @endcode
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle machine name.
   * @param string $mode
   *   View mode machine name (e.g., 'default', 'teaser').
   * @param string $field_name
   *   Machine name of the component (field) to configure.
   * @param array $options
   *   Formatter options passed to the display: 'type', 'label', 'settings',
   *   'weight', 'region', 'third_party_settings'.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   The saved view display.
   */
  public function setViewComponent(string $entity_type, string $bundle, string $mode, string $field_name, array $options = []): EntityDisplayInterface {
    $display = $this->entityDisplayRepository->getViewDisplay($entity_type, $bundle, $mode);

    return $this->set($display, $field_name, $options, 'view');
  }

  /**
   * Hide a component on an entity form display.
   *
   * @code
   * Helper::display()->hideFormComponent('node', 'article', 'default', 'field_subtitle');
   * @endcode
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle machine name.
   * @param string $mode
   *   Form mode machine name (e.g., 'default').
   * @param string $field_name
   *   Machine name of the component (field) to hide.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   The saved form display.
   */
  public function hideFormComponent(string $entity_type, string $bundle, string $mode, string $field_name): EntityDisplayInterface {
    $display = $this->entityDisplayRepository->getFormDisplay($entity_type, $bundle, $mode);

    return $this->hide($display, $field_name, 'form');
  }

  /**
   * Hide a component on an entity view display.
   *
   * @code
   * Helper::display()->hideViewComponent('node', 'article', 'teaser', 'field_subtitle');
   * @endcode
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle machine name.
   * @param string $mode
   *   View mode machine name (e.g., 'default', 'teaser').
   * @param string $field_name
   *   Machine name of the component (field) to hide.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   The saved view display.
   */
  public function hideViewComponent(string $entity_type, string $bundle, string $mode, string $field_name): EntityDisplayInterface {
    $display = $this->entityDisplayRepository->getViewDisplay($entity_type, $bundle, $mode);

    return $this->hide($display, $field_name, 'view');
  }

  /**
   * Set a component on a display and save it.
   *
   * @param \Drupal\Core\Entity\Display\EntityDisplayInterface $display
   *   The form or view display to modify.
   * @param string $field_name
   *   Machine name of the component to configure.
   * @param array $options
   *   Component options passed to EntityDisplayInterface::setComponent().
   * @param string $type
   *   Display type label ('form' or 'view') for the status message.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   The saved display.
   */
  protected function set(EntityDisplayInterface $display, string $field_name, array $options, string $type): EntityDisplayInterface {
    $display->setComponent($field_name, $options)->save();

    $this->reporter->updated($this->t('Set component "@field" on the @type display "@display".', [
      '@field' => $field_name,
      '@type' => $type,
      '@display' => $display->id(),
    ]));

    return $display;
  }

  /**
   * Hide a component on a display and save it.
   *
   * @param \Drupal\Core\Entity\Display\EntityDisplayInterface $display
   *   The form or view display to modify.
   * @param string $field_name
   *   Machine name of the component to hide.
   * @param string $type
   *   Display type label ('form' or 'view') for the status message.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   The saved display.
   */
  protected function hide(EntityDisplayInterface $display, string $field_name, string $type): EntityDisplayInterface {
    $display->removeComponent($field_name)->save();

    $this->reporter->updated($this->t('Hid component "@field" on the @type display "@display".', [
      '@field' => $field_name,
      '@type' => $type,
      '@display' => $display->id(),
    ]));

    return $display;
  }

}
