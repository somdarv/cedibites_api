<?php

namespace Database\Factories;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MenuItemOption>
 */
class MenuItemOptionFactory extends Factory
{
    public function definition(): array
    {
        $label = fake()->randomElement(['Small', 'Medium', 'Large', '350ml', '500ml']);

        return [
            'menu_item_id' => MenuItem::factory(),
            'option_key' => Str::slug($label).'-'.fake()->unique()->numerify('##'),
            'option_label' => $label,
            'price' => fake()->randomFloat(2, 15, 90),
            'display_order' => fake()->numberBetween(0, 5),
            'is_available' => true,
        ];
    }
}
