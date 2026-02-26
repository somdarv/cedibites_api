<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeAuthResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'employee' => [
                'id' => $this->employee->id,
                'employee_no' => $this->employee->employee_no,
                'status' => $this->employee->status->value,
                'branch' => [
                    'id' => $this->employee->branch->id,
                    'name' => $this->employee->branch->name,
                    'area' => $this->employee->branch->area,
                ],
            ],
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
        ];
    }
}
