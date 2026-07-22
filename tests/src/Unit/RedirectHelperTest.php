<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\drupal_helpers\Helpers\Redirect;
use Drupal\drupal_helpers\Report\Reporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Redirect helper service.
 */
#[CoversClass(Redirect::class)]
class RedirectHelperTest extends TestCase {

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
   * Tests creating a new redirect with the default language.
   */
  public function testCreateNew(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $storage = $this->mockStorage();
    $storage->method('loadByProperties')->willReturn([]);
    $storage->expects($this->once())->method('create')->with([
      'redirect_source' => ['path' => 'old-page'],
      'redirect_redirect' => ['uri' => 'internal:/new-page'],
      'status_code' => 301,
      'language' => 'und',
    ])->willReturn($entity);
    $entity->expects($this->once())->method('save');
    $this->messenger->expects($this->once())->method('addStatus');

    $result = $this->createRedirect()->create('old-page', '/new-page');

    $this->assertSame($entity, $result);
  }

  /**
   * Tests that creating an existing redirect short-circuits without saving.
   */
  public function testCreateSkipExisting(): void {
    $existing = $this->createMock(ContentEntityInterface::class);
    $storage = $this->mockStorage();
    $storage->method('loadByProperties')->willReturn([$existing]);
    $storage->expects($this->never())->method('create');
    $this->messenger->expects($this->once())->method('addStatus');

    $result = $this->createRedirect()->create('old-page', '/new-page');

    $this->assertSame($existing, $result);
  }

  /**
   * Tests that the language flows into the lookup and the created entity.
   */
  public function testCreateWithLanguage(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $storage = $this->mockStorage();
    $storage->expects($this->once())->method('loadByProperties')->with([
      'redirect_source__path' => 'old-page',
      'language' => 'fr',
    ])->willReturn([]);
    $storage->expects($this->once())->method('create')->with([
      'redirect_source' => ['path' => 'old-page'],
      'redirect_redirect' => ['uri' => 'internal:/nouvelle'],
      'status_code' => 301,
      'language' => 'fr',
    ])->willReturn($entity);
    $entity->expects($this->once())->method('save');

    $result = $this->createRedirect()->create('old-page', '/nouvelle', 301, TRUE, 'fr');

    $this->assertSame($entity, $result);
  }

  /**
   * Tests status code parsing for valid values.
   *
   * @dataProvider dataProviderParseStatusCode
   */
  #[DataProvider('dataProviderParseStatusCode')]
  public function testParseStatusCode(string $value, int $expected): void {
    $this->assertSame($expected, $this->invoke('parseStatusCode', $value));
  }

  /**
   * Tests status code parsing rejects non-3xx values.
   *
   * @dataProvider dataProviderParseStatusCodeInvalid
   */
  #[DataProvider('dataProviderParseStatusCodeInvalid')]
  public function testParseStatusCodeInvalid(string $value): void {
    $this->expectException(\RuntimeException::class);

    $this->invoke('parseStatusCode', $value);
  }

  /**
   * Tests language parsing defaults to language-neutral.
   */
  public function testParseLanguage(): void {
    $this->assertSame('und', $this->invoke('parseLanguage', ''));
    $this->assertSame('und', $this->invoke('parseLanguage', '   '));
    $this->assertSame('en', $this->invoke('parseLanguage', 'en'));
    $this->assertSame('fr', $this->invoke('parseLanguage', '  fr  '));
  }

  /**
   * Tests the URI-to-path inverse used by export.
   */
  public function testUriToPath(): void {
    $this->assertSame('/new-page', $this->invoke('uriToPath', 'internal:/new-page'));
    $this->assertSame('https://example.com', $this->invoke('uriToPath', 'https://example.com'));
    $this->assertSame('entity:node/1', $this->invoke('uriToPath', 'entity:node/1'));
    $this->assertSame('route:<front>', $this->invoke('uriToPath', 'route:<front>'));
  }

  /**
   * Tests validating and normalizing a well-formed import row.
   */
  public function testValidateImportRow(): void {
    $row = ['line' => 1, 'source' => ' old ', 'target' => ' /new ', 'status_code' => '302', 'language' => 'fr'];

    $this->assertSame(['old', '/new', 302, 'fr'], $this->invoke('validateImportRow', $row));
  }

