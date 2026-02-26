<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SMSService
{
    protected string $driver;

    public function __construct()
    {
        $this->driver = config('services.sms.driver', 'log');
    }

    /**
     * Send SMS message.
     */
    public function send(string $phone, string $message): bool
    {
        try {
            return match ($this->driver) {
                'log' => $this->logMessage($phone, $message),
                'africastalking' => $this->sendViaAfricasTalking($phone, $message),
                'hubtel' => $this->sendViaHubtel($phone, $message),
                default => $this->logMessage($phone, $message),
            };
        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send OTP message.
     */
    public function sendOTP(string $phone, string $otp): bool
    {
        $message = "Your CediBites verification code is: {$otp}. Valid for 5 minutes.";

        return $this->send($phone, $message);
    }

    /**
     * Log message (dev mode).
     */
    protected function logMessage(string $phone, string $message): bool
    {
        Log::info('SMS sent (dev mode)', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return true;
    }

    /**
     * Send via Africa's Talking.
     */
    protected function sendViaAfricasTalking(string $phone, string $message): bool
    {
        // TODO: Implement Africa's Talking integration
        Log::warning('Africa\'s Talking integration not yet implemented');

        return $this->logMessage($phone, $message);
    }

    /**
     * Send via Hubtel.
     */
    protected function sendViaHubtel(string $phone, string $message): bool
    {
        // TODO: Implement Hubtel integration
        Log::warning('Hubtel integration not yet implemented');

        return $this->logMessage($phone, $message);
    }
}
