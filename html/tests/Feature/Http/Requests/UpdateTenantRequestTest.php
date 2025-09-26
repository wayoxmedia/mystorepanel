<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Requests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UpdateTenantRequestTest
 *
 * Purpose:
 * - Validate update rules such as immutable slug and seat-limit safety.
 * - Ensure authorization (policy update) is enforced.
 */
class UpdateTenantRequestTest extends TestCase
{
  use RefreshDatabase;

  public function testUpdatesAllowedFields(): void
  {
    /** @var User $admin */
    $admin = User::factory()->create(['status' => 'active']);

    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create([
      'slug' => 'keep-this',
      'user_seat_limit' => 5,
      'status' => 'active',
    ]);

    $payload = [
      'name'            => 'Renamed Corp',
      'slug'            => 'keep-this',
      'status'          => 'active',
      'user_seat_limit' => 6,
      'primary_domain'  => 'renamed.example.test',
    ];

    $res = $this->actingAs($admin)
      ->json('PUT', route('admin.tenants.update', $tenant), $payload)
      ->assertStatus(200);

    // Ensure slug remained the same, while other fields changed
    $tenant->refresh();
    $this->assertSame('keep-this', $tenant->slug);
    $this->assertSame('Renamed Corp', $tenant->name);
    $this->assertSame('renamed.example.test', $tenant->primary_domain);
    $this->assertSame(6, $tenant->user_seat_limit);
  }

  public function testSeatLimitCannotGoBelowActiveUsers(): void
  {
    /** @var User $admin */
    $admin = User::factory()->create(['status' => 'active']);

    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create([
      'user_seat_limit' => 5,
      'status' => 'active',
    ]);

    // crear 3 usuarios activos en este tenant
    User::factory()->count(3)->create([
      'tenant_id' => $tenant->id,
      'status' => 'active',
    ]);

    // Bajar a 2 debe fallar
    $payload = [
      'name'            => $tenant->name,
      'slug'            => $tenant->slug,
      'status'          => 'active',
      'user_seat_limit' => 2,
    ];

    $this->actingAs($admin)
      ->json('PUT', route('admin.tenants.update', $tenant), $payload)
      ->assertStatus(422)
      ->assertJsonValidationErrors(['user_seat_limit']);
  }

  public function testRequiresAuthentication(): void
  {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();

    $this->json('PUT', route('admin.tenants.update', $tenant), [])
      ->assertStatus(401);
  }
}
