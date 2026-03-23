<?php

namespace App\Events;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public string $changeType,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("orders.branch.{$this->order->branch_id}"),
            new Channel("orders.{$this->order->order_number}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->changeType,
            'order' => (new OrderResource(
                $this->order->load(['branch', 'items.menuItem', 'items.menuItemOption.media'])
            ))->toArray(request()),
        ];
    }
}
