<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $firstOption = $this->relationLoaded('options')
            ? $this->options->sortBy('display_order')->first()
            : null;

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_available' => $this->is_available,
            'rating' => $this->rating,
            'rating_count' => $this->rating_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'branch' => $this->whenLoaded('branch', fn () => new BranchResource($this->branch)),
            'category' => $this->whenLoaded('category', fn () => new MenuCategoryResource($this->category)),
            'image_url' => ($media = $firstOption?->getFirstMedia('menu-item-options'))
                ? route('media.show', $media)
                : null,
            'options' => MenuItemOptionResource::collection($this->whenLoaded('options')),
            'tags' => MenuTagResource::collection($this->whenLoaded('tags')),
            'add_ons' => MenuAddOnResource::collection($this->whenLoaded('addOns')),
        ];
    }
}
