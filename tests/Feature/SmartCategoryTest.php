<?php

use App\Enums\SmartCategory;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\SmartCategories\Resolvers\NewArrivalsResolver;
use App\Services\SmartCategories\Resolvers\OrderAgainResolver;
use App\Services\SmartCategories\Resolvers\PopularResolver;
use App\Services\SmartCategories\Resolvers\TopRatedResolver;
use App\Services\SmartCategories\Resolvers\TrendingResolver;
use App\Services\SmartCategories\SmartCategoryService;

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a completed, paid order with items for a branch.
 *
 * @param  array<int, array{menu_item_id: int, quantity?: int}>  $items
 */
function createCompletedOrder(
    Branch $branch,
    array $items,
    ?Customer $customer = null,
    ?string $createdAt = null,
): Order {
    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer?->id ?? Customer::factory(),
        'status' => 'completed',
        'created_at' => $createdAt ?? now(),
    ]);

    Payment::factory()->completed()->create([
        'order_id' => $order->id,
        'customer_id' => $order->customer_id,
    ]);

    foreach ($items as $item) {
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $item['menu_item_id'],
            'quantity' => $item['quantity'] ?? 1,
        ]);
    }

    return $order;
}

/*
|--------------------------------------------------------------------------
| SmartCategory Enum
|--------------------------------------------------------------------------
*/

describe('SmartCategory enum', function () {
    it('has labels for every case', function () {
        foreach (SmartCategory::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });

    it('has icons for every case', function () {
        foreach (SmartCategory::cases() as $case) {
            expect($case->icon())->toBeString()->not->toBeEmpty();
        }
    });

    it('correctly identifies time-based categories', function () {
        expect(SmartCategory::BreakfastFavorites->isTimeBased())->toBeTrue();
        expect(SmartCategory::LunchPicks->isTimeBased())->toBeTrue();
        expect(SmartCategory::DinnerFavorites->isTimeBased())->toBeTrue();
        expect(SmartCategory::LateNightBites->isTimeBased())->toBeTrue();
        expect(SmartCategory::MostPopular->isTimeBased())->toBeFalse();
    });

    it('checks visibility at hour for standard time windows', function () {
        // Breakfast: 5–11
        expect(SmartCategory::BreakfastFavorites->isVisibleAtHour(6))->toBeTrue();
        expect(SmartCategory::BreakfastFavorites->isVisibleAtHour(14))->toBeFalse();
    });

    it('checks visibility at hour for overnight windows', function () {
        // Late Night: 21–3
        expect(SmartCategory::LateNightBites->isVisibleAtHour(22))->toBeTrue();
        expect(SmartCategory::LateNightBites->isVisibleAtHour(1))->toBeTrue();
        expect(SmartCategory::LateNightBites->isVisibleAtHour(10))->toBeFalse();
    });

    it('identifies customer-required categories', function () {
        expect(SmartCategory::OrderAgain->requiresCustomer())->toBeTrue();
        expect(SmartCategory::MostPopular->requiresCustomer())->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| PopularResolver
|--------------------------------------------------------------------------
*/

describe('PopularResolver', function () {
    it('returns items ranked by order quantity in last 30 days', function () {
        $branch = Branch::factory()->create();
        $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);

        $itemA = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id]);
        $itemB = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id]);
        $itemC = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id]);

        // itemB: 10 units, itemA: 3 units, itemC: 1 unit
        createCompletedOrder($branch, [
            ['menu_item_id' => $itemB->id, 'quantity' => 10],
            ['menu_item_id' => $itemA->id, 'quantity' => 3],
            ['menu_item_id' => $itemC->id, 'quantity' => 1],
        ]);

        $resolver = new PopularResolver;
        $result = $resolver->resolve($branch->id, 10);

        expect($result)->toHaveCount(3);
        expect($result->first())->toBe($itemB->id);
    });

    it('excludes unavailable items', function () {
        $branch = Branch::factory()->create();
        $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);

        $available = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id]);
        $unavailable = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id, 'is_available' => false]);

        createCompletedOrder($branch, [
            ['menu_item_id' => $available->id, 'quantity' => 5],
            ['menu_item_id' => $unavailable->id, 'quantity' => 10],
        ]);

        $result = (new PopularResolver)->resolve($branch->id, 10);

        expect($result)->toContain($available->id);
        expect($result)->not->toContain($unavailable->id);
    });

    it('excludes orders older than 30 days', function () {
        $branch = Branch::factory()->create();
        $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);

        $recentItem = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id]);
        $oldItem = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id]);

        createCompletedOrder($branch, [['menu_item_id' => $recentItem->id, 'quantity' => 5]]);
        createCompletedOrder($branch, [['menu_item_id' => $oldItem->id, 'quantity' => 10]], createdAt: now()->subDays(35)->toDateTimeString());

        $result = (new PopularResolver)->resolve($branch->id, 10);

        expect($result)->toContain($recentItem->id);
        expect($result)->not->toContain($oldItem->id);
    });

    it('only counts orders from the specified branch', function () {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $catA = MenuCategory::factory()->create(['branch_id' => $branchA->id]);
        $catB = MenuCategory::factory()->create(['branch_id' => $branchB->id]);

        $itemA = MenuItem::factory()->create(['branch_id' => $branchA->id, 'category_id' => $catA->id]);
        $itemB = MenuItem::factory()->create(['branch_id' => $branchB->id, 'category_id' => $catB->id]);

        createCompletedOrder($branchA, [['menu_item_id' => $itemA->id, 'quantity' => 5]]);
        createCompletedOrder($branchB, [['menu_item_id' => $itemB->id, 'quantity' => 5]]);

        $result = (new PopularResolver)->resolve($branchA->id, 10);

        expect($result)->toContain($itemA->id);
        expect($result)->not->toContain($itemB->id);
    });

    it('returns empty collection when no orders exist', function () {
        $branch = Branch::factory()->create();

        $result = (new PopularResolver)->resolve($branch->id, 10);

        expect($result)->toBeEmpty();
    });
});

