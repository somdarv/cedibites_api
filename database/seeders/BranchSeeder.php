<?php

namespace Database\Seeders;

use App\Models\Branch;
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
                'latitude' => 5.5557,
                'longitude' => -0.1769,
                'delivery_fee' => 15.00,
                'delivery_radius_km' => 10.0,
                'estimated_delivery_time' => '30-45 mins',
            ],
            [
                'name' => 'East Legon',
                'area' => 'East Legon',
                'address' => 'American House Junction, Accra',
                'phone' => '+233509876543',
                'latitude' => 5.6465,
                'longitude' => -0.1549,
                'delivery_fee' => 12.00,
                'delivery_radius_km' => 8.0,
                'estimated_delivery_time' => '25-40 mins',
            ],
            [
                'name' => 'Labadi',
                'area' => 'Labadi',
                'address' => 'Labadi Road, Near Labadi Beach, Accra',
                'phone' => '+233205551234',
                'latitude' => 5.6372,
                'longitude' => -0.0924,
                'delivery_fee' => 18.00,
                'delivery_radius_km' => 12.0,
                'estimated_delivery_time' => '35-50 mins',
            ],
        ];

        foreach ($branches as $branchData) {
            Branch::updateOrCreate(
                ['name' => $branchData['name']],
                array_merge($branchData, [
                    'is_active' => true,
                    'operating_hours' => '8:00 AM - 10:00 PM',
                ])
            );
        }
    }
}
