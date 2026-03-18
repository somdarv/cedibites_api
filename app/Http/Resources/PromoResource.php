<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Matches frontend Promo interface.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadMissing(['branches', 'menuItems']);

        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'value' => (float) $this->value,
            'scope' => $this->scope,
            'branchIds' => $this->branches->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
            'appliesTo' => $this->applies_to,
            'itemIds' => $this->menuItems->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
            'minOrderValue' => $this->min_order_value !== null ? (float) $this->min_order_value : null,
            'maxOrderValue' => $this->max_order_value !== null ? (float) $this->max_order_value : null,
            'maxDiscount' => $this->max_discount !== null ? (float) $this->max_discount : null,
            'startDate' => $this->start_date->format('Y-m-d'),
            'endDate' => $this->end_date->format('Y-m-d'),
            'isActive' => (bool) $this->is_active,
            'accountingCode' => $this->accounting_code,
        ];
    }
}
