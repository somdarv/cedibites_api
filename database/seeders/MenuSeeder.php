<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();

        foreach ($branches as $branch) {
            $this->createCategoriesAndItems($branch);
        }
    }

    private function createCategoriesAndItems(Branch $branch): void
    {
        $categories = [
            ['name' => 'Basic Meals', 'slug' => 'basic-meals', 'display_order' => 1],
            ['name' => 'Budget Bowls', 'slug' => 'budget-bowls', 'display_order' => 2],
            ['name' => 'Combos', 'slug' => 'combos', 'display_order' => 3],
            ['name' => 'Top Ups', 'slug' => 'top-ups', 'display_order' => 4],
            ['name' => 'Drinks', 'slug' => 'drinks', 'display_order' => 5],
        ];

        foreach ($categories as $categoryData) {
            $category = MenuCategory::updateOrCreate(
                ['branch_id' => $branch->id, 'slug' => $categoryData['slug']],
                array_merge($categoryData, ['is_active' => true])
            );

            $this->createMenuItems($branch, $category);
        }
    }

    private function createMenuItems(Branch $branch, MenuCategory $category): void
    {
        $menuItems = match ($category->slug) {
            'basic-meals' => [
                [
                    'name' => 'Jollof Rice with Chicken Drumsticks',
                    'description' => 'Smoky party jollof rice with chicken drumsticks',
                    'base_price' => null,
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Plain', 'price' => 65.00],
                        ['name' => 'Assorted', 'price' => 85.00],
                    ],
                ],
                [
                    'name' => 'Fried Rice with Chicken Drumsticks',
                    'description' => 'Mixed vegetable fried rice with chicken drumsticks',
                    'base_price' => null,
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Plain', 'price' => 65.00],
                        ['name' => 'Assorted', 'price' => 85.00],
                    ],
                ],
                [
                    'name' => 'Banku with Tilapia',
                    'description' => 'Fermented corn & cassava dough with grilled tilapia',
                    'base_price' => 60.00,
                    'is_popular' => true,
                ],
                [
                    'name' => 'Assorted Noodles with Chicken Drumsticks',
                    'description' => 'Assorted noodles with mixed proteins and chicken drumsticks',
                    'base_price' => 90.00,
                ],
            ],
            'budget-bowls' => [
                [
                    'name' => 'Jollof Bowl',
                    'description' => 'Plain jollof rice bowl - perfect for a quick meal',
                    'base_price' => null,
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Small', 'price' => 60.00],
                        ['name' => 'Large', 'price' => 90.00],
                    ],
                ],
                [
                    'name' => 'Fried Rice Bowl',
                    'description' => 'Plain fried rice bowl - quick and satisfying',
                    'base_price' => null,
                    'sizes' => [
                        ['name' => 'Small', 'price' => 60.00],
                        ['name' => 'Large', 'price' => 90.00],
                    ],
                ],
                [
                    'name' => 'Assorted Jollof Bowl',
                    'description' => 'Jollof rice with mixed proteins',
                    'base_price' => null,
                    'sizes' => [
                        ['name' => 'Small', 'price' => 60.00],
                        ['name' => 'Large', 'price' => 90.00],
                    ],
                ],
                [
                    'name' => 'Assorted Fried Rice Bowl',
                    'description' => 'Fried rice with mixed proteins',
                    'base_price' => null,
                    'sizes' => [
                        ['name' => 'Small', 'price' => 60.00],
                        ['name' => 'Large', 'price' => 90.00],
                    ],
                ],
            ],
            'combos' => [
                [
                    'name' => 'Banku × Charcoal Grilled Tilapia',
                    'description' => 'Fermented banku with whole charcoal grilled tilapia & garden eggs pepper',
                    'base_price' => 120.00,
                    'is_popular' => true,
                ],
                [
                    'name' => 'Street Budget: FR/Jollof + 3 Drumsticks',
                    'description' => 'Choose Fried Rice or Jollof Rice with 3 drumsticks',
                    'base_price' => 99.00,
                    'is_popular' => true,
                ],
                [
                    'name' => 'Street Budget: Assorted + 3 Drumsticks',
                    'description' => 'Assorted Noodles, Fried Rice, or Jollof with 3 Drumsticks',
                    'base_price' => 119.00,
                    'is_popular' => true,
                ],
                [
                    'name' => 'Big Budget: FR/Jollof + 5 Drumsticks',
                    'description' => 'Choose Fried Rice or Jollof Rice with 5 drumsticks',
                    'base_price' => 129.00,
                ],
            ],
            'top-ups' => [
                [
                    'name' => 'Rotisserie Chicken',
                    'description' => 'Perfectly seasoned and roasted chicken',
                    'base_price' => null,
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Full', 'price' => 300.00],
                        ['name' => 'Half', 'price' => 160.00],
                        ['name' => 'Quarter', 'price' => 90.00],
                    ],
                ],
                [
                    'name' => 'Chicken Basket - 10 Drumsticks',
                    'description' => '10 crispy chicken drumsticks - perfect for sharing',
                    'base_price' => 110.00,
                ],
                [
                    'name' => 'Chicken Basket - 15 Drumsticks',
                    'description' => '15 crispy chicken drumsticks - party size!',
                    'base_price' => 150.00,
                ],
            ],
            'drinks' => [
                [
                    'name' => 'Sobolo',
                    'description' => 'Chilled hibiscus flower drink with ginger & cloves',
                    'base_price' => null,
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => '350ml', 'price' => 15.00],
                        ['name' => '500ml', 'price' => 22.00],
                    ],
                ],
                [
                    'name' => 'Asaana',
                    'description' => 'Fermented roasted corn drink — sweet, tangy & refreshing',
                    'base_price' => null,
                    'sizes' => [
                        ['name' => '350ml', 'price' => 15.00],
                        ['name' => '500ml', 'price' => 22.00],
                    ],
                ],
                [
                    'name' => 'Pineapple Ginger Juice',
                    'description' => 'Freshly blended pineapple with Ghanaian ginger & lime',
                    'base_price' => null,
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => '350ml', 'price' => 20.00],
                        ['name' => '500ml', 'price' => 28.00],
                    ],
                ],
                [
                    'name' => 'Bottled Water',
                    'description' => 'Pure chilled still water',
                    'base_price' => null,
                    'sizes' => [
                        ['name' => '500ml', 'price' => 7.00],
                        ['name' => '1L', 'price' => 12.00],
                    ],
                ],
            ],
            default => [],
        };

        foreach ($menuItems as $itemData) {
            $sizes = $itemData['sizes'] ?? [];
            unset($itemData['sizes']);

            $slug = Str::slug($itemData['name']);

            $item = MenuItem::updateOrCreate(
                ['branch_id' => $branch->id, 'slug' => $slug],
                array_merge($itemData, [
                    'category_id' => $category->id,
                    'is_available' => true,
                ])
            );

            foreach ($sizes as $index => $sizeData) {
                MenuItemSize::updateOrCreate(
                    ['menu_item_id' => $item->id, 'name' => $sizeData['name']],
                    array_merge($sizeData, [
                        'size_order' => $index,
                        'is_available' => true,
                    ])
                );
            }
        }
    }
}
