<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\drupal_helpers\Report\Reporter;
use Drupal\drupal_helpers\Traits\TreeExportTrait;
use Drupal\drupal_helpers\Traits\TreeSyncTrait;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Menu link helpers for deploy hooks.
 */
class Menu extends HelperBase {

  use TreeExportTrait;
  use TreeSyncTrait;

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
   *
   * // Reconcile: re-apply the tree to existing links and delete any not listed.
   * Helper::menu()->createTree('main', $tree, mode: Menu::MODE_SYNC);
   * @endcode
   *
   * @param string $menu_name
   *   Menu machine name.
   * @param array $tree
   *   Nested array where keys are link titles and values are either path
   *   strings or arrays with 'path' and optional 'children', plus any
   *   extra fields for the menu link entity.
   * @param string $mode
   *   Reconciliation mode, matching links by title within their parent:
   *   self::MODE_SAFE (default) creates missing links and leaves existing ones
   *   untouched; self::MODE_UPDATE also re-applies the path, order, expansion
   *   and extra fields to existing links; self::MODE_SYNC additionally deletes
   *   links absent from the tree.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface[]
   *   Array of created, updated or preserved menu link entities.
   */
  public function createTree(string $menu_name, array $tree, string $mode = self::MODE_SAFE): array {
    $this->assertMode($mode);

    $existing = $this->indexLinksByParent($menu_name);
    $kept = [];
    $links = $this->reconcileMenuTree($menu_name, $tree, NULL, $mode, $existing, $kept);

    if ($mode === self::MODE_SYNC) {
      $this->syncDeleteLinks($menu_name, $tree, $kept);
    }

    return $links;
  }

