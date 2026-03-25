<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OrderManagementService
{
    /**
     * Get orders for employee's branch (or all branches for admin/super_admin).
     */
    public function getBranchOrders(User $user, array $filters = []): Builder
    {
        $employee = $user->employee;
        $canSeeAllOrders = $user->hasRole('super_admin') || $user->hasRole('admin');

        // No payment filter here - admin sees all orders by default
        $query = Order::with(['customer.user', 'items.menuItemOption.menuItem', 'payments', 'branch', 'statusHistory.changedBy', 'assignedEmployee.user']);

        $employeeBranchIds = $employee?->branches()->pluck('branches.id');

        if (! $canSeeAllOrders && (! $employee || $employeeBranchIds->isEmpty())) {
            return Order::query()->whereRaw('1 = 0');
        }

        if (! $canSeeAllOrders) {
            $query->whereIn('branch_id', $employeeBranchIds);
        }

        if (! empty($filters['branch_id'])) {
            $bid = (int) $filters['branch_id'];
            if (! $canSeeAllOrders && $employee && ! $employee->branches()->where('branches.id', $bid)->exists()) {
                return Order::query()->whereRaw('1 = 0');
            }
            $query->where('branch_id', $bid);
        }

        if (! empty($filters['branch_name'])) {
            $query->whereHas('branch', fn ($q) => $q->where('name', 'like', '%'.$filters['branch_name'].'%')
                ->orWhere('area', 'like', '%'.$filters['branch_name'].'%'));
        }

        if (! empty($filters['staff_id'])) {
            $query->where('assigned_employee_id', $filters['staff_id']);
        }

        if (! empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $query->whereIn('status', $statuses);
        }

        if (! empty($filters['order_type'])) {
            $types = is_array($filters['order_type']) ? $filters['order_type'] : [$filters['order_type']];
            $query->whereIn('order_type', $types);
        }

        if (! empty($filters['order_source'])) {
            $sources = is_array($filters['order_source']) ? $filters['order_source'] : [$filters['order_source']];
            $query->whereIn('order_source', $sources);
        }

        if (! empty($filters['contact_phone'])) {
            $query->where('contact_phone', 'like', '%'.$filters['contact_phone'].'%');
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', '%'.$search.'%')
                    ->orWhere('contact_name', 'like', '%'.$search.'%')
                    ->orWhere('contact_phone', 'like', '%'.$search.'%')
                    ->orWhereHas('customer.user', fn ($uq) => $uq->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%'));
            });
        }

        if (! empty($filters['payment_status'])) {
            $statuses = is_array($filters['payment_status']) ? $filters['payment_status'] : [$filters['payment_status']];
            $query->whereHas('payments', fn ($q) => $q->whereIn('payment_status', $statuses));
        }

        if (! empty($filters['payment_method'])) {
            $methods = is_array($filters['payment_method']) ? $filters['payment_method'] : [$filters['payment_method']];
            $query->whereHas('payments', fn ($q) => $q->whereIn('payment_method', $methods));
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

        $branchIds = $employee->branches()->pluck('branches.id');
        if ($branchIds->isEmpty()) {
            return $this->emptyStats();
        }

        $today = now()->startOfDay();

        return [
            'pending_orders' => Order::whereIn('branch_id', $branchIds)
                ->paymentConfirmed()
                ->where('status', 'received')
                ->count(),

            'preparing_orders' => Order::whereIn('branch_id', $branchIds)
                ->paymentConfirmed()
                ->whereIn('status', ['preparing', 'ready', 'ready_for_pickup', 'out_for_delivery'])
                ->count(),

            'today_orders' => Order::whereIn('branch_id', $branchIds)
                ->paymentConfirmed()
                ->whereDate('created_at', $today)
                ->count(),

            'today_revenue' => Order::whereIn('branch_id', $branchIds)
                ->whereDate('created_at', $today)
                ->whereIn('status', ['completed', 'delivered'])
                ->sum('total_amount'),

            'completed_today' => Order::whereIn('branch_id', $branchIds)
                ->whereDate('created_at', $today)
                ->whereIn('status', ['completed', 'delivered'])
                ->count(),
        ];
    }

    /**
     * Update order status.
     *
     * @param  \App\Models\User|null  $causer  User who performed the status change (e.g. employee)
     */
    public function updateOrderStatus(Order $order, string $status, ?string $notes = null, ?User $causer = null): Order
    {
        $oldStatus = $order->status;

        $updateData = ['status' => $status];

        // Auto-assign employee when order is accepted
        if ($status === 'accepted' && ! $order->assigned_employee_id && $causer?->employee) {
            $updateData['assigned_employee_id'] = $causer->employee->id;
        }

        $order->update($updateData);

        if ($notes) {
            $order->statusHistory()->create([
                'status' => $status,
                'notes' => $notes,
                'changed_at' => now(),
            ]);
        }

        if ($causer) {
            activity('orders')
                ->causedBy($causer)
                ->performedOn($order)
                ->withProperties([
                    'old_status' => $oldStatus,
                    'new_status' => $status,
                    'notes' => $notes,
                    'assigned_employee_id' => $order->assigned_employee_id,
                ])
                ->event('status_changed')
                ->log("Order {$order->order_number} status changed to {$status}");
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

        $branchIds = $employee->branches()->pluck('branches.id');
        if ($branchIds->isEmpty()) {
            return Order::query()->whereRaw('1 = 0');
        }

        return Order::with(['customer.user', 'items.menuItemOption.menuItem', 'branch'])
            ->whereIn('branch_id', $branchIds)
            ->paymentConfirmed()
            ->whereIn('status', ['received', 'preparing', 'ready', 'ready_for_pickup', 'out_for_delivery'])
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
