<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\MenuCategory;
use Illuminate\Database\Seeder;

class MenuCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = Branch::all();

        $categories = [
            ['name' => 'Main delights', 'display_order' => 1],
            ['name' => 'Meat bites', 'display_order' => 2],
            ['name' => 'Combos', 'display_order' => 3],
            ['name' => 'Soft bites', 'display_order' => 4],
        ];

        foreach ($branches as $branch) {
            foreach ($categories as $categoryData) {
                MenuCategory::firstOrCreate([
                    'branch_id' => $branch->id,
                    'slug' => \Str::slug($categoryData['name']),
                ], [
                    'name' => $categoryData['name'],
                    'display_order' => $categoryData['display_order'],
                    'is_active' => true,
                ]);
            }
        }
    }
}
