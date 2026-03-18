<?php

use App\Enums\EmployeeStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->password = 'password';

    // Seed roles and permissions for tests
    $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
});

describe('Employee Login', function () {
    test('logs in active employee successfully', function () {
        $user = User::factory()->create([
            'email' => 'employee@cedibites.com',
            'password' => bcrypt($this->password),
        ]);

        Employee::factory()->forBranches([$this->branch])->create([
            'user_id' => $user->id,
            'status' => EmployeeStatus::Active,
        ]);

        $user->assignRole(Role::Employee->value);

        $response = $this->postJson('/api/v1/employee/login', [
            'identifier' => 'employee@cedibites.com',
            'password' => $this->password,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'role',
                        'branch',
                        'branchId',
                    ],
                ],
            ]);

        expect($response->json('data.token'))->toBeString();
        expect($response->json('data.user.name'))->toBe($user->name);
        expect($response->json('data.user.role'))->toBe('employee');
    });

    test('rejects invalid credentials', function () {
        $user = User::factory()->create([
            'email' => 'employee@cedibites.com',
            'password' => bcrypt($this->password),
        ]);

        Employee::factory()->forBranches([$this->branch])->create([
            'user_id' => $user->id,
        ]);

        $response = $this->postJson('/api/v1/employee/login', [
            'identifier' => 'employee@cedibites.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized();
    });

    test('rejects user without employee record', function () {
        $user = User::factory()->create([
            'email' => 'customer@cedibites.com',
            'password' => bcrypt($this->password),
        ]);

        $response = $this->postJson('/api/v1/employee/login', [
            'identifier' => 'customer@cedibites.com',
            'password' => $this->password,
        ]);

        $response->assertForbidden()
            ->assertJson([
                'message' => 'User is not an employee',
            ]);
    });

    test('rejects inactive employee', function () {
        $user = User::factory()->create([
            'email' => 'employee@cedibites.com',
            'password' => bcrypt($this->password),
        ]);

        Employee::factory()->forBranches([$this->branch])->create([
            'user_id' => $user->id,
            'status' => EmployeeStatus::Suspended,
        ]);

        $response = $this->postJson('/api/v1/employee/login', [
            'identifier' => 'employee@cedibites.com',
            'password' => $this->password,
        ]);

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Employee account is inactive',
            ]);
    });

    test('rejects terminated employee', function () {
        $user = User::factory()->create([
            'email' => 'employee@cedibites.com',
            'password' => bcrypt($this->password),
        ]);

        Employee::factory()->forBranches([$this->branch])->create([
            'user_id' => $user->id,
            'status' => EmployeeStatus::Terminated,
        ]);

        $response = $this->postJson('/api/v1/employee/login', [
            'identifier' => 'employee@cedibites.com',
            'password' => $this->password,
        ]);

        $response->assertForbidden();
    });

    test('validates identifier is required', function () {
        $response = $this->postJson('/api/v1/employee/login', [
            'password' => $this->password,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['identifier']);
    });

    test('validates password is required', function () {
        $response = $this->postJson('/api/v1/employee/login', [
            'identifier' => 'employee@cedibites.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });

    test('returns roles and permissions', function () {
        $user = User::factory()->create([
            'email' => 'manager@cedibites.com',
            'password' => bcrypt($this->password),
        ]);

        Employee::factory()->forBranches([$this->branch])->create([
            'user_id' => $user->id,
            'status' => EmployeeStatus::Active,
        ]);

        $user->assignRole(Role::Manager->value);

        $response = $this->postJson('/api/v1/employee/login', [
            'identifier' => 'manager@cedibites.com',
            'password' => $this->password,
        ]);

        $response->assertOk();

        expect($response->json('data.user.role'))->toBe('manager');
    });
});

describe('Employee Logout', function () {
    test('logs out employee successfully', function () {
        $user = User::factory()->create();
        Employee::factory()->forBranches([$this->branch])->create([
            'user_id' => $user->id,
        ]);

        $token = $user->createToken('employee-auth-token');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/employee/logout');

        $response->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    });

    test('rejects unauthenticated logout', function () {
        $response = $this->postJson('/api/v1/employee/logout');

        $response->assertUnauthorized();
    });
});
