<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\SubscriberController;
use Illuminate\Support\Facades\Route;

/**
 * Auth Routes (JWT protected)
 */
// Group Logout (POST) and Me (GET) routes under auth:api middleware.
Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

/**
 * No Authentication required (Public) Routes
 */
// Login (POST)
Route::post('/login', [AuthController::class, 'login']);

// Send Contact Form (POST)
Route::post('/contact-form', [ContactController::class, 'store']);

// Subscribe Form (POST)
Route::post('/subscribe-form', [SubscriberController::class, 'store']);

// Get Subscriber by ID (GET)
Route::get('/subscribers/{id}', [SubscriberController::class, 'show']);
