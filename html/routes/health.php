<?php

use Illuminate\Support\Facades\Route;

Route::get('/up', function () {
    // Minimal, fast health endpoint for uptime checks.
    // Does not hit DB or external services.
    return response()->json([
        'status' => 'ok',
        'app'    => config('app.name'),
        'env'    => app()->environment(),
        'time'   => now()->toIso8601String(),
    ], 200);
});

// Optional: readiness check (uncomment if/when you really need it).
// - Keep it lightweight; avoid heavy/slow checks.
// - If you want, you can ping the Backend API with a short timeout.
// Route::get('/ready', function () {
//     return response()->json([
//         'status' => 'ok',
//         'time'   => now()->toIso8601String(),
//     ], 200);
// });
