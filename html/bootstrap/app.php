<?php

use App\Http\Middleware\EnsureTenantManager;
use App\Http\Middleware\VerifyServiceToken;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RecentlyReauthenticated;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\RequireTenantRole;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: __DIR__.'/../routes/health.php',
  )
  ->withMiddleware(function (Middleware $middleware) {
    /**
     * We only append RequestId to the 'api' group so every API request
     * has a correlation id in both the Request and the Response.
     */
    $middleware->prependToGroup('api', [
      RequestId::class,
    ]);

    // Custom middleware aliases
    $middleware->group('api', [
      'throttle:api',
      SubstituteBindings::class,
    ]);

    $middleware->alias([
      'auth' => Authenticate::class,
      'verified' => EnsureEmailIsVerified::class,
      's2s' => VerifyServiceToken::class,
      'tenant.manager' => EnsureTenantManager::class,
      'active' => EnsureUserIsActive::class,
      'email.verified' => EnsureEmailVerified::class,
      'role' => RequireTenantRole::class,
      'reauth' => RecentlyReauthenticated::class,
    ]);
  })
  ->withSchedule(function (Schedule $schedule) {
    // Expire invitations older than their expiration date (free seats)
    $schedule->command('invitations:expire')
      ->hourly()
      ->onOneServer()
      ->withoutOverlapping();
    // Si prefieres otra cadencia:
    // $schedule->command('invitations:expire')->everyFifteenMinutes()->onOneServer()->withoutOverlapping();
  })
  ->withExceptions(function (Exceptions $exceptions) {
    //
  })
  ->withProviders([
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
  ])
  ->create();
