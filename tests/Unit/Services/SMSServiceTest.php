<?php

use App\Services\SMSService;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->smsService = new SMSService;
});

test('sends SMS in log mode', function () {
    config(['services.sms.driver' => 'log']);

    Log::shouldReceive('info')
        ->once()
        ->with('SMS sent (dev mode)', [
            'phone' => '+233241234567',
            'message' => 'Test message',
        ]);

    $result = $this->smsService->send('+233241234567', 'Test message');

    expect($result)->toBeTrue();
});

test('sends OTP message', function () {
    config(['services.sms.driver' => 'log']);

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function ($message, $context) {
            return $message === 'SMS sent (dev mode)'
                && $context['phone'] === '+233241234567'
                && str_contains($context['message'], '123456')
                && str_contains($context['message'], 'CediBites');
        });

    $result = $this->smsService->sendOTP('+233241234567', '123456');

    expect($result)->toBeTrue();
});

test('handles SMS sending failure gracefully', function () {
    config(['services.sms.driver' => 'invalid']);

    Log::shouldReceive('info')->once();

    $result = $this->smsService->send('+233241234567', 'Test');

    expect($result)->toBeTrue();
});
