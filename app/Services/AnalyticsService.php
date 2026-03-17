<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
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

        // Orders by hour - PostgreSQL compatible
        $driver = DB::connection()->getDriverName();
        $hourExpression = $driver === 'pgsql'
            ? 'EXTRACT(HOUR FROM created_at)'
            : 'HOUR(created_at)';

        $ordersByHour = (clone $query)
            ->select(DB::raw("{$hourExpression} as hour"), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw($hourExpression))
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

        // Top customers by order count with spending and last order
        $topCustomers = Customer::withCount('orders')
            ->with(['user', 'orders' => function ($query) {
                $query->whereIn('status', ['completed', 'delivered'])
                    ->orderByDesc('created_at')
                    ->limit(1);
            }])
            ->addSelect([
                'total_spend' => Order::selectRaw('SUM(total_amount)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereIn('status', ['completed', 'delivered']),
            ])
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->user?->name ?? $customer->contact_name ?? 'Guest Customer',
                    'orders_count' => $customer->orders_count,
                    'total_spend' => round($customer->total_spend ?? 0, 2),
                    'last_order_date' => $customer->orders->first()?->created_at?->format('Y-m-d'),
                    'user' => $customer->user ? [
                        'name' => $customer->user->name,
                        'phone' => $customer->user->phone,
                    ] : null,
                ];
            });

        // Top customers by spending
        $topSpenders = Customer::with(['user', 'orders' => function ($query) {
            $query->whereIn('status', ['completed', 'delivered'])
                ->orderByDesc('created_at')
                ->limit(1);
        }])
            ->addSelect([
                'total_spend' => Order::selectRaw('SUM(total_amount)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereIn('status', ['completed', 'delivered']),
            ])
            ->orderByDesc('total_spend')
            ->limit(10)
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->user?->name ?? $customer->contact_name ?? 'Guest Customer',
                    'total_spend' => round($customer->total_spend ?? 0, 2),
                    'last_order_date' => $customer->orders->first()?->created_at?->format('Y-m-d'),
                    'user' => $customer->user ? [
                        'name' => $customer->user->name,
                        'phone' => $customer->user->phone,
                    ] : null,
                ];
            });

        return [
            'total_customers' => $totalCustomers,
            'new_customers_30_days' => $newCustomers,
            'top_customers_by_orders' => $topCustomers,
            'top_customers_by_spending' => $topSpenders,
        ];
    }

    /**
     * Get order source analytics.
     */
    public function getOrderSourceAnalytics(array $filters = []): array
    {
        $query = Order::query()->whereIn('status', ['completed', 'delivered']);

        $this->applyDateFilters($query, $filters);
        $this->applyBranchFilter($query, $filters);

        $sources = (clone $query)
            ->select(
                'order_source',
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(total_amount) as avg_value')
            )
            ->groupBy('order_source')
            ->get();

        $totalOrders = $sources->sum('count');

        return $sources->map(function ($source) use ($totalOrders) {
            return [
                'name' => ucfirst($source->order_source ?? 'Unknown'),
                'count' => $source->count,
                'pct' => $totalOrders > 0 ? round(($source->count / $totalOrders) * 100) : 0,
                'avgValue' => round($source->avg_value ?? 0, 2),
            ];
        })->sortByDesc('count')->values()->toArray();
    }

    /**
     * Get top items analytics.
     */
    public function getTopItemsAnalytics(array $filters = [], int $limit = 10): array
    {
        $query = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->whereIn('orders.status', ['completed', 'delivered']);

        $this->applyDateFilters($query, $filters, 'orders');
        $this->applyBranchFilter($query, $filters, 'orders');

        $items = $query
            ->select(
                'menu_items.id',
                'menu_items.name',
                DB::raw('SUM(order_items.quantity) as units'),
                DB::raw('SUM(order_items.subtotal) as revenue')
            )
            ->groupBy('menu_items.id', 'menu_items.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        // Calculate trends (compare with previous period)
        $previousPeriodItems = $this->getPreviousPeriodItems($filters, $limit);

        return $items->map(function ($item) use ($previousPeriodItems) {
            $previousRevenue = $previousPeriodItems[$item->name] ?? 0;
            $trend = $previousRevenue > 0
                ? round((($item->revenue - $previousRevenue) / $previousRevenue) * 100)
                : ($item->revenue > 0 ? 100 : 0);

            return [
                'id' => $item->id,
                'name' => $item->name,
                'units' => $item->units,
                'rev' => round($item->revenue, 2),
                'trend' => $trend,
            ];
        })->toArray();
    }

    /**
     * Get bottom items analytics.
     */
    public function getBottomItemsAnalytics(array $filters = [], int $limit = 5): array
    {
        $query = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->whereIn('orders.status', ['completed', 'delivered']);

        $this->applyDateFilters($query, $filters, 'orders');
        $this->applyBranchFilter($query, $filters, 'orders');

        return $query
            ->select(
                'menu_items.id',
                'menu_items.name',
                DB::raw('SUM(order_items.quantity) as units'),
                DB::raw('SUM(order_items.subtotal) as revenue')
            )
            ->groupBy('menu_items.id', 'menu_items.name')
            ->orderBy('revenue')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'units' => $item->units,
                    'rev' => round($item->revenue, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get category revenue analytics.
     */
    public function getCategoryRevenueAnalytics(array $filters = []): array
    {
        $query = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->join('menu_categories', 'menu_items.category_id', '=', 'menu_categories.id')
            ->whereIn('orders.status', ['completed', 'delivered']);

        $this->applyDateFilters($query, $filters, 'orders');
        $this->applyBranchFilter($query, $filters, 'orders');

        $categories = $query
            ->select(
                'menu_categories.name as cat',
                DB::raw('SUM(order_items.subtotal) as rev')
            )
            ->groupBy('menu_categories.name')
            ->get();

        $totalRevenue = $categories->sum('rev');

        return $categories->map(function ($category) use ($totalRevenue) {
            return [
                'cat' => $category->cat,
                'rev' => round($category->rev, 2),
                'pct' => $totalRevenue > 0 ? round(($category->rev / $totalRevenue) * 100) : 0,
            ];
        })->sortByDesc('rev')->values()->toArray();
    }

    /**
     * Get branch performance analytics.
     */
    public function getBranchPerformanceAnalytics(array $filters = []): array
    {
        $query = Order::query()->with('branch');

        $this->applyDateFilters($query, $filters);

        $branches = $query
            ->select(
                'branch_id',
                DB::raw("SUM(CASE WHEN status IN ('completed', 'delivered') THEN total_amount ELSE 0 END) as revenue"),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw("AVG(CASE WHEN status IN ('completed', 'delivered') THEN total_amount ELSE NULL END) as avg_value"),
                DB::raw("COUNT(CASE WHEN status IN ('completed', 'delivered') THEN 1 END) as completed_orders"),
                DB::raw("COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders")
            )
            ->groupBy('branch_id')
            ->get();

        return $branches->map(function ($branch) {
            $fulfilmentRate = $branch->total_orders > 0
                ? round(($branch->completed_orders / $branch->total_orders) * 100)
                : 0;

            $cancellationRate = $branch->total_orders > 0
                ? round(($branch->cancelled_orders / $branch->total_orders) * 100)
                : 0;

            return [
                'name' => $branch->branch?->name ?? 'Unknown Branch',
                'rev' => round($branch->revenue ?? 0, 2),
                'orders' => $branch->completed_orders,
                'avg' => round($branch->avg_value ?? 0, 2),
                'fulfilment' => $fulfilmentRate,
                'cancelled' => $cancellationRate,
            ];
        })->sortByDesc('rev')->values()->toArray();
    }

    /**
     * Get delivery vs pickup analytics.
     */
    public function getDeliveryPickupAnalytics(array $filters = []): array
    {
        $query = Order::query()->whereIn('status', ['completed', 'delivered']);

        $this->applyDateFilters($query, $filters);
        $this->applyBranchFilter($query, $filters);

        $orderTypes = $query
            ->select('order_type', DB::raw('COUNT(*) as count'))
            ->groupBy('order_type')
            ->get();

        $totalOrders = $orderTypes->sum('count');

        $delivery = $orderTypes->where('order_type', 'delivery')->first();
        $pickup = $orderTypes->where('order_type', 'pickup')->first();

        return [
            'delivery_pct' => $totalOrders > 0 ? round((($delivery?->count ?? 0) / $totalOrders) * 100) : 0,
            'pickup_pct' => $totalOrders > 0 ? round((($pickup?->count ?? 0) / $totalOrders) * 100) : 0,
        ];
    }

    /**
     * Get payment method analytics.
     */
    public function getPaymentMethodAnalytics(array $filters = []): array
    {
        $query = Payment::query()
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->where('payments.payment_status', 'completed')
            ->whereIn('orders.status', ['completed', 'delivered']);

        $this->applyDateFilters($query, $filters, 'orders');
        $this->applyBranchFilter($query, $filters, 'orders');

        $methods = $query
            ->select('payments.payment_method', DB::raw('COUNT(*) as count'))
            ->groupBy('payments.payment_method')
            ->get();

        $totalPayments = $methods->sum('count');

        return $methods->map(function ($method) use ($totalPayments) {
            $label = match ($method->payment_method) {
                'mobile_money' => 'Mobile Money',
                'cash_on_delivery' => 'Cash on Delivery',
                'cash_at_pickup' => 'Cash at Pickup',
                'card' => 'Card Payment',
                default => ucfirst(str_replace('_', ' ', $method->payment_method))
            };

            return [
                'label' => $label,
                'pct' => $totalPayments > 0 ? round(($method->count / $totalPayments) * 100) : 0,
            ];
        })->sortByDesc('pct')->values()->toArray();
    }

    /**
     * Get previous period items for trend calculation.
     */
    protected function getPreviousPeriodItems(array $filters, int $limit): array
    {
        if (! isset($filters['date_from']) || ! isset($filters['date_to'])) {
            return [];
        }

        $dateFrom = new \DateTime($filters['date_from']);
        $dateTo = new \DateTime($filters['date_to']);
        $daysDiff = $dateFrom->diff($dateTo)->days;

        $previousDateTo = (clone $dateFrom)->modify('-1 day');
        $previousDateFrom = (clone $previousDateTo)->modify("-{$daysDiff} days");

        $previousFilters = array_merge($filters, [
            'date_from' => $previousDateFrom->format('Y-m-d'),
            'date_to' => $previousDateTo->format('Y-m-d'),
        ]);

        $query = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->whereIn('orders.status', ['completed', 'delivered']);

        $this->applyDateFilters($query, $previousFilters, 'orders');
        $this->applyBranchFilter($query, $previousFilters, 'orders');

        return $query
            ->select(
                'menu_items.name',
                DB::raw('SUM(order_items.subtotal) as revenue')
            )
            ->groupBy('menu_items.id', 'menu_items.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->pluck('revenue', 'name')
            ->toArray();
    }

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

        // Daily breakdown - PostgreSQL compatible
        $driver = DB::connection()->getDriverName();
        $dayExpression = $driver === 'pgsql'
            ? 'EXTRACT(DAY FROM created_at)'
            : 'DAY(created_at)';

        $dailyBreakdown = Order::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->whereIn('status', ['completed', 'delivered'])
            ->select(
                DB::raw("{$dayExpression} as day"),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy(DB::raw($dayExpression))
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
    protected function applyDateFilters($query, array $filters, ?string $tablePrefix = null): void
    {
        $createdAtColumn = $tablePrefix ? "{$tablePrefix}.created_at" : 'created_at';

        if (isset($filters['date_from'])) {
            $query->whereDate($createdAtColumn, '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate($createdAtColumn, '<=', $filters['date_to']);
        }
    }

    /**
     * Apply branch filter to query.
     */
    protected function applyBranchFilter($query, array $filters, ?string $tablePrefix = null): void
    {
        if (isset($filters['branch_id'])) {
            $branchIdColumn = $tablePrefix ? "{$tablePrefix}.branch_id" : 'branch_id';
            $query->where($branchIdColumn, $filters['branch_id']);
        }
    }
}
