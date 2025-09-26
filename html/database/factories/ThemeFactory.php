<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Theme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * ThemeFactory
 *
 * Purpose:
 * Generate per-tenant Theme instances with a realistic JSON config payload and
 * a slug unique within the tenant namespace. Includes helpful states for active,
 * draft, and archived themes.
 *
 * Assumptions:
 * - The Theme model/table includes: tenant_id, name, slug, status, description, config.
 * - A Tenant factory exists and can create a tenant on demand.
 *
 * Notes:
 * - Slug uniqueness is enforced at (tenant_id, slug) level in the DB. To reduce
 *   collision risk in tests/seeding, a short random suffix is appended.
 * - Adjust the JSON structure in "config" to match your design token schema.
 */
class ThemeFactory extends Factory
{
  /** @var class-string<\App\Models\Theme> */
  protected $model = Theme::class;

  /**
   * Define the model's default state.
   *
   * @return array<string, mixed>
   */
  public function definition(): array
  {
    $baseName = $this->faker->unique()->words(2, true) . ' Theme';
    $slug     = Str::slug($baseName) . '-' . Str::lower(Str::random(6));

    return [
      'tenant_id'   => Tenant::factory(),
      'name'        => $baseName,
      'slug'        => $slug,
      'status'      => $this->faker->randomElement(['active', 'draft', 'archived']),
      'description' => $this->faker->optional()->sentence(12),

      // Example design tokens / visual configuration.
      'config'      => [
        'brand' => [
          'primary'   => $this->faker->hexColor(),
          'secondary' => $this->faker->hexColor(),
          'accent'    => $this->faker->hexColor(),
          'logo'      => [
            'url'    => $this->faker->optional()->imageUrl(320, 80, 'logo', true),
            'alt'    => $this->faker->optional()->words(3, true),
            'height' => 48,
          ],
        ],
        'typography' => [
          'heading' => [
            'family' => $this->faker->randomElement(['Inter', 'Roboto', 'Open Sans', 'Lato']),
            'weight' => 600,
          ],
          'body' => [
            'family' => $this->faker->randomElement(['Inter', 'Roboto', 'Open Sans', 'Lato']),
            'weight' => 400,
            'size'   => 16,
          ],
        ],
        'layout' => [
          'container' => 'lg', // sm|md|lg|xl
          'radius'    => 8,
          'shadow'    => true,
        ],
        'features' => [
          'darkMode'    => $this->faker->boolean(30),
          'showNewsletter' => $this->faker->boolean(60),
        ],
      ],
    ];
  }

  /**
   * Mark the theme as active.
   *
   * @return $this
   */
  public function active(): self
  {
    return $this->state(fn () => ['status' => 'active']);
  }

  /**
   * Mark the theme as draft.
   *
   * @return $this
   */
  public function draft(): self
  {
    return $this->state(fn () => ['status' => 'draft']);
  }

  /**
   * Mark the theme as archived.
   *
   * @return $this
   */
  public function archived(): self
  {
    return $this->state(fn () => ['status' => 'archived']);
  }

  /**
   * Associate with a specific tenant.
   *
   * @param  Tenant|int  $tenant
   * @return $this
   */
  public function forTenant(Tenant|int $tenant): self
  {
    $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

    return $this->state(fn () => ['tenant_id' => $tenantId]);
  }
}
