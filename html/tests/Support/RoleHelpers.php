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
    $id = DB::table('roles')
      ->where('slug', $slug)
      ->value('id');
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
   * Quick check helper for assertions.
   * @param Authenticatable|Model $user
   * @param string $slug
   * @return boolean
   * @throws InvalidArgumentException
   * @deprecated role_user table does not exist
   */
  protected function userHasRoleSlug(Authenticatable|Model $user, string $slug): bool
  {
    $uid = $this->extractUserId($user);
    $rid = $this->roleId($slug);

    // TODO no user_role table exists, this logic needs to be changed.
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
    throw new InvalidArgumentException(
      'Unable to resolve user id from given value.'
    );
  }
}
