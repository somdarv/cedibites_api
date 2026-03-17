<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\RateLimiter;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable rate limiting in tests
        RateLimiter::for('otp-send', fn () => \Illuminate\Cache\RateLimiting\Limit::none());
        RateLimiter::for('otp-verify', fn () => \Illuminate\Cache\RateLimiting\Limit::none());

        // Set Hubtel SMS configuration for tests
        config([
            'services.hubtel.client_id' => 'test_client_id',
            'services.hubtel.client_secret' => 'test_client_secret',
            'services.hubtel.sender_id' => 'CediBites',
            'services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages',
        ]);
    }
}
