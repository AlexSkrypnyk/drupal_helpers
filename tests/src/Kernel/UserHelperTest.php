<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\drupal_helpers\Helpers\User;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Kernel tests for the User helper service.
 */
#[CoversClass(User::class)]
#[RunTestsInSeparateProcesses]
class UserHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_helpers', 'system', 'user'];

  /**
   * The user helper service.
   */
  protected User $userHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['user']);

    // Create 'editor' and 'administrator' roles.
    $role_storage = $this->container->get('entity_type.manager')->getStorage('user_role');
    $role_storage->create(['id' => 'editor', 'label' => 'Editor'])->save();
    $role_storage->create(['id' => 'administrator', 'label' => 'Administrator'])->save();

    $this->userHelper = $this->container->get('drupal_helpers.user');
  }

  /**
   * Tests that requiredModules returns an empty array.
   */
  public function testRequiredModules(): void {
    $this->assertEquals([], $this->userHelper->requiredModules());
  }

  /**
   * Tests creating a user with email and roles.
   */
  public function testCreate(): void {
    $user = $this->userHelper->create('admin@example.com', ['editor', 'administrator']);

    $this->assertNotNull($user->id());
    $this->assertEquals('admin@example.com', $user->getEmail());
    $this->assertEquals('admin@example.com', $user->getAccountName());
    $this->assertEquals(1, (int) $user->get('status')->value);
    $this->assertTrue($user->hasRole('editor'));
    $this->assertTrue($user->hasRole('administrator'));
  }

  /**
   * Tests creating a user with custom name and inactive status.
   */
  public function testCreateWithCustomFields(): void {
    $user = $this->userHelper->create('custom@example.com', [], [
      'name' => 'custom_user',
      'status' => 0,
    ]);

    $this->assertEquals('custom_user', $user->getAccountName());
    $this->assertEquals(0, (int) $user->get('status')->value);
    $this->assertEquals('custom@example.com', $user->getEmail());
  }

  /**
   * Tests creating multiple users at once.
   */
  public function testCreateMultiple(): void {
    $users = $this->userHelper->createMultiple([
      'user1@example.com',
      'user2@example.com',
      'user3@example.com',
    ], ['editor']);

    $this->assertCount(3, $users);

    foreach ($users as $user) {
      $this->assertTrue($user->hasRole('editor'));
    }

    $this->assertEquals('user1@example.com', $users[0]->getEmail());
    $this->assertEquals('user2@example.com', $users[1]->getEmail());
    $this->assertEquals('user3@example.com', $users[2]->getEmail());
  }

  /**
   * Tests assigning roles to an existing user.
   */
  public function testAssignRoles(): void {
    $user = $this->userHelper->create('noroles@example.com');

    $this->assertFalse($user->hasRole('editor'));
    $this->assertFalse($user->hasRole('administrator'));

    $this->userHelper->assignRoles('noroles@example.com', ['editor', 'administrator']);

    // Reload the user entity.
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $storage->resetCache([$user->id()]);
    $reloaded = $storage->load($user->id());

    $this->assertTrue($reloaded->hasRole('editor'));
    $this->assertTrue($reloaded->hasRole('administrator'));
  }

  /**
   * Tests assigning roles to a non-existent user produces a warning.
   */
  public function testAssignRolesUserNotFound(): void {
    $this->userHelper->assignRoles('ghost@example.com', ['editor']);

    $messages = $this->container->get('messenger')->messagesByType('warning');
    $this->assertNotEmpty($messages);
  }

  /**
   * Tests removing roles from an existing user.
   */
  public function testRemoveRoles(): void {
    $user = $this->userHelper->create('roled@example.com', ['editor', 'administrator']);

    $this->assertTrue($user->hasRole('editor'));
    $this->assertTrue($user->hasRole('administrator'));

    $this->userHelper->removeRoles('roled@example.com', ['editor', 'administrator']);

    // Reload the user entity.
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $storage->resetCache([$user->id()]);
    $reloaded = $storage->load($user->id());

    $this->assertFalse($reloaded->hasRole('editor'));
    $this->assertFalse($reloaded->hasRole('administrator'));
  }

  /**
   * Tests removing roles from a non-existent user produces a warning.
   */
  public function testRemoveRolesUserNotFound(): void {
    $this->userHelper->removeRoles('phantom@example.com', ['editor']);

    $messages = $this->container->get('messenger')->messagesByType('warning');
    $this->assertNotEmpty($messages);
  }

}