/*
|--------------------------------------------------------------------------
| TopRatedResolver
|--------------------------------------------------------------------------
*/

describe('TopRatedResolver', function () {
    it('returns items with high ratings and sufficient review count', function () {
        $branch = Branch::factory()->create();
        $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);

        $topRated = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id, 'rating' => 4.5, 'rating_count' => 10]);
        $lowRated = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id, 'rating' => 3.0, 'rating_count' => 20]);
        $fewReviews = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id, 'rating' => 5.0, 'rating_count' => 2]);

        $result = (new TopRatedResolver)->resolve($branch->id, 10);

        expect($result)->toContain($topRated->id);
        expect($result)->not->toContain($lowRated->id);
        expect($result)->not->toContain($fewReviews->id);
    });
});

/*
|--------------------------------------------------------------------------
| NewArrivalsResolver
|--------------------------------------------------------------------------
*/

describe('NewArrivalsResolver', function () {
    it('returns items created in the last 14 days', function () {
        $branch = Branch::factory()->create();
        $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);

        $newItem = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id, 'created_at' => now()->subDays(3)]);
        $oldItem = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id, 'created_at' => now()->subDays(20)]);

        $result = (new NewArrivalsResolver)->resolve($branch->id, 10);

        expect($result)->toContain($newItem->id);
        expect($result)->not->toContain($oldItem->id);
    });
});

/*
|--------------------------------------------------------------------------
| TrendingResolver
|--------------------------------------------------------------------------
*/

describe('TrendingResolver', function () {
    it('identifies items with increasing order velocity', function () {
        $branch = Branch::factory()->create();
        $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);

        $trendingItem = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id]);
        $stableItem = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id]);

        // Previous week: stable has 10, trending has 2
        createCompletedOrder($branch, [
            ['menu_item_id' => $stableItem->id, 'quantity' => 10],
            ['menu_item_id' => $trendingItem->id, 'quantity' => 2],
        ], createdAt: now()->subDays(10)->toDateTimeString());

        // Current week: trending jumps to 15, stable stays at 10
        createCompletedOrder($branch, [
            ['menu_item_id' => $trendingItem->id, 'quantity' => 15],
            ['menu_item_id' => $stableItem->id, 'quantity' => 10],
        ], createdAt: now()->subDays(3)->toDateTimeString());

        $result = (new TrendingResolver)->resolve($branch->id, 10);

        // Trending item has bigger velocity increase
        expect($result->first())->toBe($trendingItem->id);
    });
});

