# Design Document: Hubtel Payment Integration

## Overview

This design document specifies the technical implementation for integrating Hubtel payment gateway into the cedibites_api Laravel application. The integration enables customers to complete order payments using multiple payment methods including mobile money (MTN, Vodafone, AirtelTigo), bank cards (Visa, Mastercard), digital wallets (Hubtel, G-Money, Zeepay), GhQR, and cash/cheque payments.

The implementation follows Laravel 12 conventions and mirrors the existing PaystackService pattern for consistency. The design supports both redirect and onsite (iframe) checkout experiences, handles asynchronous payment callbacks, and provides manual payment verification capabilities.

### Key Design Principles

- **Consistency**: Follow existing PaystackService patterns for service structure and error handling
- **Security**: Use Basic Authentication for API requests, store credentials in environment configuration
- **Reliability**: Implement callback handling with fallback to manual verification
- **Maintainability**: Leverage Laravel's built-in features (HTTP client, Form Requests, API Resources)
- **Testability**: Design for comprehensive Pest test coverage with HTTP fakes

### Integration Flow

1. Customer initiates payment for an order
2. Application calls HubtelPaymentService to initialize transaction
3. Hubtel returns checkout URLs (redirect and onsite options)
4. Customer completes payment on Hubtel's interface
5. Hubtel sends callback to application with payment status
6. Application updates payment and order records
7. Fallback: Manual verification via Status Check API if callback not received

## Architecture

### Component Overview

```
┌─────────────────┐
│   Frontend      │
│   Application   │
└────────┬────────┘
         │
         │ HTTP Request
         ▼
┌─────────────────────────────────────────────────────┐
│              Laravel Application                     │
│                                                      │
│  ┌──────────────────┐      ┌──────────────────┐   │
│  │ PaymentController│─────▶│  HubtelPaymentService   │   │
│  │  (API Routes)    │      │                  │   │
│  └──────────────────┘      └────────┬─────────┘   │
│           │                          │              │
│           │                          │ HTTP Client  │
│           ▼                          ▼              │
│  ┌──────────────────┐      ┌──────────────────┐   │
│  │ Form Requests    │      │  Laravel HTTP    │   │
│  │ - InitiatePayment│      │  Facade          │   │
│  │ - VerifyPayment  │      └────────┬─────────┘   │
│  └──────────────────┘               │              │
│           │                          │              │
│           ▼                          │              │
│  ┌──────────────────┐               │              │
│  │ Payment Model    │               │              │
│  │ - Eloquent ORM   │               │              │
│  │ - Activity Log   │               │              │
│  │ - customer_id    │               │              │
│  │   (nullable)     │               │              │
│  └──────────────────┘               │              │
│           │                          │              │
│           ▼                          │              │
│  ┌──────────────────┐               │              │
│  │ Order Model      │               │              │
│  └──────────────────┘               │              │
└──────────────────────────────────────┼──────────────┘
                                       │
                                       │ HTTPS
                                       ▼
                              ┌──────────────────┐
                              │  Hubtel API      │
                              │  - Payment Init  │
                              │  - Status Check  │
                              │  - Callbacks     │
                              └──────────────────┘
```

Note: Payment initiation supports both authenticated customers and guest customers (customer_id can be null).

### Component Responsibilities

**PaymentController**
- Handle HTTP requests for payment operations
- Validate requests using Form Request classes
- Delegate business logic to HubtelPaymentService
- Return standardized API responses using PaymentResource

**HubtelPaymentService**
- Encapsulate all Hubtel API communication
- Manage authentication (Basic Auth with client credentials)
- Transform application data to Hubtel API format
- Parse and normalize Hubtel responses
- Handle error scenarios and logging

**Form Request Classes**
- Validate payment initiation data (order existence, amount format, phone numbers)
- Validate payment verification requests
- Provide field-specific error messages

**Payment Model**
- Store payment transaction records
- Maintain relationships with Order and Customer models
- Log payment status changes via Spatie Activity Log
- Store complete Hubtel responses in JSON field

**Laravel HTTP Client**
- Execute HTTP requests to Hubtel API
- Handle authentication headers
- Manage timeouts and retries
- Provide response parsing

### Data Flow Diagrams

#### Payment Initiation Flow

```
Customer → Frontend → POST /api/orders/{order}/payments/hubtel/initiate
                              ↓
                      PaymentController::initiate()
                              ↓
                      InitiatePaymentRequest (validation)
                              ↓
                      HubtelPaymentService::initializeTransaction()
                              ↓
                      Create Payment record (status: pending)
                      - customer_id: auth()->id() OR null (guest)
                              ↓
                      POST https://payproxyapi.hubtel.com/items/initiate
                      - payeeName: customer name OR order contact_name
                      - payeeMobileNumber: customer phone OR order contact_phone
                              ↓
                      Parse Hubtel response
                              ↓
                      Update Payment with transaction_id (checkoutId)
                              ↓
                      Return PaymentResource with checkout URLs
                              ↓
                      Frontend redirects to checkoutUrl or embeds checkoutDirectUrl
```

#### Callback Handling Flow

