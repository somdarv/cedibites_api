<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeHrFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(['PermissionSeeder', 'RoleSeeder', 'BranchSeeder']);
    }

    public function test_can_create_employee_with_hr_fields_and_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $branch = Branch::first();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/employees', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '0241234567',
            'role' => 'call_center',
            'branch_ids' => [$branch->id],
            'ssnit_number' => 'C123456789',
            'ghana_card_id' => 'GHA-123456789-0',
            'tin_number' => 'P1234567890',
            'date_of_birth' => '1990-01-15',
            'nationality' => 'Ghanaian',
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_phone' => '0241234568',
            'emergency_contact_relationship' => 'Spouse',
            'pos_pin' => '1234',
            'permissions' => ['create_orders', 'view_orders'],
        ]);

        $response->assertStatus(201);

        $employee = Employee::where('user_id', $response->json('data.user_id'))->first();

        $this->assertEquals('C123456789', $employee->ssnit_number);
        $this->assertEquals('GHA-123456789-0', $employee->ghana_card_id);
        $this->assertEquals('P1234567890', $employee->tin_number);
        $this->assertEquals('1990-01-15', $employee->date_of_birth->toDateString());
        $this->assertEquals('Ghanaian', $employee->nationality);
        $this->assertEquals('Jane Doe', $employee->emergency_contact_name);
        $this->assertEquals('0241234568', $employee->emergency_contact_phone);
        $this->assertEquals('Spouse', $employee->emergency_contact_relationship);
        $this->assertEquals('1234', $employee->pos_pin);

        // Check individual permissions
        $this->assertTrue($employee->user->hasPermissionTo('create_orders'));
        $this->assertTrue($employee->user->hasPermissionTo('view_orders'));
        $this->assertFalse($employee->user->hasPermissionTo('manage_employees'));
    }

    public function test_can_update_employee_hr_fields_and_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $employee = Employee::factory()->create();
        $employee->user->assignRole('call_center');

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/employees/{$employee->id}", [
            'ssnit_number' => 'C987654321',
            'ghana_card_id' => 'GHA-987654321-0',
            'tin_number' => 'P0987654321',
            'date_of_birth' => '1985-05-20',
            'nationality' => 'Ghanaian',
            'emergency_contact_name' => 'John Smith',
            'emergency_contact_phone' => '0241234569',
            'emergency_contact_relationship' => 'Brother',
            'pos_pin' => '5678',
            'permissions' => ['create_orders', 'update_orders', 'view_analytics'],
        ]);

        $response->assertStatus(200);

        $employee->refresh();

        $this->assertEquals('C987654321', $employee->ssnit_number);
        $this->assertEquals('GHA-987654321-0', $employee->ghana_card_id);
        $this->assertEquals('P0987654321', $employee->tin_number);
        $this->assertEquals('1985-05-20', $employee->date_of_birth->toDateString());
        $this->assertEquals('Ghanaian', $employee->nationality);
        $this->assertEquals('John Smith', $employee->emergency_contact_name);
        $this->assertEquals('0241234569', $employee->emergency_contact_phone);
        $this->assertEquals('Brother', $employee->emergency_contact_relationship);
        $this->assertEquals('5678', $employee->pos_pin);

        // Check updated permissions
        $this->assertTrue($employee->user->hasPermissionTo('create_orders'));
        $this->assertTrue($employee->user->hasPermissionTo('update_orders'));
        $this->assertTrue($employee->user->hasPermissionTo('view_analytics'));
        $this->assertFalse($employee->user->hasPermissionTo('manage_employees'));
    }

    public function test_employee_resource_includes_hr_fields_and_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $employee = Employee::factory()->create([
            'ssnit_number' => 'C111222333',
            'ghana_card_id' => 'GHA-111222333-0',
            'tin_number' => 'P1112223330',
            'date_of_birth' => '1992-03-10',
            'nationality' => 'Ghanaian',
            'emergency_contact_name' => 'Mary Johnson',
            'emergency_contact_phone' => '0241234570',
            'emergency_contact_relationship' => 'Sister',
            'pos_pin' => '9999',
        ]);

        $employee->user->assignRole('manager');
        $employee->user->givePermissionTo(['create_orders', 'manage_menu']);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/employees/{$employee->id}");

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals('C111222333', $data['ssnit_number']);
        $this->assertEquals('GHA-111222333-0', $data['ghana_card_id']);
        $this->assertEquals('P1112223330', $data['tin_number']);
        $this->assertEquals('1992-03-10', $data['date_of_birth']);
        $this->assertEquals('Ghanaian', $data['nationality']);
        $this->assertEquals('Mary Johnson', $data['emergency_contact_name']);
        $this->assertEquals('0241234570', $data['emergency_contact_phone']);
        $this->assertEquals('Sister', $data['emergency_contact_relationship']);
        $this->assertEquals('9999', $data['pos_pin']);

        $this->assertContains('create_orders', $data['user']['permissions']);
        $this->assertContains('manage_menu', $data['user']['permissions']);
    }
}
