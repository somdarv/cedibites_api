<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemSizeResource extends JsonResource
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
            'menu_item_id' => $this->menu_item_id,
            'size_key' => strtolower(str_replace(' ', '_', $this->name)),
            'size_label' => $this->name,
            'price' => (float) $this->price,
            'is_available' => $this->is_available,
        ];
    }
}
