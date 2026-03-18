<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePosOrderRequest;
use Illuminate\Http\JsonResponse;

class PosOrderController extends Controller
{
    private const TAX_RATE = 0.025;

    private const PRICE_TOLERANCE = 0.01;

    public function store(StorePosOrderRequest $request): JsonResponse
    {
        // Log incoming request for debugging
        \Log::info('POS order request received', [
            'validated' => $request->validated(),
            'items' => $request->validated('items'),
        ]);

        // Get authenticated user and retrieve employee record
        $user = $request->user();
        $employee = $user->employee;

        // Verify employee exists and is active
        if (! $employee) {
            return response()->json([
                'message' => 'Employee record not found',
            ], 403);
        }

        if ($employee->status !== \App\Enums\EmployeeStatus::Active) {
            return response()->json([
                'message' => 'Your account is not active',
            ], 403);
        }

        // Verify branch and staff authorization
        $branchId = $request->validated('branch_id');
        $this->verifyStaffAuthorization($employee, $branchId);

        // Validate menu items
        $items = $request->validated('items');
        $menuItems = $this->validateMenuItems($items, $branchId);

        // Verify prices match expected values
        $this->verifyPrices($items, $menuItems);

        try {
            // Wrap all database operations in a transaction
            $order = \DB::transaction(function () use ($request, $employee, $branchId, $items, $menuItems) {
                // Calculate order totals
                $discount = (float) ($request->validated('discount') ?? 0.0);
                $totals = $this->calculateOrderTotals($items, $discount);

                // Generate unique order number
                $orderNumber = $this->generateOrderNumber();

                // Build delivery note
                $fulfillmentType = $request->validated('fulfillment_type');
                $deliveryNote = "Fulfillment: {$fulfillmentType}";
                $customerNotes = $request->validated('customer_notes');
                if ($customerNotes) {
                    $deliveryNote .= "\n{$customerNotes}";
                }

                // Create order record
                $order = \App\Models\Order::create([
                    'order_number' => $orderNumber,
                    'branch_id' => $branchId,
                    'assigned_employee_id' => $employee->id,
                    'order_type' => 'pickup',
                    'order_source' => 'pos',
                    'contact_name' => $request->validated('contact_name'),
                    'contact_phone' => $request->validated('contact_phone'),
                    'delivery_note' => $deliveryNote,
                    'subtotal' => $totals['subtotal'],
                    'delivery_fee' => $totals['delivery_fee'],
                    'tax_rate' => self::TAX_RATE,
                    'tax_amount' => $totals['tax_amount'],
                    'total_amount' => $totals['total_amount'],
                    'status' => 'received',
                    'customer_id' => null,
                    'delivery_address' => null,
                    'delivery_latitude' => null,
                    'delivery_longitude' => null,
                ]);

                // Create order items
                foreach ($items as $item) {
                    $menuItem = $menuItems[$item['menu_item_id']];
                    $menuItemSize = null;

                    // Get menu item size if provided
                    if (isset($item['menu_item_size_id']) && $item['menu_item_size_id'] !== null) {
                        $menuItemSize = \App\Models\MenuItemSize::find($item['menu_item_size_id']);
                    }

                    // Create snapshots
                    $snapshots = $this->createOrderSnapshots($menuItem, $menuItemSize);

                    // Create order item
                    \App\Models\OrderItem::create([
                        'order_id' => $order->id,
                        'menu_item_id' => $item['menu_item_id'],
                        'menu_item_size_id' => $item['menu_item_size_id'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'subtotal' => $item['quantity'] * $item['unit_price'],
                        'menu_item_snapshot' => json_encode($snapshots['menu_item_snapshot']),
                        'menu_item_size_snapshot' => $snapshots['menu_item_size_snapshot'] ? json_encode($snapshots['menu_item_size_snapshot']) : null,
                    ]);
                }

                // Get branch for logging and payment description
                $branch = \App\Models\Branch::find($branchId);

                // Create payment record based on payment method
                $paymentMethod = $request->validated('payment_method');

                if ($paymentMethod === 'mobile_money') {
                    // For mobile money, initiate Hubtel payment
                    try {
                        $hubtelService = app(\App\Services\HubtelService::class);

                        \Log::info('Attempting to initialize Hubtel payment', [
                            'order_id' => $order->id,
                            'order_number' => $orderNumber,
                            'amount' => $totals['total_amount'],
                        ]);

                        $hubtelData = $hubtelService->initializeTransaction([
                            'order' => $order,
                            'description' => "POS Order {$orderNumber} - {$branch->name}",
                            'customer_name' => $request->validated('contact_name'),
                            'customer_phone' => $request->validated('momo_number') ?? $request->validated('contact_phone'),
                        ]);

                        \Log::info('Hubtel payment initialized successfully', [
                            'order_id' => $order->id,
                            'checkout_url' => $hubtelData['checkoutUrl'] ?? null,
                        ]);

                        // Payment record is created by HubtelService with status 'pending'
                        // The payment will be completed via Hubtel callback
                    } catch (\RuntimeException $e) {
                        // If Hubtel is not configured, create a pending payment record
                        // This allows the order to be created but payment must be handled manually
                        \Log::warning('Hubtel not configured, creating pending payment', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);

                        \App\Models\Payment::create([
                            'order_id' => $order->id,
                            'payment_method' => $paymentMethod,
                            'amount' => $totals['total_amount'],
                            'payment_status' => 'pending',
                            'customer_id' => null,
                        ]);
                    }
                } else {
                    // For cash, card, wallet, ghqr - mark as completed immediately
                    \App\Models\Payment::create([
                        'order_id' => $order->id,
                        'payment_method' => $paymentMethod,
                        'amount' => $totals['total_amount'],
                        'payment_status' => 'completed',
                        'paid_at' => now(),
                        'customer_id' => null,
                    ]);
                }

                // Log activity
                $description = "POS order {$orderNumber} created by {$employee->user->name} at {$branch->name} for GHS {$totals['total_amount']}";

                activity()
                    ->causedBy($request->user())
                    ->performedOn($order)
                    ->withProperties([
                        'order_number' => $orderNumber,
                        'branch_name' => $branch->name,
                        'staff_name' => $employee->user->name,
                        'total_amount' => $totals['total_amount'],
                    ])
                    ->log($description);

                return $order;
            });

            // Load relationships for response
            $order->load(['branch', 'assignedEmployee.user', 'items.menuItem', 'items.menuItemSize', 'payments']);

            // For mobile money payments, include Hubtel checkout URL
            $responseData = ['data' => $order];

            if ($request->validated('payment_method') === 'mobile_money') {
                // Get the payment record to check if it has Hubtel data
                $payment = $order->payments->first();
                if ($payment && $payment->payment_gateway_response) {
                    $hubtelResponse = $payment->payment_gateway_response;
                    $responseData['hubtel'] = [
                        'checkoutUrl' => $hubtelResponse['checkoutUrl'] ?? null,
                        'checkoutDirectUrl' => $hubtelResponse['checkoutDirectUrl'] ?? null,
                        'checkoutId' => $hubtelResponse['checkoutId'] ?? null,
                    ];
                }
            }

            // Return response with 201 status
            return response()->json($responseData, 201);

        } catch (\Exception $e) {
            // Log error for debugging
            \Log::error('POS order creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'employee_id' => $employee->id,
                'branch_id' => $branchId,
            ]);

            // Return 500 error response
            return response()->json([
                'message' => 'An error occurred while creating the order. Please try again.',
            ], 500);
        }
    }

