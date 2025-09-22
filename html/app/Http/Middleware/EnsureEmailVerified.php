<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;

class EnsureEmailVerified
{
  public function handle(Request $request, Closure $next)
  {
    $user = $request->user();

    // No autenticado -> 401
    if (! $user) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Si el modelo implementa MustVerifyEmail y no está verificado -> 403
    if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
      return response()->json([
        'message' => 'Email not verified',
        'code'    => 'email_unverified',
      ], 403);
    }

    return $next($request);
  }
}
