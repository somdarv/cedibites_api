<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'phone' => $this->phone,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'opening_time' => $this->opening_time,
            'closing_time' => $this->closing_time,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'employees' => EmployeeResource::collection($this->whenLoaded('employees')),
            'employees_count' => $this->whenCounted('employees'),
            'orders_count' => $this->whenCounted('orders'),
            'stats' => $this->when(isset($this->stats), fn () => $this->stats),
        ];
    }
}
