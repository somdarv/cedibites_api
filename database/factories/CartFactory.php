<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'session_id' => null,
            'branch_id' => Branch::factory(),
            'status' => 'active',
        ];
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => null,
            'session_id' => fake()->uuid(),
        ]);
    }
}
