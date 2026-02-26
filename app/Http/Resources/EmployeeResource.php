<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'branch_id' => $this->branch_id,
            'employee_no' => $this->employee_no,
            'status' => $this->status,
            'hire_date' => $this->hire_date?->toDateString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'roles' => $this->user->roles->pluck('name'),
            ],
            'branch' => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'location' => $this->branch->location,
            ],
        ];
    }
}
