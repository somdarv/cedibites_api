<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchDeliverySetting;
use App\Models\BranchOperatingHour;
use App\Models\BranchOrderType;
use App\Models\BranchPaymentMethod;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Ashaiman',
                'area' => 'Ashaiman',
                'address' => 'Nii Tetteh Amui Street, Tema',
                'phone' => '+233548162282',
                'email' => 'info@cedibites.com',
                'latitude' => 5.60370000,
                'longitude' => -0.18700000,
                'delivery' => [
                    'base_delivery_fee' => 15.00,
                    'per_km_fee' => 3.00,
                    'delivery_radius_km' => 10.0,
                    'min_order_value' => 50.00,
                    'estimated_delivery_time' => '30-45 mins',
                ],
            ],
        ];

        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $orderTypes = ['delivery', 'pickup', 'dine_in'];
        $paymentMethods = ['momo', 'cash_on_delivery'];

        foreach ($branches as $branchData) {
            $deliveryData = $branchData['delivery'];
            unset($branchData['delivery']);

            $branch = Branch::updateOrCreate(
                ['name' => $branchData['name']],
                array_merge($branchData, ['is_active' => true])
            );

            // Create operating hours (8 AM - 10 PM daily)
            foreach ($daysOfWeek as $day) {
                BranchOperatingHour::updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'day_of_week' => $day,
                    ],
                    [
                        'is_open' => true,
                        'open_time' => '08:00:00',
                        'close_time' => '22:00:00',
                    ]
                );
            }

            // Create delivery settings
            BranchDeliverySetting::updateOrCreate(
                ['branch_id' => $branch->id, 'is_active' => true],
                array_merge($deliveryData, [
                    'effective_from' => now(),
                    'effective_until' => null,
                ])
            );

            // Create order types
            foreach ($orderTypes as $type) {
                BranchOrderType::updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'order_type' => $type,
                    ],
                    [
                        'is_enabled' => $type !== 'dine_in', // Enable delivery and pickup, disable dine-in
                        'metadata' => $type === 'dine_in' ? ['capacity' => 0] : null,
                    ]
                );
            }

            // Create payment methods
            foreach ($paymentMethods as $method) {
                BranchPaymentMethod::updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'payment_method' => $method,
                    ],
                    [
                        'is_enabled' => true,
                        'metadata' => null,
                    ]
                );
            }
        }
    }
}
