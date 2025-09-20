<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

class AddListUnsubscribeHeaders
{
  /**
   * Handle the event.
   *
   * @param  MessageSending  $event
   * @return void
   */
  public function handle(MessageSending $event): void
  {
    // Safety: ensure we have a Symfony Email instance
    $message = $event->message;
    if (! $message instanceof Email) {
      return;
    }

    // --- Resolve tenant_id from the outgoing message (REQUIRED) ---
    // 1) Prefer custom header X-Tenant-Id (set when building the message)
    $headers = $message->getHeaders();
    $tenantId = null;
    if ($headers->has('X-Tenant-Id')) {
      $tenantId = (int) trim((string) $headers->get('X-Tenant-Id')->getBodyAsString());
    }

    // 2) Fallback to $event->data['tenant_id'] if present (Mailables / Mail::send view data)
    if (! $tenantId && isset($event->data['tenant_id'])) {
      $tenantId = (int) $event->data['tenant_id'];
    }

    // Mandatory: without tenant we do NOT emit unsubscribe URLs
    if (! $tenantId || $tenantId <= 0) {
      return;
    }

    // If routes are not registered yet, skip to avoid errors
    $hasOneClickRoute = Route::has('list-unsubscribe.one-click');
    $hasPageRoute     = Route::has('unsubscribe.page');

    // Determine sender domain to build mailto fallback
    $from = $message->getFrom();
    $fromAddress = !empty($from) ? $from[0]->getAddress() : null;
    $domain = $fromAddress && str_contains($fromAddress, '@')
      ? Str::after($fromAddress, '@')
      : (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.com');

    // --- Build URIs: mailto + page (signed) + one-click (signed) ---
    $uris = [];

    // Ensure a mailto fallback exists
    $uris[] = "mailto:unsubscribe@{$domain}?subject=unsubscribe";

    // Determine primary recipient (typical transactional email has one To)
    $to = $message->getTo();
    if (! empty($to)) {
      $primary = $to[0]->getAddress();

      if ($hasPageRoute) {
        $pageUrl = URL::temporarySignedRoute(
          'unsubscribe.page',
          now()->addDays(14),
          ['email' => $primary, 'tenant_id' => $tenantId]
        );
        $uris[] = $pageUrl;
      }

      if ($hasOneClickRoute) {
        $oneClickUrl = URL::temporarySignedRoute(
          'list-unsubscribe.one-click',
          now()->addDays(14),
          ['email' => $primary, 'tenant_id' => $tenantId]
        );
        $uris[] = $oneClickUrl;
      }
    }

    // Deduplicate & format per RFC (angle brackets, comma-separated)
    $uris = array_values(array_unique($uris));
    $headerValue = implode(', ', array_map(fn ($u) => "<{$u}>", $uris));

    // Remove previous headers (avoid duplicates) and add ours
    if ($headers->has('List-Unsubscribe')) {
      $headers->remove('List-Unsubscribe');
    }
    if ($headers->has('List-Unsubscribe-Post')) {
      $headers->remove('List-Unsubscribe-Post');
    }
    $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
    $headers->addTextHeader('List-Unsubscribe', $headerValue);

    // Extra headers from your config (optional)
    $customHeaders = (array) (config('mystore.mail.headers') ?? []);
    foreach ($customHeaders as $key => $val) {
      if (! is_string($key) || $key === '' || $val === null) continue;
      if ($headers->has($key)) $headers->remove($key);
      $headers->addTextHeader($key, (string) $val);
    }
  }
}
