<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Tenant;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
  /** @var class-string<Invitation> */
  protected $model = Invitation::class;

  /**
   * Allowed statuses from your schema.
   */
  private const ALLOWED_STATUSES = ['pending', 'accepted', 'expired', 'cancelled'];

  public function definition(): array
  {
    return [
      // Relation: set it with ->forTenant(...) in tests an specific tenant is needed.
      'tenant_id'  => null,

      'email'      => $this->faker->unique()->safeEmail(),
      // token varchar(128), use 40 for simplicity
      'token'      => Str::random(40),

      // default enum
      'status'     => 'pending',

      // optional: assign with ->forRoleSlug(...) in tests
      // or set directly role_id if you prefer
      'role_id'    => null,

      // typical 7 days expiration, can be changed with ->expiringAt(...) in tests
      'expires_at' => now()->addDays(7),

      'created_at' => now(),
      'updated_at' => now(),
    ];
  }

  /* ------------------------------
   | States
   * ------------------------------ */

  public function pending(): static
  {
    return $this->state(fn () => ['status' => 'pending']);
  }

  public function accepted(): static
  {
    return $this->state(fn () => ['status' => 'accepted']);
  }

  public function expired(): static
  {
    return $this->state(fn () => [
      'status'     => 'expired',
      'expires_at' => now()->subDay(),
    ]);
  }

  public function cancelled(): static
  {
    return $this->state(fn () => ['status' => 'cancelled']);
  }

  /* ------------------------------
   | Relation / fields Helpers
   * ------------------------------ */

  /**
   * Asociar a un tenant (modelo o id).
   */
  public function forTenant(Tenant|int $tenant): static
  {
    $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

    return $this->state(fn () => ['tenant_id' => $tenantId]);
  }

  /**
   * Fix token explicitly.
   */
  public function withToken(string $token): static
  {
    if (strlen($token) > 128) {
      throw new InvalidArgumentException('Invitation token exceeds 128 characters.');
    }
    return $this->state(fn () => ['token' => $token]);
  }

  /**
   * Assign rol per slug (validate against DB).
   *
   * Require BaseRolesSeeder had ran.
   */
  public function forRoleSlug(string $slug): static
  {
    return $this->afterMaking(function (Invitation $inv) use ($slug): void {
      $roleId = DB::table('roles')->where('slug', $slug)->value('id');
      if (!$roleId) {
        throw new InvalidArgumentException(
          sprintf('Role slug "%s" not found. Did you run BaseRolesSeeder?', $slug)
        );
      }
      $inv->role_id = (int) $roleId;
    });
  }

  /**
   * Change status with validation.
   */
  public function setStatus(string $status): static
  {
    if (!in_array($status, self::ALLOWED_STATUSES, true)) {
      throw new InvalidArgumentException(
        sprintf('Invalid status "%s". Allowed: %s', $status, implode(', ', self::ALLOWED_STATUSES))
      );
    }
    return $this->state(fn () => ['status' => $status]);
  }

  /**
   * Adjust expiration datetime.
   */
  public function expiringAt(DateTimeInterface $when): static
  {
    return $this->state(fn () => ['expires_at' => $when]);
  }
}
