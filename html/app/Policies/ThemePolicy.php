<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Theme;
use App\Models\User;

/**
 * ThemePolicy
 *
 * Purpose:
 * Authorize CRUD operations over per-tenant Themes.
 * In early development we keep it permissive for authenticated active users.
 *
 * Notes:
 * - Replace isDevAllowed() with your real permission logic before go-live.
 */
class ThemePolicy
{
  public function viewAny(User $user): bool
  {
    if ($this->isSA($user)) {
      return true;
    }
    return $this->isTenantManager($user);
  }

  public function view(User $user, Theme $theme): bool
  {
    if ($this->isSA($user)) {
      return true;
    }
    return $this->isTenantManager($user) && $user->tenant_id === $theme->tenant_id;
  }

  public function create(User $user): bool
  {
    if ($this->isSA($user)) {
      return true;
    }
    // For creation, we cannot see the Theme instance yet.
    // We allow tenant managers; the StoreThemeRequest should enforce tenant_id match.
    return $this->isTenantManager($user);
  }

  public function update(User $user, Theme $theme): bool
  {
    if ($this->isSA($user)) {
      return true;
    }
    return $this->isTenantManager($user) && $user->tenant_id === $theme->tenant_id;
  }

  public function delete(User $user, Theme $theme): bool
  {
    if ($this->isSA($user)) {
      return true;
    }
    return $this->isTenantManager($user) && $user->tenant_id === $theme->tenant_id;
  }

  // --- helpers ---

  private function isSA(User $user): bool
  {
    return $user->isPlatformSuperAdmin();
  }

  private function isTenantManager(User $user): bool
  {
    return $user->hasAnyRole(['tenant_owner', 'tenant_admin'])
      && ($user->status ?? 'active') === 'active';
  }
}
