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
                'menu_item_size_id' => $item->menu_item_size_id,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
                'special_instructions' => $item->special_instructions,
                'menu_item' => [
                    'id' => $item->menuItem?->id,
                    'name' => $item->menuItem?->name,
                    'category' => $item->menuItem?->category?->name,
                ],
                'size' => $item->menuItemSize ? [
                    'id' => $item->menuItemSize->id,
                    'size_key' => $item->menuItemSize->size_key,
                    'size_label' => $item->menuItemSize->size_label,
                ] : null,
            ]),
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->user?->name,
                'phone' => $this->customer->user?->phone,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
