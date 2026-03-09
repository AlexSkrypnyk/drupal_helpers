<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Helpers;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\user\UserInterface;

/**
 * User helpers for deploy hooks.
 */
class User extends HelperBase {

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    MessengerInterface $messenger,
    protected PasswordGeneratorInterface $passwordGenerator,
  ) {
    parent::__construct($entityTypeManager, $messenger);
  }

  /**
   * Create a user account.
   *
   * @code
   * Helper::user()->create('admin@example.com', ['administrator']);
   * Helper::user()->create('editor@example.com', ['editor'], [
   *   'name' => 'editor1',
   *   'status' => 1,
   * ]);
   * @endcode
   *
   * @param string $email
   *   Email address (also used as username if name not provided).
   * @param array $roles
   *   Array of role machine names.
   * @param array $fields
   *   Additional field values to set on the user entity.
   *
   * @return \Drupal\user\UserInterface
   *   Created user entity.
   */
  public function create(string $email, array $roles = [], array $fields = []): UserInterface {
    $storage = $this->entityTypeManager->getStorage('user');

    $values = [
      'mail' => $email,
      'name' => $fields['name'] ?? $email,
      'pass' => $fields['pass'] ?? $this->passwordGenerator->generate(),
      'status' => $fields['status'] ?? 1,
    ] + $fields;

    unset($values['roles']);

    /** @var \Drupal\user\UserInterface $user */
    $user = $storage->create($values);

    foreach ($roles as $role) {
      $user->addRole($role);
    }

    $user->save();

    $this->messenger->addStatus($this->t('Created user "@name" (uid: @uid).', [
      '@name' => $user->getAccountName(),
      '@uid' => $user->id(),
    ]));

    return $user;
  }

  /**
   * Create multiple user accounts.
   *
   * @code
   * Helper::user()->createMultiple([
   *   'user1@example.com',
   *   'user2@example.com',
   * ], ['editor']);
   * @endcode
   *
   * @param array $emails
   *   Array of email addresses.
   * @param array $roles
   *   Array of role machine names to assign to all users.
   * @param array $fields
   *   Additional field values for all users.
   *
   * @return \Drupal\user\UserInterface[]
   *   Array of created user entities.
   */
  public function createMultiple(array $emails, array $roles = [], array $fields = []): array {
    $users = [];

    foreach ($emails as $email) {
      $users[] = $this->create($email, $roles, $fields);
    }

    return $users;
  }

  /**
   * Assign roles to an existing user.
   *
   * @code
   * Helper::user()->assignRoles('admin@example.com', ['administrator']);
   * @endcode
   *
   * @param string $user_identifier
   *   Email address or username.
   * @param array $roles
   *   Array of role machine names to add.
   */
  public function assignRoles(string $user_identifier, array $roles): void {
    $user = $this->findUser($user_identifier);

    if ($user === NULL) {
      $this->messenger->addWarning($this->t('User "@identifier" not found — skipped.', [
        '@identifier' => $user_identifier,
      ]));

      return;
    }

    foreach ($roles as $role) {
      $user->addRole($role);
    }

    $user->save();

    $this->messenger->addStatus($this->t('Assigned @count roles to "@name".', [
      '@count' => count($roles),
      '@name' => $user->getAccountName(),
    ]));
  }

  /**
   * Remove roles from an existing user.
   *
   * @code
   * Helper::user()->removeRoles('admin@example.com', ['administrator']);
   * @endcode
   *
   * @param string $user_identifier
   *   Email address or username.
   * @param array $roles
   *   Array of role machine names to remove.
   */
  public function removeRoles(string $user_identifier, array $roles): void {
    $user = $this->findUser($user_identifier);

    if ($user === NULL) {
      $this->messenger->addWarning($this->t('User "@identifier" not found — skipped.', [
        '@identifier' => $user_identifier,
      ]));

      return;
    }

    foreach ($roles as $role) {
      $user->removeRole($role);
    }

    $user->save();

    $this->messenger->addStatus($this->t('Removed @count roles from "@name".', [
      '@count' => count($roles),
      '@name' => $user->getAccountName(),
    ]));
  }

  /**
   * Find a user by email or username.
   *
   * @param string $user_identifier
   *   Email address or username.
   *
   * @return \Drupal\user\UserInterface|null
   *   User entity or NULL if not found.
   */
  protected function findUser(string $user_identifier): ?UserInterface {
    $storage = $this->entityTypeManager->getStorage('user');

    // Try by email first.
    $users = $storage->loadByProperties(['mail' => $user_identifier]);
    if ($users) {
      return reset($users);
    }

    // Try by name.
    $users = $storage->loadByProperties(['name' => $user_identifier]);

    return $users ? reset($users) : NULL;
  }

}