    /**
     * Verify that the branch exists and the employee is authorized to create orders for it.
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    private function verifyStaffAuthorization(\App\Models\Employee $employee, int $branchId): void
    {
        // Verify branch exists
        $branch = \App\Models\Branch::find($branchId);
        if (! $branch) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'Invalid branch',
                ], 422)
            );
        }

        // Super admins can create orders for any branch
        if ($employee->user->hasRole(\App\Enums\Role::SuperAdmin)) {
            return;
        }

        // Call center agents can create orders for any branch
        if ($employee->user->hasRole(\App\Enums\Role::CallCenter)) {
            return;
        }

        // Branch managers can create orders for branches they manage
        if ($employee->user->hasRole(\App\Enums\Role::Manager)) {
            $managesBranch = $employee->managedBranches()->where('branches.id', $branchId)->exists();
            if ($managesBranch) {
                return;
            }
        }

        // Verify employee is assigned to the branch
        $isAssignedToBranch = $employee->branches()->where('branches.id', $branchId)->exists();
        if (! $isAssignedToBranch) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'You are not authorized to create orders for this branch',
                ], 403)
            );
        }
    }

    /**
     * Validate that all menu items exist, belong to the branch, and verify sizes if provided.
     *
     * @param  array  $items  The items array from the request
     * @param  int  $branchId  The branch ID to validate against
     * @return array Array of MenuItem models keyed by ID
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    private function validateMenuItems(array $items, int $branchId): array
    {
        // Extract all menu_item_id values
        $menuItemIds = array_column($items, 'menu_item_id');

        // Fetch all menu items at once
        $menuItems = \App\Models\MenuItem::whereIn('id', $menuItemIds)
            ->with('branch')
            ->get()
            ->keyBy('id');

        // Verify all menu items exist
        foreach ($menuItemIds as $menuItemId) {
            if (! $menuItems->has($menuItemId)) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json([
                        'message' => 'Invalid menu item',
                    ], 422)
                );
            }
        }

        // Verify all menu items belong to the specified branch
        foreach ($menuItems as $menuItem) {
            if ($menuItem->branch_id !== $branchId) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json([
                        'message' => "Menu item {$menuItem->name} is not available at this branch",
                    ], 422)
                );
            }
        }

        // Validate menu item sizes for items that have them
        foreach ($items as $item) {
            if (isset($item['menu_item_size_id']) && $item['menu_item_size_id'] !== null) {
                $menuItemSizeId = $item['menu_item_size_id'];
                $menuItemId = $item['menu_item_id'];

                // Fetch the size and verify it exists
                $menuItemSize = \App\Models\MenuItemSize::find($menuItemSizeId);

                if (! $menuItemSize) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        response()->json([
                            'message' => 'Invalid menu item size',
                        ], 422)
                    );
                }

                // Verify the size belongs to the menu item
                if ($menuItemSize->menu_item_id !== $menuItemId) {
                    $menuItem = $menuItems->get($menuItemId);
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        response()->json([
                            'message' => "The selected size does not belong to menu item {$menuItem->name}",
                        ], 422)
                    );
                }
            }
        }

        return $menuItems->toArray();
    }

    /**
     * Verify that the unit prices in the request match the expected menu item prices.
     *
     * @param  array  $items  The items array from the request
     * @param  array  $menuItems  Array of MenuItem models keyed by ID
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    private function verifyPrices(array $items, array $menuItems): void
    {
        foreach ($items as $item) {
            $menuItemId = $item['menu_item_id'];

            // Check if menu item exists in the array
            if (! isset($menuItems[$menuItemId])) {
                continue; // Skip if not found (should have been caught by validateMenuItems)
            }

            $menuItem = $menuItems[$menuItemId];
            $providedPrice = (float) $item['unit_price'];

            // Determine expected price based on whether item has a size
            if (isset($item['menu_item_size_id']) && $item['menu_item_size_id'] !== null) {
                // Item has a size variant - use the size's price
                $menuItemSize = \App\Models\MenuItemSize::find($item['menu_item_size_id']);
                if (! $menuItemSize) {
                    continue; // Skip if size not found (should have been caught by validateMenuItems)
                }
                $expectedPrice = (float) $menuItemSize->price;
            } else {
                // Item has no size - use base price
                $expectedPrice = (float) $menuItem['base_price'];
            }

            // Compare prices with tolerance
            $priceDifference = abs($providedPrice - $expectedPrice);
            if ($priceDifference > self::PRICE_TOLERANCE) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json([
                        'message' => "Price mismatch for item {$menuItem['name']}: expected {$expectedPrice}, got {$providedPrice}",
                    ], 422)
                );
            }
        }
    }

    /**
     * Calculate order totals including subtotal, tax, delivery fee, and total amount.
     *
     * @param  array  $items  The items array from the request
     * @param  float  $discount  The discount amount to apply
     * @return array Associative array with subtotal, tax_amount, delivery_fee, total_amount
     */
    private function calculateOrderTotals(array $items, float $discount): array
    {
        // Calculate subtotal by summing (quantity * unit_price) for all items
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += $item['quantity'] * $item['unit_price'];
        }

