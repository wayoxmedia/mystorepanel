<?php

namespace Tests\Feature\Webhooks;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Http\Middleware\VerifySvixSignature;

/**
 * Verifies that our /api/webhooks/resend endpoint persists bounce/complaint
 * events into the `subscribers` table as expected.
 *
 * NOTE: We disable the Svix middleware in these tests because we are not
 * crafting real signatures. The middleware itself should be tested separately.
 */
class ResendWebhookHandlerTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    // Ensure app URL exists for any URL building (not used here but harmless).
    config(['app.url' => config('app.url', 'http://mystorepanel.test')]);

    // Disable the Svix signature middleware for these tests.
    $this->withoutMiddleware(VerifySvixSignature::class);
  }

  /**
   * Test that a permanent bounce event creates or updates a subscriber
   * and unsubscribes them.
   * @return void
   */
  public function testPermanentBounceCreatesOrUpdatesSubscriberAndUnsubscribes(): void
  {
    $tenant = Tenant::factory()->create();
    $tenantId = $tenant->id;
    $email    = 'bounced.user@example.test';

    $payload = [
      'type'      => 'email.bounced',
      'email'     => $email,
      'tenant_id' => $tenantId,
      'tags'      => ['tenant_id' => (string) $tenantId],
      'data'      => [
        'email_id'     => 'em_123',
        'broadcast_id' => null,
        'bounce'       => [
          'type'    => 'Permanent',
          'subType' => 'General',
          'message' => 'User Unknown',
        ],
      ],
    ];

    $res = $this->postJson('/api/webhooks/resend', $payload);
    $res->assertOk()->assertJson(['status' => 'ok']);

    $row = DB::table('subscribers')
      ->where('address', $email)
      ->where('address_type', 'e')
      ->where('tenant_id', $tenantId)
      ->first();

    $this->assertNotNull($row, 'Subscriber row should be created');
    $this->assertSame(0, (int) $row->active, 'Hard bounce must deactivate the subscriber');
    $this->assertNotNull($row->unsubscribed_at, 'Unsubscribed timestamp should be set');
    $this->assertSame('bounce', $row->unsubscribe_source);
    $this->assertGreaterThanOrEqual(1, (int) $row->bounce_count);

    // Meta should contain an events array with an entry for email.bounced
    $meta = json_decode($row->unsubscribe_meta ?? '{}', true);
    $this->assertIsArray($meta);
    $this->assertArrayHasKey('events', $meta);
    $this->assertNotEmpty($meta['events']);
    $this->assertSame('email.bounced', $meta['events'][array_key_last($meta['events'])]['event'] ?? null);
  }

  /**
   * Test that a complaint event creates or updates a subscriber
   * and unsubscribes them.
   * @return void
   */
  public function testComplaintCreatesOrUpdatesSubscriberAndUnsubscribes(): void
  {
    $tenant = Tenant::factory()->create();
    $tenantId = $tenant->id;
    $email    = 'complaint.user@example.test';

    $payload = [
      'type'      => 'email.complained',
      'email'     => $email,
      'tenant_id' => $tenantId,
      'tags'      => ['tenant_id' => (string) $tenantId],
      'data'      => [
        'email_id'     => 'em_456',
        'broadcast_id' => 'br_789',
      ],
    ];

    $res = $this->postJson('/api/webhooks/resend', $payload);
    $res->assertOk()->assertJson(['status' => 'ok']);

    $row = DB::table('subscribers')
      ->where('address', $email)
      ->where('address_type', 'e')
      ->where('tenant_id', $tenantId)
      ->first();

    $this->assertNotNull($row, 'Subscriber row should be created');
    $this->assertSame(0, (int) $row->active, 'Complaint must deactivate the subscriber');
    $this->assertNotNull($row->unsubscribed_at, 'Unsubscribed timestamp should be set');
    $this->assertNotNull($row->complained_at, 'Complained timestamp should be set');
    $this->assertSame('complaint', $row->unsubscribe_source);

    $meta = json_decode($row->unsubscribe_meta ?? '{}', true);
    $this->assertIsArray($meta);
    $this->assertArrayHasKey('events', $meta);
    $this->assertNotEmpty($meta['events']);
    $this->assertSame('email.complained', $meta['events'][array_key_last($meta['events'])]['event'] ?? null);
  }
}
