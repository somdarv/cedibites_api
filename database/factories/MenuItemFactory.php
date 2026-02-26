<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Jollof Rice with Chicken',
            'Fried Rice with Chicken',
            'Banku with Tilapia',
            'Waakye Special',
            'Fufu & Light Soup',
            'Kelewele',
            'Sobolo',
            'Asaana',
        ]);

        return [
            'branch_id' => Branch::factory(),
            'category_id' => MenuCategory::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'base_price' => fake()->optional(0.5)->randomFloat(2, 20, 100),
            'is_available' => true,
            'is_popular' => fake()->boolean(30),
        ];
    }
}
