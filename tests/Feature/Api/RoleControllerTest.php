<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(['PermissionSeeder', 'RoleSeeder']);
    }

    public function test_can_get_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/roles');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'display_name',
                    'permissions',
                ],
            ],
        ]);

        $roles = $response->json('data');
        $this->assertGreaterThan(0, count($roles));

        // Check that we have expected roles
        $roleNames = collect($roles)->pluck('name')->toArray();
        $this->assertContains('super_admin', $roleNames);
        $this->assertContains('call_center', $roleNames);
    }

    public function test_can_get_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/permissions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'display_name',
                    'description',
                ],
            ],
        ]);

        $permissions = $response->json('data');
        $this->assertGreaterThan(0, count($permissions));

        // Check that we have expected permissions
        $permissionNames = collect($permissions)->pluck('name')->toArray();
        $this->assertContains('create_orders', $permissionNames);
        $this->assertContains('manage_employees', $permissionNames);
    }

    public function test_requires_manage_employees_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('call_center'); // This role doesn't have manage_employees permission

        $response = $this->actingAs($user)->getJson('/api/v1/admin/roles');
        $response->assertStatus(403);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/permissions');
        $response->assertStatus(403);
    }
}
