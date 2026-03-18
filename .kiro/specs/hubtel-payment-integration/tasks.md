# Implementation Plan: Hubtel Payment Integration

## Overview

This implementation plan breaks down the Hubtel payment gateway integration into discrete, actionable tasks. The implementation follows Laravel 12 conventions and mirrors the existing PaystackService pattern for consistency. Tasks are ordered to build incrementally, with early validation through tests and checkpoints.

The integration enables customers (both authenticated and guest) to complete order payments using multiple payment methods through Hubtel's secure checkout interface, with support for both redirect and onsite (iframe) checkout experiences.

## Tasks

- [x] 1. Set up database schema and configuration
  - [x] 1.1 Update existing payments table migration
    - Modify `database/migrations/2025_02_20_000013_create_payments_table.php`
    - Update payment_method enum to: 'mobile_money', 'card', 'wallet', 'ghqr', 'cash'
    - Update payment_status enum to: 'pending', 'completed', 'failed', 'refunded', 'cancelled', 'expired'
    - _Requirements: 6.1, 6.2_
  
  - [x] 1.2 Run migrate:fresh with seed
    - Execute `php artisan migrate:fresh --seed`
    - Verify enum values updated correctly in database
    - _Requirements: 6.1, 6.2_
  
  - [x] 1.3 Update config/services.php with Hubtel configuration
    - Add 'hubtel' configuration block with client_id, client_secret, merchant_account_number, base_url, status_check_url
    - _Requirements: 10.1, 10.2, 10.3, 10.6_
  
  - [x] 1.4 Update .env.example with Hubtel environment variables
    - Add HUBTEL_CLIENT_ID, HUBTEL_CLIENT_SECRET, HUBTEL_MERCHANT_ACCOUNT_NUMBER, HUBTEL_BASE_URL, HUBTEL_STATUS_CHECK_URL
    - _Requirements: 10.1, 10.2, 10.3_


- [x] 2. Implement HubtelService core functionality
  - [x] 2.1 Create HubtelService class with constructor and configuration loading
    - Use `php artisan make:class Services/HubtelService --no-interaction`
    - Implement constructor with configuration loading from config('services.hubtel')
    - Add configuration validation that throws RuntimeException if credentials missing
    - _Requirements: 7.1, 7.2, 10.1, 10.2, 10.3, 10.5_
  
  - [x] 2.2 Write unit tests for HubtelService constructor
    - Test constructor loads configuration correctly
    - Test constructor throws exception when credentials missing
    - _Requirements: 7.2, 10.5_
  
  - [x] 2.3 Implement Basic Auth header generation method
    - Create protected getAuthHeader() method
    - Return Base64 encoded client_id:client_secret
    - _Requirements: 7.3, 17_
  
  - [x] 2.4 Write unit test for Basic Auth header format
    - **Property 17: Basic Authentication Format**
    - **Validates: Requirements 7.3**
  
  - [x] 2.5 Implement response code to message mapping
    - Create protected mapResponseCodeToMessage() method
    - Map codes: 0000→success, 0005→processor error, 2001→failed, 4000→validation, 4070→fees
    - _Requirements: 1.8, 9.1, 9.2, 9.3, 9.4, 9.5_
  
  - [x] 2.6 Write unit tests for response code mapping
    - **Property 7: Error Response Code Mapping**
    - **Validates: Requirements 1.8, 9.1, 9.2, 9.3, 9.4, 9.5**
  
  - [x] 2.7 Implement Hubtel status to payment status mapping
    - Create protected mapHubtelStatusToPaymentStatus() method
    - Map: (Success|Paid + 0000)→completed, Unpaid→pending, Refunded→refunded, (any + non-0000)→failed
    - _Requirements: 4.2, 4.3, 5.3, 9.6_
  
  - [x] 2.8 Write unit tests for status mapping
    - **Property 10: Status Mapping Consistency**
    - **Validates: Requirements 4.2, 4.3, 5.3, 9.6**

