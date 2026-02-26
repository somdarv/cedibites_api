<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Http\Resources\MenuItemCollection;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $menuItems = MenuItem::with(['branch', 'category', 'sizes'])
            ->when($request->branch_id, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($request->category_id, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($request->is_available !== null, fn ($query) => $query->where('is_available', $request->boolean('is_available')))
            ->when($request->is_popular !== null, fn ($query) => $query->where('is_popular', $request->boolean('is_popular')))
            ->paginate($request->per_page ?? 15);

        return response()->paginated(new MenuItemCollection($menuItems));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        try {
            $menuItem = MenuItem::create($request->validated());

            return response()->created(
                new MenuItemResource($menuItem->load(['branch', 'category', 'sizes']))
            );
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(MenuItem $menuItem): JsonResponse
    {
        $menuItem->load(['branch', 'category', 'sizes']);

        return response()->success(new MenuItemResource($menuItem));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): JsonResponse
    {
        try {
            $menuItem->update($request->validated());

            return response()->success(
                new MenuItemResource($menuItem->fresh(['branch', 'category', 'sizes']))
            );
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MenuItem $menuItem): JsonResponse
    {
        try {
            $menuItem->delete();

            return response()->deleted();
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }
}
