<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Create authenticated user with employee
    $this->user = User::factory()->create();
    $this->employee = Employee::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);

    // Create branch and assign employee
    $this->branch = Branch::factory()->create();
    $this->employee->branches()->attach($this->branch->id);

    // Create menu items for the branch
    $this->menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 25.00,
    ]);

    $this->menuItemSize = MenuItemSize::factory()->create([
        'menu_item_id' => $this->menuItem->id,
        'name' => 'Large',
        'price' => 30.00, // Total price for this size
    ]);

    // Register test route
    Route::post('/test-pos-order', function (\App\Http\Requests\StorePosOrderRequest $request) {
        return response()->json(['validated' => true]);
    })->middleware('auth:sanctum');
});

test('property: price validation with exact match', function () {
    // **Property 26: Price Validation**
    // **Validates: Requirements 15.1, 15.2, 15.3, 15.5**
    // Feature: pos-order-creation, Property 26: Price validation

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/test-pos-order', [
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    'quantity' => 2,
                    'unit_price' => 25.00, // Exact match
                ],
            ],
            'payment_method' => 'cash',
            'fulfillment_type' => 'dine_in',
            'contact_name' => 'John Doe',
            'contact_phone' => '0241234567',
        ]);

    expect($response->status())->toBe(200);
    expect($response->json('validated'))->toBe(true);
});

test('property: price validation within tolerance', function () {
    // **Property 26: Price Validation**
    // **Validates: Requirements 15.1, 15.2, 15.3, 15.5**
    // Feature: pos-order-creation, Property 26: Price validation

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/test-pos-order', [
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    'quantity' => 1,
                    'unit_price' => 25.01, // Within 0.01 tolerance
                ],
            ],
            'payment_method' => 'cash',
            'fulfillment_type' => 'takeaway',
            'contact_name' => 'Jane Smith',
            'contact_phone' => '0501234567',
        ]);

    expect($response->status())->toBe(200);
    expect($response->json('validated'))->toBe(true);
});

test('property: price validation with variant exact match', function () {
    // **Property 26: Price Validation**
    // **Validates: Requirements 15.1, 15.2, 15.3, 15.5**
    // Feature: pos-order-creation, Property 26: Price validation

    $expectedPrice = 30.00; // Size price

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/test-pos-order', [
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    'menu_item_size_id' => $this->menuItemSize->id,
                    'quantity' => 3,
                    'unit_price' => $expectedPrice, // Exact match with variant
                ],
            ],
            'payment_method' => 'mobile_money',
            'fulfillment_type' => 'dine_in',
            'contact_name' => 'Bob Johnson',
            'contact_phone' => '0241234567',
        ]);

    expect($response->status())->toBe(200);
    expect($response->json('validated'))->toBe(true);
});

test('property: validation accepts all payment methods', function () {
    // **Property 26: Price Validation**
    // **Validates: Requirements 12.6**
    // Feature: pos-order-creation, Property 26: Price validation

    $paymentMethods = ['cash', 'mobile_money', 'card', 'wallet', 'ghqr'];

    foreach ($paymentMethods as $method) {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/test-pos-order', [
                'branch_id' => $this->branch->id,
                'items' => [
                    [
                        'menu_item_id' => $this->menuItem->id,
                        'quantity' => 1,
                        'unit_price' => 25.00,
                    ],
                ],
                'payment_method' => $method,
                'fulfillment_type' => 'dine_in',
                'contact_name' => 'Test User',
                'contact_phone' => '0241234567',
            ]);

        expect($response->status())->toBe(200);
        expect($response->json('validated'))->toBe(true);
    }
});

test('property: validation accepts both fulfillment types', function () {
    // **Property 26: Price Validation**
    // **Validates: Requirements 12.7**
    // Feature: pos-order-creation, Property 26: Price validation

    $fulfillmentTypes = ['dine_in', 'takeaway'];

    foreach ($fulfillmentTypes as $type) {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/test-pos-order', [
                'branch_id' => $this->branch->id,
                'items' => [
                    [
                        'menu_item_id' => $this->menuItem->id,
                        'quantity' => 1,
                        'unit_price' => 25.00,
                    ],
                ],
                'payment_method' => 'cash',
                'fulfillment_type' => $type,
                'contact_name' => 'Test User',
                'contact_phone' => '0241234567',
            ]);

        expect($response->status())->toBe(200);
        expect($response->json('validated'))->toBe(true);
    }
});

