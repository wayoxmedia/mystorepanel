<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyServiceToken
{
    /**
     * Validate "Authorization: Bearer <token>" against SERVICE_TOKEN (.env).
     *
     * Enforces a shared secret for server-to-server calls.
     * Looks for the token in the 'X-Service-Token' header (preferred) or ?service_token= (dev only).
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
        $expected = (string) config('services.service_token');

        // If not configured, treat as server misconfiguration
        if ($expected === '' || $expected === 'replace-with-a-long-random-string') {
            Log::warning('S2S token not configured', [
                'ip'        => $request->ip(),
                'path'      => $request->path(),
                'userAgent' => $request->userAgent(),
            ]);

            return response()->json(['error' => 'Service token not configured.'], 500);
        }

        // Constant-time comparison to avoid timing attacks
        if (!is_string($provided) || !hash_equals($expected, $provided)) {
            Log::warning('S2S token mismatch', [
                'ip'        => $request->ip(),
                'path'      => $request->path(),
                'userAgent' => $request->userAgent(),
                'hasHeader' => $request->headers->has('X-Service-Token'),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
