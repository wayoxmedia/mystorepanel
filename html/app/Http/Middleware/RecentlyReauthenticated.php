<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Middleware to ensure the user has recently re-authenticated.
 *
 * This middleware checks for a temporary flag in the cache that indicates
 * the user has recently re-authenticated. If the flag is missing, it returns
 * a 403 Forbidden response indicating that re-authentication is required.
 *
 * This is useful for protecting sensitive routes that require recent authentication.
 */
class RecentlyReauthenticated
{
  public function handle(Request $request, Closure $next)
  {
    $user = $request->user();

    // 401 if no authenticated user (JWT)
    if (!$user) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Tomar el JWT actual (igual que en el controller de reauth)
    $token = (string)(JWTAuth::getToken() ?: '');
    if ($token === '') {
      // Fallback por si acaso
      $bearer = (string)$request->bearerToken();
      if ($bearer !== '') {
        $token = $bearer;
      }
    }

    $key = sprintf(
      'reauth:%d:%s',
      $user->id,
      sha1($token)
    );

    // If no flag in cache => require reauth
    if (!Cache::has($key)) {
      return response()->json([
        'message' => 'Forbidden: reauth required',
        'code' => 'reauth_required',
      ], 403);
    }

    return $next($request);
  }
}
