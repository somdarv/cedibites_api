<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 50, 200);
        $deliveryFee = fake()->randomFloat(2, 10, 20);
        $serviceCharge = round($subtotal * 0.025, 2);
        $total = $subtotal + $deliveryFee + $serviceCharge;

        return [
            'order_number' => 'CB'.fake()->unique()->numberBetween(100000, 999999),
            'customer_id' => Customer::factory(),
            'branch_id' => Branch::factory(),
            'assigned_employee_id' => Employee::factory(),
            'order_type' => fake()->randomElement(['delivery', 'pickup']),
            'order_source' => fake()->randomElement(['online', 'phone', 'whatsapp', 'instagram', 'facebook', 'pos']),
            'delivery_address' => fake()->streetAddress().', Accra',
            'delivery_latitude' => fake()->latitude(5.5, 5.7),
            'delivery_longitude' => fake()->longitude(-0.3, -0.1),
            'contact_name' => fake()->name(),
            'contact_phone' => '+233'.fake()->numerify('#########'),
            'delivery_note' => fake()->optional()->sentence(),
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'service_charge' => $serviceCharge,
            'total_amount' => $total,
            'status' => fake()->randomElement(['received', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'completed']),
            'estimated_prep_time' => fake()->numberBetween(15, 45),
            'estimated_delivery_time' => fake()->dateTimeBetween('now', '+2 hours'),
            'actual_delivery_time' => null,
            'cancelled_at' => null,
            'cancelled_reason' => null,
        ];
    }
}
