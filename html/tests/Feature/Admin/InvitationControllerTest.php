<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\WithTenantSession;
use Tests\TestCase;

/**
 * Tests for InvitationController in admin area.
 *
 * Note: these tests assume the 'tenant.manager' middleware is active unless
 * specifically disabled. This ensures we test the full stack including middleware
 * and policies/gates.
 */
final class InvitationControllerTest extends TestCase
{
  use RefreshDatabase;
  use WithTenantSession;

  /**
   * Route names from `php artisan route:list | grep invitation`
   */
  private const R_INDEX        = 'admin.invitations.index';
  private const R_CANCEL       = 'admin.invitations.cancel';
  private const R_RESEND       = 'admin.invitations.resend';

  /**
   * Resend likely requires auth/policy; set actingAs once we confirm guard/role.
   * @return void
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
   * @return void
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
   * @return void
   */
  public function testAdminInvitationsIndexRequiresAuth(): void
  {
    $res = $this->get(route(self::R_INDEX));
    $res->assertStatus(302); // redirect to login page.
  }

  /**
   * With verified user (guard web) and only disabling 'tenant.manager' should load 200
   * @return void
   */
  public function testAdminInvitationsIndexLoadsForVerifiedTenantAdmin(): void
  {
    $this->actingAsTenantAdmin();

    $res = $this->get(route(self::R_INDEX));
    $res->assertStatus(200);
  }

