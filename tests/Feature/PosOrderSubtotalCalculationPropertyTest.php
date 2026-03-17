<?php

use App\Enums\EmployeeStatus;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\MenuItem;
use App\Models\Order;
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
});

test('Property 11: Subtotal Calculation - single item', function () {
    // **Property 11: Subtotal Calculation**
    // **Validates: Requirements 5.1, 5.2**
    // Feature: pos-order-creation, Property 11: Subtotal calculation

    // Create a menu item
    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 25.50,
    ]);

    $quantity = 3;
    $unitPrice = 25.50;
    $expectedSubtotal = $quantity * $unitPrice;

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'John Doe',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect((float) $response->json('data.subtotal'))->toBe($expectedSubtotal);
});

test('Property 11: Subtotal Calculation - multiple items with random quantities and prices', function () {
    // **Property 11: Subtotal Calculation**
    // **Validates: Requirements 5.1, 5.2**
    // Feature: pos-order-creation, Property 11: Subtotal calculation

    // Generate random number of items (2-5)
    $itemCount = rand(2, 5);
    $items = [];
    $expectedSubtotal = 0.0;

    for ($i = 0; $i < $itemCount; $i++) {
        $menuItem = MenuItem::factory()->create([
            'branch_id' => $this->branch->id,
            'base_price' => round(rand(500, 10000) / 100, 2), // Random price between 5.00 and 100.00
            'slug' => 'item-'.uniqid().'-'.$i,
        ]);

        $quantity = rand(1, 10);
        $unitPrice = $menuItem->base_price;

        $items[] = [
            'menu_item_id' => $menuItem->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ];

        $expectedSubtotal += $quantity * $unitPrice;
    }

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => $items,
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'John Doe',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect((float) $response->json('data.subtotal'))->toBe(round($expectedSubtotal, 2));
})->repeat(100);

test('Property 11: Subtotal Calculation - with discount', function () {
    // **Property 11: Subtotal Calculation**
    // **Validates: Requirements 5.1, 5.2**
    // Feature: pos-order-creation, Property 11: Subtotal calculation

    // Generate random items
    $itemCount = rand(1, 3);
    $items = [];
    $expectedSubtotal = 0.0;

    for ($i = 0; $i < $itemCount; $i++) {
        $menuItem = MenuItem::factory()->create([
            'branch_id' => $this->branch->id,
            'base_price' => round(rand(1000, 5000) / 100, 2),
            'slug' => 'item-'.uniqid().'-'.$i,
        ]);

        $quantity = rand(1, 5);
        $unitPrice = $menuItem->base_price;

        $items[] = [
            'menu_item_id' => $menuItem->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ];

        $expectedSubtotal += $quantity * $unitPrice;
    }

    // Discount should not affect subtotal field (only affects tax and total calculation)
    $discount = round(rand(100, 500) / 100, 2);

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => $items,
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'John Doe',
        'contact_phone' => '0241234567',
        'discount' => $discount,
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    // Subtotal should be the sum of item subtotals, not affected by discount
    expect((float) $response->json('data.subtotal'))->toBe(round($expectedSubtotal, 2));
})->repeat(50);

test('Property 11: Subtotal Calculation - large quantities', function () {
    // **Property 11: Subtotal Calculation**
    // **Validates: Requirements 5.1, 5.2**
    // Feature: pos-order-creation, Property 11: Subtotal calculation

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => round(rand(100, 1000) / 100, 2),
    ]);

    $quantity = rand(50, 200); // Large quantity
    $unitPrice = $menuItem->base_price;
    $expectedSubtotal = $quantity * $unitPrice;

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'John Doe',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect((float) $response->json('data.subtotal'))->toBe(round($expectedSubtotal, 2));
})->repeat(30);
