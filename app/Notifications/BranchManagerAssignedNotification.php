<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Branch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BranchManagerAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [60, 300, 900];

    public $timeout = 30;

    public function __construct(
        public Branch $branch
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Disable SMS in testing environment
        if (app()->environment('testing')) {
            $channels = ['database'];
            if ($notifiable->email) {
                $channels[] = 'mail';
            }

            return $channels;
        }

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
            ->subject("You've Been Assigned as Branch Manager")
            ->view('emails.staff.branch-manager-assigned', [
                'user' => $notifiable,
                'branch' => $this->branch,
            ]);
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(object $notifiable): string
    {
        return "CediBites: You've been assigned as manager of {$this->branch->name} branch. Check your email for more details.";
    }

    /**
     * Get the array representation of the notification (database).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'branch_id' => $this->branch->id,
            'branch_name' => $this->branch->name,
            'message' => "You've been assigned as manager of {$this->branch->name} branch.",
        ];
    }
}