```
Hubtel → POST /api/payments/hubtel/callback (no auth)
              ↓
      PaymentController::callback()
              ↓
      HubtelPaymentService::handleCallback()
              ↓
      Parse callback payload (ResponseCode, Status, Data)
              ↓
      Find Payment by clientReference (order_number)
              ↓
      Map Hubtel status to payment_status
              ↓
      Update Payment record
              ↓
      Store complete callback in payment_gateway_response
              ↓
      If completed: Update paid_at, trigger order fulfillment
              ↓
      Return HTTP 200 to Hubtel
```

#### Manual Verification Flow

```
Admin/System → GET /api/payments/{payment}/verify
                    ↓
            PaymentController::verify()
                    ↓
            HubtelPaymentService::verifyTransaction()
                    ↓
            GET https://api-txnstatus.hubtel.com/transactions/{merchantAccountNumber}/status
                    ↓
            Parse status response
                    ↓
            Update Payment record if status changed
                    ↓
            Return PaymentResource with current status
```

## Components and Interfaces

### HubtelPaymentService Class

**Location**: `app/Services/HubtelPaymentService.php`

**Constructor Dependencies**:
```php
protected string $clientId;
protected string $clientSecret;
protected string $merchantAccountNumber;
protected string $baseUrl;
protected string $statusCheckUrl;
```

**Public Methods**:

```php
/**
 * Initialize a payment transaction with Hubtel
 *
 * @param array $data Payment initialization data
 * @return array Normalized response with checkout URLs
 * @throws \Exception When initialization fails
 */
public function initializeTransaction(array $data): array

/**
 * Verify a transaction status via Hubtel Status Check API
 *
 * @param string $clientReference The order number used as client reference
 * @return array Normalized transaction status
 * @throws \Exception When verification fails
 */
public function verifyTransaction(string $clientReference): array

/**
 * Handle payment callback from Hubtel
 *
 * @param array $payload The callback payload from Hubtel
 * @return void
 * @throws \Exception When callback processing fails
 */
public function handleCallback(array $payload): void

/**
 * Map Hubtel response code to descriptive error message
 *
 * @param string $responseCode Hubtel response code
 * @return string Human-readable error message
 */
protected function mapResponseCodeToMessage(string $responseCode): string

/**
 * Map Hubtel status to application payment status
 *
 * @param string $hubtelStatus Status from Hubtel (Success, Paid, Unpaid, Refunded)
 * @param string $responseCode Response code from Hubtel
 * @return string Application payment status
 */
protected function mapHubtelStatusToPaymentStatus(string $hubtelStatus, string $responseCode): string

/**
 * Build Basic Auth header value
 *
 * @return string Base64 encoded credentials
 */
protected function getAuthHeader(): string

/**
 * Execute HTTP request with retry logic
 *
 * @param callable $request The HTTP request to execute
 * @param int $maxRetries Maximum retry attempts
 * @return \Illuminate\Http\Client\Response
 */
protected function executeWithRetry(callable $request, int $maxRetries = 3): \Illuminate\Http\Client\Response
```

### PaymentController Endpoints

**Location**: `app/Http/Controllers/Api/PaymentController.php`

**Routes**:
```php
// Initiate Hubtel payment for an order (supports both authenticated and guest customers)
POST /api/orders/{order}/payments/hubtel/initiate
Auth: Optional (optional.auth middleware)
Request: InitiateHubtelPaymentRequest
Response: PaymentResource

// Receive callback from Hubtel
POST /api/payments/hubtel/callback
Auth: None (public endpoint)
Request: JSON payload from Hubtel
Response: HTTP 200

// Manually verify payment status
GET /api/payments/{payment}/verify
Auth: Sanctum
Response: PaymentResource
```

**Controller Methods**:

```php
/**
 * Initiate a Hubtel payment for an order
 * Supports both authenticated customers and guest customers
 */
public function initiateHubtelPayment(InitiateHubtelPaymentRequest $request, Order $order): JsonResponse

/**
 * Handle payment callback from Hubtel
 */
public function hubtelCallback(Request $request): JsonResponse

/**
 * Manually verify payment status
 */
public function verifyPayment(Payment $payment): JsonResponse
```

### Form Request Classes

**InitiateHubtelPaymentRequest**

**Location**: `app/Http/Requests/InitiateHubtelPaymentRequest.php`

**Validation Rules**:
```php
public function rules(): array
{
    return [
        'customer_name' => ['nullable', 'string', 'max:255'],
        'customer_phone' => ['nullable', 'string', 'regex:/^233[0-9]{9}$/'],
        'customer_email' => ['nullable', 'email', 'max:255'],
        'description' => ['required', 'string', 'max:500'],
    ];
}
```

**Authorization Logic**:
- For authenticated customers: Verify order belongs to authenticated customer
- For guest customers: Verify order exists and is payable (no customer ownership check)
- Verify order status allows payment
- Verify order doesn't already have completed payment

**Example Authorization Method**:
```php
public function authorize(): bool
{
    $order = $this->route('order');
    
    // If authenticated, verify order ownership
    if ($this->user()) {
        if ($order->customer_id !== $this->user()->id) {
            return false;
        }
    }
    
    // Verify order is payable
    if ($order->payment_status === 'completed') {
        return false;
    }
    
    return true;
}
```

### API Resource

**PaymentResource**

**Location**: `app/Http/Resources/PaymentResource.php`

