<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class GateBypassTest extends TestCase
{
  use RefreshDatabase;

  public function testPlatformAdminBypassWorksAcrossTenants(): void
  {
    $t1   = Tenant::factory()->active()->create();
    $t2   = Tenant::factory()->active()->create();

    $root = User::factory()->verified()->active()->forTenant($t1)->asPlatformSuperAdmin()->create();
    $u2   = User::factory()->verified()->active()->forTenant($t2)->asTenantViewer()->create();

    // Gate::before en AuthServiceProvider debe permitir todo
    $this->assertTrue(Gate::forUser($root)->allows('create', [User::class, $t2]));
    $this->assertTrue(Gate::forUser($root)->allows('updateRole', $u2));
    $this->assertTrue(Gate::forUser($root)->allows('updateStatus', $u2));
    $this->assertTrue(Gate::forUser($root)->allows('impersonate', $u2));
  }
}
