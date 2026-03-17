<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $menuItemSize = $this->menuItemSize;
        $sizeKey = $menuItemSize
            ? strtolower(str_replace(' ', '_', $menuItemSize->name))
            : 'default';

        return [
            'id' => $this->id,
            'cart_id' => $this->cart_id,
            'menu_item_id' => $this->menu_item_id,
            'menu_item_size_id' => $this->menu_item_size_id,
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'subtotal' => (float) $this->subtotal,
            'special_instructions' => $this->special_instructions,
            'size_key' => $sizeKey,
            'menu_item' => $this->whenLoaded('menuItem', fn () => new MenuItemResource($this->menuItem)),
            'menu_item_size' => $this->whenLoaded('menuItemSize', fn () => new MenuItemSizeResource($this->menuItemSize)),
        ];
    }
}
