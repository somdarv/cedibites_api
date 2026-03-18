# Implementation Plan: POS Order Creation

## Overview

This implementation plan breaks down the POS Order Creation feature into discrete coding tasks. The feature enables restaurant staff to create orders directly through the POS system with immediate payment completion. Implementation follows Laravel 12 conventions and integrates with existing models while maintaining transaction integrity.

## Tasks

- [x] 1. Create form request validator for POS orders
  - Create `StorePosOrderRequest` using `php artisan make:request StorePosOrderRequest --no-interaction`
  - Implement validation rules for all required fields: branch_id, items array, payment_method, fulfillment_type, contact_name, contact_phone
  - Add validation for optional discount field
  - Implement custom error messages for each validation rule
  - Validate items array structure (menu_item_id, quantity, unit_price required per item)
  - Validate payment_method against allowed values: cash, mobile_money, card, wallet, ghqr
  - Validate fulfillment_type against allowed values: dine_in, takeaway
  - Set authorize() method to return true (authorization handled in controller)
  - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 12.7, 12.8, 12.9, 12.10, 12.11_

- [x] 1.1 Write property test for request validation
  - **Property 26: Price Validation**
  - **Validates: Requirements 15.1, 15.2, 15.3, 15.5**
  - Generate random order items with intentional price mismatches
  - Verify requests with price differences beyond 0.01 tolerance are rejected with 422
  - Verify requests within tolerance are accepted

- [x] 2. Create POS order controller
  - Create `PosOrderController` using `php artisan make:controller Api/PosOrderController --no-interaction`
  - Add class constants: TAX_RATE = 0.025, PRICE_TOLERANCE = 0.01
  - Implement store() method with StorePosOrderRequest parameter
  - Add JsonResponse return type to store() method
  - _Requirements: 1.1_

- [x] 2.1 Write unit tests for controller structure
  - Test controller exists and has store method
  - Test store method accepts StorePosOrderRequest
  - Test store method returns JsonResponse

- [x] 3. Implement staff authentication and authorization
  - In store() method, get authenticated user from request
  - Retrieve Employee model for authenticated user
  - Verify employee status is "active", return 403 if not
  - Extract branch_id from validated request data
  - Verify employee is assigned to the specified branch using relationship
  - Return 403 with message "You are not authorized to create orders for this branch" if not assigned
  - _Requirements: 2.1, 2.3, 2.4, 2.5, 3.2, 3.3_

- [x] 3.1 Write property test for staff authorization
  - **Property 6: Active Staff Authorization**
  - **Validates: Requirements 2.3**
  - Generate random staff members with various statuses
  - Verify only "active" staff can create orders
  - Verify inactive staff receive 403 response

- [x] 3.2 Write property test for branch authorization
  - **Property 8: Staff Branch Authorization**
  - **Validates: Requirements 3.2**
  - Generate random staff and branch combinations
  - Verify staff can only create orders for assigned branches
  - Verify unassigned branch attempts receive 403 response

- [x] 4. Implement branch validation
  - Create private method verifyStaffAuthorization(Employee $employee, int $branchId): void
  - Query Branch model to verify branch_id exists
  - Return 422 with message "Invalid branch" if branch doesn't exist
  - Move staff-branch assignment check to this method
  - _Requirements: 3.1, 3.4_

- [x] 4.1 Write property test for branch validation
  - **Property 7: Branch Existence Validation**
  - **Validates: Requirements 3.1**
  - Generate random branch IDs including non-existent ones
  - Verify non-existent branches are rejected with 422

- [x] 5. Implement menu item validation
  - Create private method validateMenuItems(array $items, int $branchId): array
  - Extract all menu_item_id values from items array
  - Query MenuItem model with whereIn to fetch all items at once
  - Verify all menu items exist, return 422 with "Invalid menu item: {name}" if any missing
  - Verify all menu items belong to specified branch, return 422 with "Menu item {name} is not available at this branch" if not
  - For items with menu_item_size_id, query MenuItemSize and verify they exist and belong to the menu item
  - Return 422 with appropriate message if size validation fails
  - Return array of MenuItem models keyed by ID for later use
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

- [x] 5.1 Write property test for menu item validation
  - **Property 9: Menu Item Existence and Branch Validation**
  - **Validates: Requirements 4.1, 4.2**
  - Generate random orders with valid and invalid menu items
  - Verify invalid menu items are rejected with 422
  - Verify menu items from wrong branch are rejected with 422

