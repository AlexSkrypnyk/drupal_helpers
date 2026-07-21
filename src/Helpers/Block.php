<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Core\Entity\EntityInterface;

/**
 * Block placement and block content helpers for deploy hooks.
 *
 * Placement manages core 'block' config entities. Content creation and deletion
 * additionally require the 'block_content' module.
 */
class Block extends HelperBase {

  /**
   * {@inheritdoc}
   */
  public function requiredModules(): array {
    return ['block'];
  }

  /**
   * Place a plugin block into a theme region.
   *
   * @code
   * Helper::block()->place('system_powered_by_block', 'olivero', 'footer', [
   *   'weight' => 10,
   * ]);
   *
   * // With visibility conditions:
   * Helper::block()->place('system_branding_block', 'olivero', 'header', [
   *   'visibility' => [
   *     'request_path' => [
   *       'id' => 'request_path',
   *       'pages' => '/admin/*',
   *       'negate' => TRUE,
   *     ],
   *   ],
   * ]);
   * @endcode
   *
   * @param string $plugin_id
   *   Block plugin ID (e.g., 'system_powered_by_block').
   * @param string $theme
   *   Theme machine name (e.g., 'olivero').
   * @param string $region
   *   Region machine name (e.g., 'footer_top').
   * @param array $options
   *   Optional overrides:
   *   - 'id': Explicit block machine name. Defaults to "{theme}_{plugin}".
   *   - 'weight': Block weight. Defaults to 0.
   *   - 'settings': Block plugin settings, merged over the plugin defaults.
   *   - 'visibility': Visibility condition configuration keyed by condition ID.
   * @param bool $skip_existing
   *   If TRUE, skip placing when a block with the resolved machine name already
   *   exists. Defaults to TRUE.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Created block entity, or the existing one when skipped.
   */
  public function place(string $plugin_id, string $theme, string $region, array $options = [], bool $skip_existing = TRUE): ?EntityInterface {
    $id = $options['id'] ?? $this->deriveId($theme, $plugin_id);
    $storage = $this->entityTypeManager->getStorage('block');

    if ($skip_existing) {
      $existing = $storage->load($id);
      if ($existing instanceof EntityInterface) {
        $this->reporter->skipped($this->t('Block "@id" already exists - skipped.', [
          '@id' => $id,
        ]));

        return $existing;
      }
    }

    $block = $storage->create([
      'id' => $id,
      'plugin' => $plugin_id,
      'theme' => $theme,
      'region' => $region,
      'weight' => $options['weight'] ?? 0,
      'settings' => $options['settings'] ?? [],
      'visibility' => $options['visibility'] ?? [],
    ]);
    $block->save();

    $this->reporter->created($this->t('Placed block "@plugin" as "@id" in "@theme:@region".', [
      '@plugin' => $plugin_id,
      '@id' => $id,
      '@theme' => $theme,
      '@region' => $region,
    ]));

    return $block;
  }

