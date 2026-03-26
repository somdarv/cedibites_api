# Hubtel UAT - Tinker Commands for Production

Run these commands on your production server to get sample responses for all Hubtel APIs.

## Setup

```bash
# SSH into production server
ssh your-production-server

# Navigate to project directory
cd /path/to/cedibites_api

# Start tinker
php artisan tinker
```

---

## QUICK START - Get All Responses in One Command

If you already have completed payments in production, run this:

```php
// Collect all existing Hubtel responses from database
$responses = [
    'generated_at' => now()->toIso8601String(),
    'environment' => config('app.env'),
];

// Get payment responses
$payments = \App\Models\Payment::whereNotNull('payment_gateway_response')
    ->latest()
    ->take(20)
    ->get();

echo "Found " . $payments->count() . " payments with Hubtel responses\n\n";

foreach ($payments as $payment) {
    $response = $payment->payment_gateway_response;
    
    echo "=== Payment ID: {$payment->id} | Order: {$payment->order->order_number} | Status: {$payment->payment_status} ===\n";
    echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
}

// Save to file
$filename = 'hubtel_uat_responses_' . now()->format('Y-m-d_His') . '.json';
file_put_contents(storage_path('app/' . $filename), json_encode($payments->pluck('payment_gateway_response'), JSON_PRETTY_PRINT));
echo "Saved to: storage/app/{$filename}\n";
```

**If no payments exist**, continue to the sections below to create test payments.

---

## 1. PAYMENT APIs - Sample Responses

### A. Online Checkout Payment Initiation

```php
// Option 1: Use existing order (any status)
$order = \App\Models\Order::latest()->first();

// Option 2: Create a test order if no orders exist
if (!$order) {
    $branch = \App\Models\Branch::first();
    $customer = \App\Models\Customer::first();
    
    $order = \App\Models\Order::create([
        'branch_id' => $branch->id,
        'customer_id' => $customer?->id,
        'order_number' => 'ORD-UAT-' . now()->format('YmdHis'),
        'order_type' => 'delivery',
        'status' => 'pending',
        'payment_status' => 'pending',
        'total_amount' => 50.00,
        'contact_name' => 'UAT Test Customer',
        'contact_phone' => '233241234567',
        'contact_email' => 'uat@test.com',
    ]);
}

// Initialize Hubtel payment service
$hubtelService = app(\App\Services\HubtelPaymentService::class);

// Initiate payment
$result = $hubtelService->initializeTransaction([
    'order' => $order,
    'description' => 'UAT Test Payment for Order #' . $order->order_number,
    'customer_name' => $order->contact_name,
    'customer_phone' => $order->contact_phone,
    'customer_email' => $order->contact_email,
]);

// Print the response
print_r($result);

// Get the payment record with full response
$payment = \App\Models\Payment::find($result['payment']->id);
echo json_encode($payment->payment_gateway_response, JSON_PRETTY_PRINT);
```

**Expected Output Structure**:
```json
{
  "checkoutId": "abc123xyz",
  "checkoutUrl": "https://checkout.hubtel.com/...",
  "checkoutDirectUrl": "https://checkout.hubtel.com/direct/...",
  "clientReference": "ORD-123456"
}
```

---

### B. POS Mobile Money Payment (Direct Receive Money)

```php
// Option 1: Use existing order
$order = \App\Models\Order::latest()->first();

// Option 2: Create test order if needed
if (!$order) {
    $branch = \App\Models\Branch::first();
    $order = \App\Models\Order::create([
        'branch_id' => $branch->id,
        'order_number' => 'ORD-UAT-RMP-' . now()->format('YmdHis'),
        'order_type' => 'dine_in',
        'status' => 'pending',
        'payment_status' => 'pending',
        'total_amount' => 25.00,
        'contact_name' => 'UAT RMP Test',
        'contact_phone' => '233241234567',
    ]);
}

// Initialize service
$hubtelService = app(\App\Services\HubtelPaymentService::class);

// Initiate RMP payment (use a real Ghana mobile number)
$result = $hubtelService->initializeReceiveMoney([
    'order' => $order,
    'customer_phone' => '0241234567', // Replace with YOUR real number for testing
    'customer_name' => 'UAT Test Customer',
    'description' => 'UAT POS Payment for Order #' . $order->order_number
]);

// Print the response
print_r($result);

// Get full payment record
$payment = \App\Models\Payment::find($result['payment']->id);
echo json_encode($payment->payment_gateway_response, JSON_PRETTY_PRINT);
```

