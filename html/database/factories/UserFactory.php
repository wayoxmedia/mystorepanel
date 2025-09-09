<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
  /** @var class-string<User> */
  protected $model = User::class;

  /**
   * Allowed status enum values from your schema.
   */
  private const ALLOWED_STATUSES = [
    'active',
    'pending_invite',
    'suspended',
    'locked'
  ];

  /**
   * Allowed role slugs present in the roles table.
   * Keep in sync with BaseRolesSeeder.
   */
  private const ALLOWED_ROLE_SLUGS = [
    'platform_super_admin',
    'tenant_owner',
    'tenant_admin',
    'tenant_editor',
    'tenant_viewer',
  ];

  /**
   * The current password being used by the factory.
   */
  protected static ?string $password;

  /**
   * Define the model's default state.
   *
   * Columns aligned to your schema:
   * - status enum: active|pending_invite|suspended|locked
   * - role (string): defaults to 'editor'
   * - tenant_id: nullable, set via forTenant()
   *
   * @return array<string, mixed>
   */
  public function definition(): array
  {
    return [
      'name'              => $this->faker->name(),
      'email'             => $this->faker->unique()->safeEmail(),
      'email_verified_at' => now(), // default verified for tests; use unverified() to flip
      'password'          => static::$password ??= Hash::make('password'),
      'remember_token'    => Str::random(10),

      // Extra columns from your users table
      'status'            => 'active',
      'role'              => 'editor',
      'tenant_id'         => null,
    ];
  }

  /* ------------------------------
   | Email verification helpers
   * ------------------------------ */

  /**
   * Mark email as unverified.
   */
  public function unverified(): static
  {
    return $this->state(fn () => ['email_verified_at' => null]);
  }

  /**
   * Convenience: explicitly verified (default already is).
   */
  public function verified(): static
  {
    return $this->state(fn () => ['email_verified_at' => now()]);
  }

  /* ------------------------------
   | Status helpers (keep originals)
   * ------------------------------ */

  /**
   * Status Active.
   */
  public function active(): static
  {
    return $this->state(fn () => ['status' => 'active']);
  }

  /**
   * Status Pending Invite.
   */
  public function pendingInvite(): static
  {
    return $this->state(fn () => ['status' => 'pending_invite']);
  }

  /**
   * Status Suspended.
   */
  public function suspended(): static
  {
    return $this->state(fn () => ['status' => 'suspended']);
  }

  /**
   * Status Locked.
   */
  public function locked(): static
  {
    return $this->state(fn () => ['status' => 'locked']);
  }

  /* ------------------------------
   | Tenant relation helper
   * ------------------------------ */

  /**
   * Attach to a tenant (accepts Tenant model or id).
   *
   * @param  Tenant|int  $tenant
   * @return $this
   */
  public function forTenant(Tenant|int $tenant): static
  {
    $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

    return $this->state(fn () => ['tenant_id' => $tenantId]);
  }

  /* ------------------------------
   | Role helpers (wrappers per role)
   | - Set users.role to the slug
   | - Attach pivot (role_user) ensuring the role exists
   * ------------------------------ */

  /**
   * Set role to platform_super_admin.
   *
   * @return $this
   */
  public function asPlatformSuperAdmin(): static
  {
    return $this->withRoleSlug('platform_super_admin');
  }

  /**
   * Set role to tenant_owner.
   *
   * @return $this
   */
  public function asTenantOwner(): static
  {
    return $this->withRoleSlug('tenant_owner');
  }

  /**
   * Set role to tenant_admin.
   *
   * @return $this
   */
  public function asTenantAdmin(): static
  {
    return $this->withRoleSlug('tenant_admin');
  }

  /**
   * Set role to tenant_editor.
   *
   * @return $this
   */
  public function asTenantEditor(): static
  {
    return $this->withRoleSlug('tenant_editor');
  }

  /**
   * Set role to tenant_viewer.
   *
   * @return $this
   */
  public function asTenantViewer(): static
  {
    return $this->withRoleSlug('tenant_viewer');
  }

  /**
   * Generic role setter with validation + pivot attach.
   * Prefer using the wrapper methods above in tests for readability.
   */
  public function withRoleSlug(string $slug): static
  {
    if (!in_array($slug, self::ALLOWED_ROLE_SLUGS, true)) {
      throw new InvalidArgumentException(
        sprintf(
          'Invalid role slug "%s". Allowed: %s',
          $slug, implode(
            ', ',
            self::ALLOWED_ROLE_SLUGS
          )
        )
      );
    }

    return $this
      ->state(fn () => ['role' => $slug]) // set simple string column
      ->afterCreating(function (User $user) use ($slug): void {
        $this->attachRolePivotBySlug($user, $slug);
      });
  }

  /**
   * Attach role to user via pivot (role_user), validating existence in roles table.
   */
  private function attachRolePivotBySlug(User $user, string $slug): void
  {
    $roleId = DB::table('roles')
      ->where('slug', $slug)
      ->value('id');
    if (!$roleId) {
      throw new InvalidArgumentException(
        sprintf(
          'Role slug "%s" not found in roles table. Did you run BaseRolesSeeder?',
          $slug
        )
      );
    }

    DB::table('role_user')->updateOrInsert(
      [
        'role_id' => (int) $roleId,
        'user_id' => (int) $user->getKey()
      ],
      []
    );
  }
}
