<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

/**
 * Authorization service provider.
 * Define gates and policies here.
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
    // \App\Models\Page::class => \App\Policies\PagePolicy::class,
  ];

  /**
   * Register any authentication / authorization services.
   */
  public function boot(): void
  {
    // Optional: global bypass for Platform Super Admin
    Gate::before(function (User $user, string $ability) {
      return $user->isPlatformSuperAdmin() ? true : null;
    });

    // Define an ability to ensure users act only within their tenant.
    Gate::define('act-on-tenant', function (User $user, int $tenantId): bool {
      return (int)$user->tenant_id === (int)$tenantId;
    });

    // Administer users platform level only (superadmin only).
    Gate::define('manage-platform-users', function (User $user): bool {
      return $user->isPlatformSuperAdmin();
    });

    // Administer users within their tenant (owner/admin).
    Gate::define('manage-tenant-users', function (User $user, ?int $tenantId = null): bool {
      $isTenantManager = $user->hasAnyRole(['tenant_owner', 'tenant_admin']);
      $sameTenant = $tenantId ? ((int)$user->tenant_id === (int)$tenantId) : true;
      return $isTenantManager && $sameTenant;
    });

    // Impersonate other user.
    Gate::define('impersonate-user', function (User $actor, User $target): bool {
      // No sense impersonate yourself.
      if ($actor->id === $target->id) return false;

      // If superadmin, you can impersonate anyone (Gate::before allows it).
      if ($actor->isPlatformSuperAdmin()) return true;

      // Tenant Owner/Admin can impersonate ONLY users within their tenant.
      if (! $actor->hasAnyRole(['tenant_owner', 'tenant_admin'])) return false;
      if ((int)$actor->tenant_id !== (int)$target->tenant_id) return false;

      // Don't allow impersonating a Platform Super Admin if you are not one
      if ($target->isPlatformSuperAdmin()) return false;

      return true;
    });
  }
}
