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
            ['name' => 'Basic Meals', 'display_order' => 1],
            ['name' => 'Rice Dishes', 'display_order' => 2],
            ['name' => 'Grilled Items', 'display_order' => 3],
            ['name' => 'Soups & Stews', 'display_order' => 4],
            ['name' => 'Drinks', 'display_order' => 5],
            ['name' => 'Desserts', 'display_order' => 6],
            ['name' => 'Snacks', 'display_order' => 7],
        ];

        foreach ($branches as $branch) {
            foreach ($categories as $categoryData) {
                MenuCategory::firstOrCreate([
                    'branch_id' => $branch->id,
                    'name' => $categoryData['name'],
                ], [
                    'slug' => \Str::slug($categoryData['name']),
                    'display_order' => $categoryData['display_order'],
                    'is_active' => true,
                ]);
            }
        }
    }
}
