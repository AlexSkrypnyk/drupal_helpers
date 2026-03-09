<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use Drupal\drupal_helpers\Helpers\HelperBase;
use Drupal\drupal_helpers\Helper;
use Drupal\drupal_helpers\Helpers\Config;
use Drupal\drupal_helpers\Helpers\Entity;
use Drupal\drupal_helpers\Helpers\Field;
use Drupal\drupal_helpers\Helpers\Menu;
use Drupal\drupal_helpers\Helpers\Redirect;
use Drupal\drupal_helpers\Helpers\Term;
use Drupal\drupal_helpers\Helpers\User;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Kernel tests for the Helper facade.
 */
#[CoversClass(Helper::class)]
class HelperFacadeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system', 'taxonomy', 'menu_link_content', 'link', 'node', 'user', 'field', 'text', 'redirect', 'path_alias'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('redirect');
    $this->installConfig(['system']);
  }

  /**
   * Tests that each facade method returns the correct service type.
   *
   * @dataProvider dataProviderServiceResolution
   */
  #[DataProvider('dataProviderServiceResolution')]
  public function testServiceResolution(string $method, string $expected_class): void {
    $instance = Helper::$method();

    /** @phpstan-ignore argument.type */
    $this->assertInstanceOf($expected_class, $instance);
  }

  /**
   * Data provider for testServiceResolution().
   */
  public static function dataProviderServiceResolution(): array {
    return [
      'term' => ['term', Term::class],
      'menu' => ['menu', Menu::class],
      'field' => ['field', Field::class],
      'entity' => ['entity', Entity::class],
      'config' => ['config', Config::class],
      'user' => ['user', User::class],
      'redirect' => ['redirect', Redirect::class],
    ];
  }

  /**
   * Tests that the same sandbox returns the same cloned instance.
   */
  public function testSandboxCloning(): void {
    $sandbox_a = [];
    $instance_a1 = Helper::entity($sandbox_a);
    $instance_a2 = Helper::entity($sandbox_a);

    $this->assertSame($instance_a1, $instance_a2);

    $sandbox_b = [];
    $instance_b = Helper::entity($sandbox_b);

    $this->assertNotSame($instance_a1, $instance_b);
  }

  /**
   * Tests that passing a sandbox sets the _dh_id key.
   */
  public function testSandboxSetsId(): void {
    $sandbox = [];

    Helper::entity($sandbox);

    $this->assertArrayHasKey('_dh_id', $sandbox);
    $this->assertIsInt($sandbox['_dh_id']);
  }

  /**
   * Tests that calling without sandbox returns the service directly.
   */
  public function testWithoutSandbox(): void {
    $instance_a = Helper::entity();
    $instance_b = Helper::entity();

    // Without sandbox, the container returns the same service instance.
    $this->assertSame($instance_a, $instance_b);
  }

  /**
   * Tests that redirect works when the redirect module is installed.
   */
  public function testRequirementCheckingPasses(): void {
    $instance = Helper::redirect();

    $this->assertInstanceOf(Redirect::class, $instance);
  }

  /**
   * Tests that checkRequirements throws when a required module is missing.
   */
  public function testRequirementCheckingFails(): void {
    $helper = $this->createMock(HelperBase::class);
    $helper->method('requiredModules')->willReturn(['nonexistent_module']);

    $method = new \ReflectionMethod(Helper::class, 'checkRequirements');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Required modules not installed: nonexistent_module');
    $method->invoke(NULL, $helper);
  }

  /**
   * Tests that a custom batch_size is set on the cloned instance.
   */
  public function testBatchSize(): void {
    $sandbox = [];
    $instance = Helper::entity($sandbox, 25);

    $reflection = new \ReflectionProperty($instance, 'batchSize');
    $this->assertEquals(25, $reflection->getValue($instance));
  }

}
