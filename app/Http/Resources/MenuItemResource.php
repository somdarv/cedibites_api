<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        
        // Add image_url from media library
        $data['image_url'] = $this->getFirstMediaUrl('menu-items') ?: null;
        
        // Add is_new field (items created in the last 7 days)
        $data['is_new'] = $this->created_at->isAfter(now()->subDays(7));
        
        // Transform sizes using MenuItemSizeResource
        if (isset($data['sizes'])) {
            $data['sizes'] = MenuItemSizeResource::collection($this->sizes);
        }
        
        return $data;
    }
}
