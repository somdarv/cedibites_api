<?php

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->branch = Branch::factory()->create();
    $this->category = MenuCategory::factory()->create([
        'branch_id' => $this->branch->id,
    ]);
});

test('can create menu item', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/admin/menu-items', [
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'name' => 'Test Menu Item',
            'slug' => 'test-menu-item-'.time(),
            'description' => 'A test menu item',
            'base_price' => 25.50,
            'is_available' => true,
            'is_popular' => false,
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'slug',
                'description',
                'base_price',
                'is_available',
                'is_popular',
                'branch',
                'category',
            ],
        ]);

    expect($this->branch->menuItems()->where('name', 'Test Menu Item')->exists())->toBeTrue();
});

test('can get menu categories', function () {
    $response = $this->getJson('/api/v1/menu-categories');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'is_active',
                    'menu_items_count',
                ],
            ],
        ]);
});

test('can bulk import menu items', function () {
    $csvContent = "name,category,description,price,is_available,is_popular\n";
    $csvContent .= "Jollof Rice,Basic Meals,Delicious rice,75,true,false\n";
    $csvContent .= "Fried Rice,Basic Meals,Tasty fried rice,65,true,true\n";

    $file = UploadedFile::fake()->createWithContent('menu-items.csv', $csvContent);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/admin/menu-items/bulk-import', [
            'csv_file' => $file,
            'branch_id' => $this->branch->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'imported',
                'errors',
            ],
        ]);

    expect($this->branch->menuItems()->where('name', 'Jollof Rice')->exists())->toBeTrue();
    expect($this->branch->menuItems()->where('name', 'Fried Rice')->where('is_popular', true)->exists())->toBeTrue();
});
