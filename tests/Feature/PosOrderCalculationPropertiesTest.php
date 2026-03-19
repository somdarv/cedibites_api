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

test('Property 12: Tax Calculation - verifies tax is back-calculated from tax-inclusive subtotal', function () {
    // **Property 12: Tax Calculation**
    // **Validates: Requirements 5.3, 5.4**
    // Feature: pos-order-creation, Property 12: Tax calculation
    // Prices are tax-inclusive; included tax = subtotal × (0.20 / 1.20)

    // Generate random items
    $itemCount = rand(1, 4);
    $items = [];
    $expectedSubtotal = 0.0;

    for ($i = 0; $i < $itemCount; $i++) {
        $menuItem = MenuItem::factory()->create([
            'branch_id' => $this->branch->id,
            'base_price' => round(rand(500, 8000) / 100, 2),
            'slug' => 'item-'.uniqid().'-'.$i,
        ]);

        $quantity = rand(1, 8);
        $unitPrice = $menuItem->base_price;

        $items[] = [
            'menu_item_id' => $menuItem->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ];

        $expectedSubtotal += $quantity * $unitPrice;
    }

    $expectedTax = round($expectedSubtotal * (0.20 / 1.20), 2);

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
    expect((float) $response->json('data.tax_amount'))->toBe($expectedTax);
    expect((float) $response->json('data.tax_rate'))->toBe(0.20);
})->repeat(100);

test('Property 12: Tax Calculation - with discount applied', function () {
    // **Property 12: Tax Calculation**
    // **Validates: Requirements 5.3, 5.4**
    // Feature: pos-order-creation, Property 12: Tax calculation

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => round(rand(2000, 5000) / 100, 2),
    ]);

    $quantity = rand(2, 5);
    $unitPrice = $menuItem->base_price;
    $subtotal = $quantity * $unitPrice;
    $discount = round(rand(100, 1000) / 100, 2);

    // Tax is back-calculated from the tax-inclusive (subtotal - discount)
    $expectedTax = round(($subtotal - $discount) * (0.20 / 1.20), 2);

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
        'discount' => $discount,
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect((float) $response->json('data.tax_amount'))->toBe($expectedTax);
})->repeat(50);

test('Property 13: Delivery Fee for POS Orders - always 0.00', function () {
    // **Property 13: Delivery Fee for POS Orders**
    // **Validates: Requirements 5.5**
    // Feature: pos-order-creation, Property 13: Delivery fee for POS orders

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => round(rand(1000, 5000) / 100, 2),
    ]);

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => rand(1, 5),
                'unit_price' => $menuItem->base_price,
            ],
        ],
        'payment_method' => ['cash', 'mobile_money', 'card', 'wallet', 'ghqr'][rand(0, 4)],
        'fulfillment_type' => ['dine_in', 'takeaway'][rand(0, 1)],
        'contact_name' => 'John Doe',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect((float) $response->json('data.delivery_fee'))->toBe(0.00);
})->repeat(100);

test('Property 14: Total Amount Calculation - without discount', function () {
    // **Property 14: Total Amount Calculation**
    // **Validates: Requirements 5.6, 5.7**
    // Feature: pos-order-creation, Property 14: Total amount calculation

    $itemCount = rand(1, 3);
    $items = [];
    $expectedSubtotal = 0.0;

    for ($i = 0; $i < $itemCount; $i++) {
        $menuItem = MenuItem::factory()->create([
            'branch_id' => $this->branch->id,
            'base_price' => round(rand(500, 5000) / 100, 2),
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

    $expectedTotal = $expectedSubtotal + 0.00; // delivery_fee is 0; tax is included in prices

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
    expect((float) $response->json('data.total_amount'))->toBe(round($expectedTotal, 2));
})->repeat(100);

test('Property 14: Total Amount Calculation - with discount', function () {
    // **Property 14: Total Amount Calculation**
    // **Validates: Requirements 5.6, 5.7**
    // Feature: pos-order-creation, Property 14: Total amount calculation

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => round(rand(2000, 8000) / 100, 2),
    ]);

    $quantity = rand(2, 6);
    $unitPrice = $menuItem->base_price;
    $subtotal = $quantity * $unitPrice;
    $discount = round(rand(100, 1000) / 100, 2);

    $subtotalAfterDiscount = $subtotal - $discount;
    $expectedTotal = $subtotalAfterDiscount + 0.00; // tax is included in prices, not added

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
        'discount' => $discount,
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect((float) $response->json('data.total_amount'))->toBe(round($expectedTotal, 2));
})->repeat(50);
