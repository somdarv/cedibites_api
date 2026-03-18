<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Get sales analytics.
     */
    public function sales(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        $analytics = $this->analyticsService->getSalesAnalytics($filters);

        return response()->success($analytics, 'Sales analytics retrieved successfully.');
    }

    /**
     * Get order analytics.
     */
    public function orders(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        $analytics = $this->analyticsService->getOrderAnalytics($filters);

        return response()->success($analytics, 'Order analytics retrieved successfully.');
    }

    /**
     * Get customer analytics.
     */
    public function customers(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to']);

        $analytics = $this->analyticsService->getCustomerAnalytics($filters);

        return response()->success($analytics, 'Customer analytics retrieved successfully.');
    }

    /**
     * Get order source analytics.
     */
    public function orderSources(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        $analytics = $this->analyticsService->getOrderSourceAnalytics($filters);

        return response()->success($analytics, 'Order source analytics retrieved successfully.');
    }

    /**
     * Get top items analytics.
     */
    public function topItems(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);
        $limit = $request->integer('limit', 10);

        $analytics = $this->analyticsService->getTopItemsAnalytics($filters, $limit);

        return response()->success($analytics, 'Top items analytics retrieved successfully.');
    }

    /**
     * Get bottom items analytics.
     */
    public function bottomItems(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);
        $limit = $request->integer('limit', 5);

        $analytics = $this->analyticsService->getBottomItemsAnalytics($filters, $limit);

        return response()->success($analytics, 'Bottom items analytics retrieved successfully.');
    }

    /**
     * Get category revenue analytics.
     */
    public function categoryRevenue(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        $analytics = $this->analyticsService->getCategoryRevenueAnalytics($filters);

        return response()->success($analytics, 'Category revenue analytics retrieved successfully.');
    }

    /**
     * Get branch performance analytics.
     */
    public function branchPerformance(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to']);

        $analytics = $this->analyticsService->getBranchPerformanceAnalytics($filters);

        return response()->success($analytics, 'Branch performance analytics retrieved successfully.');
    }

    /**
     * Get delivery vs pickup analytics.
     */
    public function deliveryPickup(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        $analytics = $this->analyticsService->getDeliveryPickupAnalytics($filters);

        return response()->success($analytics, 'Delivery vs pickup analytics retrieved successfully.');
    }

    /**
     * Get payment method analytics.
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        $analytics = $this->analyticsService->getPaymentMethodAnalytics($filters);

        return response()->success($analytics, 'Payment method analytics retrieved successfully.');
    }
}
