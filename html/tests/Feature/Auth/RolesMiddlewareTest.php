<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;


class RolesMiddlewareTest extends TestCase
{
  /**
   * Setup test routes with necessary middleware.
   * @return void
   */
  protected function setUp(): void
  {
    parent::setUp();

    // Define routes only for this test case
    $middlewares = [
      'api',
      'auth:api',
      'active',
      'email.verified',
      'role:tenant_admin'
    ];
    Route::middleware($middlewares)
      ->get(
        '/_test/tenants/{tenant_id}/ping',
        fn () => response()->json(
          ['ok' => true]
        )
      );
  }

  /**
   * Test unauthenticated access returns 401 Unauthorized.
   * @return void
   */
  public function testUnauthenticatedReturns401(): void
  {
    $tenant = $this->makeTenant();
    $this->getJson("/_test/tenants/{$tenant->id}/ping")
      ->assertStatus(401);
  }


  /**
   * Test Roles Expectations.
   * @param $expectedOk
   * @param $userData
   * @param $scenario
   * @return void
   */
  #[DataProvider('rolesProvider')]
  public function testRolesExpectations($expectedOk, $userData, $scenario): void
  {
    $tenant1 = $this->makeTenant(); // tenant for the user
    $tenant2 = $this->makeTenant(); // another tenant

    $u1 = $this->makeUser($tenant1, $userData);
    // $u2 = $this->makeUser($tenant2, $userData);

    switch ($scenario) {
      case 'admin_same_tenant':
      default:
        $tenantId = $tenant1->id;
        $userToUse = $u1;
        break;
      case 'viewer_same_tenant':
        $tenantId = $tenant1->id;
        $userToUse = $u1;
        $testCode = ['message' => 'Forbidden: insufficient role'];
        break;
      case 'unverified_email':
        $tenantId = $tenant1->id;
        $userToUse = $u1;
        $testCode = ['code' => 'email_unverified'];
        break;
      case 'admin_other_tenant':
        $tenantId = $tenant2->id;
        $userToUse = $u1;
        $testCode = ['message' => 'Forbidden: insufficient role'];
        break;
      case 'viewer_other_tenant':
        $tenantId = $tenant2->id;
        $userToUse = $u1;
        break;
      case 'inactive_user':
        $tenantId = $tenant1->id;
        $userToUse = $u1;
        $testCode = ['message' => 'Forbidden: user not active'];
        break;
      case 'super_admin':
        $tenantId = $tenant2->id;
        $userToUse = $this->makeSuperAdmin($userData);
        break;

    }

    $token = JWTAuth::fromUser($userToUse);

    $req = $this->withHeader('Authorization', 'Bearer '.$token)
      ->withHeader('X-Tenant-Id', (string) $tenantId)
      ->getJson("/_test/tenants/{$tenantId}/ping");

    if ($expectedOk) {
      $req->assertOk();
      $req->assertJson(['ok' => true]);
    } else {
      $req->assertStatus(403);
      if (isset($testCode)) {
        $req->assertJson($testCode);
      }
    }
  }

  /**
   * Data Providers for tests/
   * @return array
   */
  public static function rolesProvider(): array
  {
    // $expectedOk, $userData, $scenario
    return [
      'Admin Of Same Tenant Gets 200' => [
        true, ['role_id' => 3], 'admin_same_tenant'
      ],
      // different tenant, PSAs are null
      'Platform SuperAdmin Bypasses Tenant And Gets 200' => [
        true, ['tenant_id' => null, 'role_id' => 1], 'super_admin'
      ],
      // Route is explicitly tenant_admin, so viewer fails
      'Insufficient Role (Viewer) Gets 403' => [
        false, ['role_id' => 5], 'viewer_same_tenant'
      ],
      'Cross Tenant Admin Access Gets 403' => [
        false, ['role_id' => 3], 'admin_other_tenant'
      ],
      'Unverified Email Gets 403' => [
        false, ['role_id' => 3, 'email_verified_at' => null], 'unverified_email'
      ],
      'Inactive User Gets 403' => [
        false, ['role_id' => 3, 'status' => 'locked'], 'inactive_user'
      ],
      'Viewer Other Tenant Gets 403' => [
        false, ['role_id' => 5], 'viewer_other_tenant'
      ],
    ];
  }

  /**
   * Helper to create a user with defaults, allowing overrides.
   * @param  Tenant  $tenant
   * @param  array  $overrides
   * @return User
   */
  private function makeUser(Tenant $tenant, array $overrides = []): User
  {
    $defaults = [
      'status'            => 'active',
      'email_verified_at' => now(),
      'tenant_id'         => $tenant->id,
      'role_id'           => 5, // viewer por defecto
    ];
    $final = array_merge($defaults, $overrides);

    /** @var User $u */
    $u = User::factory()->create($final);

    return $u->fresh();
  }

  private function makeTenant(array $overrides = []): Tenant
  {
    return Tenant::factory()->create($overrides);
  }

  private function makeSuperAdmin(array $overrides = []): User
  {
    unset($overrides['tenant_id'], $overrides['role_id']);

    $defaults = [
      'status'            => 'active',
      'email_verified_at' => now(),
    ];
    $forced = [
      'tenant_id' => null, // force null tenant_id
      'role_id'=> 1 // force super admin
    ];
    $data = array_merge($defaults, $overrides, $forced);

    $user = User::factory()->create($data);

    // never is too much to ensure (nunca esta de mas asegurarse)
    $this->assertNull($user->tenant_id);
    $this->assertTrue($user->isPlatformSuperAdmin());

    return $user->fresh();
  }
}
