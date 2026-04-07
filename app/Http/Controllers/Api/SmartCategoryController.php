<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SmartCategories\SmartCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmartCategoryController extends Controller
{
    /**
     * Get active smart categories for a branch.
     *
     * Returns only categories visible at the current time-of-day,
     * with resolved menu item IDs. Personalized categories (e.g. "Order Again")
     * are included when the request has an authenticated customer.
     */
    public function index(Request $request, SmartCategoryService $service): JsonResponse
    {
        $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $branchId = (int) $request->input('branch_id');
        $customerId = $request->user()?->id;

        $categories = $service->getActiveForContext($branchId, $customerId);

        return response()->success($categories);
    }
}