**Response Structure**:
```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'order_id' => $this->order_id,
        'payment_method' => $this->payment_method,
        'payment_status' => $this->payment_status,
        'amount' => $this->amount,
        'transaction_id' => $this->transaction_id,
        'paid_at' => $this->paid_at?->toIso8601String(),
        'checkout_url' => $this->payment_gateway_response['checkoutUrl'] ?? null,
        'checkout_direct_url' => $this->payment_gateway_response['checkoutDirectUrl'] ?? null,
        'created_at' => $this->created_at->toIso8601String(),
        'updated_at' => $this->updated_at->toIso8601String(),
    ];
}
```

## Data Models

### Payment Model Updates

**Migration**: `database/migrations/YYYY_MM_DD_HHMMSS_update_payments_table_for_hubtel.php`

**Schema Changes**:
```php
Schema::table('payments', function (Blueprint $table) {
    // Update payment_method enum to include 'hubtel' and remove 'paystack'
    $table->enum('payment_method', ['momo', 'cash_delivery', 'cash_pickup', 'hubtel'])
        ->change();
    
    // Update payment_status enum to include 'cancelled' and 'expired'
    $table->enum('payment_status', ['pending', 'completed', 'failed', 'refunded', 'cancelled', 'expired'])
        ->change();
});
```

**Note**: Laravel requires the `doctrine/dbal` package for column modifications. The migration should include a check or documentation about this dependency.

### Payment Model Enum Values

**payment_method**:
- `mobile_money`: Mobile money payment (MTN, Vodafone, AirtelTigo)
- `card`: Bank card payment (Visa, Mastercard)
- `wallet`: Digital wallet payment (Hubtel, G-Money, Zeepay)
- `ghqr`: GhQR payment
- `cash`: Cash payment (delivery or pickup)

Note: The payment_method is determined from Hubtel's callback `PaymentDetails.PaymentType` field. When a payment is initiated, it starts as 'pending' with payment_method set based on what the customer selects on Hubtel's checkout page. The callback updates this with the actual payment method used.

**payment_status**:
- `pending`: Payment initiated, awaiting completion
- `completed`: Payment successfully completed
- `failed`: Payment failed
- `refunded`: Payment refunded to customer
- `cancelled`: Payment cancelled by customer (NEW)
- `expired`: Payment session expired (NEW)

### Payment Gateway Response Structure

The `payment_gateway_response` JSON field stores complete Hubtel responses:

**After Initialization**:
```json
{
    "checkoutId": "string",
    "checkoutUrl": "https://...",
    "checkoutDirectUrl": "https://...",
    "clientReference": "ORD-123456"
}
```

**After Callback**:
```json
{
    "ResponseCode": "0000",
    "Status": "Success",
    "Data": {
        "CheckoutId": "string",
        "SalesInvoiceId": "string",
        "ClientReference": "ORD-123456",
        "Status": "Paid",
        "Amount": 100.00,
        "CustomerPhoneNumber": "233XXXXXXXXX",
        "PaymentDetails": {
            "MobileMoneyNumber": "233XXXXXXXXX",
            "PaymentType": "mobilemoney",
            "Channel": "mtn-gh"
        }
    }
}
```

**After Status Check**:
```json
{
    "transactionId": "string",
    "externalTransactionId": "string",
    "amount": 100.00,
    "charges": 2.50,
    "status": "Paid"
}
```

### Database Relationships

**Payment Model**:
```php
// Existing relationships (no changes)
public function order(): BelongsTo
{
    return $this->belongsTo(Order::class);
}

public function customer(): BelongsTo
{
    return $this->belongsTo(Customer::class);
}
```

**Note**: The `customer_id` field is nullable to support guest orders. When `customer_id` is null, the payment is associated with a guest customer, and order contact information (contact_name, contact_phone) is used for Hubtel's payer details.

**Order Model** (no changes required):
- Already has `hasMany` relationship to Payment
- Order fulfillment logic triggered when payment completes

## Request/Response Formats

### Payment Initiation

**Request to Application (Authenticated Customer)**:
```http
POST /api/orders/{order}/payments/hubtel/initiate
Authorization: Bearer {sanctum_token}
Content-Type: application/json

{
    "customer_name": "John Doe",
    "customer_phone": "233XXXXXXXXX",
    "customer_email": "john@example.com",
    "description": "Payment for Order #ORD-123456"
}
```

**Request to Application (Guest Customer)**:
```http
POST /api/orders/{order}/payments/hubtel/initiate
Content-Type: application/json

{
    "description": "Payment for Order #ORD-123456"
}
```

Note: For guest customers, the system uses order contact information (contact_name, contact_phone) for Hubtel's payer details.

**Request to Hubtel API**:
```http
POST https://payproxyapi.hubtel.com/items/initiate
Authorization: Basic {base64(client_id:client_secret)}
Content-Type: application/json

{
    "totalAmount": 100.00,
    "description": "Payment for Order #ORD-123456",
    "callbackUrl": "https://api.cedibites.com/api/payments/hubtel/callback",
    "returnUrl": "https://cedibites.com/orders/ORD-123456/payment/success",
    "cancellationUrl": "https://cedibites.com/orders/ORD-123456/payment/cancelled",
    "merchantAccountNumber": "HM12345",
    "clientReference": "ORD-123456",
    "payeeName": "John Doe",
    "payeeMobileNumber": "233XXXXXXXXX",
    "payeeEmail": "john@example.com"
}
```

