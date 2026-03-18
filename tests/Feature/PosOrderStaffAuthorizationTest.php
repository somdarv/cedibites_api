<?php

use App\Enums\EmployeeStatus;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\MenuItem;
use App\Models\User;

beforeEach(function () {
    // Create branch
    $this->branch = Branch::factory()->create();

    // Create menu item for the branch
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

test('unauthenticated request returns 401', function () {
    // **Validates: Requirements 2.1, 2.2**

    $response = $this->postJson('/api/v1/pos/orders', $this->requestData);

    expect($response->status())->toBe(401);
});

test('authenticated user without employee record returns 403', function () {
    // **Validates: Requirements 2.3**

    $user = User::factory()->create();
    // No employee record created

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $this->requestData);

    expect($response->status())->toBe(403);
    expect($response->json('message'))->toBe('Employee record not found');
});

test('inactive employee cannot create orders', function () {
    // **Property 6: Active Staff Authorization**
    // **Validates: Requirements 2.3, 2.4**
    // Feature: pos-order-creation, Property 6: Active staff authorization

    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'status' => EmployeeStatus::Suspended,
    ]);
    $employee->branches()->attach($this->branch->id);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $this->requestData);

    expect($response->status())->toBe(403);
    expect($response->json('message'))->toBe('Your account is not active');
});

test('employee on leave cannot create orders', function () {
    // **Property 6: Active Staff Authorization**
    // **Validates: Requirements 2.3, 2.4**
    // Feature: pos-order-creation, Property 6: Active staff authorization

    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'status' => EmployeeStatus::OnLeave,
    ]);
    $employee->branches()->attach($this->branch->id);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $this->requestData);

    expect($response->status())->toBe(403);
    expect($response->json('message'))->toBe('Your account is not active');
});

test('terminated employee cannot create orders', function () {
    // **Property 6: Active Staff Authorization**
    // **Validates: Requirements 2.3, 2.4**
    // Feature: pos-order-creation, Property 6: Active staff authorization

    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'status' => EmployeeStatus::Terminated,
    ]);
    $employee->branches()->attach($this->branch->id);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $this->requestData);

    expect($response->status())->toBe(403);
    expect($response->json('message'))->toBe('Your account is not active');
});

test('active employee can create orders for assigned branch', function () {
    // **Validates: Requirements 2.3, 2.5, 3.2**

    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'status' => EmployeeStatus::Active,
    ]);
    $employee->branches()->attach($this->branch->id);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $this->requestData);

    // Should not return 403 (will return 501 until full implementation)
    expect($response->status())->not->toBe(403);
});

test('employee cannot create orders for unassigned branch', function () {
    // **Property 8: Staff Branch Authorization**
    // **Validates: Requirements 3.2, 3.3**
    // Feature: pos-order-creation, Property 8: Staff branch authorization

    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'status' => EmployeeStatus::Active,
    ]);

    // Create another branch that employee is NOT assigned to
    $otherBranch = Branch::factory()->create();
    $employee->branches()->attach($otherBranch->id);

    // Try to create order for the original branch (not assigned)
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $this->requestData);

    expect($response->status())->toBe(403);
    expect($response->json('message'))->toBe('You are not authorized to create orders for this branch');
});

test('employee assigned to multiple branches can create orders for any assigned branch', function () {
    // **Property 8: Staff Branch Authorization**
    // **Validates: Requirements 3.2**
    // Feature: pos-order-creation, Property 8: Staff branch authorization

    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'status' => EmployeeStatus::Active,
    ]);

    // Assign employee to multiple branches
    $branch1 = Branch::factory()->create();
    $branch2 = Branch::factory()->create();
    $employee->branches()->attach([$branch1->id, $branch2->id]);

    // Create menu items for both branches
    $menuItem1 = MenuItem::factory()->create([
        'branch_id' => $branch1->id,
        'base_price' => 20.00,
    ]);
    $menuItem2 = MenuItem::factory()->create([
        'branch_id' => $branch2->id,
        'base_price' => 30.00,
    ]);

    // Test order for branch 1
    $requestData1 = array_merge($this->requestData, [
        'branch_id' => $branch1->id,
        'items' => [
            [
                'menu_item_id' => $menuItem1->id,
                'quantity' => 1,
                'unit_price' => 20.00,
            ],
        ],
    ]);

    $response1 = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData1);

    expect($response1->status())->not->toBe(403);

    // Test order for branch 2
    $requestData2 = array_merge($this->requestData, [
        'branch_id' => $branch2->id,
        'items' => [
            [
                'menu_item_id' => $menuItem2->id,
                'quantity' => 1,
                'unit_price' => 30.00,
            ],
        ],
    ]);

    $response2 = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData2);

    expect($response2->status())->not->toBe(403);
});
