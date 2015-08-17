<?php
/**
 * @file
 * User-related helpers.
 */

namespace Drupal\drupal_helpers;

class User {
  /**
   * Helper to create user with specified fields and roles.
   *
   * @param array $editOverrides
   *   Array of user override fields. Value of an element with a 'mail' key
   *   is required.
   *
   * @param array $roleNames
   *   Optional array of role names to be assigned.
   *
   * @return bool|Object
   *   User account object or FALSE if user was not created.
   */
  public static function create($editOverrides, $roleNames = array()) {
    // Mail is an absolute minimum that we require.
    if (!isset($editOverrides['mail'])) {
      return FALSE;
    }

    $edit['mail'] = Random::email();
    $edit['name'] = $edit['mail'];
    $edit['pass'] = user_password();
    $edit['status'] = 1;
    $edit['roles'] = array();
    if (!empty($roleNames)) {
      $roleNames = is_array($roleNames) ? $roleNames : array($roleNames);
      foreach ($roleNames as $role_name) {
        $role = user_role_load_by_name($role_name);
        $edit['roles'][$role->rid] = $role->rid;
      }
    }

    // Merge fields with provided $edit_overrides.
    $edit = array_merge($edit, $editOverrides);

    // Build an empty user object, including all default fields.
    $account = drupal_anonymous_user();
    foreach (field_info_instances('user', 'user') as $field_name => $info) {
      if (!isset($account->{$field_name})) {
        $account->{$field_name} = array();
      }
    }

    $account = user_save($account, $edit);

    if (!$account && empty($account->uid)) {
      return FALSE;
    }

    // Add raw password just in case if we need to login with this user.
    $account->pass_raw = $edit['pass'];

    return $account;
  }
}
