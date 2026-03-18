<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 15, 90);

        return [
            'order_id' => Order::factory(),
            'menu_item_id' => MenuItem::factory(),
            'menu_item_size_id' => MenuItemSize::factory(),
            'menu_item_snapshot' => [
                'name' => fake()->words(3, true),
                'description' => fake()->sentence(),
            ],
            'menu_item_size_snapshot' => [
                'name' => fake()->randomElement(['Small', 'Large']),
                'price' => $unitPrice,
            ],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $quantity * $unitPrice,
            'special_instructions' => fake()->optional()->sentence(),
        ];
    }
}
