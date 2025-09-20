<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to verify service token for server-to-server communication.
 *
 * This middleware checks the "X-Service-Token" header or the "service_token" query parameter
 * against a configured service token in the environment.
 * It is intended for use in server-to-server API calls to ensure secure communication.
 */
class VerifyServiceToken
{
  /**
   * The URIs that should be excluded from service token verification.
   *
   * @var array<int, string>
   */
  protected array $except = [
    '/.well-known/list-unsubscribe',
    '/webhooks/resend',
  ];

  /**
   * Validate "Authorization: Bearer <token>" against SERVICE_TOKEN (.env).
   *
   * Enforces a shared secret for server-to-server calls.
   * Looks for the token in the 'X-Service-Token' header (preferred) or ?service_token= (dev only).
   *
   * @param  Request  $request
   * @param  Closure  $next
   * @return Response
   */
  public function handle(Request $request, Closure $next): Response
  {
    /**
     * Header always; query param only in local for testing.
     * Use different tokens for production and local development.
     * May want to remove this later, we don't want to leak the token in production.
     */
    $allowQueryInLocal = app()->environment('local');

    $provided = $request->headers->get('X-Service-Token');
    if (!$provided && $allowQueryInLocal) {
      $provided = $request->query('service_token');
    }

    // Read from config; cast to string to avoid null issues
    $expected = (string)config('services.service_token');

    // If not configured, treat as server misconfiguration
    if ($expected === '' || $expected === 'replace-with-a-long-random-string') {
      Log::warning('S2S token not configured', [
        'ip' => $request->ip(),
        'path' => $request->path(),
        'userAgent' => $request->userAgent(),
      ]);

      return response()->json(['error' => 'Service token not configured.'], 500);
    }

    // Constant-time comparison to avoid timing attacks
    if (!is_string($provided) || !hash_equals($expected, $provided)) {
      Log::warning('S2S token mismatch', [
        'ip' => $request->ip(),
        'path' => $request->path(),
        'userAgent' => $request->userAgent(),
        'hasHeader' => $request->headers->has('X-Service-Token'),
      ]);

      return response()->json(['error' => 'Unauthorized'], 401);
    }

    return $next($request);
  }
}
