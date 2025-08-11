<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\SiteResolveController;
use App\Http\Controllers\Api\SubscriberController;
use App\Http\Controllers\Api\TenantPagesController;
use Illuminate\Support\Facades\Route;

/**
 * Auth Routes (JWT protected)
 */
// Group Auth (POST and GET) routes under auth:api middleware.
Route::middleware('auth:api')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
});

/**
 * No Authentication required (Public) Routes
 */
// Login (POST)
Route::post('/auth/login', [AuthController::class, 'login']);

/**
 * Contact Routes
 */
// Send Contact Form (POST)
Route::post('/contact-form', [ContactController::class, 'store']);

// Get Contact by ID (GET)
Route::get('/contacts/{id}', [ContactController::class, 'show']);

// Get Contacts List (GET)
Route::get('/contacts', [ContactController::class, 'index']);


/**
 * Subscriber Routes
 */
// Subscribe Form (POST)
Route::post('/subscribe-form', [SubscriberController::class, 'store']);

// Get Subscriber by ID (GET)
Route::get('/subscribers/{id}', [SubscriberController::class, 'show']);

// Get Subscribers List (GET)
Route::get('/subscribers', [SubscriberController::class, 'index']);

Route::middleware('s2s')->group(function () {
    Route::get('/sites/resolve', [SiteResolveController::class, 'resolveByDomain']);
    Route::get('/tenants/{tenant}/pages', [TenantPagesController::class, 'show']);
});
Route::get('/ping', fn () => ['ok' => true]);
