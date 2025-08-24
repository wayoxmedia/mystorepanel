<?php

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
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
Route::get('/email/verify', fn () => view('auth.verify-email'))
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
Route::middleware(['auth:web', 'verified'])
  ->prefix('admin')
  ->name('admin.')
  ->group(function () {
    Route::get('users', [UserController::class, 'index'])
      ->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])
      ->name('users.create');
    Route::post('users', [UserController::class, 'store'])
      ->name('users.store');

    // Impersonation routes
    Route::post('impersonate/{user}', [ImpersonationController::class, 'start'])
      ->name('impersonate.start');
    Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])
      ->name('impersonate.stop');
  });
