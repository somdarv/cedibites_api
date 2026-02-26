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
        $branches = Branch::all();

        foreach ($branches as $branch) {
            $this->createEmployeesForBranch($branch);
        }
    }

    private function createEmployeesForBranch(Branch $branch): void
    {
        // Create manager
        $manager = User::updateOrCreate(
            ['email' => 'manager.'.$branch->id.'@cedibites.com'],
            [
                'name' => fake()->name(),
                'username' => 'manager'.$branch->id,
                'phone' => '+233'.fake()->numerify('#########'),
                'password' => bcrypt('password'),
            ]
        );

        $manager->syncRoles([Role::Manager->value]);

        Employee::updateOrCreate(
            ['user_id' => $manager->id],
            [
                'employee_no' => 'MGR'.str_pad($branch->id, 4, '0', STR_PAD_LEFT),
                'branch_id' => $branch->id,
                'status' => EmployeeStatus::Active,
                'hire_date' => now()->subMonths(12),
                'performance_rating' => 4.5,
            ]
        );

        // Create 2 employees
        for ($i = 1; $i <= 2; $i++) {
            $employee = User::updateOrCreate(
                ['email' => 'employee.'.$branch->id.'.'.$i.'@cedibites.com'],
                [
                    'name' => fake()->name(),
                    'username' => 'employee'.$branch->id.$i,
                    'phone' => '+233'.fake()->numerify('#########'),
                    'password' => bcrypt('password'),
                ]
            );

            $employee->syncRoles([Role::Employee->value]);

            Employee::updateOrCreate(
                ['user_id' => $employee->id],
                [
                    'employee_no' => 'EMP'.str_pad(($branch->id * 10 + $i), 4, '0', STR_PAD_LEFT),
                    'branch_id' => $branch->id,
                    'status' => EmployeeStatus::Active,
                    'hire_date' => now()->subMonths(rand(3, 18)),
                    'performance_rating' => fake()->randomFloat(2, 3.5, 5.0),
                ]
            );
        }
    }
}
