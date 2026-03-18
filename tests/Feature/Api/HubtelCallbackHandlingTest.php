<?php

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;

beforeEach(function () {
    // Set up Hubtel configuration for tests
    config([
        'services.hubtel.client_id' => 'test_client_id',
        'services.hubtel.client_secret' => 'test_client_secret',
        'services.hubtel.merchant_account_number' => 'test_merchant_account',
        'services.hubtel.base_url' => 'https://payproxyapi.hubtel.com',
        'services.hubtel.status_check_url' => 'https://api-txnstatus.hubtel.com',
    ]);
});

test('callback with success response updates payment to completed', function () {
    // Arrange
    $branch = Branch::factory()->create();
    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'order_number' => 'CB123456',
        'total_amount' => 100.00,
    ]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'payment_status' => 'pending',
        'amount' => 100.00,
        'paid_at' => null,
    ]);

    // Prepare callback payload with ResponseCode "0000" (success)
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'test-checkout-id-123',
            'SalesInvoiceId' => 'INV-789456',
            'ClientReference' => 'CB123456',
            'Status' => 'Paid',
            'Amount' => 100.00,
            'CustomerPhoneNumber' => '233244123456',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233244123456',
                'PaymentType' => 'mobilemoney',
                'Channel' => 'mtn-gh',
            ],
        ],
    ];

    // Act - Send callback to the endpoint (no authentication required)
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Assert
    $response->assertOk();
    $response->assertJson([
        'data' => null,
    ]);

    // Verify payment was updated to completed
    $payment->refresh();
    expect($payment->payment_status)->toBe('completed');
    expect($payment->paid_at)->not->toBeNull();
    expect($payment->payment_gateway_response)->toBeArray();
    expect($payment->payment_gateway_response['ResponseCode'])->toBe('0000');
    expect($payment->payment_gateway_response['Status'])->toBe('Success');
})->group('feature', 'hubtel', 'callback');

test('callback with failed response updates payment to failed', function () {
    // Arrange
    $branch = Branch::factory()->create();
    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'order_number' => 'CB789012',
        'total_amount' => 150.00,
    ]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'payment_status' => 'pending',
        'amount' => 150.00,
        'paid_at' => null,
    ]);

    // Prepare callback payload with ResponseCode "2001" (failed)
    $callbackPayload = [
        'ResponseCode' => '2001',
        'Status' => 'Failed',
        'Data' => [
            'CheckoutId' => 'test-checkout-id-456',
            'SalesInvoiceId' => 'INV-123789',
            'ClientReference' => 'CB789012',
            'Status' => 'Failed',
            'Amount' => 150.00,
            'CustomerPhoneNumber' => '233244987654',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233244987654',
                'PaymentType' => 'mobilemoney',
                'Channel' => 'vodafone-gh',
            ],
        ],
    ];

    // Act - Send callback to the endpoint
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Assert
    $response->assertOk();
    $response->assertJson([
        'data' => null,
    ]);

    // Verify payment was updated to failed
    $payment->refresh();
    expect($payment->payment_status)->toBe('failed');
    expect($payment->paid_at)->toBeNull(); // paid_at should remain null for failed payments
    expect($payment->payment_gateway_response)->toBeArray();
    expect($payment->payment_gateway_response['ResponseCode'])->toBe('2001');
    expect($payment->payment_gateway_response['Status'])->toBe('Failed');
})->group('feature', 'hubtel', 'callback');