- [x] 3. Implement payment initiation functionality
  - [x] 3.1 Implement initializeTransaction() method in HubtelService
    - Accept array with order, customer details, description
    - Build Hubtel API request payload with all required fields
    - Use clientReference = order->order_number (max 32 chars)
    - Include optional customer details if provided
    - Create Payment record with payment_method='pending' (will be updated by callback), payment_status='pending'
    - Send POST request to Hubtel initiate endpoint with Basic Auth
    - Store complete response in payment_gateway_response
    - Return normalized response with checkout URLs
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 6.3, 6.4, 6.5, 7.4_
  
  - [x] 3.2 Write property test for payment initiation request completeness
    - **Property 1: Payment Initiation Request Completeness**
    - **Validates: Requirements 1.2, 3.4, 3.5**
  
  - [x] 3.3 Write property test for client reference derivation
    - **Property 2: Client Reference Derivation**
    - **Validates: Requirements 1.3, 12.4**
  
  - [x] 3.4 Write property test for optional customer details inclusion
    - **Property 3: Optional Customer Details Inclusion**
    - **Validates: Requirements 1.4**
  
  - [x] 3.5 Write property test for checkout URLs response structure
    - **Property 4: Checkout URLs Response Structure**
    - **Validates: Requirements 1.5, 3.1, 3.2, 3.3, 7.4**
  
  - [x] 3.6 Write property test for payment record creation
    - **Property 5: Payment Record Creation**
    - **Validates: Requirements 1.6, 6.3, 6.4**
  
  - [x] 3.7 Write property test for gateway response persistence
    - **Property 6: Gateway Response Persistence**
    - **Validates: Requirements 1.7, 4.5, 5.7, 6.5, 9.7**
  
  - [x] 3.8 Write feature test for payment initiation with valid data
    - Test authenticated customer can initiate payment
    - Test guest customer can initiate payment
    - Verify Payment record created with correct fields
    - Use HTTP fake for Hubtel API
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 13.1_


- [x] 4. Implement callback handling functionality
  - [x] 4.1 Implement handleCallback() method in HubtelService
    - Parse callback JSON payload (ResponseCode, Status, Data)
    - Extract CheckoutId, SalesInvoiceId, ClientReference, Amount, CustomerPhoneNumber, PaymentDetails
    - Map PaymentDetails.PaymentType to payment_method: 'mobilemoney'→'mobile_money', 'card'→'card', 'wallet'→'wallet', 'ghqr'→'ghqr', 'cash'→'cash'
    - Find Payment record by clientReference (order_number)
    - Map Hubtel status to payment_status using mapHubtelStatusToPaymentStatus()
    - Update Payment record with payment_method and new status
    - Store complete callback in payment_gateway_response
    - Set paid_at timestamp if status is 'completed'
    - Trigger order fulfillment if payment completed
    - Log callback receipt with ResponseCode and Status
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.9, 2.7, 14.1, 14.2, 14.3_
  
  - [x] 4.2 Write property test for callback payment details extraction
    - **Property 8: Callback Payment Details Extraction**
    - **Validates: Requirements 2.7, 14.3**
  
  - [x] 4.3 Write property test for callback JSON parsing
    - **Property 9: Callback JSON Parsing**
    - **Validates: Requirements 4.1, 4.4, 14.1, 14.2**
  
  - [x] 4.4 Write property test for payment completion timestamp
    - **Property 11: Payment Completion Timestamp**
    - **Validates: Requirements 4.6, 6.6**
  
  - [x] 4.5 Write property test for order fulfillment trigger
    - **Property 12: Order Fulfillment Trigger**
    - **Validates: Requirements 4.7**
  
  - [x] 4.6 Write property test for callback JSON round-trip
    - **Property 27: Callback JSON Round-Trip**
    - **Validates: Requirements 14.6**
  
  - [x] 4.7 Write property test for callback amount validation
    - **Property 28: Callback Amount Validation**
    - **Validates: Requirements 14.8**
  
  - [x] 4.8 Write feature test for callback handling with success response
    - Test callback with ResponseCode "0000" updates payment to completed
    - Test paid_at timestamp is set
    - Test order fulfillment is triggered
    - _Requirements: 4.2, 4.6, 4.7, 13.3_
  
  - [x] 4.9 Write feature test for callback handling with failed response
    - Test callback with ResponseCode "2001" updates payment to failed
    - Test paid_at remains null
    - _Requirements: 4.3, 13.4_
  
  - [x] 4.10 Write unit test for malformed callback JSON handling
    - Test handleCallback() logs error and throws exception for invalid JSON
    - _Requirements: 14.4_

