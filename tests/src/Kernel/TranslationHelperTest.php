<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use Drupal\drupal_helpers\Helper;
use Drupal\drupal_helpers\Helpers\Translation;
use Drupal\drupal_helpers\Report\Reporter;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\locale\SourceString;
use Drupal\locale\StringStorageInterface;
use Drupal\locale\TranslationString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the Translation helper service.
 */
#[CoversClass(Translation::class)]
#[RunTestsInSeparateProcesses]
class TranslationHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system', 'language', 'locale'];

  /**
   * The translation helper service.
   */
  protected Translation $translationHelper;

  /**
   * The locale string storage.
   */
  protected StringStorageInterface $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    foreach (['fr', 'de'] as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }

    $this->installSchema('locale', ['locales_location', 'locales_source', 'locales_target']);

    $this->translationHelper = $this->container->get('drupal_helpers.translation');
    $this->storage = $this->container->get('locale.storage');
  }

  /**
   * Tests that requiredModules returns the locale module.
   */
  public function testRequiredModules(): void {
    $this->assertEquals(['locale'], $this->translationHelper->requiredModules());
  }

  /**
   * Tests that set() creates the source string and a customized translation.
   */
  public function testSetCreatesTranslation(): void {
    $this->translationHelper->set('fr', 'Submit', 'Envoyer');

    $source = $this->storage->findString(['source' => 'Submit', 'context' => '']);
    $this->assertInstanceOf(SourceString::class, $source);

    $translation = $this->storage->findTranslation(['language' => 'fr', 'lid' => $source->getId()]);
    $this->assertInstanceOf(TranslationString::class, $translation);
    $this->assertSame('Envoyer', $translation->translation);
    $this->assertEquals(LOCALE_CUSTOMIZED, $translation->customized);
  }

  /**
   * Tests that set() creates the source string only when it is missing.
   */
  public function testSetCreatesSourceWhenMissing(): void {
    $this->assertCount(0, $this->storage->getStrings(['source' => 'Save']));

    $this->translationHelper->set('fr', 'Save', 'Enregistrer');

    $source = $this->storage->findString(['source' => 'Save', 'context' => '']);
    $this->assertInstanceOf(SourceString::class, $source);
  }

  /**
   * Tests that set() updates an existing translation in place.
   */
  public function testSetUpdatesExistingTranslation(): void {
    $this->translationHelper->set('fr', 'Submit', 'Envoyer');
    $this->translationHelper->set('fr', 'Submit', 'Soumettre');

    $translation = $this->storage->findTranslation(['language' => 'fr', 'source' => 'Submit', 'context' => '']);
    $this->assertInstanceOf(TranslationString::class, $translation);
    $this->assertSame('Soumettre', $translation->translation);

    // The source string is reused, not duplicated.
    $this->assertCount(1, $this->storage->getStrings(['source' => 'Submit']));

    // The translation row is updated in place, not duplicated.
    $count = $this->container->get('database')->select('locales_target', 't')->condition('t.language', 'fr')->countQuery()->execute()->fetchField();
    $this->assertEquals(1, $count);
  }

  /**
   * Tests that set() separates translations of one source by context.
   */
  public function testSetKeepsContextsSeparate(): void {
    $this->translationHelper->set('fr', 'May', 'Mai', 'Long month name');
    $this->translationHelper->set('fr', 'May', 'Peut', 'Uncertainty');

    $month = $this->storage->findTranslation(['language' => 'fr', 'source' => 'May', 'context' => 'Long month name']);
    $uncertainty = $this->storage->findTranslation(['language' => 'fr', 'source' => 'May', 'context' => 'Uncertainty']);

    $this->assertInstanceOf(TranslationString::class, $month);
    $this->assertInstanceOf(TranslationString::class, $uncertainty);
    $this->assertSame('Mai', $month->translation);
    $this->assertSame('Peut', $uncertainty->translation);

    // Two distinct source strings exist for the same source text.
    $this->assertCount(2, $this->storage->getStrings(['source' => 'May']));
  }

  /**
   * Tests that set() reports a created then an updated operation.
   */
  public function testSetReports(): void {
    $reporter = $this->container->get('drupal_helpers.reporter');

    $this->translationHelper->set('fr', 'Submit', 'Envoyer');
    $this->assertEquals(1, $reporter->count(Reporter::CREATED));

    $this->translationHelper->set('fr', 'Submit', 'Soumettre');
    $this->assertEquals(1, $reporter->count(Reporter::UPDATED));
    $this->assertEquals(1, $reporter->count(Reporter::CREATED));
  }

  /**
   * Tests that the facade returns the helper when locale is installed.
   */
  public function testFacadeReturnsTranslation(): void {
    /** @phpstan-ignore method.alreadyNarrowedType */
    $this->assertInstanceOf(Translation::class, Helper::translation());
  }

}
