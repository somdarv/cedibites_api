<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Get admin dashboard data: KPIs, branch stats, live orders.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->startOfDay();
        $activeStatuses = ['received', 'confirmed', 'preparing', 'ready', 'ready_for_pickup', 'out_for_delivery'];

        $todayOrders = Order::paymentConfirmed()->whereDate('created_at', $today);
        $completedToday = (clone $todayOrders)->whereIn('status', ['completed', 'delivered']);
        $cancelledToday = (clone $todayOrders)->where('status', 'cancelled');
        $activeNow = Order::paymentConfirmed()->whereIn('status', $activeStatuses);

        $revenueToday = round($completedToday->sum('total_amount'), 2);
        $ordersToday = $todayOrders->count();
        $activeOrders = $activeNow->count();
        $cancelledTodayCount = $cancelledToday->count();
        $cancelledRevenueToday = round((clone $cancelledToday)->sum('total_amount'), 2);

        $branches = Branch::where('is_active', true)->get()->map(function (Branch $branch) use ($today) {
            $branchTodayOrders = $branch->orders()->paymentConfirmed()->whereDate('created_at', $today);
            $branchTodayRevenue = (clone $branchTodayOrders)
                ->whereIn('status', ['completed', 'delivered'])
                ->sum('total_amount');

            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'status' => 'open',
                'revenue_today' => round($branchTodayRevenue, 2),
                'orders_today' => $branchTodayOrders->count(),
            ];
        });

        $liveOrders = Order::with(['customer.user', 'branch'])
            ->paymentConfirmed()
            ->whereIn('status', $activeStatuses)
            ->latest()
            ->limit(10)
            ->get();

        $liveOrdersFormatted = $liveOrders->map(function (Order $order) {
            $createdAt = $order->created_at;
            $mins = $createdAt ? (int) now()->diffInMinutes($createdAt) : 0;
            $timeAgo = $mins < 60 ? "{$mins} min" : (int) ($mins / 60).' hr';

            return [
                'id' => $order->order_number,
                'customer' => $order->contact_name ?? $order->customer?->user?->name ?? '—',
                'branch' => $order->branch?->name ?? '—',
                'source' => ucfirst($order->order_source ?? 'online'),
                'status' => $order->status,
                'time_ago' => $timeAgo,
                'amount' => (float) $order->total_amount,
            ];
        });

        return response()->success([
            'user_name' => $user->name ?? 'Admin',
            'kpis' => [
                'revenue_today' => $revenueToday,
                'orders_today' => $ordersToday,
                'active_orders' => $activeOrders,
                'cancelled_today' => $cancelledTodayCount,
                'cancelled_revenue_today' => $cancelledRevenueToday,
            ],
            'branches' => $branches,
            'live_orders' => $liveOrdersFormatted,
        ], 'Dashboard data retrieved successfully.');
    }
}
