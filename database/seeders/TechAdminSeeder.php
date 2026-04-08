<?php

namespace Database\Seeders;

use App\Enums\EmployeeStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class TechAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['phone' => '+233592123054'],
            [
                'name' => 'Black Baron',
                'email' => 'richardsomdajnr@gmail.com',
                'password' => bcrypt('_GdmuddifyK3'),
                'must_reset_password' => false,
            ]
        );

        $user->syncRoles([Role::TechAdmin->value]);

        $emp = Employee::updateOrCreate(
            ['user_id' => $user->id],
            [
                'employee_no' => 'TECH0001',
                'status' => EmployeeStatus::Active,
                'hire_date' => now(),
            ]
        );

        $emp->branches()->syncWithoutDetaching(Branch::all());

        $this->command->info("Tech admin created: user_id={$user->id}, employee_no={$emp->employee_no}");
    }
}