/*
|--------------------------------------------------------------------------
| OrderAgainResolver
|--------------------------------------------------------------------------
*/

describe('OrderAgainResolver', function () {
    it('returns items previously ordered by the customer', function () {
        $branch = Branch::factory()->create();
        $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);
        $customer = Customer::factory()->create();

        $orderedItem = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id]);
        $otherItem = MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id]);

        createCompletedOrder($branch, [['menu_item_id' => $orderedItem->id, 'quantity' => 3]], $customer);

        $result = (new OrderAgainResolver)->resolve($branch->id, 10, $customer->id);

        expect($result)->toContain($orderedItem->id);
        expect($result)->not->toContain($otherItem->id);
    });

    it('returns empty when no customer provided', function () {
        $branch = Branch::factory()->create();

        $result = (new OrderAgainResolver)->resolve($branch->id, 10);

        expect($result)->toBeEmpty();
    });
});

/*
|--------------------------------------------------------------------------
| SmartCategoryService
|--------------------------------------------------------------------------
*/

describe('SmartCategoryService', function () {
    it('returns active smart categories with item IDs', function () {
        $branch = Branch::factory()->create();
        $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);

        // Create items with high ratings to trigger TopRated
        MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id, 'rating' => 4.8, 'rating_count' => 15]);

        // Create a new item for NewArrivals
        MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id, 'created_at' => now()->subDays(2)]);

        $service = new SmartCategoryService;
        $result = $service->getActiveForContext($branch->id);

        // Should have at least TopRated and NewArrivals (no order data for Popular/Trending)
        $slugs = collect($result)->pluck('slug');
        expect($slugs)->toContain('top-rated');
        expect($slugs)->toContain('new-arrivals');
    });

    it('excludes customer-required categories for guests', function () {
        $branch = Branch::factory()->create();

        $service = new SmartCategoryService;
        $result = $service->getActiveForContext($branch->id, customerId: null);

        $slugs = collect($result)->pluck('slug');
        expect($slugs)->not->toContain('order-again');
    });

    it('caches results for non-personalized categories', function () {
        $branch = Branch::factory()->create();
        $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);

        MenuItem::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id, 'rating' => 4.5, 'rating_count' => 10]);

        $service = new SmartCategoryService;

        // First call populates cache
        $first = $service->resolve(SmartCategory::TopRated, $branch->id);
        // Second call should hit cache
        $second = $service->resolve(SmartCategory::TopRated, $branch->id);

        expect($first->toArray())->toBe($second->toArray());
    });

    it('invalidates branch cache', function () {
        $branch = Branch::factory()->create();

        $service = new SmartCategoryService;
        $service->warmCacheForBranch($branch->id);
        $service->invalidateBranch($branch->id);

        // After invalidation, resolving should recompute (this tests it doesn't error)
        $result = $service->resolve(SmartCategory::MostPopular, $branch->id);
        expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });
});

/*
|--------------------------------------------------------------------------
| API Endpoint
|--------------------------------------------------------------------------
*/

describe('GET /smart-categories', function () {
    it('returns smart categories for a branch', function () {
        $branch = Branch::factory()->create();
        $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);

        MenuItem::factory()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'rating' => 4.8,
            'rating_count' => 20,
            'created_at' => now()->subDays(2),
        ]);

        $this->getJson("/v1/smart-categories?branch_id={$branch->id}")
            ->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['slug', 'name', 'icon', 'item_ids'],
                ],
            ]);
    });

    it('requires branch_id', function () {
        $this->getJson('/v1/smart-categories')
            ->assertUnprocessable();
    });
});
