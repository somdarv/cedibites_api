<?php

use App\Models\Branch;
use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\PermissionSeeder::class,
        \Database\Seeders\RoleSeeder::class,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin, 'sanctum');
});

test('can create branch with normalized data', function () {
    $payload = [
        'name' => 'Test Branch',
        'area' => 'Test Area',
        'address' => '123 Test St',
        'phone' => '+233123456789',
        'email' => 'test@branch.com',
        'latitude' => 5.6037,
        'longitude' => -0.1870,
        'is_active' => true,
        'operating_hours' => [
            'monday' => ['is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
            'tuesday' => ['is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
            'wednesday' => ['is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
            'thursday' => ['is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
            'friday' => ['is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
            'saturday' => ['is_open' => true, 'open_time' => '09:00', 'close_time' => '18:00'],
            'sunday' => ['is_open' => false, 'open_time' => null, 'close_time' => null],
        ],
        'delivery_settings' => [
            'base_delivery_fee' => 15.00,
            'per_km_fee' => 3.00,
            'delivery_radius_km' => 10.00,
            'min_order_value' => 50.00,
            'estimated_delivery_time' => '30-45 mins',
        ],
        'order_types' => [
            'delivery' => ['is_enabled' => true],
            'pickup' => ['is_enabled' => true],
            'dine_in' => ['is_enabled' => false],
        ],
        'payment_methods' => [
            'momo' => ['is_enabled' => true],
            'cash_on_delivery' => ['is_enabled' => true],
            'cash_at_pickup' => ['is_enabled' => true],
        ],
    ];

    $response = $this->postJson('/api/v1/admin/branches', $payload);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'area',
                'address',
                'phone',
                'email',
                'operating_hours',
                'delivery_settings',
                'order_types',
                'payment_methods',
            ],
        ]);

    $branch = Branch::first();
    expect($branch->name)->toBe('Test Branch');
    expect($branch->operatingHours)->toHaveCount(7);
    expect($branch->deliverySettings)->toHaveCount(1);
    expect($branch->orderTypes)->toHaveCount(3);
    expect($branch->paymentMethods)->toHaveCount(3);
});

test('can update branch with normalized data', function () {
    $branch = Branch::factory()->create(['name' => 'Original Name']);

    $payload = [
        'name' => 'Updated Name',
        'delivery_settings' => [
            'base_delivery_fee' => 20.00,
            'per_km_fee' => 5.00,
            'delivery_radius_km' => 15.00,
            'min_order_value' => 75.00,
        ],
        'order_types' => [
            'delivery' => ['is_enabled' => true],
            'pickup' => ['is_enabled' => false],
            'dine_in' => ['is_enabled' => true],
        ],
    ];

    $response = $this->patchJson("/api/v1/admin/branches/{$branch->id}", $payload);

    $response->assertOk();

    $branch->refresh();
    expect($branch->name)->toBe('Updated Name');
    expect($branch->activeDeliverySetting()->base_delivery_fee)->toBe('20.00');
    expect($branch->orderTypes()->where('order_type', 'pickup')->first()->is_enabled)->toBeFalse();
    expect($branch->orderTypes()->where('order_type', 'dine_in')->first()->is_enabled)->toBeTrue();
});

test('can retrieve branch with all normalized relationships', function () {
    $branch = Branch::factory()->create();

    $response = $this->getJson("/api/v1/admin/branches/{$branch->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'operating_hours',
                'delivery_settings',
                'order_types',
                'payment_methods',
            ],
        ]);
});

test('can delete branch', function () {
    $branch = Branch::factory()->create();

    $response = $this->deleteJson("/api/v1/admin/branches/{$branch->id}");

    $response->assertOk();
    expect(Branch::find($branch->id))->toBeNull();
});

test('can assign manager to branch', function () {
    $branch = Branch::factory()->create();

    // Create an employee without manager role initially
    $employee = \App\Models\Employee::factory()->create();

    $payload = [
        'manager_id' => $employee->id,
    ];

    $response = $this->patchJson("/api/v1/admin/branches/{$branch->id}", $payload);

    $response->assertOk();

    // Verify employee is attached to branch
    $branch = $branch->fresh();
    expect($branch->employees()->where('employee_id', $employee->id)->exists())->toBeTrue();

    // Verify employee now has manager role
    $employee = $employee->fresh();
    expect($employee->user->hasRole('manager'))->toBeTrue();
});

test('can change branch manager', function () {
    $branch = Branch::factory()->create();

    // Create first manager
    $manager1 = \App\Models\Employee::factory()->create();
    $manager1->user->assignRole('manager');
    $branch->employees()->attach($manager1->id);

    // Create second manager (without role initially)
    $manager2 = \App\Models\Employee::factory()->create();

    // Change manager
    $payload = [
        'manager_id' => $manager2->id,
    ];

    $response = $this->patchJson("/api/v1/admin/branches/{$branch->id}", $payload);

    $response->assertOk();

    $branch = $branch->fresh();

    // Verify old manager is removed
    expect($branch->employees()->where('employee_id', $manager1->id)->exists())->toBeFalse();

    // Verify new manager is attached
    expect($branch->employees()->where('employee_id', $manager2->id)->exists())->toBeTrue();

    // Verify new manager now has the role
    $manager2 = $manager2->fresh();
    expect($manager2->user->hasRole('manager'))->toBeTrue();
});
