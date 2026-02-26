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
}
