<?php

use App\Enums\EmployeeStatus;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->employee = Employee::factory()->create([
        'user_id' => $this->user->id,
        'status' => EmployeeStatus::Active,
    ]);

    $this->branch = Branch::factory()->create();
    $this->employee->branches()->attach($this->branch->id);

    $this->menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 25.00,
    ]);

    $this->menuItemSize = MenuItemSize::factory()->create([
        'menu_item_id' => $this->menuItem->id,
        'name' => 'Large',
        'price' => 35.00,
    ]);

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

test('correct base price passes and order is created', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $this->requestData);

    expect($response->status())->toBe(201);
    expect((float) $response->json('data.items.0.unit_price'))->toBe(25.00);
});

test('frontend price is overridden with DB base price', function () {
    // Backend ignores the frontend price and uses the DB value
    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
                'unit_price' => 120.00, // wrong — DB has 25.00
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect((float) $response->json('data.items.0.unit_price'))->toBe(25.00);
});

test('size price is used when menu_item_size_id is provided', function () {
    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'menu_item_size_id' => $this->menuItemSize->id,
                'quantity' => 1,
                'unit_price' => 35.00,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect((float) $response->json('data.items.0.unit_price'))->toBe(35.00);
});

test('wrong size price is overridden with DB size price', function () {
    // Frontend sends wrong price for a size — backend corrects it to size's DB price
    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'menu_item_size_id' => $this->menuItemSize->id,
                'quantity' => 1,
                'unit_price' => 99.00, // wrong — size DB price is 35.00
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect((float) $response->json('data.items.0.unit_price'))->toBe(35.00);
});

test('subtotal and total reflect DB prices, not frontend prices', function () {
    // Frontend sends inflated price; total must use the DB price
    $requestData = array_merge($this->requestData, [
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 2,
                'unit_price' => 999.00, // wrong
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    // subtotal = 2 × 25.00 = 50.00; total = 50.00 (tax-inclusive, no extra added)
    expect((float) $response->json('data.subtotal'))->toBe(50.00);
    expect((float) $response->json('data.total_amount'))->toBe(50.00);
});

test('multiple items each use their DB prices', function () {
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
                'unit_price' => 20.00, // wrong — DB has 15.50
            ],
        ],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    // subtotal = (2 × 25) + (1 × 15.50) = 65.50
    expect((float) $response->json('data.subtotal'))->toBe(65.50);
});
