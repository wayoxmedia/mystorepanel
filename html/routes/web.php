<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

/**
 * Public Routes
 */
Route::get('/', function () {
  return view('welcome');
});

Route::get('/login', [LoginController::class, 'showLoginForm'])
  ->name('login'); // needed by middleware

Route::post('/login', [LoginController::class, 'login'])
  ->name('login.attempt')
  ->middleware('throttle:6,1');

Route::post('/logout', [LoginController::class, 'logout'])
  ->name('logout')
  ->middleware('auth');

Route::get('/invitation/accept/{token}', [InvitationAcceptanceController::class, 'show'])
  ->name('invitations.accept');

Route::post('/invitation/accept', [InvitationAcceptanceController::class, 'store'])
  ->name('invitations.accept.store');


Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
  ->name('password.request');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
  ->name('password.email')->middleware('throttle:3,1');

Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
  ->name('password.reset');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
  ->name('password.update')->middleware('throttle:3,1');

/**
 * Protected Routes
 */
Route::middleware(['auth:web']) // add 'verified' if you enforce email verification
  ->prefix('admin')
  ->name('admin.')
  ->group(function () {
    Route::get('users', [UserController::class, 'index'])
      ->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])
      ->name('users.create');
    Route::post('users', [UserController::class, 'store'])
      ->name('users.store');
  });
