<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 15, 90);

        return [
            'order_id' => Order::factory(),
            'menu_item_id' => MenuItem::factory(),
            'menu_item_option_id' => null,
            'menu_item_snapshot' => [
                'name' => fake()->words(3, true),
                'description' => fake()->sentence(),
            ],
            'menu_item_option_snapshot' => [
                'option_key' => 'standard',
                'option_label' => 'Standard',
                'price' => $unitPrice,
            ],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $quantity * $unitPrice,
            'special_instructions' => fake()->optional()->sentence(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (OrderItem $item): void {
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
