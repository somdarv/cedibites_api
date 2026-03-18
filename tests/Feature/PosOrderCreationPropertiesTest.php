<?php

use App\Enums\EmployeeStatus;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

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

test('Property 3: Order Number Format - matches CB + 6 digits', function () {
    // **Property 3: Order Number Format**
    // **Validates: Requirements 1.4**
    // Feature: pos-order-creation, Property 3: Order number format

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => round(rand(1000, 5000) / 100, 2),
    ]);

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => rand(1, 3),
                'unit_price' => $menuItem->base_price,
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
    $orderNumber = $response->json('data.order_number');
    expect($orderNumber)->toMatch('/^CB\d{6}$/');
})->repeat(100);

test('Property 1: Order Source Assignment - always pos', function () {
    // **Property 1: Order Source Assignment**
    // **Validates: Requirements 1.2**
    // Feature: pos-order-creation, Property 1: Order source assignment

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => round(rand(1000, 5000) / 100, 2),
    ]);

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => $menuItem->base_price,
            ],
        ],
        'payment_method' => ['cash', 'mobile_money', 'card'][rand(0, 2)],
        'fulfillment_type' => ['dine_in', 'takeaway'][rand(0, 1)],
        'contact_name' => 'Test User',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect($response->json('data.order_source'))->toBe('pos');
})->repeat(100);

test('Property 2: Employee Assignment - matches authenticated employee', function () {
    // **Property 2: Employee Assignment**
    // **Validates: Requirements 1.3**
    // Feature: pos-order-creation, Property 2: Employee assignment

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 25.00,
    ]);

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => $menuItem->base_price,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'Test User',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect($response->json('data.assigned_employee_id'))->toBe($this->employee->id);
})->repeat(50);

test('Property 15: Fulfillment Type Mapping - dine_in and takeaway map to pickup', function () {
    // **Property 15: Fulfillment Type Mapping**
    // **Validates: Requirements 6.1, 6.2, 6.3**
    // Feature: pos-order-creation, Property 15: Fulfillment type mapping

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 25.00,
    ]);

    $fulfillmentType = ['dine_in', 'takeaway'][rand(0, 1)];

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => $menuItem->base_price,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => $fulfillmentType,
        'contact_name' => 'Test User',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect($response->json('data.order_type'))->toBe('pickup');
    expect($response->json('data.delivery_note'))->toContain("Fulfillment: {$fulfillmentType}");
})->repeat(100);

test('Property 18: Contact Information Storage - stores contact details and notes', function () {
    // **Property 18: Contact Information Storage**
    // **Validates: Requirements 8.3, 8.4, 8.5**
    // Feature: pos-order-creation, Property 18: Contact information storage

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 25.00,
    ]);

    $contactName = 'Customer '.rand(1000, 9999);
    $contactPhone = '024'.rand(1000000, 9999999);
    $customerNotes = 'Note '.rand(1000, 9999);

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => $menuItem->base_price,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => $contactName,
        'contact_phone' => $contactPhone,
        'customer_notes' => $customerNotes,
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect($response->json('data.contact_name'))->toBe($contactName);
    expect($response->json('data.contact_phone'))->toBe($contactPhone);
    expect($response->json('data.delivery_note'))->toContain($customerNotes);
})->repeat(50);

test('Property 22: Initial Order Status - always received', function () {
    // **Property 22: Initial Order Status**
    // **Validates: Requirements 10.1**
    // Feature: pos-order-creation, Property 22: Initial order status

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 25.00,
    ]);

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => $menuItem->base_price,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'Test User',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect($response->json('data.status'))->toBe('received');
})->repeat(50);

test('Property 23: POS Order Null Fields - customer and delivery fields are null', function () {
    // **Property 23: POS Order Null Fields**
    // **Validates: Requirements 10.2, 10.3, 10.4, 10.5**
    // Feature: pos-order-creation, Property 23: POS order null fields

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 25.00,
    ]);

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => $menuItem->base_price,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'Test User',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect($response->json('data.customer_id'))->toBeNull();
    expect($response->json('data.delivery_address'))->toBeNull();
    expect($response->json('data.delivery_latitude'))->toBeNull();
    expect($response->json('data.delivery_longitude'))->toBeNull();
})->repeat(50);

test('Property 19: Menu Item Snapshot Creation - includes required fields', function () {
    // **Property 19: Menu Item Snapshot Creation**
    // **Validates: Requirements 9.1, 9.5**
    // Feature: pos-order-creation, Property 19: Menu item snapshot creation

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => round(rand(1000, 5000) / 100, 2),
    ]);

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => $menuItem->base_price,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'Test User',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);

    $orderItem = $response->json('data.items.0');
    $snapshot = json_decode($orderItem['menu_item_snapshot'], true);

    expect($snapshot)->toHaveKeys(['id', 'name', 'description', 'base_price', 'image_url']);
    expect($snapshot['id'])->toBe($menuItem->id);
    expect($snapshot['name'])->toBe($menuItem->name);
})->repeat(50);