  /**
   * Tests that an import row with no source is rejected.
   */
  public function testValidateImportRowMissingSource(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('missing source path');

    $this->invoke('validateImportRow', ['line' => 1, 'source' => '', 'target' => '/x', 'status_code' => '', 'language' => '']);
  }

  /**
   * Tests that an import row with no target is rejected.
   */
  public function testValidateImportRowMissingTarget(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('missing target path');

    $this->invoke('validateImportRow', ['line' => 1, 'source' => 'x', 'target' => '', 'status_code' => '', 'language' => '']);
  }

  /**
   * Tests validating and normalizing a well-formed delete row.
   */
  public function testValidateDeleteRow(): void {
    $row = ['line' => 1, 'source' => ' old ', 'target' => '', 'status_code' => '', 'language' => 'fr'];

    $this->assertSame(['old', 'fr'], $this->invoke('validateDeleteRow', $row));
  }

  /**
   * Tests that a delete row with no source is rejected.
   */
  public function testValidateDeleteRowMissingSource(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('missing source path');

    $this->invoke('validateDeleteRow', ['line' => 1, 'source' => '  ', 'target' => '', 'status_code' => '', 'language' => '']);
  }

  /**
   * Tests the CSV run summary is built from the accumulated tally.
   */
  public function testSummarizeCsvTally(): void {
    $redirect = $this->createRedirect();

    $this->invokeOn($redirect, 'bumpTally', Reporter::CREATED);
    $this->invokeOn($redirect, 'bumpTally', Reporter::CREATED);
    $this->invokeOn($redirect, 'bumpTally', Reporter::UPDATED);
    $this->invokeOn($redirect, 'bumpTally', Reporter::FAILED);

    $this->assertSame('Created 2, updated 1, failed 1.', $this->invokeOn($redirect, 'summarizeCsvTally'));
  }

  /**
   * Tests the CSV run summary reports no changes for an empty tally.
   */
  public function testSummarizeCsvTallyEmpty(): void {
    $this->assertSame('No changes.', $this->invoke('summarizeCsvTally'));
  }

  /**
   * Data provider for testParseStatusCode().
   *
   * @return array<int, array{string, int}>
   *   Raw value and expected parsed status code.
   */
  public static function dataProviderParseStatusCode(): array {
    return [
      ['', 301],
      ['301', 301],
      ['302', 302],
      ['308', 308],
      ['300', 300],
      ['399', 399],
      ['  302  ', 302],
    ];
  }

  /**
   * Data provider for testParseStatusCodeInvalid().
   *
   * @return array<int, array{string}>
   *   Raw values that are not valid 3xx status codes.
   */
  public static function dataProviderParseStatusCodeInvalid(): array {
    return [
      ['abc'],
      ['404'],
      ['200'],
      ['2xx'],
      ['-1'],
    ];
  }

  /**
   * Create a Redirect helper wired to the mocked collaborators.
   */
  protected function createRedirect(): Redirect {
    $redirect = new Redirect($this->entityTypeManager, $this->reporter);
    $redirect->setStringTranslation($this->translation);

    return $redirect;
  }

  /**
   * Wire the entity type manager to return a fresh redirect storage mock.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The storage mock to configure per test.
   */
  protected function mockStorage(): MockObject {
    $storage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->method('getStorage')->with('redirect')->willReturn($storage);

    return $storage;
  }

  /**
   * Invoke a protected method on a fresh Redirect helper.
   *
   * @param string $method
   *   The method name.
   * @param mixed ...$args
   *   Arguments to pass.
   *
   * @return mixed
   *   The method return value.
   */
  protected function invoke(string $method, mixed ...$args): mixed {
    return $this->invokeOn($this->createRedirect(), $method, ...$args);
  }

  /**
   * Invoke a protected method on a given Redirect helper.
   *
   * @param \Drupal\drupal_helpers\Helpers\Redirect $redirect
   *   The helper instance.
   * @param string $method
   *   The method name.
   * @param mixed ...$args
   *   Arguments to pass.
   *
   * @return mixed
   *   The method return value.
   */
  protected function invokeOn(Redirect $redirect, string $method, mixed ...$args): mixed {
    return (new \ReflectionMethod($redirect, $method))->invokeArgs($redirect, $args);
  }

}
