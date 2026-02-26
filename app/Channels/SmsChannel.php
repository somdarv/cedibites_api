<?php

namespace App\Channels;

use App\Services\SMSService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SmsChannel
{
    public function __construct(
        protected SMSService $smsService
    ) {}

    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $phone = $notifiable->phone ?? $notifiable->customer?->phone;

        if (! $phone) {
            Log::warning('Cannot send SMS notification: no phone number', [
                'notifiable_id' => $notifiable->id,
                'notification' => get_class($notification),
            ]);

            return;
        }

        $message = $notification->toSms($notifiable);

        try {
            $this->smsService->send($phone, $message);

            Log::info('SMS notification sent', [
                'notifiable_id' => $notifiable->id,
                'phone' => $phone,
                'notification' => get_class($notification),
            ]);
        } catch (\Exception $e) {
            Log::error('SMS notification failed', [
                'notifiable_id' => $notifiable->id,
                'phone' => $phone,
                'notification' => get_class($notification),
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger queue retry
        }
    }
}
