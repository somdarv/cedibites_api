<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService,
    ) {}

    public function sales(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        return response()->success(
            $this->analyticsService->getSalesMetrics($filters),
            'Sales analytics retrieved successfully.'
        );
    }

    public function orders(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        return response()->success(
            $this->analyticsService->getOrderMetrics($filters),
            'Order analytics retrieved successfully.'
        );
    }

    public function customers(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to']);

        return response()->success(
            $this->analyticsService->getCustomerMetrics($filters),
            'Customer analytics retrieved successfully.'
        );
    }

    public function orderSources(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        return response()->success(
            $this->analyticsService->getSourceMetrics($filters),
            'Order source analytics retrieved successfully.'
        );
    }

    public function topItems(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);
        $limit = $request->integer('limit', 10);

        return response()->success(
            $this->analyticsService->getTopItemsMetrics($filters, $limit),
            'Top items analytics retrieved successfully.'
        );
    }

    public function bottomItems(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);
        $limit = $request->integer('limit', 5);

        return response()->success(
            $this->analyticsService->getBottomItemsMetrics($filters, $limit),
            'Bottom items analytics retrieved successfully.'
        );
    }

    public function categoryRevenue(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        return response()->success(
            $this->analyticsService->getCategoryRevenueMetrics($filters),
            'Category revenue analytics retrieved successfully.'
        );
    }

    public function branchPerformance(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        return response()->success(
            $this->analyticsService->getBranchMetrics($filters),
            'Branch performance analytics retrieved successfully.'
        );
    }

    public function deliveryPickup(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        return response()->success(
            $this->analyticsService->getDeliveryPickupMetrics($filters),
            'Delivery vs pickup analytics retrieved successfully.'
        );
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        return response()->success(
            $this->analyticsService->getPaymentMethodMetrics($filters),
            'Payment method analytics retrieved successfully.'
        );
    }

    // ─── NEW ENDPOINTS ──────────────────────────────────────────────

    public function fulfillment(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        return response()->success(
            $this->analyticsService->getFulfillmentMetrics($filters),
            'Fulfillment analytics retrieved successfully.'
        );
    }

    public function promos(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        return response()->success(
            $this->analyticsService->getPromoMetrics($filters),
            'Promo analytics retrieved successfully.'
        );
    }

    public function checkoutFunnel(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);

        return response()->success(
            $this->analyticsService->getFunnelMetrics($filters),
            'Checkout funnel analytics retrieved successfully.'
        );
    }
}