**Response from Hubtel**:
```json
{
    "status": "Success",
    "message": "Payment initiated successfully",
    "data": {
        "checkoutId": "abc123xyz",
        "checkoutUrl": "https://checkout.hubtel.com/abc123xyz",
        "checkoutDirectUrl": "https://checkout.hubtel.com/direct/abc123xyz",
        "clientReference": "ORD-123456"
    }
}
```

**Response to Application Client**:
```json
{
    "success": true,
    "message": "Payment initiated successfully",
    "data": {
        "id": 1,
        "order_id": 123,
        "payment_method": "hubtel",
        "payment_status": "pending",
        "amount": "100.00",
        "transaction_id": "abc123xyz",
        "checkout_url": "https://checkout.hubtel.com/abc123xyz",
        "checkout_direct_url": "https://checkout.hubtel.com/direct/abc123xyz",
        "paid_at": null,
        "created_at": "2024-01-15T10:30:00Z",
        "updated_at": "2024-01-15T10:30:00Z"
    }
}
```

### Payment Callback

**Request from Hubtel**:
```http
POST https://api.cedibites.com/api/payments/hubtel/callback
Content-Type: application/json

{
    "ResponseCode": "0000",
    "Status": "Success",
    "Data": {
        "CheckoutId": "abc123xyz",
        "SalesInvoiceId": "INV-789",
        "ClientReference": "ORD-123456",
        "Status": "Paid",
        "Amount": 100.00,
        "CustomerPhoneNumber": "233XXXXXXXXX",
        "PaymentDetails": {
            "MobileMoneyNumber": "233XXXXXXXXX",
            "PaymentType": "mobilemoney",
            "Channel": "mtn-gh"
        }
    }
}
```

**Response to Hubtel**:
```http
HTTP/1.1 200 OK
Content-Type: application/json

{
    "success": true,
    "message": "Callback processed successfully"
}
```

### Payment Verification

**Request to Application**:
```http
GET /api/payments/{payment}/verify
Authorization: Bearer {sanctum_token}
```

**Request to Hubtel Status Check API**:
```http
GET https://api-txnstatus.hubtel.com/transactions/{merchantAccountNumber}/status?clientReference=ORD-123456
Authorization: Basic {base64(client_id:client_secret)}
```

**Response from Hubtel**:
```json
{
    "transactionId": "abc123xyz",
    "externalTransactionId": "EXT-456",
    "amount": 100.00,
    "charges": 2.50,
    "status": "Paid",
    "clientReference": "ORD-123456"
}
```

**Response to Application Client**:
```json
{
    "success": true,
    "message": "Payment verified successfully",
    "data": {
        "id": 1,
        "order_id": 123,
        "payment_method": "hubtel",
        "payment_status": "completed",
        "amount": "100.00",
        "transaction_id": "abc123xyz",
        "paid_at": "2024-01-15T10:35:00Z",
        "created_at": "2024-01-15T10:30:00Z",
        "updated_at": "2024-01-15T10:35:00Z"
    }
}
```

### Error Responses

**Validation Error**:
```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "customer_phone": [
            "The customer phone format is invalid. Use format: 233XXXXXXXXX"
        ]
    }
}
```

**Hubtel API Error**:
```json
{
    "success": false,
    "message": "Payment initialization failed: Validation error",
    "error_code": "4000"
}
```

**Configuration Error**:
```json
{
    "success": false,
    "message": "Hubtel payment gateway is not properly configured. Please contact support."
}
```


## Configuration Management

### Configuration File Updates

**Location**: `config/services.php`

**Hubtel Configuration Block**:
```php
'hubtel' => [
    'client_id' => env('HUBTEL_CLIENT_ID'),
    'client_secret' => env('HUBTEL_CLIENT_SECRET'),
    'merchant_account_number' => env('HUBTEL_MERCHANT_ACCOUNT_NUMBER'),
    'sender_id' => env('HUBTEL_SENDER_ID', 'CediBites'), // Existing for SMS
    'base_url' => env('HUBTEL_BASE_URL', 'https://payproxyapi.hubtel.com'),
    'status_check_url' => env('HUBTEL_STATUS_CHECK_URL', 'https://api-txnstatus.hubtel.com'),
],
```

### Environment Variables

**Required Variables** (`.env`):
```env
# Hubtel Payment Gateway
HUBTEL_CLIENT_ID=your_client_id_here
HUBTEL_CLIENT_SECRET=your_client_secret_here
HUBTEL_MERCHANT_ACCOUNT_NUMBER=your_merchant_account_number_here

# Optional: Override default API URLs (for sandbox testing)
HUBTEL_BASE_URL=https://payproxyapi.hubtel.com
HUBTEL_STATUS_CHECK_URL=https://api-txnstatus.hubtel.com
```

### Configuration Validation

The HubtelPaymentService constructor should validate required configuration:

