<?php

use App\Enums\EmployeeStatus;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\User;

beforeEach(function () {
    // Create authenticated user with active employee
    $this->user = User::factory()->create();
    $this->employee = Employee::factory()->create([
        'user_id' => $this->user->id,
        'status' => EmployeeStatus::Active,
    ]);

    // Create branch and assign employee
    $this->branch = Branch::factory()->create();
    $this->employee->branches()->attach($this->branch->id);

    // Create menu item for the branch
    $this->menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 25.00,
    ]);

    // Create menu item sizes (variants)
    $this->smallSize = MenuItemSize::factory()->create([
        'menu_item_id' => $this->menuItem->id,
        'name' => 'Small',
        'price' => 20.00,
    ]);

    $this->largeSize = MenuItemSize::factory()->create([
        'menu_item_id' => $this->menuItem->id,
        'name' => 'Large',
        'price' => 30.00,
    ]);

    // Base request data
    $this->requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
                'unit_price' => 25.00,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'John Doe',
        'contact_phone' => '0241234567',
    ];
});

test('non-existent menu item size returns 422', function () {
    // **Property 10: Menu Item Variant Validation**
    // **Validates: Requirements 4.3**
    // Feature: pos-order-creation, Property 10: Menu item variant validation

    // Get a non-existent size ID
    $nonExistentSizeId = MenuItemSize::max('id') + 1;

    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'menu_item_size_id' => $nonExistentSizeId,
                'quantity' => 1,
                'unit_price' => 25.00,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(422);
    expect($response->json('message'))->toContain('Invalid menu item size');
});

test('menu item size from different menu item returns 422', function () {
    // **Property 10: Menu Item Variant Validation**
    // **Validates: Requirements 4.3**
    // Feature: pos-order-creation, Property 10: Menu item variant validation

    // Create another menu item with its own size
    $otherMenuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 35.00,
    ]);

    $otherSize = MenuItemSize::factory()->create([
        'menu_item_id' => $otherMenuItem->id,
        'name' => 'Medium',
        'price' => 40.00,
    ]);

    // Try to use size from different menu item
    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'menu_item_size_id' => $otherSize->id,
                'quantity' => 1,
                'unit_price' => 40.00,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(422);
    expect($response->json('message'))->toContain('does not belong to menu item');
});

test('valid menu item size passes validation', function () {
    // **Property 10: Menu Item Variant Validation**
    // **Validates: Requirements 4.3**
    // Feature: pos-order-creation, Property 10: Menu item variant validation

    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'menu_item_size_id' => $this->smallSize->id,
                'quantity' => 1,
                'unit_price' => 20.00,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    // Should not return 422 for valid size
    expect($response->status())->not->toBe(422);
});

