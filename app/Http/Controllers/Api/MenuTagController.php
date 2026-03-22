<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuTagRequest;
use App\Http\Requests\UpdateMenuTagRequest;
use App\Http\Resources\MenuTagResource;
use App\Models\MenuTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuTagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MenuTag::query()->orderBy('display_order');

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        return response()->success(MenuTagResource::collection($query->get()));
    }

    public function store(StoreMenuTagRequest $request): JsonResponse
    {
        $tag = MenuTag::create($request->validated());

        return response()->created(new MenuTagResource($tag));
    }

    public function show(MenuTag $menuTag): JsonResponse
    {
        return response()->success(new MenuTagResource($menuTag));
    }

    public function update(UpdateMenuTagRequest $request, MenuTag $menuTag): JsonResponse
    {
        $menuTag->update($request->validated());

        return response()->success(new MenuTagResource($menuTag->fresh()));
    }

    public function destroy(MenuTag $menuTag): JsonResponse
    {
        $menuTag->delete();

        return response()->deleted();
    }
}
