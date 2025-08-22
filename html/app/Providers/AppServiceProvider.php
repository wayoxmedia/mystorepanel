<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Application-level service provider.
 * Use this for bindings, macros, and environment-specific tweaks.
 */
class AppServiceProvider extends ServiceProvider
{
  protected $policies = [
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
  }
}
