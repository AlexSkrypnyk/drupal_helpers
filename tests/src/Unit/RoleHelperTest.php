<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\drupal_helpers\Helpers\Role;
use Drupal\drupal_helpers\Report\Reporter;
use Drupal\user\PermissionHandlerInterface;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Role helper service.
 */
#[CoversClass(Role::class)]
class RoleHelperTest extends TestCase {

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $entityTypeManager;

  /**
   * The messenger mock.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $messenger;

  /**
   * The reporter forwarding to the messenger mock.
   */
  protected Reporter $reporter;

  /**
   * The permission handler mock.
   *
   * @var \Drupal\user\PermissionHandlerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $permissionHandler;

  /**
   * The translation stub.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $translation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->reporter = new Reporter($this->createMock(LoggerChannelInterface::class), $this->messenger);
    $this->permissionHandler = $this->createMock(PermissionHandlerInterface::class);

    $this->translation = $this->createMock(TranslationInterface::class);
    $this->translation->method('translateString')->willReturnCallback(fn($input): string => (string) $input->getUntranslatedString());
  }

  /**
   * Tests that requiredModules returns the user module.
   */
  public function testRequiredModules(): void {
    $this->assertEquals(['user'], $this->createRole()->requiredModules());
  }

  /**
   * Tests creating a new role.
   */
  public function testCreateNew(): void {
    $role = $this->createMock(RoleInterface::class);
    $storage = $this->mockStorage();
    $storage->method('load')->willReturn(NULL);
    $storage->expects($this->once())->method('create')->with(['id' => 'editor', 'label' => 'Editor'])->willReturn($role);
    $role->expects($this->once())->method('save');

    $result = $this->createRole()->create('editor', 'Editor');

    $this->assertSame($role, $result);
  }

  /**
   * Tests that creating an existing role short-circuits without saving.
   */
  public function testCreateExisting(): void {
    $role = $this->createMock(RoleInterface::class);
    $storage = $this->mockStorage();
    $storage->method('load')->willReturn($role);
    $storage->expects($this->never())->method('create');
    $role->expects($this->never())->method('save');

    $result = $this->createRole()->create('editor', 'Editor');

    $this->assertSame($role, $result);
  }

  /**
   * Tests deleting an existing role.
   */
  public function testDelete(): void {
    $role = $this->createMock(RoleInterface::class);
    $storage = $this->mockStorage();
    $storage->method('load')->willReturn($role);
    $role->expects($this->once())->method('delete');

    $this->createRole()->delete('editor');
  }

  /**
   * Tests that deleting an absent role short-circuits.
   */
  public function testDeleteAbsent(): void {
    $storage = $this->mockStorage();
    $storage->method('load')->willReturn(NULL);
    $this->messenger->expects($this->once())->method('addStatus');

    $this->createRole()->delete('ghost');
  }

  /**
   * Tests granting valid permissions to a role.
   */
  public function testGrantPermissions(): void {
    $role = $this->createMock(RoleInterface::class);
    $storage = $this->mockStorage();
    $storage->method('load')->willReturn($role);
    $this->permissionHandler->method('getPermissions')->willReturn([
      'access administration pages' => [],
      'administer users' => [],
    ]);

    $granted = [];
    $role->expects($this->exactly(2))->method('grantPermission')->willReturnCallback(function (string $permission) use (&$granted, $role): RoleInterface {
      $granted[] = $permission;

      return $role;
    });
    $role->expects($this->once())->method('save');

    $result = $this->createRole()->grantPermissions('editor', ['access administration pages', 'administer users']);

    $this->assertSame($role, $result);
    $this->assertEquals(['access administration pages', 'administer users'], $granted);
  }

  /**
   * Tests that an unknown permission is rejected before anything is saved.
   */
  public function testGrantUnknownPermissionThrows(): void {
    $role = $this->createMock(RoleInterface::class);
    $storage = $this->mockStorage();
    $storage->method('load')->willReturn($role);
    $this->permissionHandler->method('getPermissions')->willReturn(['administer users' => []]);
    $role->expects($this->never())->method('grantPermission');
    $role->expects($this->never())->method('save');

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown permission(s): bogus permission');

    $this->createRole()->grantPermissions('editor', ['administer users', 'bogus permission']);
  }

  /**
   * Tests that granting to a missing role throws.
   */
  public function testGrantRoleNotFound(): void {
    $storage = $this->mockStorage();
    $storage->method('load')->willReturn(NULL);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Role "ghost" does not exist.');

    $this->createRole()->grantPermissions('ghost', ['administer users']);
  }

  /**
   * Tests that granting without the permission handler throws.
   */
  public function testGrantWithoutPermissionHandlerThrows(): void {
    $role = $this->createMock(RoleInterface::class);
    $storage = $this->mockStorage();
    $storage->method('load')->willReturn($role);
    $role->expects($this->never())->method('save');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The "user.permissions" service is required to validate permissions.');

    $this->buildRole(NULL)->grantPermissions('editor', ['administer users']);
  }

  /**
   * Tests revoking permissions without validating names.
   */
  public function testRevokePermissions(): void {
    $role = $this->createMock(RoleInterface::class);
    $storage = $this->mockStorage();
    $storage->method('load')->willReturn($role);
    $this->permissionHandler->expects($this->never())->method('getPermissions');

    $revoked = [];
    $role->expects($this->exactly(2))->method('revokePermission')->willReturnCallback(function (string $permission) use (&$revoked, $role): RoleInterface {
      $revoked[] = $permission;

      return $role;
    });
    $role->expects($this->once())->method('save');

    $result = $this->createRole()->revokePermissions('editor', ['access administration pages', 'administer users']);

    $this->assertSame($role, $result);
    $this->assertEquals(['access administration pages', 'administer users'], $revoked);
  }

  /**
   * Tests that revoking from a missing role throws.
   */
  public function testRevokeRoleNotFound(): void {
    $storage = $this->mockStorage();
    $storage->method('load')->willReturn(NULL);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Role "ghost" does not exist.');

    $this->createRole()->revokePermissions('ghost', ['administer users']);
  }

  /**
   * Create a Role helper with the mocked permission handler.
   */
  protected function createRole(): Role {
    return $this->buildRole($this->permissionHandler);
  }

  /**
   * Create a Role helper with a given permission handler.
   *
   * @param \Drupal\user\PermissionHandlerInterface|null $permission_handler
   *   The permission handler, or NULL to simulate the missing service.
   */
  protected function buildRole(?PermissionHandlerInterface $permission_handler): Role {
    $role = new Role($this->entityTypeManager, $this->reporter, $permission_handler);
    $role->setStringTranslation($this->translation);

    return $role;
  }

  /**
   * Wire the entity type manager to return a fresh user_role storage mock.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The storage mock to configure per test.
   */
  protected function mockStorage(): MockObject {
    $storage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->method('getStorage')->with('user_role')->willReturn($storage);

    return $storage;
  }

}
