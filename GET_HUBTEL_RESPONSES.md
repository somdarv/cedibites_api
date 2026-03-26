# Get All Hubtel Responses - Simple Command

Based on your logs, you have real Hubtel data! Just run this in tinker:

```bash
php artisan tinker
```

Then paste this:

```php
// Get all payments with Hubtel responses
$payments = \App\Models\Payment::whereNotNull('payment_gateway_response')->get();

echo "Found {$payments->count()} payments with Hubtel data\n\n";

// Organize by type
$responses = [
    'online_checkout_initiation' => [],
    'online_checkout_callback_success' => [],
    'online_checkout_callback_failed' => [],
    'rmp_payment_initiation' => [],
    'rmp_callback_success' => [],
    'rmp_callback_failed' => [],
    'momo_verification' => [],
];

foreach ($payments as $p) {
    $r = $p->payment_gateway_response;
    
    // Online checkout initiation
    if (isset($r['checkoutUrl'])) {
        $responses['online_checkout_initiation'][] = $r;
    }
    
    // Online checkout callback
    if (isset($r['ResponseCode']) && isset($r['Data']['CheckoutId'])) {
        if ($r['ResponseCode'] === '0000') {
            $responses['online_checkout_callback_success'][] = $r;
        } else {
            $responses['online_checkout_callback_failed'][] = $r;
        }
    }
    
    // RMP initiation
    if (isset($r['TransactionId']) && isset($r['channel'])) {
        $responses['rmp_payment_initiation'][] = $r;
    }
    
    // RMP callback
    if (isset($r['rmp_callback'])) {
        if ($r['rmp_callback']['ResponseCode'] === '0000') {
            $responses['rmp_callback_success'][] = $r['rmp_callback'];
        } else {
            $responses['rmp_callback_failed'][] = $r['rmp_callback'];
        }
    }
}

// Print summary
echo "=== SUMMARY ===\n";
echo "Online Checkout Initiation: " . count($responses['online_checkout_initiation']) . "\n";
echo "Online Checkout Success: " . count($responses['online_checkout_callback_success']) . "\n";
echo "Online Checkout Failed: " . count($responses['online_checkout_callback_failed']) . "\n";
echo "RMP Payment Initiation: " . count($responses['rmp_payment_initiation']) . "\n";
echo "RMP Callback Success: " . count($responses['rmp_callback_success']) . "\n";
echo "RMP Callback Failed: " . count($responses['rmp_callback_failed']) . "\n\n";

// Save to file
$filename = 'hubtel_uat_responses_' . now()->format('Y-m-d_His') . '.json';
file_put_contents(storage_path('app/' . $filename), json_encode($responses, JSON_PRETTY_PRINT));

echo "✅ Saved to: storage/app/{$filename}\n\n";

// Print one sample of each type
echo "=== SAMPLE RESPONSES ===\n\n";

if (!empty($responses['online_checkout_initiation'])) {
    echo "--- Online Checkout Initiation ---\n";
    echo json_encode($responses['online_checkout_initiation'][0], JSON_PRETTY_PRINT) . "\n\n";
}

if (!empty($responses['online_checkout_callback_success'])) {
    echo "--- Online Checkout Callback (Success) ---\n";
    echo json_encode($responses['online_checkout_callback_success'][0], JSON_PRETTY_PRINT) . "\n\n";
}

if (!empty($responses['rmp_payment_initiation'])) {
    echo "--- RMP Payment Initiation ---\n";
    echo json_encode($responses['rmp_payment_initiation'][0], JSON_PRETTY_PRINT) . "\n\n";
}

if (!empty($responses['rmp_callback_success'])) {
    echo "--- RMP Callback (Success) ---\n";
    echo json_encode($responses['rmp_callback_success'][0], JSON_PRETTY_PRINT) . "\n\n";
}

echo "Done! Download the file with:\n";
echo "scp your-server:/var/www/production/laravel/cedibites_api/storage/app/{$filename} ./\n";
```

That's it! This will:
1. Extract all Hubtel responses from your database
2. Organize them by type
3. Save to a JSON file
4. Show you samples

Then download the file:
```bash
scp srv1506143:/var/www/production/laravel/cedibites_api/storage/app/hubtel_uat_responses_*.json ./
```

You'll have all the responses Hubtel needs for UAT!
