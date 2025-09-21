<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsActive
{
  public function handle(Request $request, Closure $next)
  {
    // Usa el guard por defecto del request; si quieres forzar 'api', usa user('api')
    $user = $request->user();

    // No autenticado -> 401
    if (! $user) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    $status = (string) ($user->status ?? '');
    if ($status !== 'active') {
      return response()->json([
        'message' => 'Forbidden: user not active',
        'status'  => $status,
      ], 403);
    }

    return $next($request);
  }
}
