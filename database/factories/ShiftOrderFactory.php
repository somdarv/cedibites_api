<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftOrderFactory extends Factory
{
    public function definition(): array
    {
        $orderTotal = fake()->randomFloat(2, 20, 200);

        return [
            'shift_id' => Shift::factory(),
            'order_id' => Order::factory(),
            'order_total' => $orderTotal,
        ];
    }
}
