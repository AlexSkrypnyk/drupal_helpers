<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\drupal_helpers\Helpers\Translation;
use Drupal\drupal_helpers\Report\Reporter;
use Drupal\locale\SourceString;
use Drupal\locale\StringStorageInterface;
use Drupal\locale\TranslationString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Translation helper service.
 */
#[CoversClass(Translation::class)]
class TranslationHelperTest extends TestCase {

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
   * The translation stub.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $translation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->reporter = new Reporter($this->createMock(LoggerChannelInterface::class), $this->messenger);

    $this->translation = $this->createMock(TranslationInterface::class);
    $this->translation->method('translateString')->willReturnCallback(fn($input): string => (string) $input->getUntranslatedString());
  }

  /**
   * Tests that requiredModules returns the locale module.
   */
  public function testRequiredModules(): void {
    $this->assertEquals(['locale'], $this->buildTranslation($this->createMock(StringStorageInterface::class))->requiredModules());
  }

  /**
   * Tests that set() creates the source string and its translation.
   */
  public function testSetCreatesSourceAndTranslation(): void {
    $storage = $this->createMock(StringStorageInterface::class);
    $storage->method('findString')->with(['source' => 'Submit', 'context' => ''])->willReturn(NULL);

    $source = $this->createMock(SourceString::class);
    $source->method('getId')->willReturn(42);
    $source->expects($this->once())->method('save');
    $storage->expects($this->once())->method('createString')->with(['source' => 'Submit', 'context' => ''])->willReturn($source);

    $absent = $this->createMock(TranslationString::class);
    $absent->method('isNew')->willReturn(TRUE);
    $storage->method('findTranslation')->with(['language' => 'fr', 'lid' => 42])->willReturn($absent);

    $created = $this->createMock(TranslationString::class);
    $created->expects($this->once())->method('setCustomized')->with(TRUE);
    $created->expects($this->once())->method('save');
    $storage->expects($this->once())->method('createTranslation')->with(['lid' => 42, 'language' => 'fr', 'translation' => 'Envoyer'])->willReturn($created);

    $storage->expects($this->never())->method('save');
    $this->messenger->expects($this->once())->method('addStatus');

    $this->buildTranslation($storage)->set('fr', 'Submit', 'Envoyer');

    $this->assertEquals(1, $this->reporter->count(Reporter::CREATED));
  }

  /**
   * Tests that set() reuses an existing source and creates the translation.
   */
  public function testSetReusesExistingSource(): void {
    $storage = $this->createMock(StringStorageInterface::class);

    $source = $this->createMock(SourceString::class);
    $source->method('getId')->willReturn(9);
    $storage->method('findString')->willReturn($source);
    $storage->expects($this->never())->method('createString');

    // No translation yet: findTranslation returns NULL.
    $storage->method('findTranslation')->willReturn(NULL);

    $created = $this->createMock(TranslationString::class);
    $created->expects($this->once())->method('setCustomized')->with(TRUE);
    $created->expects($this->once())->method('save');
    $storage->expects($this->once())->method('createTranslation')->with(['lid' => 9, 'language' => 'fr', 'translation' => 'Envoyer'])->willReturn($created);

    $this->buildTranslation($storage)->set('fr', 'Submit', 'Envoyer');

    $this->assertEquals(1, $this->reporter->count(Reporter::CREATED));
  }

  /**
   * Tests that set() updates an existing translation without recreating it.
   */
  public function testSetUpdatesExistingTranslation(): void {
    $storage = $this->createMock(StringStorageInterface::class);

    $source = $this->createMock(SourceString::class);
    $source->method('getId')->willReturn(7);
    $storage->method('findString')->willReturn($source);
    $storage->expects($this->never())->method('createString');

    $existing = $this->createMock(TranslationString::class);
    $existing->method('isNew')->willReturn(FALSE);
    $existing->expects($this->once())->method('setString')->with('Soumettre');
    $existing->expects($this->once())->method('setCustomized')->with(TRUE);
    $existing->expects($this->once())->method('save');
    $storage->method('findTranslation')->willReturn($existing);

    $storage->expects($this->never())->method('createTranslation');
    $this->messenger->expects($this->once())->method('addStatus');

    $this->buildTranslation($storage)->set('fr', 'Submit', 'Soumettre');

    $this->assertEquals(1, $this->reporter->count(Reporter::UPDATED));
  }

  /**
   * Tests that set() passes the context through to the storage lookups.
   */
  public function testSetPassesContext(): void {
    $storage = $this->createMock(StringStorageInterface::class);
    $storage->expects($this->once())->method('findString')->with(['source' => 'May', 'context' => 'Long month name'])->willReturn(NULL);

    $source = $this->createMock(SourceString::class);
    $source->method('getId')->willReturn(3);
    $storage->expects($this->once())->method('createString')->with(['source' => 'May', 'context' => 'Long month name'])->willReturn($source);

    $storage->method('findTranslation')->willReturn(NULL);
    $storage->method('createTranslation')->willReturn($this->createMock(TranslationString::class));

    $this->buildTranslation($storage)->set('fr', 'May', 'Mai', 'Long month name');
  }

  /**
   * Tests that set() fails clearly when the locale storage is unavailable.
   */
  public function testSetWithoutStorageThrows(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The "locale.storage" service is required; enable the locale module.');

    $this->buildTranslation(NULL)->set('fr', 'Submit', 'Envoyer');
  }

  /**
   * Create a Translation helper with a given string storage.
   *
   * @param \Drupal\locale\StringStorageInterface|null $string_storage
   *   The locale string storage, or NULL to simulate the missing service.
   */
  protected function buildTranslation(?StringStorageInterface $string_storage): Translation {
    $helper = new Translation($this->entityTypeManager, $this->reporter, $string_storage);
    $helper->setStringTranslation($this->translation);

    return $helper;
  }

}
