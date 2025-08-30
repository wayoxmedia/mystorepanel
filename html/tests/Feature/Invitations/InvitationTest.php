<?php

declare(strict_types=1);

namespace Tests\Feature\Invitations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\TestHelpers;
use Tests\TestCase;

final class InvitationTest extends TestCase
{
  use RefreshDatabase;
  use TestHelpers;

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
  public function test_accept_show_displays_page_for_valid_token(): void
  {
    $tenantId = $this->seedTenant(['user_seat_limit' => 5]);
    $inv = $this->seedInvitation($tenantId);

    $res = $this->get(route(self::R_ACCEPT_SHOW, ['token' => $inv['token']]));
    $res->assertStatus(200);
  }

  /**
   * Define payload and status (422/302) for accept store, then enable.
   */
  public function test_accept_store_requires_valid_payload(): void
  {
    $this->markTestIncomplete('Confirma payload y status esperado (422/302) para invitations.accept.store, luego quitamos esta línea.');
    $tenantId = $this->seedTenant();
    $inv = $this->seedInvitation($tenantId);

    $res = $this->post(route(self::R_ACCEPT_STORE), ['token' => $inv['token']]);
    // Adjust:
    $res->assertStatus(422); // API-style
    // or:
    $res->assertStatus(302); // web redirect on validation error
  }

  /**
   * Resend likely requires auth/policy; set actingAs once we confirm guard/role.
   */
  public function test_admin_can_resend_invitation(): void
  {
    $this->markTestIncomplete('Define actingAs(guard/rol) y status esperado, luego habilitamos.');
    $tenantId = $this->seedTenant();
    $inv = $this->seedInvitation($tenantId);

    // Example if guard is 'web' and model is App\Models\User (we resolve from config):
    $admin = $this->seedAdminUser($tenantId);
    $adminModel = $this->findUserModel($admin['id']);
    $this->assertNotNull($adminModel, 'Admin user model not found; check auth.providers.users.model.');

    // $this->actingAs($adminModel, 'web');
    // $res = $this->post(route(self::R_RESEND, ['invitation' => $inv['id']]));
    // $res->assertOk();
  }

  /**
   * Cancel likely requires auth/policy; same as above.
   */
  public function test_admin_can_cancel_invitation(): void
  {
    $this->markTestIncomplete('Define guard/role for actingAs and final DB state (status=canceled), then remove this line.');

    $tenantId = $this->seedTenant();
    $inv = $this->seedInvitation($tenantId);

    $admin = $this->seedAdminUser($tenantId);
    $adminModel = $this->findUserModel($admin['id']);
    $this->assertNotNull($adminModel, 'Admin user model not found; check auth.providers.users.model.');

    // $this->actingAs($adminModel, 'web');
    // $res = $this->post(route(self::R_CANCEL, ['invitation' => $inv['id']]));
    // $res->assertOk();

    // if your implementation marks status in DB:
    // $this->assertDatabaseHas('invitations', ['id' => $inv['id'], 'status' => 'canceled']);
  }

  /**
   * Ensure index requires auth; keep incomplete until we confirm guard.
   */
  public function test_admin_invitations_index_requires_auth(): void
  {
    $this->markTestIncomplete('Confirm if index is protected. If so, assert redirect/401 accordingly.');

    if (! Schema::hasTable('invitations')) {
      $this->markTestSkipped('invitations table not found.');
    }

    // $res = $this->get(route(self::R_INDEX));
    // $res->assertStatus(302); // or 401 for API
  }
}