  /**
   * First, we need to check the actual strategy, so the tests have the right expectations.
   * The strategy can be 'send' or 'queue'.
   *
   * After resending an invitation, the send_count should increment or not
   * depending on whether the cooldown period has passed.
   *
   * If it has not passed, no email should be sent, send_count should remain the same.
   *
   * If it has passed, an email should be sent, send_count should increment by 1,
   * last_sent_at should be updated to a later time.
   * @return void
   */
  public function testAdminResendUpdatesOrNotSendCountAndLastSentAt(): void
  {
    [$tenant] = $this->actingAsTenantAdmin();

    $isQueue = config('mystore.mail.dispatch') === 'queue';
    $this->assertDatabaseCount('invitations', 0);

    // Create a fresh invitation (send_count = 0)
    $inv = Invitation::factory()
      ->pending()
      ->forTenant($tenant)
      ->create([
        'send_count'   => 0,
        'last_sent_at' => null,
      ]);
    /*
    dump('tx level', DB::transactionLevel());
    dump('current db', DB::selectOne('select database() as db')->db);
    dump('invitation connection', (new \App\Models\Invitation())->getConnectionName());
    */

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

    if ($isQueue) {
      // In 'queue' mode, email is not sent immediately.
      Mail::assertQueued(InvitationMail::class, 1);
      Mail::assertNothingSent();
    } else {
      // In 'send' mode, no queueing happens; email is sent immediately.
      Mail::assertSent(InvitationMail::class, 1);
      Mail::assertNothingQueued();
    }

    // Fetch fresh row and capture values for later comparison
    $fresh1 = DB::table('invitations')
      ->where('id', $inv->id)
      ->first();
    $this->assertNotNull($fresh1);
    $this->assertSame(1, (int) $fresh1->send_count);
    $this->assertNotNull($fresh1->last_sent_at);
    $lastSentAt1 = Carbon::parse($fresh1->last_sent_at);

    // --- Second resend within cooldown: should NOT send and NOT bump ---
    Mail::fake(); // Calling it again resets fake window

    $res2 = $this->post(
      route(
        self::R_RESEND,
        ['invitation' => $inv->id]
      )
    );
    $res2->assertStatus(302);

    // Nothing queued and nothing sent in this second window
    Mail::assertQueued(InvitationMail::class, 0);
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

    if ($isQueue) {
      Mail::assertQueued(InvitationMail::class, 1);
      Mail::assertNothingSent();
    } else {
      Mail::assertSent(InvitationMail::class, 1);
      Mail::assertNothingQueued();
    }

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
   * @return void
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

  /** -----------------------------
   *  Index access by role matrix
   * ------------------------------*/

  /**
   * Test access to index by various roles.
   *
   * @param  string  $roleSlug    Role slug to test (e.g. 'tenant_admin').
   * @param  boolean $shouldAllow Whether access should be allowed.
   * @return void
   */
  #[DataProvider('roleMatrix')]
  public function testIndexAccessByRole(string $roleSlug, bool $shouldAllow): void
  {
    $tenant = Tenant::factory()->active()->create();

    /** @var User $user */
    $user = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->{$this->roleWrapper($roleSlug)}()
      ->create();

    // We want to test middleware + policies/gates, so DO NOT disable tenant.manager.
    $this->withTenantSession($tenant);
    $this->actingAs($user, 'web');

    $res = $this->get(route(self::R_INDEX));

    if ($shouldAllow) {
      $this->assertContains(
        $res->getStatusCode(),
        [200, 302],
        'Allowed role should not be blocked (expect 200 or a normal redirect).'
      );
    } else {
      // Typically 403 for forbidden (or 302 to login if guard bounced).
      $this->assertContains(
        $res->getStatusCode(),
        [403, 302],
        'Forbidden role should not get a 200.'
      );
    }
  }

  /**
   * Role matrix for testing access.
   *
   * Each entry: [role_slug, should_allow_bool]
   * @return array<int, array{0: string, 1: bool}>
   */
  public static function roleMatrix(): array
  {
    return [
      'tenant_admin allowed' => ['tenant_admin', true],
      'tenant_editor denied' => ['tenant_editor', false],
      'tenant_viewer denied' => ['tenant_viewer', false],
    ];
  }

  /** -----------------------------
   *  Verified middleware behavior
   * ------------------------------*/

  /**
   * Index requires verified email; unverified should be redirected (302).
   * @return void
   */
  public function testIndexRequiresVerifiedEmail(): void
  {
    $tenant = Tenant::factory()->active()->create();

    $user = User::factory()
      ->unverified()   // explicitly unverified
      ->active()
      ->forTenant($tenant)
      ->asTenantAdmin()
      ->create();

    $this->withTenantSession($tenant);
    // Do NOT disable tenant.manager; we want real middleware stack
    $this->actingAs($user, 'web');

    $res = $this->get(route(self::R_INDEX));
    // Laravel's 'verified' middleware redirects to verification.notice (302)
    $this->assertSame(302, $res->getStatusCode(), 'Unverified user should be redirected by verified middleware.');
  }

  /** -----------------------------
   *  POST actions by role matrix
   * ------------------------------*/

  /**
   * @param  string  $roleSlug
   * @param  boolean $shouldAllow
   * @return void
   */
  #[DataProvider('roleMatrix')]
  public function testResendAccessByRole(string $roleSlug, bool $shouldAllow): void
  {
    $tenant = Tenant::factory()->active()->create();
    $inv = Invitation::factory()->pending()->forTenant($tenant)->create();

    $user = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->{$this->roleWrapper($roleSlug)}()
      ->create();

    $this->withTenantSession($tenant);
    $this->actingAs($user, 'web');

    $res = $this->post(route(self::R_RESEND, ['invitation' => $inv->id]));

    if ($shouldAllow) {
      $this->assertContains($res->getStatusCode(), [200, 302], 'Allowed role should be able to resend.');
    } else {
      $this->assertContains($res->getStatusCode(), [403, 302], 'Forbidden role should not be able to resend.');
    }
  }

  /**
   * Cancel by role matrix
   *
   * @param  string  $roleSlug
   * @param  boolean $shouldAllow
   * @return void
   */
  #[DataProvider('roleMatrix')]
  public function testCancelAccessByRole(string $roleSlug, bool $shouldAllow): void
  {
    $tenant = Tenant::factory()->active()->create();
    $inv = Invitation::factory()->pending()->forTenant($tenant)->create();

    $user = User::factory()
      ->verified()
      ->active()
      ->forTenant($tenant)
      ->{$this->roleWrapper($roleSlug)}()
      ->create();

    $this->withTenantSession($tenant);
    $this->actingAs($user, 'web');

    $res = $this->post(route(self::R_CANCEL, ['invitation' => $inv->id]));

    if ($shouldAllow) {
      $this->assertContains($res->getStatusCode(), [200, 302], 'Allowed role should be able to cancel.');
      // If cancel is allowed, DB should reflect status change
      $this->assertDatabaseHas('invitations', [
        'id' => $inv->id,
        'status' => 'cancelled',
      ]);
    } else {
      $this->assertContains($res->getStatusCode(), [403, 302], 'Forbidden role should not be able to cancel.');

      // Ensure DB didn't change when forbidden
      $this->assertDatabaseHas('invitations', [
        'id' => $inv->id,
        'status' => 'pending',
      ]);
    }
  }

  /**
   * Helper to map role slug -> UserFactory wrapper method name
   * @param  string $slug
   * @throws InvalidArgumentException On unsupported slug.
   * @return string
   */
  private function roleWrapper(string $slug): string
  {
    return match ($slug) {
      'tenant_admin' => 'asTenantAdmin',
      'tenant_editor' => 'asTenantEditor',
      'tenant_viewer' => 'asTenantViewer',
      default => throw new InvalidArgumentException("Unsupported role slug: {$slug}"),
    };
  }

  public function testResendQueuesWhenDispatchIsQueue(): void
  {
    [$tenant] = $this->actingAsTenantAdmin();
    $inv = Invitation::factory()->pending()->forTenant($tenant)->create([
      'send_count' => 0, 'last_sent_at' => null,
    ]);

    Config::set('mystore.mail.dispatch', 'queue');
    Config::set('mystore.mail.queue', 'mail');

    Mail::fake();

    $res = $this->post(route(self::R_RESEND, ['invitation' => $inv->id]));
    $res->assertStatus(302);

    Mail::assertQueued(InvitationMail::class, 1);
    Mail::assertNothingSent();
  }

  public function testResendSendsWhenDispatchIsSend(): void
  {
    [$tenant] = $this->actingAsTenantAdmin();
    $inv = Invitation::factory()->pending()->forTenant($tenant)->create([
      'send_count' => 0, 'last_sent_at' => null,
    ]);

    Config::set('mystore.mail.dispatch', 'send');
    $this->assertSame('send', config('mystore.mail.dispatch'));

    Mail::fake();

    $res = $this->post(route(self::R_RESEND, ['invitation' => $inv->id]));
    $res->assertStatus(302);

    Mail::assertSent(InvitationMail::class, 1);
    // For completeness: when sending sync, it should NOT be queued
    if (method_exists(Mail::class, 'assertNotQueued')) {
      Mail::assertNotQueued(InvitationMail::class);
    }
  }
}
