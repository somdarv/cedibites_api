<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use Illuminate\Database\Seeder;

class CartSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();

        foreach ($branches as $branch) {
            // Create 2-3 active carts per branch
            for ($i = 0; $i < rand(2, 3); $i++) {
                $customer = Customer::inRandomOrder()->first();

                $cart = Cart::create([
                    'customer_id' => $customer->id,
                    'branch_id' => $branch->id,
                    'status' => 'active',
                ]);

                // Add 1-3 items to cart
                $menuItems = $branch->menuItems()->inRandomOrder()->limit(rand(1, 3))->get();

                foreach ($menuItems as $menuItem) {
                    $size = $menuItem->sizes()->inRandomOrder()->first();
                    $quantity = rand(1, 2);
                    $unitPrice = $size?->price ?? $menuItem->base_price ?? 50.00;

                    CartItem::create([
                        'cart_id' => $cart->id,
                        'menu_item_id' => $menuItem->id,
                        'menu_item_size_id' => $size?->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => $quantity * $unitPrice,
                        'special_instructions' => null,
                    ]);
                }
            }
        }
    }
}
