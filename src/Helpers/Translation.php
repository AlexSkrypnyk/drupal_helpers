<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\drupal_helpers\Report\Reporter;
use Drupal\locale\SourceString;
use Drupal\locale\StringStorageInterface;
use Drupal\locale\TranslationString;

/**
 * Interface translation helpers for deploy hooks.
 *
 * Requires the core 'locale' module.
 */
class Translation extends HelperBase {

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Reporter $reporter,
    protected ?StringStorageInterface $stringStorage = NULL,
  ) {
    parent::__construct($entity_type_manager, $reporter);
  }

  /**
   * {@inheritdoc}
   */
  public function requiredModules(): array {
    return ['locale'];
  }

  /**
   * Add or update the translation of a source string for a language.
   *
   * Creates the source string when it does not exist yet, then creates or
   * updates its translation for the given language. Translations are marked
   * as customized so a later translation import does not overwrite them.
   *
   * @code
   * Helper::translation()->set('fr', 'Submit', 'Envoyer');
   * // Disambiguate a source string that carries a context:
   * Helper::translation()->set('fr', 'May', 'Mai', 'Long month name');
   * @endcode
   *
   * @param string $langcode
   *   The target language code (e.g., 'fr').
   * @param string $source
   *   The untranslated source string, exactly as passed to t().
   * @param string $translation
   *   The translated string.
   * @param string $context
   *   (optional) The string context, matching the 'context' option of t().
   *   Defaults to an empty string.
   */
  public function set(string $langcode, string $source, string $translation, string $context = ''): void {
    $storage = $this->stringStorage();

    $string = $storage->findString(['source' => $source, 'context' => $context]);

    if (!$string instanceof SourceString) {
      $string = $storage->createString(['source' => $source, 'context' => $context]);
      $string->save();
    }

    $existing = $storage->findTranslation(['language' => $langcode, 'lid' => $string->getId()]);

    if ($existing instanceof TranslationString && !$existing->isNew()) {
      $existing->setString($translation);
      $existing->setCustomized(TRUE);
      $existing->save();

      $this->reporter->updated($this->t('Updated "@langcode" translation of "@source".', [
        '@langcode' => $langcode,
        '@source' => $source,
      ]));

      return;
    }

    $new = $storage->createTranslation([
      'lid' => $string->getId(),
      'language' => $langcode,
      'translation' => $translation,
    ]);
    $new->setCustomized(TRUE);
    $new->save();

    $this->reporter->created($this->t('Created "@langcode" translation of "@source".', [
      '@langcode' => $langcode,
      '@source' => $source,
    ]));
  }

  /**
   * Return the locale string storage, or fail clearly when it is unavailable.
   *
   * @return \Drupal\locale\StringStorageInterface
   *   The locale string storage.
   *
   * @throws \RuntimeException
   *   When the 'locale.storage' service is not available.
   */
  protected function stringStorage(): StringStorageInterface {
    if (!$this->stringStorage instanceof StringStorageInterface) {
      throw new \RuntimeException('The "locale.storage" service is required; enable the locale module.');
    }

    return $this->stringStorage;
  }

}
