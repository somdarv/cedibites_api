<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuAddOnRequest;
use App\Http\Requests\UpdateMenuAddOnRequest;
use App\Http\Resources\MenuAddOnResource;
use App\Models\MenuAddOn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuAddOnController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $addOns = MenuAddOn::query()
            ->where('branch_id', $request->integer('branch_id'))
            ->orderBy('display_order')
            ->get();

        return response()->success(MenuAddOnResource::collection($addOns));
    }

    public function store(StoreMenuAddOnRequest $request): JsonResponse
    {
        $addOn = MenuAddOn::create($request->validated());

        return response()->created(new MenuAddOnResource($addOn));
    }

    public function show(MenuAddOn $menuAddOn): JsonResponse
    {
        return response()->success(new MenuAddOnResource($menuAddOn));
    }

    public function update(UpdateMenuAddOnRequest $request, MenuAddOn $menuAddOn): JsonResponse
    {
        $menuAddOn->update($request->validated());

        return response()->success(new MenuAddOnResource($menuAddOn->fresh()));
    }

    public function destroy(MenuAddOn $menuAddOn): JsonResponse
    {
        $menuAddOn->delete();

        return response()->deleted();
    }
}
