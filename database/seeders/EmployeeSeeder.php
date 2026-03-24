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
    /**
     * POS login test credentials: PIN 1234 for at least one employee per branch.
     * Admin: admin@cedibites.com / password
     */
    public function run(): void
    {
        $this->createAdmin();

        $branches = Branch::all();

        foreach ($branches as $branch) {
            $this->createEmployeesForBranch($branch);
        }
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
                'pos_pin' => null,
            ]
        );
        $emp->branches()->sync(Branch::all());
    }

    private function createEmployeesForBranch(Branch $branch): void
    {
        // Create manager (with pos_pin 1234 for POS login testing)
        $manager = User::updateOrCreate(
            ['email' => 'manager.'.$branch->id.'@cedibites.com'],
            [
                'name' => 'Branch Manager '.$branch->id,
                'username' => 'manager'.$branch->id,
                'phone' => '+23324'.str_pad($branch->id, 7, '0', STR_PAD_LEFT),
                'password' => bcrypt('password'),
            ]
        );

        $manager->syncRoles([Role::Manager->value]);

        $emp = Employee::updateOrCreate(
            ['user_id' => $manager->id],
            [
                'employee_no' => 'MGR'.str_pad($branch->id, 4, '0', STR_PAD_LEFT),
                'status' => EmployeeStatus::Active,
                'hire_date' => now()->subMonths(12),
                'performance_rating' => 4.5,
                'pos_pin' => '1234',
            ]
        );
        $emp->branches()->sync([$branch->id]);

        // Create 2 employees
        for ($i = 1; $i <= 2; $i++) {
            $employee = User::updateOrCreate(
                ['email' => 'employee.'.$branch->id.'.'.$i.'@cedibites.com'],
                [
                    'name' => 'Employee '.$branch->id.'-'.$i,
                    'username' => 'employee'.$branch->id.$i,
                    'phone' => '+23320'.str_pad($branch->id * 10 + $i, 7, '0', STR_PAD_LEFT),
                    'password' => bcrypt('password'),
                ]
            );

            $employee->syncRoles([Role::Employee->value]);

            $emp = Employee::updateOrCreate(
                ['user_id' => $employee->id],
                [
                    'employee_no' => 'EMP'.str_pad(($branch->id * 10 + $i), 4, '0', STR_PAD_LEFT),
                    'status' => EmployeeStatus::Active,
                    'hire_date' => now()->subMonths(rand(3, 18)),
                    'performance_rating' => round(3.5 + ($i * 0.5), 2),
                ]
            );
            $emp->branches()->sync([$branch->id]);
        }
    }
}