- [x] 5.2 Write property test for variant validation
  - **Property 10: Menu Item Variant Validation**
  - **Validates: Requirements 4.3**
  - Generate random orders with valid and invalid menu item sizes
  - Verify invalid sizes are rejected with 422
  - Verify sizes not belonging to menu item are rejected with 422

- [x] 6. Implement price verification
  - Create private method verifyPrices(array $items, array $menuItems): void
  - For each item in request, get corresponding MenuItem from menuItems array
  - If item has menu_item_size_id, fetch MenuItemSize and calculate expected price as base_price + price_adjustment
  - If item has no size, expected price is base_price
  - Compare item unit_price with expected price using abs(unit_price - expected_price) <= PRICE_TOLERANCE
  - Return 422 with message "Price mismatch for item {name}: expected {expected}, got {provided}" if validation fails
  - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5_

- [x] 6.1 Write unit tests for price verification
  - Test exact price match passes validation
  - Test price within tolerance (0.01) passes validation
  - Test price beyond tolerance fails with 422
  - Test variant price calculation (base_price + adjustment)
  - Test non-variant price calculation (base_price only)

- [x] 7. Implement order calculation
  - Create private method calculateOrderTotals(array $items, float $discount): array
  - Calculate subtotal by summing (quantity * unit_price) for all items
  - Subtract discount from subtotal if discount > 0
  - Calculate tax_amount as (subtotal - discount) * TAX_RATE, round to 2 decimals using round()
  - Set delivery_fee to 0.00
  - Calculate total_amount as (subtotal - discount) + tax_amount + delivery_fee
  - Return associative array with keys: subtotal, tax_amount, delivery_fee, total_amount
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7_

- [x] 7.1 Write property test for subtotal calculation
  - **Property 11: Subtotal Calculation**
  - **Validates: Requirements 5.1, 5.2**
  - Generate random orders with varying quantities and prices
  - Verify subtotal equals sum of (quantity * unit_price)

- [x] 7.2 Write property test for tax calculation
  - **Property 12: Tax Calculation**
  - **Validates: Requirements 5.3, 5.4**
  - Generate random orders with varying subtotals
  - Verify tax_amount equals subtotal * 0.025 rounded to 2 decimals

- [x] 7.3 Write property test for delivery fee
  - **Property 13: Delivery Fee for POS Orders**
  - **Validates: Requirements 5.5**
  - Generate random POS orders
  - Verify delivery_fee is always 0.00

- [x] 7.4 Write property test for total calculation
  - **Property 14: Total Amount Calculation**
  - **Validates: Requirements 5.6, 5.7**
  - Generate random orders with and without discounts
  - Verify total_amount equals (subtotal - discount) + tax_amount + delivery_fee

- [x] 8. Implement order number generation
  - Create private method generateOrderNumber(): string
  - Generate random 6-digit number using str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT)
  - Prepend "CB" to create order number
  - Query Order model to check if order_number already exists
  - If exists, recursively call generateOrderNumber() to get new number
  - Return unique order number
  - _Requirements: 1.4, 1.5_

- [x] 8.1 Write property test for order number format
  - **Property 3: Order Number Format**
  - **Validates: Requirements 1.4**
  - Generate multiple orders
  - Verify each order_number matches pattern "CB" + 6 digits using regex

- [x] 8.2 Write unit tests for order number generation
  - Test order number format is correct
  - Test order number uniqueness (mock collision scenario)
  - Test regeneration on collision

- [x] 9. Implement snapshot creation
  - Create private method createOrderSnapshots(MenuItem $menuItem, ?MenuItemSize $size): array
  - Create menu_item_snapshot array with keys: id, name, description, base_price, image_url
  - If size is not null, create menu_item_size_snapshot with keys: id, size_name, price_adjustment
  - If size is null, set menu_item_size_snapshot to null
  - Return associative array with keys: menu_item_snapshot, menu_item_size_snapshot
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6_

- [x] 9.1 Write property test for menu item snapshots
  - **Property 19: Menu Item Snapshot Creation**
  - **Validates: Requirements 9.1, 9.5**
  - Generate random orders with various menu items
  - Verify each order item has menu_item_snapshot with required fields

- [x] 9.2 Write property test for size snapshots
  - **Property 20: Menu Item Size Snapshot Creation**
  - **Validates: Requirements 9.2, 9.6**
  - Generate random orders with items that have variants
  - Verify each variant item has menu_item_size_snapshot with required fields

