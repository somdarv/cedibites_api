<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MenuCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement(['Basic Meals', 'Budget Bowls', 'Combos', 'Top Ups', 'Drinks']);

        return [
            'branch_id' => Branch::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'display_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}
