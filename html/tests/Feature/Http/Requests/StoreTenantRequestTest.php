<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Requests;

use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * StoreTenantRequestTest
 *
 * Purpose:
 * - Validate happy-path and common validation failures for StoreTenantRequest.
 * - Ensure authorization (policy create) is enforced.
 *
 * Assumptions:
 * - Factories exist for User, Tenant, Template.
 * - Auth middleware is present; we use actingAs to bypass it.
 */
class StoreTenantRequestTest extends TestCase
{
  use RefreshDatabase;

  public function testAllowsAuthorizedUserToCreateTenant(): void
  {
    /** @var User $user */
    $user = User::factory()->create(['status' => 'active']);

    /** @var Template $template */
    $template = Template::factory()->create();

    $payload = [
      'name'            => 'Acme Inc.',
      'slug'            => 'acme',
      'status'          => 'active',
      'template_id'     => $template->id,
      'user_seat_limit' => 5,
      'billing_email'   => 'billing@acme.com',
      'timezone'        => 'UTC',
      'locale'          => 'en',
      'plan'            => 'pro',
      'trial_ends_at'   => now()->addDays(14)->toISOString(),
      'primary_domain'  => 'acme.example.test',
    ];

    $this->actingAs($user)
      ->json('POST', route('admin.tenants.store'), $payload)
      ->assertCreated()
      ->assertJsonPath('slug', 'acme');

    $this->assertDatabaseHas('tenants', [
      'slug'           => 'acme',
      'primary_domain' => 'acme.example.test',
    ]);
  }

  /**
   * Test that invalid payloads are rejected with 422 and appropriate error keys.
   *
   * @param  array<string,mixed>  $payload
   * @param  array<string>        $expectedErrorKeys
   */
  #[DataProvider('invalidStorePayloadProvider')]
  public function testRejectsInvalidPayloads(array $payload, array $expectedErrorKeys): void
  {
    /** @var User $user */
    $user = User::factory()->create(['status' => 'active']);

    $resp = $this->actingAs($user)
      ->json('POST', route('admin.tenants.store'), $payload)
      ->assertStatus(422)
      ->json();

    foreach ($expectedErrorKeys as $key) {
      $this->assertArrayHasKey($key, $resp['errors'] ?? []);
    }
  }

  /**
   * @return array<int, array{array<string,mixed>, array<string>}>
   */
  public static function invalidStorePayloadProvider(): array
  {
    return [
      'missing required fields' => [
        [
          // empty
        ],
        ['name', 'slug', 'status', 'user_seat_limit'],
      ],
      'invalid domain and seat limit' => [
        [
          'name' => 'X',
          'slug' => 'x',
          'status' => 'active',
          'user_seat_limit' => 0,
          'primary_domain' => 'http://bad.url/with/path',
        ],
        ['user_seat_limit', 'primary_domain'],
      ],
      'invalid locale format' => [
        [
          'name' => 'X',
          'slug' => 'x',
          'status' => 'active',
          'user_seat_limit' => 5,
          'locale' => 'english',
        ],
        ['locale'],
      ],
    ];
  }

  public function testRequiresAuthentication(): void
  {
    $this->json('POST', route('admin.tenants.store'), [])
      ->assertStatus(401);
  }

  public function testSlugMustBeUnique(): void
  {
    /** @var User $user */
    $user = User::factory()->create(['status' => 'active']);

    Tenant::factory()->create(['slug' => 'dup']);

    $payload = [
      'name'            => 'Dup Co',
      'slug'            => 'dup',
      'status'          => 'active',
      'user_seat_limit' => 3,
    ];

    $this->actingAs($user)
      ->json('POST', route('admin.tenants.store'), $payload)
      ->assertStatus(422)
      ->assertJsonValidationErrors(['slug']);
  }
}
