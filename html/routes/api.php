<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\PagesController;
use App\Http\Controllers\Api\SiteResolveController;
use App\Http\Controllers\Api\SubscriberController;
use App\Http\Controllers\Api\TenantPagesController;
use Illuminate\Support\Facades\Route;

/**
 *|--------------------------------------------------------------------------
 *| API Routes
 *|--------------------------------------------------------------------------
 *| This file consolidates existing endpoints and the JWT auth contract
 *| consumed by template1 (frontend). Endpoints preserved as-is, grouped
 *| under an 'auth' prefix with route names added for consistency.
 */
// --- Auth (JWT) ---
Route::prefix('auth')->name('api.auth.')->group(function () {
    // Public login (no middleware)
    Route::post('/login', [AuthController::class, 'login'])
        ->name('login');

    // Protected by JWT guard (api)
    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])
            ->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('logout');
        Route::post('/refresh', [AuthController::class, 'refresh'])
            ->name('refresh');
    });
});

// --- Contact Routes ---
Route::post('/contact-form', [ContactController::class, 'store'])
    ->name('api.contacts.store');
Route::get('/contacts/{id}', [ContactController::class, 'show'])
    ->name('api.contacts.show');
Route::get('/contacts', [ContactController::class, 'index'])
    ->name('api.contacts.index');

// --- Subscriber Routes ---
Route::post('/subscribe-form', [SubscriberController::class, 'store'])
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
Route::get('/ping', fn () => ['ok' => true])
    ->name('api.ping');
Route::get('/tenants/{tenant}/pages', [PagesController::class, 'show'])
    ->name('api.tenants.pages.show');
