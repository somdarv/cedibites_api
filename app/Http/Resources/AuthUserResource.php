<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
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
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'is_guest' => $this->customer->is_guest,
                'status' => $this->customer->status instanceof \App\Enums\CustomerStatus
                    ? $this->customer->status->value
                    : ($this->customer->status ?? 'active'),
            ] : null,
            'roles' => $this->roles->pluck('name'),
            'permissions' => $this->getAllPermissions()->pluck('name'),
        ];
    }
}
