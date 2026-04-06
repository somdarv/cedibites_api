<?php

namespace App\Services\SmartCategories;

use App\Enums\SmartCategory;
use App\Models\MenuItem;
use App\Services\SmartCategories\Resolvers\NewArrivalsResolver;
use App\Services\SmartCategories\Resolvers\OrderAgainResolver;
use App\Services\SmartCategories\Resolvers\PopularResolver;
use App\Services\SmartCategories\Resolvers\TimeBasedResolver;
use App\Services\SmartCategories\Resolvers\TopRatedResolver;
use App\Services\SmartCategories\Resolvers\TrendingResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Orchestrates smart category resolution, caching, and visibility.
 *
 * Smart categories are code-defined virtual categories whose items are
 * computed from order data, ratings, timestamps, or customer history.
 * Results are cached per branch and refreshed by a scheduled command.
 */
class SmartCategoryService
{
    private const CACHE_TTL_SECONDS = 6 * 60 * 60; // 6 hours

    /**
     * Get all smart categories that should be visible right now for a branch.
     *
     * Filters by time-of-day visibility and customer requirements.
     * Returns each category with its resolved item IDs.
     *
     * @return array<int, array{slug: string, name: string, icon: string, item_ids: int[]}>
     */
    public function getActiveForContext(int $branchId, ?int $customerId = null): array
    {
        $currentHour = (int) now()->format('G');
        $results = [];

        foreach (SmartCategory::cases() as $category) {
            // Skip customer-only categories for guests
            if ($category->requiresCustomer() && $customerId === null) {
                continue;
            }

            // Skip time-based categories outside their visible window
            if (! $category->isVisibleAtHour($currentHour)) {
                continue;
            }

            $itemIds = $this->resolve($category, $branchId, $customerId);

            // Only include categories that have items
            if ($itemIds->isEmpty()) {
                continue;
            }

            $results[] = [
                'slug' => $category->value,
                'name' => $category->label(),
                'icon' => $category->icon(),
                'item_ids' => $itemIds->values()->all(),
            ];
        }

        return $results;
    }

    /**
     * Resolve a single smart category for a branch.
     * Uses cache for non-personalized categories; computes live for personalized ones.
     *
     * @return Collection<int, int>
     */
    public function resolve(SmartCategory $category, int $branchId, ?int $customerId = null): Collection
    {
        // Personalized categories (OrderAgain) are computed live per customer
        if ($category->requiresCustomer()) {
            return $this->getResolver($category)
                ->resolve($branchId, $category->defaultLimit(), $customerId);
        }

        $cacheKey = $this->cacheKey($category, $branchId);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($category, $branchId) {
            return $this->getResolver($category)
                ->resolve($branchId, $category->defaultLimit());
        });
    }

    /**
     * Warm the cache for all non-personalized smart categories for a branch.
     * Called by the scheduled command.
     */
    public function warmCacheForBranch(int $branchId): void
    {
        foreach (SmartCategory::cases() as $category) {
            if ($category->requiresCustomer()) {
                continue;
            }

            $cacheKey = $this->cacheKey($category, $branchId);
            $itemIds = $this->getResolver($category)
                ->resolve($branchId, $category->defaultLimit());

            Cache::put($cacheKey, $itemIds, self::CACHE_TTL_SECONDS);
        }
    }

    /**
     * Invalidate all smart category caches for a branch.
     * Useful after bulk menu changes.
     */
    public function invalidateBranch(int $branchId): void
    {
        foreach (SmartCategory::cases() as $category) {
            if ($category->requiresCustomer()) {
                continue;
            }

            Cache::forget($this->cacheKey($category, $branchId));
        }
    }

    /**
     * Hydrate item IDs into full MenuItem models (with relationships).
     * Used when the API needs to return full item data instead of just IDs.
     *
     * @param  Collection<int, int>  $itemIds
     * @return Collection<int, MenuItem>
     */
    public function hydrateItems(Collection $itemIds, int $branchId): Collection
    {
        if ($itemIds->isEmpty()) {
            return collect();
        }

        return MenuItem::query()
            ->with([
                'category',
                'options' => fn ($q) => $q->orderBy('display_order'),
                'options.media',
                'options.branchPrices' => fn ($q) => $q->where('branch_id', $branchId),
                'tags',
                'addOns',
            ])
            ->whereIn('id', $itemIds)
            ->where('is_available', true)
            ->get()
            ->sortBy(fn (MenuItem $item) => $itemIds->search($item->id));
    }

    private function getResolver(SmartCategory $category): SmartCategoryResolver
    {
        return match ($category) {
            SmartCategory::MostPopular => new PopularResolver,
            SmartCategory::Trending => new TrendingResolver,
            SmartCategory::TopRated => new TopRatedResolver,
            SmartCategory::NewArrivals => new NewArrivalsResolver,
            SmartCategory::BreakfastFavorites => new TimeBasedResolver($category),
            SmartCategory::LunchPicks => new TimeBasedResolver($category),
            SmartCategory::DinnerFavorites => new TimeBasedResolver($category),
            SmartCategory::LateNightBites => new TimeBasedResolver($category),
            SmartCategory::OrderAgain => new OrderAgainResolver,
        };
    }

    private function cacheKey(SmartCategory $category, int $branchId): string
    {
        return "smart_category:{$category->value}:branch:{$branchId}";
    }
}
