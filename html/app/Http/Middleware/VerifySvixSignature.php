<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Svix\Webhook;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * VerifySvixSignature
 *
 * - Verifies Resend (Svix) webhook signatures using headers:
 *   svix-id, svix-timestamp, svix-signature.
 * - Secret is read from config('services.resend.webhook_secret') or RESEND_WEBHOOK_SECRET.
 *
 * Usage:
 *   Route::post('/webhooks/resend', ...)->middleware('svix.verify:resend');
 *
 * Notes:
 * - Requires svix/svix-php. Install with: composer require svix/svix-php
 */
class VerifySvixSignature
{
  public function handle(Request $request, Closure $next, string $provider = 'resend'): Response
  {
    $id        = $request->header('svix-id');
    $ts        = $request->header('svix-timestamp');
    $sig       = $request->header('svix-signature');

    if (!$id || !$ts || !$sig) {
      Log::warning('Svix verify: missing headers', [
        'has_id' => (bool) $id,
        'has_ts' => (bool) $ts,
        'has_sig'=> (bool) $sig,
        'ua'     => $request->userAgent(),
        'ip'     => $request->ip(),
      ]);
      return response()->json(['message' => 'missing signature headers'], 400);
    }

    // Pick secret based on provider (add more cases if you verify other Svix sources).
    $secret = match ($provider) {
      'resend' => (string) (config('services.resend.webhook_secret') ?? env('RESEND_WEBHOOK_SECRET', '')),
      default  => (string) env('SVIX_WEBHOOK_SECRET', ''),
    };

    if ($secret === '') {
      Log::error('Svix verify: missing secret for provider', ['provider' => $provider]);
      return response()->json(['message' => 'webhook secret not configured'], 500);
    }

    // Raw body exactly as received
    $payload = $request->getContent();

    try {
      // Using official Svix PHP SDK
      // composer require svix/svix-php
      $wh = new Webhook($secret);
      $wh->verify($payload, [
        'svix-id'        => $id,
        'svix-timestamp' => $ts,
        'svix-signature' => $sig,
      ]);

      // Optional: log a tiny trace for diagnostics
      Log::info('Svix verify: ok', ['provider' => $provider]);

    } catch (Throwable $e) {
      Log::warning('Svix verify: failed', [
        'provider' => $provider,
        'error'    => $e->getMessage(),
      ]);
      return response()->json(['message' => 'invalid signature'], 400);
    }

    return $next($request);
  }
}
