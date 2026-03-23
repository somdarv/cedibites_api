<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\MenuAddOn;
use Illuminate\Database\Seeder;

class MenuAddOnSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Branch::all() as $branch) {
            MenuAddOn::updateOrCreate(
                ['branch_id' => $branch->id, 'slug' => 'drumsticks'],
                [
                    'name' => 'Drumsticks',
                    'price' => 12,
                    'is_per_piece' => true,
                    'display_order' => 1,
                    'is_active' => true,
                ]
            );

            MenuAddOn::updateOrCreate(
                ['branch_id' => $branch->id, 'slug' => 'charcoal-grilled-tilapia'],
                [
                    'name' => 'Charcoal Grilled Tilapia',
                    'price' => 60,
                    'is_per_piece' => false,
                    'display_order' => 2,
                    'is_active' => true,
                ]
            );
        }
    }
}
