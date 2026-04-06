<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmartCategorySettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enum = $this->smartCategory();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $enum->label(),
            'icon' => $enum->icon(),
            'is_enabled' => $this->is_enabled,
            'display_order' => $this->display_order,
            'item_limit' => $this->item_limit,
            'is_time_based' => $enum->isTimeBased(),
            'requires_customer' => $enum->requiresCustomer(),
            'visible_hour_start' => $this->visible_hour_start,
            'visible_hour_end' => $this->visible_hour_end,
            'default_visible_hour_start' => $enum->visibleHours()['start'] ?? null,
            'default_visible_hour_end' => $enum->visibleHours()['end'] ?? null,
            'default_item_limit' => $enum->defaultLimit(),
        ];
    }
}
