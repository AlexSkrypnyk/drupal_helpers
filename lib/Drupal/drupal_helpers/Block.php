<?php

namespace Drupal\drupal_helpers;

class Block {
  /**
   * Helper to simply return rendered block.
   *
   * @param string $block_delta
   *   Block delta.
   * @param string $block_module
   *   Block module name.
   * @param bool $renderable_array
   *   TRUE: returns renderable array.
   *   FALSE: returns rendered string.
   */
  public static function render($block_delta, $block_module, $renderable_array = FALSE) {
    $block = block_load($block_module, $block_delta);
    $render = _block_get_renderable_array(_block_render_blocks(array($block)));

    if ($renderable_array) {
      return $render;
    }
    else {
      return render($render);
    }
  }

  /**
   * Helper to place a block in a region using core block module.
   *
   * @param $block_delta
   * @param $block_module
   * @param $region
   * @param $theme
   * @param int $weight
   */
  public static function place($block_delta, $block_module, $region, $theme, $weight = 0) {
    _block_rehash($theme);
    db_update('block')
      ->fields(array(
        'status' => 1,
        'weight' => $weight,
        'region' => $region,
      ))
      ->condition('module', $block_module)
      ->condition('delta', $block_delta)
      ->condition('theme', $theme)
      ->execute();

    \Drupal\drupal_helpers\General::messageSet(format_string('Block "@block_module-@block_delta" successfully added to the "@region" region in "@theme" theme.', array(
      '@block_delta' => $block_delta,
      '@block_module' => $block_module,
      '@region' => $region,
      '@theme' => $theme,
    )));

    drupal_flush_all_caches();
  }

  /**
   * Helper to remove a block from a region using core block module.
   *
   * @param $block_delta
   * @param $block_module
   * @param $theme
   */
  public static function remove($block_delta, $block_module, $theme) {
    _block_rehash($theme);
    db_update('block')
      ->fields(array(
        'status' => 0,
      ))
      ->condition('module', $block_module)
      ->condition('delta', $block_delta)
      ->condition('theme', $theme)
      ->execute();

    \Drupal\drupal_helpers\General::messageSet(format_string('Block "@block_module-@block_delta" successfully remove from "@theme".', array(
      '@block_delta' => $block_delta,
      '@block_module' => $block_module,
      '@theme' => $theme,
    )));

    drupal_flush_all_caches();
  }

  /**
   * Set the block visibility in the core block admin page.
   *
   * @param int $visibility
   *  - BLOCK_VISIBILITY_LISTED
   *  - BLOCK_VISIBILITY_NOTLISTED
   *  - BLOCK_VISIBILITY_PHP
   */
  public static function visibility($block_delta, $block_module, $theme, $pages, $visibility = BLOCK_VISIBILITY_LISTED) {
    _block_rehash($theme);
    db_update('block')
      ->fields(array(
        'visibility' => $visibility,
        'pages' => $pages,
      ))
      ->condition('module', $block_module)
      ->condition('delta', $block_delta)
      ->condition('theme', $theme)
      ->execute();

    \Drupal\drupal_helpers\General::messageSet(format_string('Block "@block_module-@block_delta" successfully configured with visibility rules.', array(
      '@block_delta' => $block_delta,
      '@block_module' => $block_module,
    )));

    drupal_flush_all_caches();
  }
}
