<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::all();
        $branches = Branch::all();

        foreach ($customers as $customer) {
            $orderCount = rand(1, 3);

            for ($i = 0; $i < $orderCount; $i++) {
                $this->createOrder($customer, $branches->random());
            }
        }
    }

    private function createOrder(Customer $customer, Branch $branch): void
    {
        $subtotal = fake()->randomFloat(2, 80, 250);
        $deliverySetting = $branch->activeDeliverySetting();
        $deliveryFee = $deliverySetting ? $deliverySetting->base_delivery_fee : 0;
        $taxRate = 0.025;
        $taxAmount = $subtotal * $taxRate;
        $total = $subtotal + $deliveryFee + $taxAmount;

        // For guest customers, generate contact info; for registered customers, use user info
        if ($customer->is_guest) {
            $contactName = fake()->name();
            $contactPhone = '+233'.fake()->numerify('#########');
        } else {
            $contactName = $customer->user->name;
            $contactPhone = $customer->user->phone;
        }

        $order = Order::create([
            'order_number' => 'CB'.fake()->unique()->numberBetween(100000, 999999),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'assigned_employee_id' => $branch->employees()->inRandomOrder()->first()?->id,
            'order_type' => fake()->randomElement(['delivery', 'pickup']),
            'order_source' => fake()->randomElement(['online', 'phone', 'whatsapp', 'instagram']),
            'delivery_address' => $customer->addresses()->first()->full_address ?? fake()->address(),
            'delivery_latitude' => fake()->latitude(5.5, 5.7),
            'delivery_longitude' => fake()->longitude(-0.3, -0.1),
            'contact_name' => $contactName,
            'contact_phone' => $contactPhone,
            'delivery_note' => fake()->optional()->sentence(),
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $total,
            'status' => fake()->randomElement(['received', 'preparing', 'delivered', 'completed']),
            'estimated_prep_time' => fake()->numberBetween(20, 40),
            'estimated_delivery_time' => now()->addMinutes(45),
        ]);

        // Create order items
        $menuItems = $branch->menuItems()->inRandomOrder()->limit(rand(2, 4))->get();

        foreach ($menuItems as $menuItem) {
            $size = $menuItem->sizes()->inRandomOrder()->first();
            $quantity = rand(1, 3);
            $unitPrice = $size?->price ?? $menuItem->base_price ?? 50.00;

            OrderItem::create([
                'order_id' => $order->id,
                'menu_item_id' => $menuItem->id,
                'menu_item_size_id' => $size?->id,
                'menu_item_snapshot' => [
                    'name' => $menuItem->name,
                    'description' => $menuItem->description,
                ],
                'menu_item_size_snapshot' => $size ? [
                    'name' => $size->name,
                    'price' => $size->price,
                ] : null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $quantity * $unitPrice,
                'special_instructions' => fake()->optional()->sentence(),
            ]);
        }

        // Create status history
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'received',
            'changed_by_type' => 'system',
            'changed_at' => $order->created_at,
        ]);

        // Create payment
        Payment::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'payment_method' => fake()->randomElement(['mobile_money', 'cash']),
            'payment_status' => 'completed',
            'amount' => $total,
            'transaction_id' => 'TXN'.fake()->unique()->numerify('##########'),
            'paid_at' => now(),
        ]);
    }
}
