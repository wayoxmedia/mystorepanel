<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\WithTenantSession;
use Tests\TestCase;

class ImpersonationControllerTest extends TestCase
{
  use RefreshDatabase;
  use WithTenantSession;

  private const R_START = 'admin.impersonate.start';
  private const R_STOP  = 'impersonate.stop';

  public function testAdminCanImpersonateViewerInSameTenant(): void
  {
    $tenant = Tenant::factory()->active()->create();
    $this->withTenantSession($tenant);

    $admin  = User::factory()->verified()->active()->forTenant($tenant)->asTenantAdmin()->create();
    $viewer = User::factory()->verified()->active()->forTenant($tenant)->asTenantViewer()->create();

    $this->actingAs($admin, 'web');

    $res = $this->post(route(self::R_START, ['user' => $viewer->id]));
    $this->assertContains($res->getStatusCode(), [200, 302]);

    $res2 = $this->post(route(self::R_STOP));
    $this->assertContains($res2->getStatusCode(), [200, 302]);
  }

  #[DataProvider('forbiddenMatrix')]
  public function testImpersonationForbiddenCases(callable $actorFactory, callable $targetFactory): void
  {
    $tenant = Tenant::factory()->active()->create();
    $this->withTenantSession($tenant);

    $actor  = $actorFactory($tenant);
    $target = $targetFactory($tenant);

    $this->actingAs($actor, 'web');

    $res = $this->post(route(self::R_START, ['user' => $target->id]));
    $this->assertContains($res->getStatusCode(), [403, 302]);
  }

  public static function forbiddenMatrix(): array
  {
    return [
      'editor cannot impersonate viewer' => [
        fn ($t) => User::factory()->verified()->active()->forTenant($t)->asTenantEditor()->create(),
        fn ($t) => User::factory()->verified()->active()->forTenant($t)->asTenantViewer()->create(),
      ],
      'admin cannot impersonate owner' => [
        fn ($t) => User::factory()->verified()->active()->forTenant($t)->asTenantAdmin()->create(),
        fn ($t) => User::factory()->verified()->active()->forTenant($t)->asTenantOwner()->create(),
      ],
      'cannot impersonate platform admin' => [
        fn ($t) => User::factory()->verified()->active()->forTenant($t)->asTenantOwner()->create(),
        fn ($t) => User::factory()->verified()->active()->forTenant($t)->asPlatformSuperAdmin()->create(),
      ],
      'cannot impersonate self' => [
        fn ($t) => User::factory()->verified()->active()->forTenant($t)->asTenantAdmin()->create(),
        fn ($t) => User::query()->latest('id')->first(), // last created = same user
      ],
      'cross-tenant blocked (non-platform)' => [
        fn ($t) => User::factory()->verified()->active()->forTenant($t)->asTenantAdmin()->create(),
        fn ($t) => User::factory()->verified()->active()->forTenant(Tenant::factory()->active()->create())->asTenantViewer()->create(),
      ],
    ];
  }

  public function testPlatformAdminCanImpersonateAcrossTenants(): void
  {
    $t1 = Tenant::factory()->active()->create();
    $t2 = Tenant::factory()->active()->create();
    $this->withTenantSession($t1);

    $root  = User::factory()->verified()->active()->forTenant($t1)->asPlatformSuperAdmin()->create();
    $user2 = User::factory()->verified()->active()->forTenant($t2)->asTenantViewer()->create();

    $this->actingAs($root, 'web');

    $res = $this->post(route(self::R_START, ['user' => $user2->id]));
    $this->assertContains($res->getStatusCode(), [200, 302]);
  }
}
