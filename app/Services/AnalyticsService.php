<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Get sales analytics.
     */
    public function getSalesAnalytics(array $filters = []): array
    {
        $query = Order::query()->whereIn('status', ['completed', 'delivered']);

        $this->applyDateFilters($query, $filters);
        $this->applyBranchFilter($query, $filters);

        $totalSales = $query->sum('total_amount');
        $totalOrders = $query->count();
        $averageOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

        // Sales by day
        $salesByDay = (clone $query)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as total'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Sales by order type
        $salesByType = (clone $query)
            ->select('order_type', DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as orders'))
            ->groupBy('order_type')
            ->get();

        return [
            'total_sales' => round($totalSales, 2),
            'total_orders' => $totalOrders,
            'average_order_value' => round($averageOrderValue, 2),
            'sales_by_day' => $salesByDay,
            'sales_by_type' => $salesByType,
        ];
    }

    /**
     * Get order analytics.
     */
    public function getOrderAnalytics(array $filters = []): array
    {
        $query = Order::query();

        $this->applyDateFilters($query, $filters);
        $this->applyBranchFilter($query, $filters);

        // Orders by status
        $ordersByStatus = (clone $query)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        // Orders by hour
        $ordersByHour = (clone $query)
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // Average preparation time
        $avgPrepTime = (clone $query)
            ->whereNotNull('estimated_prep_time')
            ->avg('estimated_prep_time');

        return [
            'orders_by_status' => $ordersByStatus,
            'orders_by_hour' => $ordersByHour,
            'average_prep_time' => round($avgPrepTime ?? 0, 2),
            'total_orders' => $query->count(),
        ];
    }

    /**
     * Get customer analytics.
     */
    public function getCustomerAnalytics(array $filters = []): array
    {
        $query = Customer::query()->with('user');

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $totalCustomers = $query->count();
        $newCustomers = (clone $query)->whereDate('created_at', '>=', now()->subDays(30))->count();

        // Top customers by order count
        $topCustomers = Customer::withCount('orders')
            ->with('user')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get();

        // Top customers by spending
        $topSpenders = Customer::with('user')
            ->select('customers.*')
            ->join('orders', 'customers.id', '=', 'orders.customer_id')
            ->where('orders.status', 'completed')
            ->groupBy('customers.id')
            ->orderByRaw('SUM(orders.total_amount) DESC')
            ->limit(10)
            ->get();

        return [
            'total_customers' => $totalCustomers,
            'new_customers_30_days' => $newCustomers,
            'top_customers_by_orders' => $topCustomers,
            'top_customers_by_spending' => $topSpenders,
        ];
    }

    /**
     * Get daily report.
     */
    public function getDailyReport(?string $date = null): array
    {
        $date = $date ?? now()->toDateString();

        $orders = Order::whereDate('created_at', $date)->get();
        $completedOrders = $orders->whereIn('status', ['completed', 'delivered']);

        return [
            'date' => $date,
            'total_orders' => $orders->count(),
            'completed_orders' => $completedOrders->count(),
            'cancelled_orders' => $orders->where('status', 'cancelled')->count(),
            'total_revenue' => round($completedOrders->sum('total_amount'), 2),
            'average_order_value' => round($completedOrders->avg('total_amount') ?? 0, 2),
            'orders_by_type' => $orders->groupBy('order_type')->map->count(),
            'orders_by_status' => $orders->groupBy('status')->map->count(),
        ];
    }

    /**
     * Get monthly report.
     */
    public function getMonthlyReport(?int $year = null, ?int $month = null): array
    {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        $orders = Order::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->get();

        $completedOrders = $orders->whereIn('status', ['completed', 'delivered']);

        // Daily breakdown
        $dailyBreakdown = Order::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->whereIn('status', ['completed', 'delivered'])
            ->select(
                DB::raw('DAY(created_at) as day'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return [
            'year' => $year,
            'month' => $month,
            'total_orders' => $orders->count(),
            'completed_orders' => $completedOrders->count(),
            'cancelled_orders' => $orders->where('status', 'cancelled')->count(),
            'total_revenue' => round($completedOrders->sum('total_amount'), 2),
            'average_order_value' => round($completedOrders->avg('total_amount') ?? 0, 2),
            'daily_breakdown' => $dailyBreakdown,
        ];
    }

    /**
     * Apply date filters to query.
     */
    protected function applyDateFilters($query, array $filters): void
    {
        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }

    /**
     * Apply branch filter to query.
     */
    protected function applyBranchFilter($query, array $filters): void
    {
        if (isset($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }
    }
}
