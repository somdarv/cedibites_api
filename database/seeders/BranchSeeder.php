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
                'name' => 'Accra Central',
                'area' => 'Central Accra',
                'address' => 'Ring Road Central, Accra',
                'phone' => '+233241234567',
                'email' => 'accra.central@cedibites.com',
                'latitude' => 5.5557,
                'longitude' => -0.1769,
                'delivery' => [
                    'base_delivery_fee' => 15.00,
                    'per_km_fee' => 3.00,
                    'delivery_radius_km' => 10.0,
                    'min_order_value' => 50.00,
                    'estimated_delivery_time' => '30-45 mins',
                ],
            ],
            [
                'name' => 'East Legon',
                'area' => 'East Legon',
                'address' => 'American House Junction, Accra',
                'phone' => '+233509876543',
                'email' => 'east.legon@cedibites.com',
                'latitude' => 5.6465,
                'longitude' => -0.1549,
                'delivery' => [
                    'base_delivery_fee' => 12.00,
                    'per_km_fee' => 2.50,
                    'delivery_radius_km' => 8.0,
                    'min_order_value' => 40.00,
                    'estimated_delivery_time' => '25-40 mins',
                ],
            ],
            [
                'name' => 'Labadi',
                'area' => 'Labadi',
                'address' => 'Labadi Road, Near Labadi Beach, Accra',
                'phone' => '+233205551234',
                'email' => 'labadi@cedibites.com',
                'latitude' => 5.6372,
                'longitude' => -0.0924,
                'delivery' => [
                    'base_delivery_fee' => 18.00,
                    'per_km_fee' => 3.50,
                    'delivery_radius_km' => 12.0,
                    'min_order_value' => 60.00,
                    'estimated_delivery_time' => '35-50 mins',
                ],
            ],
        ];

        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $orderTypes = ['delivery', 'pickup', 'dine_in'];
        $paymentMethods = ['momo', 'cash_on_delivery', 'cash_at_pickup'];

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
