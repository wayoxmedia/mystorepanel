<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tests for the InvitationAcceptanceController.
 *
 * Covers:
 * - Displaying the acceptance page with a valid token.
 * - Validating the acceptance payload.
 * - Respecting tenant seat limits during acceptance.
 * - Handling expired tokens correctly.
 *
 * Note: This test assumes the existence of factories for Tenant, Invitation, and User models,
 * as well as appropriate routes and middleware in the application.
 */
final class InvitationAcceptanceControllerTest extends TestCase
{
  use RefreshDatabase;

  private const R_ACCEPT_STORE = 'invitations.accept.store';
  private const R_ACCEPT_SHOW  = 'invitations.accept';

  /**
   * Public page should load for a valid token.
   *
   * @return void
   */
  public function testAcceptShowDisplaysPageForValidToken(): void
  {
    $tenant = Tenant::factory()->active()->create();
    $inv    = Invitation::factory()->pending()->forTenant($tenant)->create();

    $res = $this->get(route(self::R_ACCEPT_SHOW, ['token' => $inv->token]));
    $res->assertStatus(200);
  }

  /**
   * Define payload and status (422/302) for accept store, then enable.
   * @return void
   */
  public function testAcceptStoreValidatesPayload(): void
  {
    $tenant = Tenant::factory()->active()->create();
    $inv    = Invitation::factory()->pending()->forTenant($tenant)->create();

    // Intentionally omit required fields to trigger validation.
    $res = $this->post(route(self::R_ACCEPT_STORE), [
      'token' => $inv->token,
    ]);

    $res->assertStatus(302);
  }

  /**
   * Test that acceptance respects seat limits.
   *
   * @param  integer $seatLimit
   * @param  integer $activeUsers
   * @param  boolean $shouldAccept
   * @param  integer $expectedHttp
   * @return void
   */
  #[DataProvider('seatLimitCases')]
  public function testAcceptRespectsSeatLimits(
    int $seatLimit,
    int $activeUsers,
    bool $shouldAccept,
    int $expectedHttp
  ): void {
    // Arrange: tenant with limit and pre-filled active users
    $tenant = Tenant::factory()->active()->withSeatLimit($seatLimit)->create();
    User::factory()->active()->verified()->forTenant($tenant)->count($activeUsers)->create();

    $email = 'invitee.' . Str::random(5) . '@example.test';
    $inv = Invitation::factory()->pending()->forTenant($tenant)->create(['email' => $email]);

    // Act
    $res = $this->post(route(self::R_ACCEPT_STORE), $this->buildAcceptPayload($inv));

    // Assert HTTP (web usually redirects; adjust if your endpoint is JSON)
    $res->assertStatus($expectedHttp);

    if ($shouldAccept) {
      $this->assertDatabaseHas('invitations', ['id' => $inv->id, 'status' => 'accepted']);
      $this->assertTrue(
        DB::table('users')->where('tenant_id', $tenant->id)->where('email', $email)->exists(),
        'User should exist after acceptance when a seat is available.'
      );
      $this->assertSame($seatLimit, $this->activeSeatCount($tenant->id));
    } else {
      $this->assertDatabaseHas('invitations', ['id' => $inv->id, 'status' => 'pending']);
      $this->assertFalse(
        DB::table('users')->where('tenant_id', $tenant->id)->where('email', $email)->exists(),
        'No user should be created when seat limit is reached.'
      );
      $this->assertSame($activeUsers, $this->activeSeatCount($tenant->id));
    }
  }

  /**
   * Counts active seats for a tenant.
   * @param  integer $tenantId
   * @return integer
   */
  private function activeSeatCount(int $tenantId): int
  {
    return DB::table('users')
      ->where('tenant_id', $tenantId)
      ->where('status', 'active')
      ->count();
  }

  /**
   * Data provider for seat limit test cases.
   *
   * Each case is an array with:
   * - seat limit (int)
   * - active users (int)
   * - should accept (bool)
   * - expected HTTP status (int)
   *
   * @return array<string, array{0: int, 1: int, 2: bool, 3: int}>
   */
  public static function seatLimitCases(): array
  {
    return [
      'full capacity blocks acceptance'       => [2, 2, false, 302],
      'one seat available allows acceptance'  => [3, 2, true,  302],
    ];
  }

  /**
   * Acceptance should fail for expired tokens.
   * @return void
   */
  public function testAcceptFailsForExpiredToken(): void
  {
    $tenant = Tenant::factory()->active()->withSeatLimit(2)->create();
    $inv = Invitation::factory()->expired()->forTenant($tenant)->create([
      'email' => 'expired@example.test',
    ]);

    $res = $this->post(route(self::R_ACCEPT_STORE), $this->buildAcceptPayload($inv));
    $res->assertStatus(302);

    $this->assertDatabaseHas('invitations', ['id' => $inv->id, 'status' => 'expired']);
    $this->assertFalse(
      DB::table('users')
        ->where('tenant_id', $tenant->id)
        ->where('email', 'expired@example.test')
        ->exists(),
      'No user should be created for expired tokens.'
    );
  }

  /**
   * Data provider for invalid token scenarios.
   * Builds a plausible acceptance payload.
   * @param  Invitation $inv
   * @param  array      $overrides
   * @return array<string, array{0: array}>
   */
  private function buildAcceptPayload(Invitation $inv, array $overrides = []): array
  {
    return array_merge([
      'token'                 => $inv->token,
      'name'                  => 'Invited User ' . Str::random(4),
      'password'              => 'Password123!',
      'password_confirmation' => 'Password123!',
    ], $overrides);
  }
}