test('Property 20: Menu Item Size Snapshot Creation - includes required fields for variants', function () {
    // **Property 20: Menu Item Size Snapshot Creation**
    // **Validates: Requirements 9.2, 9.6**
    // Feature: pos-order-creation, Property 20: Menu item size snapshot creation

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 20.00,
    ]);

    $menuItemSize = MenuItemSize::factory()->create([
        'menu_item_id' => $menuItem->id,
        'name' => 'Large',
        'price' => 30.00,
    ]);

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'menu_item_size_id' => $menuItemSize->id,
                'quantity' => 1,
                'unit_price' => $menuItemSize->price,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'Test User',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);

    $orderItem = $response->json('data.items.0');
    $sizeSnapshot = json_decode($orderItem['menu_item_size_snapshot'], true);

    expect($sizeSnapshot)->toHaveKeys(['id', 'size_name', 'price']);
    expect($sizeSnapshot['id'])->toBe($menuItemSize->id);
})->repeat(50);

test('Property 21: Null Variant Handling - null for items without variants', function () {
    // **Property 21: Null Variant Handling**
    // **Validates: Requirements 9.3, 9.4**
    // Feature: pos-order-creation, Property 21: Null variant handling

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 25.00,
    ]);

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => $menuItem->base_price,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'Test User',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);

    $orderItem = $response->json('data.items.0');
    expect($orderItem['menu_item_size_id'])->toBeNull();
    expect($orderItem['menu_item_size_snapshot'])->toBeNull();
})->repeat(50);

test('Property 16: Payment Completion - all payment methods completed immediately', function () {
    // **Property 16: Payment Completion**
    // **Validates: Requirements 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7**
    // Feature: pos-order-creation, Property 16: Payment completion

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 25.00,
    ]);

    $paymentMethod = ['cash', 'mobile_money', 'card', 'wallet', 'ghqr'][rand(0, 4)];

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => $menuItem->base_price,
            ],
        ],
        'payment_method' => $paymentMethod,
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'Test User',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);

    $payment = $response->json('data.payments.0');
    expect($payment['payment_status'])->toBe('completed');
    expect($payment['paid_at'])->not->toBeNull();
    expect((float) $payment['amount'])->toBe((float) $response->json('data.total_amount'));
})->repeat(100);

test('Property 17: POS Payment Customer Null - customer_id is null', function () {
    // **Property 17: POS Payment Customer Null**
    // **Validates: Requirements 7.8**
    // Feature: pos-order-creation, Property 17: POS payment customer null

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 25.00,
    ]);

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => $menuItem->base_price,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'Test User',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);

    $payment = $response->json('data.payments.0');
    expect($payment['customer_id'])->toBeNull();
})->repeat(50);

test('Property 24: Activity Logging - creates activity log with required details', function () {
    // **Property 24: Activity Logging**
    // **Validates: Requirements 11.1, 11.2, 11.3, 11.4**
    // Feature: pos-order-creation, Property 24: Activity logging

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 25.00,
    ]);

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => $menuItem->base_price,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'Test User',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);

    $orderId = $response->json('data.id');
    $orderNumber = $response->json('data.order_number');

    $activity = Activity::where('subject_type', Order::class)
        ->where('subject_id', $orderId)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($this->user->id);
    expect($activity->description)->toContain($orderNumber);
    expect($activity->description)->toContain($this->branch->name);
    expect($activity->description)->toContain($this->user->name);
})->repeat(50);

test('Property 4: Complete Response Structure - includes all relationships', function () {
    // **Property 4: Complete Response Structure**
    // **Validates: Requirements 1.6, 14.2, 14.3, 14.4**
    // Feature: pos-order-creation, Property 4: Complete response structure

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => 25.00,
    ]);

    $requestData = [
        'branch_id' => $this->branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => $menuItem->base_price,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'Test User',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
    expect($response->json('data'))->toHaveKeys([
        'id', 'order_number', 'branch', 'assigned_employee', 'items', 'payments',
    ]);
    expect($response->json('data.branch'))->not->toBeNull();
    expect($response->json('data.assigned_employee'))->not->toBeNull();
    expect($response->json('data.items'))->toBeArray();
    expect($response->json('data.payments'))->toBeArray();
    expect($response->json('data.items.0.menu_item'))->not->toBeNull();
})->repeat(50);

test('Property 25: Success Response Status Code - returns 201', function () {
    // **Property 25: Success Response Status Code**
    // **Validates: Requirements 14.1**
    // Feature: pos-order-creation, Property 25: Success response status code

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
        'contact_name' => 'Test User',
        'contact_phone' => '0241234567',
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(201);
})->repeat(100);