**Expected Output Structure**:
```json
{
  "TransactionId": "rmp-txn-123",
  "channel": "mtn-gh",
  "message": "Payment initiated successfully"
}
```

---

### C. Mobile Number Verification

```php
// Initialize service
$hubtelService = app(\App\Services\HubtelPaymentService::class);

// Verify a mobile money number (use real Ghana number)
$result = $hubtelService->verifyMomoNumber('0241234567');

// Print the response
print_r($result);
echo json_encode($result, JSON_PRETTY_PRINT);
```

**Expected Output Structure**:
```json
{
  "isRegistered": true,
  "name": "John Doe",
  "status": "active",
  "profile": "registered"
}
```

---

### D. Payment Status Verification

```php
// Get any payment with transaction_id (completed or pending)
$payment = \App\Models\Payment::whereNotNull('transaction_id')->latest()->first();

// If no payments exist, you need to initiate one first (see section A or B above)
if (!$payment) {
    echo "No payments found. Please initiate a payment first using section A or B.\n";
    exit;
}

$order = $payment->order;

// Initialize service
$hubtelService = app(\App\Services\HubtelPaymentService::class);

// Verify transaction status
$result = $hubtelService->verifyTransaction($order->order_number);

// Print the response
print_r($result);
echo json_encode($result, JSON_PRETTY_PRINT);
```

**Expected Output Structure**:
```json
{
  "transactionId": "abc123xyz",
  "externalTransactionId": "EXT-MTN-456",
  "amount": 50.00,
  "charges": 1.25,
  "status": "Paid",
  "paymentStatus": "completed"
}
```

---

### E. View Stored Callback Responses

```php
// Get recent completed payments to see callback data
$payments = \App\Models\Payment::where('payment_status', 'completed')
    ->whereNotNull('payment_gateway_response')
    ->latest()
    ->take(5)
    ->get();

// Print each payment's callback response
foreach ($payments as $payment) {
    echo "\n=== Payment ID: {$payment->id} | Order: {$payment->order->order_number} ===\n";
    echo json_encode($payment->payment_gateway_response, JSON_PRETTY_PRINT);
    echo "\n";
}
```

**Expected Callback Structure (Online Checkout)**:
```json
{
  "ResponseCode": "0000",
  "Status": "Success",
  "Data": {
    "CheckoutId": "abc123xyz",
    "SalesInvoiceId": "INV-2024-001",
    "ClientReference": "ORD-123456",
    "Status": "Paid",
    "Amount": 50.00,
    "CustomerPhoneNumber": "233241234567",
    "PaymentDetails": {
      "MobileMoneyNumber": "233241234567",
      "PaymentType": "mobilemoney",
      "Channel": "mtn-gh"
    }
  }
}
```

**Expected Callback Structure (RMP)**:
```json
{
  "ResponseCode": "0000",
  "Data": {
    "TransactionId": "rmp-txn-123",
    "ClientReference": "ORD-123456",
    "Amount": 50.00,
    "CustomerMsisdn": "233241234567",
    "Channel": "mtn-gh",
    "Status": "Success"
  }
}
```

---

## 2. SMS APIs - Sample Responses

### A. Send Single SMS

```php
// Initialize SMS service
$smsService = app(\App\Services\HubtelSmsService::class);

// Send single SMS (use real Ghana number in format 233XXXXXXXXX)
$result = $smsService->sendSingle('233241234567', 'Test message from CediBites');

// Print the response
print_r($result);
echo json_encode($result, JSON_PRETTY_PRINT);
```

