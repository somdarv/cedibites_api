# Requirements Document

## Introduction

The POS Order Creation feature enables restaurant staff to create orders directly through the Point of Sale (POS) system without requiring customers to use the online cart flow. This feature supports in-person transactions at restaurant branches, allowing staff to take orders, process payments immediately, and generate receipts for dine-in and takeaway customers.

The POS frontend is fully implemented in Next.js with PIN-based authentication, menu browsing, cart management, and receipt printing. This specification defines the backend API requirements to support direct POS order creation, bypassing the cart-based flow used by online customers.

## Glossary

- **POS_System**: The Point of Sale backend API that processes staff-initiated orders
- **Staff_Member**: An authenticated employee with an active account and valid POS PIN
- **POS_Order**: An order created directly by staff through the POS interface, not through the customer cart flow
- **Order_Item**: A menu item with quantity, price, and optional variant included in an order
- **Payment_Record**: A database record tracking payment method, amount, and status for an order
- **Branch**: A restaurant location where staff members work and orders are fulfilled
- **Menu_Item**: A product available for purchase with a defined price and availability status
- **Order_Number**: A unique identifier generated for each order in format "CB" followed by 6 digits
- **Tax_Rate**: The percentage applied to calculate tax amount, currently 2.5% (0.025)
- **Fulfillment_Type**: The method of order completion, either "dine_in" or "takeaway"
- **Payment_Method**: The method used to pay for an order: "cash", "mobile_money", "card", "wallet", or "ghqr"
- **Order_Source**: The channel through which an order was created, including "pos" for POS orders
- **Instant_Payment**: A payment that is completed immediately at the time of order creation

## Requirements

### Requirement 1: POS Order Creation Endpoint

**User Story:** As a staff member, I want to create orders directly through the POS system, so that I can process in-person customer transactions without requiring them to use the online cart.

#### Acceptance Criteria

1. THE POS_System SHALL provide a POST endpoint at `/api/v1/pos/orders` for creating orders
2. WHEN a Staff_Member submits a valid order request, THE POS_System SHALL create an Order record with order_source set to "pos"
3. WHEN a Staff_Member submits a valid order request, THE POS_System SHALL assign the order to the Staff_Member using the assigned_employee_id field
4. WHEN a Staff_Member submits a valid order request, THE POS_System SHALL generate a unique Order_Number in format "CB" followed by 6 digits
5. WHEN an Order_Number collision occurs, THE POS_System SHALL regenerate a new Order_Number until a unique value is found
6. WHEN a POS_Order is created, THE POS_System SHALL return the complete order details including order number, items, totals, and payment information
7. WHEN a POS_Order creation fails, THE POS_System SHALL rollback all database changes and return an error response

### Requirement 2: Staff Authentication and Authorization

**User Story:** As a system administrator, I want to ensure only authenticated staff can create POS orders, so that unauthorized users cannot create fraudulent orders.

#### Acceptance Criteria

1. WHEN a request is made to the POS order endpoint, THE POS_System SHALL require a valid authentication token
2. WHEN an unauthenticated request is made, THE POS_System SHALL return a 401 Unauthorized response
3. WHEN an authenticated Staff_Member creates an order, THE POS_System SHALL verify the Staff_Member has an active employee account
4. WHEN a Staff_Member with an inactive account attempts to create an order, THE POS_System SHALL return a 403 Forbidden response
5. THE POS_System SHALL extract the Staff_Member identity from the authentication token

### Requirement 3: Branch Validation

**User Story:** As a branch manager, I want orders to be created only for branches where staff members work, so that orders are properly assigned to the correct location.

#### Acceptance Criteria

1. WHEN a Staff_Member creates a POS_Order, THE POS_System SHALL verify the specified branch_id exists in the database
2. WHEN a Staff_Member creates a POS_Order, THE POS_System SHALL verify the Staff_Member is assigned to the specified Branch
3. WHEN a Staff_Member attempts to create an order for a Branch they are not assigned to, THE POS_System SHALL return a 403 Forbidden response with message "You are not authorized to create orders for this branch"
4. WHEN a non-existent branch_id is provided, THE POS_System SHALL return a 422 Unprocessable Entity response with message "Invalid branch"

### Requirement 4: Menu Item Validation

**User Story:** As a staff member, I want to ensure all items in an order are valid and available, so that customers receive accurate orders.

#### Acceptance Criteria

1. WHEN a POS_Order contains Order_Items, THE POS_System SHALL verify each Menu_Item exists in the database
2. WHEN a POS_Order contains Order_Items, THE POS_System SHALL verify each Menu_Item belongs to the specified Branch
3. WHEN a POS_Order contains Order_Items with variants, THE POS_System SHALL verify each menu_item_size_id exists and belongs to the corresponding Menu_Item
4. WHEN a POS_Order contains an invalid Menu_Item, THE POS_System SHALL return a 422 Unprocessable Entity response with message "Invalid menu item: {item_name}"
5. WHEN a POS_Order contains a Menu_Item from a different Branch, THE POS_System SHALL return a 422 Unprocessable Entity response with message "Menu item {item_name} is not available at this branch"
6. WHEN a POS_Order contains zero Order_Items, THE POS_System SHALL return a 422 Unprocessable Entity response with message "Order must contain at least one item"

