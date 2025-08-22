<?php

use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
  return view('welcome');
});


Route::middleware(['auth']) // add 'verified' if you enforce email verification
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
