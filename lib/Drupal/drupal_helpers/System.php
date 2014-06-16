<?php

namespace Drupal\drupal_helpers;

class System {
  /**
   * Retrieves the weight of a module, theme or profile from the system table.
   *
   * @param string $name
   *  Machine name of module, theme or profile.
   * @param string $type
   *  Item type as it appears in 'type' column in system table. Typical values:
   *  - 'module'
   *  - 'theme'
   *  - 'profile'
   *
   * @return int
   *  Weight of the specified item.
   */
  public function weightGet($name, $type = 'module') {
    return db_query("SELECT weight FROM {system} WHERE name = :name AND type = :type", array(
      ':name' => $name,
      ':type' => $type,
    ))->fetchField();
  }

  /**
   * Updates the weight of a module, theme or profile in the system table.
   *
   * @param string $name
   *  Machine name of module, theme or profile.
   * @param int $weight
   *  Weight value to set.
   */
  public function weightSet($name, $weight) {
    db_update('system')->fields(array('weight' => $weight))
      ->condition('name', $name)->execute();
  }

  /**
   * Checks the status of a module, theme or profile in the system table.
   *
   * @param string $name
   *  Machine name of module, theme or profile.
   * @param string $type
   *  Item type as it appears in 'type' column in system table. Typical values:
   *  - 'module'
   *  - 'theme'
   *  - 'profile'
   *
   * @return bool
   *  - TRUE: item is enabled.
   *  - FALSE: item is disabled.
   */
  public function isEnabled($name, $type = 'module') {
    $q = db_select('system');
    $q->fields('system', array('name', 'status'))
      ->condition('name', $name, '=')
      ->condition('type', $type, '=');
    $rs = $q->execute();
    return (bool) $rs->fetch()->status;
  }

  /**
   * Checks the status of a module, theme or profile in the system table.
   *
   * @param string $name
   *  Machine name of module, theme or profile.
   * @param string $type
   *  Item type as it appears in 'type' column in system table. Typical values:
   *  - 'module'
   *  - 'theme'
   *  - 'profile'
   *
   * @return bool
   *  - FALSE: item is enabled.
   *  - TRUE: item is disabled.
   */
  public function isDisabled($name, $type = 'module') {
    $reflector = new \ReflectionClass(get_called_class());
    return !$reflector::isEnabled($name, $type);
  }
}