        // Subtract discount from subtotal if discount > 0
        $subtotalAfterDiscount = $subtotal - $discount;

        // Calculate tax_amount as (subtotal - discount) * TAX_RATE, rounded to 2 decimals
        $taxAmount = round($subtotalAfterDiscount * self::TAX_RATE, 2);

        // Set delivery_fee to 0.00 for POS orders
        $deliveryFee = 0.00;

        // Calculate total_amount
        $totalAmount = $subtotalAfterDiscount + $taxAmount + $deliveryFee;

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'delivery_fee' => $deliveryFee,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Generate a unique order number in format "CB" + 6 digits.
     *
     * @return string Unique order number
     */
    private function generateOrderNumber(): string
    {
        // Generate random 6-digit number
        $number = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $orderNumber = 'CB'.$number;

        // Check if order number already exists
        if (\App\Models\Order::where('order_number', $orderNumber)->exists()) {
            // Recursively generate new number if collision occurs
            return $this->generateOrderNumber();
        }

        return $orderNumber;
    }

    /**
     * Create snapshots of menu item and size for order item preservation.
     *
     * @param  array|object  $menuItem  The menu item to snapshot (can be array or model)
     * @param  \App\Models\MenuItemSize|null  $size  The menu item size to snapshot (if applicable)
     * @return array Associative array with menu_item_snapshot and menu_item_size_snapshot
     */
    private function createOrderSnapshots($menuItem, $size): array
    {
        // Handle both array and object formats for menu item
        $menuItemData = is_array($menuItem) ? $menuItem : $menuItem->toArray();

        // Create menu item snapshot with required fields
        $menuItemSnapshot = [
            'id' => $menuItemData['id'],
            'name' => $menuItemData['name'],
            'description' => $menuItemData['description'],
            'base_price' => $menuItemData['base_price'],
            'image_url' => $menuItemData['image_url'] ?? null,
        ];

        // Create size snapshot if size is provided
        $menuItemSizeSnapshot = null;
        if ($size !== null) {
            $menuItemSizeSnapshot = [
                'id' => $size->id,
                'size_name' => $size->name,
                'price' => $size->price,
            ];
        }

        return [
            'menu_item_snapshot' => $menuItemSnapshot,
            'menu_item_size_snapshot' => $menuItemSizeSnapshot,
        ];
    }
}
