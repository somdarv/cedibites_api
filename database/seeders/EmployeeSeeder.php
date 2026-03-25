<?php

namespace Database\Seeders;

use App\Enums\EmployeeStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $this->createAdmin();
    }

    private function createAdmin(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@cedibites.com'],
            [
                'name' => 'Platform Admin',
                'username' => 'admin',
                'phone' => '+233241000000',
                'password' => bcrypt('password'),
            ]
        );

        $admin->syncRoles([Role::Admin->value]);

        $emp = Employee::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'employee_no' => 'ADM0001',
                'status' => EmployeeStatus::Active,
                'hire_date' => now()->subYear(),
                'performance_rating' => null,
            ]
        );
        $emp->branches()->sync(Branch::all());
    }
}
