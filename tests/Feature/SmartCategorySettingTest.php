<?php

use App\Enums\SmartCategory;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\SmartCategorySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

// Clear migration-seeded rows and set up auth
beforeEach(function () {
    SmartCategorySetting::query()->delete();

    // Seed role + permission for admin auth
    $role = SpatieRole::findOrCreate('admin', 'api');
    $permission = SpatiePermission::findOrCreate('manage_menu', 'api');
    $role->givePermissionTo($permission);
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function adminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

/*
|--------------------------------------------------------------------------
| Model
|--------------------------------------------------------------------------
*/

describe('SmartCategorySetting model', function () {
    it('resolves the SmartCategory enum from slug', function () {
        $setting = SmartCategorySetting::create([
            'slug' => 'most-popular',
            'is_enabled' => true,
            'display_order' => 0,
            'item_limit' => 12,
        ]);

        expect($setting->smartCategory())->toBe(SmartCategory::MostPopular);
    });

    it('detects custom time windows', function () {
        $withWindow = SmartCategorySetting::create([
            'slug' => 'breakfast-favorites',
            'is_enabled' => true,
            'display_order' => 0,
            'item_limit' => 10,
            'visible_hour_start' => 6,
            'visible_hour_end' => 10,
        ]);

        $withoutWindow = SmartCategorySetting::create([
            'slug' => 'most-popular',
            'is_enabled' => true,
            'display_order' => 0,
            'item_limit' => 12,
        ]);

        expect($withWindow->hasCustomTimeWindow())->toBeTrue()
            ->and($withoutWindow->hasCustomTimeWindow())->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| GET /admin/smart-categories — Index
|--------------------------------------------------------------------------
*/

describe('GET /admin/smart-categories', function () {
    it('returns all settings ordered by display_order', function () {
        // Seed settings like the migration would
        foreach (SmartCategory::cases() as $i => $cat) {
            SmartCategorySetting::create([
                'slug' => $cat->value,
                'is_enabled' => true,
                'display_order' => $i,
                'item_limit' => $cat->defaultLimit(),
            ]);
        }

        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/v1/admin/smart-categories')
            ->assertSuccessful()
            ->assertJsonCount(9, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'slug', 'name', 'icon', 'is_enabled',
                        'display_order', 'item_limit', 'is_time_based',
                        'requires_customer', 'visible_hour_start', 'visible_hour_end',
                        'default_visible_hour_start', 'default_visible_hour_end',
                        'default_item_limit',
                    ],
                ],
            ]);
    });

    it('rejects unauthenticated requests', function () {
        $this->getJson('/v1/admin/smart-categories')
            ->assertUnauthorized();
    });
});

/*
|--------------------------------------------------------------------------
| PATCH /admin/smart-categories/{id} — Update
|--------------------------------------------------------------------------
*/

describe('PATCH /admin/smart-categories/{id}', function () {
    it('toggles enabled state', function () {
        $setting = SmartCategorySetting::create([
            'slug' => 'trending',
            'is_enabled' => true,
            'display_order' => 1,
            'item_limit' => 8,
        ]);

        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/v1/admin/smart-categories/{$setting->id}", [
                'is_enabled' => false,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.is_enabled', false);

        expect($setting->fresh()->is_enabled)->toBeFalse();
    });

    it('updates item limit', function () {
        $setting = SmartCategorySetting::create([
            'slug' => 'most-popular',
            'is_enabled' => true,
            'display_order' => 0,
            'item_limit' => 12,
        ]);

        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/v1/admin/smart-categories/{$setting->id}", [
                'item_limit' => 20,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.item_limit', 20);
    });

    it('updates time window', function () {
        $setting = SmartCategorySetting::create([
            'slug' => 'breakfast-favorites',
            'is_enabled' => true,
            'display_order' => 4,
            'item_limit' => 10,
            'visible_hour_start' => 5,
            'visible_hour_end' => 11,
        ]);

        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/v1/admin/smart-categories/{$setting->id}", [
                'visible_hour_start' => 6,
                'visible_hour_end' => 10,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.visible_hour_start', 6)
            ->assertJsonPath('data.visible_hour_end', 10);
    });

    it('validates item limit range', function () {
        $setting = SmartCategorySetting::create([
            'slug' => 'trending',
            'is_enabled' => true,
            'display_order' => 1,
            'item_limit' => 8,
        ]);

        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/v1/admin/smart-categories/{$setting->id}", [
                'item_limit' => 0,
            ])
            ->assertUnprocessable();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/v1/admin/smart-categories/{$setting->id}", [
                'item_limit' => 51,
            ])
            ->assertUnprocessable();
    });
});

/*
|--------------------------------------------------------------------------
| POST /admin/smart-categories/reorder
|--------------------------------------------------------------------------
*/

