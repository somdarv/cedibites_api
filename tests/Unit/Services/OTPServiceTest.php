<?php

use App\Models\Otp;
use App\Services\OTPService;

beforeEach(function () {
    $this->otpService = new OTPService;
});

test('generates 6 digit OTP', function () {
    $otp = $this->otpService->generate();

    expect($otp)
        ->toBeString()
        ->toHaveLength(6)
        ->toMatch('/^\d{6}$/');
});

test('stores OTP with expiry', function () {
    $phone = '+233241234567';
    $otp = '123456';
    $ip = '127.0.0.1';

    $otpRecord = $this->otpService->store($phone, $otp, $ip);

    expect($otpRecord)
        ->toBeInstanceOf(Otp::class)
        ->phone->toBe($phone)
        ->otp->toBe($otp)
        ->ip_address->toBe($ip)
        ->expires_at->toBeInstanceOf(Carbon\Carbon::class);

    expect($otpRecord->fresh()->verified)->toBeFalse();

    $this->assertDatabaseHas('otps', [
        'phone' => $phone,
        'otp' => $otp,
        'verified' => false,
    ]);
});

test('verifies valid OTP', function () {
    $phone = '+233241234567';
    $otp = '123456';

    $this->otpService->store($phone, $otp);

    $verified = $this->otpService->verify($phone, $otp);

    expect($verified)
        ->toBeInstanceOf(Otp::class)
        ->verified->toBeTrue();
});

test('returns null for invalid OTP', function () {
    $phone = '+233241234567';

    $verified = $this->otpService->verify($phone, '999999');

    expect($verified)->toBeNull();
});

test('returns null for expired OTP', function () {
    $phone = '+233241234567';
    $otp = '123456';

    Otp::create([
        'phone' => $phone,
        'otp' => $otp,
        'expires_at' => now()->subMinutes(10),
        'verified' => false,
    ]);

    $verified = $this->otpService->verify($phone, $otp);

    expect($verified)->toBeNull();
});

test('returns null for already verified OTP', function () {
    $phone = '+233241234567';
    $otp = '123456';

    Otp::create([
        'phone' => $phone,
        'otp' => $otp,
        'expires_at' => now()->addMinutes(5),
        'verified' => true,
    ]);

    $verified = $this->otpService->verify($phone, $otp);

    expect($verified)->toBeNull();
});

test('checks if phone has recently verified OTP', function () {
    $phone = '+233241234567';

    Otp::create([
        'phone' => $phone,
        'otp' => '123456',
        'expires_at' => now()->addMinutes(5),
        'verified' => true,
        'created_at' => now()->subMinutes(5),
    ]);

    $hasRecent = $this->otpService->hasRecentlyVerified($phone);

    expect($hasRecent)->toBeTrue();
});

test('returns false for phone without recent verified OTP', function () {
    $phone = '+233241234567';

    $hasRecent = $this->otpService->hasRecentlyVerified($phone);

    expect($hasRecent)->toBeFalse();
});

test('returns false for phone with old verified OTP', function () {
    $phone = '+233241234567';

    $otp = Otp::create([
        'phone' => $phone,
        'otp' => '123456',
        'expires_at' => now()->addMinutes(5),
        'verified' => true,
    ]);

    // Manually update created_at to be 11 minutes ago
    $otp->created_at = now()->subMinutes(11);
    $otp->saveQuietly();

    $hasRecent = $this->otpService->hasRecentlyVerified($phone);

    expect($hasRecent)->toBeFalse();
});

test('cleans up expired OTPs', function () {
    Otp::create([
        'phone' => '+233241234567',
        'otp' => '123456',
        'expires_at' => now()->subMinutes(10),
        'verified' => false,
    ]);

    Otp::create([
        'phone' => '+233241234568',
        'otp' => '654321',
        'expires_at' => now()->addMinutes(5),
        'verified' => false,
    ]);

    $count = $this->otpService->cleanup();

    expect($count)->toBe(1);
    $this->assertDatabaseCount('otps', 1);
});
