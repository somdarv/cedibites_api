<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MenuItem>
 */
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
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'description' => fake()->sentence(),
            'is_available' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (MenuItem $item): void {
            if ($item->options()->exists()) {
                return;
            }

            MenuItemOption::create([
                'menu_item_id' => $item->id,
                'option_key' => 'standard',
                'option_label' => 'Standard',
                'price' => fake()->randomFloat(2, 10, 100),
                'display_order' => 0,
                'is_available' => true,
            ]);
        });
    }
}