test('callback for guest order updates payment correctly', function () {
    // Arrange
    $branch = Branch::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => null, // Guest order
        'branch_id' => $branch->id,
        'order_number' => 'CB999888',
        'total_amount' => 250.00,
        'contact_name' => 'Guest Customer',
        'contact_phone' => '233501234567',
    ]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'customer_id' => null, // Guest payment
        'payment_status' => 'pending',
        'amount' => 250.00,
        'paid_at' => null,
    ]);

    // Prepare callback payload with ResponseCode "0000" (success)
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'guest-checkout-999',
            'SalesInvoiceId' => 'INV-GUEST-999',
            'ClientReference' => 'CB999888', // Uses order_number for identification
            'Status' => 'Paid',
            'Amount' => 250.00,
            'CustomerPhoneNumber' => '233501234567',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233501234567',
                'PaymentType' => 'mobilemoney',
                'Channel' => 'mtn-gh',
            ],
        ],
    ];

    // Act - Send callback to the endpoint (no authentication required)
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Assert
    $response->assertOk();

    // Verify payment was updated to completed
    $payment->refresh();
    expect($payment->payment_status)->toBe('completed');
    expect($payment->paid_at)->not->toBeNull();
    expect($payment->customer_id)->toBeNull(); // Still null for guest
    expect($payment->payment_gateway_response)->toBeArray();
    expect($payment->payment_gateway_response['ResponseCode'])->toBe('0000');
    expect($payment->payment_gateway_response['Data']['ClientReference'])->toBe('CB999888');
})->group('feature', 'hubtel', 'callback', 'guest');

test('callback uses order_number for identification regardless of customer_id', function () {
    // Arrange - Create two orders with same order_number pattern but different IDs
    $branch = Branch::factory()->create();

    // Guest order
    $guestOrder = Order::factory()->create([
        'customer_id' => null,
        'branch_id' => $branch->id,
        'order_number' => 'CB777666',
        'total_amount' => 300.00,
        'contact_name' => 'Guest User',
        'contact_phone' => '233201111111',
    ]);

    $guestPayment = Payment::factory()->create([
        'order_id' => $guestOrder->id,
        'customer_id' => null,
        'payment_status' => 'pending',
        'amount' => 300.00,
    ]);

    // Prepare callback payload
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'checkout-777',
            'SalesInvoiceId' => 'INV-777',
            'ClientReference' => 'CB777666', // Matches order_number
            'Status' => 'Paid',
            'Amount' => 300.00,
            'CustomerPhoneNumber' => '233201111111',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233201111111',
                'PaymentType' => 'card',
                'Channel' => 'visa',
            ],
        ],
    ];

    // Act
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Assert
    $response->assertOk();

    // Verify the correct payment was updated using order_number
    $guestPayment->refresh();
    expect($guestPayment->payment_status)->toBe('completed');
    expect($guestPayment->payment_method)->toBe('card'); // Updated from callback
    expect($guestPayment->paid_at)->not->toBeNull();
})->group('feature', 'hubtel', 'callback', 'guest');

test('property: callback acknowledgment', function () {
    // **Property 13: Callback Acknowledgment**
    // **Validates: Requirements 4.8**
    // For any callback received from Hubtel, the endpoint SHALL respond with HTTP 200 status code

    $responseCodes = ['0000', '2001', '0005', '4000', '4070'];
    $statuses = ['Success', 'Paid', 'Unpaid', 'Failed', 'Refunded'];
    $paymentTypes = ['mobilemoney', 'card', 'wallet', 'ghqr', 'cash'];
    $channels = ['mtn-gh', 'vodafone-gh', 'airtel-gh', 'visa', 'mastercard', 'hubtel', 'g-money', 'zeepay'];

    // Run 100 iterations with randomized callback payloads
    for ($i = 0; $i < 100; $i++) {
        // Create order and payment with random data
        $branch = Branch::factory()->create();
        $order = Order::factory()->create([
            'branch_id' => $branch->id,
            'order_number' => 'CB'.fake()->numerify('######'),
            'total_amount' => fake()->randomFloat(2, 10, 10000),
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'payment_status' => 'pending',
            'amount' => $order->total_amount,
            'paid_at' => null,
        ]);

        // Generate random callback payload
        $callbackPayload = [
            'ResponseCode' => fake()->randomElement($responseCodes),
            'Status' => fake()->randomElement($statuses),
            'Data' => [
                'CheckoutId' => fake()->uuid(),
                'SalesInvoiceId' => 'INV-'.fake()->numerify('######'),
                'ClientReference' => $order->order_number,
                'Status' => fake()->randomElement($statuses),
                'Amount' => (float) $order->total_amount,
                'CustomerPhoneNumber' => '233'.fake()->numerify('#########'),
                'PaymentDetails' => [
                    'MobileMoneyNumber' => '233'.fake()->numerify('#########'),
                    'PaymentType' => fake()->randomElement($paymentTypes),
                    'Channel' => fake()->randomElement($channels),
                ],
            ],
        ];

        // Act - Send callback to the endpoint (no authentication required)
        $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

        // Assert - MUST respond with HTTP 200 regardless of callback content
        // This is the critical property: ANY callback receives HTTP 200 acknowledgment
        $response->assertOk(); // HTTP 200
        $response->assertJson([
            'data' => null,
        ]);
    }
})->group('property', 'hubtel', 'callback');

