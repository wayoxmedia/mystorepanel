<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Feature tests for TenantUsersController (status/role updates).
 *
 * Covers:
 *  - 403 reauth_required when missing
 *  - 200 OK after successful reauth
 *  - 422 validation for invalid role_id
 *
 * Assumptions:
 *  - routes/api.php defines:
 *      POST  /auth/reauth
 *      PATCH /tenants/{tenant_id}/users/{user}/status
 *      PATCH /tenants/{tenant_id}/users/{user}/role
 *  - You have config('roles.map') with keys = role_id and values = role code
 *    e.g., ['1'=>'platform_super_admin','3'=>'tenant_admin','5'=>'tenant_viewer', ...]
 *  - JWT guard is configured as 'api' (driver 'jwt')
 *  - .env.testing has JWT_SECRET set
 */
class TenantUsersControllerTest extends TestCase
{
  use RefreshDatabase;

  /**
   * A known password to set on created users for reauth
   */
  private const PWD = 'Secret123$';

  /**
   * Quick lookup helpers from config('roles.map')
   * @param string $code Role code, e.g. 'tenant_admin'
   * @return int role_id
   */
  private function roleId(string $code): int
  {
    $map = (array) config('roles.role_map', []);
    $id  = array_search($code, $map, true);
    $this->assertNotFalse(
      $id,
      "Missing role code '{$code}' in config('roles.role_map')."
    );
    return (int) ($id);
  }

  /**
   * Tenant role IDs from config('roles.map')
   * @return int role_id for 'tenant_admin' (default 3)
   */
  private function tenantAdminId(): int
  {
    return $this->roleId('tenant_admin');
  }

  /**
   * Tenant role IDs from config('roles.map')
   * @return int role_id for 'tenant_viewer' (default 5)
   */
  private function tenantViewerId(): int
  {
    return $this->roleId('tenant_viewer');
  }

  /**
   * Tenant role IDs from config('roles.map')
   * @return int role_id for 'tenant_editor' (default 4)
   */
  private function tenantEditorId(): int
  {
    return $this->roleId('tenant_editor');
  }

  /**
   * Creates a tenant; uses factory if available.
   * @return object with at least 'id' property
   */
  private function makeTenant(): object
  {
    /** @var Tenant $t */
    $t = Tenant::factory()->create();
    return (object) ['id' => $t->id];
  }

  /**
   * Creates a user in a given tenant with defaults suitable for these tests
   * (overrides allow customizing role_id, status, etc.).
   * @param int $tenantId
   * @param array $overrides
   * @return User
   */
  private function makeUser(int $tenantId, array $overrides = []): User
  {
    $defaults = [
      'name'              => 'Test User',
      'email'             => 'user'.uniqid().'@example.test',
      'password'          => Hash::make(self::PWD),
      'status'            => 'active',
      'email_verified_at' => now(),
      'tenant_id'         => $tenantId,
      'role_id'           => $this->tenantViewerId(), // default viewer
    ];

    /** @var User $u */
    $u = User::factory()
      ->create(array_merge($defaults, $overrides));

    return $u->fresh();
  }

  /**
   * Build Authorization + X-Tenant-Id headers for the given user and tenant
   * @param User $user
   * @param int $tenantId
   * @return array
   */
  private function authHeaders(User $user, int $tenantId): array
  {
    $token = JWTAuth::fromUser($user);
    return [
      'Authorization' => 'Bearer '.$token,
      'X-Tenant-Id'   => (string) $tenantId,
      'Accept'        => 'application/json',
    ];
  }

  /**
   * Calls POST /auth/reauth with the known password to set the short-lived flag
   * for the given user and tenant.
   * Asserts 200 OK and expected JSON structure.
   * @param User $user
   * @param int $tenantId
   * @return void
   */
  private function reauth(User $user, int $tenantId): void
  {
    $res = $this
      ->withHeaders($this->authHeaders($user, $tenantId))
      ->postJson('/api/auth/reauth', ['password' => self::PWD]);

    $res
      ->assertOk()
      ->assertJsonStructure(
        ['status','reauth_until','ttl_seconds']
      );
  }

  private function mintToken(User $user): string
  {
    return JWTAuth::fromUser($user);
  }

  private function authHeadersWithToken(string $token, int $tenantId): array
  {
    return [
      'Authorization' => 'Bearer '.$token,
      'X-Tenant-Id'   => (string) $tenantId,
      'Accept'        => 'application/json',
    ];
  }

