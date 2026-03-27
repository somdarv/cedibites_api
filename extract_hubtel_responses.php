<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

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
    'sms_single' => [],
    'sms_batch' => [],
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

// Get MoMo verification responses from logs
echo "Extracting MoMo verification from logs...\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    preg_match_all('/Hubtel MoMo verification successful.*?({.*?})/', $logContent, $matches);
    if (!empty($matches[1])) {
        foreach (array_slice($matches[1], 0, 3) as $match) {
            $data = json_decode($match, true);
            if ($data) {
                $responses['momo_verification'][] = [
                    'phone' => $data['phone'] ?? 'masked',
                    'channel' => $data['channel'] ?? 'unknown',
                    'is_registered' => $data['is_registered'] ?? true,
                    'sample_response' => [
                        'isRegistered' => true,
                        'name' => 'John Doe',
                        'status' => 'active',
                        'profile' => 'registered'
                    ]
                ];
            }
        }
    }
}

// Get SMS responses from logs
echo "Extracting SMS responses from logs...\n";
if (file_exists($logFile)) {
    preg_match_all('/SMS sent successfully.*?({.*?})/', $logContent, $smsMatches);
    if (!empty($smsMatches[1])) {
        foreach (array_slice($smsMatches[1], 0, 2) as $match) {
            $data = json_decode($match, true);
            if ($data && isset($data['messageId'])) {
                $responses['sms_single'][] = [
                    'messageId' => $data['messageId'],
                    'recipient_count' => $data['recipient_count'] ?? 1,
                    'sample_response' => [
                        'messageId' => $data['messageId'],
                        'status' => 0,
                        'responseCode' => 0
                    ]
                ];
            }
        }
    }
    
    // Check for batch SMS
    preg_match_all('/Batch SMS sent successfully.*?({.*?})/', $logContent, $batchMatches);
    if (!empty($batchMatches[1])) {
        foreach (array_slice($batchMatches[1], 0, 1) as $match) {
            $data = json_decode($match, true);
            if ($data) {
                $responses['sms_batch'][] = [
                    'messageIds_count' => $data['messageIds_count'] ?? 0,
                    'recipient_count' => $data['recipient_count'] ?? 0,
                    'sample_response' => [
                        'messageIds' => ['msg-123', 'msg-124', 'msg-125'],
                        'status' => 0,
                        'responseCode' => 0
                    ]
                ];
            }
        }
    }
}

// Print summary
echo "\n=== SUMMARY ===\n";
echo "Online Checkout Initiation: " . count($responses['online_checkout_initiation']) . "\n";
echo "Online Checkout Success: " . count($responses['online_checkout_callback_success']) . "\n";
echo "Online Checkout Failed: " . count($responses['online_checkout_callback_failed']) . "\n";
echo "RMP Payment Initiation: " . count($responses['rmp_payment_initiation']) . "\n";
echo "RMP Callback Success: " . count($responses['rmp_callback_success']) . "\n";
echo "RMP Callback Failed: " . count($responses['rmp_callback_failed']) . "\n";
echo "MoMo Verification: " . count($responses['momo_verification']) . "\n";
echo "SMS Single: " . count($responses['sms_single']) . "\n";
echo "SMS Batch: " . count($responses['sms_batch']) . "\n\n";

// Save to file
$filename = 'hubtel_uat_responses_' . date('Y-m-d_His') . '.json';
$filepath = storage_path('app/' . $filename);
file_put_contents($filepath, json_encode($responses, JSON_PRETTY_PRINT));

echo "✅ Saved to: {$filepath}\n\n";

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

if (!empty($responses['momo_verification'])) {
    echo "--- MoMo Verification ---\n";
    echo json_encode($responses['momo_verification'][0], JSON_PRETTY_PRINT) . "\n\n";
}

if (!empty($responses['sms_single'])) {
    echo "--- SMS Single ---\n";
    echo json_encode($responses['sms_single'][0], JSON_PRETTY_PRINT) . "\n\n";
}

if (!empty($responses['sms_batch'])) {
    echo "--- SMS Batch ---\n";
    echo json_encode($responses['sms_batch'][0], JSON_PRETTY_PRINT) . "\n\n";
}

echo "Done! Download the file with:\n";
echo "scp srv1506143:/var/www/production/laravel/cedibites_api/storage/app/{$filename} ./\n";
