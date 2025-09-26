<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Requests;

use App\Models\Tenant;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * StoreThemeRequestAuthorizeTest
 *
 * Purpose:
 * - Ensure creation is allowed only for SA or tenant managers creating under their own tenant_id.
 *
 * Notes:
 * - Uses the real HTTP endpoint to exercise FormRequest::authorize().
 */
class StoreThemeRequestAuthorizeTest extends TestCase
{
  use RefreshDatabase;

  public function testPlatformSaCanCreateForAnyTenant(): void
  {
    /** @var Tenant $tOther */
    $tOther = Tenant::factory()->create();

    /** @var User $sa */
    $sa = User::factory()
      ->asPlatformSuperAdmin()
      ->create([
        'status'    => 'active',
        'tenant_id' => null
      ]);
    $this->actingAs($sa);

    $payload = [
      'tenant_id'   => $tOther->id,
      'name'        => 'SA Theme',
      'slug'        => 'sa-theme',
      'status'      => 'active',
      'description' => null,
      'config'      => ['brand' => ['primary' => '#112233']],
    ];

    $res = $this->json(
      'POST',
      route('admin.themes.store'),
      $payload
    );
    $res->assertCreated();
    $res->assertJsonPath('data.slug', 'sa-theme');
  }

  /**
   * Manager roles Provider, to test both 'tenant_owner' and 'tenant_admin'.
   * @return array<string, array{managerRole:string}>
   */
  public static function managerRolesProvider(): array
  {
    return [
      'tenant_owner' => ['managerRole' => 'tenant_owner'],
      'tenant_admin' => ['managerRole' => 'tenant_admin'],
    ];
  }

  #[DataProvider('managerRolesProvider')]
  public function testManagerCanCreateOnlyForOwnTenant(string $managerRole): void
  {
    /** @var Tenant $t1 */
    $t1 = Tenant::factory()->create();
    /** @var Tenant $t2 */
    $t2 = Tenant::factory()->create();

    /** @var UserFactory $manager */
    $manager = User::factory();

    switch ($managerRole) {
      case 'tenant_owner':
        $manager = $manager->asTenantOwner();
        break;
      case 'tenant_admin':
        $manager = $manager->asTenantAdmin();
        break;
      default:
        $this->fail("Invalid managerRole '$managerRole' in data provider.");
    }
    $manager = $manager->create([
      'status'    => 'active',
      'tenant_id' => $t1->id,
    ]);

    $this->actingAs($manager);
    $this->assertTrue($manager->hasAnyRole([$managerRole]));

    // Own tenant -> allowed
    $payloadOk = [
      'tenant_id'   => $t1->id,
      'name'        => 'Own Theme',
      'slug'        => 'own-theme',
      'status'      => 'active',
      'description' => null,
      'config'      => ['brand' => ['primary' => '#445566']],
    ];
    $this->json('POST', route('admin.themes.store'), $payloadOk)
      ->assertCreated()
      ->assertJsonPath('data.slug', 'own-theme');

    // Other tenant -> forbidden by authorize()
    $payloadNo = [
      'tenant_id'   => $t2->id,
      'name'        => 'Other Theme',
      'slug'        => 'other-theme',
      'status'      => 'active',
    ];
    $this->json('POST', route('admin.themes.store'), $payloadNo)
      ->assertStatus(403);
  }

  public function testNonManagerCannotCreate(): void
  {
    /** @var Tenant $t1 */
    $t1 = Tenant::factory()->create();

    /** @var User $user */
    $user = User::factory()->create([
      'status'    => 'active',
      'tenant_id' => $t1->id,
    ]);
    $this->actingAs($user);

    $payload = [
      'tenant_id'   => $t1->id,
      'name'        => 'Nope Theme',
      'slug'        => 'nope-theme',
      'status'      => 'active',
    ];

    $this->json('POST', route('admin.themes.store'), $payload)
      ->assertStatus(403);
  }
}
