<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\PermissionSeeder::class,
        \Database\Seeders\RoleSeeder::class,
    ]);
});

test('admin can create menu item', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $branch = Branch::factory()->create();
    $category = MenuCategory::factory()->create();

    $menuItemData = [
        'branch_id' => $branch->id,
        'category_id' => $category->id,
        'name' => 'Jollof Rice with Chicken',
        'slug' => 'jollof-rice-chicken',
        'description' => 'Delicious jollof rice served with grilled chicken',
        'base_price' => 25.50,
        'is_available' => true,
        'is_popular' => false,
    ];

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/admin/menu-items', $menuItemData);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'slug',
                'description',
                'base_price',
                'is_available',
                'is_popular',
                'branch',
                'category',
            ],
        ]);

    $this->assertDatabaseHas('menu_items', [
        'name' => 'Jollof Rice with Chicken',
        'slug' => 'jollof-rice-chicken',
        'branch_id' => $branch->id,
        'category_id' => $category->id,
    ]);
});

test('user without manage_menu permission cannot create menu item', function () {
    $user = User::factory()->create();

    $branch = Branch::factory()->create();
    $category = MenuCategory::factory()->create();

    $menuItemData = [
        'branch_id' => $branch->id,
        'category_id' => $category->id,
        'name' => 'Test Item',
        'slug' => 'test-item',
        'base_price' => 10.00,
    ];

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/menu-items', $menuItemData);

    $response->assertForbidden();
});

test('validation fails with missing required fields', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/admin/menu-items', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['branch_id', 'name', 'slug']);
});
