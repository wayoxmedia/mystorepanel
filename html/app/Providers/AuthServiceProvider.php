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
    // Define an ability to ensure users act only within their tenant.
    Gate::define('act-on-tenant', function (User $user, int $tenantId): bool {
      return (int)$user->tenant_id === (int)$tenantId;
    });
  }
}
