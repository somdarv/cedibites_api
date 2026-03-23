<?php

namespace Database\Factories;

use App\Models\MenuTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MenuTag>
 */
class MenuTagFactory extends Factory
{
    protected $model = MenuTag::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'name' => ucfirst($name),
            'display_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
