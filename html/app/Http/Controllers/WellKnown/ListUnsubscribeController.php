<?php

namespace App\Http\Controllers\WellKnown;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListUnsubscribeController extends Controller
{
  /**
   * Handle both GET and POST One-Click Unsubscribe requests.
   * - Signed URL enforced by 'signed' middleware in routes.
   * - Marks email as unsubscribed in subscribers table.
   */
  public function __invoke(Request $request)
  {
    if (! $request->hasValidSignature()) {
      // Signature mismatch or expired (URL::temporarySignedRoute controls TTL)
      return response('Invalid or expired signature.', 400);
    }

    // Normalize and validate email
    $email = strtolower(
      trim((string) $request->query('email', ''))
    );
    if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return response('Invalid email.', 400);
    }

    // Optional: restrict to one tenant if you later include tenant_id in the signed URL
    $tenantId = $request->query('tenant_id');

    // Build update payload
    $now = now();
    $meta = [
      'ip'        => $request->ip(),
      'userAgent' => $request->userAgent(),
      'method'    => $request->method(),
      'source'    => 'one_click',
      'at'        => $now->toIso8601String(),
    ];

    // Update subscribers: email channel only (address_type = 'e')
    $q = DB::table('subscribers')
      ->where('address_type', 'e')
      ->whereRaw('LOWER(address) = ?', [$email]);

    if (! empty($tenantId)) {
      $q->where('tenant_id', $tenantId);
    }

    $affected = $q->update([
      'unsubscribed_at'   => $now,
      'unsubscribe_source'=> 'one_click',
      'unsubscribe_meta'  => json_encode(
        $meta,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
      ),
      'active'            => 0,
      'updated_at'        => $now,
    ]);

    Log::info('[One-Click Unsubscribe] Persisted', [
      'email'    => $email,
      'tenantId' => $tenantId,
      'affected' => $affected,
    ]);

    // 200 OK text response (RFC-friendly; Gmail/Yahoo accept 200/204)
    return response(
      'Unsubscribed',
      200
    )->header(
      'Content-Type',
      'text/plain'
    );
  }
}
