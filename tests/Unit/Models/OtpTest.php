<?php

use App\Models\Otp;

test('checks if OTP is expired', function () {
    $expiredOtp = Otp::create([
        'phone' => '+233241234567',
        'otp' => '123456',
        'expires_at' => now()->subMinutes(10),
        'verified' => false,
    ]);

    $validOtp = Otp::create([
        'phone' => '+233241234568',
        'otp' => '654321',
        'expires_at' => now()->addMinutes(5),
        'verified' => false,
    ]);

    expect($expiredOtp->isExpired())->toBeTrue();
    expect($validOtp->isExpired())->toBeFalse();
});

test('checks if OTP is valid', function () {
    $validOtp = Otp::create([
        'phone' => '+233241234567',
        'otp' => '123456',
        'expires_at' => now()->addMinutes(5),
        'verified' => false,
    ]);

    $expiredOtp = Otp::create([
        'phone' => '+233241234568',
        'otp' => '654321',
        'expires_at' => now()->subMinutes(10),
        'verified' => false,
    ]);

    $verifiedOtp = Otp::create([
        'phone' => '+233241234569',
        'otp' => '111111',
        'expires_at' => now()->addMinutes(5),
        'verified' => true,
    ]);

    expect($validOtp->isValid())->toBeTrue();
    expect($expiredOtp->isValid())->toBeFalse();
    expect($verifiedOtp->isValid())->toBeFalse();
});

test('marks OTP as verified', function () {
    $otp = Otp::create([
        'phone' => '+233241234567',
        'otp' => '123456',
        'expires_at' => now()->addMinutes(5),
        'verified' => false,
    ]);

    expect($otp->verified)->toBeFalse();

    $otp->markAsVerified();

    expect($otp->fresh()->verified)->toBeTrue();
});

test('casts expires_at to datetime', function () {
    $otp = Otp::create([
        'phone' => '+233241234567',
        'otp' => '123456',
        'expires_at' => now()->addMinutes(5),
        'verified' => false,
    ]);

    expect($otp->expires_at)->toBeInstanceOf(Carbon\Carbon::class);
});

test('casts verified to boolean', function () {
    $otp = Otp::create([
        'phone' => '+233241234567',
        'otp' => '123456',
        'expires_at' => now()->addMinutes(5),
        'verified' => false,
    ]);

    expect($otp->verified)->toBeBool();
});
