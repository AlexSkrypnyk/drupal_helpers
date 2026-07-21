<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Unit;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\drupal_helpers\Helpers\Display;
use Drupal\drupal_helpers\Report\Reporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Display helper service.
 */
#[CoversClass(Display::class)]
class DisplayHelperTest extends TestCase {

  /**
   * The entity display repository mock.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $entityDisplayRepository;

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
   * The Display helper under test.
   */
  protected Display $display;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityDisplayRepository = $this->createMock(EntityDisplayRepositoryInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->reporter = new Reporter($this->createMock(LoggerChannelInterface::class), $this->messenger);

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(fn($input): string => (string) $input->getUntranslatedString());

    $this->display = new Display($this->entityDisplayRepository, $this->reporter);
    $this->display->setStringTranslation($translation);
  }

  /**
   * Tests that setFormComponent configures and saves the form display.
   */
  public function testSetFormComponent(): void {
    $options = ['type' => 'string_textfield', 'weight' => 5];

    $form_display = $this->createMock(EntityFormDisplayInterface::class);
    $form_display->expects($this->once())->method('setComponent')->with('field_subtitle', $options)->willReturnSelf();
    $form_display->expects($this->once())->method('save');
    $form_display->method('id')->willReturn('node.article.default');

    $this->entityDisplayRepository->expects($this->once())->method('getFormDisplay')
      ->with('node', 'article', 'default')
      ->willReturn($form_display);
    $this->messenger->expects($this->once())->method('addStatus');

    $result = $this->display->setFormComponent('node', 'article', 'default', 'field_subtitle', $options);

    $this->assertSame($form_display, $result);
  }

  /**
   * Tests that setViewComponent configures and saves the view display.
   */
  public function testSetViewComponent(): void {
    $options = ['type' => 'string', 'weight' => 3];

    $view_display = $this->createMock(EntityViewDisplayInterface::class);
    $view_display->expects($this->once())->method('setComponent')->with('field_subtitle', $options)->willReturnSelf();
    $view_display->expects($this->once())->method('save');
    $view_display->method('id')->willReturn('node.article.teaser');

    $this->entityDisplayRepository->expects($this->once())->method('getViewDisplay')
      ->with('node', 'article', 'teaser')
      ->willReturn($view_display);
    $this->messenger->expects($this->once())->method('addStatus');

    $result = $this->display->setViewComponent('node', 'article', 'teaser', 'field_subtitle', $options);

    $this->assertSame($view_display, $result);
  }

  /**
   * Tests that hideFormComponent removes the component and saves.
   */
  public function testHideFormComponent(): void {
    $form_display = $this->createMock(EntityFormDisplayInterface::class);
    $form_display->expects($this->once())->method('removeComponent')->with('field_subtitle')->willReturnSelf();
    $form_display->expects($this->once())->method('save');
    $form_display->method('id')->willReturn('node.article.default');

    $this->entityDisplayRepository->expects($this->once())->method('getFormDisplay')
      ->with('node', 'article', 'default')
      ->willReturn($form_display);
    $this->messenger->expects($this->once())->method('addStatus');

    $result = $this->display->hideFormComponent('node', 'article', 'default', 'field_subtitle');

    $this->assertSame($form_display, $result);
  }

  /**
   * Tests that hideViewComponent removes the component and saves.
   */
  public function testHideViewComponent(): void {
    $view_display = $this->createMock(EntityViewDisplayInterface::class);
    $view_display->expects($this->once())->method('removeComponent')->with('field_subtitle')->willReturnSelf();
    $view_display->expects($this->once())->method('save');
    $view_display->method('id')->willReturn('node.article.teaser');

    $this->entityDisplayRepository->expects($this->once())->method('getViewDisplay')
      ->with('node', 'article', 'teaser')
      ->willReturn($view_display);
    $this->messenger->expects($this->once())->method('addStatus');

    $result = $this->display->hideViewComponent('node', 'article', 'teaser', 'field_subtitle');

    $this->assertSame($view_display, $result);
  }

}
