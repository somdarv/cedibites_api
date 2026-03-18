<?php

namespace App\Imports;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class MenuItemsImport implements SkipsEmptyRows, SkipsOnError, SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use Importable, SkipsErrors, SkipsFailures;

    protected int $branchId;

    protected int $imported = 0;

    protected int $skipped = 0;

    protected array $createdCategories = [];

    public function __construct(int $branchId)
    {
        $this->branchId = $branchId;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            try {
                // Find or create category
                $categoryId = null;
                if (! empty($row['category'])) {
                    $categoryName = trim($row['category']);

                    // Check if we've already created this category in this import
                    if (isset($this->createdCategories[$categoryName])) {
                        $categoryId = $this->createdCategories[$categoryName];
                    } else {
                        $category = MenuCategory::firstOrCreate([
                            'branch_id' => $this->branchId,
                            'name' => $categoryName,
                        ], [
                            'slug' => Str::slug($categoryName),
                            'display_order' => 0,
                            'is_active' => true,
                        ]);

                        $categoryId = $category->id;
                        $this->createdCategories[$categoryName] = $categoryId;
                    }
                }

                // Generate unique slug
                $baseSlug = Str::slug($row['name']);
                $slug = $baseSlug.'-'.time().'-'.uniqid();

                // Create menu item
                MenuItem::create([
                    'branch_id' => $this->branchId,
                    'category_id' => $categoryId,
                    'name' => trim($row['name']),
                    'slug' => $slug,
                    'description' => ! empty($row['description']) ? trim($row['description']) : null,
                    'base_price' => ! empty($row['price']) ? (float) $row['price'] : null,
                    'is_available' => $this->parseBoolean($row['is_available'] ?? 'true'),
                    'is_popular' => $this->parseBoolean($row['is_popular'] ?? 'false'),
                ]);

                $this->imported++;
            } catch (\Exception $e) {
                $this->skipped++;
                \Log::error('Failed to import menu item', [
                    'row' => $row->toArray(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_available' => ['nullable'],
            'is_popular' => ['nullable'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'price.numeric' => 'The price must be a number.',
            'price.min' => 'The price must be at least 0.',
        ];
    }

    public function getImportedCount(): int
    {
        return $this->imported;
    }

    public function getSkippedCount(): int
    {
        return $this->skipped;
    }

    protected function parseBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim($value));

        return in_array($value, ['true', '1', 'yes', 'y'], true);
    }
}
