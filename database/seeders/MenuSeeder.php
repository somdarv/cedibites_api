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
                        ['name' => 'Plain', 'price' => 65.00],
                        ['name' => 'Assorted', 'price' => 85.00],
                        ['name' => 'Seafood', 'price' => 105.00],
                    ],
                ],
                [
                    'name' => 'Fried Rice',
                    'description' => 'Mixed vegetable fried rice — served with free coleslaw',
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Plain', 'price' => 60.00],
                        ['name' => 'Assorted', 'price' => 80.00],
                        ['name' => 'Seafood', 'price' => 100.00],
                    ],
                ],
                [
                    'name' => 'Noodles',
                    'description' => 'Noodles — served with free coleslaw',
                    'sizes' => [
                        ['name' => 'Assorted', 'price' => 80.00],
                        ['name' => 'Seafood', 'price' => 100.00],
                    ],
                ],
                [
                    'name' => 'Banku',
                    'description' => 'Fermented corn & cassava dough with grilled tilapia',
                    'sizes' => [
                        ['name' => 'Grilled Tilapia', 'price' => 110.00],
                    ],
                ],
            ],
            'meat-bites' => [
                [
                    'name' => 'Drumsticks',
                    'description' => 'Crispy chicken drumsticks',
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Special Crunch - 5 pieces', 'price' => 65.00],
                        ['name' => 'Special Crunch - 10 pieces', 'price' => 110.00],
                        ['name' => 'Juicy Fried - 5 pieces', 'price' => 65.00],
                        ['name' => 'Juicy Fried - 10 pieces', 'price' => 110.00],
                    ],
                ],
                [
                    'name' => 'Rotisserie Grilled',
                    'description' => 'Perfectly seasoned and roasted chicken',
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Full', 'price' => 170.00],
                        ['name' => 'Half Cut', 'price' => 90.00],
                    ],
                ],
            ],
            'combos' => [
                [
                    'name' => 'Fried Rice / Jollof + 3 Drums',
                    'description' => '"For the street" — Choose Fried Rice or Jollof with 3 drumsticks',
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Fried Rice', 'price' => 90.00],
                        ['name' => 'Jollof', 'price' => 95.00],
                    ],
                ],
                [
                    'name' => 'Assorted Fried Rice / Jollof / Noodles + 3 Drums',
                    'description' => '"For the street" — Assorted Fried Rice, Jollof, or Noodles with 3 drumsticks',
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Fried Rice', 'price' => 110.00],
                        ['name' => 'Jollof', 'price' => 115.00],
                        ['name' => 'Noodles', 'price' => 110.00],
                    ],
                ],
                [
                    'name' => 'Fried Rice / Jollof + 7 Drums + Kɔkɔɔ',
                    'description' => '"Big budget meal" — Choose Fried Rice or Jollof with 7 drumsticks and kɔkɔɔ',
                    'sizes' => [
                        ['name' => 'Fried Rice', 'price' => 145.00],
                        ['name' => 'Jollof', 'price' => 150.00],
                    ],
                ],
                [
                    'name' => 'Assorted Fried Rice / Jollof / Noodles + 7 Drums + Kɔkɔɔ',
                    'description' => '"Big budget meal" — Assorted Fried Rice, Jollof, or Noodles with 7 drumsticks and kɔkɔɔ',
                    'sizes' => [
                        ['name' => 'Fried Rice', 'price' => 165.00],
                        ['name' => 'Jollof', 'price' => 150.00],
                        ['name' => 'Noodles', 'price' => 165.00],
                    ],
                ],
                [
                    'name' => 'Assorted Fried Rice / Jollof / Noodles + Full Chicken + Kɔkɔɔ',
                    'description' => '"Big budget meal" — Assorted Fried Rice, Jollof, or Noodles with a full chicken and kɔkɔɔ',
                    'sizes' => [
                        ['name' => 'Fried Rice', 'price' => 250.00],
                        ['name' => 'Jollof', 'price' => 255.00],
                        ['name' => 'Noodles', 'price' => 250.00],
                    ],
                ],
            ],
            'soft-bites' => [
                [
                    'name' => 'Cedi Wraps',
                    'description' => 'Cedi wraps with your choice of filling',
                    'is_popular' => true,
                    'sizes' => [
                        ['name' => 'Chicken', 'price' => 60.00],
                        ['name' => 'Beef', 'price' => 60.00],
                        ['name' => 'Mix', 'price' => 70.00],
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
            $displayNameMap = self::displayNames()[$slug] ?? [];

            $expectedOptionKeys = [];
            foreach ($sizes as $sizeData) {
                $expectedOptionKeys[] = Str::slug($sizeData['name']);
            }

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
                        'display_name' => $displayNameMap[$key] ?? null,
                        'price' => $sizeData['price'],
                        'display_order' => $index,
                        'is_available' => true,
                    ]
                );
            }

            // Ensure we don't leave old/incorrect options behind when prices/options change.
            if ($expectedOptionKeys !== []) {
                MenuItemOption::query()
                    ->where('menu_item_id', $item->id)
                    ->whereNotIn('option_key', $expectedOptionKeys)
                    ->update(['is_available' => false]);
            }
        }
    }

    /** @return array<string, array<string, string>> item slug → option key → display name */
    private static function displayNames(): array
    {
        return [
            'jollof' => [
                'plain' => 'Plain Jollof',
                'assorted' => 'Assorted Jollof',
                'seafood' => 'Seafood Jollof',
            ],
            'fried-rice' => [
                'plain' => 'Plain Fried Rice',
                'assorted' => 'Assorted Fried Rice',
                'seafood' => 'Seafood Fried Rice',
            ],
            'noodles' => [
                'assorted' => 'Assorted Noodles',
                'seafood' => 'Seafood Noodles',
            ],
            'banku' => [
                'grilled-tilapia' => 'Banku with Grilled Tilapia',
            ],
            'drumsticks' => [
                'special-crunch-5-pieces' => 'Special Crunch Drumsticks (5 pcs)',
                'special-crunch-10-pieces' => 'Special Crunch Drumsticks (10 pcs)',
                'juicy-fried-5-pieces' => 'Juicy Fried Drumsticks (5 pcs)',
                'juicy-fried-10-pieces' => 'Juicy Fried Drumsticks (10 pcs)',
            ],
            'rotisserie-grilled' => [
                'full' => 'Full Rotisserie Grilled Chicken',
                'half-cut' => 'Half Cut Rotisserie Grilled Chicken',
            ],
            'fried-rice-jollof-3-drums' => [
                'fried-rice' => 'Fried Rice + 3 Drumsticks',
                'jollof' => 'Jollof + 3 Drumsticks',
            ],
            'assorted-fried-rice-jollof-noodles-3-drums' => [
                'fried-rice' => 'Assorted Fried Rice + 3 Drumsticks',
                'jollof' => 'Assorted Jollof + 3 Drumsticks',
                'noodles' => 'Assorted Noodles + 3 Drumsticks',
            ],
            'fried-rice-jollof-7-drums-kk' => [
                'fried-rice' => 'Fried Rice + 7 Drumsticks + Kɔkɔɔ',
                'jollof' => 'Jollof + 7 Drumsticks + Kɔkɔɔ',
            ],
            'assorted-fried-rice-jollof-noodles-7-drums-kk' => [
                'fried-rice' => 'Assorted Fried Rice + 7 Drumsticks + Kɔkɔɔ',
                'jollof' => 'Assorted Jollof + 7 Drumsticks + Kɔkɔɔ',
                'noodles' => 'Assorted Noodles + 7 Drumsticks + Kɔkɔɔ',
            ],
            'assorted-fried-rice-jollof-noodles-full-chicken-kk' => [
                'fried-rice' => 'Assorted Fried Rice + Full Chicken + Kɔkɔɔ',
                'jollof' => 'Assorted Jollof + Full Chicken + Kɔkɔɔ',
                'noodles' => 'Assorted Noodles + Full Chicken + Kɔkɔɔ',
            ],
            'cedi-wraps' => [
                'chicken' => 'Chicken Cedi Wrap',
                'beef' => 'Beef Cedi Wrap',
                'mix' => 'Mix Cedi Wrap',
            ],
        ];
    }
}
