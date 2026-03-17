<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftFactory extends Factory
{
    public function definition(): array
    {
        $loginAt = fake()->dateTimeBetween('-8 hours', 'now');
        $logoutAt = fake()->optional(0.5)->dateTimeBetween($loginAt, 'now');

        $totalSales = fake()->randomFloat(2, 0, 500);
        $orderCount = fake()->numberBetween(0, 15);

        return [
            'employee_id' => Employee::factory(),
            'branch_id' => Branch::factory(),
            'login_at' => $loginAt,
            'logout_at' => $logoutAt,
            'total_sales' => $totalSales,
            'order_count' => $orderCount,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'logout_at' => null,
            'login_at' => now()->subHours(rand(1, 6)),
        ]);
    }

    public function closed(): static
    {
        return $this->state(function (array $attributes) {
            $loginAt = $attributes['login_at'] ?? fake()->dateTimeBetween('-12 hours', '-2 hours');
            $loginAt = $loginAt instanceof \DateTimeInterface ? $loginAt : new \DateTime($loginAt);
            $logoutAt = (clone $loginAt)->modify('+'.rand(4, 8).' hours');

            return [
                'login_at' => $loginAt,
                'logout_at' => $logoutAt,
            ];
        });
    }
}
