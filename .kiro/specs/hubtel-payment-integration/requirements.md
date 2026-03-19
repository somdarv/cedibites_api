# Requirements Document

## Introduction

This document specifies the requirements for integrating the Hubtel payment gateway into the cedibites_api Laravel application. Hubtel is a Ghanaian payment platform that enables mobile money payments (MTN, Vodafone, AirtelTigo), card payments (Visa, Mastercard), wallet payments (Hubtel, G-Money, Zeepay), GhQR, and cash/cheque payments. This integration will allow customers to complete payments for orders using their preferred payment method through Hubtel's secure checkout interface, with support for both redirect and onsite (iframe) checkout experiences.

## Glossary

- **Hubtel_Service**: The service class that handles communication with Hubtel's payment API
- **Mobile_Money**: Electronic wallet payment method used in Ghana (MTN, Vodafone, AirtelTigo)
- **Payment_Model**: The Eloquent model representing payment records in the database
- **Callback_Endpoint**: The endpoint that receives payment status updates from Hubtel
- **Client_Reference**: The unique transaction identifier from the merchant (uses Order.order_number)
- **Checkout_Id**: Hubtel's unique transaction reference returned after payment initiation
- **Payment_Status**: The current state of a payment in the application (pending, completed, failed, refunded, cancelled, expired)
- **Hubtel_Status**: The payment status from Hubtel (Success, Paid, Unpaid, Refunded)
- **Basic_Auth**: Authentication method using Base64 encoded client_id:client_secret
- **Callback_Url**: The URL where Hubtel sends final payment status notifications
- **Return_Url**: The URL where customers are redirected after completing payment
- **Cancellation_Url**: The URL where customers are redirected after cancelling payment
- **Merchant_Account_Number**: The POS Sales ID from Hubtel merchant account
- **Checkout_Url**: The redirect URL where customers complete payment on Hubtel's page
- **Checkout_Direct_Url**: The URL for embedding Hubtel checkout in an iframe (onsite integration)
- **Sales_Invoice_Id**: Hubtel's internal invoice identifier returned in callbacks
- **Payment_Channel**: The specific payment method used (mtn-gh, vodafone-gh, visa, mastercard, etc.)
- **Status_Check_API**: Hubtel's API for querying transaction status (requires IP whitelisting)
- **Response_Code**: Hubtel's status code (0000=Success, 0005=Processor error, 2001=Failed, 4000=Validation error, 4070=Fees issue)

## Requirements

### Requirement 1: Payment Initiation

**User Story:** As a customer, I want to initiate a payment for my order, so that I can complete my purchase using my preferred payment method.

#### Acceptance Criteria

1. WHEN a payment initiation request is received with valid order details, THE Hubtel_Service SHALL send a POST request to https://payproxyapi.hubtel.com/items/initiate with Basic_Auth
2. THE Hubtel_Service SHALL include totalAmount, description, callbackUrl, returnUrl, cancellationUrl, merchantAccountNumber, and clientReference in the initiation request
3. THE Hubtel_Service SHALL use Order.order_number as the Client_Reference (maximum 32 characters)
4. WHEN customer details are available, THE Hubtel_Service SHALL include payeeName, payeeMobileNumber, and payeeEmail as optional parameters
5. WHEN Hubtel responds successfully, THE Hubtel_Service SHALL return checkoutUrl, checkoutDirectUrl, checkoutId, and clientReference to the client
6. THE Hubtel_Service SHALL create a Payment_Model record with payment_method='hubtel', payment_status='pending', and transaction_id=checkoutId
7. THE Hubtel_Service SHALL store the complete Hubtel response in Payment_Model.payment_gateway_response JSON field
8. WHEN payment initiation fails, THE Hubtel_Service SHALL parse the Response_Code and return a descriptive error message

### Requirement 2: Payment Method Support

**User Story:** As a customer, I want to choose from multiple payment methods on Hubtel's checkout page, so that I can pay using my preferred option.

#### Acceptance Criteria