```php
public function __construct()
{
    $this->clientId = config('services.hubtel.client_id');
    $this->clientSecret = config('services.hubtel.client_secret');
    $this->merchantAccountNumber = config('services.hubtel.merchant_account_number');
    
    if (empty($this->clientId) || empty($this->clientSecret) || empty($this->merchantAccountNumber)) {
        throw new \RuntimeException(
            'Hubtel payment gateway is not properly configured. ' .
            'Please set HUBTEL_CLIENT_ID, HUBTEL_CLIENT_SECRET, and HUBTEL_MERCHANT_ACCOUNT_NUMBER in your environment.'
        );
    }
    
    $this->baseUrl = config('services.hubtel.base_url', 'https://payproxyapi.hubtel.com');
    $this->statusCheckUrl = config('services.hubtel.status_check_url', 'https://api-txnstatus.hubtel.com');
}
```

### URL Configuration

**Callback URL**: Generated from named route
```php
route('payments.hubtel.callback') // /api/payments/hubtel/callback
```

**Return URL**: Frontend URL with order reference
```php
config('app.frontend_url') . "/orders/{$order->order_number}/payment/success"
```

**Cancellation URL**: Frontend URL with order reference
```php
config('app.frontend_url') . "/orders/{$order->order_number}/payment/cancelled"
```

### Security Considerations

1. **Credential Storage**: Never commit credentials to version control
2. **Credential Access**: Only HubtelPaymentService should access credentials
3. **Logging**: Sanitize credentials from all log entries
4. **Response Exposure**: Never include credentials in API responses
5. **Environment Separation**: Use different credentials for sandbox/production

## Error Handling

### Error Categories

**1. Configuration Errors**
- Missing or invalid credentials
- Invalid API URLs
- Thrown at service instantiation
- HTTP 500 response to client

**2. Validation Errors**
- Invalid request data (amount, phone format, email)
- Missing required fields
- Handled by Form Request classes
- HTTP 422 response with field-specific errors

**3. Hubtel API Errors**
- Response codes: 0005, 2001, 4000, 4070
- Network failures
- Timeout errors
- Logged with full context
- HTTP 400/500 response to client with user-friendly message

**4. Business Logic Errors**
- Order already paid
- Order not found
- Payment not found
- HTTP 404/409 response to client

### Response Code Mapping

**HubtelPaymentService::mapResponseCodeToMessage()**:

```php
protected function mapResponseCodeToMessage(string $responseCode): string
{
    return match ($responseCode) {
        '0000' => 'Payment successful',
        '0005' => 'Payment processor error or network issue. Please try again.',
        '2001' => 'Transaction failed. Please try again or use a different payment method.',
        '4000' => 'Invalid payment data. Please check your information and try again.',
        '4070' => 'Payment amount issue. Please contact support.',
        default => 'An unexpected error occurred. Please try again or contact support.',
    };
}
```

### Status Mapping

**HubtelPaymentService::mapHubtelStatusToPaymentStatus()**:

```php
protected function mapHubtelStatusToPaymentStatus(string $hubtelStatus, string $responseCode): string
{
    // Failed response code overrides status
    if ($responseCode !== '0000') {
        return 'failed';
    }
    
    return match (strtolower($hubtelStatus)) {
        'success', 'paid' => 'completed',
        'unpaid' => 'pending',
        'refunded' => 'refunded',
        default => 'pending',
    };
}
```

### Retry Logic

**Network Error Handling**:

```php
protected function executeWithRetry(callable $request, int $maxRetries = 3): Response
{
    $attempt = 0;
    $lastException = null;
    
    while ($attempt < $maxRetries) {
        try {
            return $request();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $lastException = $e;
            $attempt++;
            
            if ($attempt < $maxRetries) {
                // Exponential backoff: 1s, 2s, 4s
                $delay = pow(2, $attempt - 1);
                sleep($delay);
                
                Log::warning('Hubtel API request failed, retrying', [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'delay' => $delay,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
    
    Log::error('Hubtel API request failed after all retries', [
        'attempts' => $attempt,
        'error' => $lastException->getMessage(),
    ]);
    
    throw new \Exception('Failed to connect to Hubtel payment gateway. Please try again later.');
}
```

### Logging Strategy

**Log Levels**:
- `Log::info()`: Successful operations (initiation, callback, verification)
- `Log::warning()`: Retryable errors, unexpected but handled scenarios
- `Log::error()`: Failed operations, exceptions, configuration issues

**Log Context**:
```php
// Payment Initiation
Log::info('Hubtel payment initiated', [
    'order_id' => $order->id,
    'order_number' => $order->order_number,
    'amount' => $order->total_amount,
    'checkout_id' => $checkoutId,
]);

// Callback Receipt
Log::info('Hubtel callback received', [
    'response_code' => $payload['ResponseCode'],
    'status' => $payload['Status'],
    'checkout_id' => $payload['Data']['CheckoutId'],
    'client_reference' => $payload['Data']['ClientReference'],
]);

// API Error
Log::error('Hubtel API request failed', [
    'endpoint' => $url,
    'response_code' => $response->json()['ResponseCode'] ?? 'unknown',
    'message' => $response->json()['message'] ?? 'No message',
    'status_code' => $response->status(),
]);
```

