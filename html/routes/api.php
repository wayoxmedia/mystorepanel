<?php

use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\SubscriberController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/contact-form', [ContactController::class, 'store']);

Route::post('/subscribe-form', [SubscriberController::class, 'store']);

Route::get('/subscribers/{id}', [SubscriberController::class, 'show']);
