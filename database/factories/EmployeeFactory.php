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
            'status' => fake()->randomElement(EmployeeStatus::cases()),
            'hire_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'performance_rating' => fake()->optional(0.7)->randomFloat(2, 3.0, 5.0),
        ];
    }

    /**
     * Attach the employee to one or more branches.
     */
    public function forBranches(array $branches): static
    {
        return $this->afterCreating(function (\App\Models\Employee $employee) use ($branches) {
            $employee->branches()->sync(
                collect($branches)->map(fn ($b) => $b instanceof Branch ? $b->id : $b)->all()
            );
        });
    }
}
