<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Invitations;

use App\Models\Invitation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    $res->assertStatus(302); // o $res->assertStatus(422) si es API
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

    // If your controller returns JSON 200, change this to assertStatus(200).
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
    $res->assertStatus(302); // adjust to 200 if API-style

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
}
