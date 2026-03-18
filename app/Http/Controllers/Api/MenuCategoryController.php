<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateMenuCategoryRequest;
use App\Http\Requests\UpdateMenuCategoryRequest;
use App\Http\Resources\MenuCategoryResource;
use App\Models\MenuCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MenuCategory::withCount('menuItems');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        // For the public endpoint, return unique categories by name
        // This prevents duplicate category names when multiple branches have the same categories
        $categories = $query->orderBy('display_order')
            ->get()
            ->groupBy('name')
            ->map(function ($group) {
                // Return the first category of each name group
                return $group->first();
            })
            ->values();

        return response()->success(
            MenuCategoryResource::collection($categories),
            'Menu categories retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateMenuCategoryRequest $request): JsonResponse
    {
        $category = MenuCategory::create($request->validated());

        return response()->success(
            new MenuCategoryResource($category),
            'Menu category created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(MenuCategory $menuCategory): JsonResponse
    {
        return response()->success(
            new MenuCategoryResource($menuCategory->loadCount('menuItems')),
            'Menu category retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMenuCategoryRequest $request, MenuCategory $menuCategory): JsonResponse
    {
        $menuCategory->update($request->validated());

        return response()->success(
            new MenuCategoryResource($menuCategory),
            'Menu category updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MenuCategory $menuCategory): JsonResponse
    {
        if ($menuCategory->menuItems()->count() > 0) {
            return response()->error('Cannot delete category with menu items.', 422);
        }

        $menuCategory->delete();

        return response()->success(null, 'Menu category deleted successfully.');
    }
}
