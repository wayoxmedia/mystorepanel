<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

trait RoleHelpers
{
  /**
   * Resolve a role id by slug (throws if not found).
   * @param string $slug
   * @throws InvalidArgumentException
   * @return integer
   */
  protected function roleId(string $slug): int
  {
    $id = DB::table('roles')->where('slug', $slug)->value('id');
    if (!$id) {
      throw new InvalidArgumentException(
        sprintf(
          'Role slug "%s" not found in roles table. Did you run BaseRolesSeeder?',
          $slug
        )
      );
    }
    return (int) $id;
  }

  /**
   * Attach a single role to the user via the role_user pivot.
   *
   * Idempotent: does nothing if the role is already attached.
   * @param Authenticatable|Model $user
   * @param string $slug
   * @return void
   * @throws InvalidArgumentException
   */
  protected function attachRoleBySlug(Authenticatable|Model $user, string $slug): void
  {
    $uid = (int) $this->extractUserId($user);
    $rid = $this->roleId($slug);

    DB::table('role_user')->updateOrInsert(
      ['role_id' => $rid, 'user_id' => $uid],
      [] // idempotent: no extra columns to update
    );
  }

  /**
   * Attach multiple roles (array of slugs) to the user.
   *
   * Idempotent: does nothing if a role is already attached.
   * @param Authenticatable|Model $user
   * @param array $slugs
   * @return void
   * @throws InvalidArgumentException
   */
  protected function attachRolesBySlug(Authenticatable|Model $user, array $slugs): void
  {
    foreach ($slugs as $slug) {
      $this->attachRoleBySlug($user, (string) $slug);
    }
  }

  /**
   * Quick check helper for assertions.
   * @param Authenticatable|Model $user
   * @param string $slug
   * @return boolean
   * @throws InvalidArgumentException
   */
  protected function userHasRoleSlug(Authenticatable|Model $user, string $slug): bool
  {
    $uid = (int) $this->extractUserId($user);
    $rid = $this->roleId($slug);

    return DB::table('role_user')
      ->where('user_id', $uid)
      ->where('role_id', $rid)
      ->exists();
  }

  /**
   * Extract primary key from Authenticatable|Model|int.
   * @param Authenticatable|Model|integer $user
   * @return integer
   * @throws InvalidArgumentException
   */
  private function extractUserId(Authenticatable|Model|int $user): int
  {
    if (is_int($user)) {
      return $user;
    }
    if ($user instanceof Model) {
      return (int) $user->getKey();
    }
    if (method_exists($user, 'getAuthIdentifier')) {
      return (int) $user->getAuthIdentifier();
    }
    throw new InvalidArgumentException('Unable to resolve user id from given value.');
  }
}