- [x] 5. Implement payment verification functionality
  - [x] 5.1 Implement verifyTransaction() method in HubtelService
    - Accept clientReference (order_number) parameter
    - Send GET request to Status Check API with Basic Auth
    - Parse response (transactionId, externalTransactionId, amount, charges, status)
    - Map Hubtel status to payment_status
    - Update Payment record if status changed
    - Store complete response in payment_gateway_response
    - Return normalized transaction status
    - Log verification attempt with timestamp and result
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 5.10, 7.5_
  
  - [x] 5.2 Write property test for status check response parsing
    - **Property 14: Status Check Response Parsing**
    - **Validates: Requirements 5.6**
  
  - [x] 5.3 Write feature test for manual payment verification
    - Test verifyTransaction() queries Status Check API
    - Test Payment record updated when status changes
    - Use HTTP fake for Hubtel API
    - _Requirements: 5.1, 5.2, 5.3, 5.8, 13.5_
  
  - [x] 5.4 Write unit test for non-existent client reference
    - Test verifyTransaction() returns error for invalid clientReference
    - _Requirements: 5.9_


- [x] 6. Implement error handling and retry logic
  - [x] 6.1 Implement executeWithRetry() method in HubtelService
    - Accept callable request and maxRetries parameter (default 3)
    - Implement exponential backoff (1s, 2s, 4s)
    - Catch ConnectionException and retry
    - Log retry attempts with context
    - Throw exception after all retries exhausted
    - _Requirements: 11.2, 11.3_
  
  - [x] 6.2 Write unit test for retry logic
    - **Property 22: Network Retry Logic**
    - **Validates: Requirements 11.2**
  
  - [x] 6.3 Implement data sanitization for logging
    - Create protected sanitizeForLogging() method
    - Mask phone numbers (show first 3 and last 2 digits)
    - Mask emails (show first 3 chars and domain)
    - Remove sensitive fields (client_secret)
    - _Requirements: 10.4, 11.7, 11.8_
  
  - [x] 6.4 Write unit test for sensitive data sanitization
    - **Property 25: Sensitive Data Sanitization in Logs**
    - **Validates: Requirements 11.8**
  
  - [x] 6.5 Add comprehensive logging throughout HubtelService
    - Log payment initiation with order_id, amount, clientReference
    - Log callback receipt with ResponseCode, Status, CheckoutId
    - Log verification attempts with timestamp and result
    - Log API errors with endpoint, response_code, message
    - Use sanitizeForLogging() for all log entries
    - _Requirements: 11.1, 11.4, 11.5, 11.6_
  
  - [x] 6.6 Write property test for payment initiation logging
    - **Property 23: Payment Initiation Logging**
    - **Validates: Requirements 11.4**
  
  - [x] 6.7 Write property test for exception logging and safe error response
    - **Property 24: Exception Logging and Safe Error Response**
    - **Validates: Requirements 11.6**

- [x] 7. Create Form Request validation classes
  - [x] 7.1 Create InitiateHubtelPaymentRequest class
    - Use `php artisan make:request InitiateHubtelPaymentRequest --no-interaction`
    - Add validation rules: customer_name (nullable, string, max:255), customer_phone (nullable, regex:233[0-9]{9}), customer_email (nullable, email), description (required, string, max:500)
    - Implement authorize() method to check order ownership for authenticated users
    - Verify order is payable (not already completed)
    - Add custom error messages for each field
    - _Requirements: 8.6, 12.1, 12.2, 12.3, 12.5, 12.6, 12.7, 15.8_
  
  - [x] 7.2 Write unit tests for InitiateHubtelPaymentRequest validation
    - Test validation passes with valid data
    - Test validation fails with invalid phone format
    - Test validation fails with invalid email format
    - Test validation fails with missing description
    - _Requirements: 12.5, 12.6, 12.7, 13.2_
  
  - [x] 7.3 Write property test for input validation completeness
    - **Property 26: Input Validation Completeness**
    - **Validates: Requirements 12.1, 12.2, 12.3, 12.5, 12.6**
  
  - [x] 7.4 Write unit test for authorization logic
    - Test authenticated user can access their own order
    - Test authenticated user cannot access other user's order
    - Test guest user can access order without customer_id
    - Test authorization fails for already completed orders
    - _Requirements: 15.1, 15.8_