- [x] 9.3 Write property test for null variant handling
  - **Property 21: Null Variant Handling**
  - **Validates: Requirements 9.3, 9.4**
  - Generate random orders with items without variants
  - Verify menu_item_size_id and menu_item_size_snapshot are both null

- [x] 10. Implement database transaction and order creation
  - In store() method, wrap all database operations in DB::transaction()
  - Call validateMenuItems() to get menu items array
  - Call verifyPrices() to validate prices
  - Call calculateOrderTotals() to get totals
  - Call generateOrderNumber() to get unique order number
  - Create Order record with fields: order_number, branch_id, assigned_employee_id (from auth user), order_type ("pickup"), order_source ("pos"), contact_name, contact_phone, subtotal, delivery_fee (0.00), tax_rate (0.025), tax_amount, total_amount, status ("received"), customer_id (null), delivery_address (null), delivery_latitude (null), delivery_longitude (null)
  - Build delivery_note as "Fulfillment: {fulfillment_type}" and append customer_notes if provided
  - _Requirements: 1.2, 1.3, 1.4, 1.6, 6.1, 6.2, 6.3, 8.3, 8.4, 10.1, 10.2, 10.3, 10.4, 10.5, 13.1_

- [x] 10.1 Write property test for order source
  - **Property 1: Order Source Assignment**
  - **Validates: Requirements 1.2**
  - Generate random POS orders
  - Verify order_source is always "pos"

- [x] 10.2 Write property test for employee assignment
  - **Property 2: Employee Assignment**
  - **Validates: Requirements 1.3**
  - Generate random orders with different authenticated staff
  - Verify assigned_employee_id matches authenticated staff member

- [x] 10.3 Write property test for fulfillment type mapping
  - **Property 15: Fulfillment Type Mapping**
  - **Validates: Requirements 6.1, 6.2, 6.3**
  - Generate random orders with "dine_in" and "takeaway" fulfillment types
  - Verify order_type is "pickup" for both
  - Verify delivery_note contains "Fulfillment: {fulfillment_type}"

- [x] 10.4 Write property test for contact information
  - **Property 18: Contact Information Storage**
  - **Validates: Requirements 8.3, 8.4, 8.5**
  - Generate random orders with contact info and notes
  - Verify contact_name and contact_phone are stored correctly
  - Verify customer_notes are appended to delivery_note

- [x] 10.5 Write property test for initial status
  - **Property 22: Initial Order Status**
  - **Validates: Requirements 10.1**
  - Generate random POS orders
  - Verify status is always "received"

- [x] 10.6 Write property test for null fields
  - **Property 23: POS Order Null Fields**
  - **Validates: Requirements 10.2, 10.3, 10.4, 10.5**
  - Generate random POS orders
  - Verify customer_id, delivery_address, delivery_latitude, delivery_longitude are all null

- [x] 11. Implement order items creation
  - Inside transaction, loop through validated items array
  - For each item, get MenuItem from menuItems array
  - Get MenuItemSize if menu_item_size_id is present, otherwise null
  - Call createOrderSnapshots() to get snapshot data
  - Create OrderItem record with fields: order_id, menu_item_id, menu_item_size_id (or null), quantity, unit_price, menu_item_snapshot (JSON), menu_item_size_snapshot (JSON or null)
  - _Requirements: 9.1, 9.2, 9.3, 9.4_

- [x] 11.1 Write unit tests for order items creation
  - Test order items are created with correct quantities
  - Test order items with variants have size_id set
  - Test order items without variants have null size_id
  - Test snapshots are stored as JSON

- [x] 12. Implement payment creation
  - Inside transaction, create Payment record with fields: order_id, payment_method (from request), amount (total_amount), payment_status ("completed"), paid_at (now()), customer_id (null)
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8_

- [x] 12.1 Write property test for payment completion
  - **Property 16: Payment Completion**
  - **Validates: Requirements 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7**
  - Generate random orders with all payment methods
  - Verify payment_status is "completed" for all methods
  - Verify paid_at is set to a timestamp
  - Verify amount equals order total_amount

- [x] 12.2 Write property test for payment customer null
  - **Property 17: POS Payment Customer Null**
  - **Validates: Requirements 7.8**
  - Generate random POS orders
  - Verify payment customer_id is always null