1. THE Hubtel_Service SHALL support customer selection of Mobile Money (MTN, Vodafone, AirtelTigo) on Hubtel's checkout page
2. THE Hubtel_Service SHALL support customer selection of Bank Cards (Visa, Mastercard) on Hubtel's checkout page
3. THE Hubtel_Service SHALL support customer selection of Wallet payments (Hubtel, G-Money, Zeepay) on Hubtel's checkout page
4. THE Hubtel_Service SHALL support customer selection of GhQR payment on Hubtel's checkout page
5. THE Hubtel_Service SHALL support customer selection of Cash/Cheque payment on Hubtel's checkout page
6. THE Hubtel_Service SHALL not pre-select or restrict payment channels during initiation
7. WHEN a callback is received, THE Hubtel_Service SHALL extract PaymentType and Channel from PaymentDetails and store in Payment_Model.payment_gateway_response

### Requirement 3: Checkout Integration Options

**User Story:** As a developer, I want to support both redirect and onsite checkout, so that I can choose the integration approach that fits the user experience.

#### Acceptance Criteria

1. WHERE redirect checkout is used, THE Hubtel_Service SHALL provide checkoutUrl for redirecting customers to Hubtel's payment page
2. WHERE onsite checkout is used, THE Hubtel_Service SHALL provide checkoutDirectUrl for embedding Hubtel checkout in an iframe
3. THE Hubtel_Service SHALL return both checkoutUrl and checkoutDirectUrl in the payment initiation response
4. THE Hubtel_Service SHALL configure returnUrl to redirect customers back to the application after successful payment
5. THE Hubtel_Service SHALL configure cancellationUrl to redirect customers back to the application after cancelling payment

### Requirement 4: Payment Callback Handling

**User Story:** As the system, I want to receive payment status updates from Hubtel, so that I can update order statuses automatically.

#### Acceptance Criteria

1. WHEN a callback request is received from Hubtel, THE Callback_Endpoint SHALL parse the JSON payload containing ResponseCode, Status, and Data
2. WHEN ResponseCode is "0000" and Status is "Success", THE Callback_Endpoint SHALL update Payment_Model.payment_status to "completed"
3. WHEN ResponseCode is not "0000", THE Callback_Endpoint SHALL update Payment_Model.payment_status to "failed"
4. THE Callback_Endpoint SHALL extract CheckoutId, SalesInvoiceId, ClientReference, Amount, CustomerPhoneNumber, and PaymentDetails from the callback Data
5. THE Callback_Endpoint SHALL store the complete callback payload in Payment_Model.payment_gateway_response JSON field
6. THE Callback_Endpoint SHALL update Payment_Model.paid_at timestamp when payment_status changes to "completed"
7. WHEN payment status changes to "completed", THE Callback_Endpoint SHALL trigger order fulfillment processes
8. THE Callback_Endpoint SHALL respond to Hubtel with HTTP 200 status to acknowledge receipt
9. THE Callback_Endpoint SHALL log all callback receipts with ResponseCode and Status for audit purposes
10. THE Callback_Endpoint SHALL not perform webhook signature validation (not supported by Hubtel API)

### Requirement 5: Payment Status Verification

**User Story:** As the system, I want to verify payment status directly with Hubtel, so that I can confirm transaction outcomes when callbacks are not received.

#### Acceptance Criteria

1. WHEN no callback is received within 5 minutes of payment initiation, THE Hubtel_Service SHALL query the Status_Check_API
2. THE Hubtel_Service SHALL send GET request to https://api-txnstatus.hubtel.com/transactions/{merchantAccountNumber}/status?clientReference={Client_Reference}
3. WHEN status check returns "Paid" or "Success", THE Hubtel_Service SHALL update Payment_Model.payment_status to "completed"
4. WHEN status check returns "Unpaid", THE Hubtel_Service SHALL keep Payment_Model.payment_status as "pending"
5. WHEN status check returns "Refunded", THE Hubtel_Service SHALL update Payment_Model.payment_status to "refunded"
6. THE Hubtel_Service SHALL extract transactionId, externalTransactionId, amount, and charges from status check response
7. THE Hubtel_Service SHALL store the complete status check response in Payment_Model.payment_gateway_response JSON field
8. THE Hubtel_Service SHALL support manual verification requests via an API endpoint
9. WHEN verification is requested for a non-existent Client_Reference, THE Hubtel_Service SHALL return an error response
10. THE Hubtel_Service SHALL log all verification attempts with timestamp and result
11. THE Hubtel_Service SHALL handle IP whitelisting requirement by documenting server IP for Hubtel configuration

