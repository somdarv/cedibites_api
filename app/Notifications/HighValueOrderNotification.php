<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class HighValueOrderNotification extends Notification implements ShouldQueue
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
        $channels = ['database'];

        // Optionally send SMS to managers for high value orders
        if ($notifiable->phone && $this->order->total_amount > 500) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toSms(object $notifiable): string
    {
        return "CediBites: High value order #{$this->order->order_number} - GHS {$this->order->total_amount}. Customer: {$this->order->customer->user->name}";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'total_amount' => $this->order->total_amount,
            'customer_name' => $this->order->customer->user->name,
            'customer_phone' => $this->order->contact_phone,
            'message' => "High value order #{$this->order->order_number} - GHS {$this->order->total_amount}",
        ];
    }
}