  /**
   * Reconcile one level of a menu tree, recursing into children.
   *
   * @param string $menu_name
   *   Menu machine name.
   * @param array $tree
   *   The nested tree level to reconcile.
   * @param string|null $parent_id
   *   Parent menu link plugin ID for this level (NULL for the top level).
   * @param string $mode
   *   Reconciliation mode.
   * @param array $existing
   *   Existing links indexed as [parent plugin ID][title] => link, where the
   *   top level uses '' as the parent key.
   * @param array<int|string, bool> $kept
   *   Accumulates the IDs of reconciled links by reference, as a set of
   *   [link ID => TRUE].
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface[]
   *   Reconciled links for this level and its descendants.
   */
  protected function reconcileMenuTree(string $menu_name, array $tree, ?string $parent_id, string $mode, array $existing, array &$kept): array {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $links = [];
    $weight = 0;

    foreach ($tree as $title => $leaf) {
      $leaf = is_array($leaf) ? $leaf : ['path' => $leaf];

      $path = $leaf['path'] ?? '';
      $children = $leaf['children'] ?? [];
      unset($leaf['path'], $leaf['children']);

      $uri = $this->pathToUri($path);
      $expanded = !empty($children);

      $link = $existing[$parent_id ?? ''][$title] ?? NULL;

      if ($link instanceof MenuLinkContentInterface) {
        $this->reconcileLink($link, $uri, $weight, $expanded, $leaf, $mode, $menu_name);
      }
      else {
        $values = [
          'menu_name' => $menu_name,
          'title' => $title,
          'link' => ['uri' => $uri],
          'weight' => $weight,
          'expanded' => $expanded,
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
      }

      $links[] = $link;
      $kept[$link->id()] = TRUE;

      if ($children) {
        $plugin_id = 'menu_link_content:' . $link->uuid();
        $links = array_merge($links, $this->reconcileMenuTree($menu_name, $children, $plugin_id, $mode, $existing, $kept));
      }

      $weight++;
    }

    return $links;
  }

  /**
   * Apply the reconciliation mode to an existing menu link.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $link
   *   The existing link matched in the tree.
   * @param string $uri
   *   The link URI derived from the tree path.
   * @param int $weight
   *   The link's position within its parent.
   * @param bool $expanded
   *   Whether the link should be expanded (it has children).
   * @param array $extra
   *   Extra field values supplied for the link in the tree.
   * @param string $mode
   *   Reconciliation mode.
   * @param string $menu_name
   *   Menu machine name, for reporting.
   */
  protected function reconcileLink(MenuLinkContentInterface $link, string $uri, int $weight, bool $expanded, array $extra, string $mode, string $menu_name): void {
    if ($mode === self::MODE_SAFE) {
      $this->reporter->skipped($this->t('Menu link "@title" already exists in "@menu" - skipped.', [
        '@title' => $link->getTitle(),
        '@menu' => $menu_name,
      ]));

      return;
    }

    $values = [
      'link' => ['uri' => $uri],
      'weight' => $weight,
      'expanded' => $expanded,
    ] + $extra;

    foreach ($values as $field => $value) {
      $link->set($field, $value);
    }

    $link->save();

    $this->reporter->updated($this->t('Updated menu link "@title" in "@menu".', [
      '@title' => $link->getTitle(),
      '@menu' => $menu_name,
    ]));
  }

  /**
   * Index all links in a menu by parent plugin ID and title.
   *
   * @param string $menu_name
   *   Menu machine name.
   *
   * @return array<string, array<string, \Drupal\menu_link_content\MenuLinkContentInterface>>
   *   Links indexed as [parent plugin ID][title] => link, where the top level
   *   uses '' as the parent key. When several links share a title under the
   *   same parent, the first loaded wins.
   */
  protected function indexLinksByParent(string $menu_name): array {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $index = [];

    foreach ($storage->loadByProperties(['menu_name' => $menu_name]) as $link) {
      $index[$link->getParentId()][$link->getTitle()] ??= $link;
    }

    return $index;
  }

  /**
   * Delete links absent from the supplied tree during a sync.
   *
   * @param string $menu_name
   *   Menu machine name.
   * @param array $tree
   *   The tree that was reconciled.
   * @param array<int|string, bool> $kept
   *   Set of kept link IDs.
   */
  protected function syncDeleteLinks(string $menu_name, array $tree, array $kept): void {
    if ($tree === []) {
      $this->reporter->skipped($this->t('Refused to delete every link in "@menu" from an empty sync tree; use deleteTree() to clear it intentionally.', [
        '@menu' => $menu_name,
      ]), severity: Reporter::SEVERITY_WARNING);

      return;
    }

    $storage = $this->entityTypeManager->getStorage('menu_link_content');

    foreach ($storage->loadByProperties(['menu_name' => $menu_name]) as $id => $link) {
      if (isset($kept[$id])) {
        continue;
      }

      $this->reporter->deleted($this->t('Deleted menu link "@title" from "@menu".', [
        '@title' => $link->getTitle(),
        '@menu' => $menu_name,
      ]));
      $link->delete();
    }
  }

  /**
   * Export a menu to the nested tree accepted by createTree().
   *
   * Sibling links that share a title cannot both be represented and are
   * reported with a warning during export.
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
      usort($links, $this->compareLinks(...));
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
      $title = $link->getTitle();

      if (isset($tree[$title])) {
        $this->reporter->skipped($this->t('Menu links share the title "@title" at the same level; the exported tree can only keep one.', [
          '@title' => $title,
        ]), severity: Reporter::SEVERITY_WARNING);
      }

      $path = $this->uriToPath($link->get('link')->first()->get('uri')->getValue());
      $children = $this->buildMenuTree($children_by_parent, 'menu_link_content:' . $link->uuid());

      $tree[$title] = $children !== [] ? ['path' => $path, 'children' => $children] : $path;
    }

    return $tree;
  }

  /**
   * Compare two menu links by weight, then by title.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $a
   *   First menu link.
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $b
   *   Second menu link.
   *
   * @return int
   *   Negative, zero or positive per the usort() contract.
   */
  protected function compareLinks(MenuLinkContentInterface $a, MenuLinkContentInterface $b): int {
    return $this->compareByWeight($a->getWeight(), $a->getTitle(), $b->getWeight(), $b->getTitle());
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
      $this->reporter->skipped($this->t('Menu link not found in "@menu" - skipped update.', [
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
