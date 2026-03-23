<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 15, 90);

        return [
            'cart_id' => Cart::factory(),
            'menu_item_id' => MenuItem::factory(),
            'menu_item_option_id' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $quantity * $unitPrice,
            'special_instructions' => fake()->optional()->sentence(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (CartItem $item): void {
            if ($item->menu_item_option_id !== null) {
                return;
            }
            $opt = $item->menuItem->options()->orderBy('display_order')->first();
            if ($opt) {
                $item->update(['menu_item_option_id' => $opt->id]);
            }
        });
    }
}
