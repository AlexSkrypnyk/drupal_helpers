<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\drupal_helpers\Report\Reporter;
use Drupal\drupal_helpers\Traits\TreeExportTrait;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Menu link helpers for deploy hooks.
 */
class Menu extends HelperBase {

  use TreeExportTrait;

  /**
   * Create menu links from a nested tree structure.
   *
   * @code
   * $tree = [
   *   'Home' => '/',
   *   'About' => [
   *     'path' => '/about',
   *     'children' => [
   *       'Team' => '/about/team',
   *       'Contact' => '/about/contact',
   *     ],
   *   ],
   *   'External' => 'https://example.com',
   * ];
   * Helper::menu()->createTree('main', $tree);
   * @endcode
   *
   * @param string $menu_name
   *   Menu machine name.
   * @param array $tree
   *   Nested array where keys are link titles and values are either path
   *   strings or arrays with 'path' and optional 'children', plus any
   *   extra fields for the menu link entity.
   * @param string|null $parent_id
   *   Internal parameter for recursion. Parent menu link plugin ID.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface[]
   *   Array of created menu link entities.
   */
  public function createTree(string $menu_name, array $tree, ?string $parent_id = NULL): array {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $links = [];
    $weight = 0;

    foreach ($tree as $title => $leaf) {
      $leaf = is_array($leaf) ? $leaf : ['path' => $leaf];

      $path = $leaf['path'] ?? '';
      $children = $leaf['children'] ?? [];
      unset($leaf['path'], $leaf['children']);

      $uri = $this->pathToUri($path);

      $values = [
        'menu_name' => $menu_name,
        'title' => $title,
        'link' => ['uri' => $uri],
        'weight' => $weight,
        'expanded' => !empty($children),
      ] + $leaf;

      if ($parent_id !== NULL) {
        $values['parent'] = $parent_id;
      }

      /** @var \Drupal\menu_link_content\MenuLinkContentInterface $link */
      $link = $storage->create($values);
      $link->save();

      $this->reporter->created($this->t('Created menu link "@title" in "@menu".', [
        '@title' => $title,
        '@menu' => $menu_name,
      ]));

      $links[] = $link;
      $weight++;

      if ($children) {
        $plugin_id = 'menu_link_content:' . $link->uuid();
        $links = array_merge($links, $this->createTree($menu_name, $children, $plugin_id));
      }
    }

    return $links;
  }

  /**
   * Export a menu to the nested tree accepted by createTree().
   *
   * @code
   * // Snapshot structure as data:
   * $tree = Helper::menu()->exportTree('main');
   *
   * // Render as ready-to-paste PHP or YAML:
   * $php = Helper::menu()->exportTree('main', Menu::FORMAT_PHP);
   * $yaml = Helper::menu()->exportTree('main', Menu::FORMAT_YAML);
   * @endcode
   *
   * @param string $menu_name
   *   Menu machine name.
   * @param string $format
   *   Output format: self::FORMAT_ARRAY (default), self::FORMAT_PHP or
   *   self::FORMAT_YAML.
   *
   * @return array|string
   *   The nested tree array, or a rendered PHP/YAML string.
   */
  public function exportTree(string $menu_name, string $format = self::FORMAT_ARRAY): array|string {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');

    $children_by_parent = [];
    foreach ($storage->loadByProperties(['menu_name' => $menu_name]) as $link) {
      $children_by_parent[$link->getParentId()][] = $link;
    }

    foreach ($children_by_parent as &$links) {
      usort($links, fn(MenuLinkContentInterface $a, MenuLinkContentInterface $b): int => ($a->getWeight() <=> $b->getWeight()) ?: strcmp($a->getTitle(), $b->getTitle()));
    }
    unset($links);

    $tree = $this->buildMenuTree($children_by_parent, '');

    return $format === self::FORMAT_ARRAY ? $tree : $this->renderTree($tree, $format);
  }

  /**
   * Build a nested menu tree from links grouped by parent.
   *
   * @param array $children_by_parent
   *   Menu link entities keyed by parent plugin ID, each level ordered by
   *   weight then title.
   * @param string $parent_id
   *   Parent menu link plugin ID whose level is being built ('' for the top
   *   level).
   *
   * @return array
   *   Nested tree keyed by link title, matching the structure accepted by
   *   createTree(). Leaf links map to a path string; links with children map to
   *   an array with 'path' and 'children'.
   */
  protected function buildMenuTree(array $children_by_parent, string $parent_id): array {
    $tree = [];

    foreach ($children_by_parent[$parent_id] ?? [] as $link) {
      $path = $this->uriToPath($link->get('link')->first()->get('uri')->getValue());
      $children = $this->buildMenuTree($children_by_parent, 'menu_link_content:' . $link->uuid());

      $tree[$link->getTitle()] = $children !== [] ? ['path' => $path, 'children' => $children] : $path;
    }

    return $tree;
  }

