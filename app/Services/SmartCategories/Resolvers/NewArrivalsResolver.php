<?php

namespace App\Services\SmartCategories\Resolvers;

use App\Models\MenuItem;
use App\Services\SmartCategories\SmartCategoryResolver;
use Illuminate\Support\Collection;

/**
 * Resolves "New on the Menu" — items created in the last 14 days.
 *
 * Simple real-time query against created_at. No caching dependency.
 */
class NewArrivalsResolver implements SmartCategoryResolver
{
    private const DAYS_THRESHOLD = 14;

    public function resolve(int $branchId, int $limit, ?int $customerId = null): Collection
    {
        return MenuItem::query()
            ->where('branch_id', $branchId)
            ->where('is_available', true)
            ->where('created_at', '>=', now()->subDays(self::DAYS_THRESHOLD))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('id');
    }
}