**Data Sanitization**:
```php
protected function sanitizeForLogging(array $data): array
{
    $sanitized = $data;
    
    // Remove sensitive fields
    unset($sanitized['client_secret']);
    
    // Mask phone numbers (show first 3 and last 2 digits)
    if (isset($sanitized['customer_phone'])) {
        $phone = $sanitized['customer_phone'];
        $sanitized['customer_phone'] = substr($phone, 0, 3) . '****' . substr($phone, -2);
    }
    
    // Mask emails (show first 3 chars and domain)
    if (isset($sanitized['customer_email'])) {
        $email = $sanitized['customer_email'];
        $parts = explode('@', $email);
        if (count($parts) === 2) {
            $sanitized['customer_email'] = substr($parts[0], 0, 3) . '***@' . $parts[1];
        }
    }
    
    return $sanitized;
}
```

### Exception Handling

**Controller Level**:
```php
public function initiateHubtelPayment(InitiateHubtelPaymentRequest $request, Order $order): JsonResponse
{
    try {
        $payment = $this->hubtelService->initializeTransaction([
            'order' => $order,
            'customer_name' => $request->input('customer_name'),
            'customer_phone' => $request->input('customer_phone'),
            'customer_email' => $request->input('customer_email'),
            'description' => $request->input('description'),
        ]);
        
        return response()->success(
            new PaymentResource($payment),
            'Payment initiated successfully'
        );
    } catch (\RuntimeException $e) {
        // Configuration errors
        Log::error('Hubtel configuration error', ['error' => $e->getMessage()]);
        return response()->error('Payment gateway configuration error', 500);
    } catch (\Exception $e) {
        // All other errors
        Log::error('Payment initiation failed', [
            'order_id' => $order->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->error($e->getMessage(), 400);
    }
}
```


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Payment Initiation Request Completeness

*For any* valid payment initiation request, the request sent to Hubtel SHALL include all required fields: totalAmount, description, callbackUrl, returnUrl, cancellationUrl, merchantAccountNumber, and clientReference.

**Validates: Requirements 1.2, 3.4, 3.5**

### Property 2: Client Reference Derivation

*For any* order, when initiating a payment, the clientReference in the Hubtel request SHALL equal the order's order_number and SHALL NOT exceed 32 characters.

**Validates: Requirements 1.3, 12.4**

### Property 3: Optional Customer Details Inclusion

*For any* payment initiation request where customer details (name, phone, email) are provided, those details SHALL be included in the Hubtel API request as payeeName, payeeMobileNumber, and payeeEmail.

**Validates: Requirements 1.4**

### Property 4: Checkout URLs Response Structure

*For any* successful payment initiation, the service response SHALL include checkoutUrl, checkoutDirectUrl, checkoutId, and clientReference fields.

**Validates: Requirements 1.5, 3.1, 3.2, 3.3, 7.4**

### Property 5: Payment Record Creation

*For any* successful payment initiation, a Payment model record SHALL be created with payment_method='hubtel', payment_status='pending', transaction_id equal to the checkoutId, and the order_id and amount from the request.

**Validates: Requirements 1.6, 6.3, 6.4**

### Property 6: Gateway Response Persistence

*For any* Hubtel API interaction (initiation, callback, or status check), the complete response SHALL be stored in the Payment model's payment_gateway_response JSON field.

**Validates: Requirements 1.7, 4.5, 5.7, 6.5, 9.7**

### Property 7: Error Response Code Mapping

*For any* Hubtel response code, the service SHALL map it to a descriptive, user-friendly error message according to the defined mapping (0000→success, 0005→processor error, 2001→failed, 4000→validation error, 4070→fees issue).

**Validates: Requirements 1.8, 9.1, 9.2, 9.3, 9.4, 9.5**

### Property 8: Callback Payment Details Extraction

*For any* callback received with PaymentDetails, the service SHALL extract and store PaymentType, Channel, and MobileMoneyNumber in the payment_gateway_response.

**Validates: Requirements 2.7, 14.3**

### Property 9: Callback JSON Parsing

*For any* valid callback payload, the service SHALL successfully parse the JSON structure and extract ResponseCode, Status, and Data fields, along with nested fields CheckoutId, SalesInvoiceId, ClientReference, Amount, CustomerPhoneNumber, and PaymentDetails from the Data object.

**Validates: Requirements 4.1, 4.4, 14.1, 14.2**

### Property 10: Status Mapping Consistency

*For any* Hubtel status value (Success, Paid, Unpaid, Refunded) and response code, the service SHALL consistently map to the correct application payment_status: (Success|Paid + 0000)→completed, Unpaid→pending, Refunded→refunded, (any + non-0000)→failed.

**Validates: Requirements 4.2, 4.3, 5.3, 9.6**

### Property 11: Payment Completion Timestamp

*For any* payment that transitions to payment_status='completed', the paid_at timestamp SHALL be set to the current time.

**Validates: Requirements 4.6, 6.6**

### Property 12: Order Fulfillment Trigger

*For any* payment that changes to payment_status='completed', the system SHALL trigger order fulfillment processes (event dispatch or method call).

**Validates: Requirements 4.7**

### Property 13: Callback Acknowledgment

*For any* callback received from Hubtel, the endpoint SHALL respond with HTTP 200 status code.

**Validates: Requirements 4.8**

### Property 14: Status Check Response Parsing

*For any* status check API response, the service SHALL extract transactionId, externalTransactionId, amount, charges, and status fields.

**Validates: Requirements 5.6**

### Property 15: Refund Data Recording

