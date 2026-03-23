<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
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
        // Register custom SMS notification channel
        \Illuminate\Support\Facades\Notification::resolved(function ($service) {
            $service->extend('sms', function ($app) {
                return new \App\Channels\SmsChannel($app->make(\App\Services\HubtelSmsService::class));
            });
        });

        // Register Order observer
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);

        // Register broadcasting auth under the v1 prefix with Sanctum middleware
        Broadcast::routes(['prefix' => 'v1', 'middleware' => ['auth:sanctum']]);

        // Register Payment observer
        \App\Models\Payment::observe(\App\Observers\PaymentObserver::class);

        // Register rate limiters (relaxed in local for testing)
        \Illuminate\Support\Facades\RateLimiter::for('otp-send', function ($request) {
            $limit = app()->environment('local') ? 60 : 3;

            return \Illuminate\Cache\RateLimiting\Limit::perHour($limit)->by($request->input('phone') ?? $request->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('otp-verify', function ($request) {
            $limit = app()->environment('local') ? 30 : 5;

            return \Illuminate\Cache\RateLimiting\Limit::perMinute($limit)->by($request->input('phone') ?? $request->ip());
        });
    }
}
