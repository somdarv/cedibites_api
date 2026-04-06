<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSmartCategorySettingRequest;
use App\Http\Resources\SmartCategorySettingResource;
use App\Models\SmartCategorySetting;
use App\Services\SmartCategories\SmartCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SmartCategorySettingController extends Controller
{
    /** List all smart category settings ordered by display_order. */
    public function index(): JsonResponse
    {
        $settings = SmartCategorySetting::query()
            ->orderBy('display_order')
            ->get();

        return response()->success(SmartCategorySettingResource::collection($settings));
    }

    /** Update a single smart category setting. */
    public function update(
        UpdateSmartCategorySettingRequest $request,
        SmartCategorySetting $smartCategorySetting,
    ): JsonResponse {
        $smartCategorySetting->update($request->validated());

        return response()->success(new SmartCategorySettingResource($smartCategorySetting->fresh()));
    }

    /**
     * Reorder smart categories.
     * Expects { "order": [3, 1, 5, 2, ...] } — array of setting IDs in desired order.
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'exists:smart_category_settings,id'],
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->input('order') as $position => $id) {
                SmartCategorySetting::where('id', $id)
                    ->update(['display_order' => $position]);
            }
        });

        $settings = SmartCategorySetting::query()
            ->orderBy('display_order')
            ->get();

        return response()->success(SmartCategorySettingResource::collection($settings));
    }

    /**
     * Preview resolved items for a smart category in a specific branch.
     * Returns the item IDs (and count) the resolver would produce right now.
     */
    public function preview(
        Request $request,
        SmartCategorySetting $smartCategorySetting,
        SmartCategoryService $service,
    ): JsonResponse {
        $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $category = $smartCategorySetting->smartCategory();
        $branchId = (int) $request->input('branch_id');

        // Resolve live (bypass cache) for preview
        $resolver = $service->getResolver($category);
        $itemIds = $resolver->resolve($branchId, $smartCategorySetting->item_limit);

        $items = $service->hydrateItems($itemIds, $branchId);

        return response()->success([
            'slug' => $category->value,
            'branch_id' => $branchId,
            'item_count' => $items->count(),
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category?->name,
                'rating' => $item->rating,
                'is_available' => $item->is_available,
            ])->values(),
        ]);
    }

    /** Force warm the cache for a specific branch (or all active branches). */
    public function warmCache(Request $request, SmartCategoryService $service): JsonResponse
    {
        $request->validate([
            'branch_id' => ['sometimes', 'integer', 'exists:branches,id'],
        ]);

        $branchId = $request->input('branch_id');

        if ($branchId) {
            $service->warmCacheForBranch((int) $branchId);

            return response()->success(null, 'Cache warmed for branch.');
        }

        // Warm all active branches
        $branches = \App\Models\Branch::where('is_active', true)->pluck('id');
        foreach ($branches as $id) {
            $service->warmCacheForBranch($id);
        }

        return response()->success(null, "Cache warmed for {$branches->count()} branches.");
    }

    /** Reset a smart category setting to its enum defaults. */
    public function resetToDefault(SmartCategorySetting $smartCategorySetting): JsonResponse
    {
        $category = $smartCategorySetting->smartCategory();
        $hours = $category->visibleHours();

        $smartCategorySetting->update([
            'item_limit' => $category->defaultLimit(),
            'visible_hour_start' => $hours['start'] ?? null,
            'visible_hour_end' => $hours['end'] ?? null,
        ]);

        return response()->success(new SmartCategorySettingResource($smartCategorySetting->fresh()));
    }
}
