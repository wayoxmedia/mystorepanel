<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/**
 * TenantPolicy - Initial version
 *
 * Purpose:
 * Authorize access to Tenant management actions. In early development,
 * we allow authenticated users by default to avoid blocking workflows.
 * Harden this policy before going live.
 *
 * Assumptions:
 * - Users table has 'status' with 'active'.
 * - Future: integrate with your real permission system (roles, abilities).
 *
 * Notes:
 * - Replace the `isDevAllowed()` check with real permission logic (e.g., roles/abilities).
 * - Keep suspend/resume separate for clarity around state transitions.
 */
class TenantPolicy
{
  /**
   * Allow listing tenants.
   *
   * @param  User  $user
   * @return bool
   */
  public function viewAny(User $user): bool
  {
    return $this->isDevAllowed($user);
  }

  /**
   * Allow viewing a single tenant.
   *
   * @param  User    $user
   * @param  Tenant  $tenant
   * @return bool
   */
  public function view(User $user, Tenant $tenant): bool
  {
    return $this->isDevAllowed($user);
  }

  /**
   * Allow creating tenants.
   *
   * @param  User  $user
   * @return bool
   */
  public function create(User $user): bool
  {
    return $this->isDevAllowed($user);
  }

  /**
   * Allow updating tenants.
   *
   * @param  User    $user
   * @param  Tenant  $tenant
   * @return bool
   */
  public function update(User $user, Tenant $tenant): bool
  {
    return $this->isDevAllowed($user);
  }

  /**
   * Allow deleting tenants (soft delete).
   *
   * @param  User    $user
   * @param  Tenant  $tenant
   * @return bool
   */
  public function delete(User $user, Tenant $tenant): bool
  {
    return $this->isDevAllowed($user);
  }

  /**
   * Allow suspending an active tenant.
   *
   * @param  User    $user
   * @param  Tenant  $tenant
   * @return bool
   */
  public function suspend(User $user, Tenant $tenant): bool
  {
    return $this->isDevAllowed($user);
  }

  /**
   * Allow resuming a suspended/pending tenant.
   *
   * @param  User    $user
   * @param  Tenant  $tenant
   * @return bool
   */
  public function resume(User $user, Tenant $tenant): bool
  {
    return $this->isDevAllowed($user);
  }

  /**
   * Dev-only permissive rule (to be replaced).
   *
   * @param  User  $user
   * @return bool
   */
  private function isDevAllowed(User $user): bool
  {
    // TODO: Replace with real permission checks (roles/abilities).
    return $user !== null && ($user->status ?? 'active') === 'active';
  }
}
