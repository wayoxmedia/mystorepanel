<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

/**
 * Authorization service provider.
 *
 * Define gates and policies here.
 *
 * How to use:
 *
 *
 * Controllers (Make actions): Soft rules with policies:
 *
 * - this->authorize('create', [User::class, $tenant])
 * - this->authorize('updateRole', $target)
 * - this->authorize('updateStatus', $target)
 * - this->authorize('impersonate', $target)
 *
 * UI (show buttons): Hard rules with gates:
 * @can('manage-user', $target) // shows user management section.
 * @can('manage-user-roles', $target)
 * @can('manage-user-status', $target) // show specific status buttons.
 */
class AuthServiceProvider extends ServiceProvider
{
  /**
   * The model to policy mappings for the application.
   * Add your policies here if/when you create them.
   *
   * @var array<class-string, class-string>
   */
  protected $policies = [
    User::class => UserPolicy::class,
    // \App\Models\Page::class => \App\Policies\PagePolicy::class,
  ];

  /**
   * Register any authentication / authorization services.
   */
  public function boot(): void
  {
    $this->registerPolicies();

    // Optional: global bypass for Platform Super Admin
    Gate::before(function (User $user, string $ability = null) {
      return $user->isPlatformSuperAdmin() ? true : null;
    });

    // Define an ability to ensure users act only within their tenant.
    Gate::define(
      'act-on-tenant',
      function (User $user, int $tenantId): bool {
        return (int) $user->tenant_id === $tenantId;
      });

    /**
     * Can I manage this user at all?
     *
     * Used to display management buttons in Blade: @can('manage-user', $target))
     */
    Gate::define(
      'manage-user',
      function (User $actor, User $target): bool {
        // Never touch a Platform SA (if you are not one).
        if ($target->isPlatformSuperAdmin()) {
          return false;
        }
        // Tenant Owner/Admin can manage users of their own tenant.
        if ($actor->hasAnyRole(['tenant_owner','tenant_admin'])) {
          return (int) $actor->tenant_id === (int) $target->tenant_id;
        }
        return false;
    });

    /**
     * Can I manage user roles?
     * Same as manage-user for now.
     */
    Gate::define(
      'manage-user-roles',
      function (User $actor, User $target): bool {
        // Don't touch Platform SA roles.
        if ($target->isPlatformSuperAdmin()) {
          return false;
        }
        if ($actor->hasAnyRole(['tenant_owner','tenant_admin'])) {
          return (int) $actor->tenant_id === (int) $target->tenant_id;
        }
        return false;
    });

    /**
     * Can I manage user status? (activate/suspend/lock/delete)?
     */
    Gate::define(
      'manage-user-status',
      function (User $actor, User $target): bool {
        // No tocar Platform SA ni a sí mismo
        if ($target->isPlatformSuperAdmin()) {
          return false;
        }
        if ($actor->id === $target->id) {
          return false;
        }
        if ($actor->hasAnyRole(['tenant_owner','tenant_admin'])) {
          return (int) $actor->tenant_id === (int) $target->tenant_id;
        }
        return false;
    });

    // Administer users platform level only (superadmin only).
    Gate::define(
      'manage-platform-users',
      function (User $user): bool {
        return $user->isPlatformSuperAdmin();
    });

    // Administer users within their tenant (owner/admin).
    Gate::define(
      'manage-tenant-users',
      function (User $user, ?int $tenantId = null): bool {
        $isTenantManager = $user->hasAnyRole(['tenant_owner', 'tenant_admin']);
        $sameTenant = $tenantId ? ((int)$user->tenant_id === $tenantId) : true;
        return $isTenantManager && $sameTenant;
    });

    // Impersonate other user.
    Gate::define(
      'impersonate-user',
      function (User $actor, User $target): bool {
        // No sense impersonate yourself.
        if ($actor->id === $target->id) return false;

        // If superadmin, you can impersonate anyone (Gate::before allows it).
        if ($actor->isPlatformSuperAdmin()) return true;

        // Tenant Owner/Admin can impersonate ONLY users within their tenant.
        if (! $actor->hasAnyRole(['tenant_owner', 'tenant_admin'])) return false;

        // Same tenant only
        if ((int) $actor->tenant_id !== (int) $target->tenant_id) return false;

        // Don't allow impersonating a Platform Super Admin if you are not one
        if ($target->isPlatformSuperAdmin()) return false;

        return true;
    });
  }
}
