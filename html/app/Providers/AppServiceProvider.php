<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

/**
 * Application-level service provider.
 * Use this for bindings, macros, and environment-specific tweaks.
 */
class AppServiceProvider extends ServiceProvider
{
  protected array $policies = [
    // Model => Policy
  ];

  /**
   * Register any application services.
   */
  public function register(): void
  {
    // Register container bindings here if needed.
  }

  /**
   * Bootstrap any application services.
   * @return void
   */
  public function boot(): void
  {
    // Force HTTPS in production so generated URLs are secure.
    if (app()->environment('production')) {
      URL::forceScheme('https');
    }

    /**
     * Default API limiter (used by 'throttle:api').
     * 60 req/min, keyed by authenticated user id or fallback to IP.
     */
    RateLimiter::for('api', function (Request $request) {
      $key = $request->user()?->getAuthIdentifier()
        ? 'api|user:'.$request->user()->getAuthIdentifier()
        : 'api|ip:'.$request->ip();

      return [
        Limit::perMinute(60)
          ->by($key)
          ->response(fn() => throttleCallback('Too many requests. Try again in 60 seconds.')),
      ];
    });

    /**
     * Login limiter: 6 attempts/min by (email + IP).
     *
     * @todo Consider:
     *    catch data to log it (e.g. IP, user agent, etc.)
     *    use a custom exception handler to log throttling attempts
     *    (e.g. log throttling attempts to a file or database)
     */
    RateLimiter::for('login', function (Request $request) {
      $email = Str::lower((string)$request->input('email', ''));
      $key = 'login|'.$email.'|'.$request->ip();

      return [
        Limit::perMinute(6)
          ->by($key)
          ->response(function () use ($request, $email) {
            Log::warning('Rate limit hit: login', [
              'ip' => $request->ip(),
              'email' => $email ?: null,
              'path' => $request->path(),
              'userAgent' => $request->userAgent(),
              'referer' => $request->headers->get('referer'),
            ]);

            return throttleCallback('Too many login attempts. Please try again in 60 seconds.');
          }),
      ];
    });

    /**
     * Contact form limiter: 6 requests/min by (email if present + IP).
     */
    RateLimiter::for('contact-store', function (Request $request) {
      $email = Str::lower((string)$request->input('email', ''));
      $key = 'contact|'.$email.'|'.$request->ip();

      return [
        Limit::perMinute(6)
          ->by($key)
          ->response(function () use ($request, $email) {
            Log::warning('Rate limit hit: contact-store', [
              'ip' => $request->ip(),
              'email' => $email ?: null,
              'path' => $request->path(),
              'userAgent' => $request->userAgent(),
              'referer' => $request->headers->get('referer'),
              'payload' => $request->except(['password']), // evita logs sensibles
            ]);

            return throttleCallback('Too many contact requests. Please try again in 60 seconds.');
          }),
      ];
    });

    /**
     * Subscribe form limiter: 6 requests/min by (email if present + IP).
     */
    RateLimiter::for('subscribe-store', function (Request $request) {
      $email = Str::lower((string)$request->input('email', ''));
      $key = 'subscribe|'.$email.'|'.$request->ip();

      return [
        Limit::perMinute(6)
          ->by($key)
          ->response(function () use ($request, $email) {
            Log::warning('Rate limit hit: subscribe-store', [
              'ip' => $request->ip(),
              'email' => $email ?: null,
              'path' => $request->path(),
              'userAgent' => $request->userAgent(),
              'referer' => $request->headers->get('referer'),
              'payload' => $request->except(['password']),
            ]);

            return throttleCallback('Too many subscribe requests. Please try again in 60 seconds.');
          }),
      ];
    });

    /**
     * Global mail deliverability tweaks:
     * - Ensure a default From if missing (config: mystore.mail.from).
     * - Add List-Unsubscribe (+ One-Click) if configured.
     * - Set Return-Path (bounce) when transport supports it; fallback to header.
     * - Inject any extra custom headers from config.
     *
     * Notes:
     * - This listens to MessageSending so all Mailables inherit these headers.
     * - Works with Symfony Mailer (Laravel 9+). Guarded for Email instance.
     */
    Event::listen(MessageSending::class, function (MessageSending $event): void {
      $cfg = config('mystore.mail');
      if (!is_array($cfg)) {
        return;
      }

      $message = $event->message;

      // Only handle Symfony Mime Email instances
      if (!($message instanceof Email)) {
        return;
      }

      // 1) Ensure From if not explicitly set on the mailable
      $from = $cfg['from'] ?? null;
      if (is_array($from) && !empty($from['address']) && !$message->getFrom()) {
        $message->from(new Address($from['address'], $from['name'] ?? null));
      }

      // 2) List-Unsubscribe (recommended for inbox placement)
      if (!empty($cfg['list_unsubscribe'])) {
        // Avoid duplicates
        if ($message->getHeaders()->has('List-Unsubscribe')) {
          $message->getHeaders()->remove('List-Unsubscribe');
        }
        $message->getHeaders()->addTextHeader('List-Unsubscribe', (string) $cfg['list_unsubscribe']);

        // One-Click (supported by Gmail and others)
        if ($message->getHeaders()->has('List-Unsubscribe-Post')) {
          $message->getHeaders()->remove('List-Unsubscribe-Post');
        }
        $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
      }

      // 3) Return-Path / bounce envelope (best-effort)
      if (!empty($cfg['bounce']) && is_string($cfg['bounce'])) {
        // Prefer API when available on Symfony Email
        if (method_exists($message, 'returnPath')) {
          $message->returnPath(new Address($cfg['bounce']));
        } else {
          // Fallback header (some transports override this)
          if ($message->getHeaders()->has('Return-Path')) {
            $message->getHeaders()->remove('Return-Path');
          }
          $message->getHeaders()->addTextHeader('Return-Path', $cfg['bounce']);
        }
      }

      // 4) Extra custom headers
      $headers = $cfg['headers'] ?? [];
      if (is_array($headers)) {
        foreach ($headers as $name => $value) {
          if (!is_string($name) || !is_string($value)) {
            continue;
          }
          if ($message->getHeaders()->has($name)) {
            $message->getHeaders()->remove($name);
          }
          $message->getHeaders()->addTextHeader($name, $value);
        }
      }
    });
  }
}
