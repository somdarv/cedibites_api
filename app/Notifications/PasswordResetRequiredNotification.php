<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [60, 300, 900];

    public $timeout = 30;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', SmsChannel::class];

        if ($notifiable->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Password Reset Required - CediBites')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your administrator has requested that you reset your password for security reasons.')
            ->line('You will be required to set a new password the next time you log in to the CediBites staff portal.')
            ->line('This is a security measure to ensure your account remains protected.')
            ->line('If you have any questions, please contact your administrator.')
            ->salutation('Thank you, The CediBites Team');
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(): string
    {
        return 'CediBites: Your administrator has required a password reset. You will be prompted to set a new password on your next login to the staff portal.';
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Password reset required by administrator',
            'required_at' => now()->toISOString(),
        ];
    }
}
