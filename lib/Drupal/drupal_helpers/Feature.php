<?php
/**
 * @file
 * Features helpers.
 */

namespace Drupal\drupal_helpers;

/**
 * Class Feature.
 *
 * @package Drupal\drupal_helpers
 */
class Feature extends \Drupal\drupal_helpers\Module {
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

    if (($feature = feature_load($module, TRUE)) && module_exists($module)) {
      $components = array();
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
        features_revert(array($module => array($component)));
      }

      \Drupal\drupal_helpers\General::messageSet(t('Reverted "!module" feature components !components.', array(
        '!module' => $module,
        '!components' => implode(', ', $components),
      )));
    }
    else {
      \Drupal\drupal_helpers\General::messageSet(t('Unable to revert "!module" feature.', array('!module' => $module)));
    }
  }

}
