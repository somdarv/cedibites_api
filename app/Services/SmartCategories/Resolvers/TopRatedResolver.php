<?php

namespace App\Services\SmartCategories\Resolvers;

use App\Models\MenuItem;
use App\Services\SmartCategories\SmartCategoryResolver;
use Illuminate\Support\Collection;

/**
 * Resolves "Top Rated" — items with rating >= 4.0 AND at least 5 ratings.
 *
 * Uses the pre-aggregated rating/rating_count on the MenuItem model.
 * Ordered by rating descending, then by rating_count descending (tiebreaker).
 */
class TopRatedResolver implements SmartCategoryResolver
{
    private const MIN_RATING = 4.0;

    private const MIN_RATING_COUNT = 5;

    public function resolve(int $branchId, int $limit, ?int $customerId = null): Collection
    {
        return MenuItem::query()
            ->where('branch_id', $branchId)
            ->where('is_available', true)
            ->where('rating', '>=', self::MIN_RATING)
            ->where('rating_count', '>=', self::MIN_RATING_COUNT)
            ->orderByDesc('rating')
            ->orderByDesc('rating_count')
            ->limit($limit)
            ->pluck('id');
    }
}
