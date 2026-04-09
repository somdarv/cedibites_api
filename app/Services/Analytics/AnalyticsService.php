<?php

namespace App\Services\Analytics;

use App\Models\CheckoutSession;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Unified analytics engine — the single source of truth for all metrics.
 *
 * Every controller, dashboard, report, and API endpoint MUST use this service.
 * No inline analytics computation is permitted outside this class.
 *
 * Architecture: AnalyticsQueryBuilder → AnalyticsService → Controllers → Frontend
 */
class AnalyticsService
{
    public function __construct(
        protected AnalyticsQueryBuilder $queryBuilder,
    ) {}

    // ─── A. SALES METRICS ───────────────────────────────────────────

    /**
     * @return array{gross_revenue: float, total_orders: int, completed_orders: int, cancelled_orders: int, cancelled_revenue: float, no_charge_count: int, no_charge_amount: float, average_order_value: float, sales_by_day: \Illuminate\Support\Collection, sales_by_type: \Illuminate\Support\Collection, avg_items_per_order: float}
     */
    public function getSalesMetrics(array $filters = []): array
    {
        $grossRevenue = $this->queryBuilder->computeRevenue($filters);
        $revenueOrderCount = $this->queryBuilder->computeRevenueOrderCount($filters);
        $totalOrders = $this->queryBuilder->computePlacedOrderCount($filters);

        $completedOrders = $this->queryBuilder->completedOrders($filters)->count();

        $cancelledQuery = $this->queryBuilder->cancelledOrders($filters);
        $cancelledOrders = (clone $cancelledQuery)->count();
        $cancelledRevenue = round((float) (clone $cancelledQuery)->sum('total_amount'), 2);

        $noChargeQuery = $this->queryBuilder->noChargeOrders($filters);
        $noChargeCount = (clone $noChargeQuery)->count();
        $noChargeAmount = round((float) (clone $noChargeQuery)->sum('total_amount'), 2);

        $averageOrderValue = $revenueOrderCount > 0
            ? round($grossRevenue / $revenueOrderCount, 2)
            : 0;

        // Sales by day
        $salesByDay = (clone $this->queryBuilder->revenueOrders($filters))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as total'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Sales by order type
        $salesByType = (clone $this->queryBuilder->revenueOrders($filters))
            ->select('order_type', DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as orders'))
            ->groupBy('order_type')
            ->get();

        // Average items per order
        $revenueOrderIds = (clone $this->queryBuilder->revenueOrders($filters))->select('id');
        $totalItems = \App\Models\OrderItem::whereIn('order_id', $revenueOrderIds)->sum('quantity');
        $avgItemsPerOrder = $revenueOrderCount > 0 ? round($totalItems / $revenueOrderCount, 1) : 0;

        return [
            'total_sales' => $grossRevenue,
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'cancelled_orders' => $cancelledOrders,
            'cancelled_revenue' => $cancelledRevenue,
            'no_charge_count' => $noChargeCount,
            'no_charge_amount' => $noChargeAmount,
            'average_order_value' => $averageOrderValue,
            'sales_by_day' => $salesByDay,
            'sales_by_type' => $salesByType,
            'avg_items_per_order' => $avgItemsPerOrder,
        ];
    }

    // ─── B. ORDER METRICS ───────────────────────────────────────────

    /**
     * @return array{orders_by_status: \Illuminate\Support\Collection, orders_by_hour: \Illuminate\Support\Collection, active_orders: int, total_orders: int, average_prep_time: float|null}
     */
    public function getOrderMetrics(array $filters = []): array
    {
        $placedQuery = $this->queryBuilder->placedOrders($filters);

        // Orders by status
        $ordersByStatus = (clone $placedQuery)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        // Orders by hour — PostgreSQL compatible
        $driver = DB::connection()->getDriverName();
        $hourExpression = $driver === 'pgsql'
            ? 'EXTRACT(HOUR FROM created_at)::integer'
            : 'HOUR(created_at)';

        $ordersByHour = (clone $placedQuery)
            ->select(DB::raw("{$hourExpression} as hour"), DB::raw('COUNT(*) as count'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $activeOrders = $this->queryBuilder->activeOrders($filters)->count();
        $totalOrders = $this->queryBuilder->computePlacedOrderCount($filters);

        // Average prep time from OrderStatusHistory
        $averagePrepTime = $this->computeAveragePrepTime($filters);

        return [
            'orders_by_status' => $ordersByStatus,
            'orders_by_hour' => $ordersByHour,
            'active_orders' => $activeOrders,
            'total_orders' => $totalOrders,
            'average_prep_time' => $averagePrepTime,
        ];
    }

    // ─── C. CUSTOMER METRICS ────────────────────────────────────────

    /**
     * @return array{total_customers: int, new_customers_in_period: int, top_customers_by_orders: \Illuminate\Support\Collection, top_customers_by_spending: \Illuminate\Support\Collection}
     */
    public function getCustomerMetrics(array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        $customerQuery = Customer::query();
        if ($dateFrom) {
            $customerQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $customerQuery->whereDate('created_at', '<=', $dateTo);
        }

        $totalCustomers = (clone $customerQuery)->count();

        // New customers = first placed order falls in date range
        $newCustomers = 0;
        if ($dateFrom && $dateTo) {
            $newCustomers = Customer::whereHas(
                'orders',
                fn ($q) => $q->paymentConfirmed()
                    ->whereDate('created_at', '>=', $dateFrom)
                    ->whereDate('created_at', '<=', $dateTo)
            )->whereDoesntHave(
                'orders',
                fn ($q) => $q->paymentConfirmed()
                    ->whereDate('created_at', '<', $dateFrom)
            )->count();
        }

        // Top customers by orders — using revenue-contributing orders
        $revenueSubquery = $this->queryBuilder->revenueOrders($filters)->select('id');

        $topByOrders = Customer::query()
            ->withCount(['orders as placed_order_count' => fn ($q) => $q->whereIn('orders.id', $revenueSubquery)])
            ->addSelect([
                'total_spend' => Order::selectRaw('SUM(total_amount)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereIn('id', $revenueSubquery),
            ])
            ->having('placed_order_count', '>', 0)
            ->orderByDesc('placed_order_count')
            ->limit(10)
            ->get();

        // Top customers by spending
        $topBySpending = Customer::query()
            ->addSelect([
                'total_spend' => Order::selectRaw('SUM(total_amount)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereIn('id', $revenueSubquery),
            ])
            ->having('total_spend', '>', 0)
            ->orderByDesc('total_spend')
            ->limit(10)
            ->get();

        // New customers in last 30 days (always computed, not date-dependent)
        $thirtyDaysAgo = now()->subDays(30)->toDateString();
        $newCustomers30Days = Customer::whereHas(
            'orders',
            fn ($q) => $q->paymentConfirmed()
                ->whereDate('created_at', '>=', $thirtyDaysAgo)
        )->whereDoesntHave(
            'orders',
            fn ($q) => $q->paymentConfirmed()
                ->whereDate('created_at', '<', $thirtyDaysAgo)
        )->count();

        return [
            'total_customers' => $totalCustomers,
            'new_customers_30_days' => $newCustomers30Days,
            'new_customers_in_period' => $newCustomers,
            'top_customers_by_orders' => $topByOrders,
            'top_customers_by_spending' => $topBySpending,
        ];
    }

    // ─── D. ITEM & MENU METRICS ─────────────────────────────────────

    public function getTopItemsMetrics(array $filters = [], int $limit = 10): array
    {
        $query = $this->queryBuilder->orderItems($filters);

        $items = $query
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->leftJoin('menu_item_options', 'order_items.menu_item_option_id', '=', 'menu_item_options.id')
            ->select(
                'menu_items.name',
                DB::raw('COALESCE(menu_item_options.display_name, menu_item_options.option_label) as size_label'),
                DB::raw('SUM(order_items.quantity) as units'),
                DB::raw('SUM(order_items.subtotal) as revenue')
            )
            ->groupBy('menu_items.name', DB::raw('COALESCE(menu_item_options.display_name, menu_item_options.option_label)'))
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        // Compute trend vs previous period
        $previousItems = $this->getPreviousPeriodItems($filters, $limit);

        return $items->map(function ($item) use ($previousItems) {
            $key = $item->name.'|'.($item->size_label ?? '');
            $prevRevenue = $previousItems[$key] ?? 0;
            $trend = $prevRevenue > 0 ? round((($item->revenue - $prevRevenue) / $prevRevenue) * 100) : 0;

            return [
                'name' => $item->name,
                'size_label' => $item->size_label,
                'units' => (int) $item->units,
                'rev' => round($item->revenue, 2),
                'trend' => $trend,
            ];
        })->toArray();
    }

    public function getBottomItemsMetrics(array $filters = [], int $limit = 5): array
    {
        $query = $this->queryBuilder->orderItems($filters);

        return $query
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->leftJoin('menu_item_options', 'order_items.menu_item_option_id', '=', 'menu_item_options.id')
            ->select(
                'menu_items.name',
                DB::raw('COALESCE(menu_item_options.display_name, menu_item_options.option_label) as size_label'),
                DB::raw('SUM(order_items.quantity) as units'),
                DB::raw('SUM(order_items.subtotal) as revenue')
            )
            ->groupBy('menu_items.name', DB::raw('COALESCE(menu_item_options.display_name, menu_item_options.option_label)'))
            ->orderBy('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => [
                'name' => $item->name,
                'size_label' => $item->size_label,
                'units' => (int) $item->units,
                'rev' => round($item->revenue, 2),
            ])
            ->toArray();
    }

    public function getCategoryRevenueMetrics(array $filters = []): array
    {
        $query = $this->queryBuilder->orderItems($filters);

        $categories = $query
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->join('menu_categories', 'menu_items.category_id', '=', 'menu_categories.id')
            ->select(
                'menu_categories.name as cat',
                DB::raw('SUM(order_items.subtotal) as rev')
            )
            ->groupBy('menu_categories.name')
            ->get();

        $totalRevenue = $categories->sum('rev');

        return $categories->map(fn ($c) => [
            'cat' => $c->cat,
            'rev' => round($c->rev, 2),
            'pct' => $totalRevenue > 0 ? round(($c->rev / $totalRevenue) * 100) : 0,
        ])->sortByDesc('rev')->values()->toArray();
    }

    // ─── E. BRANCH PERFORMANCE METRICS ──────────────────────────────

    public function getBranchMetrics(array $filters = []): array
    {
        $query = $this->queryBuilder->placedOrders($filters);

        $branches = (clone $query)
            ->select(
                'branch_id',
                DB::raw("SUM(CASE
                    WHEN status != 'cancelled'
                         AND EXISTS (SELECT 1 FROM payments WHERE payments.order_id = orders.id AND payments.payment_status = 'completed')
                    THEN total_amount ELSE 0 END) as revenue"),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw("AVG(CASE
                    WHEN status != 'cancelled'
                         AND EXISTS (SELECT 1 FROM payments WHERE payments.order_id = orders.id AND payments.payment_status = 'completed')
                    THEN total_amount ELSE NULL END) as avg_value"),
                DB::raw("COUNT(CASE WHEN status IN ('completed', 'delivered') THEN 1 END) as completed_orders"),
                DB::raw("COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders")
            )
            ->groupBy('branch_id')
            ->get();

        // Pre-load branch names to avoid N+1
        $branchNames = \App\Models\Branch::whereIn('id', $branches->pluck('branch_id'))
            ->pluck('name', 'id');

        return $branches->map(function ($b) use ($branchNames) {
            $fulfilmentRate = $b->total_orders > 0
                ? round(($b->completed_orders / $b->total_orders) * 100)
                : 0;

            $cancellationRate = $b->total_orders > 0
                ? round(($b->cancelled_orders / $b->total_orders) * 100)
                : 0;

            return [
                'name' => $branchNames[$b->branch_id] ?? 'Unknown',
                'rev' => round($b->revenue ?? 0, 2),
                'orders' => $b->completed_orders,
                'avg' => round($b->avg_value ?? 0, 2),
                'fulfilment' => $fulfilmentRate,
                'cancelled' => $cancellationRate,
            ];
        })->sortByDesc('rev')->values()->toArray();
    }

    // ─── F. STAFF SALES METRICS ─────────────────────────────────────

    /**
     * Per-staff sales breakdown by payment method for a given date and branch.
     * Uses DB-level conditional aggregation — no PHP iteration.
     */
    public function getStaffSalesMetrics(array $filters = []): array
    {
        // Build base query: placed, not cancelled, assigned to staff, joined with payments
        $baseQuery = $this->queryBuilder->placedOrders($filters)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('assigned_employee_id')
            ->join('payments', function ($join) {
                $join->on('payments.order_id', '=', 'orders.id')
                    ->whereIn('payments.payment_status', ['completed', 'no_charge']);
            })
            ->join('employees', 'orders.assigned_employee_id', '=', 'employees.id')
            ->join('users', 'employees.user_id', '=', 'users.id');

        $rows = $baseQuery
            ->select(
                'orders.assigned_employee_id as employee_id',
                'users.name as staff_name',
                DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                // MoMo
                DB::raw("SUM(CASE WHEN payments.payment_method = 'mobile_money' AND payments.payment_status = 'completed' THEN orders.total_amount ELSE 0 END) as momo_total"),
                DB::raw("COUNT(DISTINCT CASE WHEN payments.payment_method = 'mobile_money' AND payments.payment_status = 'completed' THEN orders.id END) as momo_count"),
                // Cash
                DB::raw("SUM(CASE WHEN payments.payment_method = 'cash' AND payments.payment_status = 'completed' THEN orders.total_amount ELSE 0 END) as cash_total"),
                DB::raw("COUNT(DISTINCT CASE WHEN payments.payment_method = 'cash' AND payments.payment_status = 'completed' THEN orders.id END) as cash_count"),
                // Card
                DB::raw("SUM(CASE WHEN payments.payment_method = 'card' AND payments.payment_status = 'completed' THEN orders.total_amount ELSE 0 END) as card_total"),
                DB::raw("COUNT(DISTINCT CASE WHEN payments.payment_method = 'card' AND payments.payment_status = 'completed' THEN orders.id END) as card_count"),
                // No charge
                DB::raw("SUM(CASE WHEN payments.payment_status = 'no_charge' THEN orders.total_amount ELSE 0 END) as no_charge_total"),
                DB::raw("COUNT(DISTINCT CASE WHEN payments.payment_status = 'no_charge' THEN orders.id END) as no_charge_count"),
            )
            ->groupBy('orders.assigned_employee_id', 'users.name')
            ->orderByDesc(DB::raw("SUM(CASE WHEN payments.payment_status = 'completed' THEN orders.total_amount ELSE 0 END)"))
            ->get();

        return $rows->map(fn ($r) => [
            'employee_id' => $r->employee_id,
            'staff_name' => $r->staff_name,
            'total_orders' => (int) $r->total_orders,
            'momo_total' => round((float) $r->momo_total, 2),
            'momo_count' => (int) $r->momo_count,
            'cash_total' => round((float) $r->cash_total, 2),
            'cash_count' => (int) $r->cash_count,
            'card_total' => round((float) $r->card_total, 2),
            'card_count' => (int) $r->card_count,
            'no_charge_total' => round((float) $r->no_charge_total, 2),
            'no_charge_count' => (int) $r->no_charge_count,
            'total_revenue' => round((float) $r->momo_total + (float) $r->cash_total + (float) $r->card_total, 2),
        ])->toArray();
    }

    // ─── G. DELIVERY & PICKUP METRICS ───────────────────────────────

    public function getDeliveryPickupMetrics(array $filters = []): array
    {
        $query = $this->queryBuilder->revenueOrders($filters);

        // Also include no_charge for order type counts
        $allPlaced = $this->queryBuilder->placedOrders($filters)
            ->where('status', '!=', 'cancelled');

        $orderTypes = (clone $allPlaced)
            ->select('order_type', DB::raw('COUNT(*) as count'))
            ->groupBy('order_type')
            ->get();

        $totalOrders = $orderTypes->sum('count');

        $delivery = $orderTypes->where('order_type', 'delivery')->first();
        $pickup = $orderTypes->where('order_type', 'pickup')->first();

        // Revenue split by type
        $deliveryRevenue = (clone $query)->where('order_type', 'delivery')->sum('total_amount');
        $pickupRevenue = (clone $query)->where('order_type', 'pickup')->sum('total_amount');

        return [
            'delivery_pct' => $totalOrders > 0 ? round((($delivery?->count ?? 0) / $totalOrders) * 100) : 0,
            'pickup_pct' => $totalOrders > 0 ? round((($pickup?->count ?? 0) / $totalOrders) * 100) : 0,
            'delivery_revenue' => round((float) $deliveryRevenue, 2),
            'pickup_revenue' => round((float) $pickupRevenue, 2),
        ];
    }

    // ─── H. PAYMENT METHOD METRICS ──────────────────────────────────

    public function getPaymentMethodMetrics(array $filters = []): array
    {
        $paidQuery = $this->queryBuilder->payments($filters, 'completed');

        $methods = (clone $paidQuery)
            ->select('payments.payment_method', DB::raw('COUNT(*) as count'))
            ->groupBy('payments.payment_method')
            ->get();

        // No-charge count
        $noChargeCount = $this->queryBuilder->noChargeOrders($filters)->count();

        $totalPayments = $methods->sum('count') + $noChargeCount;

        $result = $methods->map(function ($method) use ($totalPayments) {
            $label = match ($method->payment_method) {
                'mobile_money' => 'Mobile Money',
                'cash_on_delivery', 'cash' => 'Cash on Delivery',
                'card' => 'Card Payment',
                default => ucfirst(str_replace('_', ' ', $method->payment_method ?? 'Unknown'))
            };

            return [
                'label' => $label,
                'pct' => $totalPayments > 0 ? round(($method->count / $totalPayments) * 100) : 0,
            ];
        })->sortByDesc('pct')->values()->toArray();

        if ($noChargeCount > 0) {
            $result[] = [
                'label' => 'No Charge',
                'pct' => $totalPayments > 0 ? round(($noChargeCount / $totalPayments) * 100) : 0,
            ];
        }

        return $result;
    }

    // ─── I. SOURCE METRICS ──────────────────────────────────────────

    public function getSourceMetrics(array $filters = []): array
    {
        $query = $this->queryBuilder->placedOrders($filters)
            ->where('status', '!=', 'cancelled');

        $sources = (clone $query)
            ->select(
                'order_source',
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(total_amount) as avg_value'),
                DB::raw('SUM(total_amount) as total_revenue')
            )
            ->groupBy('order_source')
            ->get();

        $totalOrders = $sources->sum('count');

        return $sources->map(fn ($s) => [
            'name' => ucfirst(str_replace('_', ' ', $s->order_source ?? 'Unknown')),
            'count' => (int) $s->count,
            'pct' => $totalOrders > 0 ? round(($s->count / $totalOrders) * 100) : 0,
            'avgValue' => round($s->avg_value ?? 0, 2),
            'total_revenue' => round($s->total_revenue ?? 0, 2),
        ])->sortByDesc('count')->values()->toArray();
    }

    // ─── J. PAYMENT STATS (Transactions page) ──────────────────────

    public function getPaymentStats(array $filters = []): array
    {
        $query = Payment::query();

        if (isset($filters['branch_id'])) {
            $query->whereHas('order', fn ($q) => $q->where('branch_id', $filters['branch_id']));
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('payments.created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('payments.created_at', '<=', $filters['date_to']);
        }

        $rows = (clone $query)
            ->selectRaw('payment_status, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_status')
            ->get()
            ->keyBy('payment_status');

        // For no_charge, sum the order total_amount (payment amount is always 0)
        $noChargeOrderTotal = (clone $query)
            ->where('payments.payment_status', 'no_charge')
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->sum('orders.total_amount');

        $stat = fn (string $status) => [
            'count' => (int) ($rows[$status]->count ?? 0),
            'total' => (float) ($rows[$status]->total ?? 0),
        ];

        return [
            'completed' => $stat('completed'),
            'pending' => $stat('pending'),
            'refunded' => $stat('refunded'),
            'no_charge' => [
                'count' => (int) ($rows['no_charge']->count ?? 0),
                'total' => (float) $noChargeOrderTotal,
            ],
        ];
    }

    // ─── K. FULFILLMENT METRICS ─────────────────────────────────────

    public function getFulfillmentMetrics(array $filters = []): array
    {
        $orderIds = $this->queryBuilder->completedOrders($filters)->pluck('id');

        if ($orderIds->isEmpty()) {
            return [
                'avg_time_to_accept' => null,
                'avg_prep_time' => null,
                'avg_fulfillment_time' => null,
            ];
        }

        return [
            'avg_time_to_accept' => $this->computeTransitionTime($orderIds, 'received', ['accepted']),
            'avg_prep_time' => $this->computeTransitionTime($orderIds, 'preparing', ['ready', 'ready_for_pickup']),
            'avg_fulfillment_time' => $this->computeTransitionTime($orderIds, 'received', ['completed', 'delivered']),
        ];
    }

    // ─── L. PROMO METRICS ───────────────────────────────────────────

    public function getPromoMetrics(array $filters = []): array
    {
        $revenueQuery = $this->queryBuilder->revenueOrders($filters)
            ->whereNotNull('promo_id');

        $promosUsed = (clone $revenueQuery)
            ->select(
                'promo_id',
                'promo_name',
                DB::raw('COUNT(*) as usage_count'),
                DB::raw('SUM(discount) as total_discount'),
                DB::raw('SUM(total_amount) as revenue_generated')
            )
            ->groupBy('promo_id', 'promo_name')
            ->get();

        return $promosUsed->map(fn ($p) => [
            'promo_id' => $p->promo_id,
            'promo_name' => $p->promo_name,
            'usage_count' => (int) $p->usage_count,
            'total_discount' => round($p->total_discount, 2),
            'revenue_generated' => round($p->revenue_generated, 2),
        ])->sortByDesc('usage_count')->values()->toArray();
    }

    // ─── M. CHECKOUT FUNNEL METRICS ─────────────────────────────────

    public function getFunnelMetrics(array $filters = []): array
    {
        $sessionQuery = CheckoutSession::query();

        if (isset($filters['date_from'])) {
            $sessionQuery->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $sessionQuery->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (isset($filters['branch_id'])) {
            $sessionQuery->whereHas('order', fn ($q) => $q->where('branch_id', $filters['branch_id']));
        }

        $totalSessions = (clone $sessionQuery)->count();
        $completedSessions = (clone $sessionQuery)->where('status', 'completed')->count();

        $conversionRate = $totalSessions > 0
            ? round(($completedSessions / $totalSessions) * 100, 1)
            : 0;

        return [
            'sessions_created' => $totalSessions,
            'sessions_completed' => $completedSessions,
            'conversion_rate' => $conversionRate,
        ];
    }

    // ─── N. DASHBOARD KPIs ──────────────────────────────────────────

    /**
     * Consolidated dashboard metrics — replaces inline AdminDashboardController logic.
     */
    public function getDashboardMetrics(array $filters = []): array
    {
        $today = now()->startOfDay()->toDateString();
        $todayFilters = array_merge($filters, ['date_from' => $today, 'date_to' => $today]);

        $revenueToday = $this->queryBuilder->computeRevenue($todayFilters);
        $ordersToday = $this->queryBuilder->computePlacedOrderCount($todayFilters);
        $activeOrders = $this->queryBuilder->activeOrders($filters)->count();

        $cancelledToday = $this->queryBuilder->cancelledOrders($todayFilters)->count();
        $cancelledRevenueToday = round(
            (float) $this->queryBuilder->cancelledOrders($todayFilters)->sum('total_amount'),
            2
        );

        $noChargeQuery = $this->queryBuilder->noChargeOrders($todayFilters);
        $noChargeToday = (clone $noChargeQuery)->count();
        $noChargeTodayAmount = round((float) (clone $noChargeQuery)->sum('total_amount'), 2);

        return [
            'revenue_today' => $revenueToday,
            'orders_today' => $ordersToday,
            'active_orders' => $activeOrders,
            'cancelled_today' => $cancelledToday,
            'cancelled_revenue_today' => $cancelledRevenueToday,
            'no_charge_today' => $noChargeToday,
            'no_charge_today_amount' => $noChargeTodayAmount,
        ];
    }

    /**
     * Per-branch today stats — used by dashboard branch cards.
     */
    public function getBranchTodayStats(int $branchId): array
    {
        $today = now()->startOfDay()->toDateString();
        $filters = ['branch_id' => $branchId, 'date_from' => $today, 'date_to' => $today];

        return [
            'revenue_today' => $this->queryBuilder->computeRevenue($filters),
            'orders_today' => $this->queryBuilder->computePlacedOrderCount($filters),
        ];
    }

    /**
     * Bulk per-branch today stats (2 queries total instead of 2N).
     *
     * @param  int[]  $branchIds
     * @return array<int, array{revenue_today: float, orders_today: int}>
     */
    public function getBranchTodayStatsBulk(array $branchIds): array
    {
        if (empty($branchIds)) {
            return [];
        }

        $today = now()->startOfDay()->toDateString();
        $filters = ['date_from' => $today, 'date_to' => $today];

        $revenueByBranch = $this->queryBuilder->revenueOrders($filters)
            ->whereIn('branch_id', $branchIds)
            ->select('branch_id', DB::raw('SUM(total_amount) as revenue'))
            ->groupBy('branch_id')
            ->pluck('revenue', 'branch_id');

        $ordersByBranch = $this->queryBuilder->placedOrders($filters)
            ->whereIn('branch_id', $branchIds)
            ->select('branch_id', DB::raw('COUNT(*) as orders'))
            ->groupBy('branch_id')
            ->pluck('orders', 'branch_id');

        $result = [];
        foreach ($branchIds as $id) {
            $result[$id] = [
                'revenue_today' => round((float) ($revenueByBranch[$id] ?? 0), 2),
                'orders_today' => (int) ($ordersByBranch[$id] ?? 0),
            ];
        }

        return $result;
    }

    // ─── O. REPORTS ─────────────────────────────────────────────────

    public function getDailyReport(?string $date = null): array
    {
        $date = $date ?? now()->toDateString();
        $filters = ['date_from' => $date, 'date_to' => $date];

        $totalOrders = $this->queryBuilder->computePlacedOrderCount($filters);
        $completedOrders = $this->queryBuilder->completedOrders($filters)->count();
        $cancelledOrders = $this->queryBuilder->cancelledOrders($filters)->count();
        $totalRevenue = $this->queryBuilder->computeRevenue($filters);
        $revenueOrderCount = $this->queryBuilder->computeRevenueOrderCount($filters);
        $avgOrderValue = $revenueOrderCount > 0 ? round($totalRevenue / $revenueOrderCount, 2) : 0;

        // Orders by type
        $ordersByType = $this->queryBuilder->placedOrders($filters)
            ->select('order_type', DB::raw('COUNT(*) as count'))
            ->groupBy('order_type')
            ->pluck('count', 'order_type');

        // Orders by status
        $ordersByStatus = $this->queryBuilder->placedOrders($filters)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'date' => $date,
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'cancelled_orders' => $cancelledOrders,
            'total_revenue' => $totalRevenue,
            'average_order_value' => $avgOrderValue,
            'orders_by_type' => $ordersByType,
            'orders_by_status' => $ordersByStatus,
        ];
    }

    public function getMonthlyReport(?int $year = null, ?int $month = null): array
    {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = \Carbon\Carbon::parse($startDate)->endOfMonth()->toDateString();
        $filters = ['date_from' => $startDate, 'date_to' => $endDate];

        $totalOrders = $this->queryBuilder->computePlacedOrderCount($filters);
        $completedOrders = $this->queryBuilder->completedOrders($filters)->count();
        $cancelledOrders = $this->queryBuilder->cancelledOrders($filters)->count();
        $totalRevenue = $this->queryBuilder->computeRevenue($filters);
        $revenueOrderCount = $this->queryBuilder->computeRevenueOrderCount($filters);
        $avgOrderValue = $revenueOrderCount > 0 ? round($totalRevenue / $revenueOrderCount, 2) : 0;

        // Daily breakdown
        $dailyBreakdown = (clone $this->queryBuilder->revenueOrders($filters))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'year' => $year,
            'month' => $month,
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'cancelled_orders' => $cancelledOrders,
            'total_revenue' => $totalRevenue,
            'average_order_value' => $avgOrderValue,
            'daily_breakdown' => $dailyBreakdown,
        ];
    }

    // ─── P. BRANCH DETAIL STATS (Manager/Partner dashboard) ────────

    /**
     * Branch-level stats used by BranchController::stats().
     * Same canonical queries, just branch-scoped.
     */
    public function getBranchDetailStats(int $branchId): array
    {
        $today = now()->startOfDay()->toDateString();
        $thisMonth = now()->startOfMonth()->toDateString();
        $todayFilters = ['branch_id' => $branchId, 'date_from' => $today, 'date_to' => $today];
        $monthFilters = ['branch_id' => $branchId, 'date_from' => $thisMonth, 'date_to' => now()->toDateString()];
        $allTimeFilters = ['branch_id' => $branchId];

        $branch = \App\Models\Branch::find($branchId);

        return [
            'total_employees' => $branch?->employees()->count() ?? 0,
            'active_employees' => $branch?->employees()->where('status', 'active')->count() ?? 0,
            'total_orders' => $this->queryBuilder->computePlacedOrderCount($allTimeFilters),
            'today_orders' => $this->queryBuilder->computePlacedOrderCount($todayFilters),
            'month_orders' => $this->queryBuilder->computePlacedOrderCount($monthFilters),
            'today_revenue' => $this->queryBuilder->computeRevenue($todayFilters),
            'month_revenue' => $this->queryBuilder->computeRevenue($monthFilters),
            'today_cancelled' => $this->queryBuilder->cancelledOrders($todayFilters)->count(),
            'today_cancelled_revenue' => round(
                (float) $this->queryBuilder->cancelledOrders($todayFilters)->sum('total_amount'),
                2
            ),
        ];
    }

    // ─── Q. TOP ITEMS FOR BRANCH (Manager dashboard) ────────────────

    /**
     * DB-aggregated top items for a branch — replaces in-memory PHP iteration.
     */
    public function getBranchTopItems(int $branchId, string $period = 'today', int $limit = 5): array
    {
        $now = now();
        $dateFrom = match ($period) {
            'week' => $now->startOfWeek()->toDateString(),
            'month' => $now->startOfMonth()->toDateString(),
            default => $now->startOfDay()->toDateString(),
        };

        $filters = ['branch_id' => $branchId, 'date_from' => $dateFrom, 'date_to' => $now->toDateString()];

        return $this->getTopItemsMetrics($filters, $limit);
    }

    // ─── R. REVENUE CHART DATA (Manager dashboard) ──────────────────

    public function getBranchRevenueChart(int $branchId, string $period = 'week'): array
    {
        $now = now();
        if ($period === 'week') {
            $startDate = $now->copy()->startOfWeek(Carbon::SUNDAY);
            $endDate = $now->copy()->endOfWeek(Carbon::SATURDAY);
        } else {
            $startDate = $now->copy()->startOfMonth();
            $endDate = $now->copy()->endOfMonth();
        }

        $filters = [
            'branch_id' => $branchId,
            'date_from' => $startDate->toDateString(),
            'date_to' => $endDate->toDateString(),
        ];

        $dailyRevenue = (clone $this->queryBuilder->revenueOrders($filters))
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Fill missing dates with 0 revenue
        $chartData = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $revenue = $dailyRevenue->get($dateStr)?->revenue ?? 0;

            $chartData[] = [
                'date' => $dateStr,
                'day' => $currentDate->format('D'),
                'revenue' => (float) $revenue,
            ];

            $currentDate->addDay();
        }

        $maxRevenue = collect($chartData)->max('revenue');
        if ($maxRevenue > 0) {
            foreach ($chartData as &$data) {
                $data['percentage'] = round(($data['revenue'] / $maxRevenue) * 100);
            }
        }

        return $chartData;
    }

    // ─── S. EMPLOYEE BRANCH STATS (Staff dashboard) ─────────────────

    /**
     * Order stats for employee's branches — replaces OrderManagementService::getBranchStats().
     */
    public function getEmployeeBranchStats(array $branchIds): array
    {
        if (empty($branchIds)) {
            return [
                'pending_orders' => 0,
                'preparing_orders' => 0,
                'today_orders' => 0,
                'today_revenue' => 0,
                'completed_today' => 0,
            ];
        }

        $today = now()->startOfDay()->toDateString();
        $todayFilters = [
            'date_from' => $today,
            'date_to' => $today,
            'branch_ids' => $branchIds,
        ];

        $branchFilter = ['branch_ids' => $branchIds];

        return [
            'pending_orders' => (clone $this->queryBuilder->activeOrders($branchFilter))
                ->where('status', 'received')
                ->count(),

            'preparing_orders' => (clone $this->queryBuilder->activeOrders($branchFilter))
                ->where('status', '!=', 'received')
                ->count(),

            'today_orders' => $this->queryBuilder->computePlacedOrderCount($todayFilters),

            'today_revenue' => $this->queryBuilder->computeRevenue($todayFilters),

            'completed_today' => $this->queryBuilder->completedOrders($todayFilters)->count(),
        ];
    }

    // ─── PRIVATE HELPERS ────────────────────────────────────────────

    protected function computeAveragePrepTime(array $filters): ?float
    {
        $orderIds = $this->queryBuilder->placedOrders($filters)->pluck('id');

        if ($orderIds->isEmpty()) {
            return null;
        }

        return $this->computeTransitionTime($orderIds, 'preparing', ['ready', 'ready_for_pickup']);
    }

    /**
     * Compute average minutes between two status transitions from OrderStatusHistory.
     */
    protected function computeTransitionTime($orderIds, string $fromStatus, array $toStatuses): ?float
    {
        $fromTimes = OrderStatusHistory::whereIn('order_id', $orderIds)
            ->where('status', $fromStatus)
            ->select('order_id', DB::raw('MIN(changed_at) as started_at'))
            ->groupBy('order_id')
            ->get()
            ->keyBy('order_id');

        $toTimes = OrderStatusHistory::whereIn('order_id', $orderIds)
            ->whereIn('status', $toStatuses)
            ->select('order_id', DB::raw('MIN(changed_at) as ended_at'))
            ->groupBy('order_id')
            ->get()
            ->keyBy('order_id');

        $diffs = [];
        foreach ($fromTimes as $orderId => $from) {
            $to = $toTimes->get($orderId);
            if (! $to) {
                continue;
            }
            $diffMinutes = \Carbon\Carbon::parse($from->started_at)->diffInMinutes(\Carbon\Carbon::parse($to->ended_at));
            // Sanity filter: only 0-180 minutes
            if ($diffMinutes >= 0 && $diffMinutes <= 180) {
                $diffs[] = $diffMinutes;
            }
        }

        return count($diffs) > 0 ? round(array_sum($diffs) / count($diffs), 1) : null;
    }

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

        $query = $this->queryBuilder->orderItems($previousFilters);

        return $query
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->leftJoin('menu_item_options', 'order_items.menu_item_option_id', '=', 'menu_item_options.id')
            ->select(
                'menu_items.name',
                DB::raw('COALESCE(menu_item_options.display_name, menu_item_options.option_label) as size_label'),
                DB::raw('SUM(order_items.subtotal) as revenue')
            )
            ->groupBy('menu_items.name', DB::raw('COALESCE(menu_item_options.display_name, menu_item_options.option_label)'))
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->mapWithKeys(function ($item) {
                $key = $item->name.'|'.($item->size_label ?? '');

                return [$key => $item->revenue];
            })
            ->toArray();
    }
}
