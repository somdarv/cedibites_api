<?php

namespace Tests\Feature\Api;

use App\Enums\EmployeeStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles and permissions
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        // Create test branches
        Branch::factory()->create(['id' => 1, 'name' => 'Test Branch 1']);
        Branch::factory()->create(['id' => 2, 'name' => 'Test Branch 2']);

        // Create admin user with permissions
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole(Role::Admin->value);
        $this->adminUser->givePermissionTo(['view_employees', 'manage_employees']);
    }

    public function test_admin_can_list_employees(): void
    {
        // Create test employees
        $employee1 = Employee::factory()->create();
        $employee1->branches()->attach([1, 2]);

        $employee2 = Employee::factory()->create();
        $employee2->branches()->attach([1]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/employees');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'user' => ['id', 'name', 'email', 'phone', 'roles'],
                            'branches' => [
                                '*' => ['id', 'name'],
                            ],
                            'status',
                            'employee_no',
                        ],
                    ],
                ],
            ]);
    }

    public function test_admin_can_create_employee(): void
    {
        $employeeData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '0241234567',
            'branch_ids' => [1, 2],
            'role' => Role::Employee->value,
            'status' => EmployeeStatus::Active->value,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/employees', $employeeData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user' => ['id', 'name', 'email', 'phone'],
                    'branches',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '0241234567',
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue($user->hasRole(Role::Employee->value));

        $employee = Employee::where('user_id', $user->id)->first();
        $this->assertNotNull($employee);
        $this->assertEquals(EmployeeStatus::Active, $employee->status);
        $this->assertCount(2, $employee->branches);
    }

    public function test_admin_can_update_employee(): void
    {
        $employee = Employee::factory()->create();
        $employee->branches()->attach([1]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'branch_ids' => [1, 2],
            'role' => Role::Manager->value,
        ];

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/admin/employees/{$employee->id}", $updateData);

        $response->assertOk()
            ->assertJsonPath('data.user.name', 'Updated Name')
            ->assertJsonPath('data.user.email', 'updated@example.com');

        $employee->refresh();
        $this->assertEquals('Updated Name', $employee->user->name);
        $this->assertEquals('updated@example.com', $employee->user->email);
        $this->assertTrue($employee->user->hasRole(Role::Manager->value));
        $this->assertCount(2, $employee->branches);
    }

    public function test_admin_can_view_single_employee(): void
    {
        $employee = Employee::factory()->create();
        $employee->branches()->attach([1]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/admin/employees/{$employee->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user' => ['id', 'name', 'email', 'phone', 'roles'],
                    'branches' => [
                        '*' => ['id', 'name'],
                    ],
                    'status',
                ],
            ]);
    }

    public function test_admin_can_deactivate_employee(): void
    {
        $employee = Employee::factory()->create(['status' => EmployeeStatus::Active->value]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/admin/employees/{$employee->id}");

        $response->assertOk();

        $employee->refresh();
        $this->assertEquals(EmployeeStatus::Suspended, $employee->status);
    }

    public function test_validation_fails_with_invalid_data(): void
    {
        $invalidData = [
            'name' => '', // Required
            'email' => 'invalid-email', // Invalid format
            'phone' => '', // Required (empty)
            'branch_ids' => [], // Required, min:1
            'role' => 'invalid-role', // Invalid enum
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/employees', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'branch_ids', 'role']);
    }

    public function test_user_without_permission_cannot_access_employees(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/admin/employees');

        $response->assertStatus(403);
    }

    public function test_can_filter_employees_by_branch(): void
    {
        $employee1 = Employee::factory()->create();
        $employee1->branches()->attach([1]);

        $employee2 = Employee::factory()->create();
        $employee2->branches()->attach([2]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/employees?branch_id=1');

        $response->assertOk();

        $employees = $response->json('data.data');
        $this->assertCount(1, $employees);
    }

    public function test_can_search_employees(): void
    {
        $user1 = User::factory()->create(['name' => 'John Doe', 'email' => 'john@test.com']);
        $user2 = User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@test.com']);

        Employee::factory()->create(['user_id' => $user1->id]);
        Employee::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/employees?search=John');

        $response->assertOk();

        $employees = $response->json('data.data');
        $this->assertCount(1, $employees);
        $this->assertEquals('John Doe', $employees[0]['user']['name']);
    }
}
