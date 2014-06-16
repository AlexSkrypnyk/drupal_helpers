<?php

namespace Drupal\drupal_helpers;

class Feature extends \Drupal\drupal_helpers\Module {
  /**
   * Reverts a feature.
   *
   * @param string $module
   *  Machine name of the feature to revert.
   * @param $component
   *  Name of an individual component to revert. If NULL, all components are
   *  reverted.
   */
  public function revert($module, $component = NULL) {
    module_load_include('inc', 'features', 'features.export');
    features_include();
    if (($feature = feature_load($module, TRUE)) && module_exists($module)) {
      $components = array();
      if (is_null($component)) {
        // Forcefully revert all components of a feature.
        foreach (array_keys($feature->info['features']) as $component) {
          if (features_hook($component, 'features_revert')) {
            $components[] = $component;
          }
        }
      }
      else {
        // Use the $component argument of this function.
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
