<?php

namespace App\Services;

use App\Models\Order;

class OrderNumberService
{
    /**
     * Generate the next unique order number.
     *
     * Format: "CB" followed by 6 zero-padded digits (CB000001, CB000002, …).
     */
    public function generate(): string
    {
        $last = Order::where('order_number', 'like', 'CB%')
            ->orderByDesc('order_number')
            ->value('order_number');

        $next = $last ? $this->increment($last) : 'CB000001';

        // Collision safety — retry if somehow already taken
        while (Order::where('order_number', $next)->exists()) {
            $next = $this->increment($next);
        }

        return $next;
    }

    /**
     * Increment a CB-format order number to the next value.
     */
    private function increment(string $orderNumber): string
    {
        $num = (int) substr($orderNumber, 2);

        return 'CB'.str_pad($num + 1, 6, '0', STR_PAD_LEFT);
    }
}
