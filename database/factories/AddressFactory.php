<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'label' => fake()->randomElement(['Home', 'Office', 'Other']),
            'full_address' => fake()->streetAddress().', '.fake()->city().', Accra',
            'note' => fake()->optional()->sentence(),
            'latitude' => fake()->latitude(5.5, 5.7),
            'longitude' => fake()->longitude(-0.3, -0.1),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
