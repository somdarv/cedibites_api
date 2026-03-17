<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResolvePromoRequest;
use App\Http\Requests\StorePromoRequest;
use App\Http\Requests\UpdatePromoRequest;
use App\Http\Resources\PromoResource;
use App\Models\Promo;
use App\Services\PromoResolutionService;
use Illuminate\Http\JsonResponse;

class PromoController extends Controller
{
    public function __construct(
        protected PromoResolutionService $promoResolution
    ) {}

    /**
     * List all promos.
     */
    public function index(): JsonResponse
    {
        $promos = Promo::query()->with(['branches', 'menuItems'])->orderBy('name')->get();

        return response()->success(PromoResource::collection($promos));
    }

    /**
     * Get a single promo.
     */
    public function show(Promo $promo): JsonResponse
    {
        return response()->success(new PromoResource($promo));
    }

    /**
     * Create a promo.
     */
    public function store(StorePromoRequest $request): JsonResponse
    {
        $data = $request->validated();
        $branchIds = $data['branch_ids'] ?? [];
        $itemIds = $data['item_ids'] ?? [];
        $data['is_active'] = $data['is_active'] ?? true;
        unset($data['branch_ids'], $data['item_ids']);

        $promo = Promo::create($data);
        $promo->branches()->sync($branchIds);
        $promo->menuItems()->sync($itemIds);

        return response()->created(new PromoResource($promo->load(['branches', 'menuItems'])));
    }

    /**
     * Update a promo.
     */
    public function update(UpdatePromoRequest $request, Promo $promo): JsonResponse
    {
        $data = $request->validated();
        $validated = $request->validated();
        $branchIds = $data['branch_ids'] ?? null;
        $itemIds = $data['item_ids'] ?? null;
        unset($data['branch_ids'], $data['item_ids']);

        if (array_key_exists('branch_ids', $validated)) {
            $promo->branches()->sync($branchIds ?? []);
        }
        if (array_key_exists('item_ids', $validated)) {
            $promo->menuItems()->sync($itemIds ?? []);
        }

        $promo->update($data);

        return response()->success(new PromoResource($promo->fresh(['branches', 'menuItems'])));
    }

    /**
     * Delete a promo.
     */
    public function destroy(Promo $promo): JsonResponse
    {
        $promo->delete();

        return response()->deleted();
    }

    /**
     * Resolve the best applicable promo.
     */
    public function resolve(ResolvePromoRequest $request): JsonResponse
    {
        $promo = $this->promoResolution->resolve(
            $request->item_ids,
            $request->branch_id,
            (float) ($request->subtotal ?? 0)
        );

        return response()->success($promo ? new PromoResource($promo) : null);
    }
}
