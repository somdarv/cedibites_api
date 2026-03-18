<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffRoleMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles and permissions
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        // Create test branch
        Branch::factory()->create(['id' => 1, 'name' => 'Test Branch']);

        // Create admin user
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole(Role::Admin->value);
        $this->adminUser->givePermissionTo(['view_employees', 'manage_employees']);
    }

    public function test_can_create_employees_with_all_staff_roles(): void
    {
        $staffRoles = [
            'super_admin',
            'branch_partner',
            'manager',
            'call_center',
            'kitchen',
            'rider',
        ];

        foreach ($staffRoles as $role) {
            $employeeData = [
                'name' => "Test {$role} Employee",
                'email' => "{$role}@example.com",
                'phone' => '024'.str_pad(rand(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                'branch_ids' => [1],
                'role' => $role,
            ];

            $response = $this->actingAs($this->adminUser)
                ->postJson('/api/v1/admin/employees', $employeeData);

            $response->assertStatus(201)
                ->assertJsonPath('data.user.roles.0', $role);

            // Verify the employee was created with correct role
            $user = User::where('email', $employeeData['email'])->first();
            $this->assertNotNull($user);
            $this->assertTrue($user->hasRole($role));
        }
    }

    public function test_employee_resource_returns_correct_role_format(): void
    {
        // Create an employee with call_center role
        $user = User::factory()->create();
        $user->assignRole('call_center');

        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $employee->branches()->attach([1]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/admin/employees/{$employee->id}");

        $response->assertOk()
            ->assertJsonPath('data.user.roles.0', 'call_center')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'roles',
                    ],
                    'branches',
                    'status',
                ],
            ]);
    }

    public function test_all_roles_exist_in_database(): void
    {
        $expectedRoles = [
            'admin',
            'super_admin',
            'branch_partner',
            'manager',
            'call_center',
            'kitchen',
            'rider',
            'employee', // legacy
        ];

        foreach ($expectedRoles as $role) {
            $this->assertDatabaseHas('roles', [
                'name' => $role,
                'guard_name' => 'api',
            ]);
        }
    }

    public function test_roles_have_appropriate_permissions(): void
    {
        // Test that super_admin has all permissions
        $superAdmin = \Spatie\Permission\Models\Role::where('name', 'super_admin')->first();
        $this->assertTrue($superAdmin->hasPermissionTo('manage_employees'));
        $this->assertTrue($superAdmin->hasPermissionTo('manage_menu'));
        $this->assertTrue($superAdmin->hasPermissionTo('view_analytics'));

        // Test that call_center has limited permissions
        $callCenter = \Spatie\Permission\Models\Role::where('name', 'call_center')->first();
        $this->assertTrue($callCenter->hasPermissionTo('create_orders'));
        $this->assertTrue($callCenter->hasPermissionTo('view_customers'));
        $this->assertFalse($callCenter->hasPermissionTo('manage_employees'));

        // Test that kitchen has order-related permissions
        $kitchen = \Spatie\Permission\Models\Role::where('name', 'kitchen')->first();
        $this->assertTrue($kitchen->hasPermissionTo('view_orders'));
        $this->assertTrue($kitchen->hasPermissionTo('update_orders'));
        $this->assertFalse($kitchen->hasPermissionTo('create_orders'));
    }
}
