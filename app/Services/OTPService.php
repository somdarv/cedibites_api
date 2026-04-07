<?php

namespace App\Services;

use App\Models\Otp;

class OTPService
{
    /**
     * Generate a 6-digit OTP.
     */
    public function generate(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Store OTP with 5-minute expiry.
     */
    public function store(string $phone, string $otp, ?string $ipAddress = null): Otp
    {
        return Otp::create([
            'phone' => $phone,
            'otp' => hash('sha256', $otp),
            'expires_at' => now()->addMinutes(5),
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Verify OTP and mark as verified.
     */
    public function verify(string $phone, string $otp): ?Otp
    {
        $otpRecord = Otp::where('phone', $phone)
            ->where('otp', hash('sha256', $otp))
            ->where('verified', false)
            ->latest()
            ->first();

        if (! $otpRecord || $otpRecord->isExpired()) {
            return null;
        }

        $otpRecord->markAsVerified();

        return $otpRecord;
    }

    /**
     * Check if phone has recently verified OTP (within 10 minutes).
     */
    public function hasRecentlyVerified(string $phone): bool
    {
        return Otp::where('phone', $phone)
            ->where('verified', true)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();
    }

    /**
     * Cleanup expired and old verified OTPs.
     */
    public function cleanup(): int
    {
        return Otp::where('expires_at', '<', now())
            ->orWhere(function ($query) {
                $query->where('verified', true)
                    ->where('created_at', '<', now()->subHour());
            })
            ->delete();
    }
}