**Expected Output Structure**:
```json
{
  "messageId": "msg-123456",
  "status": 0,
  "responseCode": 0
}
```

---

### B. Send Batch SMS

```php
// Initialize SMS service
$smsService = app(\App\Services\HubtelSmsService::class);

// Send batch SMS to multiple recipients
$recipients = [
    '233241234567',
    '233501234567',
    '233201234567'
];

$result = $smsService->sendBatch($recipients, 'Batch test message from CediBites');

// Print the response
print_r($result);
echo json_encode($result, JSON_PRETTY_PRINT);
```

**Expected Output Structure**:
```json
{
  "messageIds": [
    "msg-123456",
    "msg-123457",
    "msg-123458"
  ],
  "status": 0,
  "responseCode": 0
}
```

---

### C. View SMS Logs from Database

```php
// Check activity logs for SMS notifications
$smsLogs = \Spatie\Activitylog\Models\Activity::where('log_name', 'sms')
    ->orWhere('description', 'like', '%SMS%')
    ->latest()
    ->take(10)
    ->get();

foreach ($smsLogs as $log) {
    echo "\n=== SMS Log ID: {$log->id} ===\n";
    echo "Description: {$log->description}\n";
    echo "Properties: " . json_encode($log->properties, JSON_PRETTY_PRINT) . "\n";
}
```

---

## 3. COMBINED - Get All Sample Responses at Once

```php
// This will collect all responses in one go
$responses = [];

// 1. Get recent payment initiation response
$payment = \App\Models\Payment::whereNotNull('payment_gateway_response')
    ->where('payment_method', 'mobile_money')
    ->latest()
    ->first();

if ($payment) {
    $responses['payment_initiation'] = $payment->payment_gateway_response;
}

// 2. Get callback response (completed payment)
$completedPayment = \App\Models\Payment::where('payment_status', 'completed')
    ->whereNotNull('payment_gateway_response')
    ->latest()
    ->first();

if ($completedPayment) {
    $responses['payment_callback'] = $completedPayment->payment_gateway_response;
}

// 3. Get RMP payment response
$rmpPayment = \App\Models\Payment::where('payment_method', 'mobile_money')
    ->whereJsonContains('payment_gateway_response->channel', 'mtn-gh')
    ->orWhereJsonContains('payment_gateway_response->channel', 'vodafone-gh')
    ->latest()
    ->first();

if ($rmpPayment) {
    $responses['rmp_payment'] = $rmpPayment->payment_gateway_response;
}

// Print all responses
echo json_encode($responses, JSON_PRETTY_PRINT);

// Save to file for easy sharing
file_put_contents(
    storage_path('app/hubtel_uat_responses.json'),
    json_encode($responses, JSON_PRETTY_PRINT)
);

echo "\n\nResponses saved to: storage/app/hubtel_uat_responses.json\n";
```

---

## 4. EXPORT ALL RESPONSES TO FILE

