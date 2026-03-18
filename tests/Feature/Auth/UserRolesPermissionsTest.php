<?php

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\PermissionSeeder::class,
        \Database\Seeders\RoleSeeder::class,
    ]);
});

test('authenticated user endpoint returns roles and permissions for admin', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Admin->value);

    Customer::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/user');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'phone',
                'email',
                'customer',
                'roles',
                'permissions',
            ],
        ])
        ->assertJsonPath('data.roles', ['admin'])
        ->assertJsonPath('data.permissions.0', 'view_orders');

    expect($response->json('data.permissions'))->toBeArray()
        ->and($response->json('data.permissions'))->toContain('view_branches')
        ->and($response->json('data.permissions'))->toContain('manage_branches');
});

test('authenticated user endpoint returns roles and permissions for manager', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Manager->value);

    Customer::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/user');

    $response->assertOk()
        ->assertJsonPath('data.roles', ['manager']);

    expect($response->json('data.permissions'))->toBeArray()
        ->and($response->json('data.permissions'))->toContain('view_branches')
        ->and($response->json('data.permissions'))->toContain('manage_branches');
});

test('authenticated user endpoint returns roles and permissions for employee', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Employee->value);

    Customer::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/user');

    $response->assertOk()
        ->assertJsonPath('data.roles', ['employee']);

    expect($response->json('data.permissions'))->toBeArray()
        ->and($response->json('data.permissions'))->toContain('view_branches')
        ->and($response->json('data.permissions'))->not->toContain('manage_branches');
});

test('user without roles has empty roles and permissions arrays', function () {
    $user = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/user');

    $response->assertOk()
        ->assertJsonPath('data.roles', [])
        ->assertJsonPath('data.permissions', []);
});
