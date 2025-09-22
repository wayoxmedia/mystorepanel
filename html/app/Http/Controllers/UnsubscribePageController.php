<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UnsubscribePageController extends Controller
{
  /**
   * Show confirmation page from a signed URL.
   */
  public function show(Request $request): View
  {
    // Email comes from signed URL (?email=...)
    $email = strtolower(
      trim((string) $request->query('email', ''))
    );

    // Persist in session to avoid tampering between GET and POST
    $request->session()
      ->put('unsubscribe.email', $email);
    $request->session()
      ->put('unsubscribe.tenant_id', $request->query('tenant_id')); // optional

    return view('unsubscribe.show', [
      'email' => $email,
    ]);
  }

  /**
   * Confirm unsubscribe (CSRF-protected).
   *
   * @param Request $request
   * @return View|Application|Factory|RedirectResponse
   */
  public function confirm(Request $request): View|Application|Factory|RedirectResponse
  {
    // Read from session (not from user input) to prevent tampering
    $email = strtolower(
      (string) $request->session()->pull('unsubscribe.email', '')
    );
    $tenantId = $request->session()->pull('unsubscribe.tenant_id');

    if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return back()->with('status', 'Invalid email.')->withInput();
    }

    $now = now();
    $meta = [
      'ip'        => $request->ip(),
      'userAgent' => $request->userAgent(),
      'method'    => 'page',
      'at'        => $now->toIso8601String(),
    ];

    $q = DB::table('subscribers')
      ->where('address_type', 'e')
      ->whereRaw('LOWER(address) = ?', [$email]);

    if (! empty($tenantId)) {
      $q->where('tenant_id', $tenantId);
    }

    $affected = $q->update([
      'unsubscribed_at'    => $now,
      'unsubscribe_source' => 'page',
      'unsubscribe_meta'   => json_encode(
        $meta,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
      ),
      'active'             => 0,
      'updated_at'         => $now,
    ]);

    return view('unsubscribe.done', [
      'email'    => $email,
      'affected' => $affected,
    ]);
  }
}
