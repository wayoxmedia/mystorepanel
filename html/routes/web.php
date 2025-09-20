<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\SeatUpgradeController;
use App\Http\Controllers\Admin\TenantSeatsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserStatusController;
use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\PlaygroundController;
use App\Http\Controllers\UnsubscribePageController;
use App\Http\Controllers\Webhooks\ResendWebhookController;
use App\Http\Controllers\WellKnown\ListUnsubscribeController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Public Routes
 */
Route::get('/', function () {
  return view('welcome');
});

// Auth (public)
Route::middleware('guest')->group(function () {
  Route::get('/login', [LoginController::class, 'showLoginForm'])
    ->name('login');

  Route::post('/login', [LoginController::class, 'login'])
    ->name('login.attempt')
    ->middleware('throttle:6,1');

  Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
    ->name('password.request');

  Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->name('password.email')
    ->middleware('throttle:3,1');

  Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
    ->name('password.reset');

  Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->name('password.update')
    ->middleware('throttle:3,1');
});

// Protected Logout
Route::post('/logout', [LoginController::class, 'logout'])
  ->name('logout')
  ->middleware('auth');

// Invitation acceptance (public, via token, no auth)
Route::get('/invitation/accept/{token}', [InvitationAcceptanceController::class, 'show'])
  ->name('invitations.accept');

Route::post('/invitation/accept', [InvitationAcceptanceController::class, 'store'])
  ->name('invitations.accept.store');


// Email verification notice — require session but NO verified email
Route::get('/email/verify', fn () => view('auth.invitations.verify-email'))
  ->middleware('auth')
  ->name('verification.notice');

// Enlace de verificación (desde email) — requiere login + link firmado
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
  $request->fulfill();
  return redirect()->intended(route('admin.users.index'))->with('success', 'Email verified.');
})->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');

// Reenviar correo de verificación
Route::post('/email/verification-notification', function (Request $request) {
  if ($request->user()->hasVerifiedEmail()) {
    return redirect()->intended(route('admin.users.index'));
  }
  $request->user()->sendEmailVerificationNotification();
  return back()->with('success', 'Verification link sent.');
})->middleware(['auth', 'throttle:3,1'])->name('verification.send');

/**
 * Protected Routes
 */
Route::middleware(['auth:web', 'verified', 'tenant.manager'])
  ->prefix('admin')
  ->name('admin.')
  ->group(function () {
    Route::get('users', [UserController::class, 'index'])
      ->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])
      ->name('users.create');
    Route::post('users', [UserController::class, 'store'])
      ->name('users.store');
    Route::post('users/{user}/status', [UserStatusController::class, 'update'])
      ->name('users.status.update')
      ->middleware('throttle:12,1');
    Route::delete('users/{user}', [UserController::class, 'destroy'])
      ->name('users.destroy');


    // Impersonation routes
    Route::post('impersonate/{user}', [ImpersonationController::class, 'start'])
      ->name('impersonate.start');

    // User role management
    Route::get('users/{user}/roles', [RoleController::class, 'edit'])
      ->name('users.roles.edit');
    Route::post('users/{user}/roles', [RoleController::class, 'update'])
      ->name('users.roles.update');

    // Invitations lifecycle
    Route::get('invitations', [InvitationController::class, 'index'])
      ->name('invitations.index');
    Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])
      ->middleware('throttle:3,1') // <= max 3 tries per minute
      ->name('invitations.resend');
    Route::post('invitations/{invitation}/cancel', [InvitationController::class, 'cancel'])
      ->middleware('throttle:12,1')
      ->name('invitations.cancel');

    // Seats (only Platform SA; controller validates and aborts if not)
    Route::get('tenants/seats', [TenantSeatsController::class, 'index'])
      ->name('tenants.seats.index');
    Route::post('tenants/{tenant}/seats', [TenantSeatsController::class, 'update'])
      ->name('tenants.seats.update');
    Route::get('seats/upgrade', [SeatUpgradeController::class, 'show'])
      ->name('seats.upgrade.show');
    Route::post('seats/upgrade', [SeatUpgradeController::class, 'request'])
      ->name('seats.upgrade.request');

  });

Route::middleware(['auth:web'])->group(function () {
  Route::get('/account', [AccountController::class, 'show'])->name('account.show');
  Route::post('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
});

// Impersonation stop (global, outside /admin, for convenience)
Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])
  ->name('impersonate.stop')
  ->middleware('auth');

// Playground route for testing (not part of the main app)
Route::get('playground', [PlaygroundController::class, 'index'])
  ->name('playground.index');

Route::get('email-preview/{route}', [InvitationController::class, 'previewEmail'])
   ->name('email.preview');

Route::get('/.well-known/list-unsubscribe', ListUnsubscribeController::class)
  ->middleware('signed')
  ->name('list-unsubscribe.one-click'); // must match the name used in URL::temporarySignedRoute

Route::post('/.well-known/list-unsubscribe', ListUnsubscribeController::class)
  ->middleware('signed')
  ->withoutMiddleware([VerifyCsrfToken::class]); // POST has same URI; signed URL still validates

// Page-style Unsubscribe (signed GET shows confirm UI; POST confirms with CSRF)
Route::get('/unsubscribe', [UnsubscribePageController::class, 'show'])
  ->middleware('signed')
  ->name('unsubscribe.page');

Route::post('/unsubscribe', [UnsubscribePageController::class, 'confirm'])
  ->withoutMiddleware([VerifyCsrfToken::class]) // CSRF not needed; signed URL still validates
  ->name('unsubscribe.confirm');

Route::post('/webhooks/resend', ResendWebhookController::class)
  ->withoutMiddleware([VerifyCsrfToken::class])
  ->name('webhooks.resend');
