<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TenantControllerTest
 *
 * Purpose:
 * - Exercise the JSON endpoints for index/store/show/update/destroy/suspend/resume
 *   without relying on Blade views.
 */
class TenantControllerTest extends TestCase
{
  use RefreshDatabase;

  public function testIndexReturnsPaginatedJson(): void
  {
    /** @var User $user */
    $user = User::factory()->create(['status' => 'active']);

    Tenant::factory()->count(3)->create();

    $this->actingAs($user)
      ->json('GET', route('admin.tenants.index'), ['per_page' => 2])
      ->assertOk()
      ->assertJsonStructure(['data', 'links', 'meta']);
  }

  public function testStoreCreatesTenantAndReturnsJson(): void
  {
    /** @var User $user */
    $user = User::factory()->create(['status' => 'active']);

    $payload = Tenant::factory()->make(['slug' => 'json-tenant'])->only([
      'name', 'slug', 'status', 'user_seat_limit', 'primary_domain',
    ]);

    $this->actingAs($user)
      ->json('POST', route('admin.tenants.store'), $payload)
      ->assertCreated()
      ->assertJsonPath('slug', 'json-tenant');

    $this->assertDatabaseHas('tenants', ['slug' => 'json-tenant']);
  }

  public function testShowReturnsTenantJson(): void
  {
    /** @var User $user */
    $user = User::factory()->create(['status' => 'active']);

    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();

    $this->actingAs($user)
      ->json('GET', route('admin.tenants.show', $tenant))
      ->assertOk()
      ->assertJsonPath('data.id', $tenant->id);
  }

  public function testUpdateModifiesTenantAndKeepsSlug(): void
  {
    /** @var User $user */
    $user = User::factory()->create(['status' => 'active']);

    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create(['slug' => 'immutable']);

    $payload = [
      'name'            => 'Changed',
      'slug'            => 'immutable', // <- same slug, should be kept
      'status'          => 'active',
      'user_seat_limit' => 3,
    ];

    $this->actingAs($user)
      ->json('PUT', route('admin.tenants.update', $tenant), $payload)
      ->assertOk()
      ->assertJsonPath('slug', 'immutable')
      ->assertJsonPath('name', 'Changed');

    $tenant->refresh();
    $this->assertSame('immutable', $tenant->slug);
    $this->assertSame('Changed', $tenant->name);
  }

  public function testDestroySoftDeletesTenant(): void
  {
    /** @var User $user */
    $user = User::factory()->create(['status' => 'active']);

    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();

    $this->actingAs($user)
      ->json('DELETE', route('admin.tenants.destroy', $tenant))
      ->assertOk();

    $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
  }

  public function testSuspendAndResumeChangeStatus(): void
  {
    /** @var User $user */
    $user = User::factory()->create(['status' => 'active']);

    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create(['status' => 'active']);

    $this->actingAs($user)
      ->json('POST', route('admin.tenants.suspend', $tenant))
      ->assertOk()
      ->assertJsonPath('status', 'suspended');

    $tenant->refresh();
    $this->assertSame('suspended', $tenant->status);

    $this->actingAs($user)
      ->json('POST', route('admin.tenants.resume', $tenant))
      ->assertOk()
      ->assertJsonPath('status', 'active');

    $tenant->refresh();
    $this->assertSame('active', $tenant->status);
  }
}
