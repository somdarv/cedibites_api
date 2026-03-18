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

    // Create menu item size
    $this->menuItemSize = MenuItemSize::factory()->create([
        'menu_item_id' => $this->menuItem->id,
        'name' => 'Large',
        'price' => 35.00,
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

test('exact price match passes validation', function () {
    // Test that exact price match passes validation
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $this->requestData);

    // Should not return 422 for exact price match
    expect($response->status())->not->toBe(422);
});

test('price within tolerance passes validation', function () {
    // Test that price within 0.01 tolerance passes validation
    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
                'unit_price' => 25.005, // Within tolerance
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    // Should not return 422 for price within tolerance
    expect($response->status())->not->toBe(422);
});

test('price beyond tolerance fails with 422', function () {
    // Test that price beyond 0.01 tolerance fails with 422
    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
                'unit_price' => 25.02, // Beyond tolerance
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(422);
    expect($response->json('message'))->toContain('Price mismatch');
    expect($response->json('message'))->toContain($this->menuItem->name);
    expect($response->json('message'))->toContain('expected 25');
    expect($response->json('message'))->toContain('got 25.02');
});

test('variant price calculation uses size price', function () {
    // Test that variant price calculation uses the size's price
    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'menu_item_size_id' => $this->menuItemSize->id,
                'quantity' => 1,
                'unit_price' => 35.00, // Size price
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    // Should not return 422 for correct size price
    expect($response->status())->not->toBe(422);
});

test('non-variant price calculation uses base price', function () {
    // Test that non-variant price calculation uses base_price only
    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
                'unit_price' => 25.00, // Base price
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    // Should not return 422 for correct base price
    expect($response->status())->not->toBe(422);
});

test('incorrect variant price fails validation', function () {
    // Test that incorrect variant price fails validation
    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'menu_item_size_id' => $this->menuItemSize->id,
                'quantity' => 1,
                'unit_price' => 25.00, // Wrong - should be 35.00
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(422);
    expect($response->json('message'))->toContain('Price mismatch');
    expect($response->json('message'))->toContain('expected 35');
    expect($response->json('message'))->toContain('got 25');
});

test('lower price beyond tolerance fails validation', function () {
    // Test that lower price beyond tolerance fails validation
    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
                'unit_price' => 24.98, // Beyond tolerance (lower)
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(422);
    expect($response->json('message'))->toContain('Price mismatch');
});

test('multiple items with correct prices pass validation', function () {
    // Test that multiple items with correct prices pass validation
    $menuItem2 = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 15.50,
        'slug' => 'item-2-'.uniqid(),
    ]);

    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 2,
                'unit_price' => 25.00,
            ],
            [
                'menu_item_id' => $menuItem2->id,
                'quantity' => 1,
                'unit_price' => 15.50,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    // Should not return 422 for all correct prices
    expect($response->status())->not->toBe(422);
});

test('multiple items with one incorrect price fails validation', function () {
    // Test that multiple items with one incorrect price fails validation
    $menuItem2 = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 15.50,
        'slug' => 'item-2-'.uniqid(),
    ]);

    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 2,
                'unit_price' => 25.00, // Correct
            ],
            [
                'menu_item_id' => $menuItem2->id,
                'quantity' => 1,
                'unit_price' => 20.00, // Incorrect
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(422);
    expect($response->json('message'))->toContain('Price mismatch');
    expect($response->json('message'))->toContain($menuItem2->name);
});