### Requirement 5: Order Calculation

**User Story:** As a staff member, I want the system to automatically calculate order totals, so that pricing is accurate and consistent.

#### Acceptance Criteria

1. WHEN a POS_Order is created, THE POS_System SHALL calculate the subtotal by summing all Order_Item subtotals
2. WHEN a POS_Order is created, THE POS_System SHALL calculate each Order_Item subtotal as quantity multiplied by unit_price
3. WHEN a POS_Order is created, THE POS_System SHALL calculate tax_amount by multiplying subtotal by Tax_Rate (0.025)
4. WHEN a POS_Order is created, THE POS_System SHALL round tax_amount to 2 decimal places
5. WHEN a POS_Order is created, THE POS_System SHALL set delivery_fee to 0.00 for all POS orders
6. WHEN a POS_Order is created, THE POS_System SHALL calculate total_amount as subtotal plus tax_amount plus delivery_fee
7. WHERE a discount is provided, THE POS_System SHALL subtract the discount amount from the subtotal before calculating tax and total

### Requirement 6: Order Type Handling

**User Story:** As a staff member, I want to specify whether an order is for dine-in or takeaway, so that the kitchen knows how to prepare and package the order.

#### Acceptance Criteria

1. WHEN a POS_Order is created with fulfillment_type "dine_in", THE POS_System SHALL set order_type to "pickup"
2. WHEN a POS_Order is created with fulfillment_type "takeaway", THE POS_System SHALL set order_type to "pickup"
3. WHEN a POS_Order is created, THE POS_System SHALL store the original fulfillment_type in the delivery_note field as "Fulfillment: {fulfillment_type}"
4. WHEN an invalid fulfillment_type is provided, THE POS_System SHALL return a 422 Unprocessable Entity response with message "Fulfillment type must be either dine_in or takeaway"

### Requirement 7: Payment Processing

**User Story:** As a staff member, I want to record payment information when creating an order, so that the transaction is properly tracked.

#### Acceptance Criteria

1. WHEN a POS_Order is created, THE POS_System SHALL create a Payment_Record with the specified payment_method
2. WHEN a POS_Order is created with payment_method "cash", THE POS_System SHALL set payment_status to "completed" and paid_at to current timestamp
3. WHEN a POS_Order is created with payment_method "mobile_money", THE POS_System SHALL set payment_status to "completed" and paid_at to current timestamp
4. WHEN a POS_Order is created with payment_method "card", THE POS_System SHALL set payment_status to "completed" and paid_at to current timestamp
5. WHEN a POS_Order is created with payment_method "wallet", THE POS_System SHALL set payment_status to "completed" and paid_at to current timestamp
6. WHEN a POS_Order is created with payment_method "ghqr", THE POS_System SHALL set payment_status to "completed" and paid_at to current timestamp
7. WHEN a Payment_Record is created, THE POS_System SHALL set the amount to the order total_amount
8. WHEN a Payment_Record is created, THE POS_System SHALL set customer_id to null for POS orders
9. IF an invalid payment_method is provided, THEN THE POS_System SHALL return a 422 Unprocessable Entity response with message "Invalid payment method"

### Requirement 8: Customer Contact Information

**User Story:** As a staff member, I want to record customer contact information, so that we can reach customers if needed.

#### Acceptance Criteria

1. WHEN a POS_Order is created, THE POS_System SHALL require contact_name in the request
2. WHEN a POS_Order is created, THE POS_System SHALL require contact_phone in the request
3. WHEN a POS_Order is created, THE POS_System SHALL store contact_name in the order contact_name field
4. WHEN a POS_Order is created, THE POS_System SHALL store contact_phone in the order contact_phone field
5. WHERE customer notes are provided, THE POS_System SHALL append them to the delivery_note field
6. WHEN contact_name is missing, THE POS_System SHALL return a 422 Unprocessable Entity response
7. WHEN contact_phone is missing, THE POS_System SHALL return a 422 Unprocessable Entity response

### Requirement 9: Order Item Snapshots

**User Story:** As a system administrator, I want to preserve menu item details at the time of order, so that historical orders remain accurate even if menu items change.

#### Acceptance Criteria

1. WHEN an Order_Item is created, THE POS_System SHALL store the Menu_Item details in menu_item_snapshot as JSON
2. WHEN an Order_Item is created with a variant, THE POS_System SHALL store the menu_item_size details in menu_item_size_snapshot as JSON
3. WHEN an Order_Item is created without a variant, THE POS_System SHALL set menu_item_size_id to null
4. WHEN an Order_Item is created without a variant, THE POS_System SHALL set menu_item_size_snapshot to null
5. THE menu_item_snapshot SHALL include at minimum: id, name, description, base_price, image_url
6. THE menu_item_size_snapshot SHALL include at minimum: id, size_name, price_adjustment

