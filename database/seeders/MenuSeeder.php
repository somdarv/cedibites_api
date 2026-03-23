<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemOption;
use App\Models\MenuTag;
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
            ['name' => 'Main delights', 'slug' => 'main-delights', 'display_order' => 1],
            ['name' => 'Meat bites', 'slug' => 'meat-bites', 'display_order' => 2],
            ['name' => 'Combos', 'slug' => 'combos', 'display_order' => 3],
            ['name' => 'Soft bites', 'slug' => 'soft-bites', 'display_order' => 4],
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
            'main-delights' => [
                [
                    'name' => 'Jollof',
                    'description' => 'Smoky party jollof rice — served with free coleslaw',
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Plain', 'price' => 0.00],
                        ['name' => 'Assorted', 'price' => 0.00],
                        ['name' => 'Seafood', 'price' => 0.00],
                    ],
                ],
                [
                    'name' => 'Fried Rice',
                    'description' => 'Mixed vegetable fried rice — served with free coleslaw',
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Plain', 'price' => 0.00],
                        ['name' => 'Assorted', 'price' => 0.00],
                        ['name' => 'Seafood', 'price' => 0.00],
                    ],
                ],
                [
                    'name' => 'Noodles',
                    'description' => 'Noodles — served with free coleslaw',
                    'sizes' => [
                        ['name' => 'Assorted', 'price' => 0.00],
                        ['name' => 'Seafood', 'price' => 0.00],
                    ],
                ],
                [
                    'name' => 'Banku',
                    'description' => 'Fermented corn & cassava dough with grilled tilapia',
                    'sizes' => [
                        ['name' => 'Grilled Tilapia', 'price' => 0.00],
                    ],
                ],
            ],
            'meat-bites' => [
                [
                    'name' => 'Drumsticks',
                    'description' => 'Crispy chicken drumsticks',
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Special Crunch', 'price' => 0.00],
                        ['name' => 'Juicy Fried', 'price' => 0.00],
                    ],
                ],
                [
                    'name' => 'Rotisserie Grilled',
                    'description' => 'Perfectly seasoned and roasted chicken',
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Full', 'price' => 0.00],
                        ['name' => 'Half Cut', 'price' => 0.00],
                    ],
                ],
            ],
            'combos' => [
                [
                    'name' => 'Fried Rice / Jollof + 3 Drums',
                    'description' => '"For the street" — Choose Fried Rice or Jollof with 3 drumsticks',
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Fried Rice', 'price' => 0.00],
                        ['name' => 'Jollof', 'price' => 0.00],
                    ],
                ],
                [
                    'name' => 'Assorted Fried Rice / Jollof / Noodles + 3 Drums',
                    'description' => '"For the street" — Assorted Fried Rice, Jollof, or Noodles with 3 drumsticks',
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Fried Rice', 'price' => 0.00],
                        ['name' => 'Jollof', 'price' => 0.00],
                        ['name' => 'Noodles', 'price' => 0.00],
                    ],
                ],
                [
                    'name' => 'Fried Rice / Jollof + 7 Drums + Kɔkɔɔ',
                    'description' => '"Big budget meal" — Choose Fried Rice or Jollof with 7 drumsticks and kɔkɔɔ',
                    'sizes' => [
                        ['name' => 'Fried Rice', 'price' => 0.00],
                        ['name' => 'Jollof', 'price' => 0.00],
                    ],
                ],
                [
                    'name' => 'Assorted Fried Rice / Jollof / Noodles + 7 Drums + Kɔkɔɔ',
                    'description' => '"Big budget meal" — Assorted Fried Rice, Jollof, or Noodles with 7 drumsticks and kɔkɔɔ',
                    'sizes' => [
                        ['name' => 'Fried Rice', 'price' => 0.00],
                        ['name' => 'Jollof', 'price' => 0.00],
                        ['name' => 'Noodles', 'price' => 0.00],
                    ],
                ],
                [
                    'name' => 'Assorted Fried Rice / Jollof / Noodles + Full Chicken + Kɔkɔɔ',
                    'description' => '"Big budget meal" — Assorted Fried Rice, Jollof, or Noodles with a full chicken and kɔkɔɔ',
                    'sizes' => [
                        ['name' => 'Fried Rice', 'price' => 0.00],
                        ['name' => 'Jollof', 'price' => 0.00],
                        ['name' => 'Noodles', 'price' => 0.00],
                    ],
                ],
            ],
            'soft-bites' => [
                [
                    'name' => 'Cedi Wraps',
                    'description' => 'Cedi wraps with your choice of filling',
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Chicken', 'price' => 0.00],
                        ['name' => 'Beef', 'price' => 0.00],
                        ['name' => 'Mix', 'price' => 0.00],
                    ],
                ],
            ],
            default => [],
        };

        $popularTag = MenuTag::query()->where('slug', 'popular')->first();

        foreach ($menuItems as $itemData) {
            $sizes = $itemData['sizes'] ?? [];
            $isPopular = (bool) ($itemData['is_popular'] ?? false);
            unset($itemData['sizes'], $itemData['is_popular']);

            $slug = Str::slug($itemData['name']);

            $item = MenuItem::updateOrCreate(
                ['branch_id' => $branch->id, 'slug' => $slug],
                array_merge($itemData, [
                    'category_id' => $category->id,
                    'is_available' => true,
                ])
            );

            if ($popularTag && $isPopular) {
                $item->tags()->syncWithoutDetaching([$popularTag->id]);
            }

            foreach ($sizes as $index => $sizeData) {
                $key = Str::slug($sizeData['name']);
                MenuItemOption::updateOrCreate(
                    ['menu_item_id' => $item->id, 'option_key' => $key],
                    [
                        'option_label' => $sizeData['name'],
                        'price' => $sizeData['price'],
                        'display_order' => $index,
                        'is_available' => true,
                    ]
                );
            }
        }
    }
}
