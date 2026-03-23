<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuAddOnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'price' => (float) $this->price,
            'is_per_piece' => $this->is_per_piece,
            'display_order' => $this->display_order,
            'is_active' => $this->is_active,
        ];
    }
}
