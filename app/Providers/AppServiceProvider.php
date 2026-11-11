<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

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
        if (config('app.env') === 'production' || $this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Public application / intake forms. Each limiter has its own counter,
        // so uploading several documents no longer uses up the allowance for
        // submitting the form ("Too Many Attempts" on real applications).
        RateLimiter::for('public-forms', function (Request $request) {
            return Limit::perMinute(30)->by('public-forms|' . $request->ip());
        });

        RateLimiter::for('public-uploads', function (Request $request) {
            return Limit::perMinute(60)->by('public-uploads|' . $request->ip());
        });
    }
}
