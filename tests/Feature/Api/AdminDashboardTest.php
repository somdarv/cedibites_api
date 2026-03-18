<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
});

test('admin can fetch dashboard data', function () {
    $adminUser = User::factory()->create();
    $adminUser->assignRole(Role::Admin->value);

    Branch::factory()->count(2)->create(['is_active' => true]);

    $response = $this->actingAs($adminUser, 'sanctum')
        ->getJson('/api/v1/admin/dashboard');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'user_name',
            'kpis' => [
                'revenue_today',
                'orders_today',
                'active_orders',
                'cancelled_today',
            ],
            'branches' => [
                '*' => [
                    'id',
                    'name',
                    'status',
                    'revenue_today',
                    'orders_today',
                ],
            ],
            'live_orders',
        ],
    ]);
});
