<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseRolesSeeder;
use InvalidArgumentException;

/**
 * Test concern to quickly prepare a multi-tenant context:
 * - Seeds baseline roles (idempotent)
 * - Sets the tenant session (tenant_id/current_tenant_id)
 * - Authenticates a verified user with tenant_admin role (guard 'web')
 * - Optionally disables only the 'tenant.manager' middleware
 *
 * Usage:
 *   use RefreshDatabase;
 *   use \Tests\Concerns\WithTenantSession;
 *
 *   public function testSomething(): void {
 *       [$tenant, $admin] = $this->actingAsTenantAdmin();
 *       $res = $this->get(route('admin.invitations.index'));
 *       $res->assertOk();
 *   }
 */
trait WithTenantSession
{
  /**
   * Ensure baseline roles exist (idempotent).
   */
  protected function ensureBaseRolesSeeded(): void
  {
    $this->seed(BaseRolesSeeder::class);
  }

  /**
   * Put the current tenant id in session using the keys expected by the app.
   */
  protected function withTenantSession(Tenant|int $tenant): void
  {
    $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

    $this->withSession([
      'tenant_id'         => $tenantId,
      'current_tenant_id' => $tenantId,
    ]);
  }

  /**
   * Disable only the custom tenant manager middleware when needed.
   */
  protected function disableTenantManagerMiddleware(): void
  {
    $this->withoutMiddleware('tenant.manager');
  }

  /**
   * Create (or use) an active tenant and a verified tenant_admin user,
   * set tenant session, optionally disable 'tenant.manager',
   * and authenticate with guard 'web'.
   *
   * @param  Tenant|int|null $tenant  If null, a new active tenant is created.
   * @param  array           $userAttrs Extra attributes for User::factory().
   * @param  bool            $disableTenantManager Whether to bypass only 'tenant.manager'.
   * @return array{0: Tenant, 1: User}
   */
  protected function actingAsTenantAdmin(
    Tenant|int|null $tenant = null,
    array $userAttrs = [],
    bool $disableTenantManager = true,
  ): array {
    $this->ensureBaseRolesSeeded();

    $tenantModel = match (true) {
      $tenant instanceof Tenant => $tenant,
      is_int($tenant)    => Tenant::query()->findOrFail($tenant),
      default                   => Tenant::factory()->active()->create(),
    };

    /** @var User $admin */
    $admin = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenantModel)
      ->asTenantAdmin()
      ->create($userAttrs);

    $this->withTenantSession($tenantModel);
    if ($disableTenantManager) {
      $this->disableTenantManagerMiddleware();
    }

    $this->actingAs($admin, 'web');

    return [$tenantModel, $admin];
  }

  /**
   * Same as actingAsTenantAdmin but uses an existing user (must belong to the tenant).
   *
   * @throws InvalidArgumentException when the user doesn't belong to the tenant.
   */
  protected function actingAsExistingTenantAdmin(User $admin, Tenant|int $tenant, bool $disableTenantManager = true): void
  {
    $this->ensureBaseRolesSeeded();

    $tenantModel = $tenant instanceof Tenant ? $tenant : Tenant::query()->findOrFail($tenant);

    if ((int)($admin->tenant_id ?? 0) !== (int)$tenantModel->getKey()) {
      throw new InvalidArgumentException('The given user does not belong to the provided tenant.');
    }

    $this->withTenantSession($tenantModel);
    if ($disableTenantManager) {
      $this->disableTenantManagerMiddleware();
    }

    $this->actingAs($admin, 'web');
  }
}
