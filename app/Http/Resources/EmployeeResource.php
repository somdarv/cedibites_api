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
        $hasBranches = $this->relationLoaded('branches');
        $branchIds = $hasBranches ? $this->branches->pluck('id')->values()->all() : [];
        $branchList = $hasBranches ? $this->branches->map(fn ($b) => [
            'id' => $b->id,
            'name' => $b->name,
            'location' => $b->location ?? null,
        ])->values()->all() : [];

        // Get user permissions if roles are loaded with permissions
        $permissions = [];
        if ($this->user->relationLoaded('roles')) {
            foreach ($this->user->roles as $role) {
                if ($role->relationLoaded('permissions')) {
                    $permissions = array_merge($permissions, $role->permissions->pluck('name')->toArray());
                }
            }
        }

        // Add direct user permissions
        if ($this->user->relationLoaded('permissions')) {
            $permissions = array_merge($permissions, $this->user->permissions->pluck('name')->toArray());
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'employee_no' => $this->employee_no,
            'status' => $this->status,
            'hire_date' => $this->hire_date?->toDateString(),
            'ssnit_number' => $this->ssnit_number,
            'ghana_card_id' => $this->ghana_card_id,
            'tin_number' => $this->tin_number,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'nationality' => $this->nationality,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relationship' => $this->emergency_contact_relationship,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'roles' => $this->user->roles->pluck('name'),
                'permissions' => array_unique($permissions),
            ],
            'branch_ids' => $branchIds,
            'branch' => $hasBranches && $this->branches->isNotEmpty()
                ? [
                    'id' => $this->branches->first()->id,
                    'name' => $this->branches->first()->name,
                    'location' => $this->branches->first()->location,
                ]
                : null,
            'branches' => $branchList,
        ];
    }
}
