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

    // Create authenticated user with active employee
    $this->user = User::factory()->create();
    $this->employee = Employee::factory()->create([
        'user_id' => $this->user->id,
        'status' => EmployeeStatus::Active,
    ]);
    $this->employee->branches()->attach($this->branch->id);

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

test('non-existent branch returns 422', function () {
    // **Property 7: Branch Existence Validation**
    // **Validates: Requirements 3.1, 3.4**
    // Feature: pos-order-creation, Property 7: Branch existence validation

    // Get a non-existent branch ID
    $nonExistentBranchId = Branch::max('id') + 1;

    $requestData = array_merge($this->requestData, [
        'branch_id' => $nonExistentBranchId,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $requestData);

    expect($response->status())->toBe(422);
    expect($response->json('message'))->toBe('Invalid branch');
});

test('valid branch with assigned employee passes validation', function () {
    // **Validates: Requirements 3.1**

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/pos/orders', $this->requestData);

    // Should not return 422 for valid branch (will return 501 until full implementation)
    expect($response->status())->not->toBe(422);
});

test('property test: random non-existent branch IDs are rejected', function () {
    // **Property 7: Branch Existence Validation**
    // **Validates: Requirements 3.1**
    // Feature: pos-order-creation, Property 7: Branch existence validation

    // Get the maximum branch ID
    $maxBranchId = Branch::max('id') ?? 0;

    // Test with multiple non-existent branch IDs
    for ($i = 0; $i < 10; $i++) {
        $nonExistentBranchId = $maxBranchId + rand(1, 1000);

        $requestData = array_merge($this->requestData, [
            'branch_id' => $nonExistentBranchId,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/pos/orders', $requestData);

        expect($response->status())->toBe(422);
        expect($response->json('message'))->toBe('Invalid branch');
    }
});
