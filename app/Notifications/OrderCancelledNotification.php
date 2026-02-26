<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [60, 300, 900];

    public $timeout = 30;

    public function __construct(
        public Order $order
    ) {}

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
            ->subject("Order #{$this->order->order_number} Cancelled")
            ->view('emails.orders.cancelled', ['order' => $this->order]);
    }

    public function toSms(object $notifiable): string
    {
        $reason = $this->order->cancelled_reason ?? 'Customer request';

        return "CediBites: Order #{$this->order->order_number} has been cancelled. Reason: {$reason}. Refund processing.";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => 'cancelled',
            'cancelled_reason' => $this->order->cancelled_reason,
            'message' => "Order #{$this->order->order_number} has been cancelled.",
        ];
    }
}