  /**
   * Delete all menu links from a menu.
   *
   * @code
   * Helper::menu()->deleteTree('main');
   * @endcode
   *
   * @param string $menu_name
   *   Menu machine name.
   *
   * @return string|null
   *   Status message when finished, or NULL while in progress.
   */
  public function deleteTree(string $menu_name): ?string {
    return $this->batchEntity('menu_link_content', NULL, function ($link): void {
      $link->delete();
    }, ['menu_name' => $menu_name], status: Reporter::DELETED);
  }

  /**
   * Find a menu link by properties.
   *
   * @code
   * $link = Helper::menu()->findItem('main', ['title' => 'About']);
   * @endcode
   *
   * @param string $menu_name
   *   Menu machine name.
   * @param array $properties
   *   Properties to match (e.g., ['title' => 'About']).
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface|null
   *   First matching menu link or NULL.
   */
  public function findItem(string $menu_name, array $properties): ?MenuLinkContentInterface {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $properties['menu_name'] = $menu_name;
    $links = $storage->loadByProperties($properties);

    return $links ? reset($links) : NULL;
  }

  /**
   * Update properties on an existing menu link found by properties.
   *
   * @code
   * Helper::menu()->updateItem('main', ['title' => 'About'], [
   *   'path' => '/about-us',
   *   'weight' => 5,
   * ]);
   * @endcode
   *
   * @param string $menu_name
   *   Menu machine name.
   * @param array $find_properties
   *   Properties to find the existing link.
   * @param array $updates
   *   Field values to update on the found link.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface|null
   *   Updated link or NULL if not found.
   */
  public function updateItem(string $menu_name, array $find_properties, array $updates): ?MenuLinkContentInterface {
    $link = $this->findItem($menu_name, $find_properties);

    if (!$link instanceof MenuLinkContentInterface) {
      $this->reporter->skipped($this->t('Menu link not found in "@menu" — skipped update.', [
        '@menu' => $menu_name,
      ]), severity: Reporter::SEVERITY_WARNING);

      return NULL;
    }

    foreach ($updates as $field => $value) {
      if ($field === 'path') {
        $link->set('link', ['uri' => $this->pathToUri($value)]);
      }
      else {
        $link->set($field, $value);
      }
    }

    $link->save();

    $this->reporter->updated($this->t('Updated menu link "@title" in "@menu".', [
      '@title' => $link->getTitle(),
      '@menu' => $menu_name,
    ]));

    return $link;
  }

  /**
   * Convert a path string to a URI suitable for menu links.
   *
   * @param string $path
   *   A path like '/about', '<front>', or 'https://example.com'.
   *
   * @return string
   *   URI string (internal:/, entity:node/1, or https://...).
   */
  protected function pathToUri(string $path): string {
    // Pass through anything already carrying a URI scheme (internal:, entity:,
    // mailto:, tel:, https:, ...) so it round-trips with uriToPath().
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $path) === 1) {
      return $path;
    }

    if ($path === '<front>' || $path === '<nolink>') {
      return 'route:<' . trim($path, '<>') . '>';
    }

    if (!str_starts_with($path, '/')) {
      $path = '/' . $path;
    }

    return 'internal:' . $path;
  }

  /**
   * Convert a menu link URI back to a path string.
   *
   * Inverse of pathToUri(): 'internal:/about' becomes '/about' and
   * 'route:<front>' becomes '<front>'. External, 'entity:' and other 'route:'
   * or 'base:' URIs are returned unchanged.
   *
   * @param string $uri
   *   A menu link URI like 'internal:/about', 'route:<front>' or
   *   'https://example.com'.
   *
   * @return string
   *   Path string suitable for pathToUri().
   */
  protected function uriToPath(string $uri): string {
    if (str_starts_with($uri, 'internal:')) {
      return substr($uri, strlen('internal:'));
    }

    if (str_starts_with($uri, 'route:<') && str_ends_with($uri, '>')) {
      return substr($uri, strlen('route:'));
    }

    return $uri;
  }

}
