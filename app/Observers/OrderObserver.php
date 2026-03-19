<?php

namespace App\Observers;

use App\Models\Employee;
use App\Models\Order;
use App\Notifications\HighValueOrderNotification;
use App\Notifications\NewOrderNotification;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderCompletedNotification;
use App\Notifications\OrderConfirmedNotification;
use App\Notifications\OrderOutForDeliveryNotification;
use App\Notifications\OrderPreparingNotification;
use App\Notifications\OrderReadyNotification;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // Record initial status in history
        $order->statusHistory()->create([
            'status' => $order->status,
            'changed_by_type' => 'system',
            'changed_at' => now(),
        ]);

        // Notify customer when order is created
        $order->customer?->user?->notify(new OrderConfirmedNotification($order));

        // Notify all active employees at the branch
        $this->notifyBranchEmployees($order);

        // Notify manager for high value orders
        if ($order->total_amount > 200) {
            $this->notifyBranchManager($order);
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Only act if status changed
        if (! $order->wasChanged('status')) {
            return;
        }

        // Record status change in history
        $order->statusHistory()->create([
            'status' => $order->status,
            'changed_by_type' => 'system',
            'changed_at' => now(),
        ]);

        $customer = $order->customer?->user;

        match ($order->status) {
            'preparing' => $customer?->notify(new OrderPreparingNotification($order)),
            'ready', 'ready_for_pickup' => $customer?->notify(new OrderReadyNotification($order)),
            'out_for_delivery' => $customer?->notify(new OrderOutForDeliveryNotification($order)),
            'completed', 'delivered' => $customer?->notify(new OrderCompletedNotification($order)),
            'cancelled' => $customer?->notify(new OrderCancelledNotification($order)),
            default => null,
        };
    }

    /**
     * Notify all active employees at the branch.
     */
    protected function notifyBranchEmployees(Order $order): void
    {
        $employees = Employee::whereHas('branches', fn ($q) => $q->where('branches.id', $order->branch_id))
            ->where('status', 'active')
            ->with('user')
            ->get();

        foreach ($employees as $employee) {
            $employee->user?->notify(new NewOrderNotification($order));
        }
    }

    /**
     * Notify branch manager for high value orders.
     */
    protected function notifyBranchManager(Order $order): void
    {
        $manager = Employee::whereHas('branches', fn ($q) => $q->where('branches.id', $order->branch_id))
            ->whereHas('user.roles', fn ($q) => $q->where('name', 'manager'))
            ->with('user')
            ->first();

        $manager?->user?->notify(new HighValueOrderNotification($order));
    }
}