### Requirement 6: Payment Model Integration

**User Story:** As a developer, I want payment data stored in the existing Payment model, so that Hubtel payments integrate seamlessly with the current system.

#### Acceptance Criteria

1. THE Hubtel_Service SHALL add 'mobile_money', 'card', 'wallet', 'ghqr', and 'cash' to the Payment_Model.payment_method enum values
2. THE Hubtel_Service SHALL add 'cancelled' and 'expired' to the Payment_Model.payment_status enum values
3. WHEN a payment is initiated, THE Hubtel_Service SHALL create a Payment_Model record with order_id, customer_id (nullable for guest customers), payment_method (determined from callback), payment_status='pending', amount, and transaction_id=Checkout_Id
4. THE Hubtel_Service SHALL store Hubtel's Checkout_Id in Payment_Model.transaction_id field
5. THE Hubtel_Service SHALL store complete Hubtel responses (initiation, callback, status check) in Payment_Model.payment_gateway_response JSON field
6. WHEN payment is completed, THE Hubtel_Service SHALL update Payment_Model.paid_at timestamp
7. WHEN payment is refunded, THE Hubtel_Service SHALL update Payment_Model.refunded_at timestamp and refund_reason
8. THE Hubtel_Service SHALL maintain Payment_Model relationships with Order and Customer models (customer_id is nullable to support guest orders)
9. THE Hubtel_Service SHALL leverage existing Payment_Model activity logging for audit trail

### Requirement 7: Hubtel Service Implementation

**User Story:** As a developer, I want a dedicated Hubtel service class, so that payment logic is organized and follows existing patterns.

#### Acceptance Criteria

1. THE Hubtel_Service SHALL be implemented as app/Services/HubtelPaymentService.php (similar to how PaystackService was structured)
2. THE Hubtel_Service constructor SHALL load client_id, client_secret, and merchant_account_number from config('services.hubtel')
3. THE Hubtel_Service SHALL use Laravel HTTP client with Basic_Auth (Base64 encoded client_id:client_secret)
4. THE Hubtel_Service SHALL implement initializeTransaction() method that returns checkoutUrl, checkoutDirectUrl, checkoutId, and clientReference
5. THE Hubtel_Service SHALL implement verifyTransaction() method that queries Status_Check_API and returns normalized status
6. THE Hubtel_Service SHALL implement handleCallback() method that processes callback payload and updates Payment_Model
7. WHEN Hubtel API requests fail, THE Hubtel_Service SHALL log the request, response, and error details with context
8. THE Hubtel_Service SHALL not implement webhook signature validation (not supported by Hubtel)

### Requirement 8: API Endpoints

**User Story:** As a frontend developer, I want RESTful API endpoints for Hubtel payments, so that I can integrate payment functionality into the application interface.

#### Acceptance Criteria

1. THE Hubtel_Service SHALL integrate with existing OrderController or create PaymentController in app/Http/Controllers/Api/
2. THE Hubtel_Service SHALL provide POST /api/orders/{order}/payments/hubtel/initiate endpoint for initiating payments
3. THE Hubtel_Service SHALL provide POST /api/payments/hubtel/callback endpoint for receiving Hubtel callbacks (no authentication)
4. THE Hubtel_Service SHALL provide GET /api/payments/{payment}/verify endpoint for manual status verification
5. THE Hubtel_Service SHALL require Sanctum authentication for all endpoints except callback
6. THE Hubtel_Service SHALL use Laravel Form Request classes for validation
7. THE Hubtel_Service SHALL return responses using PaymentResource or similar API Resource with consistent JSON structure
8. WHEN validation fails on any endpoint, THE Hubtel_Service SHALL return HTTP 422 with detailed error messages
9. THE Hubtel_Service SHALL use existing response() helpers (success, error, created) for consistent API responses

### Requirement 9: Response Code Handling

