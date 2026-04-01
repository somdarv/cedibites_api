<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Services\OrderNumberService;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    private OrderNumberService $orderNumberService;

    public function run(): void
    {
        $customers = Customer::all();
        $branches = Branch::all();
        $this->orderNumberService = new OrderNumberService;

        foreach ($customers as $customer) {
            $orderCount = rand(1, 3);

            for ($i = 0; $i < $orderCount; $i++) {
                $this->createOrder($customer, $branches->random());
            }
        }
    }

    private function createOrder(Customer $customer, Branch $branch): void
    {
        // Prices are tax-inclusive (Ghana GRA: VAT 15% + NHIL 2.5% + GETFund 2.5% = 20%)
        // Tax is back-calculated: tax = subtotal × (rate / (1 + rate))
        $subtotal = 150.00;
        $deliverySetting = $branch->activeDeliverySetting();
        $deliveryFee = $deliverySetting ? $deliverySetting->base_delivery_fee : 0;
        $taxRate = 0.20;
        $taxAmount = round($subtotal * ($taxRate / (1 + $taxRate)), 2);
        $total = $subtotal + $deliveryFee;

        $orderTypes = ['delivery', 'pickup'];
        $orderSources = ['online', 'phone', 'whatsapp', 'instagram'];
        $statuses = ['received', 'preparing', 'delivered', 'completed'];

        // For guest customers, generate contact info; for registered customers, use user info
        if ($customer->is_guest) {
            $contactName = 'Guest Customer';
            $contactPhone = '+233000000000';
        } else {
            $contactName = $customer->user->name;
            $contactPhone = $customer->user->phone;
        }

        $order = Order::create([
            'order_number' => $this->orderNumberService->generate(),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'assigned_employee_id' => $branch->employees()->inRandomOrder()->first()?->id,
            'order_type' => $orderTypes[array_rand($orderTypes)],
            'order_source' => $orderSources[array_rand($orderSources)],
            'delivery_address' => $customer->addresses()->first()->full_address ?? 'Accra, Ghana',
            'delivery_latitude' => 5.6,
            'delivery_longitude' => -0.2,
            'contact_name' => $contactName,
            'contact_phone' => $contactPhone,
            'delivery_note' => null,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $total,
            'status' => $statuses[array_rand($statuses)],
            'estimated_prep_time' => 30,
            'estimated_delivery_time' => now()->addMinutes(45),
        ]);

        // Create order items
        $menuItems = $branch->menuItems()->inRandomOrder()->limit(rand(2, 4))->get();

        foreach ($menuItems as $menuItem) {
            $option = $menuItem->options()->inRandomOrder()->first();
            $quantity = rand(1, 3);
            $unitPrice = $option?->price ?? 50.00;

            OrderItem::create([
                'order_id' => $order->id,
                'menu_item_id' => $menuItem->id,
                'menu_item_option_id' => $option?->id,
                'menu_item_snapshot' => [
                    'id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'description' => $menuItem->description,
                ],
                'menu_item_option_snapshot' => $option ? [
                    'id' => $option->id,
                    'option_key' => $option->option_key,
                    'option_label' => $option->option_label,
                    'display_name' => $option->display_name,
                    'price' => (float) $option->price,
                ] : null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $quantity * $unitPrice,
                'special_instructions' => null,
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
            'payment_method' => 'mobile_money',
            'payment_status' => 'completed',
            'amount' => $total,
            'transaction_id' => 'TXN'.uniqid(),
            'paid_at' => now(),
        ]);
    }
}