  /**
   * Place multiple plugin blocks.
   *
   * @code
   * Helper::block()->placeMultiple([
   *   [
   *     'plugin' => 'system_powered_by_block',
   *     'theme' => 'olivero',
   *     'region' => 'footer',
   *   ],
   *   [
   *     'plugin' => 'system_branding_block',
   *     'theme' => 'olivero',
   *     'region' => 'header',
   *     'options' => ['weight' => -10],
   *   ],
   * ]);
   * @endcode
   *
   * @param array $blocks
   *   Array of blocks, each being an array with keys:
   *   - 'plugin': Block plugin ID.
   *   - 'theme': Theme machine name.
   *   - 'region': Region machine name.
   *   - 'options': (optional) Overrides as accepted by place().
   *
   * @return int
   *   Number of processed blocks.
   */
  public function placeMultiple(array $blocks): int {
    $count = 0;

    foreach ($blocks as $block) {
      $result = $this->place($block['plugin'], $block['theme'], $block['region'], $block['options'] ?? []);

      if ($result instanceof EntityInterface) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * Remove a placed block.
   *
   * @code
   * Helper::block()->remove('olivero_system_powered_by_block');
   * @endcode
   *
   * @param string $id
   *   Block machine name, as returned by place() via ->id(), or the default
   *   "{theme}_{plugin}".
   *
   * @return bool
   *   TRUE if a block was removed, FALSE if none matched.
   */
  public function remove(string $id): bool {
    $block = $this->entityTypeManager->getStorage('block')->load($id);

    if (!$block instanceof EntityInterface) {
      $this->reporter->skipped($this->t('Block "@id" not found - nothing to remove.', [
        '@id' => $id,
      ]));

      return FALSE;
    }

    $block->delete();

    $this->reporter->deleted($this->t('Removed block "@id".', [
      '@id' => $id,
    ]));

    return TRUE;
  }

  /**
   * Create a block content entity.
   *
   * @code
   * Helper::block()->createContent('basic', [
   *   'info' => 'Footer contact',
   *   'body' => 'Call us on 1234',
   * ]);
   * @endcode
   *
   * @param string $bundle
   *   Block content type (bundle) machine name.
   * @param array $values
   *   Field values, e.g. 'info' (the block description) and body or other
   *   fields. The bundle is set automatically.
   * @param bool $skip_existing
   *   If TRUE and an 'info' value is given, skip creating when block content
   *   with the same 'info' already exists in the bundle. Defaults to TRUE.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Created block content entity, or the existing one when skipped.
   */
  public function createContent(string $bundle, array $values = [], bool $skip_existing = TRUE): ?EntityInterface {
    $this->requireEntityType('block_content');
    $storage = $this->entityTypeManager->getStorage('block_content');

    $info = $values['info'] ?? NULL;

    if ($skip_existing && $info !== NULL) {
      $existing = $storage->loadByProperties(['type' => $bundle, 'info' => $info]);
      if ($existing) {
        $this->reporter->skipped($this->t('Block content "@info" already exists - skipped.', [
          '@info' => $info,
        ]));

        return reset($existing);
      }
    }

    $block_content = $storage->create(['type' => $bundle] + $values);
    $block_content->save();

    $this->reporter->created($this->t('Created "@type" block content "@info".', [
      '@type' => $bundle,
      '@info' => $info ?? '',
    ]));

    return $block_content;
  }

  /**
   * Create multiple block content entities.
   *
   * @code
   * Helper::block()->createContentMultiple([
   *   ['type' => 'basic', 'info' => 'Footer contact', 'body' => 'Call us'],
   *   ['type' => 'basic', 'info' => 'Opening hours', 'body' => '9am - 5pm'],
   * ]);
   * @endcode
   *
   * @param array $blocks
   *   Array of block content, each a values array that must include 'type' (the
   *   bundle) alongside 'info' and any field values.
   *
   * @return int
   *   Number of processed block content entities.
   */
  public function createContentMultiple(array $blocks): int {
    $count = 0;

    foreach ($blocks as $block) {
      $result = $this->createContent($block['type'], $block);

      if ($result instanceof EntityInterface) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * Delete block content by info label.
   *
   * @code
   * Helper::block()->deleteContent('Footer contact');
   * Helper::block()->deleteContent('Footer contact', 'basic');
   * @endcode
   *
   * @param string $info
   *   Block description ('info') to match.
   * @param string|null $bundle
   *   Block content type to match, or NULL to match any bundle.
   *
   * @return int
   *   Number of deleted block content entities.
   */
  public function deleteContent(string $info, ?string $bundle = NULL): int {
    $this->requireEntityType('block_content');
    $storage = $this->entityTypeManager->getStorage('block_content');

    $properties = ['info' => $info];

    if ($bundle !== NULL) {
      $properties['type'] = $bundle;
    }

    $blocks = $storage->loadByProperties($properties);

    if (empty($blocks)) {
      return 0;
    }

    $storage->delete($blocks);

    $count = count($blocks);
    $this->reporter->deleted($this->t('Deleted @count block content matching "@info".', [
      '@count' => $count,
      '@info' => $info,
    ]), $count);

    return $count;
  }

  /**
   * Derive a deterministic block machine name from theme and plugin.
   *
   * @param string $theme
   *   Theme machine name.
   * @param string $plugin_id
   *   Block plugin ID.
   *
   * @return string
   *   Machine name "{theme}_{plugin}" with unsupported characters collapsed to
   *   underscores.
   */
  protected function deriveId(string $theme, string $plugin_id): string {
    $id = strtolower($theme . '_' . $plugin_id);
    $id = (string) preg_replace('/[^a-z0-9_]+/', '_', $id);

    return trim((string) preg_replace('/_+/', '_', $id), '_');
  }

}
