<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\OrderResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $branches = Branch::query()
            ->when($request->is_active !== null, fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->area, fn ($query, $area) => $query->where('area', $area))
            ->get(); // Changed from paginate to get for simple array response

        return response()->success(BranchResource::collection($branches));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        try {
            $branch = Branch::create($request->validated());

            return response()->created(new BranchResource($branch));
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Branch $branch): JsonResponse
    {
        $branch->load(['employees', 'menuCategories', 'menuItems']);

        return response()->success(new BranchResource($branch));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        try {
            $branch->update($request->validated());

            return response()->success(new BranchResource($branch->fresh()));
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch): JsonResponse
    {
        try {
            $branch->delete();

            return response()->deleted();
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Get branch employees.
     */
    public function employees(Branch $branch): JsonResponse
    {
        $employees = $branch->employees()
            ->with(['user.roles'])
            ->latest()
            ->get();

        return response()->success(
            EmployeeResource::collection($employees),
            'Branch employees retrieved successfully.'
        );
    }

    /**
     * Get branch orders.
     */
    public function orders(Request $request, Branch $branch): JsonResponse
    {
        $query = $branch->orders()->with(['customer.user', 'orderItems.menuItemSize.menuItem', 'payment']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->latest()->paginate($request->per_page ?? 15);

        return response()->success(
            OrderResource::collection($orders)->response()->getData(true),
            'Branch orders retrieved successfully.'
        );
    }

    /**
     * Get branch statistics.
     */
    public function stats(Branch $branch): JsonResponse
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $stats = [
            'total_employees' => $branch->employees()->count(),
            'active_employees' => $branch->employees()->where('status', 'active')->count(),
            'total_orders' => $branch->orders()->count(),
            'today_orders' => $branch->orders()->whereDate('created_at', $today)->count(),
            'month_orders' => $branch->orders()->whereDate('created_at', '>=', $thisMonth)->count(),
            'today_revenue' => $branch->orders()
                ->whereDate('created_at', $today)
                ->whereIn('status', ['completed', 'delivered'])
                ->sum('total_amount'),
            'month_revenue' => $branch->orders()
                ->whereDate('created_at', '>=', $thisMonth)
                ->whereIn('status', ['completed', 'delivered'])
                ->sum('total_amount'),
        ];

        return response()->success($stats, 'Branch statistics retrieved successfully.');
    }
}
