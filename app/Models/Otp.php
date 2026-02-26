<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $fillable = [
        'phone',
        'otp',
        'expires_at',
        'verified',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified' => 'boolean',
        ];
    }

    /**
     * Check if OTP is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if OTP is valid (not expired and not verified yet).
     */
    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->verified;
    }

    /**
     * Mark OTP as verified.
     */
    public function markAsVerified(): void
    {
        $this->update(['verified' => true]);
    }
}
