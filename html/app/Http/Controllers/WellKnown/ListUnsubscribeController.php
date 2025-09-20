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
   * Requirements:
   * - Signed URL (already enforced by 'signed' middleware).
   * - Accept POST with "List-Unsubscribe=One-Click" form body (Gmail/Yahoo).
   * - Do not require any auth; must be a one-click action.
   */
  public function __invoke(Request $request)
  {
    if (! $request->hasValidSignature()) {
      // Signature mismatch or expired (URL::temporarySignedRoute controls TTL)
      return response('Invalid or expired signature.', 400);
    }

    // Recipient bound to the signed URL (from the header we injected).
    $email = (string) $request->query('email', '');

    // TODO: In a future step, persist to a suppression table per tenant.
    Log::info('[One-Click Unsubscribe] Accepted', [
      'email'  => $email,
      'method' => $request->method(),
      'ip'     => $request->ip(),
      'ua'     => $request->userAgent(),
    ]);

    // RFC 8058 allows 200/204. Return plain text 200 for easier debugging today.
    return
      response(
        'Unsubscribed',
        200
      )->header(
        'Content-Type',
        'text/plain'
      );
  }
}