*For any* payment that transitions to payment_status='refunded', the refunded_at timestamp SHALL be set and refund_reason SHALL be recorded if provided.

**Validates: Requirements 6.7**

### Property 16: Activity Logging Integration

*For any* payment status change, the Spatie Activity Log SHALL record the change with the payment_status, amount, and payment_method fields.

**Validates: Requirements 6.9**

### Property 17: Basic Authentication Format

*For any* Hubtel API request, the Authorization header SHALL be formatted as "Basic {base64(client_id:client_secret)}".

**Validates: Requirements 7.3**

### Property 18: API Response Structure Consistency

*For any* successful API endpoint response, the response SHALL use PaymentResource and follow the consistent JSON structure with success, message, and data fields.

**Validates: Requirements 8.7**

### Property 19: Validation Error Response Format

*For any* request that fails validation, the endpoint SHALL return HTTP 422 status with a JSON response containing field-specific error messages.

**Validates: Requirements 8.8, 12.8**

### Property 20: Credential Security in Responses

*For any* API response or log entry, the client_id and client_secret SHALL NOT be present in the output.

**Validates: Requirements 10.4, 11.7**

### Property 21: URL Generation from Routes

*For any* payment initiation, the callbackUrl, returnUrl, and cancellationUrl SHALL be generated from Laravel named routes and application configuration.

**Validates: Requirements 10.8**

### Property 22: Network Retry Logic

*For any* Hubtel API request that fails due to network error, the service SHALL retry up to 3 times with exponential backoff (1s, 2s, 4s delays).

**Validates: Requirements 11.2**

### Property 23: Payment Initiation Logging

*For any* payment initiation attempt, a log entry SHALL be created containing order_id, amount, and clientReference.

**Validates: Requirements 11.4**

### Property 24: Exception Logging and Safe Error Response

*For any* exception during payment processing, the service SHALL log the stack trace and return a generic error message to the client (not exposing internal details).

**Validates: Requirements 11.6**

### Property 25: Sensitive Data Sanitization in Logs

*For any* log entry containing customer phone numbers or emails, the data SHALL be sanitized showing only the first 3 and last 2 characters (e.g., "233****89", "joh***@example.com").

**Validates: Requirements 11.8**

### Property 26: Input Validation Completeness

*For any* payment initiation request, validation SHALL enforce: order_id exists in database, totalAmount is positive with max 2 decimals, description is non-empty, payeeMobileNumber (if provided) matches format 233XXXXXXXXX, and payeeEmail (if provided) is valid email format.

**Validates: Requirements 12.1, 12.2, 12.3, 12.5, 12.6**

### Property 27: Callback JSON Round-Trip

*For any* valid callback JSON payload, parsing the JSON into a data structure and then serializing it back to JSON SHALL produce an equivalent structure (preserving all fields and values).

**Validates: Requirements 14.6**

### Property 28: Callback Amount Validation

*For any* callback received, the Amount field in the callback SHALL match the Payment model's amount field within an acceptable tolerance of 0.01 GHS.

**Validates: Requirements 14.8**

## Testing Strategy

### Overview

The Hubtel payment integration will be tested using a dual approach combining unit tests and property-based tests. This comprehensive strategy ensures both specific edge cases and general correctness across all possible inputs.

**Testing Framework**: Pest 4 (PHP testing framework)
**HTTP Mocking**: Laravel HTTP Fake for mocking Hubtel API responses
**Database**: In-memory SQLite for fast test execution
**Factories**: Leverage existing Payment and Order model factories

### Test Organization

```
tests/
├── Feature/
│   ├── HubtelPaymentInitiationTest.php
│   ├── HubtelCallbackHandlingTest.php
│   ├── HubtelPaymentVerificationTest.php
│   └── HubtelPaymentEndpointsTest.php
└── Unit/
    ├── HubtelPaymentServiceTest.php
    ├── HubtelStatusMappingTest.php
    ├── HubtelResponseParsingTest.php
    └── InitiateHubtelPaymentRequestTest.php
```


### Unit Tests

Unit tests focus on specific examples, edge cases, and isolated component behavior.

**HubtelPaymentServiceTest.php**:
- Test constructor loads configuration correctly
- Test constructor throws exception when credentials missing
- Test Basic Auth header generation format
- Test response code to message mapping for each code
- Test Hubtel status to payment status mapping
- Test retry logic executes correct number of attempts
- Test data sanitization masks phone numbers and emails

**HubtelStatusMappingTest.php**:
- Test mapping "Success" with "0000" → "completed"
- Test mapping "Paid" with "0000" → "completed"
- Test mapping "Unpaid" → "pending"
- Test mapping "Refunded" → "refunded"
- Test mapping any status with non-"0000" code → "failed"

**InitiateHubtelPaymentRequestTest.php**:
- Test validation passes with valid data
- Test validation fails with non-existent order
- Test validation fails with negative amount
- Test validation fails with invalid phone format
- Test validation fails with invalid email format

### Property-Based Tests

Property-based tests verify universal properties across many generated inputs. Each test runs minimum 100 iterations with randomized data.

**Test Tagging Format**:
```php
test('property: payment initiation includes all required fields', function () {
    // Feature: hubtel-payment-integration, Property 1: Payment Initiation Request Completeness
    // Test implementation
})->repeat(100);
```

