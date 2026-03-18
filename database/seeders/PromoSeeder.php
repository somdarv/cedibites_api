<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\Promo;
use Illuminate\Database\Seeder;

class PromoSeeder extends Seeder
{
    public function run(): void
    {
        $this->createTenPercentOffOrdersOver100();
        $this->createFifteenOffJollofFriedRice();
        $this->createTwentyPercentOffNewCustomers();
    }

    private function createTenPercentOffOrdersOver100(): void
    {
        $promo = Promo::updateOrCreate(
            ['name' => '10% off orders over ₵100'],
            [
                'type' => 'percentage',
                'value' => 10,
                'scope' => 'global',
                'applies_to' => 'order',
                'min_order_value' => 100,
                'max_order_value' => null,
                'max_discount' => null,
                'start_date' => now()->subDays(7),
                'end_date' => now()->addMonths(3),
                'is_active' => true,
                'accounting_code' => 'PROMO-10PCT',
            ]
        );
    }

    private function createFifteenOffJollofFriedRice(): void
    {
        $eastLegon = Branch::where('name', 'East Legon')->first();
        if (! $eastLegon) {
            return;
        }

        $jollofFriedRiceItems = MenuItem::where('branch_id', $eastLegon->id)
            ->where(function ($q) {
                $q->where('name', 'like', '%Jollof%')
                    ->orWhere('name', 'like', '%Fried Rice%');
            })
            ->pluck('id')
            ->toArray();

        $promo = Promo::updateOrCreate(
            ['name' => '₵15 off Jollof/Fried Rice'],
            [
                'type' => 'fixed_amount',
                'value' => 15,
                'scope' => 'branch',
                'applies_to' => 'items',
                'min_order_value' => null,
                'max_order_value' => null,
                'max_discount' => 15,
                'start_date' => now()->subDays(7),
                'end_date' => now()->addMonths(2),
                'is_active' => true,
                'accounting_code' => 'PROMO-JFR15',
            ]
        );

        $promo->branches()->sync([$eastLegon->id]);
        if (! empty($jollofFriedRiceItems)) {
            $promo->menuItems()->sync($jollofFriedRiceItems);
        }
    }

    private function createTwentyPercentOffNewCustomers(): void
    {
        Promo::updateOrCreate(
            ['name' => '20% off for new customers'],
            [
                'type' => 'percentage',
                'value' => 20,
                'scope' => 'global',
                'applies_to' => 'order',
                'min_order_value' => 50,
                'max_order_value' => null,
                'max_discount' => null,
                'start_date' => now()->subDays(7),
                'end_date' => now()->addMonths(6),
                'is_active' => true,
                'accounting_code' => 'PROMO-NEW20',
            ]
        );
    }
}
