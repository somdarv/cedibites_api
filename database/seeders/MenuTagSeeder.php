<?php

namespace Database\Seeders;

use App\Models\MenuTag;
use Illuminate\Database\Seeder;

class MenuTagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['slug' => 'popular', 'name' => 'Popular', 'display_order' => 1],
            ['slug' => 'new', 'name' => 'New', 'display_order' => 2],
            ['slug' => 'spicy', 'name' => 'Spicy', 'display_order' => 3],
            ['slug' => 'vegetarian', 'name' => 'Vegetarian', 'display_order' => 4],
        ];

        foreach ($tags as $tag) {
            MenuTag::updateOrCreate(
                ['slug' => $tag['slug']],
                array_merge($tag, ['is_active' => true])
            );
        }
    }
}
