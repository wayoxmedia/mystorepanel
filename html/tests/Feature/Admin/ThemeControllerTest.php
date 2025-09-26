<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ThemeControllerTest
 *
 * Purpose:
 * - Smoke-test JSON endpoints: index/store/show/update/destroy.
 * - Assumes SA or a manager of the same tenant is acting.
 */
class ThemeControllerTest extends TestCase
{
  use RefreshDatabase;

  public function testIndexReturnsPaginatedJson(): void
  {
    /** @var Tenant $t1 */
    $t1 = Tenant::factory()->create();
    Theme::factory()->count(3)->forTenant($t1)->create();

    /** @var User $sa */
    $sa = User::factory()->asPlatformSuperAdmin()->create([
      'status' => 'active',
      'tenant_id' => $t1->id
    ]);

    $this->actingAs($sa);

    $this->json('GET', route('admin.themes.index'), ['per_page' => 2])
      ->assertOk()
      ->assertJsonStructure(['data', 'links', 'meta']);
  }

  public function testStoreShowUpdateDestroyFlow(): void
  {
    /** @var Tenant $t1 */
    $t1 = Tenant::factory()->create();

    /** @var User $sa */
    $sa = User::factory()->asPlatformSuperAdmin()->create([
      'status' => 'active',
      'tenant_id' => $t1->id
    ]);

    $this->actingAs($sa);

    // Store
    $payload = [
      'tenant_id' => $t1->id,
      'name'      => 'Landing Theme',
      'slug'      => 'landing-theme',
      'status'    => 'active',
      'config'    => ['brand' => ['primary' => '#123456']],
    ];
    $res = $this->json(
      'POST',
      route('admin.themes.store'),
      $payload
    )
      ->assertCreated()
      ->json();

    $id = data_get($res, 'data.id');
    $slug = data_get($res, 'data.slug');
    $this->assertNotNull($id);

    // Show
    $this->json(
      'GET',
      route('admin.themes.show', $id)
    )
      ->assertOk()
      ->assertJsonPath('data.slug', 'landing-theme');

    // Update (immutable slug/tenant_id)
    $update = [
      'tenant_id'   => $t1->id,
      'slug'        => $slug,
      'name'        => 'Updated Landing Theme',
      'status'      => 'draft',
      'description' => 'Short desc',
      'config'      => ['features' => ['darkMode' => true]],
    ];
    $this->json('PUT', route('admin.themes.update', $id), $update)
      ->assertOk()
      ->assertJsonPath('data.name', 'Updated Landing Theme')
      ->assertJsonPath('data.status', 'draft')
      ->assertJsonPath('data.slug', 'landing-theme'); // still original

    // Destroy
    $this->json('DELETE', route('admin.themes.destroy', $id))
      ->assertOk()
      ->assertJson(['deleted' => true]);

    $this->assertSoftDeleted('themes', ['id' => $id]);
  }
}
