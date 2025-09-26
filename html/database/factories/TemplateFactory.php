<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * TemplateFactory
 *
 * Purpose:
 * Generate global template definitions (structure/blueprints) with a unique slug.
 *
 * Assumptions:
 * - The Template model/table includes:
 *   - name,
 *   - slug,
 *   - is_active,
 *   - version,
 *   - description,
 *   - img_url.
 *
 * Notes:
 * - Keeping it minimal for now.
 */
class TemplateFactory extends Factory
{
  /** @var class-string<Template> */
  protected $model = Template::class;

  /**
   * Define the model's default state.
   *
   * @return array<string,mixed>
   */
  public function definition(): array
  {
    $baseName = $this->faker->unique()->words(2, true) . ' Template';

    return [
      'name'   => $baseName,
      'slug'   => Str::slug($baseName) . '-' . Str::lower(Str::random(6)),
      'is_active'   => $this->faker->boolean(85),
      'version'     => $this->faker->randomElement(['1.0', '1.1', '2.0']),
      'description' => $this->faker->optional()->sentence(12),
      'img_url'     => $this->faker->optional()->imageUrl(
        640,
        360,
        'abstract',
        true,
        'template'
      ),
    ];
  }

  /**
   * Mark template as active.
   *
   * @return $this
   */
  public function active(): self
  {
    return $this->state(fn () => ['is_active' => true]);
  }

  /**
   * Mark template as inactive.
   *
   * @return $this
   */
  public function inactive(): self
  {
    return $this->state(fn () => ['is_active' => false]);
  }

  /**
   * Force a specific version label.
   *
   * @param  string  $version
   * @return $this
   */
  public function ofVersion(string $version): self
  {
    return $this->state(fn () => ['version' => $version]);
  }
}
