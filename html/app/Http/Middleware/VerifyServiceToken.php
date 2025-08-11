<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyServiceToken
{
    /**
     * Validate "Authorization: Bearer <token>" against SERVICE_TOKEN (.env).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $provided = $request->bearerToken();
        $expected = (string) config('services.service_token');

        // Constant-time comparison to avoid timing attacks
        if (! $provided || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
