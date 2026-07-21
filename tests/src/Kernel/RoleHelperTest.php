<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use Drupal\drupal_helpers\Helpers\Role;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the Role helper service.
 */
#[CoversClass(Role::class)]
#[RunTestsInSeparateProcesses]
class RoleHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system', 'user'];

  /**
   * The role helper service.
   */
  protected Role $roleHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['user']);

    $this->roleHelper = $this->container->get('drupal_helpers.role');
  }

  /**
   * Tests that requiredModules returns the user module.
   */
  public function testRequiredModules(): void {
    $this->assertEquals(['user'], $this->roleHelper->requiredModules());
  }

  /**
   * Tests creating a role.
   */
  public function testCreate(): void {
    $role = $this->roleHelper->create('editor', 'Editor');

    $this->assertEquals('editor', $role->id());
    $this->assertEquals('Editor', $role->label());
    $this->assertInstanceOf(RoleInterface::class, $this->loadRole('editor'));
  }

  /**
   * Tests that creating an existing role is a no-op returning the existing one.
   */
  public function testCreateExisting(): void {
    $first = $this->roleHelper->create('editor', 'Editor');
    $second = $this->roleHelper->create('editor', 'Ignored label');

    $this->assertEquals($first->id(), $second->id());
    $this->assertEquals('Editor', $this->loadRole('editor')->label());
    $this->assertStatusMessageContains('already exists');
  }

  /**
   * Tests deleting a role.
   */
  public function testDelete(): void {
    $this->roleHelper->create('editor', 'Editor');

    $this->roleHelper->delete('editor');

    $this->assertNull($this->loadRole('editor'));
  }

  /**
   * Tests that deleting an absent role is a no-op.
   */
  public function testDeleteAbsent(): void {
    $this->roleHelper->delete('ghost');

    $this->assertNull($this->loadRole('ghost'));
    $this->assertStatusMessageContains('does not exist');
  }

  /**
   * Tests granting permissions to a role.
   */
  public function testGrantPermissions(): void {
    $this->roleHelper->create('editor', 'Editor');

    $this->roleHelper->grantPermissions('editor', ['access administration pages', 'administer users']);

    $reloaded = $this->loadRole('editor');
    $this->assertTrue($reloaded->hasPermission('access administration pages'));
    $this->assertTrue($reloaded->hasPermission('administer users'));
  }

  /**
   * Tests that an unknown permission is rejected and nothing is persisted.
   */
  public function testGrantUnknownPermissionRejected(): void {
    $this->roleHelper->create('editor', 'Editor');

    try {
      $this->roleHelper->grantPermissions('editor', ['administer users', 'not a real permission']);
      $this->fail('Expected an InvalidArgumentException.');
    }
    catch (\InvalidArgumentException $exception) {
      $this->assertStringContainsString('Unknown permission(s): not a real permission', $exception->getMessage());
    }

    // The valid permission in the same call must not leak in either.
    $reloaded = $this->loadRole('editor');
    $this->assertFalse($reloaded->hasPermission('administer users'));
    $this->assertFalse($reloaded->hasPermission('not a real permission'));
  }

  /**
   * Tests that granting to a missing role throws.
   */
  public function testGrantRoleNotFound(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Role "ghost" does not exist.');

    $this->roleHelper->grantPermissions('ghost', ['administer users']);
  }

  /**
   * Tests revoking permissions from a role.
   */
  public function testRevokePermissions(): void {
    $this->roleHelper->create('editor', 'Editor');
    $this->roleHelper->grantPermissions('editor', ['access administration pages', 'administer users']);

    $this->roleHelper->revokePermissions('editor', ['administer users']);

    $reloaded = $this->loadRole('editor');
    $this->assertTrue($reloaded->hasPermission('access administration pages'));
    $this->assertFalse($reloaded->hasPermission('administer users'));
  }

  /**
   * Tests that revoking an unknown permission is a lenient no-op.
   */
  public function testRevokeUnknownPermissionLenient(): void {
    $this->roleHelper->create('editor', 'Editor');

    $role = $this->roleHelper->revokePermissions('editor', ['not a real permission']);

    $this->assertFalse($role->hasPermission('not a real permission'));
  }

  /**
   * Tests that revoking from a missing role throws.
   */
  public function testRevokeRoleNotFound(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Role "ghost" does not exist.');

    $this->roleHelper->revokePermissions('ghost', ['administer users']);
  }

  /**
   * Load a role by machine name, bypassing the static cache.
   *
   * @param string $id
   *   Role machine name.
   *
   * @return \Drupal\user\RoleInterface|null
   *   The role entity or NULL when absent.
   */
  protected function loadRole(string $id): ?RoleInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('user_role');
    $storage->resetCache([$id]);

    return $storage->load($id);
  }

  /**
   * Assert that a status message containing the given text was set.
   *
   * @param string $needle
   *   Substring to search for across all status messages.
   */
  protected function assertStatusMessageContains(string $needle): void {
    $messages = $this->container->get('messenger')->messagesByType('status');
    $combined = implode("\n", array_map(strval(...), $messages));

    $this->assertStringContainsString($needle, $combined);
  }

}
