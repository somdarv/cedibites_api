<?php

namespace App\Services\SmartCategories;

use Illuminate\Support\Collection;

/**
 * Contract for all smart category resolvers.
 *
 * Each resolver computes which menu item IDs belong to its smart category
 * for a given branch. The result is cached by SmartCategoryService.
 */
interface SmartCategoryResolver
{
    /**
     * Resolve the menu item IDs that belong to this smart category.
     *
     * @return Collection<int, int> Collection of menu_item IDs
     */
    public function resolve(int $branchId, int $limit, ?int $customerId = null): Collection;
}