  /** Calls POST /auth/reauth using the SAME token that you'll reuse later */
  private function reauthWithToken(User $user, int $tenantId, string $token): void
  {
    $res = $this->withHeaders($this->authHeadersWithToken($token, $tenantId))
      ->postJson('/api/auth/reauth', ['password' => self::PWD]);

    $res->assertOk()->assertJsonStructure(['status','reauth_until','ttl_seconds']);
  }

  /**
   * 403 when reauth is missing (reauth middleware enforces it)
   * Actor is tenant_admin in same tenant as target user.
   * The action is to suspend the target user.
   * @return void
   */
  public function testUpdateStatusRequiresReauth(): void
  {
    $tenant = $this->makeTenant();
    $actor  = $this->makeUser(
      $tenant->id,
      ['role_id' => $this->tenantAdminId()]
    );
    $target = $this->makeUser(
      $tenant->id
    ); // same-tenant target

    $res = $this
      ->withHeaders($this->authHeaders($actor, $tenant->id))
      ->patchJson(
        "/api/tenants/{$tenant->id}/users/{$target->id}/status",
        ['status' => 'suspended',]
      );

    $res
      ->assertStatus(403)
      ->assertJson(['code' => 'reauth_required']);
  }

  /**
   * 200 after reauth: status change succeeds
   * Actor is tenant_admin in same tenant as target user.
   * The action is to suspend the target user.
   * @return void
   */
  public function testUpdateStatusSucceedsAfterReauth(): void
  {
    $tenant = $this->makeTenant();

    // Actor must be tenant_admin (or higher by hierarchy) in that tenant
    $actor  = $this->makeUser(
      $tenant->id,
      ['role_id' => $this->tenantAdminId()]
    );
    $target = $this->makeUser(
      $tenant->id,
      ['status' => 'active']
    );

    // Perform reauth first
    $this->reauth($actor, $tenant->id);

    // Now the sensitive action should pass
    $res = $this
      ->withHeaders($this->authHeaders($actor, $tenant->id))
      ->patchJson(
        "/api/tenants/{$tenant->id}/users/{$target->id}/status",
        ['status' => 'suspended']
      );

    $res->assertOk()
      ->assertJson([
        'ok'        => true,
        'tenant_id' => $tenant->id,
        'user_id'   => $target->id,
        'new'       => ['status' => 'suspended'],
      ]);
  }

  /**
   * 200 after reauth: role change succeeds (viewer -> editor)
   * Actor is tenant_admin in same tenant as target user.
   * The action is to promote the target user from viewer to editor.
   * @return void
   */
  public function testUpdateRoleSucceedsAfterReauth(): void
  {
    $tenant = $this->makeTenant();

    $actor  = $this->makeUser(
      $tenant->id,
      ['role_id' => $this->tenantAdminId()]
    );
    $target = $this->makeUser(
      $tenant->id,
      ['role_id' => $this->tenantViewerId()]
    );

    // ⚠️ only one token for both reauth and action
    // This simulates a real client that reauth's and then performs the action
    $token = $this->mintToken($actor);

    // reauth
    $this->reauthWithToken($actor, $tenant->id, $token);

    $newRoleId = $this->tenantEditorId(); // promote to editor

    $res = $this
      ->withHeaders($this->authHeaders($actor, $tenant->id))
      ->patchJson(
        "/api/tenants/{$tenant->id}/users/{$target->id}/role",
        ['role_id' => $newRoleId]
      );

    $res->assertOk();
    $res->assertJson([
      'ok'        => true,
      'tenant_id' => $tenant->id,
      'user_id'   => $target->id,
      'new'       => [
        'role_id' => $newRoleId,
        'role'    => config('roles.role_map')[$newRoleId] ?? null,
      ],
    ]);
  }

  /** 422 when role_id is invalid (not in config('roles.map')) */
  public function testUpdateRoleInvalidRoleIdReturns422(): void
  {
    $tenant = $this->makeTenant();

    $actor  = $this->makeUser(
      $tenant->id,
      ['role_id' => $this->tenantAdminId()]
    );
    $target = $this->makeUser($tenant->id);

    // reauth
    $this->reauth($actor, $tenant->id);

    $invalidRoleId = 999999;

    $res = $this
      ->withHeaders($this->authHeaders($actor, $tenant->id))
      ->patchJson(
        "/api/tenants/{$tenant->id}/users/{$target->id}/role",
        ['role_id' => $invalidRoleId]
      );

    $res->assertStatus(422); // validation error
  }
}
