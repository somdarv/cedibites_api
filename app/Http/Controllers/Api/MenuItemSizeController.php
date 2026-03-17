<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuItemSizeRequest;
use App\Http\Requests\UpdateMenuItemSizeRequest;
use App\Http\Resources\MenuItemSizeResource;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use Illuminate\Http\JsonResponse;

class MenuItemSizeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(MenuItem $menuItem): JsonResponse
    {
        $sizes = $menuItem->sizes()->orderBy('size_order')->get();

        return response()->success(
            MenuItemSizeResource::collection($sizes),
            'Menu item sizes retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMenuItemSizeRequest $request, MenuItem $menuItem): JsonResponse
    {
        $size = $menuItem->sizes()->create($request->validated());

        return response()->success(
            new MenuItemSizeResource($size),
            'Menu item size created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(MenuItem $menuItem, MenuItemSize $size): JsonResponse
    {
        // Ensure the size belongs to the menu item
        if ($size->menu_item_id !== $menuItem->id) {
            return response()->error('Size not found for this menu item.', 404);
        }

        return response()->success(
            new MenuItemSizeResource($size),
            'Menu item size retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMenuItemSizeRequest $request, MenuItem $menuItem, MenuItemSize $size): JsonResponse
    {
        // Ensure the size belongs to the menu item
        if ($size->menu_item_id !== $menuItem->id) {
            return response()->error('Size not found for this menu item.', 404);
        }

        $size->update($request->validated());

        return response()->success(
            new MenuItemSizeResource($size),
            'Menu item size updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MenuItem $menuItem, MenuItemSize $size): JsonResponse
    {
        // Ensure the size belongs to the menu item
        if ($size->menu_item_id !== $menuItem->id) {
            return response()->error('Size not found for this menu item.', 404);
        }

        try {
            $size->delete();

            return response()->success(null, 'Menu item size deleted successfully.');
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }
}
