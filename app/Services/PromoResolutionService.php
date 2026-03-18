<?php

namespace App\Services;

use App\Models\Promo;
use Carbon\Carbon;

class PromoResolutionService
{
    /**
     * Resolve the best applicable promo for given item IDs (menu_item_id), branch, and subtotal.
     */
    public function resolve(array $itemIds, string $branchId, ?float $subtotal = 0): ?Promo
    {
        $today = Carbon::today()->toDateString();
        $itemIds = array_map('intval', $itemIds);
        $branchIdInt = (int) $branchId;

        $query = Promo::query()
            ->with(['branches', 'menuItems'])
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where(function ($q) use ($branchIdInt) {
                $q->where('scope', 'global')
                    ->orWhere(function ($branchQ) use ($branchIdInt) {
                        $branchQ->where('scope', 'branch')
                            ->where(function ($b) use ($branchIdInt) {
                                $b->whereDoesntHave('branches')
                                    ->orWhereHas('branches', fn ($q) => $q->where('branches.id', $branchIdInt));
                            });
                    });
            })
            ->where(function ($q) use ($itemIds) {
                $q->where('applies_to', 'order')
                    ->orWhere(function ($itemsQ) use ($itemIds) {
                        $itemsQ->where('applies_to', 'items')
                            ->where(function ($m) use ($itemIds) {
                                $m->whereDoesntHave('menuItems')
                                    ->orWhereHas('menuItems', fn ($q) => $q->whereIn('menu_items.id', $itemIds));
                            });
                    });
            });

        $applicable = $query->get()->filter(function (Promo $promo) use ($subtotal) {
            if ($promo->min_order_value !== null && $subtotal < (float) $promo->min_order_value) {
                return false;
            }
            if ($promo->max_order_value !== null && $subtotal > (float) $promo->max_order_value) {
                return false;
            }

            return true;
        });

        $best = null;
        $bestDiscount = 0.0;

        foreach ($applicable as $promo) {
            $discount = $this->calculateDiscount($promo, $subtotal);
            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $best = $promo;
            }
        }

        return $best;
    }

    /**
     * Calculate discount amount for a promo and subtotal.
     */
    public function calculateDiscount(Promo $promo, float $subtotal): float
    {
        if ($promo->type === 'percentage') {
            $discount = $subtotal * ((float) $promo->value / 100);
            if ($promo->max_discount !== null) {
                $discount = min($discount, (float) $promo->max_discount);
            }

            return round($discount, 2);
        }

        return min((float) $promo->value, $subtotal);
    }
}
