<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Controller for re-authentication endpoint.
 *
 * This endpoint allows a user to re-authenticate by providing their password.
 * Upon successful re-authentication, a temporary flag is set in the cache
 * indicating that the user has recently re-authenticated.
 *
 * This is useful for sensitive operations that require recent authentication.
 */
class ReauthController extends Controller
{
  /**
   * POST /auth/reauth
   * Body: { "password": "..." }
   * Header: Authorization: Bearer <jwt>
   *
   * Response (200):
   * { "status":"ok", "reauth_until":"<ISO8601>", "ttl_seconds":600 }
   *
   * Errors:
   * 401 -> no authenticated or invalid password
   */
  public function __invoke(Request $request): JsonResponse
  {
    // Validate input
    $data = $request->validate([
      'password' => ['required', 'string'],
    ]);

    // User authenticated (JWT)
    $user = $request->user();
    if (! $user) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Verify password
    if (! Hash::check($data['password'], (string) $user->password)) {
      return response()->json([
        'message' => 'Unauthorized',
        'code'    => 'invalid_password',
      ], 401);
    }

    // Reauth TTL (seconds)
    $ttl = 10 * 60; // 10 minutes.

    // Link "flag" to this user and token
    $token = (string) (JWTAuth::getToken() ?: '');
    $key = sprintf(
      'reauth:%d:%s',
      $user->id, sha1($token)
    );

    // TTL in mins for Cache::put
    Cache::put($key, now()->timestamp, $ttl / 60);

    return response()->json([
      'status'       => 'ok',
      'reauth_until' => now()->addSeconds($ttl)->toIso8601String(),
      'ttl_seconds'  => $ttl,
    ]);
  }
}