**User Story:** As the system, I want to handle all Hubtel response codes appropriately, so that users receive accurate feedback about payment outcomes.

#### Acceptance Criteria

1. WHEN Response_Code is "0000", THE Hubtel_Service SHALL treat the response as successful
2. WHEN Response_Code is "0005", THE Hubtel_Service SHALL log "Payment processor error or network issue" and return error to client
3. WHEN Response_Code is "2001", THE Hubtel_Service SHALL log "Transaction failed" and update Payment_Model.payment_status to "failed"
4. WHEN Response_Code is "4000", THE Hubtel_Service SHALL log "Validation error" and return detailed validation messages to client
5. WHEN Response_Code is "4070", THE Hubtel_Service SHALL log "Fees not set or minimum amount issue" and return error to client
6. THE Hubtel_Service SHALL map Hubtel_Status values ("Success", "Paid", "Unpaid", "Refunded") to Payment_Model.payment_status values ("completed", "completed", "pending", "refunded")
7. THE Hubtel_Service SHALL store the original Response_Code and Hubtel_Status in Payment_Model.payment_gateway_response for reference

### Requirement 10: Configuration Management

**User Story:** As a system administrator, I want Hubtel credentials and settings managed through Laravel configuration, so that deployment is secure and flexible.

#### Acceptance Criteria

1. THE Hubtel_Service SHALL retrieve client_id from config('services.hubtel.client_id') mapped to HUBTEL_CLIENT_ID environment variable
2. THE Hubtel_Service SHALL retrieve client_secret from config('services.hubtel.client_secret') mapped to HUBTEL_CLIENT_SECRET environment variable
3. THE Hubtel_Service SHALL retrieve merchant_account_number from config('services.hubtel.merchant_account_number') mapped to HUBTEL_MERCHANT_ACCOUNT_NUMBER environment variable
4. THE Hubtel_Service SHALL not expose client_id or client_secret in API responses or logs
5. WHEN credentials are missing from configuration, THE Hubtel_Service SHALL throw a configuration exception with descriptive message
6. THE Hubtel_Service SHALL support separate sandbox and production API endpoints via config('services.hubtel.base_url')
7. WHERE environment is sandbox, THE Hubtel_Service SHALL use Hubtel's test API endpoints
8. THE Hubtel_Service SHALL configure callback_url, return_url, and cancellation_url from application routes

### Requirement 11: Error Handling and Logging

**User Story:** As a developer, I want comprehensive error handling and logging, so that I can troubleshoot payment issues effectively.

#### Acceptance Criteria

1. WHEN Hubtel API requests fail, THE Hubtel_Service SHALL log the request URL, Response_Code, and error details with context
2. WHEN network errors occur, THE Hubtel_Service SHALL retry the request up to 3 times with exponential backoff
3. IF all retry attempts fail, THEN THE Hubtel_Service SHALL return an error response to the client
4. THE Hubtel_Service SHALL log all payment initiation requests with order_id, amount, and Client_Reference
5. THE Hubtel_Service SHALL log all callback receipts with ResponseCode, Status, and CheckoutId
6. WHEN exceptions occur during payment processing, THE Hubtel_Service SHALL log the stack trace and return a generic error message to the client
7. THE Hubtel_Service SHALL not log sensitive data including client_secret, full card numbers, or customer passwords
8. THE Hubtel_Service SHALL sanitize customer phone numbers and emails in logs (show only first 3 and last 2 characters)

### Requirement 12: Payment Request Validation

**User Story:** As the system, I want payment requests validated before processing, so that invalid data is rejected early.

#### Acceptance Criteria

1. WHEN a payment initiation request is received, THE Hubtel_Service SHALL validate that order_id exists in the database
2. WHEN a payment initiation request is received, THE Hubtel_Service SHALL validate that totalAmount is a positive number with maximum 2 decimal places
3. THE Hubtel_Service SHALL validate that description is provided and not empty
4. THE Hubtel_Service SHALL validate that Client_Reference does not exceed 32 characters
5. WHEN payeeMobileNumber is provided, THE Hubtel_Service SHALL validate it matches Ghana phone number format (233XXXXXXXXX)
6. WHEN payeeEmail is provided, THE Hubtel_Service SHALL validate it is a properly formatted email address
7. THE Hubtel_Service SHALL use Laravel Form Request classes for all validation logic
8. WHEN validation fails, THE Hubtel_Service SHALL return HTTP 422 with field-specific error messages

