<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\drupal_helpers\Helpers\Display;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the Display helper service.
 */
#[CoversClass(Display::class)]
#[RunTestsInSeparateProcesses]
class DisplayHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system', 'user', 'field', 'entity_test'];

  /**
   * The display helper service.
   */
  protected Display $displayHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');

    FieldStorageConfig::create([
      'field_name' => 'field_subtitle',
      'entity_type' => 'entity_test',
      'type' => 'string',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_subtitle',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'Subtitle',
    ])->save();

    $this->displayHelper = $this->container->get('drupal_helpers.display');
  }

  /**
   * Tests setting a widget component on a form display.
   */
  public function testSetFormComponent(): void {
    $display = $this->displayHelper->setFormComponent('entity_test', 'entity_test', 'default', 'field_subtitle', [
      'type' => 'string_textfield',
      'weight' => 5,
    ]);

    $this->assertInstanceOf(EntityFormDisplayInterface::class, $display);

    $component = $display->getComponent('field_subtitle');
    $this->assertIsArray($component);
    $this->assertEquals('string_textfield', $component['type']);
    $this->assertEquals(5, $component['weight']);

    // The change persists to a freshly loaded display.
    $reloaded = $this->reloadFormDisplay();
    $this->assertNotNull($reloaded->getComponent('field_subtitle'));
    $this->assertEquals('string_textfield', $reloaded->getComponent('field_subtitle')['type']);
  }

  /**
   * Tests setting a formatter component on a view display.
   */
  public function testSetViewComponent(): void {
    $display = $this->displayHelper->setViewComponent('entity_test', 'entity_test', 'default', 'field_subtitle', [
      'type' => 'string',
      'label' => 'hidden',
      'weight' => 3,
    ]);

    $this->assertInstanceOf(EntityViewDisplayInterface::class, $display);

    $component = $display->getComponent('field_subtitle');
    $this->assertIsArray($component);
    $this->assertEquals('string', $component['type']);
    $this->assertEquals('hidden', $component['label']);
    $this->assertEquals(3, $component['weight']);

    $reloaded = $this->reloadViewDisplay();
    $this->assertNotNull($reloaded->getComponent('field_subtitle'));
    $this->assertEquals('string', $reloaded->getComponent('field_subtitle')['type']);
  }

  /**
   * Tests hiding a component on a form display.
   */
  public function testHideFormComponent(): void {
    $this->displayHelper->setFormComponent('entity_test', 'entity_test', 'default', 'field_subtitle', [
      'type' => 'string_textfield',
    ]);

    $display = $this->displayHelper->hideFormComponent('entity_test', 'entity_test', 'default', 'field_subtitle');

    $this->assertInstanceOf(EntityFormDisplayInterface::class, $display);
    $this->assertNull($display->getComponent('field_subtitle'));

    $this->assertNull($this->reloadFormDisplay()->getComponent('field_subtitle'));
  }

  /**
   * Tests hiding a component on a view display.
   */
  public function testHideViewComponent(): void {
    $this->displayHelper->setViewComponent('entity_test', 'entity_test', 'default', 'field_subtitle', [
      'type' => 'string',
    ]);

    $display = $this->displayHelper->hideViewComponent('entity_test', 'entity_test', 'default', 'field_subtitle');

    $this->assertInstanceOf(EntityViewDisplayInterface::class, $display);
    $this->assertNull($display->getComponent('field_subtitle'));

    $this->assertNull($this->reloadViewDisplay()->getComponent('field_subtitle'));
  }

  /**
   * Tests that a status message is recorded naming the affected component.
   */
  public function testStatusMessage(): void {
    $this->displayHelper->setFormComponent('entity_test', 'entity_test', 'default', 'field_subtitle', [
      'type' => 'string_textfield',
    ]);

    $messages = $this->container->get('messenger')->messagesByType('status');
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('field_subtitle', (string) reset($messages));
  }

  /**
   * Reloads the entity_test default form display from storage.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   A freshly loaded form display.
   */
  protected function reloadFormDisplay(): EntityDisplayInterface {
    return $this->container->get('entity_display.repository')->getFormDisplay('entity_test', 'entity_test', 'default');
  }

  /**
   * Reloads the entity_test default view display from storage.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   A freshly loaded view display.
   */
  protected function reloadViewDisplay(): EntityDisplayInterface {
    return $this->container->get('entity_display.repository')->getViewDisplay('entity_test', 'entity_test', 'default');
  }

}