**Key Property Tests**:

**Property 1 - Request Completeness**: For any valid payment initiation, verify all required fields (totalAmount, description, callbackUrl, returnUrl, cancellationUrl, merchantAccountNumber, clientReference) are included in Hubtel request.

**Property 2 - Client Reference**: For any order, verify clientReference equals order_number and doesn't exceed 32 characters.

**Property 5 - Payment Record Creation**: For any successful initiation, verify Payment record created with payment_method='hubtel', payment_status='pending', correct transaction_id and amount.

**Property 10 - Status Mapping**: For any Hubtel status and response code combination, verify consistent mapping to application payment_status.

**Property 11 - Completion Timestamp**: For any payment transitioning to 'completed', verify paid_at timestamp is set.

**Property 27 - JSON Round-Trip**: For any valid callback JSON, verify parsing then serializing preserves structure.

### Feature Tests

Feature tests verify end-to-end functionality through HTTP requests.

**HubtelPaymentEndpointsTest.php**:
- Test initiating payment for order returns checkout URLs
- Test callback endpoint updates payment status to completed
- Test callback endpoint does not require authentication
- Test verify endpoint requires Sanctum authentication
- Test manual verification updates payment status
- Test validation errors return HTTP 422
- Test unauthorized access returns HTTP 401

**Test Example**:
```php
test('can initiate hubtel payment for order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['customer_id' => $user->id]);
    
    Http::fake(['*' => Http::response(['status' => 'Success', 'data' => [
        'checkoutId' => 'test-id',
        'checkoutUrl' => 'https://checkout.hubtel.com/test',
        'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/test',
        'clientReference' => $order->order_number,
    ]])]);
    
    $response = $this->actingAs($user)
        ->postJson("/api/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Test payment',
        ]);
    
    $response->assertOk()
        ->assertJsonStructure(['success', 'message', 'data']);
    
    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'payment_method' => 'hubtel',
        'payment_status' => 'pending',
    ]);
});
```

### Test Data Generation

**Payment Factory Extensions**:
```php
// In PaymentFactory.php
public function hubtel(): static
{
    return $this->state(fn (array $attributes) => [
        'payment_method' => 'hubtel',
        'transaction_id' => fake()->uuid(),
    ]);
}

public function hubtelPending(): static
{
    return $this->hubtel()->state(fn (array $attributes) => [
        'payment_status' => 'pending',
        'paid_at' => null,
    ]);
}

public function hubtelCompleted(): static
{
    return $this->hubtel()->state(fn (array $attributes) => [
        'payment_status' => 'completed',
        'paid_at' => now(),
    ]);
}
```

### Test Coverage Goals

- **Line Coverage**: Minimum 90% for HubtelPaymentService
- **Branch Coverage**: Minimum 85% for conditional logic
- **Property Tests**: Minimum 100 iterations per property
- **Edge Cases**: All error codes, validation failures, boundaries

### Running Tests

```bash
# Run all Hubtel tests
php artisan test --filter=Hubtel --compact

# Run with coverage
php artisan test --filter=Hubtel --coverage

# Run only property tests
php artisan test --filter=property --compact

# Run specific test file
php artisan test tests/Feature/HubtelPaymentInitiationTest.php --compact
```

### Integration with Existing Tests

- Extend existing Payment model tests to cover 'hubtel' payment method
- Extend Order fulfillment tests to verify Hubtel payment completion triggers
- Ensure Activity Log tests cover Hubtel payment status changes
- Verify existing API response helpers work with Hubtel endpoints

---

## Implementation Notes

### Migration Considerations

The Payment model enum modifications require the `doctrine/dbal` package:

```bash
composer require doctrine/dbal --dev
```

### Route Registration

Routes should be registered in `routes/api.php`:

```php
// Hubtel Payment Routes
Route::middleware('optional.auth')->group(function () {
    Route::post('orders/{order}/payments/hubtel/initiate', [PaymentController::class, 'initiateHubtelPayment']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('payments/{payment}/verify', [PaymentController::class, 'verifyPayment']);
});

// Public callback endpoint (no authentication)
Route::post('payments/hubtel/callback', [PaymentController::class, 'hubtelCallback']);
```

Note: The `optional.auth` middleware (already exists in the codebase) allows both authenticated and guest requests for payment initiation.

### Environment Setup

Add to `.env.example`:

```env
# Hubtel Payment Gateway
HUBTEL_CLIENT_ID=
HUBTEL_CLIENT_SECRET=
HUBTEL_MERCHANT_ACCOUNT_NUMBER=
HUBTEL_BASE_URL=https://payproxyapi.hubtel.com
HUBTEL_STATUS_CHECK_URL=https://api-txnstatus.hubtel.com
```

### IP Whitelisting

For production deployment, the server IP address must be whitelisted with Hubtel to use the Status Check API. Document the server IP and submit to Hubtel support.

### Deployment Checklist

1. Run migration to update Payment model enums
2. Configure Hubtel credentials in production environment
3. Whitelist server IP with Hubtel for Status Check API
4. Configure frontend URLs for returnUrl and cancellationUrl
5. Test callback endpoint is publicly accessible
6. Verify SSL certificate is valid for callback URL
7. Run full test suite in staging environment
8. Monitor logs for first production transactions

