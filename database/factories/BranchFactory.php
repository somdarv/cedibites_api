<?php

namespace Database\Factories;

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
            'latitude' => fake()->latitude(5.5, 5.7),
            'longitude' => fake()->longitude(-0.3, -0.1),
            'is_active' => true,
            'operating_hours' => '8:00 AM - 10:00 PM',
            'delivery_fee' => fake()->randomFloat(2, 10, 20),
            'delivery_radius_km' => fake()->randomFloat(2, 5, 15),
            'estimated_delivery_time' => fake()->numberBetween(25, 45).' mins',
        ];
    }
}
