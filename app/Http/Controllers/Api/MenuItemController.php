<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Http\Resources\MenuItemCollection;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MenuItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $menuItems = MenuItem::with(['branch', 'category', 'sizes'])
            ->when($request->branch_id, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($request->category_id, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($request->is_available !== null, fn ($query) => $query->where('is_available', $request->boolean('is_available')))
            ->when($request->is_popular !== null, fn ($query) => $query->where('is_popular', $request->boolean('is_popular')))
            ->paginate($request->per_page ?? 15);

        return response()->paginated(new MenuItemCollection($menuItems));
    }

    /**
     * Store a newly created resource in storage.
     */
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        try {
            $menuItem = MenuItem::create($request->validated());

            return response()->created(
                new MenuItemResource($menuItem->load(['branch', 'category', 'sizes']))
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database constraint violations
            if ($e->getCode() === '23000') {
                // Integrity constraint violation
                if (str_contains($e->getMessage(), 'menu_items_branch_id_slug_unique')) {
                    return response()->json([
                        'message' => 'A menu item with this name already exists in this branch.',
                        'errors' => [
                            'slug' => ['This menu item name is already taken for this branch.'],
                        ],
                    ], 422);
                }
            }

            \Log::error('Menu item creation failed', [
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);

            return response()->json([
                'message' => 'Failed to create menu item. Please check your data and try again.',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Unexpected error creating menu item', [
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);

            return response()->json([
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(MenuItem $menuItem): JsonResponse
    {
        $menuItem->load(['branch', 'category', 'sizes']);

        return response()->success(new MenuItemResource($menuItem));
    }

    /**
     * Update the specified resource in storage.
     */
    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): JsonResponse
    {
        try {
            $menuItem->update($request->validated());

            return response()->success(
                new MenuItemResource($menuItem->fresh(['branch', 'category', 'sizes']))
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database constraint violations
            if ($e->getCode() === '23000') {
                // Integrity constraint violation
                if (str_contains($e->getMessage(), 'menu_items_branch_id_slug_unique')) {
                    return response()->json([
                        'message' => 'A menu item with this name already exists in this branch.',
                        'errors' => [
                            'slug' => ['This menu item name is already taken for this branch.'],
                        ],
                    ], 422);
                }
            }

            \Log::error('Menu item update failed', [
                'error' => $e->getMessage(),
                'data' => $request->validated(),
                'item_id' => $menuItem->id,
            ]);

            return response()->json([
                'message' => 'Failed to update menu item. Please check your data and try again.',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Unexpected error updating menu item', [
                'error' => $e->getMessage(),
                'data' => $request->validated(),
                'item_id' => $menuItem->id,
            ]);

            return response()->json([
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload image for menu item.
     */
    public function uploadImage(MenuItem $menuItem, Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        try {
            // Clear existing images
            $menuItem->clearMediaCollection('menu-items');

            // Add new image
            $menuItem->addMediaFromRequest('image')
                ->toMediaCollection('menu-items');

            return response()->success(
                new MenuItemResource($menuItem->fresh(['branch', 'category', 'sizes'])),
                'Image uploaded successfully.'
            );
        } catch (\Exception $e) {
            \Log::error('Menu item image upload failed', [
                'error' => $e->getMessage(),
                'item_id' => $menuItem->id,
            ]);

            return response()->json([
                'message' => 'Failed to upload image.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk import menu items from CSV using Laravel Excel.
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'], // 5MB max
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        try {
            $import = new \App\Imports\MenuItemsImport($request->branch_id);

            \Excel::import($import, $request->file('csv_file'));

            $failures = $import->failures();
            $errors = $import->errors();

            $failureDetails = [];
            foreach ($failures as $failure) {
                $failureDetails[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }

            $errorDetails = [];
            foreach ($errors as $error) {
                $errorDetails[] = [
                    'message' => $error->getMessage(),
                    'line' => $error->getLine() ?? null,
                ];
            }

            $imported = $import->getImportedCount();
            $skipped = $import->getSkippedCount();
            $failed = count($failureDetails);

            return response()->success([
                'imported' => $imported,
                'skipped' => $skipped,
                'failed' => $failed,
                'total_processed' => $imported + $skipped + $failed,
                'validation_failures' => $failureDetails,
                'errors' => $errorDetails,
            ], "Successfully imported {$imported} menu items.");

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $failureDetails = [];

            foreach ($failures as $failure) {
                $failureDetails[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }

            return response()->json([
                'message' => 'Validation failed for some rows.',
                'failures' => $failureDetails,
            ], 422);

        } catch (\Exception $e) {
            \Log::error('Bulk import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to import menu items.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview bulk import without saving using Laravel Excel.
     */
    public function bulkImportPreview(Request $request): JsonResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        try {
            $file = $request->file('csv_file');

            // Use Laravel Excel to read the file
            $rows = \Excel::toCollection(new \App\Imports\MenuItemsImport($request->branch_id), $file)->first();

            $validRows = [];
            $invalidRows = [];
            $skipped = 0;

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 for header row and 0-based index

                // Skip empty rows
                if ($row->filter()->isEmpty()) {
                    $skipped++;

                    continue;
                }

                // Validate row data
                $rowErrors = [];

                if (empty(trim($row['name'] ?? ''))) {
                    $rowErrors[] = 'Name is required';
                }

                if (! empty($row['price']) && ! is_numeric($row['price'])) {
                    $rowErrors[] = 'Price must be a number';
                }

                if (! empty($row['price']) && (float) $row['price'] < 0) {
                    $rowErrors[] = 'Price cannot be negative';
                }

                $rowPreview = [
                    'row' => $rowNumber,
                    'name' => trim($row['name'] ?? ''),
                    'category' => trim($row['category'] ?? ''),
                    'description' => trim($row['description'] ?? ''),
                    'price' => ! empty($row['price']) ? (float) $row['price'] : null,
                    'is_available' => $this->parseBoolean($row['is_available'] ?? 'true'),
                    'is_popular' => $this->parseBoolean($row['is_popular'] ?? 'false'),
                    'status' => empty($rowErrors) ? 'valid' : 'invalid',
                    'errors' => $rowErrors,
                ];

                if (empty($rowErrors)) {
                    $validRows[] = $rowPreview;
                } else {
                    $invalidRows[] = $rowPreview;
                }
            }

            return response()->success([
                'total_rows' => $rows->count(),
                'valid_rows' => count($validRows),
                'invalid_rows' => count($invalidRows),
                'skipped_rows' => $skipped,
                'preview' => array_merge($validRows, $invalidRows),
                'can_import' => count($validRows) > 0,
            ], 'CSV preview generated successfully.');

        } catch (\Exception $e) {
            \Log::error('Bulk import preview failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to preview CSV file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function parseBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim($value));

        return in_array($value, ['true', '1', 'yes', 'y'], true);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MenuItem $menuItem): JsonResponse
    {
        try {
            $menuItem->delete();

            return response()->deleted();
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }
}