```php
// Create comprehensive response document
$uatResponses = [
    'generated_at' => now()->toIso8601String(),
    'environment' => config('app.env'),
    
    // Payment APIs
    'payment_apis' => [
        'online_checkout_initiation' => null,
        'online_checkout_callback_success' => null,
        'online_checkout_callback_failed' => null,
        'rmp_initiation' => null,
        'rmp_callback_success' => null,
        'rmp_callback_failed' => null,
        'status_verification' => null,
        'momo_verification' => null,
    ],
    
    // SMS APIs
    'sms_apis' => [
        'single_sms_response' => null,
        'batch_sms_response' => null,
    ],
];

// Collect payment responses
$payments = \App\Models\Payment::whereNotNull('payment_gateway_response')
    ->latest()
    ->take(20)
    ->get();

foreach ($payments as $payment) {
    $response = $payment->payment_gateway_response;
    
    // Categorize responses
    if (isset($response['checkoutUrl'])) {
        $uatResponses['payment_apis']['online_checkout_initiation'] = $response;
    }
    
    if (isset($response['ResponseCode']) && isset($response['Data']['CheckoutId'])) {
        if ($response['ResponseCode'] === '0000') {
            $uatResponses['payment_apis']['online_checkout_callback_success'] = $response;
        } else {
            $uatResponses['payment_apis']['online_checkout_callback_failed'] = $response;
        }
    }
    
    if (isset($response['TransactionId']) && isset($response['channel'])) {
        $uatResponses['payment_apis']['rmp_initiation'] = $response;
    }
    
    if (isset($response['rmp_callback'])) {
        if ($response['rmp_callback']['ResponseCode'] === '0000') {
            $uatResponses['payment_apis']['rmp_callback_success'] = $response['rmp_callback'];
        } else {
            $uatResponses['payment_apis']['rmp_callback_failed'] = $response['rmp_callback'];
        }
    }
    
    if (isset($response['status_check'])) {
        $uatResponses['payment_apis']['status_verification'] = $response['status_check'];
    }
}

// Save to file
$filename = 'hubtel_uat_all_responses_' . now()->format('Y-m-d_His') . '.json';
$filepath = storage_path('app/' . $filename);
file_put_contents($filepath, json_encode($uatResponses, JSON_PRETTY_PRINT));

echo "All UAT responses exported to: {$filepath}\n";
echo "You can download this file and share with Hubtel\n";
```

---

## 5. QUICK REFERENCE - Get Latest of Each Type

```php
// Quick command to get one sample of each API response type
echo "=== ONLINE CHECKOUT INITIATION ===\n";
$p1 = \App\Models\Payment::whereJsonContains('payment_gateway_response->checkoutUrl', null, 'not')
    ->latest()->first();
if ($p1) echo json_encode($p1->payment_gateway_response, JSON_PRETTY_PRINT) . "\n\n";

echo "=== ONLINE CHECKOUT CALLBACK ===\n";
$p2 = \App\Models\Payment::whereJsonContains('payment_gateway_response->ResponseCode', '0000')
    ->latest()->first();
if ($p2) echo json_encode($p2->payment_gateway_response, JSON_PRETTY_PRINT) . "\n\n";

echo "=== RMP PAYMENT ===\n";
$p3 = \App\Models\Payment::whereJsonContains('payment_gateway_response->channel', 'mtn-gh')
    ->orWhereJsonContains('payment_gateway_response->channel', 'vodafone-gh')
    ->latest()->first();
if ($p3) echo json_encode($p3->payment_gateway_response, JSON_PRETTY_PRINT) . "\n\n";

echo "=== STATUS VERIFICATION ===\n";
$p4 = \App\Models\Payment::whereJsonContains('payment_gateway_response->status_check', null, 'not')
    ->latest()->first();
if ($p4) echo json_encode($p4->payment_gateway_response['status_check'], JSON_PRETTY_PRINT) . "\n\n";
```

---

## 6. DOWNLOAD RESPONSES FROM SERVER

After running the export command above, download the file:

```bash
# From your local machine
scp your-server:/path/to/cedibites_api/storage/app/hubtel_uat_all_responses_*.json ./

# Or use SFTP
sftp your-server
get /path/to/cedibites_api/storage/app/hubtel_uat_all_responses_*.json
```

---

## Notes

1. **Replace phone numbers**: Use real Ghana mobile numbers in format `0XXXXXXXXX` or `233XXXXXXXXX`
2. **Production data**: These commands use real production data, so be careful
3. **Sensitive data**: The responses will contain real customer data - sanitize before sharing
4. **File location**: All exported files are saved to `storage/app/` directory
5. **Logs**: Check `storage/logs/laravel.log` for detailed API request/response logs

## Alternative: Check Logs Directly

```bash
# View recent Hubtel API logs
tail -n 500 storage/logs/laravel.log | grep -i "hubtel"

# View only payment initiation logs
tail -n 500 storage/logs/laravel.log | grep -i "hubtel payment"

# View only callback logs
tail -n 500 storage/logs/laravel.log | grep -i "hubtel callback"

# View only SMS logs
tail -n 500 storage/logs/laravel.log | grep -i "sms"
```

The logs contain the full request and response data for all Hubtel API calls.
    