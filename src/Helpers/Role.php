<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\drupal_helpers\Report\Reporter;
use Drupal\user\PermissionHandlerInterface;
use Drupal\user\RoleInterface;

/**
 * Role and permission helpers for deploy hooks.
 *
 * Requires the core 'user' module.
 */
class Role extends HelperBase {

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Reporter $reporter,
    protected ?PermissionHandlerInterface $permissionHandler = NULL,
  ) {
    parent::__construct($entity_type_manager, $reporter);
  }

  /**
   * {@inheritdoc}
   */
  public function requiredModules(): array {
    return ['user'];
  }

  /**
   * Create a user role.
   *
   * @code
   * Helper::role()->create('editor', 'Editor');
   * @endcode
   *
   * @param string $id
   *   Role machine name.
   * @param string $label
   *   Human-readable role label.
   *
   * @return \Drupal\user\RoleInterface
   *   Created role entity, or the existing one when it already exists.
   */
  public function create(string $id, string $label): RoleInterface {
    $storage = $this->entityTypeManager->getStorage('user_role');

    /** @var \Drupal\user\RoleInterface|null $role */
    $role = $storage->load($id);

    if ($role instanceof RoleInterface) {
      $this->reporter->skipped($this->t('Role "@id" already exists - skipped.', [
        '@id' => $id,
      ]));

      return $role;
    }

    /** @var \Drupal\user\RoleInterface $role */
    $role = $storage->create(['id' => $id, 'label' => $label]);
    $role->save();

    $this->reporter->created($this->t('Created role "@id".', ['@id' => $id]));

    return $role;
  }

  /**
   * Delete a user role.
   *
   * @code
   * Helper::role()->delete('editor');
   * @endcode
   *
   * @param string $id
   *   Role machine name.
   */
  public function delete(string $id): void {
    /** @var \Drupal\user\RoleInterface|null $role */
    $role = $this->entityTypeManager->getStorage('user_role')->load($id);

    if (!$role instanceof RoleInterface) {
      $this->reporter->skipped($this->t('Role "@id" does not exist - skipped.', [
        '@id' => $id,
      ]));

      return;
    }

    $role->delete();

    $this->reporter->deleted($this->t('Deleted role "@id".', ['@id' => $id]));
  }

  /**
   * Grant permissions to a role.
   *
   * Unknown permission names are rejected before saving so a typo cannot
   * silently persist a permission that no module defines.
   *
   * @code
   * Helper::role()->grantPermissions('editor', [
   *   'access content overview',
   *   'edit any article content',
   * ]);
   * @endcode
   *
   * @param string $id
   *   Role machine name.
   * @param string[] $permissions
   *   Permission machine names to grant.
   *
   * @return \Drupal\user\RoleInterface
   *   The updated role entity.
   *
   * @throws \InvalidArgumentException
   *   When the role does not exist or a permission name is unknown.
   */
  public function grantPermissions(string $id, array $permissions): RoleInterface {
    $role = $this->loadRole($id);
    $this->assertPermissionsExist($permissions);

    foreach ($permissions as $permission) {
      $role->grantPermission($permission);
    }

    $role->save();

    $this->reporter->updated($this->t('Granted @count permission(s) to role "@id".', [
      '@count' => count($permissions),
      '@id' => $id,
    ]));

    return $role;
  }

  /**
   * Revoke permissions from a role.
   *
   * Permission names are not validated: a permission left behind by an
   * uninstalled module can still be revoked to clean it off the role.
   *
   * @code
   * Helper::role()->revokePermissions('editor', ['edit any article content']);
   * @endcode
   *
   * @param string $id
   *   Role machine name.
   * @param string[] $permissions
   *   Permission machine names to revoke.
   *
   * @return \Drupal\user\RoleInterface
   *   The updated role entity.
   *
   * @throws \InvalidArgumentException
   *   When the role does not exist.
   */
  public function revokePermissions(string $id, array $permissions): RoleInterface {
    $role = $this->loadRole($id);

    foreach ($permissions as $permission) {
      $role->revokePermission($permission);
    }

    $role->save();

    $this->reporter->updated($this->t('Revoked @count permission(s) from role "@id".', [
      '@count' => count($permissions),
      '@id' => $id,
    ]));

    return $role;
  }

  /**
   * Load a role by machine name.
   *
   * @param string $id
   *   Role machine name.
   *
   * @return \Drupal\user\RoleInterface
   *   The role entity.
   *
   * @throws \InvalidArgumentException
   *   When the role does not exist.
   */
  protected function loadRole(string $id): RoleInterface {
    /** @var \Drupal\user\RoleInterface|null $role */
    $role = $this->entityTypeManager->getStorage('user_role')->load($id);

    if (!$role instanceof RoleInterface) {
      throw new \InvalidArgumentException(sprintf('Role "%s" does not exist.', $id));
    }

    return $role;
  }

  /**
   * Assert that every permission name is defined by an installed module.
   *
   * @param string[] $permissions
   *   Permission machine names to check.
   *
   * @throws \InvalidArgumentException
   *   When one or more permission names are unknown.
   * @throws \RuntimeException
   *   When the permission handler service is unavailable.
   */
  protected function assertPermissionsExist(array $permissions): void {
    if (!$this->permissionHandler instanceof PermissionHandlerInterface) {
      throw new \RuntimeException('The "user.permissions" service is required to validate permissions.');
    }

    $unknown = array_diff($permissions, array_keys($this->permissionHandler->getPermissions()));

    if ($unknown !== []) {
      throw new \InvalidArgumentException(sprintf('Unknown permission(s): %s.', implode(', ', $unknown)));
    }
  }

}
