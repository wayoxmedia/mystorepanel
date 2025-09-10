<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Invitations;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\WithTenantSession;
use Tests\TestCase;

final class InvitationTest extends TestCase
{
  use RefreshDatabase;
  use WithTenantSession;

  /**
   * Route names from `php artisan route:list | grep invitation`
   */
  private const R_INDEX        = 'admin.invitations.index';
  private const R_CANCEL       = 'admin.invitations.cancel';
  private const R_RESEND       = 'admin.invitations.resend';
  private const R_ACCEPT_SHOW  = 'invitations.accept';
  private const R_ACCEPT_STORE = 'invitations.accept.store';

  /**
   * Public page should load for a valid token (adjust status if your controller redirects).
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
   * Resend likely requires auth/policy; set actingAs once we confirm guard/role.
   */
  public function testAdminCanResendInvitation(): void
  {
    [$tenant] = $this->actingAsTenantAdmin();

    $inv = Invitation::factory()
      ->pending()
      ->forTenant($tenant)
      ->create();

    $res = $this->post(route(self::R_RESEND, ['invitation' => $inv->id]));

    $res->assertStatus(302);
  }

  /**
   * Cancel likely requires auth/policy; same as above.
   */
  public function testAdminCanCancelInvitation(): void
  {
    [$tenant] = $this->actingAsTenantAdmin();

    $inv = Invitation::factory()
      ->pending()
      ->forTenant($tenant)
      ->create();

    $res = $this->post(route(self::R_CANCEL, ['invitation' => $inv->id]));
    $res->assertStatus(302);

    $this->assertDatabaseHas('invitations', [
      'id'     => $inv->id,
      'status' => 'cancelled',
    ]);
  }

  /**
   * Protected: without auth should redirect to login page (302).
   */
  public function testAdminInvitationsIndexRequiresAuth(): void
  {
    $res = $this->get(route(self::R_INDEX));
    $res->assertStatus(302); // redirect to login page.
  }

  /**
   * With verified user (guard web) and only disabling 'tenant.manager' should load 200
   */
  public function testAdminInvitationsIndexLoadsForVerifiedTenantAdmin(): void
  {
    $this->actingAsTenantAdmin();

    $res = $this->get(route(self::R_INDEX));
    $res->assertStatus(200);
  }

  /**
   * After resending an invitation, the send_count should increment
   * and last_sent_at should be updated. This test checks those fields.
   */
  public function testAdminResendUpdatesSendCountAndLastSentAt(): void
  {
    [$tenant] = $this->actingAsTenantAdmin();

    $this->assertSame('mysql_testing', config('database.default'));
    $this->assertSame('mystorepanel_test', config('database.connections.mysql_testing.database'));
    $this->assertDatabaseCount('invitations', 0);

    // Create a fresh invitation (send_count = 0)
    $inv = Invitation::factory()
      ->pending()
      ->forTenant($tenant)
      ->create([
        'send_count'   => 0,
        'last_sent_at' => null,
      ]);
    dump('tx level', DB::transactionLevel());
    dump('current db', DB::selectOne('select database() as db')->db);
    dump('invitation connection', (new \App\Models\Invitation())->getConnectionName());


    $cooldown = (int) config('mystore.invitations.cooldown_minutes', 5);

    // --- First resend: should send an email and bump counters ---
    Mail::fake();

    $res1 = $this->post(
      route(
        self::R_RESEND,
        ['invitation' => $inv->id]
      )
    );
    $res1->assertStatus(302);

    // Assert email was sent exactly once in this window
    Mail::assertSent(InvitationMail::class, 1);

    // Fetch fresh row and capture values for later comparison
    $fresh1 = DB::table('invitations')
      ->where('id', $inv->id)
      ->first();
    $this->assertNotNull($fresh1);
    $this->assertSame(1, (int) $fresh1->send_count);
    $this->assertNotNull($fresh1->last_sent_at);
    $lastSentAt1 = Carbon::parse($fresh1->last_sent_at);

    // --- Second resend within cooldown: should NOT send and NOT bump ---
    Mail::fake(); // reset fake window

    $res2 = $this->post(
      route(
        self::R_RESEND,
        ['invitation' => $inv->id]
      )
    );
    $res2->assertStatus(302);

    // No new emails in this second window
    Mail::assertNothingSent();

    $fresh2 = DB::table('invitations')
      ->where('id', $inv->id)
      ->first();
    $this->assertNotNull($fresh2);
    $this->assertSame(
      1,
      (int) $fresh2->send_count,
      'send_count should remain 1 during cooldown.'
    );
    $this->assertNotNull($fresh2->last_sent_at);
    $lastSentAt2 = Carbon::parse($fresh2->last_sent_at);
    $this->assertTrue(
      $lastSentAt2->equalTo($lastSentAt1),
      'last_sent_at should not change during cooldown.'
    );

    // --- Travel past cooldown and try again: should send and bump ---
    $this->travel($cooldown + 1)->minutes();
    Mail::fake(); // new window

    $res3 = $this->post(
      route(
        self::R_RESEND,
        ['invitation' => $inv->id]
      )
    );
    $res3->assertStatus(302);

    Mail::assertSent(InvitationMail::class, 1);

    $fresh3 = DB::table('invitations')
      ->where('id', $inv->id)
      ->first();
    $this->assertNotNull($fresh3);
    $this->assertSame(
      2,
      (int) $fresh3->send_count,
      'send_count should increment after cooldown.'
    );
    $this->assertNotNull($fresh3->last_sent_at);
    $lastSentAt3 = Carbon::parse($fresh3->last_sent_at);
    $this->assertTrue(
      $lastSentAt3->greaterThan($lastSentAt2),
      'last_sent_at should move forward after cooldown.'
    );
  }

  /**
   * Cancelling an already cancelled invitation should be idempotent
   * and not change the status or cause an error.
   */
  public function testAdminCancelSetsStatusCancelledAndIsIdempotent(): void
  {
    [$tenant] = $this->actingAsTenantAdmin();

    $inv = Invitation::factory()
      ->pending()
      ->forTenant($tenant)
      ->create();

    // First cancel
    $res1 = $this->post(
      route(
        self::R_CANCEL,
        ['invitation' => $inv->id]
      )
    );
    $res1->assertStatus(302);

    $this->assertDatabaseHas('invitations', [
      'id'     => $inv->id,
      'status' => 'cancelled',
    ]);

    // Second cancel (should remain 'cancelled' and not error)
    $res2 = $this->post(
      route(
        self::R_CANCEL,
        ['invitation' => $inv->id]
      )
    );
    $res2->assertStatus(302);

    $this->assertDatabaseHas('invitations', [
      'id'     => $inv->id,
      'status' => 'cancelled',
    ]);
  }
}
