<?php

use App\Enums\EmployeeStatus;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\MenuItem;
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

    // Create menu items for the branch
    $this->menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 25.00,
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

test('non-existent menu item returns 422', function () {
    // **Property 9: Menu Item Existence and Branch Validation**
    // **Validates: Requirements 4.1**
    // Feature: pos-order-creation, Property 9: Menu item existence and branch validation

    // Get a non-existent menu item ID
    $nonExistentMenuItemId = MenuItem::max('id') + 1;

    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $nonExistentMenuItemId,
                'quantity' => 1,
                'unit_price' => 25.00,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(422);
    expect($response->json('message'))->toBe('Invalid menu item');
});

test('menu item from different branch returns 422', function () {
    // **Property 9: Menu Item Existence and Branch Validation**
    // **Validates: Requirements 4.2**
    // Feature: pos-order-creation, Property 9: Menu item existence and branch validation

    // Create another branch with its own menu item
    $otherBranch = Branch::factory()->create();
    $otherMenuItem = MenuItem::factory()->create([
        'branch_id' => $otherBranch->id,
        'base_price' => 30.00,
    ]);

    // Try to order from other branch's menu item
    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $otherMenuItem->id,
                'quantity' => 1,
                'unit_price' => 30.00,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(422);
    expect($response->json('message'))->toContain('is not available at this branch');
});

test('valid menu item from correct branch passes validation', function () {
    // **Property 9: Menu Item Existence and Branch Validation**
    // **Validates: Requirements 4.1, 4.2**
    // Feature: pos-order-creation, Property 9: Menu item existence and branch validation

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $this->requestData);

    // Should not return 422 for valid menu item (will return 501 until full implementation)
    expect($response->status())->not->toBe(422);
});

test('property test: random non-existent menu items are rejected', function () {
    // **Property 9: Menu Item Existence and Branch Validation**
    // **Validates: Requirements 4.1**
    // Feature: pos-order-creation, Property 9: Menu item existence and branch validation

    // Get the maximum menu item ID
    $maxMenuItemId = MenuItem::max('id') ?? 0;

    // Test with multiple non-existent menu item IDs
    for ($i = 0; $i < 10; $i++) {
        $nonExistentMenuItemId = $maxMenuItemId + rand(1, 1000);

        $requestData = array_merge($this->requestData, [
            'items' => [
                [
                    'menu_item_id' => $nonExistentMenuItemId,
                    'quantity' => rand(1, 5),
                    'unit_price' => rand(10, 100) + (rand(0, 99) / 100),
                ],
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/pos/orders', $requestData);

        expect($response->status())->toBe(422);
        expect($response->json('message'))->toBe('Invalid menu item');
    }
});

test('property test: menu items from wrong branch are rejected', function () {
    // **Property 9: Menu Item Existence and Branch Validation**
    // **Validates: Requirements 4.2**
    // Feature: pos-order-creation, Property 9: Menu item existence and branch validation

    // Create multiple branches with menu items
    $otherBranches = Branch::factory()->count(5)->create();

    foreach ($otherBranches as $otherBranch) {
        // Create menu items for each branch
        $otherMenuItem = MenuItem::factory()->create([
            'branch_id' => $otherBranch->id,
            'base_price' => rand(10, 100) + (rand(0, 99) / 100),
        ]);

        // Try to order from other branch's menu item
        $requestData = array_merge($this->requestData, [
            'items' => [
                [
                    'menu_item_id' => $otherMenuItem->id,
                    'quantity' => rand(1, 5),
                    'unit_price' => (float) $otherMenuItem->base_price,
                ],
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/pos/orders', $requestData);

        expect($response->status())->toBe(422);
        expect($response->json('message'))->toContain('is not available at this branch');
    }
});

test('property test: multiple valid menu items from correct branch pass validation', function () {
    // **Property 9: Menu Item Existence and Branch Validation**
    // **Validates: Requirements 4.1, 4.2**
    // Feature: pos-order-creation, Property 9: Menu item existence and branch validation

    // Create multiple menu items for the branch with unique slugs
    $menuItems = collect();
    for ($i = 0; $i < 10; $i++) {
        $menuItems->push(MenuItem::factory()->create([
            'branch_id' => $this->branch->id,
            'slug' => 'item-'.uniqid().'-'.$i,
        ]));
    }

    // Test with random combinations of menu items
    for ($i = 0; $i < 10; $i++) {
        // Select random number of items (1-5)
        $itemCount = rand(1, 5);
        $selectedItems = $menuItems->random(min($itemCount, $menuItems->count()));

        $items = [];
        foreach ($selectedItems as $menuItem) {
            $items[] = [
                'menu_item_id' => $menuItem->id,
                'quantity' => rand(1, 5),
                'unit_price' => (float) $menuItem->base_price,
            ];
        }

        $requestData = array_merge($this->requestData, [
            'items' => $items,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/pos/orders', $requestData);

        // Should not return 422 for valid menu items
        expect($response->status())->not->toBe(422);
    }
});

test('property test: mixed valid and invalid menu items are rejected', function () {
    // **Property 9: Menu Item Existence and Branch Validation**
    // **Validates: Requirements 4.1**
    // Feature: pos-order-creation, Property 9: Menu item existence and branch validation

    $maxMenuItemId = MenuItem::max('id') ?? 0;

    // Test with orders containing both valid and invalid menu items
    for ($i = 0; $i < 10; $i++) {
        $nonExistentMenuItemId = $maxMenuItemId + rand(1, 1000);

        $requestData = array_merge($this->requestData, [
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    'quantity' => 1,
                    'unit_price' => (float) $this->menuItem->base_price,
                ],
                [
                    'menu_item_id' => $nonExistentMenuItemId,
                    'quantity' => 1,
                    'unit_price' => 25.00,
                ],
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/pos/orders', $requestData);

        expect($response->status())->toBe(422);
        expect($response->json('message'))->toBe('Invalid menu item');
    }
});

test('property test: mixed menu items from different branches are rejected', function () {
    // **Property 9: Menu Item Existence and Branch Validation**
    // **Validates: Requirements 4.2**
    // Feature: pos-order-creation, Property 9: Menu item existence and branch validation

    // Create another branch with menu items
    $otherBranch = Branch::factory()->create();
    $otherMenuItem = MenuItem::factory()->create([
        'branch_id' => $otherBranch->id,
        'base_price' => 30.00,
    ]);

    // Test with orders containing items from both branches
    for ($i = 0; $i < 10; $i++) {
        $requestData = array_merge($this->requestData, [
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    'quantity' => rand(1, 3),
                    'unit_price' => (float) $this->menuItem->base_price,
                ],
                [
                    'menu_item_id' => $otherMenuItem->id,
                    'quantity' => rand(1, 3),
                    'unit_price' => (float) $otherMenuItem->base_price,
                ],
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/pos/orders', $requestData);

        expect($response->status())->toBe(422);
        expect($response->json('message'))->toContain('is not available at this branch');
    }
});
