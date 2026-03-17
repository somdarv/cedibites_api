<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Shift;
use App\Models\ShiftOrder;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();

        foreach ($branches as $branch) {
            $this->createActiveShiftsForBranch($branch);
        }
    }

    private function createActiveShiftsForBranch(Branch $branch): void
    {
        $employees = $branch->employees()->inRandomOrder()->limit(2)->get();

        foreach ($employees as $employee) {
            $existing = Shift::where('employee_id', $employee->id)
                ->where('branch_id', $branch->id)
                ->whereNull('logout_at')
                ->first();

            if (! $existing) {
                Shift::create([
                    'employee_id' => $employee->id,
                    'branch_id' => $branch->id,
                    'login_at' => now()->subHours(rand(1, 5)),
                    'logout_at' => null,
                    'total_sales' => fake()->randomFloat(2, 0, 300),
                    'order_count' => fake()->numberBetween(0, 8),
                ]);
            }
        }

        $this->attachOrdersToShifts($branch);
    }

    private function attachOrdersToShifts(Branch $branch): void
    {
        $activeShifts = Shift::where('branch_id', $branch->id)
            ->whereNull('logout_at')
            ->get();

        $orders = Order::where('branch_id', $branch->id)
            ->whereIn('status', ['delivered', 'completed'])
            ->inRandomOrder()
            ->limit(min(5, $activeShifts->count() * 3))
            ->get();

        foreach ($activeShifts as $shift) {
            $ordersToAttach = $orders->take(min(2, $orders->count()));
            foreach ($ordersToAttach as $order) {
                ShiftOrder::firstOrCreate(
                    [
                        'shift_id' => $shift->id,
                        'order_id' => $order->id,
                    ],
                    ['order_total' => $order->total_amount]
                );
            }
        }
    }
}
