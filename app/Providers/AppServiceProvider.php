<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Railway (and most PaaS) terminate TLS at their proxy and forward the
        // request to the container over plain HTTP. Force the HTTPS scheme in
        // production so generated links (Swagger UI assets, passport photo URLs)
        // are not treated as mixed content and blocked by the browser.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
