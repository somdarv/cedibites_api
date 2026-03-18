<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\User;

test('can create complete POS order with single item', function () {
    // Create test data
    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'status' => \App\Enums\EmployeeStatus::Active,
    ]);
    $branch = Branch::factory()->create();
    $employee->branches()->attach($branch->id);

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $branch->id,
        'base_price' => 20.00,
    ]);

    // Create order
    $response = $this->actingAs($user)->postJson('/api/v1/pos/orders', [
        'branch_id' => $branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 2,
                'unit_price' => 20.00,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'John Doe',
        'contact_phone' => '0241234567',
    ]);

    $response->assertStatus(201);

    // Debug: Check what's actually in the response
    $data = $response->json('data');

    $response->assertJsonStructure([
        'data' => [
            'id',
            'order_number',
            'branch_id',
            'assigned_employee_id',
            'order_type',
            'order_source',
            'contact_name',
            'contact_phone',
            'subtotal',
            'tax_amount',
            'delivery_fee',
            'total_amount',
            'status',
            'branch',
            'assigned_employee', // Note: Laravel converts camelCase to snake_case in JSON
            'items',
            'payments',
        ],
    ]);

    // Verify order details
    expect($response->json('data.order_source'))->toBe('pos');
    expect($response->json('data.order_type'))->toBe('pickup');
    expect($response->json('data.assigned_employee_id'))->toBe($employee->id);
    expect($response->json('data.status'))->toBe('received');
    expect($response->json('data.subtotal'))->toBe('40.00');
    expect($response->json('data.tax_amount'))->toBe('1.00'); // 40 * 0.025
    expect($response->json('data.delivery_fee'))->toBe('0.00');
    expect($response->json('data.total_amount'))->toBe('41.00');
    expect($response->json('data.order_number'))->toMatch('/^CB\d{6}$/');
    expect($response->json('data.delivery_note'))->toContain('Fulfillment: dine_in');

    // Verify payment
    expect($response->json('data.payments'))->toHaveCount(1);
    expect($response->json('data.payments.0.payment_status'))->toBe('completed');
    expect($response->json('data.payments.0.amount'))->toBe('41.00');
    expect($response->json('data.payments.0.customer_id'))->toBeNull();

    // Verify order items
    expect($response->json('data.items'))->toHaveCount(1);
    expect($response->json('data.items.0.quantity'))->toBe(2);
    expect($response->json('data.items.0.unit_price'))->toBe('20.00');
});

test('can create POS order with multiple items and variants', function () {
    // Create test data
    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'status' => \App\Enums\EmployeeStatus::Active,
    ]);
    $branch = Branch::factory()->create();
    $employee->branches()->attach($branch->id);

    $menuItem1 = MenuItem::factory()->create([
        'branch_id' => $branch->id,
        'base_price' => 15.00,
    ]);

    $menuItem2 = MenuItem::factory()->create([
        'branch_id' => $branch->id,
        'base_price' => 25.00,
    ]);

    $size = MenuItemSize::factory()->create([
        'menu_item_id' => $menuItem2->id,
        'name' => 'Large',
        'price' => 30.00,
    ]);

    // Create order
    $response = $this->actingAs($user)->postJson('/api/v1/pos/orders', [
        'branch_id' => $branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem1->id,
                'quantity' => 1,
                'unit_price' => 15.00,
            ],
            [
                'menu_item_id' => $menuItem2->id,
                'menu_item_size_id' => $size->id,
                'quantity' => 2,
                'unit_price' => 30.00,
            ],
        ],
        'payment_method' => 'mobile_money',
        'fulfillment_type' => 'takeaway',
        'contact_name' => 'Jane Smith',
        'contact_phone' => '0501234567',
        'customer_notes' => 'Extra napkins please',
    ]);

    $response->assertStatus(201);

    // Verify calculations: subtotal = 15 + (2 * 30) = 75
    expect($response->json('data.subtotal'))->toBe('75.00');
    expect($response->json('data.tax_amount'))->toBe('1.88'); // 75 * 0.025 = 1.875, rounded to 1.88
    expect($response->json('data.total_amount'))->toBe('76.88');

    // Verify items
    expect($response->json('data.items'))->toHaveCount(2);

    // Verify delivery note includes fulfillment type and customer notes
    expect($response->json('data.delivery_note'))->toContain('Fulfillment: takeaway');
    expect($response->json('data.delivery_note'))->toContain('Extra napkins please');
});

test('can create POS order with discount', function () {
    // Create test data
    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'status' => \App\Enums\EmployeeStatus::Active,
    ]);
    $branch = Branch::factory()->create();
    $employee->branches()->attach($branch->id);

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $branch->id,
        'base_price' => 50.00,
    ]);

    // Create order with discount
    $response = $this->actingAs($user)->postJson('/api/v1/pos/orders', [
        'branch_id' => $branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 2,
                'unit_price' => 50.00,
            ],
        ],
        'payment_method' => 'card',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'Bob Johnson',
        'contact_phone' => '0241234567',
        'discount' => 10.00,
    ]);

    $response->assertStatus(201);

    // Verify calculations: subtotal = 100, after discount = 90, tax = 90 * 0.025 = 2.25
    expect($response->json('data.subtotal'))->toBe('100.00');
    expect($response->json('data.tax_amount'))->toBe('2.25');
    expect($response->json('data.total_amount'))->toBe('92.25');
});

