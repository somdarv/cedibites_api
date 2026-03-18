<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 15, 90);

        return [
            'cart_id' => Cart::factory(),
            'menu_item_id' => MenuItem::factory(),
            'menu_item_size_id' => MenuItemSize::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $quantity * $unitPrice,
            'special_instructions' => fake()->optional()->sentence(),
        ];
    }
}
