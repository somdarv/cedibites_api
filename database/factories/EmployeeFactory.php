<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'employee_no' => 'EMP'.fake()->unique()->numberBetween(1000, 9999),
            'branch_id' => Branch::factory(),
            'status' => fake()->randomElement(EmployeeStatus::cases()),
            'hire_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'performance_rating' => fake()->randomFloat(2, 3.0, 5.0),
        ];
    }
}