- [-] 8. Implement PaymentController endpoints
  - [x] 8.1 Create or update PaymentController with Hubtel endpoints
    - Create PaymentController if it doesn't exist: `php artisan make:controller Api/PaymentController --no-interaction`
    - Inject HubtelService via constructor
    - _Requirements: 8.1_
  
  - [x] 8.2 Implement initiateHubtelPayment() method
    - Accept InitiateHubtelPaymentRequest and Order parameters
    - Support both authenticated and guest customers
    - Use order contact info for guest customers (contact_name, contact_phone)
    - Call HubtelService->initializeTransaction()
    - Return PaymentResource with success message
    - Handle exceptions and return appropriate error responses
    - _Requirements: 8.2, 8.5, 8.7, 15.1, 15.2, 15.3_
  
  - [x] 8.3 Implement hubtelCallback() method
    - Accept Request parameter (no authentication required)
    - Call HubtelService->handleCallback()
    - Return HTTP 200 acknowledgment to Hubtel
    - Handle exceptions and log errors
    - _Requirements: 8.3, 4.8, 4.10_
  
  - [x] 8.4 Implement verifyPayment() method
    - Accept Payment parameter (route model binding)
    - Require Sanctum authentication
    - Call HubtelService->verifyTransaction()
    - Return PaymentResource with verification result
    - _Requirements: 8.4, 8.5, 5.8, 15.6_
  
  - [x] 8.5 Write property test for callback acknowledgment
    - **Property 13: Callback Acknowledgment**
    - **Validates: Requirements 4.8**
  
  - [x] 8.6 Write property test for API response structure consistency
    - **Property 18: API Response Structure Consistency**
    - **Validates: Requirements 8.7**
  
  - [x] 8.7 Write property test for validation error response format
    - **Property 19: Validation Error Response Format**
    - **Validates: Requirements 8.8, 12.8**

- [ ] 9. Update PaymentResource for Hubtel responses
  - [x] 9.1 Update or create PaymentResource class
    - Create if doesn't exist: `php artisan make:resource PaymentResource --no-interaction`
    - Include fields: id, order_id, payment_method, payment_status, amount, transaction_id, paid_at
    - Extract checkout_url and checkout_direct_url from payment_gateway_response
    - Format timestamps as ISO8601
    - _Requirements: 8.7, 1.5, 3.1, 3.2, 7.4_
  
  - [x] 9.2 Write property test for credential security in responses
    - **Property 20: Credential Security in Responses**
    - **Validates: Requirements 10.4, 11.7**

- [x] 10. Register routes for Hubtel payment endpoints
  - [x] 10.1 Add Hubtel routes to routes/api.php
    - Add POST /api/orders/{order}/payments/hubtel/initiate with optional.auth middleware
    - Add POST /api/payments/hubtel/callback (no authentication)
    - Add GET /api/payments/{payment}/verify with auth:sanctum middleware
    - Use named routes for URL generation
    - _Requirements: 8.2, 8.3, 8.4, 8.5, 10.8, 15.7, 15.8_
  
  - [x] 10.2 Write property test for URL generation from routes
    - **Property 21: URL Generation from Routes**
    - **Validates: Requirements 10.8**
  
  - [x] 10.3 Write feature test for endpoint authentication requirements
    - Test initiate endpoint works with and without authentication
    - Test callback endpoint works without authentication
    - Test verify endpoint requires authentication
    - _Requirements: 8.5, 15.7, 15.8_


- [x] 11. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 12. Update Payment model factory for Hubtel testing
  - [x] 12.1 Add Hubtel-specific factory states to PaymentFactory
    - Add mobileMoney() state with payment_method='mobile_money' and transaction_id
    - Add card() state with payment_method='card' and transaction_id
    - Add wallet() state with payment_method='wallet' and transaction_id
    - Add pending() state with payment_status='pending'
    - Add completed() state with payment_status='completed' and paid_at
    - _Requirements: 13.8_
  
  - [x] 12.2 Write tests using new factory states
    - Test factory creates valid Hubtel payment records
    - _Requirements: 13.8_

- [x] 13. Implement guest customer support
  - [x] 13.1 Verify Payment model supports nullable customer_id
    - Check existing migration allows customer_id to be null
    - _Requirements: 6.3, 6.8, 15.1, 15.3_
  
  - [x] 13.2 Update HubtelService to handle guest customers
    - Use order contact_name for payeeName when customer_id is null
    - Use order contact_phone for payeeMobileNumber when customer_id is null
    - Set customer_id to null in Payment record for guest orders
    - _Requirements: 15.2, 15.3_
  
  - [x] 13.3 Write feature test for guest customer payment initiation
    - Test guest can initiate payment without authentication
    - Test Payment record created with customer_id=null
    - Test order contact info used for Hubtel payer details
    - _Requirements: 15.1, 15.2, 15.3, 15.8_
  
  - [x] 13.4 Write feature test for guest customer callback handling
    - Test callback for guest order updates Payment correctly
    - Test callback uses order_number for identification
    - _Requirements: 15.4, 15.5_

