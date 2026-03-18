<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [60, 300, 900];

    public $timeout = 30;

    public function via(object $notifiable): array
    {
        $channels = ['database', SmsChannel::class];

        if ($notifiable->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to CediBites!')
            ->view('emails.welcome', ['user' => $notifiable]);
    }

    public function toSms(object $notifiable): string
    {
        return "Welcome to CediBites, {$notifiable->name}! Order delicious meals from your favorite local spots. Start exploring now!";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "Welcome to CediBites, {$notifiable->name}! We're excited to have you here.",
        ];
    }
}