test('creates activity log for POS order', function () {
    // Create test data
    $user = User::factory()->create(['name' => 'Test Staff']);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'status' => \App\Enums\EmployeeStatus::Active,
    ]);
    $branch = Branch::factory()->create(['name' => 'Test Branch']);
    $employee->branches()->attach($branch->id);

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $branch->id,
        'base_price' => 20.00,
    ]);

    // Create order
    $response = $this->actingAs($user)->postJson('/api/v1/pos/orders', [
        'branch_id' => $branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => 20.00,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'John Doe',
        'contact_phone' => '0241234567',
    ]);

    $response->assertStatus(201);

    $orderId = $response->json('data.id');

    // Verify activity log was created
    $this->assertDatabaseHas('activity_log', [
        'subject_type' => 'App\Models\Order',
        'subject_id' => $orderId,
        'causer_type' => 'App\Models\User',
        'causer_id' => $user->id,
    ]);

    $activity = \Spatie\Activitylog\Models\Activity::where('subject_id', $orderId)
        ->where('subject_type', 'App\Models\Order')
        ->first();

    // Verify activity log was created with properties
    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($user->id);
});

test('verifies all payment methods work', function ($paymentMethod) {
    // Create test data
    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'status' => \App\Enums\EmployeeStatus::Active,
    ]);
    $branch = Branch::factory()->create();
    $employee->branches()->attach($branch->id);

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $branch->id,
        'base_price' => 20.00,
    ]);

    // Create order
    $response = $this->actingAs($user)->postJson('/api/v1/pos/orders', [
        'branch_id' => $branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => 20.00,
            ],
        ],
        'payment_method' => $paymentMethod,
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'John Doe',
        'contact_phone' => '0241234567',
    ]);

    $response->assertStatus(201);
    expect($response->json('data.payments.0.payment_method'))->toBe($paymentMethod);
    expect($response->json('data.payments.0.payment_status'))->toBe('completed');
})->with(['cash', 'mobile_money', 'card', 'wallet', 'ghqr']);

test('verifies order snapshots are created correctly', function () {
    // Create test data
    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'status' => \App\Enums\EmployeeStatus::Active,
    ]);
    $branch = Branch::factory()->create();
    $employee->branches()->attach($branch->id);

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $branch->id,
        'base_price' => 25.00,
        'name' => 'Test Item',
        'description' => 'Test Description',
    ]);

    $size = MenuItemSize::factory()->create([
        'menu_item_id' => $menuItem->id,
        'name' => 'Large',
        'price' => 30.00,
    ]);

    // Create order
    $response = $this->actingAs($user)->postJson('/api/v1/pos/orders', [
        'branch_id' => $branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'menu_item_size_id' => $size->id,
                'quantity' => 1,
                'unit_price' => 30.00,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'John Doe',
        'contact_phone' => '0241234567',
    ]);

    $response->assertStatus(201);

    // Verify snapshots in database
    $orderId = $response->json('data.id');
    $orderItem = \App\Models\OrderItem::where('order_id', $orderId)->first();

    $menuItemSnapshot = json_decode($orderItem->menu_item_snapshot, true);
    expect($menuItemSnapshot)->toHaveKeys(['id', 'name', 'description', 'base_price', 'image_url']);
    expect($menuItemSnapshot['name'])->toBe('Test Item');
    expect($menuItemSnapshot['base_price'])->toBe('25.00');

    $sizeSnapshot = json_decode($orderItem->menu_item_size_snapshot, true);
    expect($sizeSnapshot)->toHaveKeys(['id', 'size_name', 'price']);
    expect($sizeSnapshot['size_name'])->toBe('Large');
    expect($sizeSnapshot['price'])->toBe('30.00');
});

test('verifies null fields for POS orders', function () {
    // Create test data
    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'status' => \App\Enums\EmployeeStatus::Active,
    ]);
    $branch = Branch::factory()->create();
    $employee->branches()->attach($branch->id);

    $menuItem = MenuItem::factory()->create([
        'branch_id' => $branch->id,
        'base_price' => 20.00,
    ]);

    // Create order
    $response = $this->actingAs($user)->postJson('/api/v1/pos/orders', [
        'branch_id' => $branch->id,
        'items' => [
            [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => 20.00,
            ],
        ],
        'payment_method' => 'cash',
        'fulfillment_type' => 'dine_in',
        'contact_name' => 'John Doe',
        'contact_phone' => '0241234567',
    ]);

    $response->assertStatus(201);

    // Verify null fields
    expect($response->json('data.customer_id'))->toBeNull();
    expect($response->json('data.delivery_address'))->toBeNull();
    expect($response->json('data.delivery_latitude'))->toBeNull();
    expect($response->json('data.delivery_longitude'))->toBeNull();
});
