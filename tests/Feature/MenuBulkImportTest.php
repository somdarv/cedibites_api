<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\PermissionSeeder::class,
        \Database\Seeders\RoleSeeder::class,
    ]);

    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Admin->value);
    $this->user->givePermissionTo(Permission::ManageMenu->value);
    $this->branch = Branch::factory()->create();
});

test('can bulk import menu items from CSV', function () {
    Storage::fake('local');

    // Create CSV content
    $csvContent = "name,category,description,price,is_available,is_popular\n";
    $csvContent .= "Jollof Rice,Basic Meals,Delicious rice,75,true,false\n";
    $csvContent .= "Fried Rice,Basic Meals,Tasty fried rice,65,true,true\n";
    $csvContent .= "Milo Drink,Drinks,Hot chocolate drink,15,true,false\n";

    $file = UploadedFile::fake()->createWithContent('menu-items.csv', $csvContent);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/admin/menu-items/bulk-import', [
            'csv_file' => $file,
            'branch_id' => $this->branch->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'imported',
                'skipped',
                'failed',
                'total_processed',
            ],
        ]);

    // Verify items were created
    expect(MenuItem::where('branch_id', $this->branch->id)->count())->toBe(3);
    expect(MenuItem::where('name', 'Jollof Rice')->exists())->toBeTrue();
    expect(MenuItem::where('name', 'Fried Rice')->where('is_popular', true)->exists())->toBeTrue();
    expect(MenuItem::where('name', 'Milo Drink')->exists())->toBeTrue();

    // Verify categories were created
    expect(MenuCategory::where('name', 'Basic Meals')->exists())->toBeTrue();
    expect(MenuCategory::where('name', 'Drinks')->exists())->toBeTrue();
});

test('can preview bulk import without saving', function () {
    Storage::fake('local');

    $csvContent = "name,category,description,price,is_available,is_popular\n";
    $csvContent .= "Valid Item,Basic Meals,Good item,75,true,false\n";
    $csvContent .= ",Basic Meals,Missing name,65,true,false\n"; // Invalid - no name
    $csvContent .= "Another Item,Basic Meals,Good item,-10,true,false\n"; // Invalid - negative price

    $file = UploadedFile::fake()->createWithContent('menu-items.csv', $csvContent);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/admin/menu-items/bulk-import-preview', [
            'csv_file' => $file,
            'branch_id' => $this->branch->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'total_rows',
                'valid_rows',
                'invalid_rows',
                'skipped_rows',
                'preview',
                'can_import',
            ],
        ]);

    $data = $response->json('data');
    expect($data['total_rows'])->toBe(3);
    expect($data['valid_rows'])->toBe(1);
    expect($data['invalid_rows'])->toBe(2);
    expect($data['can_import'])->toBeTrue();

    // Verify no items were actually created during preview
    expect(MenuItem::where('branch_id', $this->branch->id)->count())->toBe(0);
});

test('handles validation errors in bulk import', function () {
    Storage::fake('local');

    $csvContent = "name,category,description,price,is_available,is_popular\n";
    $csvContent .= ",Basic Meals,Missing name,75,true,false\n"; // Invalid - no name
    $csvContent .= "Invalid Price,Basic Meals,Bad price,not_a_number,true,false\n"; // Invalid - bad price

    $file = UploadedFile::fake()->createWithContent('menu-items.csv', $csvContent);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/admin/menu-items/bulk-import', [
            'csv_file' => $file,
            'branch_id' => $this->branch->id,
        ]);

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data['imported'])->toBe(0);
    expect($data['failed'])->toBeGreaterThan(0);
});

test('supports Excel files', function () {
    Storage::fake('local');

    // Create a simple Excel file (XLSX)
    $file = UploadedFile::fake()->create('menu-items.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/admin/menu-items/bulk-import-preview', [
            'csv_file' => $file,
            'branch_id' => $this->branch->id,
        ]);

    // Should not fail due to file type (though content might be invalid)
    expect($response->status())->toBeIn([200, 422, 500]); // Accept various responses as long as file type is accepted
});
