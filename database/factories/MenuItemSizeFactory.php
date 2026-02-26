<?php

namespace Database\Factories;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemSizeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_item_id' => MenuItem::factory(),
            'name' => fake()->randomElement(['Small', 'Medium', 'Large', '350ml', '500ml']),
            'price' => fake()->randomFloat(2, 15, 90),
            'size_order' => fake()->numberBetween(0, 5),
            'is_available' => true,
        ];
    }
}
