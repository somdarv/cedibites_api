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
    }
}
