<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewOrderNotification extends Notification implements ShouldQueue
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
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'order_type' => $this->order->order_type,
            'total_amount' => $this->order->total_amount,
            'customer_name' => $this->order->customer?->user?->name ?? $this->order->contact_name,
            'items_count' => $this->order->items->count(),
            'message' => "New order #{$this->order->order_number} received!",
        ];
    }
}