test('payment status changes are logged with activity log', function () {
    // Arrange
    $branch = Branch::factory()->create();
    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'order_number' => 'CB555444',
        'total_amount' => 200.00,
    ]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'payment_status' => 'pending',
        'payment_method' => 'mobile_money',
        'amount' => 200.00,
        'paid_at' => null,
    ]);

    // Prepare callback payload with ResponseCode "0000" (success)
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'test-checkout-555',
            'SalesInvoiceId' => 'INV-555444',
            'ClientReference' => 'CB555444',
            'Status' => 'Paid',
            'Amount' => 200.00,
            'CustomerPhoneNumber' => '233244555444',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233244555444',
                'PaymentType' => 'mobilemoney',
                'Channel' => 'mtn-gh',
            ],
        ],
    ];

    // Act - Send callback to trigger payment status change
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Assert
    $response->assertOk();

    // Verify payment was updated
    $payment->refresh();
    expect($payment->payment_status)->toBe('completed');

    // Verify activity log was created for the payment status change
    $activity = \Spatie\Activitylog\Models\Activity::inLog('payments')
        ->where('subject_type', Payment::class)
        ->where('subject_id', $payment->id)
        ->where('description', 'updated')
        ->first();

    expect($activity)->not->toBeNull();

    // Verify the activity log includes payment_status (the field that changed)
    // Note: With logOnlyDirty(), only changed fields are logged
    $properties = $activity->properties;
    expect($properties->has('attributes'))->toBeTrue();

    $attributes = $properties->get('attributes');
    expect($attributes)->toHaveKey('payment_status');

    // Verify the values are correct
    expect($attributes['payment_status'])->toBe('completed');

    // Verify old values are also logged
    expect($properties->has('old'))->toBeTrue();
    $oldAttributes = $properties->get('old');
    expect($oldAttributes['payment_status'])->toBe('pending');
})->group('feature', 'hubtel', 'activity-log');

test('failed payment status changes are logged', function () {
    // Arrange
    $branch = Branch::factory()->create();
    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'order_number' => 'CB333222',
        'total_amount' => 175.50,
    ]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'payment_status' => 'pending',
        'payment_method' => 'card',
        'amount' => 175.50,
        'paid_at' => null,
    ]);

    // Prepare callback payload with ResponseCode "2001" (failed)
    $callbackPayload = [
        'ResponseCode' => '2001',
        'Status' => 'Failed',
        'Data' => [
            'CheckoutId' => 'test-checkout-333',
            'SalesInvoiceId' => 'INV-333222',
            'ClientReference' => 'CB333222',
            'Status' => 'Failed',
            'Amount' => 175.50,
            'CustomerPhoneNumber' => '233244333222',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233244333222',
                'PaymentType' => 'card',
                'Channel' => 'visa',
            ],
        ],
    ];

    // Act - Send callback to trigger payment status change to failed
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Assert
    $response->assertOk();

    // Verify payment was updated to failed
    $payment->refresh();
    expect($payment->payment_status)->toBe('failed');

    // Verify activity log was created for the failed payment
    $activity = \Spatie\Activitylog\Models\Activity::inLog('payments')
        ->where('subject_type', Payment::class)
        ->where('subject_id', $payment->id)
        ->where('description', 'updated')
        ->first();

    expect($activity)->not->toBeNull();

    // Verify the activity log includes payment_status, amount, and payment_method
    // Note: With logOnlyDirty(), only changed fields are logged
    $properties = $activity->properties;
    expect($properties->has('attributes'))->toBeTrue();

    $attributes = $properties->get('attributes');
    expect($attributes['payment_status'])->toBe('failed');

    // Amount and payment_method may not be in attributes if they didn't change
    // The key requirement is that payment_status changes are logged
})->group('feature', 'hubtel', 'activity-log');
