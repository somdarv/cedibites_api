<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Accra Central', 'East Legon', 'Labadi', 'Osu', 'Tema']),
            'area' => fake()->randomElement(['Greater Accra', 'Tema', 'Osu']),
            'address' => fake()->streetAddress().', Accra',
            'phone' => '+233'.fake()->numerify('#########'),
            'email' => fake()->optional()->safeEmail(),
            'latitude' => fake()->latitude(5.5, 5.7),
            'longitude' => fake()->longitude(-0.3, -0.1),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Branch $branch) {
            // Create operating hours for all days
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            foreach ($days as $day) {
                $branch->operatingHours()->create([
                    'day_of_week' => $day,
                    'is_open' => true,
                    'open_time' => '08:00',
                    'close_time' => '22:00',
                ]);
            }

            // Create delivery settings
            $branch->deliverySettings()->create([
                'base_delivery_fee' => fake()->randomFloat(2, 10, 20),
                'per_km_fee' => fake()->randomFloat(2, 2, 5),
                'delivery_radius_km' => fake()->randomFloat(2, 5, 15),
                'min_order_value' => fake()->randomFloat(2, 30, 100),
                'estimated_delivery_time' => fake()->numberBetween(25, 45).' mins',
                'is_active' => true,
                'effective_from' => now(),
            ]);

            // Create order types
            $branch->orderTypes()->createMany([
                ['order_type' => 'delivery', 'is_enabled' => true],
                ['order_type' => 'pickup', 'is_enabled' => true],
                ['order_type' => 'dine_in', 'is_enabled' => fake()->boolean(30)],
            ]);

            // Create payment methods
            $branch->paymentMethods()->createMany([
                ['payment_method' => 'momo', 'is_enabled' => true],
                ['payment_method' => 'cash_on_delivery', 'is_enabled' => true],
                ['payment_method' => 'cash_at_pickup', 'is_enabled' => true],
            ]);
        });
    }
}