describe('POST /admin/smart-categories/reorder', function () {
    it('reorders categories', function () {
        $a = SmartCategorySetting::create(['slug' => 'most-popular', 'is_enabled' => true, 'display_order' => 0, 'item_limit' => 12]);
        $b = SmartCategorySetting::create(['slug' => 'trending', 'is_enabled' => true, 'display_order' => 1, 'item_limit' => 8]);
        $c = SmartCategorySetting::create(['slug' => 'top-rated', 'is_enabled' => true, 'display_order' => 2, 'item_limit' => 10]);

        $admin = adminUser();

        // Reverse order: c, b, a
        $this->actingAs($admin, 'sanctum')
            ->postJson('/v1/admin/smart-categories/reorder', [
                'order' => [$c->id, $b->id, $a->id],
            ])
            ->assertSuccessful();

        expect($a->fresh()->display_order)->toBe(2)
            ->and($b->fresh()->display_order)->toBe(1)
            ->and($c->fresh()->display_order)->toBe(0);
    });

    it('validates order array', function () {
        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/v1/admin/smart-categories/reorder', [])
            ->assertUnprocessable();
    });
});

/*
|--------------------------------------------------------------------------
| POST /admin/smart-categories/{id}/reset — Reset to Defaults
|--------------------------------------------------------------------------
*/

describe('POST /admin/smart-categories/{id}/reset', function () {
    it('resets to enum defaults', function () {
        $setting = SmartCategorySetting::create([
            'slug' => 'breakfast-favorites',
            'is_enabled' => true,
            'display_order' => 4,
            'item_limit' => 25,
            'visible_hour_start' => 3,
            'visible_hour_end' => 14,
        ]);

        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/v1/admin/smart-categories/{$setting->id}/reset")
            ->assertSuccessful()
            ->assertJsonPath('data.item_limit', SmartCategory::BreakfastFavorites->defaultLimit())
            ->assertJsonPath('data.visible_hour_start', 5)
            ->assertJsonPath('data.visible_hour_end', 11);
    });
});

/*
|--------------------------------------------------------------------------
| GET /admin/smart-categories/{id}/preview
|--------------------------------------------------------------------------
*/

describe('GET /admin/smart-categories/{id}/preview', function () {
    it('returns preview items for a branch', function () {
        $branch = Branch::factory()->create();
        $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);

        MenuItem::factory()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'rating' => 4.9,
            'rating_count' => 20,
        ]);

        $setting = SmartCategorySetting::create([
            'slug' => 'top-rated',
            'is_enabled' => true,
            'display_order' => 2,
            'item_limit' => 10,
        ]);

        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/v1/admin/smart-categories/{$setting->id}/preview?branch_id={$branch->id}")
            ->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'slug',
                    'branch_id',
                    'item_count',
                    'items' => [
                        '*' => ['id', 'name', 'category', 'rating', 'is_available'],
                    ],
                ],
            ]);
    });

    it('requires branch_id', function () {
        $setting = SmartCategorySetting::create([
            'slug' => 'top-rated',
            'is_enabled' => true,
            'display_order' => 2,
            'item_limit' => 10,
        ]);

        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/v1/admin/smart-categories/{$setting->id}/preview")
            ->assertUnprocessable();
    });
});

/*
|--------------------------------------------------------------------------
| POST /admin/smart-categories/warm-cache
|--------------------------------------------------------------------------
*/

describe('POST /admin/smart-categories/warm-cache', function () {
    it('warms cache for a specific branch', function () {
        $branch = Branch::factory()->create();

        // Need at least one enabled setting
        SmartCategorySetting::create([
            'slug' => 'most-popular',
            'is_enabled' => true,
            'display_order' => 0,
            'item_limit' => 12,
        ]);

        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/v1/admin/smart-categories/warm-cache', [
                'branch_id' => $branch->id,
            ])
            ->assertSuccessful();
    });

    it('warms cache for all active branches', function () {
        Branch::factory()->count(2)->create();

        SmartCategorySetting::create([
            'slug' => 'most-popular',
            'is_enabled' => true,
            'display_order' => 0,
            'item_limit' => 12,
        ]);

        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/v1/admin/smart-categories/warm-cache')
            ->assertSuccessful();
    });
});

/*
|--------------------------------------------------------------------------
| Service respects settings
|--------------------------------------------------------------------------
*/

describe('SmartCategoryService respects settings', function () {
    it('excludes disabled categories from active context', function () {
        $branch = Branch::factory()->create();
        $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);

        MenuItem::factory()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'rating' => 4.9,
            'rating_count' => 20,
            'created_at' => now()->subDays(2),
        ]);

        // Create settings — disable top-rated
        SmartCategorySetting::create(['slug' => 'top-rated', 'is_enabled' => false, 'display_order' => 0, 'item_limit' => 10]);
        SmartCategorySetting::create(['slug' => 'new-arrivals', 'is_enabled' => true, 'display_order' => 1, 'item_limit' => 8]);

        $service = app(\App\Services\SmartCategories\SmartCategoryService::class);
        $results = $service->getActiveForContext($branch->id);

        $slugs = collect($results)->pluck('slug');

        expect($slugs)->not->toContain('top-rated')
            ->and($slugs)->toContain('new-arrivals');
    });
});
