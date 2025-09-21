<?php

use App\Http\Controllers\Admin\TenantUsersController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\PagesController;
use App\Http\Controllers\Api\SiteResolveController;
use App\Http\Controllers\Api\SubscriberController;
use App\Http\Controllers\Api\TenantPagesController;
use App\Http\Controllers\Auth\ReauthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

require_once app_path('Helpers/ThrottleHelper.php');

/**
 *| API Routes
 *|-
 *
 * This service is headless (API-only)
 *
 * This file consolidates existing endpoints and the JWT auth contract
 * consumed by web-templates (frontend). Endpoints preserved as-is, grouped
 * under an 'auth' prefix with route names added for consistency.
 */
// --- Auth (JWT) ---
Route::prefix('auth')->name('api.auth.')->group(function () {
  // Public login (no middleware other than throttle)
  Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login');

  // Protected by JWT guard (api)
  // Once everyone has verified email         👇🏼
  // Route::middleware(['auth:api', 'active', 'email.verified'])
  Route::middleware(['auth:api', 'active'])
    ->group(function () {
      Route::get('/me', [AuthController::class, 'me'])
        ->name('me');
      Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');
      Route::post('/refresh', [AuthController::class, 'refresh'])
        ->name('refresh');
    });
});

// --- Contact Routes ---
// NOTE: Kept public for now (used by jQuery AJAX).
// Store requests have a 6 req/min IP-based limit to reduce abuse (Throttle abuse, e.g. spam).
Route::post('/contact-form', [ContactController::class, 'store'])
  ->middleware('throttle:contact-store')
  ->name('api.contacts.store');
Route::get('/contacts/{id}', [ContactController::class, 'show'])
  ->name('api.contacts.show');
Route::get('/contacts', [ContactController::class, 'index'])
  ->name('api.contacts.index');

// --- Subscriber Routes ---
Route::post('/subscribe-form', [SubscriberController::class, 'store'])
  ->middleware('throttle:subscribe-store')
  ->name('api.subscribers.store');
Route::get('/subscribers/{id}', [SubscriberController::class, 'show'])
  ->name('api.subscribers.show');
Route::get('/subscribers', [SubscriberController::class, 'index'])
  ->name('api.subscribers.index');

// --- S2S (server-to-server) ---
Route::middleware('s2s')->group(function () {
  Route::get('/sites/resolve', [SiteResolveController::class, 'resolveByDomain'])
    ->name('api.s2s.sites.resolve');
  Route::get('/tenants/{tenant}/pages', [TenantPagesController::class, 'show'])
    ->name('api.s2s.tenants.pages.show');
});

// --- Public (tenant pages, ping) ---
Route::get('/ping', fn() => ['ok' => true])
  ->name('api.ping');
Route::get('/tenants/{tenant}/pages', [PagesController::class, 'show'])
  ->name('api.tenants.pages.show');

/**
 * POST /auth/reauth
 * Purpose:
 * - User is already authenticated via JWT.
 * - The client sends the current password to re-verify identity
 * before a sensitive action.
 * - On success, the server stores a short-lived "reauth flag" (e.g., 10 minutes)
 * bound to this JWT.
 *
 * Auth:
 * - Requires a valid JWT (auth:api). No CSRF on API routes.
 *
 * Responses:
 * - 200 { status: "ok", reauth_until: "...", ttl_seconds: 600 }
 * - 401 { message: "Unauthorized", code: "invalid_password" }  (wrong password or no auth)
 */
Route::middleware(['auth:api'])
  ->post('/auth/reauth', ReauthController::class)
  ->name('auth.reauth');


/**
 * Example of a "sensitive" route that requires recent re-authentication.
 * Here we assume you already use:
 *   - 'active'            => user.status === 'active'
 *   - 'email.verified'    => user must have verified email (optional if you always block at login)
 *   - 'role:tenant_admin' => only tenant_admin (and above by hierarchy) or platform_super_admin
 *   - 'reauth'            => MUST have called POST /auth/reauth successfully in the last N minutes
 *
 * Tenant resolution:
 * - RequireTenantRole will resolve tenant_id from:
 *     1) route param: {tenant_id}
 *     2) header:      X-Tenant-Id
 *     3) query:       ?tenant_id=
 *
 * NOTE: This example uses a closure just to illustrate the middleware chain.
 * Replace it with your real controller action (e.g., UsersController@updateStatus).
 */
Route::middleware([
  'auth:api',
  'active',
  'email.verified',
  'role:tenant_admin',   // tenant_admin or higher (owner, platform_super_admin) will pass
  'reauth',              // requires a recent /auth/reauth
])->patch(
  '/tenants/{tenant_id}/users/{user}/status',
  function (Request $request, int $tenant_id, int $user) {
    // Example payload validation (adjust to your needs)
    $data = $request->validate([
      'status' => ['required', 'in:active,pending_invite,suspended,locked'],
    ]);

    // Here you would run your real update (omitted on purpose):
    //   - ensure $user belongs to $tenant_id
    //   - persist $data['status']
    //   - write an audit log entry (who/when/old->new/ip/ua)

    return response()->json([
      'ok' => true,
      'tenant_id' => $tenant_id,
      'user_id' => $user,
      'new' => ['status' => $data['status']],
      'note' => 'Status was NOT actually updated here (example endpoint). Replace with your controller.',
    ]);
  }
)
  ->name('tenants.users.status.update');


/**
 * Re-auth endpoint:
 * - Requires a valid JWT (auth:api).
 * - Checks current password and sets a short-lived "reauth" flag bound to this JWT.
 */
Route::middleware(['auth:api'])
  ->post('/auth/reauth', ReauthController::class)
  ->name('auth.reauth');

/**
 * Sensitive admin actions over users within a tenant.
 * Middleware chain:
 *  - auth:api           : JWT required
 *  - active             : user.status === 'active' (your enum)
 *  - email.verified     : email must be verified (optional if you block at login)
 *  - role:tenant_admin  : tenant_admin (and above by hierarchy) or platform_super_admin
 *  - reauth             : must have called POST /auth/reauth recently
 *
 * Route model binding resolves {user} -> App\Models\User
 */
Route::middleware([
  'auth:api',
  'active',
  'email.verified',
  'role:tenant_admin',
  'reauth',
])->group(function () {
  // PATCH /tenants/{tenant_id}/users/{user}/status
  Route::patch(
    '/tenants/{tenant_id}/users/{user}/status',
    [TenantUsersController::class, 'updateStatus']
  )
    ->name('tenants.users.status.update');

  // PATCH /tenants/{tenant_id}/users/{user}/role
  Route::patch(
    '/tenants/{tenant_id}/users/{user}/role',
    [TenantUsersController::class, 'updateRole']
  )
    ->name('tenants.users.role.update');
});
