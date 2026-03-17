<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PromoFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', '+1 month');
        $endDate = fake()->dateTimeBetween($startDate, '+3 months');

        return [
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['percentage', 'fixed_amount']),
            'value' => fake()->randomFloat(2, 5, 50),
            'scope' => fake()->randomElement(['global', 'branch']),
            'applies_to' => fake()->randomElement(['order', 'items']),
            'min_order_value' => fake()->optional(0.5)->randomFloat(2, 20, 100),
            'max_order_value' => fake()->optional(0.3)->randomFloat(2, 100, 500),
            'max_discount' => fake()->optional(0.5)->randomFloat(2, 10, 100),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_active' => fake()->boolean(80),
            'accounting_code' => fake()->optional(0.5)->regexify('[A-Z]{2}[0-9]{4}'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'start_date' => now()->subDays(7),
            'end_date' => now()->addMonths(3),
        ]);
    }

    public function percentage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'percentage',
            'value' => fake()->randomFloat(2, 5, 30),
        ]);
    }

    public function fixedAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fixed_amount',
            'value' => fake()->randomFloat(2, 5, 50),
        ]);
    }

    public function orderScope(): static
    {
        return $this->state(fn (array $attributes) => ['applies_to' => 'order']);
    }

    public function itemScope(): static
    {
        return $this->state(fn (array $attributes) => ['applies_to' => 'items']);
    }
}