- [x] 13. Implement activity logging
  - Inside transaction, use activity() helper to log activity
  - Set causedBy to authenticated user
  - Set performedOn to created order
  - Set log description as "POS order {order_number} created by {staff_name} at {branch_name} for GHS {total_amount}"
  - Use activity type "pos_order_created"
  - _Requirements: 11.1, 11.2, 11.3, 11.4_

- [x] 13.1 Write property test for activity logging
  - **Property 24: Activity Logging**
  - **Validates: Requirements 11.1, 11.2, 11.3, 11.4**
  - Generate random POS orders
  - Verify activity log entry is created
  - Verify log includes staff name, branch name, order number, total amount
  - Verify causedBy is staff member
  - Verify performedOn is order

- [x] 14. Implement response formatting
  - After transaction commits, load order relationships: branch, assignedEmployee, items.menuItem, items.menuItemSize, payments
  - Return OrderResource with loaded order
  - Set HTTP status code to 201 using response()->json()->setStatusCode(201) or JsonResponse with 201 status
  - _Requirements: 1.6, 14.1, 14.2, 14.3, 14.4, 14.5, 14.6_

- [x] 14.1 Write property test for response structure
  - **Property 4: Complete Response Structure**
  - **Validates: Requirements 1.6, 14.2, 14.3, 14.4**
  - Generate random POS orders
  - Verify response includes order with all relationships loaded
  - Verify each item includes menu item details

- [x] 14.2 Write property test for success status code
  - **Property 25: Success Response Status Code**
  - **Validates: Requirements 14.1**
  - Generate random POS orders
  - Verify HTTP status code is 201

- [x] 15. Implement error handling and transaction rollback
  - Wrap transaction in try-catch block
  - Catch any exceptions and log error details
  - Return 500 Internal Server Error response with generic error message
  - Verify transaction automatically rolls back on exception
  - _Requirements: 1.7, 13.2, 13.3, 13.4, 13.5_

- [x] 15.1 Write property test for transaction rollback
  - **Property 5: Transaction Rollback on Failure**
  - **Validates: Requirements 1.7, 13.2**
  - Simulate database errors during order creation
  - Verify no partial data exists after error
  - Verify no order, order items, or payment records are created

- [x] 15.2 Write unit tests for error handling
  - Test 401 response for unauthenticated requests
  - Test 403 response for inactive staff
  - Test 403 response for wrong branch
  - Test 422 response for invalid branch
  - Test 422 response for invalid menu items
  - Test 422 response for price mismatches
  - Test 422 response for invalid payment method
  - Test 422 response for invalid fulfillment type
  - Test 422 response for empty items array
  - Test 500 response for database errors

- [x] 16. Register POS routes
  - Open routes/employee.php
  - Add new route group for POS endpoints: Route::prefix('pos')->group()
  - Add POST route: Route::post('orders', [PosOrderController::class, 'store'])
  - Apply Sanctum auth middleware to route group
  - Import PosOrderController at top of file
  - _Requirements: 1.1_

- [x] 16.1 Write unit tests for route registration
  - Test POST /api/v1/pos/orders route exists
  - Test route requires authentication
  - Test route calls PosOrderController@store

- [x] 17. Checkpoint - Run all tests and verify implementation
  - Run `php artisan test --compact --filter=PosOrder` to execute all POS order tests
  - Verify all property tests pass (minimum 100 iterations each)
  - Verify all unit tests pass
  - Ensure all tests pass, ask the user if questions arise
  - Fix any failing tests before proceeding

- [x] 18. Integration testing with real data
  - Create feature test that creates a complete POS order with real database data
  - Test order creation with single item
  - Test order creation with multiple items
  - Test order creation with variants
  - Test order creation with discount
  - Test order creation with each payment method
  - Test order creation with dine_in fulfillment
  - Test order creation with takeaway fulfillment
  - Verify all relationships are loaded correctly
  - Verify activity log is created
  - Verify payment is marked as completed
  - _Requirements: All requirements_

- [x] 19. Final checkpoint - Ensure all tests pass
  - Run full test suite: `php artisan test --compact`
  - Run Laravel Pint to format code: `vendor/bin/pint --dirty --format agent`
  - Verify no linting issues
  - Ensure all tests pass, ask the user if questions arise

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties across randomized inputs
- Unit tests validate specific examples and edge cases
- All database operations are wrapped in transactions for atomicity
- Price validation includes 0.01 tolerance for rounding differences
- Activity logging provides audit trail for all POS orders
- Snapshots preserve menu item details for historical accuracy