### Requirement 10: Order Status Initialization

**User Story:** As a kitchen staff member, I want new POS orders to appear in my queue immediately, so that I can start preparing them.

#### Acceptance Criteria

1. WHEN a POS_Order is created, THE POS_System SHALL set the initial status to "received"
2. WHEN a POS_Order is created, THE POS_System SHALL set customer_id to null
3. WHEN a POS_Order is created, THE POS_System SHALL set delivery_address to null
4. WHEN a POS_Order is created, THE POS_System SHALL set delivery_latitude to null
5. WHEN a POS_Order is created, THE POS_System SHALL set delivery_longitude to null

### Requirement 11: Activity Logging

**User Story:** As a system administrator, I want to track POS order creation activities, so that I can audit transactions and troubleshoot issues.

#### Acceptance Criteria

1. WHEN a POS_Order is successfully created, THE POS_System SHALL log an activity event with type "pos_order_created"
2. WHEN a POS_Order is successfully created, THE POS_System SHALL include in the activity log: Staff_Member name, Branch name, Order_Number, and total_amount
3. WHEN a POS_Order is successfully created, THE POS_System SHALL set the activity causedBy to the Staff_Member user
4. WHEN a POS_Order is successfully created, THE POS_System SHALL set the activity performedOn to the created Order

### Requirement 12: Request Validation

**User Story:** As a developer, I want comprehensive request validation, so that invalid data is rejected before processing.

#### Acceptance Criteria

1. THE POS_System SHALL validate that branch_id is a required integer
2. THE POS_System SHALL validate that items is a required array with at least one element
3. THE POS_System SHALL validate that each item contains required fields: menu_item_id, quantity, unit_price
4. THE POS_System SHALL validate that quantity is a positive integer
5. THE POS_System SHALL validate that unit_price is a positive decimal number
6. THE POS_System SHALL validate that payment_method is a required string matching allowed values
7. THE POS_System SHALL validate that fulfillment_type is a required string matching "dine_in" or "takeaway"
8. THE POS_System SHALL validate that contact_name is a required string with maximum length 255
9. THE POS_System SHALL validate that contact_phone is a required string with maximum length 20
10. WHERE discount is provided, THE POS_System SHALL validate it is a non-negative decimal number
11. WHEN validation fails, THE POS_System SHALL return a 422 Unprocessable Entity response with detailed error messages

### Requirement 13: Transaction Integrity

**User Story:** As a system administrator, I want order creation to be atomic, so that partial orders are never created in case of errors.

#### Acceptance Criteria

1. WHEN creating a POS_Order, THE POS_System SHALL wrap all database operations in a transaction
2. WHEN any database operation fails during order creation, THE POS_System SHALL rollback the entire transaction
3. WHEN a transaction is rolled back, THE POS_System SHALL return a 500 Internal Server Error response
4. WHEN a transaction is rolled back, THE POS_System SHALL log the error details for debugging
5. WHEN a POS_Order is successfully created, THE POS_System SHALL commit the transaction before returning the response

### Requirement 14: Response Format

**User Story:** As a frontend developer, I want consistent response formats, so that I can reliably parse API responses.

#### Acceptance Criteria

1. WHEN a POS_Order is successfully created, THE POS_System SHALL return a 201 Created status code
2. WHEN a POS_Order is successfully created, THE POS_System SHALL return the complete order details including relationships
3. THE response SHALL include the order with loaded relationships: branch, assignedEmployee, items, payments
4. THE response SHALL include each Order_Item with its associated Menu_Item details
5. WHEN an error occurs, THE POS_System SHALL return a JSON response with an error message
6. THE response format SHALL match the existing OrderResource structure used by other order endpoints

### Requirement 15: Menu Item Price Validation

**User Story:** As a branch manager, I want to ensure staff cannot manually alter menu item prices, so that pricing integrity is maintained.

#### Acceptance Criteria

1. WHEN a POS_Order is created, THE POS_System SHALL verify each Order_Item unit_price matches the Menu_Item price
2. WHEN a POS_Order contains an Order_Item with a variant, THE POS_System SHALL verify the unit_price matches the base_price plus the size price_adjustment
3. WHEN a POS_Order contains an Order_Item without a variant, THE POS_System SHALL verify the unit_price matches the Menu_Item base_price
4. WHEN a unit_price does not match the expected price, THE POS_System SHALL return a 422 Unprocessable Entity response with message "Price mismatch for item {item_name}: expected {expected_price}, got {provided_price}"
5. THE price validation SHALL allow a tolerance of 0.01 to account for rounding differences
