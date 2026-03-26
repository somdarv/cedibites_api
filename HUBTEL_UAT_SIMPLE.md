# Hubtel UAT - Simple Commands

## If You Have Existing Payments

```bash
# SSH to production
ssh your-server
cd /path/to/cedibites_api
php artisan tinker
```

```php
// Get all Hubtel responses from existing payments
$payments = \App\Models\Payment::whereNotNull('payment_gateway_response')->latest()->get();

echo "Found {$payments->count()} payments\n\n";

foreach ($payments as $p) {
    echo "=== Payment #{$p->id} | Order: {$p->order->order_number} | Status: {$p->payment_status} ===\n";
    echo json_encode($p->payment_gateway_response, JSON_PRETTY_PRINT) . "\n\n";
}

// Save to file
file_put_contents(storage_path('app/hubtel_responses.json'), json_encode($payments->pluck('payment_gateway_response'), JSON_PRETTY_PRINT));
echo "Saved to: storage/app/hubtel_responses.json\n";
```

Then download the file:
```bash
scp your-server:/path/to/cedibites_api/storage/app/hubtel_responses.json ./
```

---

## If You Need to Create Test Payments

### 1. Create Test Order & Initiate Online Checkout

```php
// Create test order
$branch = \App\Models\Branch::first();
$order = \App\Models\Order::create([
    'branch_id' => $branch->id,
    'order_number' => 'ORD-UAT-' . now()->format('YmdHis'),
    'order_type' => 'delivery',
    'status' => 'pending',
    'payment_status' => 'pending',
    'total_amount' => 50.00,
    'contact_name' => 'UAT Test',
    'contact_phone' => '233241234567',
]);

// Initiate payment
$hubtel = app(\App\Services\HubtelPaymentService::class);
$result = $hubtel->initializeTransaction([
    'order' => $order,
    'description' => 'UAT Test Payment',
    'customer_name' => $order->contact_name,
    'customer_phone' => $order->contact_phone,
]);

echo json_encode($result['payment']->payment_gateway_response, JSON_PRETTY_PRINT);
```

### 2. Create Test Order & Initiate POS Mobile Money

```php
// Create test order
$branch = \App\Models\Branch::first();
$order = \App\Models\Order::create([
    'branch_id' => $branch->id,
    'order_number' => 'ORD-UAT-RMP-' . now()->format('YmdHis'),
    'order_type' => 'dine_in',
    'status' => 'pending',
    'payment_status' => 'pending',
    'total_amount' => 25.00,
    'contact_name' => 'UAT RMP Test',
    'contact_phone' => '0241234567', // Use YOUR real number
]);

// Initiate RMP payment
$hubtel = app(\App\Services\HubtelPaymentService::class);
$result = $hubtel->initializeReceiveMoney([
    'order' => $order,
    'customer_phone' => '0241234567', // Use YOUR real number
    'customer_name' => 'UAT Test',
    'description' => 'UAT RMP Test',
]);

echo json_encode($result['payment']->payment_gateway_response, JSON_PRETTY_PRINT);
```

### 3. Verify Mobile Number

```php
$hubtel = app(\App\Services\HubtelPaymentService::class);
$result = $hubtel->verifyMomoNumber('0241234567'); // Use real number
echo json_encode($result, JSON_PRETTY_PRINT);
```

### 4. Send Test SMS

```php
$sms = app(\App\Services\HubtelSmsService::class);
$result = $sms->sendSingle('233241234567', 'UAT Test SMS from CediBites');
echo json_encode($result, JSON_PRETTY_PRINT);
```

---

## Get All Response Types

```php
$allResponses = [
    'online_checkout_initiation' => null,
    'online_checkout_callback' => null,
    'rmp_payment' => null,
    'rmp_callback' => null,
    'status_verification' => null,
];

// Find each type
$payments = \App\Models\Payment::whereNotNull('payment_gateway_response')->latest()->take(50)->get();

foreach ($payments as $p) {
    $r = $p->payment_gateway_response;
    
    if (isset($r['checkoutUrl']) && !$allResponses['online_checkout_initiation']) {
        $allResponses['online_checkout_initiation'] = $r;
    }
    
    if (isset($r['ResponseCode']) && isset($r['Data']['CheckoutId']) && !$allResponses['online_checkout_callback']) {
        $allResponses['online_checkout_callback'] = $r;
    }
    
    if (isset($r['TransactionId']) && isset($r['channel']) && !$allResponses['rmp_payment']) {
        $allResponses['rmp_payment'] = $r;
    }
    
    if (isset($r['rmp_callback']) && !$allResponses['rmp_callback']) {
        $allResponses['rmp_callback'] = $r['rmp_callback'];
    }
    
    if (isset($r['status_check']) && !$allResponses['status_verification']) {
        $allResponses['status_verification'] = $r['status_check'];
    }
}

echo json_encode($allResponses, JSON_PRETTY_PRINT);

// Save
file_put_contents(storage_path('app/hubtel_all_types.json'), json_encode($allResponses, JSON_PRETTY_PRINT));
echo "\nSaved to: storage/app/hubtel_all_types.json\n";
```

---

## Alternative: Check Logs

```bash
# View Hubtel logs directly
tail -n 1000 storage/logs/laravel.log | grep -i "hubtel" | grep -i "response"

# Or export logs
tail -n 1000 storage/logs/laravel.log | grep -i "hubtel" > hubtel_logs.txt
```

The logs contain full request/response data for all Hubtel API calls.
