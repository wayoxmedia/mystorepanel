<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
  /** @var class-string<Tenant> */
  protected $model = Tenant::class;

  public function definition(): array
  {
    $name = $this->faker->unique()->company();

    return [
      'name'            => $name,
      'slug'            => Str::slug($name) . '-' . Str::lower(Str::random(6)),
      'primary_domain'  => null,
      'allowed_origins' => null, // keep null unless you want JSON here
      'status'          => 'active', // enum: active|suspended|pending (default pending in DB)
      'user_seat_limit' => $this->faker->numberBetween(2, 8), // DB default is 2
      'template_id'     => null,
      'template_slug'   => 'default',
      'email'           => $this->faker->safeEmail(),
      'phone'           => $this->faker->optional()->e164PhoneNumber(),
      'created_at'      => now(),
      'updated_at'      => now(),
      'deleted_at'      => null,
    ];
  }

  /**
   * Mark tenant as active.
   */
  public function active(): static
  {
    return $this->state(fn () => ['status' => 'active']);
  }

  /**
   * Mark tenant as suspended.
   */
  public function suspended(): static
  {
    return $this->state(fn () => ['status' => 'suspended']);
  }

  /**
   * Mark tenant as pending.
   */
  public function pending(): static
  {
    return $this->state(fn () => ['status' => 'pending']);
  }

  /**
   * Convenience state to set a specific seat limit.
   */
  public function withSeatLimit(int $limit): static
  {
    return $this->state(fn () => ['user_seat_limit' => max(0, $limit)]);
  }

  /**
   * Convenience state to assign a template slug.
   */
  public function withTemplate(string $templateSlug, ?int $templateId = null): static
  {
    return $this->state(fn () => [
      'template_slug' => $templateSlug,
      'template_id'   => $templateId,
    ]);
  }
}