test('property test: random non-existent sizes are rejected', function () {
    // **Property 10: Menu Item Variant Validation**
    // **Validates: Requirements 4.3**
    // Feature: pos-order-creation, Property 10: Menu item variant validation

    // Get the maximum size ID
    $maxSizeId = MenuItemSize::max('id') ?? 0;

    // Test with multiple non-existent size IDs
    for ($i = 0; $i < 10; $i++) {
        $nonExistentSizeId = $maxSizeId + rand(1, 1000);

        $requestData = array_merge($this->requestData, [
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    'menu_item_size_id' => $nonExistentSizeId,
                    'quantity' => rand(1, 5),
                    'unit_price' => rand(10, 100) + (rand(0, 99) / 100),
                ],
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/pos/orders', $requestData);

        expect($response->status())->toBe(422);
        expect($response->json('message'))->toContain('Invalid menu item size');
    }
});

test('property test: sizes from wrong menu items are rejected', function () {
    // **Property 10: Menu Item Variant Validation**
    // **Validates: Requirements 4.3**
    // Feature: pos-order-creation, Property 10: Menu item variant validation

    // Create multiple menu items with their own sizes
    for ($i = 0; $i < 10; $i++) {
        $otherMenuItem = MenuItem::factory()->create([
            'branch_id' => $this->branch->id,
            'slug' => 'item-'.uniqid().'-'.$i,
            'base_price' => rand(20, 80) + (rand(0, 99) / 100),
        ]);

        $otherSize = MenuItemSize::factory()->create([
            'menu_item_id' => $otherMenuItem->id,
            'name' => fake()->randomElement(['Small', 'Medium', 'Large']),
            'price' => rand(15, 90) + (rand(0, 99) / 100),
        ]);

        // Try to use size from different menu item
        $requestData = array_merge($this->requestData, [
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    'menu_item_size_id' => $otherSize->id,
                    'quantity' => rand(1, 5),
                    'unit_price' => (float) $otherSize->price,
                ],
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/pos/orders', $requestData);

        expect($response->status())->toBe(422);
        expect($response->json('message'))->toContain('does not belong to menu item');
    }
});

test('property test: multiple valid sizes from same menu item pass validation', function () {
    // **Property 10: Menu Item Variant Validation**
    // **Validates: Requirements 4.3**
    // Feature: pos-order-creation, Property 10: Menu item variant validation

    // Create additional sizes for the menu item
    $sizes = collect([$this->smallSize, $this->largeSize]);
    for ($i = 0; $i < 3; $i++) {
        $sizes->push(MenuItemSize::factory()->create([
            'menu_item_id' => $this->menuItem->id,
            'name' => fake()->unique()->randomElement(['Extra Small', 'Extra Large', 'Jumbo', '250ml', '750ml']),
            'price' => rand(15, 50) + (rand(0, 99) / 100),
        ]));
    }

    // Test with random valid sizes
    for ($i = 0; $i < 10; $i++) {
        $selectedSize = $sizes->random();

        $requestData = array_merge($this->requestData, [
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    'menu_item_size_id' => $selectedSize->id,
                    'quantity' => rand(1, 5),
                    'unit_price' => (float) $selectedSize->price,
                ],
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/pos/orders', $requestData);

        // Should not return 422 for valid sizes
        expect($response->status())->not->toBe(422);
    }
});

test('property test: orders with mixed valid and invalid sizes are rejected', function () {
    // **Property 10: Menu Item Variant Validation**
    // **Validates: Requirements 4.3**
    // Feature: pos-order-creation, Property 10: Menu item variant validation

    $maxSizeId = MenuItemSize::max('id') ?? 0;

    // Test with orders containing both valid and invalid sizes
    for ($i = 0; $i < 10; $i++) {
        $nonExistentSizeId = $maxSizeId + rand(1, 1000);

        $requestData = array_merge($this->requestData, [
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    'menu_item_size_id' => $this->smallSize->id,
                    'quantity' => 1,
                    'unit_price' => (float) $this->smallSize->price,
                ],
                [
                    'menu_item_id' => $this->menuItem->id,
                    'menu_item_size_id' => $nonExistentSizeId,
                    'quantity' => 1,
                    'unit_price' => 25.00,
                ],
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/pos/orders', $requestData);

        expect($response->status())->toBe(422);
        expect($response->json('message'))->toContain('Invalid menu item size');
    }
});

test('property test: orders with sizes from multiple menu items are rejected', function () {
    // **Property 10: Menu Item Variant Validation**
    // **Validates: Requirements 4.3**
    // Feature: pos-order-creation, Property 10: Menu item variant validation

    // Create another menu item with sizes
    $otherMenuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'slug' => 'other-item-'.uniqid(),
        'base_price' => 35.00,
    ]);

    $otherSize = MenuItemSize::factory()->create([
        'menu_item_id' => $otherMenuItem->id,
        'name' => 'Medium',
        'price' => 40.00,
    ]);

    // Test with orders containing items with mismatched sizes
    for ($i = 0; $i < 10; $i++) {
        $requestData = array_merge($this->requestData, [
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    'menu_item_size_id' => $this->smallSize->id,
                    'quantity' => rand(1, 3),
                    'unit_price' => (float) $this->smallSize->price,
                ],
                [
                    'menu_item_id' => $this->menuItem->id,
                    'menu_item_size_id' => $otherSize->id, // Wrong size for this menu item
                    'quantity' => rand(1, 3),
                    'unit_price' => (float) $otherSize->price,
                ],
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/pos/orders', $requestData);

        expect($response->status())->toBe(422);
        expect($response->json('message'))->toContain('does not belong to menu item');
    }
});

test('property test: null size_id for items without variants passes validation', function () {
    // **Property 10: Menu Item Variant Validation**
    // **Validates: Requirements 4.3**
    // Feature: pos-order-creation, Property 10: Menu item variant validation

    // Test with items that don't specify a size (using base price)
    for ($i = 0; $i < 10; $i++) {
        $requestData = array_merge($this->requestData, [
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    // No menu_item_size_id specified
                    'quantity' => rand(1, 5),
                    'unit_price' => (float) $this->menuItem->base_price,
                ],
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/pos/orders', $requestData);

        // Should not return 422 for items without size
        expect($response->status())->not->toBe(422);
    }
});
