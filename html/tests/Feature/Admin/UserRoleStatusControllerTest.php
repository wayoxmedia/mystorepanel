<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\WithTenantSession;
use Tests\TestCase;

class UserRoleStatusControllerTest extends TestCase
{
  use RefreshDatabase;
  use WithTenantSession;

  private const R_UPDATE_ROLE   = 'admin.users.roles.update';
  private const R_UPDATE_STATUS = 'admin.users.status.update';

  #[DataProvider('rolesMatrix')]
  public function testUpdateRoleAuthorization(
    string $actorRole,
    bool $shouldAllowTargetViewer,
    bool $shouldAllowTargetOwner): void
  {
    $tenant = Tenant::factory()->active()->create();
    $this->withTenantSession($tenant);

    $actor = User::factory()->verified()->active()->forTenant($tenant)->{$actorRole}()->create();
    $viewer = User::factory()->verified()->active()->forTenant($tenant)->asTenantViewer()->create();
    $owner  = User::factory()->verified()->active()->forTenant($tenant)->asTenantOwner()->create();

    $this->actingAs($actor, 'web');

    // Target = viewer
    $res1 = $this->post(
      route(self::R_UPDATE_ROLE, ['user' => $viewer->id]),
      ['role' => 'tenant_editor']
    );
    $this->assertContains(
      $res1->getStatusCode(),
      $shouldAllowTargetViewer ? [200, 302] : [403, 302]
    );

    // Target = owner
    $res2 = $this->post(route(self::R_UPDATE_ROLE, ['user' => $owner->id]), ['role' => 'tenant_admin']);
    $this->assertContains($res2->getStatusCode(), $shouldAllowTargetOwner ? [200, 302] : [403, 302]);
  }

  public static function rolesMatrix(): array
  {
    return [
      // actorRole, can manage viewer, can manage owner
      'tenant_owner can manage viewer and owner' => ['asTenantOwner', true, true],
      'tenant_admin can manage viewer only'      => ['asTenantAdmin', true, false],
      'tenant_editor cannot manage'              => ['asTenantEditor', false, false],
      'tenant_viewer cannot manage'              => ['asTenantViewer', false, false],
    ];
  }

  public function testUpdateRoleCrossTenantIsForbidden(): void
  {
    $t1 = Tenant::factory()->active()->create();
    $t2 = Tenant::factory()->active()->create();
    $this->withTenantSession($t1);

    $adminT1 = User::factory()->verified()->active()->forTenant($t1)->asTenantAdmin()->create();
    $userT2  = User::factory()->verified()->active()->forTenant($t2)->asTenantViewer()->create();

    $this->actingAs($adminT1, 'web');

    $res = $this->post(route(self::R_UPDATE_ROLE, ['user' => $userT2->id]), ['role' => 'tenant_editor']);
    $this->assertContains($res->getStatusCode(), [403, 302]);
  }

  public function testUpdateStatusFollowsSameRules(): void
  {
    $tenant = Tenant::factory()->active()->create();
    $this->withTenantSession($tenant);

    $admin  = User::factory()->verified()->active()->forTenant($tenant)->asTenantAdmin()->create();
    $viewer = User::factory()->verified()->active()->forTenant($tenant)->asTenantViewer()->create();

    $this->actingAs($admin, 'web');

    $res = $this->post(route(self::R_UPDATE_STATUS, ['user' => $viewer->id]), ['status' => 'suspended']);
    $this->assertContains($res->getStatusCode(), [200, 302]);

    // No self-status changes
    $res2 = $this->post(route(self::R_UPDATE_STATUS, ['user' => $admin->id]), ['status' => 'suspended']);
    $this->assertContains($res2->getStatusCode(), [403, 302]);
  }

  public function testCannotManagePlatformAdmin(): void
  {
    $tenant = Tenant::factory()->active()->create();
    $this->withTenantSession($tenant);

    $owner = User::factory()->verified()->active()->forTenant($tenant)->asTenantOwner()->create();
    $root  = User::factory()->verified()->active()->forTenant($tenant)->asPlatformSuperAdmin()->create();

    $this->actingAs($owner, 'web');

    $res1 = $this->post(route(self::R_UPDATE_ROLE, ['user' => $root->id]), ['role' => 'tenant_admin']);
    $res2 = $this->post(route(self::R_UPDATE_STATUS, ['user' => $root->id]), ['status' => 'suspended']);

    $this->assertContains($res1->getStatusCode(), [403, 302]);
    $this->assertContains($res2->getStatusCode(), [403, 302]);
  }
}
