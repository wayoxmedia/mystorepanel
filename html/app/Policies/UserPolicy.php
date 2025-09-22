<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * UserPolicy
 *
 * Purpose:
 * - Encapsulate fine-grained, per-resource authorization rules for User model.
 * - Complements middleware (auth/tenant/role/reauth) with business rules that
 *   depend on the *target* resource (e.g., platform_super_admin protection).
 *
 * Conventions:
 * - We assume your User model has the HasTenantRoles trait with:
 *     - getRoleCode(): string|null
 *     - isPlatformSuperAdmin(): bool
 */
class UserPolicy
{
  use HandlesAuthorization;

  /** Role slugs */
  private const R_PLATFORM_SUPER_ADMIN = 'platform_super_admin';
  private const R_TENANT_OWNER = 'tenant_owner';
  private const R_TENANT_ADMIN = 'tenant_admin';
  private const R_TENANT_EDITOR = 'tenant_editor';
  private const R_TENANT_VIEWER = 'tenant_viewer';

  /** Cache role_id lookups */
  private static array $roleIdCache = [];

  /**
   * "Before" hook:
   * - If the actor is platform_super_admin, allow everything by default.
   */
  public function before(User $actor): ?bool
  {
    return $actor->isPlatformSuperAdmin() ? true : null;
  }

  /**
   * Create a user in the given tenant.
   * Only within the same tenant.
   * @param  User  $actor
   * @param  Tenant  $tenant
   * @return boolean
   */
  public function create(User $actor, Tenant $tenant): bool
  {
    if (!$this->sameTenant($actor, $tenant)) {
      return false;
    }
    return $this->hasAnyRole(
      $actor,
      [self::R_TENANT_OWNER, self::R_TENANT_ADMIN]
    );
  }

  /**
   * Update the *role* of a target user.
   * Only within the same tenant.
   * @param  User  $actor
   * @param  User  $target
   * @return boolean
   */
  public function updateRole(User $actor, User $target): bool
  {
    if (!$this->sameTenant($actor, $target)) {
      return false;
    }
    return $this->roleChecks($actor, $target);
  }

  /**
   * Update the *status* (activate/lock/suspend) of a target user.
   * Same rules as updateRole.
   * @param  User  $actor
   * @param  User  $target
   * @return boolean
   */
  public function updateStatus(User $actor, User $target): bool
  {
    // Same semantics as updateRole
    return $this->updateRole($actor, $target);
  }

  /**
   * Impersonate a target user.
   * Allow cross-tenant only for platform super admins.
   * @param  User  $actor
   * @param  User  $target
   * @return boolean
   */
  public function impersonate(User $actor, User $target): bool
  {
    if (!$this->sameTenant($actor, $target)) {
      return $this->hasRole(
        $actor,
        self::R_PLATFORM_SUPER_ADMIN
      ); // platform admin can cross-tenant
    }
    return $this->roleChecks($actor, $target);
  }

  /**
   *  Helpers
   */

  /**
   * Check if actor and other (User or Tenant) belong to the same tenant.
   * @param  User  $actor
   * @param  User|Tenant  $other
   * @return bool
   */
  private function sameTenant(User $actor, User|Tenant $other): bool
  {
    $otherTenantId = $other instanceof Tenant ? $other->id : $other->tenant_id;
    return (int)$actor->tenant_id === (int)$otherTenantId;
  }

  /**
   * Check if the user has the specified role by slug.
   * @param  User  $user
   * @param  string  $slug
   * @return bool
   */
  private function hasRole(User $user, string $slug): bool
  {
    $rid = $this->roleId($slug);
    return $user->role_id === $rid;
  }

  /**
   * Check if the user has any of the specified roles by slugs.
   * @param  User  $user
   * @param  array  $slugs
   * @return bool
   */
  private function hasAnyRole(User $user, array $slugs): bool
  {
    $ids = array_map(fn(string $s) => $this->roleId($s), $slugs);
    return in_array($user->role_id, $ids);
  }

  /**
   * Get role id by slug, with caching.
   * @param  string  $slug
   * @return int
   * @throws ModelNotFoundException
   */
  private function roleId(string $slug): int
  {
    if (!isset(self::$roleIdCache[$slug])) {
      $id = DB::table('roles')
        ->where('slug', $slug)
        ->value('id');
      self::$roleIdCache[$slug] = (int)$id;
    }
    return self::$roleIdCache[$slug];
  }

  /**
   * Shared logic for updateRole and impersonate.
   * @param  User  $actor
   * @param  User  $target
   * @return boolean
   */
  private function roleChecks(User $actor, User $target): bool
  {
    if ($actor->id === $target->id) {
      return false; // no self-escalation
    }
    if ($this->hasRole($target, self::R_PLATFORM_SUPER_ADMIN)) {
      return false;
    }

    // Owner can manage/impersonate anyone in-tenant except platform admins.
    if ($this->hasRole($actor, self::R_TENANT_OWNER)) {
      return true;
    }

    // Admin can manage only lower-privileged users (viewer/editor)
    if ($this->hasRole($actor, self::R_TENANT_ADMIN)) {
      return $this->hasAnyRole(
        $target,
        [self::R_TENANT_EDITOR, self::R_TENANT_VIEWER]
      );
    }

    return false;
  }
}
