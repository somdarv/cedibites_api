<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class CancelRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $reason,
        public string $requestedByName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(mixed $notifiable, mixed $notification): WebPushMessage
    {
        $orderNumber = $this->order->order_number;
        $customerName = $this->order->contact_name ?? 'Customer';

        return (new WebPushMessage)
            ->title("Cancel Request — #{$orderNumber}")
            ->body("{$this->requestedByName} requested cancellation for {$customerName}'s order: {$this->reason}")
            ->action('View Order', "view_order_{$this->order->id}")
            ->badge('/cblogo.webp')
            ->icon('/cblogo.webp')
            ->tag("cancel-request-{$this->order->id}")
            ->data([
                'order_id' => $this->order->id,
                'order_number' => $orderNumber,
                'type' => 'cancel_requested',
                'url' => '/admin/orders',
            ]);
    }
}
