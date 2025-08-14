<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * Application-level service provider.
 * Use this for bindings, macros, and environment-specific tweaks.
 */
class AppServiceProvider extends ServiceProvider
{
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
    }
}
