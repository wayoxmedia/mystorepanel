<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * RequestId middleware
 *
 * Purpose:
 * - Ensure every HTTP request carries a stable correlation id.
 * - If the client provides X-Request-Id, we keep it; otherwise we generate a UUID v4.
 * - Expose the id on both the Request (headers + attributes) and the Response header.
 *
 * Notes:
 * - AuditLogger already reads X-Request-Id from the Request and stores it in meta.request.id.
 * - Keep the id simple and safe; reject absurdly long or weird values.
 */
class RequestId
{
  /**
   * Accept only sane header values (alnum, dash, underscore, dot; 8–128 chars).
   */
  private const SAFE_PATTERN = '/^[A-Za-z0-9._-]{8,128}$/';

  /**
   * Handle an incoming request.
   *
   * @param  Request  $request
   * @param  Closure(Request): (Response)  $next
   * @return Response
   */
  public function handle(Request $request, Closure $next): Response
  {
    $incoming = ($request->headers->get('X-Request-Id') ?? '');
    $id = $this->normalize($incoming);

    // Put it back on the request (headers + attributes) so downstream code can read it.
    $request->headers->set('X-Request-Id', $id);
    $request->attributes->set('request_id', $id);

    /** @var Response $response */
    $response = $next($request);

    // Always expose the id on the response for client-side correlation.
    $response->headers->set('X-Request-Id', $id);

    return $response;
  }

  /**
   * Normalize incoming id or mint a new one.
   *
   * @param string $incoming
   * @return string
   */
  private function normalize(string $incoming): string
  {
    // If client sent a safe id, keep it.
    if ($incoming !== '' && preg_match(self::SAFE_PATTERN, $incoming)) {
      return $incoming;
    }

    // Otherwise, mint a new v4 UUID (lowercase).
    return (string) Str::uuid();
  }
}
