<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use Drupal\drupal_helpers\Helpers\Menu;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Kernel tests for the Menu helper service.
 */
#[CoversClass(Menu::class)]
class MenuHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system', 'menu_link_content', 'link', 'user'];

  /**
   * The menu helper service.
   *
   * @var \Drupal\drupal_helpers\Helpers\Menu
   */
  protected Menu $menuHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('menu_link_content');
    $this->installConfig(['system']);

    // Create the 'main' menu if it does not exist.
    $menu_storage = $this->container->get('entity_type.manager')->getStorage('menu');
    if (!$menu_storage->load('main')) {
      $menu_storage->create([
        'id' => 'main',
        'label' => 'Main navigation',
      ])->save();
    }

    $this->menuHelper = $this->container->get('drupal_helpers.menu');
  }

  /**
   * Tests that requiredModules returns an empty array.
   */
  public function testRequiredModules(): void {
    $this->assertEquals([], $this->menuHelper->requiredModules());
  }

  /**
   * Tests creating a flat menu tree.
   */
  public function testCreateTreeFlat(): void {
    $tree = [
      'Home' => '/',
      'About' => '/about',
    ];

    $links = $this->menuHelper->createTree('main', $tree);

    $this->assertCount(2, $links);
    $this->assertEquals('Home', $links[0]->getTitle());
    $this->assertEquals('internal:/', $links[0]->get('link')->first()->get('uri')->getValue());
    $this->assertEquals('About', $links[1]->getTitle());
    $this->assertEquals('internal:/about', $links[1]->get('link')->first()->get('uri')->getValue());
  }

  /**
   * Tests creating a nested menu tree with children.
   */
  public function testCreateTreeNested(): void {
    $tree = [
      'About' => [
        'path' => '/about',
        'children' => [
          'Team' => '/about/team',
          'Contact' => '/about/contact',
        ],
      ],
    ];

    $links = $this->menuHelper->createTree('main', $tree);

    $this->assertCount(3, $links);

    // Parent link.
    $this->assertEquals('About', $links[0]->getTitle());

    // Children should reference the parent.
    $parent_plugin_id = 'menu_link_content:' . $links[0]->uuid();
    $this->assertEquals($parent_plugin_id, $links[1]->get('parent')->value);
    $this->assertEquals($parent_plugin_id, $links[2]->get('parent')->value);
    $this->assertEquals('Team', $links[1]->getTitle());
    $this->assertEquals('Contact', $links[2]->getTitle());
  }

  /**
   * Tests creating a menu link with an external URL.
   */
  public function testCreateTreeExternalLink(): void {
    $tree = [
      'External' => 'https://example.com',
    ];

    $links = $this->menuHelper->createTree('main', $tree);

    $this->assertCount(1, $links);
    $this->assertEquals('https://example.com', $links[0]->get('link')->first()->get('uri')->getValue());
  }

  /**
   * Tests deleting all menu links from a menu.
   */
  public function testDeleteTree(): void {
    $this->menuHelper->createTree('main', [
      'Home' => '/',
      'About' => '/about',
      'Contact' => '/contact',
    ]);

    $result = $this->menuHelper->deleteTree('main');

    $this->assertNotNull($result);

    $storage = $this->container->get('entity_type.manager')->getStorage('menu_link_content');
    $remaining = $storage->loadByProperties(['menu_name' => 'main']);
    $this->assertEmpty($remaining);
  }

  /**
   * Tests deleting menu links in sandbox batch mode.
   */
  public function testDeleteTreeSandbox(): void {
    // Create 120+ menu links.
    $tree = [];
    for ($i = 0; $i < 125; $i++) {
      $tree['Link ' . $i] = '/link-' . $i;
    }
    $this->menuHelper->createTree('main', $tree);

    $storage = $this->container->get('entity_type.manager')->getStorage('menu_link_content');
    $this->assertCount(125, $storage->loadByProperties(['menu_name' => 'main']));

    // Use sandbox batching.
    $sandbox = [];
    $this->menuHelper->setSandbox($sandbox);

    do {
      $result = $this->menuHelper->deleteTree('main');
    } while ($result === NULL);

    $remaining = $storage->loadByProperties(['menu_name' => 'main']);
    /** @phpstan-ignore method.impossibleType */
    $this->assertEmpty($remaining);
  }

  /**
   * Tests finding a menu link by title.
   */
  public function testFindItem(): void {
    $this->menuHelper->createTree('main', [
      'Home' => '/',
      'About' => '/about',
    ]);

    $link = $this->menuHelper->findItem('main', ['title' => 'About']);

    $this->assertNotNull($link);
    $this->assertEquals('About', $link->getTitle());
    $this->assertEquals('internal:/about', $link->get('link')->first()->get('uri')->getValue());
  }

  /**
   * Tests finding a non-existent menu link returns NULL.
   */
  public function testFindItemNotFound(): void {
    $link = $this->menuHelper->findItem('main', ['title' => 'NonExistent']);

    $this->assertNull($link);
  }

  /**
   * Tests updating properties on an existing menu link.
   */
  public function testUpdateItem(): void {
    $this->menuHelper->createTree('main', [
      'About' => '/about',
    ]);

    $updated = $this->menuHelper->updateItem('main', ['title' => 'About'], [
      'path' => '/about-us',
      'weight' => 10,
    ]);

    $this->assertNotNull($updated);
    $this->assertEquals('internal:/about-us', $updated->get('link')->first()->get('uri')->getValue());
    $this->assertEquals(10, $updated->get('weight')->value);
  }

  /**
   * Tests updating a non-existent menu link returns NULL and warns.
   */
  public function testUpdateItemNotFound(): void {
    $result = $this->menuHelper->updateItem('main', ['title' => 'Ghost'], [
      'weight' => 5,
    ]);

    $this->assertNull($result);

    $messages = $this->container->get('messenger')->messagesByType('warning');
    $this->assertNotEmpty($messages);
  }

}
