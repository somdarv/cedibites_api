<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\PermissionSeeder::class,
        \Database\Seeders\RoleSeeder::class,
    ]);
});

test('admin can view branches list', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    Branch::factory()->count(3)->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/branches');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'area', 'address', 'today_orders', 'today_revenue'],
            ],
        ]);
});

test('manager can view branches list', function () {
    $manager = User::factory()->create();
    $manager->assignRole(Role::Manager->value);

    Branch::factory()->count(3)->create();

    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/v1/admin/branches');

    $response->assertOk();
});

test('employee can view branches list', function () {
    $employee = User::factory()->create();
    $employee->assignRole(Role::Employee->value);

    Branch::factory()->count(3)->create();

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson('/api/v1/admin/branches');

    $response->assertOk();
});

test('user without view_branches permission cannot access admin branches', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/admin/branches');

    $response->assertForbidden()
        ->assertJson([
            'message' => 'You do not have permission to perform this action.',
        ]);
});

test('unauthenticated user cannot access admin branches', function () {
    $response = $this->getJson('/api/v1/admin/branches');

    $response->assertUnauthorized();
});

test('branches list includes today stats', function () {
    \Illuminate\Support\Facades\Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $branch = Branch::factory()->create();

    // Create orders for today
    \App\Models\Order::factory()->count(5)->create([
        'branch_id' => $branch->id,
        'status' => 'completed',
        'total_amount' => 100.00,
        'created_at' => now(),
    ]);

    // Create orders from yesterday (should not be counted)
    \App\Models\Order::factory()->count(3)->create([
        'branch_id' => $branch->id,
        'status' => 'completed',
        'total_amount' => 50.00,
        'created_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/branches');

    $response->assertOk();

    $branchData = collect($response->json('data'))->firstWhere('id', $branch->id);

    expect($branchData['today_orders'])->toBe(5);
    expect((float) $branchData['today_revenue'])->toBe(500.0);
});
