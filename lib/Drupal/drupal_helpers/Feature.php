<?php

namespace Drupal\drupal_helpers;

/**
 * Class Feature.
 *
 * @package Drupal\drupal_helpers
 */
class Feature extends Module {

  /**
   * Reverts a feature.
   *
   * @param string $module
   *   Machine name of the feature to revert.
   * @param string $component
   *   Name of an individual component to revert. Defaults to empty component
   *   name to trigger all components revert.
   */
  public static function revert($module, $component = '') {
    module_load_include('inc', 'features', 'features.export');
    features_include();

    if (!module_exists($module)) {
      throw new DrupalHelpersException('Module "@module" does not exist', ['@module' => $module]);
    }

    $feature = feature_load($module, TRUE);
    if (!$feature) {
      throw new DrupalHelpersException('Unable to load features from module "@module"', ['@module' => $module]);
    }

    $components = [];
    if (empty($component)) {
      // Forcefully revert all components of a feature.
      foreach (array_keys($feature->info['features']) as $component) {
        if (features_hook($component, 'features_revert')) {
          $components[] = $component;
        }
      }
    }
    else {
      // Revert only specified component.
      $components[] = $component;
    }

    foreach ($components as $component) {
      features_revert([$module => [$component]]);
    }

    Utility::message('Reverted "@module" feature components @components.', [
      '@module' => $module,
      '@components' => implode(', ', $components),
    ]);
  }

}
