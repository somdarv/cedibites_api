<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Matches frontend StaffShift interface.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadMissing(['employee.user', 'branch', 'shiftOrders.order']);

        return [
            'id' => (string) $this->id,
            'staffId' => (string) $this->employee_id,
            'staffName' => $this->employee?->user?->name ?? '',
            'branchId' => (string) $this->branch_id,
            'branchName' => $this->branch?->name ?? '',
            'loginAt' => $this->login_at->getTimestamp() * 1000,
            'logoutAt' => $this->logout_at?->getTimestamp() * 1000,
            'orderIds' => $this->shiftOrders->pluck('order.order_number')->filter()->values()->all(),
            'totalSales' => (float) $this->total_sales,
            'orderCount' => (int) $this->order_count,
        ];
    }
}
