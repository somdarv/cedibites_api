<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeAuthResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Returns StaffUser shape for frontend compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roles = $this->getRoleNames();
        $role = $roles->first() ?? 'employee';

        $firstBranch = $this->employee->branches->first();

        return [
            'id' => (string) $this->employee->id,
            'name' => $this->name,
            'role' => $role,
            'branch' => $firstBranch?->name ?? '',
            'branchId' => (string) ($firstBranch?->id ?? ''),
            'branchIds' => $this->employee->branches->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
            'roles' => $roles,
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'must_reset_password' => (bool) $this->must_reset_password,
        ];
    }
}
