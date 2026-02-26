<?php

namespace App\Providers;

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
                return new \App\Channels\SmsChannel($app->make(\App\Services\SMSService::class));
            });
        });

        // Register Order observer
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);

        // Register Payment observer
        \App\Models\Payment::observe(\App\Observers\PaymentObserver::class);

        // Register rate limiters
        \Illuminate\Support\Facades\RateLimiter::for('otp-send', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perHour(3)->by($request->input('phone_number') ?? $request->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('otp-verify', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinutes(5, 5)->by($request->input('phone_number') ?? $request->ip());
        });
    }
}