test('property: validation rejects invalid payment method', function () {
    // **Property 26: Price Validation**
    // **Validates: Requirements 12.6**
    // Feature: pos-order-creation, Property 26: Price validation

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/test-pos-order', [
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    'quantity' => 1,
                    'unit_price' => 25.00,
                ],
            ],
            'payment_method' => 'invalid_method',
            'fulfillment_type' => 'dine_in',
            'contact_name' => 'Test User',
            'contact_phone' => '0241234567',
        ]);

    expect($response->status())->toBe(422);
    expect($response->json('errors.payment_method'))->toBeArray();
});

test('property: validation rejects invalid fulfillment type', function () {
    // **Property 26: Price Validation**
    // **Validates: Requirements 12.7**
    // Feature: pos-order-creation, Property 26: Price validation

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/test-pos-order', [
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    'quantity' => 1,
                    'unit_price' => 25.00,
                ],
            ],
            'payment_method' => 'cash',
            'fulfillment_type' => 'delivery',
            'contact_name' => 'Test User',
            'contact_phone' => '0241234567',
        ]);

    expect($response->status())->toBe(422);
    expect($response->json('errors.fulfillment_type'))->toBeArray();
});

test('property: validation rejects empty items array', function () {
    // **Property 26: Price Validation**
    // **Validates: Requirements 12.2**
    // Feature: pos-order-creation, Property 26: Price validation

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/test-pos-order', [
            'branch_id' => $this->branch->id,
            'items' => [],
            'payment_method' => 'cash',
            'fulfillment_type' => 'dine_in',
            'contact_name' => 'Test User',
            'contact_phone' => '0241234567',
        ]);

    expect($response->status())->toBe(422);
    expect($response->json('errors.items'))->toBeArray();
});

test('property: validation requires all item fields', function () {
    // **Property 26: Price Validation**
    // **Validates: Requirements 12.3, 12.4, 12.5**
    // Feature: pos-order-creation, Property 26: Price validation

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/test-pos-order', [
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    // Missing quantity and unit_price
                ],
            ],
            'payment_method' => 'cash',
            'fulfillment_type' => 'dine_in',
            'contact_name' => 'Test User',
            'contact_phone' => '0241234567',
        ]);

    expect($response->status())->toBe(422);
    expect($response->json('errors'))->toHaveKeys(['items.0.quantity', 'items.0.unit_price']);
});

test('property: validation accepts optional discount', function () {
    // **Property 26: Price Validation**
    // **Validates: Requirements 12.10**
    // Feature: pos-order-creation, Property 26: Price validation

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/test-pos-order', [
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
            'contact_name' => 'Test User',
            'contact_phone' => '0241234567',
            'discount' => 5.00,
        ]);

    expect($response->status())->toBe(200);
    expect($response->json('validated'))->toBe(true);
});

test('property: validation rejects negative discount', function () {
    // **Property 26: Price Validation**
    // **Validates: Requirements 12.10**
    // Feature: pos-order-creation, Property 26: Price validation

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/test-pos-order', [
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
            'contact_name' => 'Test User',
            'contact_phone' => '0241234567',
            'discount' => -5.00,
        ]);

    expect($response->status())->toBe(422);
    expect($response->json('errors.discount'))->toBeArray();
});

test('property: validation requires contact information', function () {
    // **Property 26: Price Validation**
    // **Validates: Requirements 12.8, 12.9**
    // Feature: pos-order-creation, Property 26: Price validation

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/test-pos-order', [
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
            // Missing contact_name and contact_phone
        ]);

    expect($response->status())->toBe(422);
    expect($response->json('errors'))->toHaveKeys(['contact_name', 'contact_phone']);
});
