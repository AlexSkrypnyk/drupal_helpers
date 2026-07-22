<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\drupal_helpers\Helpers\Menu;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Kernel tests for the Menu helper service.
 */
#[CoversClass(Menu::class)]
#[RunTestsInSeparateProcesses]
class MenuHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system', 'menu_link_content', 'link', 'user'];

  /**
   * The menu helper service.
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
   * Tests creating a menu link from a path without a leading slash.
   */
  public function testCreateTreeRelativePath(): void {
    $this->menuHelper->createTree('main', ['About' => 'about']);

    $this->assertSame(['About' => '/about'], $this->menuHelper->exportTree('main'));
  }

  /**
   * Tests that safe mode does not duplicate existing links.
   */
  public function testCreateTreeSafeSkipsExisting(): void {
    $this->menuHelper->createTree('main', ['Home' => '/', 'About' => '/about']);

    $this->menuHelper->createTree('main', [
      'Home' => '/',
      'About' => '/about',
      'Contact' => '/contact',
    ]);

    $storage = $this->container->get('entity_type.manager')->getStorage('menu_link_content');
    $this->assertCount(3, $storage->loadByProperties(['menu_name' => 'main']));
    $this->assertSame([
      'Home' => '/',
      'About' => '/about',
      'Contact' => '/contact',
    ], $this->menuHelper->exportTree('main'));
  }

  /**
   * Tests that update mode changes the path of an existing link in place.
   */
  public function testCreateTreeUpdateChangesPath(): void {
    $this->menuHelper->createTree('main', ['About' => '/about']);

    $this->menuHelper->createTree('main', ['About' => '/about-us'], Menu::MODE_UPDATE);

    $this->assertSame(['About' => '/about-us'], $this->menuHelper->exportTree('main'));

    $storage = $this->container->get('entity_type.manager')->getStorage('menu_link_content');
    $this->assertCount(1, $storage->loadByProperties(['menu_name' => 'main']));
  }

  /**
   * Tests that update mode re-applies extra fields to an existing link.
   */
  public function testCreateTreeUpdateAppliesExtraFields(): void {
    $this->menuHelper->createTree('main', [
      'About' => ['path' => '/about', 'description' => 'Old description'],
    ]);

    $this->menuHelper->createTree('main', [
      'About' => ['path' => '/about', 'description' => 'New description'],
    ], Menu::MODE_UPDATE);

    $link = $this->menuHelper->findItem('main', ['title' => 'About']);
    $this->assertNotNull($link);
    $this->assertSame('New description', $link->get('description')->value);
  }

  /**
   * Tests that update mode also creates links missing from the menu.
   */
  public function testCreateTreeUpdateCreatesMissing(): void {
    $this->menuHelper->createTree('main', ['Home' => '/']);

    $this->menuHelper->createTree('main', [
      'Home' => '/',
      'About' => '/about',
    ], Menu::MODE_UPDATE);

    $this->assertSame(['Home' => '/', 'About' => '/about'], $this->menuHelper->exportTree('main'));
  }

  /**
   * Tests that sync mode deletes links absent from the supplied tree.
   */
  public function testCreateTreeSyncDeletesAbsent(): void {
    $this->menuHelper->createTree('main', [
      'Home' => '/',
      'About' => '/about',
      'Contact' => '/contact',
    ]);

    $this->menuHelper->createTree('main', [
      'Home' => '/',
      'Contact' => '/contact',
    ], Menu::MODE_SYNC);

    $this->assertSame(['Home' => '/', 'Contact' => '/contact'], $this->menuHelper->exportTree('main'));
    $this->assertNull($this->menuHelper->findItem('main', ['title' => 'About']));
  }

  /**
   * Tests that sync mode reconciles a nested tree, deleting absent children.
   */
  public function testCreateTreeSyncNested(): void {
    $this->menuHelper->createTree('main', [
      'About' => [
        'path' => '/about',
        'children' => [
          'Team' => '/about/team',
          'Contact' => '/about/contact',
        ],
      ],
    ]);

    $this->menuHelper->createTree('main', [
      'About' => [
        'path' => '/about',
        'children' => [
          'Team' => '/about/team',
        ],
      ],
    ], Menu::MODE_SYNC);

    $this->assertSame([
      'About' => [
        'path' => '/about',
        'children' => ['Team' => '/about/team'],
      ],
    ], $this->menuHelper->exportTree('main'));
    $this->assertNull($this->menuHelper->findItem('main', ['title' => 'Contact']));
  }

  /**
   * Tests that sync mode refuses to wipe a menu from an empty tree.
   */
  public function testCreateTreeSyncEmptyTreeGuard(): void {
    $this->menuHelper->createTree('main', ['Home' => '/', 'About' => '/about']);

    $result = $this->menuHelper->createTree('main', [], Menu::MODE_SYNC);

    $this->assertSame([], $result);

    $storage = $this->container->get('entity_type.manager')->getStorage('menu_link_content');
    $this->assertCount(2, $storage->loadByProperties(['menu_name' => 'main']));

    $messages = $this->container->get('messenger')->messagesByType('warning');
    $this->assertNotEmpty($messages);
  }

  /**
   * Tests that an unsupported mode throws.
   */
  public function testCreateTreeInvalidModeThrows(): void {
    $this->expectException(\InvalidArgumentException::class);

    $this->menuHelper->createTree('main', ['Home' => '/'], 'bogus');
  }

  /**
   * Tests exporting a flat menu to a tree array.
   */
  public function testExportTreeFlat(): void {
    $this->menuHelper->createTree('main', [
      'Home' => '/',
      'About' => '/about',
    ]);

    $this->assertSame(['Home' => '/', 'About' => '/about'], $this->menuHelper->exportTree('main'));
  }

  /**
   * Tests exporting a nested menu preserves hierarchy and order.
   */
  public function testExportTreeNested(): void {
    $tree = [
      'Home' => '/',
      'About' => [
        'path' => '/about',
        'children' => [
          'Team' => '/about/team',
          'Contact' => '/about/contact',
        ],
      ],
      'External' => 'https://example.com',
    ];

    $this->menuHelper->createTree('main', $tree);

    $this->assertSame($tree, $this->menuHelper->exportTree('main'));
  }

  /**
   * Tests exporting an external link round-trips the URL.
   */
  public function testExportTreeExternalLink(): void {
    $this->menuHelper->createTree('main', ['External' => 'https://example.com']);

    $this->assertSame(['External' => 'https://example.com'], $this->menuHelper->exportTree('main'));
  }

  /**
   * Tests exporting round-trips non-HTTP scheme links such as mailto and tel.
   */
  public function testExportTreeSchemeRoundTrip(): void {
    $tree = [
      'Email' => 'mailto:info@example.com',
      'Phone' => 'tel:+15551234567',
    ];
    $this->menuHelper->createTree('main', $tree);
    $exported = $this->menuHelper->exportTree('main');
    $this->assertIsArray($exported);

    $this->menuHelper->deleteTree('main');
    $this->menuHelper->createTree('main', $exported);

    $this->assertSame($tree, $this->menuHelper->exportTree('main'));
  }

  /**
   * Tests exporting a route link round-trips the token path.
   */
  public function testExportTreeFront(): void {
    $this->menuHelper->createTree('main', ['Home' => '<front>']);

    $this->assertSame(['Home' => '<front>'], $this->menuHelper->exportTree('main'));
  }

  /**
   * Tests exporting an empty menu returns an empty array.
   */
  public function testExportTreeEmpty(): void {
    $this->assertSame([], $this->menuHelper->exportTree('main'));
  }

  /**
   * Tests that exporting then re-creating reproduces the structure.
   */
  public function testExportTreeRoundTrip(): void {
    $tree = [
      'Home' => '/',
      'About' => [
        'path' => '/about',
        'children' => [
          'Team' => '/about/team',
        ],
      ],
    ];
    $this->menuHelper->createTree('main', $tree);
    $exported = $this->menuHelper->exportTree('main');
    $this->assertIsArray($exported);

    // Wipe the menu and rebuild it from the exported structure.
    $this->menuHelper->deleteTree('main');
    $this->menuHelper->createTree('main', $exported);

    $this->assertSame($tree, $this->menuHelper->exportTree('main'));
  }

  /**
   * Tests exporting a menu as a ready-to-paste PHP array literal.
   */
  public function testExportTreePhp(): void {
    $this->menuHelper->createTree('main', [
      'Home' => '/',
      'About' => [
        'path' => '/about',
        'children' => [
          'Team' => '/about/team',
        ],
      ],
    ]);

    $expected = <<<'PHP'
    [
      'Home' => '/',
      'About' => [
        'path' => '/about',
        'children' => [
          'Team' => '/about/team',
        ],
      ],
    ]
    PHP;

    $this->assertSame($expected, $this->menuHelper->exportTree('main', Menu::FORMAT_PHP));
  }

  /**
   * Tests exporting orders links by weight, not by creation order.
   */
  public function testExportTreeOrdersByWeight(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('menu_link_content');
    $storage->create(['menu_name' => 'main', 'title' => 'Third', 'link' => ['uri' => 'internal:/c'], 'weight' => 10])->save();
    $storage->create(['menu_name' => 'main', 'title' => 'First', 'link' => ['uri' => 'internal:/a'], 'weight' => -5])->save();
    $storage->create(['menu_name' => 'main', 'title' => 'Second', 'link' => ['uri' => 'internal:/b'], 'weight' => 0])->save();

    $this->assertSame(['First' => '/a', 'Second' => '/b', 'Third' => '/c'], $this->menuHelper->exportTree('main'));
  }

  /**
   * Tests exporting warns when sibling links share a title.
   */
  public function testExportTreeDuplicateTitleWarns(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('menu_link_content');
    $storage->create(['menu_name' => 'main', 'title' => 'Docs', 'link' => ['uri' => 'internal:/a'], 'weight' => 0])->save();
    $storage->create(['menu_name' => 'main', 'title' => 'Docs', 'link' => ['uri' => 'internal:/b'], 'weight' => 1])->save();

    $this->menuHelper->exportTree('main');

    $messages = $this->container->get('messenger')->messagesByType('warning');
    $this->assertNotEmpty($messages);
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
