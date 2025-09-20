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

    // --- Load config (preserve your parameters) ---
    $cfg            = (array) config('mystore.mail', []);
    $customHeaders  = isset($cfg['headers']) && is_array($cfg['headers'])
      ? $cfg['headers']
      : [];
    $cfgFromAddress = $cfg['from']['address'] ?? null;
    $cfgListUnsub   = trim((string) ($cfg['list_unsubscribe'] ?? ''));

    // If a route is not registered yet, we can still add mailto/configured URIs
    $hasOneClickRoute = Route::has('list-unsubscribe.one-click');

    // Determine domain (prefer configured from, else message from, else app.url)
    $from = $message->getFrom();
    $fromAddress = $cfgFromAddress
      ?: (!empty($from) ? $from[0]->getAddress() : null);

    $domain = $fromAddress && str_contains($fromAddress, '@')
      ? Str::after($fromAddress, '@')
      : (parse_url(
        (string) config('app.url'),
        PHP_URL_HOST)
        ?: 'mystorepanel.com'
      );

    // --- Build URIs for List-Unsubscribe ---
    $uris = [];

    // A) From config: allow CSV or single value; accept http(s) or mailto or bare email
    if ($cfgListUnsub !== '') {
      foreach (preg_split('/\s*,\s*/', $cfgListUnsub) as $u) {
        $u = trim($u);
        if ($u === '') {
          continue;
        }
        $uris[] = $this->normalizeUri($u);
      }
    }

    // B) Ensure we have a mailto fallback if none present
    $hasMailto = collect($uris)
      ->contains(fn ($u) => Str::startsWith(
        $u,
        'mailto:'
      ));
    if (! $hasMailto) {
      $uris[] = "mailto:unsubscribe@{$domain}?subject=unsubscribe";
    }

    // C) Append One-Click HTTPS (signed) if route exists
    if ($hasOneClickRoute) {
      // Determine primary recipient (usual transactional email has one To)
      $to = $message->getTo();
      if (! empty($to)) {
        $primary = $to[0]->getAddress();
        $oneClickUrl = URL::temporarySignedRoute(
          'list-unsubscribe.one-click',
          now()->addDays(14),
          ['email' => $primary]
        );
        $uris[] = $oneClickUrl;
      }
    }

    // De-dup & format with angle brackets as per RFC
    $uris = array_values(array_unique($uris));
    $headerValue = implode(
      ', ',
      array_map(fn ($u) => "<{$u}>", $uris)
    );

    // --- DEDUPE: remove any previous headers and apply ours ---
    $headers = $message->getHeaders();
    if ($headers->has('List-Unsubscribe')) {
      $headers->remove('List-Unsubscribe');
    }
    if ($headers->has('List-Unsubscribe-Post')) {
      $headers->remove('List-Unsubscribe-Post');
    }

    // RFC 8058: one-click flag
    $headers->addTextHeader(
      'List-Unsubscribe-Post',
      'List-Unsubscribe=One-Click'
    );
    // RFC 2369: multiple URIs allowed (mailto + https)
    $headers->addTextHeader(
      'List-Unsubscribe',
      $headerValue
    );

    // --- Extra custom headers from config (e.g., X-System) ---
    foreach ($customHeaders as $key => $val) {
      if (! is_string($key) || $key === '' || $val === null) {
        continue;
      }
      if ($headers->has($key)) {
        $headers->remove($key);
      }
      $headers->addTextHeader($key, (string) $val);
    }

    // Note: We DO NOT try to set Return-Path here; with API providers (Resend)
    // the envelope sender is controlled by the provider, not by headers.
  }

  /**
   * Normalize user-provided URI for List-Unsubscribe:
   * - If it's a bare email, convert to mailto.
   * - If it already starts with http(s) or mailto, keep as is.
   * - Else, return as is (provider will ignore invalid URIs).
   * @param string $u
   * @return string
   */
  private function normalizeUri(string $u): string
  {
    if (Str::startsWith(
      $u,
      ['http://', 'https://', 'mailto:'])
    ) {
      return $u;
    }
    // Bare email like "unsubscribe@domain.com"
    if (str_contains($u, '@') && ! str_contains($u, ' ')) {
      return 'mailto:' . $u;
    }
    // Fallback: return as is (provider will ignore invalid URIs)
    return $u;
  }
}
