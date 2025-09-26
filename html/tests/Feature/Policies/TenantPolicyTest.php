<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TenantPolicyTest
 *
 * Purpose:
 * - Smoke test TenantPolicy decisions for active vs suspended users (dev-permissive).
 *
 * Notes:
 * - Replace assertions when you harden the policy with real roles/abilities.
 */
class TenantPolicyTest extends TestCase
{
  use RefreshDatabase;

  public function testActiveUserIsAllowedForCrud(): void
  {
    /** @var User $user */
    $user = User::factory()->create(['status' => 'active']);

    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();

    $this->actingAs($user);

    $this->assertTrue($user->can('viewAny', Tenant::class));
    $this->assertTrue($user->can('view', $tenant));
    $this->assertTrue($user->can('create', Tenant::class));
    $this->assertTrue($user->can('update', $tenant));
    $this->assertTrue($user->can('delete', $tenant));
    $this->assertTrue($user->can('suspend', $tenant));
    $this->assertTrue($user->can('resume', $tenant));
  }

  public function testNonActiveUserIsDenied(): void
  {
    /** @var User $user */
    $user = User::factory()->create(['status' => 'suspended']);

    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();

    $this->actingAs($user);

    $this->assertFalse($user->can('create', Tenant::class));
    $this->assertFalse($user->can('update', $tenant));
  }
}
