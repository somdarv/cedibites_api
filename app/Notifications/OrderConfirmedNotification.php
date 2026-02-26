<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public $timeout = 30;

    public function __construct(
        public Order $order
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', SmsChannel::class];

        // Add email if user has email address
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
            ->subject("Order #{$this->order->order_number} Confirmed")
            ->view('emails.orders.confirmed', ['order' => $this->order]);
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(object $notifiable): string
    {
        return "CediBites: Order #{$this->order->order_number} confirmed! ".
               "Total: GHS {$this->order->total_amount}. ".
               "Estimated time: {$this->order->estimated_prep_time} mins.";
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'total_amount' => $this->order->total_amount,
            'estimated_time' => $this->order->estimated_prep_time,
            'message' => "Your order #{$this->order->order_number} has been confirmed!",
        ];
    }
}
