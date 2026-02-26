<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OrderManagementService
{
    /**
     * Get orders for employee's branch.
     */
    public function getBranchOrders(User $user, array $filters = []): Builder
    {
        $employee = $user->employee;

        if (! $employee) {
            return Order::query()->whereRaw('1 = 0'); // Return empty query
        }

        $query = Order::with(['customer.user', 'orderItems.menuItemSize.menuItem', 'payment'])
            ->where('branch_id', $employee->branch_id);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['order_type'])) {
            $query->where('order_type', $filters['order_type']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->latest();
    }

    /**
     * Get order statistics for employee's branch.
     */
    public function getBranchStats(User $user): array
    {
        $employee = $user->employee;

        if (! $employee) {
            return $this->emptyStats();
        }

        $today = now()->startOfDay();

        return [
            'pending_orders' => Order::where('branch_id', $employee->branch_id)
                ->where('status', 'pending')
                ->count(),

            'preparing_orders' => Order::where('branch_id', $employee->branch_id)
                ->whereIn('status', ['confirmed', 'preparing'])
                ->count(),

            'today_orders' => Order::where('branch_id', $employee->branch_id)
                ->whereDate('created_at', $today)
                ->count(),

            'today_revenue' => Order::where('branch_id', $employee->branch_id)
                ->whereDate('created_at', $today)
                ->whereIn('status', ['completed', 'delivered'])
                ->sum('total_amount'),

            'completed_today' => Order::where('branch_id', $employee->branch_id)
                ->whereDate('created_at', $today)
                ->whereIn('status', ['completed', 'delivered'])
                ->count(),
        ];
    }

    /**
     * Update order status.
     */
    public function updateOrderStatus(Order $order, string $status, ?string $notes = null): Order
    {
        $order->update(['status' => $status]);

        if ($notes) {
            $order->statusHistory()->create([
                'status' => $status,
                'notes' => $notes,
            ]);
        }

        return $order->fresh();
    }

    /**
     * Get pending orders for quick view.
     */
    public function getPendingOrders(User $user): Builder
    {
        $employee = $user->employee;

        if (! $employee) {
            return Order::query()->whereRaw('1 = 0');
        }

        return Order::with(['customer.user', 'orderItems.menuItemSize.menuItem'])
            ->where('branch_id', $employee->branch_id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->latest();
    }

    /**
     * Empty stats array.
     */
    protected function emptyStats(): array
    {
        return [
            'pending_orders' => 0,
            'preparing_orders' => 0,
            'today_orders' => 0,
            'today_revenue' => 0,
            'completed_today' => 0,
        ];
    }
}
