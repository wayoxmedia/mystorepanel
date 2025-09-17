<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\WithTenantSession;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
  use RefreshDatabase;
  use WithTenantSession;

  private const R_USERS_STORE = 'admin.users.store';

  /**
   *  Access & auth
   */

  public function testStoreRequiresAuth(): void
  {
    $res = $this->post(route(self::R_USERS_STORE));
    $res->assertStatus(302); // redirect to login page
  }

  #[DataProvider('rolesMatrix')]
  public function testStoreAccessByRole(string $roleMethod, bool $shouldAllow): void
  {
    $tenant = Tenant::factory()->active()->create();
    $user   = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->{$roleMethod}()
      ->create();

    $this->withTenantSession($tenant);
    $this->actingAs($user, 'web');

    $payload = [
      'mode'  => 'invite', // or 'create'; we'll test both elsewhere
      'email' => 'candidate.'.uniqid().'@example.test',
      'name'  => 'Candidate Name',
      // password fields only matter for 'create' mode; omitted here
    ];

    $res = $this->post(
      route(self::R_USERS_STORE),
      $payload
    );

    if ($shouldAllow) {
      $this->assertContains($res->getStatusCode(), [200, 201, 302]);
    } else {
      $this->assertContains($res->getStatusCode(), [403, 302]);
    }
  }

  public static function rolesMatrix(): array
  {
    return [
      'tenant_admin allowed'  => ['asTenantAdmin', true],
      'tenant_editor denied'  => ['asTenantEditor', false],
      'tenant_viewer denied'  => ['asTenantViewer', false],
    ];
  }

  /**
   *  Validation by mode
   */

  #[DataProvider('invalidInvitePayloads')]
  public function testStoreValidatesInviteMode(array $payload): void
  {
    $this->actingAsTenantAdmin();

    $res = $this->post(
      route(self::R_USERS_STORE),
      array_merge(['mode' => 'invite'], $payload)
    );
    $res->assertStatus(302); // web form redirects back with errors

    $this->assertDatabaseCount('invitations', 0);
    $this->assertDatabaseCount('users', 1); // only the admin
  }

  /**
   * Various invalid payloads for 'invite' mode.
   *
   * Each case should trigger validation errors.
   *
   * @TODO: Add cases like name missing, role missing, etc.
   * @return array<string, array{0: array}>
   */
  public static function invalidInvitePayloads(): array
  {
    return [
      'missing email' => [['email' => null]],
      'invalid email' => [['email' => 'not-an-email']],
    ];
  }

  #[DataProvider('invalidCreatePayloads')]
  public function testStoreValidatesDirectMode(array $payload): void
  {
    $this->actingAsTenantAdmin();

    $res = $this->post(
      route(self::R_USERS_STORE),
      array_merge(['mode' => 'create'], $payload)
    );
    $res->assertStatus(302);

    // no side effects
    $this->assertDatabaseCount('users', 1); // only the admin
    $this->assertDatabaseCount('invitations', 0);
  }

  /**
   * Various invalid payloads for 'create' mode.
   *
   * Each case should trigger validation errors.
   *
   * todo: Add cases like password complexity, Missing name, etc.
   * @return array<string, array{0: array}>
   */
  public static function invalidCreatePayloads(): array
  {
    return [
      'Missing all data (NULL)' => [
        [
          'email' => null,
          'name' => null,
          'password' => null,
          'password_confirmation' => null,
          'role_slug' => null
        ]
      ],
      'Invalid Email' => [
        [
          'email' => 'not-an-email',
          'name' => 'X',
          'password' => 'Password123!',
          'password_confirmation' => 'Password123!',
          'role_slug' => 'tenant_admin'
        ]
      ],
      /** Pending complexity: password rules logic is missing, need to fix this
      'password mismatch' => [
        [
          'email' => 'x@example.test',
          'name' => 'X',
          'password' => 'Password123!',
          'password_confirmation' => 'nope',
          'role_slug' => 'tenant_admin'
        ]
      ],
      */
      'No Tenant Admin' => [
        [
          'email' => 'x@example.test',
          'name' => 'X',
          'password' => 'Password123!',
          'password_confirmation' => 'nope',
          'role_slug' => ''
        ]
      ],
    ];
  }

  /**
   *  Behavior: invite mode
   */

  public function testStoreCreatesPendingInvitationInInviteMode(): void
  {
    [$tenant] = $this->actingAsTenantAdmin();

    $email = 'invite.'.uniqid().'@example.test';

    $res = $this->post(route(self::R_USERS_STORE), [
      'mode'      => 'invite',
      'email'     => $email,
      'name'      => 'Candidate',
      'role_slug' => 'tenant_admin',
    ]);

    $this->assertContains($res->getStatusCode(), [200, 201, 302]);

    $inv = Invitation::query()
      ->where('tenant_id', $tenant->id)
      ->where('email', $email)
      ->first();

    $this->assertNotNull($inv, 'Invitation should be created.');
    $this->assertSame('pending', $inv->status);
    $this->assertNotNull($inv->token);
  }

  /**
   * If the tenant has no available seats, an invitation cannot be created.
   *
   * The system should prevent creating invitations when the seat limit is reached.
   * This test ensures that behavior.
   * @return void
   */
  public function testStoreInviteModeDoesNotCreateInvitationWhenLimitIsFull(): void
  {
    // Assumption: invites can't be created if seats are full.
    $tenant = Tenant::factory()
      ->active()
      ->withSeatLimit(1)
      ->create();
    User::factory()
      ->active()
      ->verified()
      ->forTenant($tenant)
      ->create(); // fills the only seat

    [$tenant2, $admin] = $this->actingAsTenantAdmin($tenant);
    // Ensure current session tenant is the same
    $this->assertSame($tenant->id, $tenant2->id);
    $this->withTenantSession($tenant);
    $this->actingAs($admin, 'web');

    $email = 'full.'.uniqid().'@example.test';

    $res = $this->post(route(self::R_USERS_STORE), [
      'mode'      => 'invite',
      'email'     => $email,
      'name'      => 'Candidate',
      'role_slug' => 'tenant_admin',
    ]);

    $this->assertContains($res->getStatusCode(), [200, 201, 302]);

    $this->assertFalse(
      DB::table('invitations')
        ->where('tenant_id', $tenant->id)
        ->where('email', $email)
        ->exists(),
      'Invitation should not be created when seats are full.'
    );
  }

  /**
   *  Behavior: create mode
   */

  public function testStoreCreatesActiveUserInCreateMode(): void
  {
    [$tenant] = $this->actingAsTenantAdmin();

    $email = 'create.'.uniqid().'@example.test';

    $res = $this->post(route(self::R_USERS_STORE), [
      'role_slug'             => 'tenant_admin',
      'email'                 => $email,
      'mode'                  => 'create',
      'name'                  => 'Create User',
      'password'              => 'Password123!',
      'password_confirmation' => 'Password123!',
    ]);

    $this->assertContains($res->getStatusCode(), [200, 201, 302]);

    $this->assertTrue(
      DB::table('users')
        ->where('tenant_id', $tenant->id)
        ->where('email', $email)
        ->exists(),
      'User should be created directly.'
    );
  }

  public function testStoreCreateModeFailsWhenSeatLimitReached(): void
  {
    $tenant = Tenant::factory()->active()->withSeatLimit(1)->create();
    User::factory()->active()->verified()->forTenant($tenant)->create(); // seat full

    [, $admin] = $this->actingAsTenantAdmin();
    $this->withTenantSession($tenant);
    $this->actingAs($admin, 'web');

    $email = 'full.create.'.uniqid().'@example.test';

    $res = $this->post(route(self::R_USERS_STORE), [
      'mode'                  => 'create',
      'email'                 => $email,
      'name'                  => 'Created User',
      'password'              => 'Password123!',
      'password_confirmation' => 'Password123!',
    ]);

    // Web: redirect with errors; API: 409/422 (adjust as needed)
    $this->assertContains(
      $res->getStatusCode(),
      [302, 409, 422]
    );

    $this->assertFalse(
      DB::table('users')
        ->where('tenant_id', $tenant->id)
        ->where('email', $email)
        ->exists(),
      'No user should be created when seat limit is reached (create mode).'
    );
  }

  /**
   *  Duplicate email handling
   */

  public function testStoreBlocksDuplicateEmailInSameTenant(): void
  {
    [$tenant] = $this->actingAsTenantAdmin();

    $email = 'dup.'.uniqid().'@example.test';

    User::factory()
      ->active()
      ->verified()
      ->forTenant($tenant)
      ->create(['email' => $email]);

    $res = $this->post(
      route(self::R_USERS_STORE),
      [
        'mode'                  => 'create',
        'email'                 => $email,
        'name'                  => 'Dup User',
        'password'              => 'Password123!',
        'password_confirmation' => 'Password123!',
        ]
    );

    $this->assertContains(
      $res->getStatusCode(),
      [302, 409, 422]
    );

    $count = DB::table('users')
      ->where('tenant_id', $tenant->id)
      ->where('email', $email)
      ->count();
    $this->assertSame(
      1,
      $count,
      'Should not create a duplicate user with same email in tenant.');
  }
}
