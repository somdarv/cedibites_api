<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'order_type' => $this->order_type,
            'order_source' => $this->order_source,
            'payment_method' => $this->payment_method,
            'subtotal' => (float) $this->subtotal,
            'delivery_fee' => (float) $this->delivery_fee,
            'tax_amount' => (float) $this->tax_amount,
            'total_amount' => (float) $this->total_amount,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'delivery_address' => $this->delivery_address,
            'delivery_note' => $this->delivery_note,
            'branch_id' => $this->branch_id,
            'branch' => [
                'id' => $this->branch?->id,
                'name' => $this->branch?->name ?? '—',
                'address' => $this->branch?->address,
                'phone' => $this->branch?->phone,
                'latitude' => $this->branch?->latitude,
                'longitude' => $this->branch?->longitude,
            ],
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'menu_item_id' => $item->menu_item_id,
                'menu_item_option_id' => $item->menu_item_option_id,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
                'special_instructions' => $item->special_instructions,
                'menu_item' => [
                    'id' => $item->menuItem?->id,
                    'name' => $item->menuItem?->name,
                    'category' => $item->menuItem?->category?->name,
                ],
                'option' => $item->menuItemOption ? [
                    'id' => $item->menuItemOption->id,
                    'option_key' => $item->menuItemOption->option_key,
                    'option_label' => $item->menuItemOption->option_label,
                    'image_url' => $item->menuItemOption->getFirstMediaUrl('menu-item-options') ?: null,
                ] : null,
                'option_snapshot' => $item->menu_item_option_snapshot,
            ]),
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->user?->name,
                'phone' => $this->customer->user?->phone,
            ] : null,
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'payment_method' => $payment->payment_method,
                'payment_status' => $payment->payment_status,
                'amount' => (float) $payment->amount,
                'transaction_id' => $payment->transaction_id,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ])),
            'payment' => $this->whenLoaded('payments', function () {
                $payment = $this->payments->first();

                return $payment ? [
                    'id' => $payment->id,
                    'payment_method' => $payment->payment_method,
                    'payment_status' => $payment->payment_status,
                    'amount' => (float) $payment->amount,
                    'transaction_id' => $payment->transaction_id,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                ] : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
