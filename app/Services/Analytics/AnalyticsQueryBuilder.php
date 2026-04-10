<?php

namespace App\Services\Analytics;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Canonical query factory for analytics.
 *
 * Every analytics computation MUST use these builders so that
 * "revenue", "placed order", "active order", etc. mean exactly
 * one thing everywhere in the system.
 *
 * @see docs/agents/analytics-auditor-kb.md §1 for definitions
 */
class AnalyticsQueryBuilder
{
    /**
     * Order statuses considered "active" (in-progress, not terminal).
     *
     * @var string[]
     */
    public const ACTIVE_STATUSES = [
        'received',
        'accepted',
        'preparing',
        'ready',
        'ready_for_pickup',
        'out_for_delivery',
    ];

    /**
     * Order statuses considered "completed" (terminal success).
     *
     * @var string[]
     */
    public const COMPLETED_STATUSES = ['completed', 'delivered'];

    /**
     * Placed orders — orders with a confirmed payment (completed, no_charge, or refunded).
     * This is the universal base for all analytics that count "real" orders.
     */
    public function placedOrders(array $filters = []): Builder
    {
        $query = Order::whereHas('payments', fn (Builder $q) => $q->whereIn('payment_status', ['completed', 'no_charge', 'refunded']));

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Revenue-contributing orders — placed, not cancelled, payment not refunded.
     *
     * Revenue = SUM(total_amount) of these orders.
     * Excludes: cancelled orders, refunded payments, no_charge orders.
     */
    public function revenueOrders(array $filters = []): Builder
    {
        return $this->placedOrders($filters)
            ->where('status', '!=', 'cancelled')
            ->whereHas('payments', fn (Builder $q) => $q->where('payment_status', 'completed'));
    }

    /**
     * No-charge orders — placed, not cancelled, payment is no_charge.
     * Tracked separately from revenue.
     */
    public function noChargeOrders(array $filters = []): Builder
    {
        return $this->placedOrders($filters)
            ->where('status', '!=', 'cancelled')
            ->whereHas('payments', fn (Builder $q) => $q->where('payment_status', 'no_charge'));
    }

    /**
     * Cancelled placed orders — orders that were placed then cancelled.
     */
    public function cancelledOrders(array $filters = []): Builder
    {
        $query = Order::query()
            ->where('status', 'cancelled')
            ->whereHas('payments', fn (Builder $q) => $q->whereIn('payment_status', ['completed', 'no_charge', 'refunded']));

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Active orders — currently in progress (not date-filtered by default).
     */
    public function activeOrders(array $filters = []): Builder
    {
        $query = Order::paymentConfirmed()
            ->whereIn('status', self::ACTIVE_STATUSES);

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Completed orders — terminal success statuses.
     */
    public function completedOrders(array $filters = []): Builder
    {
        return $this->placedOrders($filters)
            ->whereIn('status', self::COMPLETED_STATUSES);
    }

    /**
     * OrderItem query joined to orders — for item-level analytics.
     * Includes both completed and no_charge orders (represents real demand).
     */
    public function orderItems(array $filters = []): Builder|\Illuminate\Database\Query\Builder
    {
        $query = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereExists(function ($q) {
                $q->from('payments')
                    ->whereColumn('payments.order_id', 'orders.id')
                    ->whereIn('payments.payment_status', ['completed', 'no_charge']);
            })
            ->where('orders.status', '!=', 'cancelled');

        $this->applyFilters($query, $filters, 'orders');

        return $query;
    }

    /**
     * Payment query joined to orders — for payment-level analytics.
     */
    public function payments(array $filters = [], string $paymentStatus = 'completed'): Builder|\Illuminate\Database\Query\Builder
    {
        $query = Payment::query()
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->where('payments.payment_status', $paymentStatus)
            ->where('orders.status', '!=', 'cancelled');

        $this->applyFilters($query, $filters, 'orders');

        return $query;
    }

    /**
     * Compute revenue (the canonical number).
     */
    public function computeRevenue(array $filters = []): float
    {
        return round((float) $this->revenueOrders($filters)->sum('total_amount'), 2);
    }

    /**
     * Compute total placed order count.
     */
    public function computePlacedOrderCount(array $filters = []): int
    {
        return $this->placedOrders($filters)->count();
    }

    /**
     * Compute revenue-contributing order count (for AOV denominator).
     */
    public function computeRevenueOrderCount(array $filters = []): int
    {
        return $this->revenueOrders($filters)->count();
    }

    /**
     * Apply standard filters: date_from, date_to, branch_id, employee_id.
     */
    public function applyFilters(Builder|\Illuminate\Database\Query\Builder $query, array $filters, ?string $tablePrefix = null): void
    {
        $col = fn (string $name) => $tablePrefix ? "{$tablePrefix}.{$name}" : $name;

        if (isset($filters['date_from'])) {
            $query->whereDate($col('created_at'), '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate($col('created_at'), '<=', $filters['date_to']);
        }

        if (isset($filters['branch_id'])) {
            $query->where($col('branch_id'), $filters['branch_id']);
        }

        if (isset($filters['branch_ids'])) {
            $query->whereIn($col('branch_id'), $filters['branch_ids']);
        }

        if (isset($filters['employee_id'])) {
            $query->where($col('assigned_employee_id'), $filters['employee_id']);
        }
    }
}