- [x] 14. Implement Activity Log integration
  - [x] 14.1 Verify Payment model uses Spatie Activity Log
    - Check if Payment model already has activity logging trait
    - Add trait if missing
    - _Requirements: 6.9_
  
  - [x] 14.2 Write property test for activity logging integration
    - **Property 16: Activity Logging Integration**
    - **Validates: Requirements 6.9**
  
  - [x] 14.3 Write feature test for payment status change logging
    - Test payment status changes are logged
    - Test log includes payment_status, amount, payment_method
    - _Requirements: 6.9_

- [x] 15. Implement refund data recording
  - [x] 15.1 Update HubtelService to handle refund status
    - When status is 'refunded', set refunded_at timestamp
    - Store refund_reason if provided in callback
    - _Requirements: 5.5, 6.7_
  
  - [x] 15.2 Write property test for refund data recording
    - **Property 15: Refund Data Recording**
    - **Validates: Requirements 6.7**
  
  - [x] 15.3 Write feature test for refund callback handling
    - Test callback with status "Refunded" updates payment correctly
    - Test refunded_at timestamp is set
    - _Requirements: 5.5, 6.7_


- [x] 16. Run comprehensive integration tests
  - [x] 16.1 Write end-to-end feature test for complete payment flow
    - Test initiate → callback → verify flow
    - Test authenticated customer flow
    - Test guest customer flow
    - Use HTTP fake for all Hubtel API calls
    - _Requirements: 1.1, 4.1, 5.1, 15.1_
  
  - [x] 16.2 Write feature test for payment method support
    - Test callback with different PaymentType values (mobilemoney, card, wallet)
    - Test callback with different Channel values (mtn-gh, vodafone-gh, visa, mastercard)
    - Verify PaymentDetails stored correctly
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.7_
  
  - [x] 16.3 Write feature test for checkout integration options
    - Test initiation response includes both checkoutUrl and checkoutDirectUrl
    - Test returnUrl and cancellationUrl configured correctly
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_
  
  - [x] 16.4 Run all Hubtel tests with coverage
    - Execute `php artisan test --filter=Hubtel --compact`
    - Verify minimum 90% line coverage for HubtelService
    - _Requirements: 13.1-13.12_

- [x] 17. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 18. Code formatting and quality checks
  - [x] 18.1 Run Laravel Pint to format code
    - Execute `vendor/bin/pint --dirty --format agent`
    - Fix any formatting issues
    - _Laravel Boost Guidelines: pint rules_
  
  - [x] 18.2 Check for diagnostics in all created files
    - Use getDiagnostics tool on all new PHP files
    - Fix any type errors, syntax issues, or warnings
    - _Laravel Boost Guidelines: foundation rules_

- [x] 19. Documentation and deployment preparation
  - [x] 19.1 Verify .env.example includes all Hubtel variables
    - Confirm HUBTEL_CLIENT_ID, HUBTEL_CLIENT_SECRET, HUBTEL_MERCHANT_ACCOUNT_NUMBER present
    - Confirm HUBTEL_BASE_URL and HUBTEL_STATUS_CHECK_URL with defaults
    - _Requirements: 10.1, 10.2, 10.3_
  
  - [x] 19.2 Document IP whitelisting requirement
    - Add comment in config/services.php about Status Check API IP whitelisting
    - _Requirements: 5.11_
  
  - [x] 19.3 Verify all routes are registered and named correctly
    - Check routes/api.php has all three Hubtel endpoints
    - Verify named routes for callback, return, and cancellation URLs
    - _Requirements: 8.2, 8.3, 8.4, 10.8_

- [x] 20. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- Feature tests validate end-to-end functionality through HTTP requests
- The implementation follows Laravel 12 conventions and existing PaystackService patterns
- Guest customer support is integrated throughout (customer_id nullable)
- All Hubtel API interactions use HTTP fake for testing
- Comprehensive logging and error handling included in all service methods

## Implementation Order Rationale

1. **Database and Configuration (Tasks 1)**: Foundation for all subsequent work
2. **Core Service (Tasks 2-6)**: Build HubtelService incrementally with tests
3. **Validation (Task 7)**: Ensure input validation before controller implementation
4. **Controllers and Routes (Tasks 8-10)**: Wire up HTTP endpoints
5. **Supporting Features (Tasks 12-15)**: Factory states, guest support, activity log, refunds
6. **Integration Testing (Task 16)**: Verify complete flows work end-to-end
7. **Quality and Documentation (Tasks 18-19)**: Polish and prepare for deployment

Each major section includes checkpoint tasks to validate progress before moving forward.
