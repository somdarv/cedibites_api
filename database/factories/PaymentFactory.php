<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'customer_id' => Customer::factory(),
            'payment_method' => fake()->randomElement(['momo', 'cash_delivery', 'cash_pickup']),
            'payment_status' => fake()->randomElement(['pending', 'completed', 'failed']),
            'amount' => fake()->randomFloat(2, 50, 200),
            'transaction_id' => fake()->optional()->uuid(),
            'payment_gateway_response' => null,
            'paid_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'refunded_at' => null,
            'refund_reason' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'completed',
            'paid_at' => now(),
        ]);
    }
}