### Requirement 13: Testing Support

**User Story:** As a developer, I want comprehensive test coverage using Pest 4, so that payment functionality is reliable and maintainable.

#### Acceptance Criteria

1. THE Hubtel_Service SHALL include Pest feature tests for payment initiation with valid order data
2. THE Hubtel_Service SHALL include Pest feature tests for payment initiation with invalid data (missing fields, invalid amounts)
3. THE Hubtel_Service SHALL include Pest feature tests for callback handling with Response_Code "0000" (success)
4. THE Hubtel_Service SHALL include Pest feature tests for callback handling with Response_Code "2001" (failed)
5. THE Hubtel_Service SHALL include Pest feature tests for payment status verification via Status_Check_API
6. THE Hubtel_Service SHALL include Pest unit tests for Response_Code mapping logic
7. THE Hubtel_Service SHALL include Pest unit tests for Hubtel_Status to Payment_Status conversion
8. THE Hubtel_Service SHALL use existing Payment model factory for creating test payment records
9. THE Hubtel_Service SHALL use HTTP fake for mocking Hubtel API responses in tests
10. WHERE sandbox environment is configured, THE Hubtel_Service SHALL support testing with Hubtel's test credentials
11. THE Hubtel_Service SHALL include tests verifying Payment_Model.payment_gateway_response stores complete Hubtel responses
12. THE Hubtel_Service SHALL include tests verifying paid_at timestamp is set when payment completes

### Requirement 14: Callback Payload Parsing

**User Story:** As a developer, I want reliable parsing of Hubtel callback payloads, so that payment data is correctly extracted and stored.

#### Acceptance Criteria

1. WHEN a callback payload is received, THE Hubtel_Service SHALL parse the JSON structure containing ResponseCode, Status, and Data fields
2. THE Hubtel_Service SHALL extract CheckoutId, SalesInvoiceId, ClientReference, Status, Amount, CustomerPhoneNumber, and PaymentDetails from Data object
3. THE Hubtel_Service SHALL extract MobileMoneyNumber, PaymentType, and Channel from PaymentDetails object
4. WHEN parsing fails due to malformed JSON, THE Hubtel_Service SHALL log the error and return HTTP 400 to Hubtel
5. WHEN required fields are missing from callback payload, THE Hubtel_Service SHALL log the missing fields and return HTTP 400 to Hubtel
6. THE Hubtel_Service SHALL include property-based tests that verify parsing callback JSON then serializing back to JSON produces equivalent structure (round-trip property)
7. THE Hubtel_Service SHALL include tests with various callback payload structures (success, failed, different payment channels)
8. THE Hubtel_Service SHALL validate that Amount in callback matches Payment_Model.amount within acceptable tolerance (0.01 GHS)

### Requirement 15: Guest Customer Support

**User Story:** As a guest customer without an account, I want to complete payment for my order, so that I can purchase without creating an account.

#### Acceptance Criteria

1. WHEN a guest customer initiates payment, THE Payment_Gateway SHALL support orders where customer_id is null
2. THE Payment_Gateway SHALL use order contact information (contact_name, contact_phone) for Hubtel's payeeName and payeeMobileNumber when customer_id is null
3. WHEN creating a Payment record for a guest order, THE Payment_Gateway SHALL set customer_id to null
4. THE Payment_Gateway SHALL use the order_number for callback identification regardless of whether customer_id is null or not
5. WHEN a callback is received for a guest order, THE Payment_Gateway SHALL successfully locate and update the Payment record using clientReference (order_number)
6. THE Payment_Gateway SHALL support payment verification for guest orders via the order_number
7. THE Payment_Gateway SHALL not require Sanctum authentication for the callback endpoint (already public)
8. THE Payment_Gateway SHALL support optional authentication for the payment initiation endpoint using 'optional.auth' middleware
