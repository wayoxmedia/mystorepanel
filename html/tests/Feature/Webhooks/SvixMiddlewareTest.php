<?php

namespace Tests\Feature\Webhooks;

use Tests\TestCase;

/**
 * SvixMiddlewareTest
 *
 * Tests for the Svix signature verification middleware.
 *
 * Note: These tests assume the middleware is correctly registered in the HTTP kernel
 * and applied to the relevant routes (e.g., /api/webhooks/resend).
 */
class SvixMiddlewareTest extends TestCase
{
  /**
   * Test that requests without Svix headers are rejected.
   * @return void
   */
  public function testRejectsRequestsWithoutSvixHeaders(): void
  {
    // Ensure secret is configured for the middleware path
    config(['services.resend.webhook_secret' => env(
      'RESEND_WEBHOOK_SECRET',
      'whsec_test')]
    );

    $res = $this->postJson('/api/webhooks/resend'); // no headers

    $res->assertStatus(400);
    $res->assertJson(['message' => 'missing signature headers']);
  }
}
